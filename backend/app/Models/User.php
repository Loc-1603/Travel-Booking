<?php

namespace App\Models;

use App\Enums\Role;
use App\Notifications\VerifyEmailNotification;
use App\Traits\HasUuid;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'avatar',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function savedHotels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SavedHotel::class);
    }

    public function vendorProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function payouts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payout::class, 'vendor_id');
    }

    public function bankAccounts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VendorBankAccount::class, 'vendor_id')->orderBy('is_default', 'desc')->orderBy('sort_order');
    }

    public function isVendorApproved(): bool
    {
        if ($this->role !== \App\Enums\Role::VENDOR) {
            return false;
        }
        $profile = $this->vendorProfile;
        return $profile && $profile->isApproved();
    }

    /** Public URL for stored avatar, or null if none. */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar || ! Storage::disk('public')->exists($this->avatar)) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function avatarInitial(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    /**
     * Send the email verification notification (branded). Used when an existing
     * user re-verifies (e.g. after changing their email address).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification('user:'.$this->uuid, $this->email));
    }
}
