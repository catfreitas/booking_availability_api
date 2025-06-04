<?php

namespace App\Repositories;

use App\Models\RoomAvailability;
use Illuminate\Database\Eloquent\Collection;

class DbRoomAvailabilityRepository implements RoomAvailabilityRepository
{
    public function updateOrCreateForRoomByDate(int $roomId, string $date, array $data): RoomAvailability
    {

        $findInfo = [
            'room_id' => $roomId,
            'date' => $date,
        ];

        return RoomAvailability::updateOrCreate(
            $findInfo,
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
