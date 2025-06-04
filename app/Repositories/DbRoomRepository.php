<?php

namespace App\Repositories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

class DbRoomRepository implements RoomRepository
{
    public function updateOrCreateForProperty(int $propertyId, string $externalRoomId, array $data): Room
    {
        $findInfo = [
            'property_id' => $propertyId,
            'external_room_id' => $externalRoomId,
        ];

        return Room::updateOrCreate(
            $findInfo,
            $data
        );
    }

    public function getForProperty(int $propertyId): Collection
    {
        return Room::where('property_id', $propertyId)->get();
    }
}
