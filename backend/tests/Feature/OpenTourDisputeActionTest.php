<?php

use App\Actions\OpenTourDisputeAction;
use App\Enums\TourBookingStatus;
use App\Models\Country;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourDispute;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use RuntimeException;

beforeEach(function (): void {
    $this->customer = User::create([
        'name' => 'Dispute Customer', 'email' => 'tour-dispute-customer@test.local',
        'password' => bcrypt('password'), 'role' => 'customer', 'status' => 'active',
    ]);
    $vendor = User::create([
        'name' => 'Dispute Vendor', 'email' => 'tour-dispute-vendor@test.local',
        'password' => bcrypt('password'), 'role' => 'guide', 'status' => 'active',
    ]);
    $country = Country::create(['name' => 'Vietnam', 'code' => 'VN', 'tax_rate' => 0.08, 'tax_name' => 'VAT']);
    $province = TourProvince::create([
        'country_id' => $country->id, 'name' => 'Ha Giang', 'slug' => 'ha-giang-dispute',
        'description' => 'Test', 'is_featured' => true,
    ]);
    $provider = TourProvider::create([
        'vendor_id' => $vendor->id, 'business_name' => 'Dispute Guide', 'status' => 'approved',
    ]);
    $tour = TourProduct::create([
        'provider_id' => $provider->id, 'province_id' => $province->id,
        'title' => 'Dispute tour', 'base_fixed' => 200000,
        'base_price_hourly' => 100000, 'base_price_daily' => 800000,
        'status' => 'published',
    ]);
    $slot = TourAvailabilitySlot::create([
        'tour_id' => $tour->id, 'date' => Carbon::tomorrow()->toDateString(),
        'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'booked',
    ]);
    $this->booking = TourBooking::create([
        'customer_id' => $this->customer->id, 'tour_id' => $tour->id,
        'slot_id' => $slot->id, 'provider_id' => $provider->id,
        'province_id' => $province->id, 'start_at' => Carbon::tomorrow()->setTime(8, 0),
        'end_at' => Carbon::tomorrow()->setTime(10, 0), 'pricing_mode' => 'hour',
        'duration_value' => 2, 'base_fixed' => 200000, 'unit_price' => 100000,
        'subtotal' => 400000, 'total_price' => 500000, 'currency' => 'VND',
        'status' => TourBookingStatus::CONFIRMED->value,
    ]);
});

test('action opens a dispute and falls back to the user contact details', function (): void {
    $dispute = app(OpenTourDisputeAction::class)->handle($this->booking, $this->customer, [
        'contact_name' => '',
        'contact_email' => null,
        'contact_phone' => '0900000000',
        'customer_notes' => 'The guide never showed up.',
    ]);

    expect($dispute)->toBeInstanceOf(TourDispute::class)
        ->and($dispute->tour_booking_id)->toBe($this->booking->id)
        ->and($dispute->status)->toBe('open')
        ->and($dispute->contact_name)->toBe($this->customer->name)
        ->and($dispute->contact_email)->toBe($this->customer->email)
        ->and($dispute->customer_notes)->toBe('The guide never showed up.');
});

test('action rejects a pending payment booking', function (): void {
    $this->booking->update(['status' => TourBookingStatus::PENDING_PAYMENT->value]);

    expect(fn () => app(OpenTourDisputeAction::class)->handle($this->booking, $this->customer))
        ->toThrow(RuntimeException::class, 'Disputes are available after payment is completed.');

    expect(TourDispute::count())->toBe(0);
});

test('action propagates the unique constraint for a duplicate dispute', function (): void {
    $action = app(OpenTourDisputeAction::class);
    $action->handle($this->booking, $this->customer);

    expect(fn () => $action->handle($this->booking, $this->customer))
        ->toThrow(QueryException::class);
});
