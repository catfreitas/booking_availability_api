<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'string', 'max:255'],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1'],
        ];
    }

        /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'property_id.required' => 'The property ID is required to check availability.',
            'check_in.required' => 'The check-in date is required.',
            'check_in.date_format' => 'The check-in date must be in YYYY-MM-DD format.',
            'check_in.after_or_equal' => 'The check-in date cannot be in the past.',
            'check_out.required' => 'The check-out date is required.',
            'check_out.date_format' => 'The check-out date must be in YYYY-MM-DD format.',
            'check_out.after' => 'The check-out date must be after the check-in date.',
            'guests.required' => 'The number of guests is required.',
            'guests.integer' => 'The number of guests must be an integer.',
            'guests.min' => 'At least one guest is required.',
        ];
    }
}
