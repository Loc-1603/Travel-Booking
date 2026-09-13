<?php

namespace App\Http\Requests\Api;

class TourProviderSearchRequest extends BaseApiRequest
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
            'province_id' => 'nullable|integer|exists:tour_provinces,id',
            'province_slug' => 'nullable|string|max:150',
            'from' => 'nullable|date|after_or_equal:today',
            'to' => 'nullable|date|after_or_equal:from',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'sort' => 'nullable|string|in:score,rating,price_low,price_high,name',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
