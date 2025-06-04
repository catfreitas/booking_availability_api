<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;
use Exception;

class BaseApiController extends Controller
{
    protected function notFoundResponse(Exception $exception, ?string $property_id = null, string $message = null, int $statusCode = 404): JsonResponse
    {
        $responseMessage = $message ?: $exception->getMessage();
        Log::info('Resource not found: ' . $responseMessage, ['property_id' => $property_id, 'exception_message' => $exception->getMessage()]);

        $data = ['message' => $responseMessage];

        if ($property_id && str_contains(strtolower($exception->getMessage()), 'property')) {
            $data['property_id'] = $property_id;
            $data['rooms'] = [];
        }
        return response()->json($data, $statusCode);
    }

    protected function badRequestResponse(Exception $exception, ?string $property_id = null, string $message = null, int $statusCode = 400): JsonResponse
    {
        $responseMessage = $message ?: $exception->getMessage();
        Log::warning('Bad request: ' . $responseMessage, ['property_id' => $property_id, 'exception_message' => $exception->getMessage()]);

        $data = ['message' => $responseMessage];

        if ($property_id && str_contains(strtolower($exception->getMessage()), 'date range')) {
            $data['property_id'] = $property_id;
            $data['rooms'] = [];
        }
        return response()->json($data, $statusCode);
    }

    protected function serviceUnavailableResponse(Exception $exception, string $message = 'Service temporarily unavailable.', int $statusCode = 503): JsonResponse
    {
        Log::critical('Service Unavailable: ' . $exception->getMessage(), ['exception_class' => get_class($exception)]);
        return response()->json(['message' => $message], $statusCode);
    }

    protected function generalErrorResponse(Throwable $exception, string $message = 'An unexpected error occurred.', ?array $contextParams = null, int $statusCode = 500): JsonResponse
    {
        Log::error('Controller General Error: ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'params' => $contextParams,
            'exception_class' => get_class($exception)
        ]);
        return response()->json(['message' => $message], $statusCode);
    }

     protected function successResponse($data, $message = null, $statusCode = 200): JsonResponse
     {
         $response = ['data' => $data];
         if ($message) {
             $response['message'] = $message;
         }
         return response()->json($response, $statusCode);
    }

     /**
     * Formats a JSON response for Dialogflow fulfillment errors.
     * Dialogflow usually expects a 200 OK response even for "errors"
     *
     * @param Throwable|null $exception
     * @param string|null $customMessage
     * @return JsonResponse
     */
    protected function dialogflowErrorResponse(Throwable $exception = null, string $customMessage = null): JsonResponse
    {
        $responseText = $customMessage ?: 'I\'m sorry, I encountered an unexpected issue while trying to help. Please try again in a moment.';

        if ($exception) {
            Log::error('Dialogflow Webhook Error: ' . $exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'exception_class' => get_class($exception)
            ]);
        } else {
            Log::warning('Dialogflow Webhook: Sending error fulfillmentText.', ['fulfillmentText' => $responseText]);
        }

        return response()->json([
            'fulfillmentText' => $responseText
        ]);
    }
}
