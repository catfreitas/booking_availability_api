<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'external_property_id' => 'PROP' . $this->faker->unique()->numerify('###'),
            'name' => $this->faker->company,
        ];
    }
}
