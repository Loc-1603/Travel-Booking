<?php

namespace App\Http\Requests\Api;

class TourSearchRequest extends BaseApiRequest
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
            'attraction_id' => 'nullable|integer|exists:tour_attractions,id',
            'date_from' => 'nullable|date|after_or_equal:today',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'hours' => 'nullable|integer|min:1|max:12',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0|gte:min_price',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'sort' => 'nullable|string|in:price_low,price_high,rating,name',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
