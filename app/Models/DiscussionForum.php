<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\ForumPost;

class DiscussionForum extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'type',
        'is_active',
        'is_moderated',
        'created_by',
        'post_count',
        'participant_count',
        'last_activity_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_moderated' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($forum) {
            if (empty($forum->uuid)) {
                $forum->uuid = Str::uuid();
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /**
     * Get the event that owns this forum
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the user who created this forum
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all posts in this forum
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'forum_id');
    }

    /**
     * Get top-level posts (not replies)
     */
    public function topLevelPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'forum_id')
                    ->whereNull('parent_id')
                    ->orderBy('is_pinned', 'desc')
                    ->orderBy('created_at', 'desc');
    }

    /**
     * Get pinned posts
     */
    public function pinnedPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'forum_id')
                    ->where('is_pinned', true);
    }

    /**
     * Get the latest post in this forum
     */
    public function latestPost(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'forum_id')
                    ->latest();
    }

    /**
     * Update the forum's activity statistics
     */
    public function updateActivity(): void
    {
        $this->update([
            'post_count' => $this->posts()->count(),
            'participant_count' => $this->posts()->distinct('user_id')->count('user_id'),
            'last_activity_at' => $this->posts()->latest()->first()?->created_at ?? now(),
        ]);
    }

    /**
     * Check if user can post in this forum
     */
    public function canUserPost(User $user): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check if user is registered for the event or is the host
        return $user->id === $this->event->host_id || 
               $user->registrations()->where('event_id', $this->event_id)->exists() ||
               $user->is_admin;
    }

    /**
     * Scope to get active forums
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get forums by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
