<?php

namespace App\Repositories;

use App\Models\RoomAvailability;
use Illuminate\Database\Eloquent\Collection;

interface RoomAvailabilityRepository
{
    /**
     * Find a room availability record by its room ID and date and update it,
     * or create it if it doesn't exist.
     *
     * @param int $roomId The internal ID of the room
     * @param string $date The specific date of availability (e.g., "YYYY-MM-DD")
     * @param array $data Data for creating or updating the availability (e.g., ['price' => ..., 'max_guests' => ...])
     * @return RoomAvailability
     */
    public function updateOrCreateForRoomByDate(int $roomId, string $date, array $data): RoomAvailability;

    /**
     * Get availability records for a specific room for a list of dates.
     *
     * @param int $roomId
     * @param array $dateStrings Array of date strings (e.g., ['2025-07-15', '2025-07-16'])
     * @return Collection
     */
    public function getForRoomByDates(int $roomId, array $dateStrings): Collection;
}
