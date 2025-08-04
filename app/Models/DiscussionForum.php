<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\ForumPost;

/**
 * @property int $id
 * @property string $uuid
 * @property int $event_id
 * @property string $title
 * @property string|null $description
 * @property string $type
 * @property bool $is_active
 * @property bool $is_moderated
 * @property int $created_by
 * @property int $post_count
 * @property int $participant_count
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $creator
 * @property-read \App\Models\Event $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ForumPost> $latestPost
 * @property-read int|null $latest_post_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ForumPost> $pinnedPosts
 * @property-read int|null $pinned_posts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ForumPost> $posts
 * @property-read int|null $posts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ForumPost> $topLevelPosts
 * @property-read int|null $top_level_posts_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum active()
 * @method static \Database\Factories\DiscussionForumFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereIsModerated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereLastActivityAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereParticipantCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum wherePostCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DiscussionForum whereUuid($value)
 * @mixin \Eloquent
 */
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
