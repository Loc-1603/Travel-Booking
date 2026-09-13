<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AmenityController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\HotelSearchController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SavedTourController;
use App\Http\Controllers\Api\V1\TourMessageController;
use App\Http\Controllers\Api\V1\TourProviderController;
use App\Http\Controllers\Api\V1\TourReviewController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\TourAvailabilityController;
use App\Http\Controllers\Api\V1\TourBookingController;
use App\Http\Controllers\Api\V1\TourProvinceController;
use App\Http\Controllers\Api\V1\TourSearchController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VNPay IPN: server-to-server callback (no auth, checksum verified).
| Frontend return page is separate (VNPAY_RETURN_URL, no trust for confirmation).
|--------------------------------------------------------------------------
*/
Route::get('/v1/payments/vnpay-ipn', [\App\Http\Controllers\Api\V1\VnpayIpnController::class, '__invoke'])->name('api.v1.payments.vnpay-ipn');
Route::get('/v1/payments/tour-vnpay-ipn', [\App\Http\Controllers\Api\V1\TourVnpayIpnController::class, '__invoke'])->name('api.v1.payments.tour-vnpay-ipn');

/*
|--------------------------------------------------------------------------
| API v1 Routes (Customer-facing)
|--------------------------------------------------------------------------
|
| All customer-facing API under version prefix. Use Sanctum for token-based
| auth from React/SPA.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/ping', fn () => response()->json(['ok' => true, 'version' => 'v1']))->name('ping');
    Route::get('/website-settings', [\App\Http\Controllers\Api\V1\WebsiteSettingsController::class, 'index'])->name('website-settings.index');

    // Auth (no token)
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register/vendor', [AuthController::class, 'registerVendor'])->name('register.vendor');

    // Hotel search & single hotel (no auth)
    Route::get('/hotels', [HotelSearchController::class, 'index'])->name('hotels.index');
    Route::get('/hotels/{id}/review-sentiment', [AiController::class, 'reviewSentiment'])
        ->whereNumber('id')
        ->middleware('throttle:40,1')
        ->name('hotels.review-sentiment');
    Route::get('/hotels/{id}', [HotelSearchController::class, 'show'])->name('hotels.show');

    // AI features (optional auth on recommendations via Bearer token)
    Route::get('/ai/recommendations', [AiController::class, 'recommendations'])
        ->middleware(['auth.optional', 'throttle:30,1'])
        ->name('ai.recommendations');
    Route::post('/ai/chat', [AiController::class, 'chat'])
        ->middleware('throttle:20,1')
        ->name('ai.chat');

    // Geocode autocomplete for map search (throttled)
    Route::get('/geocode/autocomplete', [\App\Http\Controllers\Api\V1\GeocodeController::class, 'autocomplete'])
        ->middleware('throttle:60,1')
        ->name('geocode.autocomplete');

    // Locations for home / browse (no auth)
    Route::get('/countries', [LocationController::class, 'countries'])->name('locations.countries');
    Route::get('/cities', [LocationController::class, 'cities'])->name('locations.cities');

    // Amenities for filters (no auth)
    Route::get('/amenities', [AmenityController::class, 'index'])->name('amenities.index');

    // Reviews list (no auth) – approved only
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/tour-reviews', [TourReviewController::class, 'index'])->name('tour-reviews.index');

    // Tour 1vs1 discovery (public, login only required at booking time)
    Route::get('/tour-provinces', [TourProvinceController::class, 'index'])->name('tour-provinces.index');
    Route::get('/tour-provinces/{slug}', [TourProvinceController::class, 'show'])->name('tour-provinces.show');
    Route::get('/tour-providers', [TourProviderController::class, 'index'])->name('tour-providers.index');
    Route::get('/tour-providers/{uuid}', [TourProviderController::class, 'show'])->name('tour-providers.show');
    Route::get('/tours', [TourSearchController::class, 'index'])->name('tours.index');
    Route::get('/tours/{uuid}', [TourSearchController::class, 'show'])->name('tours.show');
    Route::get('/tours/{uuid}/availability', [TourAvailabilityController::class, 'index'])->name('tours.availability');

    // Authenticated customer routes (booking requires login — no guest flow by design)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/me', [AuthController::class, 'update'])->name('me.update');
        Route::post('/bookings/preview', [BookingController::class, 'preview'])->name('bookings.preview');
        Route::apiResource('bookings', BookingController::class)->only(['index', 'store']);
        Route::get('/bookings/{uuid}/invoice', [BookingController::class, 'invoice'])->name('bookings.invoice');
        Route::get('/bookings/{uuid}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{uuid}/checkout-session', [BookingController::class, 'createCheckoutSession'])->name('bookings.checkout-session');
        Route::post('/bookings/{uuid}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{uuid}/dispute', [BookingController::class, 'storeDispute'])->name('bookings.dispute.store');
        Route::post('/bookings/{uuid}/claim', [BookingController::class, 'claim'])->name('bookings.claim');
        // Tour 1vs1 bookings (auth required — no guest flow by design)
        Route::post('/tour-bookings/preview', [TourBookingController::class, 'preview'])->name('tour-bookings.preview');
        Route::apiResource('tour-bookings', TourBookingController::class)->only(['index', 'store']);
        Route::get('/tour-bookings/{uuid}', [TourBookingController::class, 'show'])->name('tour-bookings.show');
        Route::post('/tour-bookings/{uuid}/checkout-session', [TourBookingController::class, 'createCheckoutSession'])->name('tour-bookings.checkout-session');
        Route::post('/tour-bookings/{uuid}/cancel', [TourBookingController::class, 'cancel'])->name('tour-bookings.cancel');
        Route::get('/tour-bookings/{uuid}/invoice', [TourBookingController::class, 'invoice'])->name('tour-bookings.invoice');
        Route::post('/tour-bookings/{uuid}/dispute', [TourBookingController::class, 'storeDispute'])->name('tour-bookings.dispute.store');
        Route::get('/tour-bookings/{uuid}/messages', [TourMessageController::class, 'index'])->name('tour-bookings.messages.index');
        Route::post('/tour-bookings/{uuid}/messages', [TourMessageController::class, 'store'])->name('tour-bookings.messages.store');
        Route::get('/saved-tours', [SavedTourController::class, 'index'])->name('saved-tours.index');
        Route::post('/saved-tours', [SavedTourController::class, 'store'])->name('saved-tours.store');
        Route::delete('/saved-tours/{tourId}', [SavedTourController::class, 'destroy'])->name('saved-tours.destroy');
        Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
        Route::post('/tour-reviews', [TourReviewController::class, 'store'])->name('tour-reviews.store');
        Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
        Route::delete('/wishlist/{hotelId}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');
        Route::get('/support-tickets', [SupportTicketController::class, 'index'])->name('support-tickets.index');
        Route::post('/support-tickets', [SupportTicketController::class, 'store'])->name('support-tickets.store');
        Route::get('/support-tickets/{supportTicket}', [SupportTicketController::class, 'show'])->name('support-tickets.show');
        Route::post('/support-tickets/{supportTicket}/replies', [SupportTicketController::class, 'storeReply'])->name('support-tickets.replies.store');
    });
});
