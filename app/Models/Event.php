<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
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

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function moderatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function registrations(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
                    ->withPivot('status', 'registered_at', 'notes')
                    ->withTimestamps();
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['published', 'completed']);
    }

    public function scopeBanned($query)
    {
        return $query->where('status', 'banned');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeUpcoming($query)
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
