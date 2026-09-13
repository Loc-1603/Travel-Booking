
@extends('admin.layouts.app')
@section('title', __('admin.profile.title'))
@section('content')
    <div class="container-fluid">
        <!-- start page title -->
        <x-page-title
            title="{{ __('admin.profile.title') }}"
            :breadcrumbs="[
                ['label' => __('admin.sidebar.dashboard'), 'url' => route('admin.dashboard')],
                ['label' => __('admin.profile.title')]
            ]"
        />
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <x-alert />

                        <section>
                            <header class="mb-4">
                                <h2 class="text-lg font-medium text-gray-900">
                                    {{ __('admin.profile.form.personal_info') }}
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    {{ __('admin.profile.form.update_info_desc') }}
                                </p>
                            </header>

                            <!-- Verification Form (hidden submit) -->
                            <form id="send-verification" method="POST" action="{{ route('verification.send') }}">
                                @csrf
                            </form>

                            <form method="POST" action="{{ route('profile.update') }}" class="mt-4" enctype="multipart/form-data">
                                @csrf
                                @method('patch')

                                <div class="mb-4 d-flex align-items-center gap-3">
                                    <div class="flex-shrink-0">
                                        @include('admin.partials.user-avatar', ['user' => $user, 'size' => 72, 'class' => 'header-profile-user'])
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label" for="avatar">{{ __('admin.profile.avatar.title') }}</label>
                                        <input type="file" class="form-control @error('avatar') is-invalid @enderror" id="avatar" name="avatar" accept="image/*">
                                        @error('avatar')
                                            <span class="text-danger small d-block">{{ $message }}</span>
                                        @enderror
                                        @if($user->avatar)
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="remove_avatar" id="remove_avatar" value="1">
                                                <label class="form-check-label" for="remove_avatar">{{ __('admin.profile.avatar.remove_current') }}</label>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Name -->
                                <div class="mb-3">
                                    <label class="form-label" for="name">{{ __('auth.fields.name') }}</label>
                                    <input type="text"
                                           class="form-control @error('name') is-invalid @enderror"
                                           id="name"
                                           name="name"
                                           value="{{ old('name', $user->name) }}"
                                           required
                                           autofocus>
                                    @error('name')
                                    <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Email -->
                                <div class="mb-3">
                                    <label class="form-label" for="email">{{ __('auth.fields.email') }}</label>
                                    <input type="email"
                                           class="form-control @error('email') is-invalid @enderror"
                                           id="email"
                                           name="email"
                                           value="{{ old('email', $user->email) }}"
                                           required>
                                    @error('email')
                                    <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                    @enderror

                                    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-800">
                                                {{ __('auth.fields.email_unverified') }}
                                                <button type="submit" form="send-verification" class="btn btn-link p-0 text-decoration-underline">
                                                    {{ __('auth.fields.resend_verification') }}
                                                </button>
                                            </p>

                                            @if (session('status') === 'verification-link-sent')
                                                <p class="mt-2 text-success">
                                                    {{ __('auth.fields.verification_sent') }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <!-- Save Button -->
                                <div class="d-flex align-items-center gap-3">
                                    <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>

                                    @if (session('status') === 'profile-updated')
                                        <p class="text-muted mb-0" x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)">
                                            {{ __('admin.profile.form.update_success') }}
                                        </p>
                                    @endif
                                </div>
                            </form>
                        </section>
                        <div class="my-4"></div>
                        <section>
                            <header class="mb-4">
                                <h2 class="text-lg font-medium text-gray-900">
                                    {{ __('admin.profile.form.change_password') }}
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    {{ __('admin.profile.form.password_desc') }}
                                </p>
                            </header>

                            <form method="POST" action="{{ route('password.update') }}" class="mt-4">
                                @csrf
                                @method('put')

                                <!-- Current Password -->
                                <div class="mb-3">
                                    <label class="form-label" for="update_password_current_password">{{ __('admin.profile.form.current_password') }}</label>
                                    <input type="password"
                                           class="form-control @error('current_password') is-invalid @enderror"
                                           id="update_password_current_password"
                                           name="current_password"
                                           autocomplete="current-password"
                                           placeholder="{{ __('auth.fields.password_placeholder') }}">
                                    @error('current_password')
                                    <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- New Password -->
                                <div class="mb-3">
                                    <label class="form-label" for="update_password_password">{{ __('auth.fields.password') }}</label>
                                    <input type="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           id="update_password_password"
                                           name="password"
                                           autocomplete="new-password"
                                           placeholder="{{ __('auth.fields.password_placeholder') }}">
                                    @error('password')
                                    <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Confirm Password -->
                                <div class="mb-3">
                                    <label class="form-label" for="update_password_password_confirmation">{{ __('auth.fields.confirm_password') }}</label>
                                    <input type="password"
                                           class="form-control @error('password_confirmation') is-invalid @enderror"
                                           id="update_password_password_confirmation"
                                           name="password_confirmation"
                                           autocomplete="new-password"
                                           placeholder="{{ __('auth.fields.confirm_password_placeholder') }}">
                                    @error('password_confirmation')
                                    <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Save Button -->
                                <div class="d-flex align-items-center gap-3">
                                    <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>

                                    @if (session('status') === 'password-updated')
                                        <p class="text-muted mb-0"
                                           x-data="{ show: true }"
                                           x-show="show"
                                           x-transition
                                           x-init="setTimeout(() => show = false, 2000)">
                                            {{ __('admin.profile.form.password_changed') }}
                                        </p>
                                    @endif
                                </div>
                            </form>
                        </section>
                        <hr class="my-4">
                        <section class="mb-4">
                            <header class="mb-3">
                                <h2 class="text-lg font-medium text-gray-900">{{ __('admin.profile.delete_account') }}</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ __('admin.profile.delete_account_desc') }}
                                </p>
                            </header>

                            <!-- Delete Button -->
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmUserDeletion">
                                {{ __('admin.profile.delete_account') }}
                            </button>

                            <!-- Modal -->
                            <div class="modal fade" id="confirmUserDeletion" tabindex="-1" aria-labelledby="confirmUserDeletionLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('profile.destroy') }}" class="p-4">
                                            @csrf
                                            @method('delete')

                                            <div class="modal-header">
                                                <h5 class="modal-title" id="confirmUserDeletionLabel">{{ __('admin.profile.delete_account_confirm') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>

                                            <div class="modal-body">
                                                <p class="text-sm text-gray-600">
                                                    {{ __('admin.profile.delete_account_desc') }}
                                                </p>

                                                <div class="mt-3">
                                                    <label class="form-label" for="password">{{ __('auth.fields.password') }}</label>
                                                    <input type="password"
                                                           class="form-control @error('password') is-invalid @enderror"
                                                           id="password"
                                                           name="password"
                                                           placeholder="{{ __('auth.fields.password_placeholder') }}">

                                                    @error('password')
                                                    <span class="text-danger mt-1 d-block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                                                <button type="submit" class="btn btn-danger">{{ __('admin.profile.delete_account') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </section>


                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
