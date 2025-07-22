<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

/**
 * @property string $uuid
 * @property string $name
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $password
 * @property string|null $avatar
 * @property string|null $bio
 * @property string $institution
 * @property string|null $department
 * @property string|null $position
 * @property string|null $website
 * @property string|null $phone
 * @property array<string, mixed>|null $social_links
 * @property string $account_status
 * @property array<string, mixed>|null $preferences
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property bool $is_admin
 * @property bool $is_banned
 * @property string|null $ban_reason
 * @property \Illuminate\Support\Carbon|null $banned_at
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'avatar',
        'bio',
        'institution',
        'department',
        'position',
        'website',
        'phone',
        'social_links',
        'account_status',
        'preferences',
        'last_login_at',
        'is_admin',
        'is_banned',
        'ban_reason',
        'banned_at',
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
            'last_login_at' => 'datetime',
            'banned_at' => 'datetime',
            'social_links' => 'array',
            'preferences' => 'array',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid()->toString();
            }
        });
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->is_admin || $this->account_status === 'admin';
    }

    /**
     * Check if user account is active
     */
    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    /**
     * Mark email as verified and activate account
     */
    public function markEmailAsVerified(): void
    {
        $this->email_verified_at = now();
        $this->account_status = 'active';
        $this->save();
    }

    /**
     * Get user's full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: $this->name;
    }

    /**
     * Calculate profile completion percentage
     */
    public function calculateProfileCompletion(): int
    {
        $fields = [
            'first_name', 'last_name', 'email', 'bio', 'institution', 
            'department', 'position', 'avatar'
        ];
        
        $completed = 0;
        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $completed++;
            }
        }
        
        return (int) round(($completed / count($fields)) * 100);
    }

    /**
     * Find user by UUID
     */
    public static function findByUuid(string $uuid): ?User
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Events hosted by this user
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Event, $this>
     */
    public function hostedEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Event::class, 'host_id');
    }

    /**
     * All events associated with this user (alias for hostedEvents for compatibility)
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Event, $this>
     */
    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hostedEvents();
    }

    /**
     * Events user has registered for
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Event, $this>
     */
    public function registeredEvents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations')
                    ->withPivot('status', 'registered_at')
                    ->withTimestamps();
    }

    /**
     * Forum posts created by user
     */
    public function forumPosts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    /**
     * Posts liked by user
     */
    public function likedPosts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ForumPost::class, 'post_likes', 'user_id', 'post_id')
                    ->withTimestamps();
    }

    /**
     * Connection requests sent by user
     */
    public function sentConnectionRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserConnection::class, 'requester_id');
    }

    /**
     * Connection requests received by user
     */
    public function receivedConnectionRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserConnection::class, 'addressee_id');
    }

    /**
     * Get user's connections (accepted only)
     */
    public function connections(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_connections', 'requester_id', 'addressee_id')
                    ->wherePivot('status', 'accepted')
                    ->withPivot('status', 'responded_at')
                    ->withTimestamps()
                    ->union(
                        $this->belongsToMany(User::class, 'user_connections', 'addressee_id', 'requester_id')
                             ->wherePivot('status', 'accepted')
                             ->withPivot('status', 'responded_at')
                             ->withTimestamps()
                    );
    }

    /**
     * Check if user is connected to another user
     */
    public function isConnectedTo(User $user): bool
    {
        return UserConnection::where(function ($query) use ($user) {
            $query->where('requester_id', $this->getKey())
                  ->where('addressee_id', $user->getKey());
        })->orWhere(function ($query) use ($user) {
            $query->where('requester_id', $user->getKey())
                  ->where('addressee_id', $this->getKey());
        })->where('status', 'accepted')->exists();
    }

    /**
     * Get connection status with another user
     */
    public function getConnectionStatusWith(User $user): ?string
    {
        $connection = UserConnection::where(function ($query) use ($user) {
            $query->where('requester_id', $this->getKey())
                  ->where('addressee_id', $user->getKey());
        })->orWhere(function ($query) use ($user) {
            $query->where('requester_id', $user->getKey())
                  ->where('addressee_id', $this->getKey());
        })->first();

        return $connection?->status;
    }

    /**
     * Send connection request to another user
     */
    public function sendConnectionRequest(User $user, ?string $message = null): UserConnection
    {
        return UserConnection::create([
            'requester_id' => $this->getKey(),
            'addressee_id' => $user->getKey(),
            'status' => 'pending',
            'message' => $message,
        ]);
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_registrations')
                    ->withPivot('uuid', 'status', 'registered_at', 'notes')
                    ->withTimestamps();
    }
}
