<?php

namespace App\DataTransferObjects;

class AvailabilityResultDTO
{
    /** @var AvailableRoomDTO[] */
    public readonly array $rooms;

    public function __construct(
        public readonly string $property_id,
        array $roomsData,
        public readonly ?string $message = null
    ) {
        $this->rooms = array_map(function ($roomData) {
            return new AvailableRoomDTO(
                room_id: $roomData['room_id'],
                max_guests: $roomData['max_guests'],
                total_price: $roomData['total_price']
            );
        }, $roomsData);
    }
}
