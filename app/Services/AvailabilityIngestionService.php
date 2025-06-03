<?php

namespace App\Services;

use App\DataTransferObjects\AvailabilityIngestionDTO; // Import the main DTO
use App\DataTransferObjects\RoomIngestionDTO;       // Import the room DTO
use App\Repositories\PropertyRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomAvailabilityRepository;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Keep for logging if any errors occur
use Throwable;

class AvailabilityIngestionService
{
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
     * Ingests availability data using DTO.
     *
     * @param AvailabilityIngestionDTO $ingestionData The DTO containing all ingestion data.
     * @return void
     * @throws \Throwable If any part of the ingestion process fails within the transaction.
     */
    public function ingestData(AvailabilityIngestionDTO $ingestionData): void
    {
        DB::transaction(function () use ($ingestionData) {
            $property = $this->processProperty($ingestionData->property_id, $ingestionData->name);

            foreach ($ingestionData->rooms as $roomDto) { // Loop through RoomIngestionDTO objects
                $room = $this->processRoom($property, $roomDto);
                $this->processRoomAvailability($room, $roomDto);
            }
        });
    }

    /**
     * Finds or creates a property.
     *
     * @param string $externalPropertyId
     * @return Property
     */
    private function processProperty(string $externalPropertyId, string $name): Property
    {
        // Using the externalPropertyId also as the name if no other name source is defined
        return $this->propertyRepository->updateOrCreateByExternalId(
            $externalPropertyId,
            ['name' => $name]
        );
    }

    /**
     * Finds or creates a room for a given property.
     *
     * @param Property $property
     * @param RoomIngestionDTO $roomDto
     * @return Room
     */
    private function processRoom(Property $property, RoomIngestionDTO $roomDto): Room
    {
        // Using the room_id (external_room_id) also as the name if not otherwise specified
        return $this->roomRepository->updateOrCreateForProperty(
            $property->id,
            $roomDto->room_id, // from DTO
            ['name' => $roomDto->room_id]
        );
    }

    /**
     * Creates or updates room availability.
     *
     * @param Room $room
     * @param RoomIngestionDTO $roomDto
     * @return void
     */
    private function processRoomAvailability(Room $room, RoomIngestionDTO $roomDto): void
    {
        $this->roomAvailabilityRepository->updateOrCreateForRoomByDate(
            $room->id,
            $roomDto->date,
            [
                'price' => $roomDto->price,
                'max_guests' => $roomDto->max_guests
            ]
        );
    }
}
