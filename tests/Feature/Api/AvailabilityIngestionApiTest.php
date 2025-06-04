<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Room;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AvailabilityIngestionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $ingestionApiUrl = '/api/availability-ingest';

    /**
     * Test: ingestion fails if the property_id is missing
     * @test
     */
    public function it_fails_ingestion_if_property_id_is_missing(): void
    {
        $invalidData = [
            'name' => 'Test Property Name',
            'rooms' => [
                [
                    'room_id' => 'R101',
                    'date' => '2025-10-01',
                    'max_guests' => 2,
                    'price' => 100.00,
                ]
            ]
        ];

        $response = $this->postJson($this->ingestionApiUrl, $invalidData);
        $response->assertStatus(422)->assertJsonValidationErrors(['property_id']);
    }

    /**
     * Test: ingestion fails if the property name is missing
     * @test
     */
    public function it_fails_ingestion_if_property_name_is_missing(): void
    {
        $invalidData = [
            'property_id' => 'PROP456',
            'rooms' => [
                [
                    'room_id' => 'R101',
                    'date' => '2025-10-01',
                    'max_guests' => 2,
                    'price' => 100.00,
                ]
            ]
        ];

        $response = $this->postJson($this->ingestionApiUrl, $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /**
     * Test: ingestion fails if the rooms array is missing
     * @test
     */
    public function it_fails_ingestion_if_rooms_array_is_missing(): void
    {
        $invalidData = [
            'property_id' => 'PROP456',
            'name' => 'Test Property Name',
        ];

        $response = $this->postJson($this->ingestionApiUrl, $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rooms']);
    }

    /**
     * Test: ingestion fails if a room_id is missing
     * @test
     */
    public function it_fails_ingestion_if_room_id_in_room_object_is_missing(): void
    {
        $invalidData = [
            'property_id' => 'PROP789',
            'name' => 'Another Test Property',
            'rooms' => [
                [
                    'date' => '2025-11-01',
                    'max_guests' => 2,
                    'price' => 150.00,
                ]
            ]
        ];

        $response = $this->postJson($this->ingestionApiUrl, $invalidData);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['rooms.0.room_id']);
    }

    /**
     * Test: successful ingestion with valid data and verify database
     * @test
     */
    public function it_successfully_ingests_valid_data_and_persists_it(): void
    {
        $validData = [
            'property_id' => '9999',
            'name' => 'Viana',
            'rooms' => [
                [
                    'room_id' => 'V101',
                    'date' => '2025-12-01',
                    'max_guests' => 2,
                    'price' => 150.75,
                ],
                [
                    'room_id' => 'V101',
                    'date' => '2025-12-02',
                    'max_guests' => 2,
                    'price' => 155.25,
                ],
                [
                    'room_id' => 'V202',
                    'date' => '2025-12-01',
                    'max_guests' => 3,
                    'price' => 210.00,
                ],
            ],
        ];

        //POST request
        $response = $this->postJson($this->ingestionApiUrl, $validData);

        //response
        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Availability data ingested successfully.'
                 ]);


        //Check properties table
        $this->assertDatabaseHas('properties', [
            'external_property_id' => '9999',
            'name' => 'Viana'
        ]);

        // Get the property created
        $property = Property::where('external_property_id', '9999')->first();
        $this->assertNotNull($property, "Property 9999 was not created.");

        if ($property) {
            // Check rooms/room_availabilites tables for V101
            $room101 = Room::where('property_id', $property->id)
                           ->where('external_room_id', 'V101')
                           ->first();
            $this->assertNotNull($room101, "Room V101 for property {$property->id} was not created.");

            if ($room101) {

                $this->assertDatabaseHas('room_availabilities', [
                    'room_id' => $room101->id,
                    'date' => '2025-12-01 00:00:00',
                    'price' => 150.75,
                    'max_guests' => 2,
                ]);
                $this->assertDatabaseHas('room_availabilities', [
                    'room_id' => $room101->id,
                    'date' => '2025-12-02 00:00:00',
                    'price' => 155.25,
                    'max_guests' => 2,
                ]);
            }

            // Check rooms/room_availabilites tables for V202
            $room202 = Room::where('property_id', $property->id)
                           ->where('external_room_id', 'V202')
                           ->first();
            $this->assertNotNull($room202, "Room V202 for property {$property->id} was not created.");

            if($room202) {

                $this->assertDatabaseHas('room_availabilities', [
                    'room_id' => $room202->id,
                    'date' => '2025-12-01 00:00:00',
                    'price' => 210.00,
                    'max_guests' => 3,
                ]);
            }
        }
    }
}
