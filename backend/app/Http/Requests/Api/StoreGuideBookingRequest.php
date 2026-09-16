<?php

namespace App\Http\Requests\Api;

/**
 * Đặt tour 1 ngày trực tiếp theo Guide (luồng mới).
 * Client gửi provider_uuid + travel_date (+ slot_id optional để tương thích
 * cũ), backend tự resolve slot trống sớm nhất của ngày đó và luôn tính
 * giá ngày x 1 ngày.
 */
class StoreGuideBookingRequest extends BaseApiRequest
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
            'provider_uuid' => 'required|string|exists:tour_providers,uuid',
            'slot_id' => 'nullable|exists:tour_availability_slots,id',
            'travel_date' => 'required|date|after_or_equal:today',
            'meeting_point' => 'nullable|string|max:500',
            'customer_notes' => 'nullable|string|max:2000',
            'coupon_code' => 'nullable|string|max:64',
            'currency' => 'nullable|string|size:3',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('slot_id')) {
                return;
            }
            $slot = \App\Models\TourAvailabilitySlot::with('tour.provider')->find($this->input('slot_id'));
            if (! $slot) {
                return;
            }

            $providerUuid = $this->input('provider_uuid');
            if (! $slot->tour
                || $slot->tour->status !== 'published'
                || ! $slot->tour->provider
                || $slot->tour->provider->uuid !== $providerUuid
                || $slot->tour->provider->status !== 'approved') {
                $validator->errors()->add('slot_id', 'The selected slot does not belong to this guide.');

                return;
            }

            if ($slot->date && $slot->date->isPast()) {
                $validator->errors()->add('slot_id', 'The selected slot is no longer available.');
            }

            $travelDate = $this->input('travel_date');
            if ($travelDate && $slot->date && $slot->date->format('Y-m-d') !== $travelDate) {
                $validator->errors()->add('travel_date', 'The selected slot is not on the travel date.');
            }
        });
    }
}
