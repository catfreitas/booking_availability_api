<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Property; // Needed for property_id
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            // You'll usually associate this with a Property when using the factory
            // 'property_id' => Property::factory(), // Example if creating a new property too
            'external_room_id' => 'R' . $this->faker->numerify('###'),
            'name' => $this->faker->words(2, true) . ' Room',
        ];
    }
}
