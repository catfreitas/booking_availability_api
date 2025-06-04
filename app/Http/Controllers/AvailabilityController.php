<?php

namespace App\Http\Controllers;


use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Exceptions\ConfigurationException;
use App\Services\DialogflowWebhookService;
use App\Services\AvailabilitySearchService;
use App\Exceptions\PropertyNotFoundException;
use App\Http\Requests\GetAvailabilityRequest;
use App\Services\AvailabilityIngestionService;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\DialogflowWebhookHandlerService;
use App\DataTransferObjects\AvailabilitySearchDTO;
use App\Exceptions\InvalidSearchParametersException;
use App\Http\Resources\PropertyAvailabilityResource;
use App\DataTransferObjects\AvailabilityIngestionDTO;
use App\Http\Requests\StoreAvailabilityIngestionRequest;


class AvailabilityController extends BaseApiController
{
    /**
     * Handles the ingestion of availability data.
     *
     * @param StoreAvailabilityIngestionRequest $request
     * @param AvailabilityIngestionService $ingestionService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(
        StoreAvailabilityIngestionRequest $request,
        AvailabilityIngestionService $ingestionService
    ) {
        try {

            $validatedData = $request->validated();

            $ingestionDto = new AvailabilityIngestionDTO(
                property_id: $validatedData['property_id'],
                name: $validatedData['name'],
                roomsData: $validatedData['rooms']
            );

            // Pass the DTO to the service
            $ingestionService->ingestData($ingestionDto);

            return response()->json(['message' => 'Availability data ingested successfully.'], 200);

        }  catch (Throwable $e) {
            return $this->generalErrorResponse($e, 'Failed to store availability. An internal error occurred.');
        }
    }

    public function index(GetAvailabilityRequest $request, AvailabilitySearchService $searchService)
    {
        $validatedParams = $request->validated();

        // Create DTO from validated request data
        $searchCriteria = new AvailabilitySearchDTO(
            property_id: $validatedParams['property_id'],
            check_in: $validatedParams['check_in'],
            check_out: $validatedParams['check_out'],
            guests: (int) $validatedParams['guests']
        );


        try {
            $availabilityResult = $searchService->findAvailableRooms($searchCriteria);
            return new PropertyAvailabilityResource($availabilityResult);

        } catch (PropertyNotFoundException $e) {
            Log::debug("Controller: Caught PropertyNotFoundException for property_id: " . $validatedParams['property_id']); // <<< ADD THIS
            return $this->notFoundResponse($e, $validatedParams['property_id']);
        } catch (InvalidSearchParametersException $e) {
            return $this->badRequestResponse($e, $validatedParams['property_id']);
        } catch (ConfigurationException $e) {
            return $this->serviceUnavailableResponse($e, 'Service temporarily unavailable due to a configuration issue. Please try again later.');
        } catch (Throwable $e) {
            return $this->generalErrorResponse($e, 'Failed to retrieve availability. An internal error occurred.', $validatedParams);
        }
    }


    public function handleAvailability(Request $request, DialogflowWebhookService $dialogflowService)
    {
        try {
            $dialogflowResponsePayload = $dialogflowService->handleRequest($request);
            return response()->json($dialogflowResponsePayload);
        } catch (Throwable $e) {
            Log::critical('FATAL ERROR in Dialogflow Webhook Controller: ' . $e->getMessage(), []);
            return $this->dialogflowErrorResponse($e, "I've encountered a critical system error. Please try again later.");
        }
    }
}
