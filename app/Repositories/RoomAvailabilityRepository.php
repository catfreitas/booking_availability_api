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
     * @param int $roomId
     * @param string $date
     * @param array $data
     * @return RoomAvailability
     */
    public function updateOrCreateForRoomByDate(int $roomId, string $date, array $data): RoomAvailability;

    /**
     * Get availability records for a specific room for a list of dates.
     *
     * @param int $roomId
     * @param array $dateStrings
     * @return Collection
     */
    public function getForRoomByDates(int $roomId, array $dateStrings): Collection;
}
