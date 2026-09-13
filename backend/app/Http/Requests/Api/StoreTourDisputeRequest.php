<?php

namespace App\Http\Requests\Api;

class StoreTourDisputeRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tour requires login: contact defaults to the authenticated user,
     * so all fields are nullable overrides (unlike Hotel guest flow).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_notes' => ['required', 'string', 'min:20', 'max:10000'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
