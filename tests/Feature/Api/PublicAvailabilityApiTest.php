<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Room;
use App\Models\User;
use App\Models\Property;
use App\Models\RoomAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse; // For type hinting
use Laravel\Sanctum\Sanctum;

class PublicAvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseApiUrl = '/api/availability';

    /**
     * Helper to get a default set of valid query parameters.
     */
    private function getValidQueryParams(array $overrides = []): array
    {
        return array_merge([
            'property_id' => 'TESTPROP1',
            'check_in' => Carbon::tomorrow()->toDateString(),
            'check_out' => Carbon::tomorrow()->addDays(3)->toDateString(),
            'guests' => 1,
        ], $overrides);
    }

    /**
     * Helper to perform an authenticated GET request and assert validation failure.
     */
    private function assertValidationFailure(array $queryParams, string|array $expectedErrorField): TestResponse
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $response = $this->getJson($this->baseApiUrl . '?' . http_build_query($queryParams));

        $response->assertStatus(422)
                 ->assertJsonValidationErrors($expectedErrorField);

        return $response; // Return response for further optional assertions like assertJsonPath
    }

    /** @test */
    public function it_returns_unauthenticated_error_if_no_token_is_provided(): void
    {
        $response = $this->getJson($this->baseApiUrl . '?' . http_build_query($this->getValidQueryParams()));
        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function it_returns_validation_error_if_property_id_is_missing(): void
    {
        $queryParams = $this->getValidQueryParams(['property_id' => '']); // Or omit it
        $this->assertValidationFailure($queryParams, 'property_id');
    }

    /** @test */
    public function it_returns_validation_error_if_check_in_date_is_missing(): void
    {
        $queryParams = $this->getValidQueryParams(['check_in' => '']);
        $this->assertValidationFailure($queryParams, 'check_in');
    }

    /** @test */
    public function it_returns_validation_error_if_check_out_date_is_missing(): void
    {
        $queryParams = $this->getValidQueryParams(['check_out' => '']);
        $this->assertValidationFailure($queryParams, 'check_out');
    }

    /** @test */
    public function it_returns_validation_error_if_guests_parameter_is_missing(): void
    {
        $queryParams = $this->getValidQueryParams(['guests' => '']);
        $this->assertValidationFailure($queryParams, 'guests');
    }

    /** @test */
    public function it_returns_validation_error_for_invalid_check_in_date_format(): void
    {
        $queryParams = $this->getValidQueryParams(['check_in' => 'invalid-date-format']);
        $this->assertValidationFailure($queryParams, 'check_in')
             ->assertJsonPath('errors.check_in.0', 'The check-in date must be in YYYY-MM-DD format.');
    }

    /** @test */
    public function it_returns_validation_error_for_invalid_check_out_date_format(): void
    {
        $queryParams = $this->getValidQueryParams(['check_out' => 'invalid-date-format']);
        $this->assertValidationFailure($queryParams, 'check_out')
             ->assertJsonPath('errors.check_out.0', 'The check-out date must be in YYYY-MM-DD format.');
    }

    /** @test */
    public function it_returns_validation_error_if_check_in_date_is_in_the_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-15'));

        $queryParams = $this->getValidQueryParams([
            'check_in' => Carbon::parse('2025-01-14')->toDateString(),
            'check_out' => Carbon::parse('2025-01-16')->toDateString(),
        ]);
        $this->assertValidationFailure($queryParams, 'check_in')
             ->assertJsonPath('errors.check_in.0', 'The check-in date cannot be in the past.');

        Carbon::setTestNow();
    }

    /** @test */
    public function it_returns_validation_error_if_check_out_date_is_not_after_check_in_date(): void
    {
        // Before check-in
        $queryParams = $this->getValidQueryParams([
            'check_in' => '2025-01-05',
            'check_out' => '2025-01-04',
        ]);
        $this->assertValidationFailure($queryParams, 'check_out')
             ->assertJsonPath('errors.check_out.0', 'The check-out date must be after the check-in date.');

         // Same as check-in
        $queryParamsSameDay = $this->getValidQueryParams([
            'check_in' => '2025-01-05',
            'check_out' => '2025-01-05',
        ]);
        $this->assertValidationFailure($queryParamsSameDay, 'check_out')
             ->assertJsonPath('errors.check_out.0', 'The check-out date must be after the check-in date.');
    }

    /** @test */
    public function it_returns_validation_error_if_guests_is_less_than_1(): void
    {
        $queryParams = $this->getValidQueryParams(['guests' => 0]);
        $this->assertValidationFailure($queryParams, 'guests')
             ->assertJsonPath('errors.guests.0', 'At least one guest is required.');
    }

    /** @test */
    public function it_returns_validation_error_if_guests_is_not_an_integer(): void
    {
        $queryParams = $this->getValidQueryParams(['guests' => 'abc']);
        $this->assertValidationFailure($queryParams, 'guests')
             ->assertJsonPath('errors.guests.0', 'The number of guests must be an integer.');
    }
}
