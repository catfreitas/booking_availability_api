<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvailabilityIngestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // TODO: chang eit later
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
            'name' => ['required', 'string', 'max:255'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*.room_id' => ['required', 'string', 'max:255'],
            'rooms.*.date' => ['required', 'date_format:Y-m-d'],
            'rooms.*.max_guests' => ['required', 'integer', 'min:1'],
            'rooms.*.price' => ['required', 'numeric', 'min:0'],
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
            'property_id.required' => 'The property ID is required.',
            'property_id.string' => 'The property ID must be a string.',
            'property_id.max' => 'The property ID may not be greater than 255 characters.',

            'name.required' => 'The property name is required.',
            'name.string' => 'The property name must be a string.',
            'name.max' => 'The property name may not be greater than 255 characters.',

            'rooms.required' => 'The rooms data is required.',
            'rooms.array' => 'The rooms data must be an array.',
            'rooms.min' => 'At least one room entry is required in the rooms array.',

            'rooms.*.room_id.required' => 'The room ID is required for each room entry.',
            'rooms.*.room_id.string' => 'The room ID for each room entry must be a string.',
            'rooms.*.room_id.max' => 'The room ID for each room entry may not be greater than 255 characters.',

            'rooms.*.date.required' => 'The date is required for each room entry.',
            'rooms.*.date.date_format' => 'The date for each room entry must be in YYYY-MM-DD format.',

            'rooms.*.max_guests.required' => 'The max guests count is required for each room entry.',
            'rooms.*.max_guests.integer' => 'The max guests count for each room entry must be an integer.',
            'rooms.*.max_guests.min' => 'The max guests count for each room entry must be at least 1.',

            'rooms.*.price.required' => 'The price is required for each room entry.',
            'rooms.*.price.numeric' => 'The price for each room entry must be a numeric value.',
            'rooms.*.price.min' => 'The price for each room entry cannot be negative.',
        ];
    }
}
