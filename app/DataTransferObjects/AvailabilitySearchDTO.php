<?php

namespace App\DataTransferObjects;

class AvailabilitySearchDTO
{
    public function __construct(
        public readonly string $property_id,
        public readonly string $check_in,
        public readonly string $check_out,
        public readonly int $guests
    ) {}
}
