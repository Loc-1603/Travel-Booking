<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;

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
            'max_price' => 'nullable|numeric|min:0',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'sort' => 'nullable|string|in:price_low,price_high,rating,name',
            'per_page' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $min = $this->input('min_price');
            $max = $this->input('max_price');
            if (is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                $validator->errors()->add('max_price', 'The max price must be greater than or equal to the min price.');
            }
        });
    }
}
