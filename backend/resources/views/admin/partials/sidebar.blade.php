<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title" data-key="t-menu">{{ __('admin.sidebar.menu') }}</li>

                @if(auth()->user()->role === \App\Enums\Role::VENDOR)
                {{-- Vendor menu: Overview → Profile → Inventory → Operations → Finance → Support --}}
                @php
                    $unreadTourMessages = 0;
                    $providerIds = \App\Models\TourProvider::where('vendor_id', auth()->id())->pluck('id');
                    if($providerIds->isNotEmpty()){
                        $unreadTourMessages = \App\Models\TourMessage::whereHas('booking', function($q) use ($providerIds){
                            $q->whereIn('provider_id', $providerIds);
                        })->where('sender_id','!=', auth()->id())->whereNull('read_at')->count();
                    }
                @endphp
                <li>
                    <a href="{{ route('admin.vendor.dashboard') }}">
                        <i data-feather="home"></i>
                        <span>{{ __('admin.sidebar.dashboard') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.vendor.profile.edit') }}">
                        <i data-feather="briefcase"></i>
                        <span>{{ __('admin.sidebar.business_details') }}</span>
                    </a>
                </li>
                @php
                    $hotelActive = request()->routeIs('admin.vendor.hotels.*','admin.vendor.rooms.*','admin.vendor.bookings.*');
                    $tourActive = request()->routeIs('admin.vendor.tours.*','admin.vendor.guide-profile.*','admin.vendor.tour-bookings.*','admin.vendor.tour-messages.*');
                @endphp
                <li class="{{ $hotelActive ? 'mm-active' : '' }}">
                    <a href="javascript: void(0);" class="has-arrow {{ $hotelActive ? 'mm-active' : '' }}">
                        <i data-feather="layers"></i>
                        <span>{{ __('admin.sidebar.hotel_management') }}</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="{{ $hotelActive ? 'true' : 'false' }}">
                        <li><a href="{{ route('admin.vendor.hotels.index') }}" class="{{ request()->routeIs('admin.vendor.hotels.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.hotels') }}</span></a></li>
                        <li><a href="{{ route('admin.vendor.rooms.index') }}" class="{{ request()->routeIs('admin.vendor.rooms.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.rooms') }}</span></a></li>
                        <li><a href="{{ route('admin.vendor.bookings.index') }}" class="{{ request()->routeIs('admin.vendor.bookings.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.bookings') }}</span></a></li>
                    </ul>
                </li>
                <li class="{{ $tourActive ? 'mm-active' : '' }}">
                    <a href="javascript: void(0);" class="has-arrow {{ $tourActive ? 'mm-active' : '' }}">
                        <i data-feather="map"></i>
                        <span>{{ __('admin.sidebar.tour_management') }}</span>
                        @if($unreadTourMessages > 0)
                            <span class="badge bg-danger rounded-pill ms-2">{{ $unreadTourMessages }}</span>
                        @endif
                    </a>
                    <ul class="sub-menu" aria-expanded="{{ $tourActive ? 'true' : 'false' }}">
                        <li><a href="{{ route('admin.vendor.tours.index') }}" class="{{ request()->routeIs('admin.vendor.tours.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tours') }}</span></a></li>
                        <li><a href="{{ route('admin.vendor.guide-profile.index') }}" class="{{ request()->routeIs('admin.vendor.guide-profile.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.guide_profiles') }}</span></a></li>
                        <li><a href="{{ route('admin.vendor.tour-bookings.index') }}" class="{{ request()->routeIs('admin.vendor.tour-bookings.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_bookings') }}</span></a></li>
                        <li><a href="{{ route('admin.vendor.tour-messages.index') }}" class="{{ request()->routeIs('admin.vendor.tour-messages.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_messages') }}</span></a></li>
                    </ul>
                </li>
                <li>
                    <a href="{{ route('admin.vendor.reports.index') }}">
                        <i data-feather="bar-chart-2"></i>
                        <span>{{ __('admin.sidebar.reports') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.vendor.payouts.index') }}">
                        <i data-feather="dollar-sign"></i>
                        <span>{{ __('admin.sidebar.payouts') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.vendor.support-tickets.index') }}">
                        <i data-feather="help-circle"></i>
                        <span>{{ __('admin.sidebar.support') }}</span>
                    </a>
                </li>
                @else
                {{-- Admin / Super Admin menu: Overview → Platform → Partners → Moderation → System --}}
                <li>
                    <a href="{{ route('admin.dashboard') }}">
                        <i data-feather="home"></i>
                        <span>{{ __('admin.sidebar.dashboard') }}</span>
                    </a>
                </li>

                @if(auth()->user()->role === \App\Enums\Role::SUPER_ADMIN)
                {{-- Platform content & configuration --}}
                @php
                    $hotelActive = request()->routeIs('admin.countries.*','admin.cities.*','admin.amenities.*','admin.disputes.*','admin.reviews.*');
                    $tourActive = request()->routeIs('admin.tour-provinces.*','admin.tour-attractions.*','admin.tour-providers.*','admin.tour-disputes.*','admin.tour-reviews.*');
                @endphp
                <li class="{{ $hotelActive ? 'mm-active' : '' }}">
                    <a href="javascript: void(0);" class="has-arrow {{ $hotelActive ? 'mm-active' : '' }}">
                        <i data-feather="layers"></i>
                        <span>{{ __('admin.sidebar.hotel_management') }}</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="{{ $hotelActive ? 'true' : 'false' }}">
                        <li><a href="{{ route('admin.countries.index') }}" class="{{ request()->routeIs('admin.countries.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.countries') }}</span></a></li>
                        <li><a href="{{ route('admin.cities.index') }}" class="{{ request()->routeIs('admin.cities.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.cities') }}</span></a></li>
                        <li><a href="{{ route('admin.amenities.index') }}" class="{{ request()->routeIs('admin.amenities.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.amenities') }}</span></a></li>
                        <li><a href="{{ route('admin.disputes.index') }}" class="{{ request()->routeIs('admin.disputes.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.disputes') }}</span></a></li>
                        <li><a href="{{ route('admin.reviews.index') }}" class="{{ request()->routeIs('admin.reviews.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.reviews') }}</span></a></li>
                    </ul>
                </li>
                <li class="{{ $tourActive ? 'mm-active' : '' }}">
                    <a href="javascript: void(0);" class="has-arrow {{ $tourActive ? 'mm-active' : '' }}">
                        <i data-feather="map"></i>
                        <span>{{ __('admin.sidebar.tour_management') }}</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="{{ $tourActive ? 'true' : 'false' }}">
                        <li><a href="{{ route('admin.tour-provinces.index') }}" class="{{ request()->routeIs('admin.tour-provinces.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_provinces') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-attractions.index') }}" class="{{ request()->routeIs('admin.tour-attractions.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_attractions') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-providers.index') }}" class="{{ request()->routeIs('admin.tour-providers.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_providers') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-disputes.index') }}" class="{{ request()->routeIs('admin.tour-disputes.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_disputes') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-reviews.index') }}" class="{{ request()->routeIs('admin.tour-reviews.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_reviews') }}</span></a></li>
                    </ul>
                </li>
                {{-- Partners & revenue --}}
                <li>
                    <a href="{{ route('admin.vendors.index') }}">
                        <i data-feather="users"></i>
                        <span>{{ __('admin.sidebar.vendors') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.commission.index') }}">
                        <i data-feather="percent"></i>
                        <span>{{ __('admin.sidebar.commission') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.payouts.index') }}">
                        <i data-feather="credit-card"></i>
                        <span>{{ __('admin.sidebar.payouts') }}</span>
                    </a>
                </li>
                {{-- Moderation & support --}}
                <li>
                    <a href="{{ route('admin.support-tickets.index') }}">
                        <i data-feather="message-circle"></i>
                        <span>{{ __('admin.sidebar.support_tickets') }}</span>
                    </a>
                </li>
                {{-- System settings --}}
                <li>
                    <a href="{{ route('admin.website-settings.index') }}">
                        <i data-feather="settings"></i>
                        <span>{{ __('admin.sidebar.website_settings') }}</span>
                    </a>
                </li>
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="shield"></i>
                        <span>{{ __('admin.sidebar.access_control') }}</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('admin.roles.index') }}"><span>{{ __('admin.sidebar.roles') }}</span></a></li>
                        <li><a href="{{ route('admin.permissions.index') }}"><span>{{ __('admin.sidebar.permissions') }}</span></a></li>
                    </ul>
                </li>
                <li>
                    <a href="{{ route('admin.users.index') }}">
                        <i data-feather="users"></i>
                        <span>{{ __('admin.sidebar.users') }}</span>
                    </a>
                </li>
                @else
                {{-- Admin (non-super): Moderation only (gồm cả tour) --}}
                @php
                    $hotelActive = request()->routeIs('admin.disputes.*','admin.reviews.*');
                    $tourActive = request()->routeIs('admin.tour-provinces.*','admin.tour-attractions.*','admin.tour-providers.*','admin.tour-disputes.*','admin.tour-reviews.*');
                @endphp
                <li class="{{ $hotelActive ? 'mm-active' : '' }}">
                    <a href="javascript: void(0);" class="has-arrow {{ $hotelActive ? 'mm-active' : '' }}">
                        <i data-feather="layers"></i>
                        <span>{{ __('admin.sidebar.hotel_management') }}</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="{{ $hotelActive ? 'true' : 'false' }}">
                        <li><a href="{{ route('admin.disputes.index') }}" class="{{ request()->routeIs('admin.disputes.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.disputes') }}</span></a></li>
                        <li><a href="{{ route('admin.reviews.index') }}" class="{{ request()->routeIs('admin.reviews.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.reviews') }}</span></a></li>
                    </ul>
                </li>
                <li class="{{ $tourActive ? 'mm-active' : '' }}">
                    <a href="javascript: void(0);" class="has-arrow {{ $tourActive ? 'mm-active' : '' }}">
                        <i data-feather="map"></i>
                        <span>{{ __('admin.sidebar.tour_management') }}</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="{{ $tourActive ? 'true' : 'false' }}">
                        <li><a href="{{ route('admin.tour-provinces.index') }}" class="{{ request()->routeIs('admin.tour-provinces.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_provinces') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-attractions.index') }}" class="{{ request()->routeIs('admin.tour-attractions.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_attractions') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-providers.index') }}" class="{{ request()->routeIs('admin.tour-providers.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_providers') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-disputes.index') }}" class="{{ request()->routeIs('admin.tour-disputes.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_disputes') }}</span></a></li>
                        <li><a href="{{ route('admin.tour-reviews.index') }}" class="{{ request()->routeIs('admin.tour-reviews.*') ? 'mm-active' : '' }}"><span>{{ __('admin.sidebar.tour_reviews') }}</span></a></li>
                    </ul>
                </li>
                <li>
                    <a href="{{ route('admin.support-tickets.index') }}">
                        <i data-feather="message-circle"></i>
                        <span>{{ __('admin.sidebar.support_tickets') }}</span>
                    </a>
                </li>
                <li>
                    <a href="javascript: void(0);" class="has-arrow">
                        <i data-feather="shield"></i>
                        <span>{{ __('admin.sidebar.access_control') }}</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('admin.roles.index') }}"><span>{{ __('admin.sidebar.roles') }}</span></a></li>
                        <li><a href="{{ route('admin.permissions.index') }}"><span>{{ __('admin.sidebar.permissions') }}</span></a></li>
                    </ul>
                </li>
                <li>
                    <a href="{{ route('admin.users.index') }}">
                        <i data-feather="users"></i>
                        <span>{{ __('admin.sidebar.users') }}</span>
                    </a>
                </li>
                @endif
                @endif
            </ul>
        </div>
    </div>
</div>
