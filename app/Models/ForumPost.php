<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $forum_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $content
 * @property int $is_pinned
 * @property int $is_solution
 * @property int $is_moderated
 * @property int $likes_count
 * @property-read int|null $replies_count
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property int|null $edited_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\DiscussionForum $forum
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $likedBy
 * @property-read int|null $liked_by_count
 * @property-read ForumPost|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ForumPost> $replies
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\ForumPostFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost replies()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost topLevel()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereEditedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereEditedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereForumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereIsModerated($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereIsPinned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereIsSolution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereLikesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereRepliesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ForumPost withoutTrashed()
 * @mixin \Eloquent
 */
class ForumPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'forum_id',
        'user_id',
        'parent_id',
        'content',
        'likes_count',
        'replies_count',
        'edited_at',
        'edited_by',
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'replies_count' => 'integer',
        'edited_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function forum(): BelongsTo
    {
        return $this->belongsTo(DiscussionForum::class, 'forum_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'parent_id');
    }

    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes', 'post_id', 'user_id')
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likedBy()->where('user_id', $user->id)->exists();
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function toggleLike(User $user): array
    {
        $isLiked = $this->isLikedBy($user);
        
        if ($isLiked) {
            $this->likedBy()->detach($user->id);
            $this->decrement('likes_count');
            return ['liked' => false, 'likes_count' => $this->likes_count];
        } else {
            $this->likedBy()->attach($user->id);
            $this->increment('likes_count');
            return ['liked' => true, 'likes_count' => $this->likes_count];
        }
    }

    public function canBeDeletedBy(User $user): bool
    {
        // Post author, forum event host, or admin can delete
        return $this->user_id === $user->id || 
               $user->is_admin || 
               $user->id === $this->forum->event->host_id;
    }

    public function markAsSolution(): void
    {
        // First, unmark any existing solution in this thread
        if ($this->parent_id) {
            // This is a reply, unmark other replies as solution
            ForumPost::where('parent_id', $this->parent_id)
                     ->where('id', '!=', $this->id)
                     ->update(['is_solution' => false]);
        } else {
            // This is a top-level post, unmark its replies as solution
            $this->replies()->update(['is_solution' => false]);
        }

        // Mark this post as solution
        $this->update(['is_solution' => true]);
    }
}
