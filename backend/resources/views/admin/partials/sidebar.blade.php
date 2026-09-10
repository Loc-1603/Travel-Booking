<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title" data-key="t-menu">{{ __('admin.sidebar.menu') }}</li>

                @if(auth()->user()->role === \App\Enums\Role::VENDOR)
                {{-- Vendor menu: Overview → Profile → Inventory → Operations → Finance → Support --}}
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
                <li>
                    <a href="{{ route('admin.vendor.hotels.index') }}">
                        <i data-feather="layers"></i>
                        <span>{{ __('admin.sidebar.hotels') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.vendor.rooms.index') }}">
                        <i data-feather="box"></i>
                        <span>{{ __('admin.sidebar.rooms') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.vendor.bookings.index') }}">
                        <i data-feather="calendar"></i>
                        <span>{{ __('admin.sidebar.bookings') }}</span>
                    </a>
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
                <li>
                    <a href="{{ route('admin.countries.index') }}">
                        <i data-feather="globe"></i>
                        <span>{{ __('admin.sidebar.countries') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.cities.index') }}">
                        <i data-feather="map-pin"></i>
                        <span>{{ __('admin.sidebar.cities') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.amenities.index') }}">
                        <i data-feather="check-square"></i>
                        <span>{{ __('admin.sidebar.amenities') }}</span>
                    </a>
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
                    <a href="{{ route('admin.disputes.index') }}">
                        <i data-feather="alert-circle"></i>
                        <span>{{ __('admin.sidebar.disputes') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.reviews.index') }}">
                        <i data-feather="star"></i>
                        <span>{{ __('admin.sidebar.reviews') }}</span>
                    </a>
                </li>
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
                {{-- Admin (non-super): Moderation only --}}
                <li>
                    <a href="{{ route('admin.disputes.index') }}">
                        <i data-feather="alert-circle"></i>
                        <span>{{ __('admin.sidebar.disputes') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('admin.reviews.index') }}">
                        <i data-feather="star"></i>
                        <span>{{ __('admin.sidebar.reviews') }}</span>
                    </a>
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
