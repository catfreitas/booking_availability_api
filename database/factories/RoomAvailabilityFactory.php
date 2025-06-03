<?php

namespace Database\Factories;

use App\Models\RoomAvailability;
use App\Models\Room; // Needed for room_id
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class RoomAvailabilityFactory extends Factory
{
    protected $model = RoomAvailability::class;

    public function definition(): array
    {
        return [
            // You'll usually associate this with a Room when using the factory
            // 'room_id' => Room::factory(), // Example if creating a new room too
            'date' => Carbon::today()->addDays($this->faker->numberBetween(1, 30))->toDateString(),
            'price' => $this->faker->randomFloat(2, 50, 500),
            'max_guests' => $this->faker->numberBetween(1, 4),
        ];
    }
}
