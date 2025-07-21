<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 * @property string $uuid
 * @property int $host_id
 * @property string $title
 * @property string $description
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon $end_date
 * @property string $timezone
 * @property string $location_type
 * @property string|null $location
 * @property string|null $virtual_link
 * @property int|null $capacity
 * @property string|null $poster
 * @property array<string, mixed>|null $agenda
 * @property array<string>|null $tags
 * @property string $status draft|pending_approval|published|cancelled|completed|banned
 * @property string $visibility
 * @property string|null $requirements
 * @property \Illuminate\Support\Carbon|null $moderated_at
 * @property int|null $moderated_by
 * @property string|null $moderation_reason
 * @property string|null $ban_reason
 * @property \Illuminate\Support\Carbon|null $banned_at
 * @property int|null $banned_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Event extends Model
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'host_id', 'title', 'description', 'start_date', 'end_date',
        'timezone', 'location_type', 'location', 'virtual_link', 'capacity',
        'poster', 'agenda', 'tags', 'status', 'visibility', 'requirements',
        'moderated_at', 'moderated_by', 'moderation_reason',
        'ban_reason', 'banned_at', 'banned_by'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'moderated_at' => 'datetime',
        'banned_at' => 'datetime',
        'agenda' => 'array',
        'tags' => 'array',
    ];

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
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
                    ->withPivot('uuid', 'status', 'registered_at', 'notes')
                    ->withTimestamps();
    }

    /**
     * @return HasMany<EventResource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(EventResource::class);
    }

    /**
     * @return HasMany<EventResource, $this>
     */
    public function activeResources(): HasMany
    {
        return $this->hasMany(EventResource::class)->where('status', 'active');
    }

    /**
     * @return HasMany<EventResource, $this>
     */
    public function publicResources(): HasMany
    {
        return $this->hasMany(EventResource::class)->where('is_public', true)->where('status', 'active');
    }

    // Scopes
    /**
     * @param Builder<Event> $query
     * @return Builder<Event>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * @param Builder<Event> $query
     * @return Builder<Event>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'completed']);
    }

    /**
     * @param Builder<Event> $query
     * @return Builder<Event>
     */
    public function scopeBanned(Builder $query): Builder
    {
        return $query->where('status', 'banned');
    }

    /**
     * Find event by UUID
     */
    public static function findByUuid(string $uuid): ?Event
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
     * @param Builder<Event> $query
     * @return Builder<Event>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    /**
     * @param Builder<Event> $query
     * @return Builder<Event>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_date', '>', now());
    }

    // Accessors
    public function getIsFullAttribute(): bool
    {
        if (!$this->capacity) return false;
        return $this->registrations()->wherePivot('status', 'registered')->count() >= $this->capacity;
    }

    public function getAvailableSpotsAttribute(): int
    {
        if (!$this->capacity) return PHP_INT_MAX;
        return $this->capacity - $this->registrations()->wherePivot('status', 'registered')->count();
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->host_id === $user->id && $this->status !== 'banned';
    }

    public function canBeModeratedBy(User $user): bool
    {
        return $user->isAdmin();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['published', 'completed']);
    }

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }
}
