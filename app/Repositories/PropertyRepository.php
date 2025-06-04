<?php

namespace App\Repositories;

use App\Models\Property;

interface PropertyRepository
{
    /**
     * Find a property by its external ID and update it, or create it if it doesn't exist.
     *
     * @param string $externalPropertyId
     * @param array $data
     * @return Property
     */
    public function updateOrCreateByExternalId(string $externalPropertyId, array $data): Property;

    public function findByExternalId(string $externalPropertyId): ?Property;

    public function findByName(string $name): ?Property;
}
