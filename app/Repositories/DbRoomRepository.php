<?php

namespace App\Repositories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

class DbRoomRepository implements RoomRepository
{
    public function updateOrCreateForProperty(int $propertyId, string $externalRoomId, array $data): Room
    {
        // Attributes to find the record by
        $findCriteria = [
            'property_id' => $propertyId,
            'external_room_id' => $externalRoomId,
        ];

        // Attributes to update with or use for creation
        // Eloquent's updateOrCreate will ensure property_id is set from $findCriteria if creating.
        // $data will contain other fields like 'name' if provided.
        return Room::updateOrCreate(
            $findCriteria,
            $data
        );
    }

    public function getForProperty(int $propertyId): Collection
    {
        return Room::where('property_id', $propertyId)->get();
    }
}
