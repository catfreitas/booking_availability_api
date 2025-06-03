<?php

namespace App\Services; // Assuming you named it DialogflowWebhookService

use App\DataTransferObjects\AvailabilitySearchDTO;
use App\DataTransferObjects\AvailabilityResultDTO;
use App\Services\AvailabilitySearchService;
use App\Exceptions\PropertyNotFoundException;
use App\Exceptions\InvalidSearchParametersException;
use App\Exceptions\DialogflowParameterException; // Our new exception
use RuntimeException as ConfigRuntimeException; // For cache config issues from SearchService
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class DialogflowWebhookService
{
    private AvailabilitySearchService $searchService;

    public function __construct(AvailabilitySearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Handles the incoming webhook request from Dialogflow and returns a Dialogflow response array.
     */
    public function handleRequest(Request $request): array
    {
        Log::debug('DialogflowWebhookService: Request Received by handleRequest', $request->all());
        $dialogflowParameters = $request->input('queryResult.parameters', []);
        $originalQueryText = $request->input('queryResult.queryText', 'their request');

        try {
            Log::debug('DialogflowWebhookService: Validating and preparing DTO...');
            $searchCriteriaDTO = $this->_validateAndPrepareSearchDTO($dialogflowParameters);
            Log::debug('DialogflowWebhookService: DTO Prepared. Calling AvailabilitySearchService...');


            /** @var AvailabilityResultDTO $availabilityResult */
            $availabilityResult = $this->searchService->findAvailableRooms($searchCriteriaDTO);
            Log::debug('DialogflowWebhookService: Result received from AvailabilitySearchService.', (array) $availabilityResult);

            $responseText = $this->_formatSuccessFulfillmentText(
                $availabilityResult,
                $searchCriteriaDTO->property_id,
                $searchCriteriaDTO->check_in,
                $searchCriteriaDTO->check_out,
                $searchCriteriaDTO->guests
            );
            $responsePayload = ['fulfillmentText' => $responseText];
            Log::info('DialogflowWebhookService: SUCCESS - Formatted fulfillmentText.', $responsePayload); // <<< LOG SUCCESS RESPONSE


        } catch (DialogflowParameterException $e) {
            Log::warning('DialogflowWebhookService: Parameter error - ' . $e->getMessage(), ['params' => $dialogflowParameters]);
            return ['fulfillmentText' => $e->getMessage()]; // Use exception message directly or a generic one
        } catch (PropertyNotFoundException $e) {
            Log::info('DialogflowWebhookService: Property not found - ' . $e->getMessage());
            return ['fulfillmentText' => "Sorry, I couldn't find any information for the property you mentioned."];
        } catch (InvalidSearchParametersException $e) {
            Log::info('DialogflowWebhookService: Invalid search parameters - ' . $e->getMessage());
            return ['fulfillmentText' => "It seems there's an issue with the dates or other search criteria. " . $e->getMessage()];
        } catch (ConfigRuntimeException $e) {
            Log::critical('DialogflowWebhookService: Critical service configuration error - ' . $e->getMessage());
            return ['fulfillmentText' => 'I\'m facing some technical difficulties with my configuration. Please try again later.'];
        } catch (Throwable $e) {
            Log::error('DialogflowWebhookService: Unhandled error - ' . $e->getMessage(), [
                'error_class' => get_class($e),
                'trace_snippet' => substr($e->getTraceAsString(), 0, 500), // Snippet to avoid huge logs
                'dialogflow_query' => $originalQueryText
            ]);
            $responsePayload = ['fulfillmentText' => 'I encountered an unexpected problem. Please try again in a moment.'];

        }
        Log::debug('DialogflowWebhookService: END handleRequest. Sending response to Dialogflow.', $responsePayload);
            return $responsePayload;
    }

    /**
     * Validates Dialogflow parameters and prepares the AvailabilitySearchDTO.
     * Throws DialogflowParameterException on failure.
     * @throws DialogflowParameterException
     */
    private function _validateAndPrepareSearchDTO(array $dialogflowParameters): AvailabilitySearchDTO
    {
        // Safely extract the first element if the parameter is an array
        $propertyId = is_array($dialogflowParameters['property_id'] ?? null) ? ($dialogflowParameters['property_id'][0] ?? null) : ($dialogflowParameters['property_id'] ?? null);
        $checkInDateStr = is_array($dialogflowParameters['check_in_date'] ?? null) ? ($dialogflowParameters['check_in_date'][0] ?? null) : ($dialogflowParameters['check_in_date'] ?? null);
        $checkOutDateStr = is_array($dialogflowParameters['check_out_date'] ?? null) ? ($dialogflowParameters['check_out_date'][0] ?? null) : ($dialogflowParameters['check_out_date'] ?? null);
        $guestsNum = is_array($dialogflowParameters['guests'] ?? null) ? ($dialogflowParameters['guests'][0] ?? null) : ($dialogflowParameters['guests'] ?? null);

        $dataToValidate = [
            'property_id' => $propertyId,
            'check_in_date' => $checkInDateStr,
            'check_out_date' => $checkOutDateStr,
            'guests' => $guestsNum,
        ];

        $validator = Validator::make($dataToValidate, [
            'property_id' => ['required', 'string', 'max:255'],
            'check_in_date' => ['required', 'string'],
            'check_out_date' => ['required', 'string'],
            'guests' => ['required', 'numeric', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            Log::warning('DialogflowWebhookService: Validation failed for Dialogflow parameters.', [
                'errors' => $validator->errors()->toArray(),
                'original_dialogflow_params' => $dialogflowParameters,
                'data_validated' => $dataToValidate
            ]);
            // Use the static factory method from your custom exception
            throw DialogflowParameterException::validationFailed($validator->errors());
        }

        // Now, attempt to parse/normalize and create the DTO
        try {
            $propertyIdForService = trim($dataToValidate['property_id']);
            if (empty($propertyIdForService)) {
                throw DialogflowParameterException::missingIdentifier('property identifier');
            }

            // Attempt to parse dates; Carbon throws an exception on invalid date strings
            $parsedCheckIn = Carbon::parse($dataToValidate['check_in_date'])->toDateString();
            $parsedCheckOut = Carbon::parse($dataToValidate['check_out_date'])->toDateString();

            return new AvailabilitySearchDTO(
                property_id: $propertyIdForService,
                check_in: $parsedCheckIn,
                check_out: $parsedCheckOut,
                guests: (int) $dataToValidate['guests']
            );
        } catch (Throwable $e) { // Catches errors from Carbon parsing or other issues
            Log::error('DialogflowWebhookService: Error formatting data or creating DTO after validation.', [
                'validated_data' => $dataToValidate,
                'original_exception_message' => $e->getMessage()
            ]);
            // Determine if it's a date parsing issue or something else
            if (str_contains(strtolower($e->getMessage()), 'date')) {
                throw DialogflowParameterException::dataFormattingError('dates provided');
            }
            // For other errors during this stage, throw a more generic parameter exception
            throw new DialogflowParameterException('There was an issue processing the details you provided. Please check their format.');
        }
    }

    /**
     * Formats the successful fulfillment text based on availability results.
     */
    private function _formatSuccessFulfillmentText(AvailabilityResultDTO $result, string $queryPropertyId, string $queryCheckIn, string $queryCheckOut, int $queryNumGuests): string
    {
        if (!empty($result->rooms)) {
            $roomCount = count($result->rooms);
            $minPrice = null;
            if ($roomCount > 0) {
                // Assuming $result->rooms is an array of AvailableRoomDTO objects
                $prices = array_map(fn($roomDto) => $roomDto->total_price, $result->rooms);
                if (!empty($prices)) {
                    $minPrice = min($prices);
                }
            }

            $responseText = "Yes! We have {$roomCount} room(s) available for property {$queryPropertyId} from {$queryCheckIn} to {$queryCheckOut} for {$queryNumGuests} guests.";
            if ($minPrice !== null) {
                $responseText .= " Prices start from \${$minPrice}.";
            }
            $responseText .= " Want to reserve now?";
            return $responseText;
        }

        return "Sorry, no rooms are available for property {$queryPropertyId} from {$queryCheckIn} to {$queryCheckOut} for {$queryNumGuests} guests at this time.";
    }
}
