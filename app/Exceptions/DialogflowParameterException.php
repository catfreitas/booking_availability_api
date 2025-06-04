<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Support\MessageBag;


class DialogflowParameterException extends Exception
{
    public static function missingParameter(string $parameterName): static
    {
        return new static("Dialogflow parameter '{$parameterName}' is missing or invalid.");
    }

    public static function invalidDateFormat(string $dateValue): static
    {
       return new static("Invalid date format received from Dialogflow: {$dateValue}.");
    }

    /**
     * Create a new exception instance for general validation failures.
     *
     * @param MessageBag $errors
     * @param string $customMessage
     * @return static
     */
    public static function validationFailed(MessageBag $errors, string $customMessage = 'Some required details are missing or invalid.'): static
    {
        $exception = new static($customMessage);
        return $exception;
    }

    /**
     * Create a new exception for data formatting issues (e.g., dates).
     *
     * @param string $details
     * @return static
     */
    public static function dataFormattingError(string $details): static
    {
        return new static("I had trouble understanding some details, like the {$details}. Please try phrasing them clearly.");
    }

    /**
     * Create a new exception for a missing or empty critical identifier.
     *
     * @param string $identifierName Name of the identifier.
     * @return static
     */
    public static function missingIdentifier(string $identifierName = 'property identifier'): static
    {
        return new static("The {$identifierName} seems to be missing or empty. Could you please provide it?");
    }
}
