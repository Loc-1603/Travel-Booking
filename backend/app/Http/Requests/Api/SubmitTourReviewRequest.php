<?php

namespace App\Http\Requests\Api;

class SubmitTourReviewRequest extends BaseApiRequest
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
            'tour_booking_id' => 'required|exists:tour_bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ];
    }
}
