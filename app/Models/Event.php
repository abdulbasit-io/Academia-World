<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 * @property int $id
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
 * @property array<array-key, mixed>|null $agenda
 * @property array<array-key, mixed>|null $tags
 * @property string|null $status
 * @property string|null $ban_reason
 * @property \Illuminate\Support\Carbon|null $banned_at
 * @property string $visibility
 * @property string|null $requirements
 * @property \Illuminate\Support\Carbon|null $moderated_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $moderated_by
 * @property string|null $moderation_reason
 * @property int|null $banned_by
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventResource> $activeResources
 * @property-read int|null $active_resources_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DiscussionForum> $forums
 * @property-read int|null $forums_count
 * @property-read int $available_spots
 * @property-read bool $is_full
 * @property-read \App\Models\User $host
 * @property-read \App\Models\User|null $moderatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventResource> $publicResources
 * @property-read int|null $public_resources_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $registrations
 * @property-read int|null $registrations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EventResource> $resources
 * @property-read int|null $resources_count
 * @method static Builder<static>|Event active()
 * @method static Builder<static>|Event banned()
 * @method static \Database\Factories\EventFactory factory($count = null, $state = [])
 * @method static Builder<static>|Event newModelQuery()
 * @method static Builder<static>|Event newQuery()
 * @method static Builder<static>|Event public()
 * @method static Builder<static>|Event published()
 * @method static Builder<static>|Event query()
 * @method static Builder<static>|Event upcoming()
 * @method static Builder<static>|Event whereAgenda($value)
 * @method static Builder<static>|Event whereBanReason($value)
 * @method static Builder<static>|Event whereBannedAt($value)
 * @method static Builder<static>|Event whereBannedBy($value)
 * @method static Builder<static>|Event whereCapacity($value)
 * @method static Builder<static>|Event whereCreatedAt($value)
 * @method static Builder<static>|Event whereDescription($value)
 * @method static Builder<static>|Event whereEndDate($value)
 * @method static Builder<static>|Event whereHostId($value)
 * @method static Builder<static>|Event whereId($value)
 * @method static Builder<static>|Event whereLocation($value)
 * @method static Builder<static>|Event whereLocationType($value)
 * @method static Builder<static>|Event whereModeratedAt($value)
 * @method static Builder<static>|Event whereModeratedBy($value)
 * @method static Builder<static>|Event whereModerationReason($value)
 * @method static Builder<static>|Event wherePoster($value)
 * @method static Builder<static>|Event whereRequirements($value)
 * @method static Builder<static>|Event whereStartDate($value)
 * @method static Builder<static>|Event whereStatus($value)
 * @method static Builder<static>|Event whereTags($value)
 * @method static Builder<static>|Event whereTimezone($value)
 * @method static Builder<static>|Event whereTitle($value)
 * @method static Builder<static>|Event whereUpdatedAt($value)
 * @method static Builder<static>|Event whereUuid($value)
 * @method static Builder<static>|Event whereVirtualLink($value)
 * @method static Builder<static>|Event whereVisibility($value)
 * @mixin \Eloquent
 */
class Event extends Model
{
    /** @use HasFactory<TFactory> */
    use HasFactory, SoftDeletes;

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

    /**
     * Get forums for this event
     */
    public function forums(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DiscussionForum::class);
    }
}
