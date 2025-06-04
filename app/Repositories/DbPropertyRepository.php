<?php

namespace App\Repositories;

use App\Models\Property;

class DbPropertyRepository implements PropertyRepository
{
    public function updateOrCreateByExternalId(string $externalPropertyId, array $data): Property
    {
        return Property::updateOrCreate(
            ['external_property_id' => $externalPropertyId],
            $data
        );
    }

    public function findByExternalId(string $externalPropertyId): ?Property
    {
        return Property::where('external_property_id', $externalPropertyId)->first();
    }

    public function findByName(string $name): ?Property
    {
        // Case-insensitive search for the name (dialogflow sent lowercase)
        return Property::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }
}
