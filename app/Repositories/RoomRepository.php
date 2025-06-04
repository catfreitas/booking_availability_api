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
     * @param int $propertyId
     * @param string $externalRoomId
     * @param array $data
     * @return Room
     */
    public function updateOrCreateForProperty(int $propertyId, string $externalRoomId, array $data): Room;
    public function getForProperty(int $propertyId): Collection;
}
