<?php

namespace App\DataTransferObjects;

class AvailableRoomDTO
{
    public function __construct(
        public readonly string $room_id,
        public readonly int $max_guests,
        public readonly float $total_price // Or use string for precise monetary values if preferred
    ) {}
}
