<?php

namespace App\Http\Requests\Api;

class StoreTourBookingRequest extends BaseApiRequest
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
        $maxHours = config('tour.max_hours', 12);
        $maxDays = config('tour.max_days', 30);

        return [
            'tour_id' => 'required|exists:tour_products,id',
            'slot_id' => 'required|exists:tour_availability_slots,id',
            'pricing_mode' => 'required|string|in:hour,day',
            'duration_value' => 'required|integer|min:1',
            'meeting_point' => 'nullable|string|max:500',
            'customer_notes' => 'nullable|string|max:2000',
            'coupon_code' => 'nullable|string|max:64',
            'currency' => 'nullable|string|size:3',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mode = $this->input('pricing_mode');
            $duration = (int) $this->input('duration_value', 0);
            if ($mode === 'hour' && $duration > config('tour.max_hours', 12)) {
                $validator->errors()->add('duration_value', 'Maximum '.config('tour.max_hours', 12).' hours per booking.');
            }
            if ($mode === 'day' && $duration > config('tour.max_days', 30)) {
                $validator->errors()->add('duration_value', 'Maximum '.config('tour.max_days', 30).' days per booking.');
            }

            // Slot must belong to the selected tour and not be in the past.
            $tourId = $this->input('tour_id');
            $slotId = $this->input('slot_id');
            if ($tourId && $slotId) {
                $slot = \App\Models\TourAvailabilitySlot::find($slotId);
                if ($slot && (int) $slot->tour_id !== (int) $tourId) {
                    $validator->errors()->add('slot_id', 'The selected slot does not belong to this tour.');
                }
                if ($slot && $slot->date && $slot->date->isPast()) {
                    $validator->errors()->add('slot_id', 'The selected slot is no longer available.');
                }
            }
        });
    }
}
