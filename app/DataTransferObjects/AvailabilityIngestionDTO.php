<?php

namespace App\DataTransferObjects;

class AvailabilityIngestionDTO
{
    /** @var RoomIngestionDTO[] */
    public readonly array $rooms;

    public function __construct(
        public readonly string $property_id,
        public readonly string $name,
        array $roomsData
    ) {
        $this->rooms = array_map(function ($roomData) {
            return new RoomIngestionDTO(
                room_id: $roomData['room_id'],
                date: $roomData['date'],
                max_guests: (int) $roomData['max_guests'],
                price: (float) $roomData['price']
            );
        }, $roomsData);
    }
}
