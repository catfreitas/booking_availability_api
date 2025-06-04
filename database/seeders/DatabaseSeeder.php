<?php

namespace Database\Seeders;

use Carbon\Carbon;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Room;
use App\Models\User;
use App\Models\Property;
use Illuminate\Database\Seeder;
use App\Models\RoomAvailability;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a default user for testing authentication
        /* User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]); */

        $this->call([
            PropertySeeder::class,
            RoomSeeder::class,
            RoomAvailabilitySeeder::class,
        ]);

            Property::factory()->count(2)->create()->each(function ($property) {
                Room::factory()->count(3)->create(['property_id' => $property->id])->each(function ($room) {

                    //Ensure unique dates
                    $startDate = Carbon::today()->addDay();
                    for ($i = 0; $i < 10; $i++) {
                        RoomAvailability::factory()->create([
                            'room_id' => $room->id,
                            'date' => $startDate->copy()->addDays($i)->toDateString(),
                        ]);
                    }
                });
        });
    }
}
