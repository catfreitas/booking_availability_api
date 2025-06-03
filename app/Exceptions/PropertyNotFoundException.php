<?php

namespace App\Exceptions;

use Exception;

class PropertyNotFoundException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $propertyId
     * @return static
     */
    public static function withIdentifier(string $propertyId): static
    {
        return new static("Property ID: '{$propertyId}' not found.");
    }
}
