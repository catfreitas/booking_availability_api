<?php

namespace App\Repositories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

interface RoomRepository
{
    /**
     * Find a room by its property ID and external room ID and update it,
     * or create it if it doesn't exist.
     *
     * @param int $propertyId The internal ID of the property
     * @param string $externalRoomId The external ID of the room (e.g., "r1")
     * @param array $data Data for creating or updating the room (e.g., ['name' => ...])
     * @return Room
     */
    public function updateOrCreateForProperty(int $propertyId, string $externalRoomId, array $data): Room;
    public function getForProperty(int $propertyId): Collection;
}
