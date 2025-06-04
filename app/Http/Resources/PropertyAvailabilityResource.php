<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\DataTransferObjects\AvailableRoomDTO;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        Log::debug('PropertyAvailabilityResource toArray() received $this->resource:', (array) $this->resource);

        return [
            'property_id' => $this->resource->property_id,

            //Convert each AvailableRoomDTO to an array
            'rooms' => array_map(function (AvailableRoomDTO $roomDto) {
                return [
                    'room_id' => $roomDto->room_id,
                    'max_guests' => $roomDto->max_guests,
                    'total_price' => $roomDto->total_price,
                ];
            }, $this->resource->rooms),
        ];
    }
}
