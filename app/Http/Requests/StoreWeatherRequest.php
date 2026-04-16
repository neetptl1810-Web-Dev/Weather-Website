<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWeatherRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'city' => 'required|string|min:2|max:100|regex:/^[a-zA-Z\s\-]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'city.regex' => 'City name can only contain letters, spaces, and hyphens.',
        ];
    }
}