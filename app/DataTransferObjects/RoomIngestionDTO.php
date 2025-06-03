<?php

namespace App\DataTransferObjects;

class RoomIngestionDTO
{
    public function __construct(
        public readonly string $room_id,
        public readonly string $date,
        public readonly int $max_guests,
        public readonly float $price    
    ) {}
}
