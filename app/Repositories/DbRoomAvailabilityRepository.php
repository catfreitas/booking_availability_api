<?php

namespace App\Repositories;

use App\Models\RoomAvailability;
use Illuminate\Database\Eloquent\Collection;
// Interface RoomAvailabilityRepository is in the same namespace

class DbRoomAvailabilityRepository implements RoomAvailabilityRepository
{
    public function updateOrCreateForRoomByDate(int $roomId, string $date, array $data): RoomAvailability
    {
        // Attributes to find the record by
        $findCriteria = [
            'room_id' => $roomId,
            'date' => $date,
        ];

        // Attributes to update with or use for creation (price, max_guests)
        // Eloquent's updateOrCreate will ensure room_id and date are set from $findCriteria if creating.
        return RoomAvailability::updateOrCreate(
            $findCriteria,
            $data
        );
    }

     public function getForRoomByDates(int $roomId, array $dateStrings): Collection
    {
        return RoomAvailability::where('room_id', $roomId)
                             ->whereIn('date', $dateStrings)
                             ->orderBy('date')
                             ->get();
    }
}
