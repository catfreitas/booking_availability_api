<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AvailabilityIngestionService;
use App\Repositories\PropertyRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomAvailabilityRepository;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\DataTransferObjects\AvailabilityIngestionDTO;
use App\DataTransferObjects\RoomIngestionDTO; // Used by AvailabilityIngestionDTO constructor
use Mockery;
use Mockery\MockInterface;

class AvailabilityIngestionServiceTest extends TestCase
{
    private PropertyRepository&MockInterface $propertyRepositoryMock;
    private RoomRepository&MockInterface $roomRepositoryMock;
    private RoomAvailabilityRepository&MockInterface $roomAvailabilityRepositoryMock;
    private AvailabilityIngestionService $ingestionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyRepositoryMock = Mockery::mock(PropertyRepository::class);
        $this->roomRepositoryMock = Mockery::mock(RoomRepository::class);
        $this->roomAvailabilityRepositoryMock = Mockery::mock(RoomAvailabilityRepository::class);
        $this->ingestionService = new AvailabilityIngestionService(
            $this->propertyRepositoryMock,
            $this->roomRepositoryMock,
            $this->roomAvailabilityRepositoryMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_successfully_ingests_valid_availability_data_using_dto(): void
    {
        $ingestionDto = $this->getCreateSampleIngestionDto();

        $mockedProperty = new Property(['name' => $ingestionDto->name]);
        $mockedProperty->external_property_id = $ingestionDto->property_id;
        $mockedProperty->id = 77;

        $mockedRoomR101 = new Room(['external_room_id' => 'R_CREATE_101']);
        $mockedRoomR101->id = 771;
        $mockedRoomR101->property_id = $mockedProperty->id;


        $mockedRoomR202 = new Room(['external_room_id' => 'R_CREATE_202']);
        $mockedRoomR202->id = 772;
        $mockedRoomR202->property_id = $mockedProperty->id;

        $this->expectPropertyCreateInteractions($ingestionDto, $mockedProperty);
        $this->expectRoomCreateInteractions($mockedProperty, $ingestionDto, $mockedRoomR101, $mockedRoomR202);
        $this->expectRoomAvailabilityCreateInteractions($mockedRoomR101, $mockedRoomR202, $ingestionDto);

        $this->ingestionService->ingestData($ingestionDto);
        $this->assertTrue(true);
    }

        /** @test */
    public function it_successfully_updates_existing_availability_data(): void
    {
        //Get DTO for update scenario
        $ingestionDto = $this->getUpdateSampleIngestionDto();

        // Prepare mock model instances
        $mockedProperty = new Property(['name' => $ingestionDto->name]);
        $mockedProperty->external_property_id = $ingestionDto->property_id;
        $mockedProperty->id = 1;

        $mockedRoom = new Room();
        $mockedRoom->id = 10;
        $mockedRoom->property_id = $mockedProperty->id;
        $mockedRoom->external_room_id = $ingestionDto->rooms[0]->room_id;

        $this->expectPropertyUpdateInteractions($ingestionDto, $mockedProperty);
        $this->expectRoomUpdateInteractions($mockedProperty, $ingestionDto->rooms[0], $mockedRoom);
        $this->expectRoomAvailabilityUpdateInteractions($mockedRoom, $ingestionDto->rooms[0]);

        $this->ingestionService->ingestData($ingestionDto);

        $this->assertTrue(true);
    }

    private function getCreateSampleIngestionDto(): AvailabilityIngestionDTO
    {
        $rawData = [
            'property_id' => 'PROP987',
            'name' => 'Hotel Viana Test Create',
            'rooms' => [
                ['room_id' => 'R_CREATE_101', 'date' => '2025-11-15', 'max_guests' => 2, 'price' => 110.00],
                ['room_id' => 'R_CREATE_101', 'date' => '2025-11-16', 'max_guests' => 2, 'price' => 115.50],
                ['room_id' => 'R_CREATE_202', 'date' => '2025-11-15', 'max_guests' => 3, 'price' => 190.00],
            ],
        ];
        return new AvailabilityIngestionDTO(
            property_id: $rawData['property_id'],
            name: $rawData['name'],
            roomsData: $rawData['rooms']
        );
    }

    private function expectPropertyCreateInteractions(AvailabilityIngestionDTO $dto, Property $returnedProperty): void
    {
        $this->propertyRepositoryMock
            ->shouldReceive('updateOrCreateByExternalId')
            ->once()
            ->with($dto->property_id, ['name' => $dto->name])
            ->andReturn($returnedProperty);
    }

    private function expectRoomCreateInteractions(Property $property, AvailabilityIngestionDTO $dto, Room $returnedRoom101, Room $returnedRoom202): void
    {
        $this->roomRepositoryMock
            ->shouldReceive('updateOrCreateForProperty')
            ->with($property->id, 'R_CREATE_101', [])
            ->twice()
            ->andReturn($returnedRoom101);

        $this->roomRepositoryMock
            ->shouldReceive('updateOrCreateForProperty')
            ->with($property->id, 'R_CREATE_202', [])
            ->once()
            ->andReturn($returnedRoom202);
    }

    private function expectRoomAvailabilityCreateInteractions(Room $room101, Room $room202, AvailabilityIngestionDTO $dto): void
    {
        $this->roomAvailabilityRepositoryMock
            ->shouldReceive('updateOrCreateForRoomByDate')
            ->with($room101->id, $dto->rooms[0]->date, ['price' => $dto->rooms[0]->price, 'max_guests' => $dto->rooms[0]->max_guests])
            ->once()->andReturn(new RoomAvailability(['id' => rand(1000, 1999)]));

        $this->roomAvailabilityRepositoryMock
            ->shouldReceive('updateOrCreateForRoomByDate')
            ->with($room101->id, $dto->rooms[1]->date, ['price' => $dto->rooms[1]->price, 'max_guests' => $dto->rooms[1]->max_guests])
            ->once()->andReturn(new RoomAvailability(['id' => rand(2000, 2999)]));

        $this->roomAvailabilityRepositoryMock
            ->shouldReceive('updateOrCreateForRoomByDate')
            ->with($room202->id, $dto->rooms[2]->date, ['price' => $dto->rooms[2]->price, 'max_guests' => $dto->rooms[2]->max_guests])
            ->once()->andReturn(new RoomAvailability(['id' => rand(3000, 3999)]));
    }

    private function getUpdateSampleIngestionDto(): AvailabilityIngestionDTO
    {
        return new AvailabilityIngestionDTO(
            property_id: 'EXISTING_PROP',
            name: 'Existing Hotel - Updated Name',
            roomsData: [
                [
                    'room_id' => 'EXISTING_R1',
                    'date' => '2025-12-01',
                    'max_guests' => 3,
                    'price' => 110.00,
                ]
            ]
        );
    }

    private function expectPropertyUpdateInteractions(AvailabilityIngestionDTO $dto, Property $returnedProperty): void
    {
        $this->propertyRepositoryMock
            ->shouldReceive('updateOrCreateByExternalId')
            ->once()
            ->with($dto->property_id, ['name' => $dto->name]) // Expects the updated name
            ->andReturn($returnedProperty);
    }

    private function expectRoomUpdateInteractions(Property $property, RoomIngestionDTO $roomDto, Room $returnedRoom): void
    {
        $this->roomRepositoryMock
            ->shouldReceive('updateOrCreateForProperty')
            ->once()
            ->with($property->id, $roomDto->room_id, []) // Assuming no other room attributes are updated from RoomIngestionDTO
            ->andReturn($returnedRoom);
    }

    private function expectRoomAvailabilityUpdateInteractions(Room $room, RoomIngestionDTO $roomDto): void
    {
        $this->roomAvailabilityRepositoryMock
            ->shouldReceive('updateOrCreateForRoomByDate')
            ->once()
            ->with($room->id, $roomDto->date, ['price' => $roomDto->price, 'max_guests' => $roomDto->max_guests])
            ->andReturn(new RoomAvailability(['id' => rand(4000,4999)])); // Return a mock/dummy instance
    }
}
