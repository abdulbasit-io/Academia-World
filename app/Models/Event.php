<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 */
class Event extends Model
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    protected $fillable = [
        'host_id', 'title', 'description', 'start_date', 'end_date',
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

    /**
     * @return BelongsTo<User, Event>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return BelongsTo<User, Event>
     */
    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /**
     * @return BelongsToMany<User, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
                    ->withPivot('status', 'registered_at', 'notes')
                    ->withTimestamps();
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
