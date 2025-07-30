<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
