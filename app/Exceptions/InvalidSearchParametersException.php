<?php

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidSearchParametersException extends InvalidArgumentException
{
    /**
     * Create a new exception instance for an invalid date range.
     *
     * @return static
     */
    public static function invalidDateRange(): static
    {
        return new static("Invalid date range: number of nights must be positive. Ensure check-out is after check-in.");
    }

    /**
     * Create a new exception instance for an invalid number of guests
     *
     * @return static
     */
    public static function guestsMustBePositive(): static
    {
        return new static("Number of guests must be at least 1.");
    }
}
