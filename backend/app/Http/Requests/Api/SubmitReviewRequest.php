<?php

namespace App\Http\Requests\Api;

class SubmitReviewRequest extends BaseApiRequest
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
            'booking_id' => 'nullable|integer|exists:bookings,id|required_without:tour_booking_id',
            'tour_booking_id' => 'nullable|integer|exists:tour_bookings,id|required_without:booking_id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            // Ảnh minh hoạ: tối đa 5 ảnh, mỗi ảnh ≤ 4MB.
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ];
    }
}
