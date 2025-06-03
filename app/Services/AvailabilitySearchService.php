<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Room;
use App\Models\Property;
use Carbon\CarbonPeriod;
use App\Traits\Cacheable;
use App\Repositories\RoomRepository;
use App\Repositories\PropertyRepository;
use App\Exceptions\ConfigurationException;
use App\Exceptions\PropertyNotFoundException;
use App\Repositories\RoomAvailabilityRepository;

use App\DataTransferObjects\AvailabilityResultDTO;
use App\DataTransferObjects\AvailabilitySearchDTO;
use App\Exceptions\InvalidSearchParametersException;
use Illuminate\Support\Facades\Log; // Used by Cacheable trait and potentially here

class AvailabilitySearchService
{
    use Cacheable;

    protected PropertyRepository $propertyRepository;
    protected RoomRepository $roomRepository;
    protected RoomAvailabilityRepository $roomAvailabilityRepository;

    public function __construct(
        PropertyRepository $propertyRepository,
        RoomRepository $roomRepository,
        RoomAvailabilityRepository $roomAvailabilityRepository
    ) {
        $this->propertyRepository = $propertyRepository;
        $this->roomRepository = $roomRepository;
        $this->roomAvailabilityRepository = $roomAvailabilityRepository;
    }

    /**
     * Main public method to find available rooms, utilizing caching.
     * Kept lean by delegating to getCachedOrFetchAvailability.
     */
    public function findAvailableRooms(AvailabilitySearchDTO $criteria): AvailabilityResultDTO
    {
        $dataFetcher = function () use ($criteria) {
            return $this->determineAvailabilityOutcome($criteria);
        };

        return $this->getCachedOrFetchAvailability($criteria, $dataFetcher);
    }

    /**
     * Handles retrieving data from cache or fetching it via the dataFetcher closure.
     * Also responsible for checking cache configuration.
     */
    private function getCachedOrFetchAvailability(AvailabilitySearchDTO $criteria, callable $dataFetcher): AvailabilityResultDTO
    {
        $keyPrefix = config('caching_settings.availability.key_prefix');
        $baseCacheTags = config('caching_settings.availability.base_tags');
        $specificTagPrefix = config('caching_settings.availability.property_tag_prefix');
        $ttlInSeconds = config('caching_settings.availability.ttl_seconds');

        if (!is_string($keyPrefix) || !is_array($baseCacheTags) || !is_string($specificTagPrefix) || !is_int($ttlInSeconds)) {
            Log::critical('Availability caching configuration is missing or invalid from config/caching_settings.php.');
            throw new ConfigurationException("Critical caching configuration is missing or invalid for availability search.");
        }

        $keyParams = (array) $criteria; // For cache key generation
        $specificTagIdentifier = $criteria->property_id;

        return $this->rememberWithTags(
            $keyPrefix,
            $keyParams,
            $baseCacheTags,
            $specificTagIdentifier,
            $specificTagPrefix,
            $ttlInSeconds,
            $dataFetcher
        );
    }

    /**
     * Orchestrates the availability search process (uncached logic).
     * Called by the $dataFetcher closure.
     */
    private function determineAvailabilityOutcome(AvailabilitySearchDTO $criteria): AvailabilityResultDTO
    {
        $searchContext = $this->getValidatedSearchContext($criteria);

        $availableRoomsData = $this->_collectAvailableRoomsData(
            $searchContext['property'],
            $searchContext['stayDateStrings'],
            $searchContext['numberOfNights'],
            $criteria->guests
        );

        return new AvailabilityResultDTO(
            property_id: $searchContext['property']->external_property_id,
            roomsData: $availableRoomsData
        );
    }

    /**
     * Prepares and validates primary inputs for the search.
     * Returns an array with 'property', 'stayDateStrings', 'numberOfNights'.
     * Throws exceptions if validation fails.
     */
    private function getValidatedSearchContext(AvailabilitySearchDTO $criteria): array
    {
        $checkInDate = Carbon::parse($criteria->check_in);
        $checkOutDate = Carbon::parse($criteria->check_out);

        // Attempt to find property by name first, then by external ID
        $property = $this->propertyRepository->findByName($criteria->property_id)
                     ?? $this->propertyRepository->findByExternalId($criteria->property_id);

        if (!$property) {
            throw PropertyNotFoundException::withIdentifier($criteria->property_id);
        }

        $stayDetails = $this->calculateStayDetails($checkInDate, $checkOutDate);
        if ($stayDetails['numberOfNights'] === 0) {
            throw InvalidSearchParametersException::invalidDateRange();
        }

        return [
            'property' => $property,
            'stayDateStrings' => $stayDetails['stayDateStrings'],
            'numberOfNights' => $stayDetails['numberOfNights'],
        ];
    }

    /**
     * Calculates stay dates and number of nights.
     */
    private function calculateStayDetails(Carbon $checkInDate, Carbon $checkOutDate): array
    {
        if ($checkOutDate->lte($checkInDate)) {
            return ['stayDateStrings' => [], 'numberOfNights' => 0];
        }
        $stayPeriod = CarbonPeriod::create($checkInDate, $checkOutDate->copy()->subDay());
        $stayDateStrings = [];
        foreach ($stayPeriod as $date) {
            $stayDateStrings[] = $date->toDateString();
        }
        return [
            'stayDateStrings' => $stayDateStrings,
            'numberOfNights' => count($stayDateStrings)
        ];
    }

    /**
     * Collects data for all available rooms for a given property and stay details.
     * Renamed from findAndProcessPropertyRooms for clarity.
     */
    private function _collectAvailableRoomsData(
        Property $property,
        array $stayDateStrings,
        int $numberOfNights,
        int $numberOfGuests
    ): array {
        $processedAvailableRooms = [];
        $rooms = $this->roomRepository->getForProperty($property->id);

        foreach ($rooms as $room) {
            $roomDetailsArray = $this->checkSingleRoomAvailability( // Renamed for clarity
                $room,
                $stayDateStrings,
                $numberOfNights,
                $numberOfGuests
            );

            if ($roomDetailsArray) {
                $processedAvailableRooms[] = $roomDetailsArray;
            }
        }
        return $processedAvailableRooms;
    }

    /**
     * Checks availability for a single room and returns its details as an array if available, or null.
     * Renamed from checkSingleRoomAvailabilityDetails for clarity.
     */
    private function checkSingleRoomAvailability(
        Room $room,
        array $stayDateStrings,
        int $numberOfNights,
        int $numberOfGuests
    ): ?array {
        $availabilityRecords = $this->roomAvailabilityRepository->getForRoomByDates(
            $room->id,
            $stayDateStrings
        );

        if ($availabilityRecords->count() !== $numberOfNights) {
            return null;
        }

        $totalPriceForRoom = 0;
        $roomEffectiveMaxGuests = PHP_INT_MAX;

        foreach ($availabilityRecords as $availabilityRecord) {
            if ($availabilityRecord->max_guests < $numberOfGuests) {
                return null;
            }
            $totalPriceForRoom += (float) $availabilityRecord->price;
            if ($availabilityRecord->max_guests < $roomEffectiveMaxGuests) {
                $roomEffectiveMaxGuests = $availabilityRecord->max_guests;
            }
        }

        return [
            'room_id' => $room->external_room_id,
            'max_guests' => $roomEffectiveMaxGuests,
            'total_price' => $totalPriceForRoom,
        ];
    }
}
