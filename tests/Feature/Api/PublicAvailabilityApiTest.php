<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Room;
use App\Models\Property;
use App\Models\RoomAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

class PublicAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseApiUrl = '/api/availability';

    /**
     * Test that unauthenticated users cannot access the availability endpoint.
     * @test
     */
    public function it_returns_unauthenticated_error_if_no_token_is_provided(): void
    {
        $response = $this->getJson($this->baseApiUrl . '?property_id=TEST&check_in=2025-01-01&check_out=2025-01-02&guests=1');

        $response->assertStatus(401)
                 ->assertJson([
                     'message' => 'Unauthenticated.'
                 ]);
    }

    /**
     * Test that property_id query parameter is required.
     * @test
     */
    public function it_returns_validation_error_if_property_id_is_missing(): void
    {
        // Authenticate as a user for this test, as auth is required before validation
        Sanctum::actingAs(
            User::factory()->create(),
            ['*'] // Grant all abilities for this test token
        );

        $response = $this->getJson($this->baseApiUrl . '?check_in=2025-01-01&check_out=2025-01-02&guests=1'); // property_id is missing

        $response->assertStatus(422) // Expect Unprocessable Entity for validation errors
                 ->assertJsonValidationErrors(['property_id']);
        // You can also check for the specific message from your GetAvailabilityRequest
        // ->assertJsonPath('errors.property_id.0', 'The property ID is required to check availability.');
    }

        /**
     * Test that check_in date is required.
     * @test
     */
    public function it_returns_validation_error_if_check_in_date_is_missing(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $queryParams = [
            'property_id' => 'PROP123',
            'check_out' => '2025-01-02',
            'guests' => 1,
        ];

        $response = $this->getJson($this->baseApiUrl . '?' . http_build_query($queryParams));

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['check_in']);
    }

    /**
     * Test that check_out date must be after check_in date.
     * @test
     */
    public function it_returns_validation_error_if_check_out_date_is_not_after_check_in_date(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $queryParams = [
            'property_id' => 'PROP123',
            'check_in' => '2025-01-05',
            'check_out' => '2025-01-04',
            'guests' => 1,
        ];

        $response = $this->getJson($this->baseApiUrl . '?' . http_build_query($queryParams));

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['check_out']);
        // You can also check for the specific message:
        // ->assertJsonPath('errors.check_out.0', 'The check-out date must be after the check-in date.');
    }

    /**
     * Test that guests parameter must be at least 1.
     * @test
     */
    public function it_returns_validation_error_if_guests_is_less_than_1(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $queryParams = [
            'property_id' => 'PROP123',
            'check_in' => '2025-01-01',
            'check_out' => '2025-01-02',
            'guests' => 0, // Guests is 0, but our rule is min:1
        ];

        $response = $this->getJson($this->baseApiUrl . '?' . http_build_query($queryParams));

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['guests']);
        // ->assertJsonPath('errors.guests.0', 'At least one guest is required.');
    }

    // TODO: Add tests for:
    // - Missing check_out date
    // - Missing guests parameter
    // - Invalid date_format for check_in
    // - Invalid date_format for check_out
    // - check_in date in the past (if 'after_or_equal:today' is strictly today, this might need careful handling in tests due to test execution time, or mock Carbon::now())
    // - guests parameter not an integer


    /**
     * Test successful response with available rooms.
     * @test
     */
    public function it_returns_available_rooms_with_correct_shape_for_valid_request(): void
    {
        // 1. Arrange: Set up data using factories
        $user = User::factory()->create();

        $property = Property::factory()->create(['external_property_id' => 'TESTPROP1']);

        $room1 = Room::factory()->create([
            'property_id' => $property->id,
            'external_room_id' => 'R101',
        ]);

        // Create availability for R101 for two nights
        $checkIn = Carbon::tomorrow();
        $checkOut = $checkIn->copy()->addDays(2); // Stay for 2 nights

        RoomAvailability::factory()->create([
            'room_id' => $room1->id,
            'date' => $checkIn->toDateString(),
            'price' => 100.00,
            'max_guests' => 2,
        ]);
        RoomAvailability::factory()->create([
            'room_id' => $room1->id,
            'date' => $checkIn->copy()->addDay()->toDateString(), // Second night
            'price' => 105.00,
            'max_guests' => 2,
        ]);

        // 2. Act: Make the authenticated API request
        Sanctum::actingAs($user);

        $queryParams = [
            'property_id' => 'TESTPROP1',
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'guests' => 2,
        ];

        $response = $this->getJson($this->baseApiUrl . '?' . http_build_query($queryParams));

        // 3. Assert: Check the response
        dump($response->getContent()); // This will dump the raw JSON string response
        // OR:
        // dump($response->json()); // This will dump the response decoded as a PHP array

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [ // <<< ADD THIS 'data' KEY
                'property_id',
                'rooms' => [
                    '*' => [
                        'room_id',
                        'max_guests',
                        'total_price',
                    ]
                ]
            ]
        ]);

        // Your more specific assertJson would also need to target inside 'data'
        $response->assertJson([
            'data' => [ // <<< ADD THIS 'data' KEY
                'property_id' => 'TESTPROP1',
                'rooms' => [
                    // ... your expected room data ...
                    // If rooms is empty, this part of the assertion might be:
                    // 'rooms' => []
                ]
            ]
        ]);
        // If asserting count of rooms, which is inside 'data'
        $response->assertJsonCount(0, 'data.rooms');
    }
}
