<?php

namespace App\Http\Requests\Api;

class StoreTourMessageRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => 'required|string|max:2000',
        ];
    }
}
