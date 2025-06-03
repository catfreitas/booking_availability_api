<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AvailabilityIngestionService;
use App\Repositories\PropertyRepository;    // Interface
use App\Repositories\RoomRepository;         // Interface
use App\Repositories\RoomAvailabilityRepository; // Interface
use App\Models\Property;                     // Eloquent Model
use App\Models\Room;                         // Eloquent Model
use App\Models\RoomAvailability;             // Eloquent Model
use Mockery;
use Mockery\MockInterface;
use Illuminate\Support\Facades\DB; // To potentially mock DB::transaction if needed, though we test through it here.

class AvailabilityIngestionServiceTest extends TestCase
{
    private PropertyRepository&MockInterface $propertyRepositoryMock;
    private RoomRepository&MockInterface $roomRepositoryMock;
    private RoomAvailabilityRepository&MockInterface $roomAvailabilityRepositoryMock;
    private AvailabilityIngestionService $ingestionService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for our repository dependencies
        $this->propertyRepositoryMock = Mockery::mock(PropertyRepository::class);
        $this->roomRepositoryMock = Mockery::mock(RoomRepository::class);
        $this->roomAvailabilityRepositoryMock = Mockery::mock(RoomAvailabilityRepository::class);

        // Instantiate the service with the mocked dependencies
        $this->ingestionService = new AvailabilityIngestionService(
            $this->propertyRepositoryMock,
            $this->roomRepositoryMock,
            $this->roomAvailabilityRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close(); // Important to close Mockery expectations after each test
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_successfully_ingests_valid_availability_data(): void
    {
        // 1. Prepare sample validated data
        $validatedData = [
            'property_id' => 'PROP123',
            'rooms' => [
                [ // First entry for R101
                    'room_id' => 'R101',
                    'date' => '2025-10-01',
                    'max_guests' => 2,
                    'price' => 100.00,
                ],
                [ // Second entry for R101 (e.g., different date)
                    'room_id' => 'R101',
                    'date' => '2025-10-02',
                    'max_guests' => 2,
                    'price' => 105.00,
                ],
                [ // Entry for R202
                    'room_id' => 'R202',
                    'date' => '2025-10-01',
                    'max_guests' => 4,
                    'price' => 180.00,
                ],
            ],
        ];

        // 2. Define expectations for the mocked repositories

        // --- Property Mock ---
        // Simulate the property object that will be returned by the repository
        $mockedProperty = new Property(['name' => 'PROP123']); // Using a real model instance (not saved)
        $mockedProperty->external_property_id = 'PROP123';
        $mockedProperty->id = 1; // Crucial: ensure it has an ID for subsequent FK use

        $this->propertyRepositoryMock
            ->shouldReceive('updateOrCreateByExternalId')
            ->once()
            ->with('PROP123', ['name' => 'PROP123']) // Service passes name as external_id if not otherwise specified
            ->andReturn($mockedProperty);

        // --- Room Mocks ---
        // Simulate room objects to be returned
        $mockedRoomR101 = new Room(['name' => 'R101']);
        $mockedRoomR101->id = 10;
        $mockedRoomR101->property_id = $mockedProperty->id;
        $mockedRoomR101->external_room_id = 'R101';

        $mockedRoomR202 = new Room(['name' => 'R202']);
        $mockedRoomR202->id = 20;
        $mockedRoomR202->property_id = $mockedProperty->id;
        $mockedRoomR202->external_room_id = 'R202';

        // Expectation for R101 (will be called twice by the service due to two entries in $validatedData)
        $this->roomRepositoryMock
            ->shouldReceive('updateOrCreateForProperty')
            ->with($mockedProperty->id, 'R101', ['name' => 'R101'])
            ->twice() // Corrected: Expect this call twice
            ->andReturn($mockedRoomR101); // Return the same stable mocked Room instance for R101

        // Expectation for R202
        $this->roomRepositoryMock
            ->shouldReceive('updateOrCreateForProperty')
            ->with($mockedProperty->id, 'R202', ['name' => 'R202'])
            ->once()
            ->andReturn($mockedRoomR202);

        // --- RoomAvailability Mocks ---
        // We need to simulate RoomAvailability instances being returned as well,
        // even if the service doesn't directly use their return values after creation,
        // because Eloquent's updateOrCreate will instantiate them.
        $this->roomAvailabilityRepositoryMock
            ->shouldReceive('updateOrCreateForRoomByDate')
            ->with($mockedRoomR101->id, '2025-10-01', ['price' => 100.00, 'max_guests' => 2])
            ->once()
            ->andReturn(new RoomAvailability(['id' => 1001, 'room_id' => $mockedRoomR101->id, 'date' => '2025-10-01', 'price' => 100.00, 'max_guests' => 2]));

        $this->roomAvailabilityRepositoryMock
            ->shouldReceive('updateOrCreateForRoomByDate')
            ->with($mockedRoomR101->id, '2025-10-02', ['price' => 105.00, 'max_guests' => 2])
            ->once()
            ->andReturn(new RoomAvailability(['id' => 1002, 'room_id' => $mockedRoomR101->id, 'date' => '2025-10-02', 'price' => 105.00, 'max_guests' => 2]));

        $this->roomAvailabilityRepositoryMock
            ->shouldReceive('updateOrCreateForRoomByDate')
            ->with($mockedRoomR202->id, '2025-10-01', ['price' => 180.00, 'max_guests' => 4])
            ->once()
            ->andReturn(new RoomAvailability(['id' => 1003, 'room_id' => $mockedRoomR202->id, 'date' => '2025-10-01', 'price' => 180.00, 'max_guests' => 4]));

        // 3. Call the service method
        // The service uses DB::transaction internally. We are testing the service's interaction
        // with its mocked repositories within that transaction's scope.
        $this->ingestionService->ingestData($validatedData);

        // 4. Assertions: Mockery's `shouldReceive` expectations serve as assertions.
        // If tearDown() completes without Mockery throwing an exception, all expectations were met.
        $this->assertTrue(true); // A simple assertion to ensure the test ran to this point.
    }
}
