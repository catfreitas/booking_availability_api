<?php

namespace App\Repositories;

use App\Models\Property;
// No 'use App\Repositories\PropertyRepository;' needed here if both are in the same App\Repositories namespace
// and the 'implements PropertyRepository' below refers to the interface in the same namespace.

class DbPropertyRepository implements PropertyRepository
{
    public function updateOrCreateByExternalId(string $externalPropertyId, array $data): Property
    {
        return Property::updateOrCreate(
            ['external_property_id' => $externalPropertyId],
            $data
        );
    }

    public function findByExternalId(string $externalPropertyId): Property
    {
        return Property::where('external_property_id', $externalPropertyId)->first();
    }

    public function findByName(string $name): ?Property
    {
        // Case-insensitive search for the name
        return Property::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
        // Or if your database collation is already case-insensitive for that column:
        // return Property::where('name', $name)->first();
    }
}
