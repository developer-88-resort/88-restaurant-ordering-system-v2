<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\LogsAuditActivity;
use App\Enums\Department;
use App\Enums\UserInvitationStatus;
use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserInvitationNotification;
use App\Support\Pin;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsAuditActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'is_active',
        'avatar_path',
        'locale',
        'invitation_token',
        'invitation_expires_at',
        'invited_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
        'pin_hash',
        'pin_lookup',
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
            'role' => UserRole::class,
            'department' => Department::class,
            'is_active' => 'boolean',
            'invitation_expires_at' => 'datetime',
            'pin_changed_at' => 'datetime',
        ];
    }

    /**
     * Staff and Admin sign in by tapping their name and entering a PIN.
     * Superadmin keeps email + password and never appears on that list.
     */
    public function usesPin(): bool
    {
        return in_array($this->role ?? UserRole::Staff, [UserRole::Admin, UserRole::Staff], true);
    }

    public function hasPin(): bool
    {
        return $this->pin_hash !== null;
    }

    /**
     * No PIN yet, or still the one an admin set for them — either way they
     * choose their own before doing anything else (EnsurePinIsSet).
     */
    public function mustSetPin(): bool
    {
        return $this->usesPin() && (! $this->hasPin() || $this->pin_changed_at === null);
    }

    /**
     * @param  bool  $temporary  An admin is setting it on the owner's behalf:
     *                           it works for one sign-in, then must be changed.
     */
    public function setPin(string $pin, bool $temporary = false): void
    {
        $this->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_lookup' => Pin::lookup($pin),
            'pin_changed_at' => $temporary ? null : now(),
        ])->save();
    }

    public function checkPin(string $pin): bool
    {
        return $this->hasPin() && Hash::check($pin, $this->pin_hash);
    }

    /**
     * The names on the sign-in screen: active Staff/Admin who have a PIN.
     */
    public function scopeSignsInWithPin(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Staff->value])
            ->whereNotNull('pin_hash');
    }

    /**
     * Managers who can approve with their PIN (an Admin always can; a
     * Superadmin has no PIN, so approves with email + password instead).
     */
    public function scopeApprovesWithPin(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereIn('role', [UserRole::Superadmin->value, UserRole::Admin->value])
            ->whereNotNull('pin_hash');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * A user who hasn't set their own password yet is still mid-invitation —
     * they exist in the system (so a Superadmin can see/manage/resend their
     * invite) but cannot sign in until they complete activation.
     */
    public function invitationStatus(): UserInvitationStatus
    {
        if (! $this->isPendingActivation()) {
            return UserInvitationStatus::Active;
        }

        if ($this->invitation_expires_at && $this->invitation_expires_at->isPast()) {
            return UserInvitationStatus::Expired;
        }

        return UserInvitationStatus::Pending;
    }

    /**
     * Still waiting on an invitation: no password and no PIN to sign in with.
     * (Staff/Admin created with a PIN are active straight away.)
     */
    public function isPendingActivation(): bool
    {
        return $this->password === null && $this->pin_hash === null;
    }

    /**
     * Issue a fresh invitation token (invalidating any previous one) and
     * email it to the user. Used both when a Superadmin first invites
     * someone and when resending an expired/unused invitation.
     */
    public function sendInvitation(): void
    {
        $token = Str::random(64);

        $this->forceFill([
            'invitation_token' => Hash::make($token),
            'invitation_expires_at' => now()->addDays(7),
        ])->save();

        $this->notify(new UserInvitationNotification($token));
    }

    /**
     * The manager tier (Superadmin + Admin) — the pair that can approve
     * what staff alone cannot.
     */
    public function isManager(): bool
    {
        return in_array($this->role, [UserRole::Superadmin, UserRole::Admin], true);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name));

        $initials = collect($words)->map(fn ($word) => mb_substr($word, 0, 1))->take(2)->implode('');

        return mb_strtoupper($initials) ?: '?';
    }

    /**
     * Where a signed-in user lands by default — used by the root "/" route
     * and by every auth-flow redirect (password confirm, email verification)
     * that falls back to a generic "home" destination.
     */
    public function homeRouteName(): string
    {
        return $this->worksIn(Department::Restaurant) ? 'superadmin.dashboard' : 'massage.dashboard';
    }

    /**
     * Whether this account may use that department's pages. A Superadmin
     * works in every department; an Admin/Staff only in their own.
     */
    public function worksIn(Department $department): bool
    {
        if ($this->role === UserRole::Superadmin) {
            return true;
        }

        return ($this->department ?? Department::Restaurant) === $department;
    }
}
