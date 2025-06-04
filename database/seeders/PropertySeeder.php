<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Property;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        Property::factory()->create([
            'external_property_id' => '1001',
            'name' => 'Sunshine',
        ]);

        Property::factory()->create([
            'external_property_id' => '1002',
            'name' => 'Mountain',
        ]);
    }
}
