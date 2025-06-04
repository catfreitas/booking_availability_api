<?php

namespace App\Services;

use App\DataTransferObjects\AvailabilitySearchDTO;
use App\DataTransferObjects\AvailabilityResultDTO;
use App\Services\AvailabilitySearchService;
use App\Exceptions\PropertyNotFoundException;
use App\Exceptions\InvalidSearchParametersException;
use App\Exceptions\DialogflowParameterException;
use RuntimeException as ConfigRuntimeException;
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
        $dialogflowParameters = $request->input('queryResult.parameters', []);
        $originalQueryText = $request->input('queryResult.queryText', 'their request');

        try {

            $searchCriteriaDTO = $this->_validateAndPrepareSearchDTO($dialogflowParameters);

            $availabilityResult = $this->searchService->findAvailableRooms($searchCriteriaDTO);

            $responseText = $this->_formatSuccessFulfillmentText(
                $availabilityResult,
                $searchCriteriaDTO->property_id,
                $searchCriteriaDTO->check_in,
                $searchCriteriaDTO->check_out,
                $searchCriteriaDTO->guests
            );
            $responsePayload = ['fulfillmentText' => $responseText];

        } catch (DialogflowParameterException $e) {
            return ['fulfillmentText' => $e->getMessage()];
        } catch (PropertyNotFoundException $e) {
            return ['fulfillmentText' => "Sorry, I couldn't find any information for the property you mentioned."];
        } catch (InvalidSearchParametersException $e) {
            return ['fulfillmentText' => "It seems there's an issue with the dates or other search criteria. " . $e->getMessage()];
        } catch (ConfigRuntimeException $e) {
            return ['fulfillmentText' => 'I\'m facing some technical difficulties with my configuration. Please try again later.'];
        } catch (Throwable $e) {
            Log::error('DialogflowWebhookService: Unhandled error - ' . $e->getMessage(), [
                'error_class' => get_class($e),
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

            throw DialogflowParameterException::validationFailed($validator->errors());
        }

        try {
            $propertyIdForService = trim($dataToValidate['property_id']);
            if (empty($propertyIdForService)) {
                throw DialogflowParameterException::missingIdentifier('property identifier');
            }

            $parsedCheckIn = Carbon::parse($dataToValidate['check_in_date'])->toDateString();
            $parsedCheckOut = Carbon::parse($dataToValidate['check_out_date'])->toDateString();

            return new AvailabilitySearchDTO(
                property_id: $propertyIdForService,
                check_in: $parsedCheckIn,
                check_out: $parsedCheckOut,
                guests: (int) $dataToValidate['guests']
            );
        } catch (Throwable $e) {
            Log::error('DialogflowWebhookService: Error formatting data or creating DTO after validation.', [
                'validated_data' => $dataToValidate,
                'original_exception_message' => $e->getMessage()
            ]);

            if (str_contains(strtolower($e->getMessage()), 'date')) {
                throw DialogflowParameterException::dataFormattingError('dates provided');
            }
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
