<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyAvailabilityResource extends JsonResource
{
     /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        Log::debug('PropertyAvailabilityResource toArray() received $this->resource:', (array) $this->resource);

        return [
            'property_id' => $this->resource['property_id'] ?? 'DEBUG_PROPERTY_ID_WAS_NULL_OR_MISSING_IN_RESOURCE',
            'rooms'       => $this->resource['rooms'] ?? [],
        ];
    }
}
