<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * @template TFactory of \Illuminate\Database\Eloquent\Factories\Factory
 * @property int $id
 * @property string $uuid
 * @property int $event_id
 * @property int $uploaded_by
 * @property string|null $title
 * @property string|null $description
 * @property string $filename
 * @property string $original_filename
 * @property string $file_path
 * @property string $file_type
 * @property string $mime_type
 * @property int $file_size
 * @property string $resource_type
 * @property bool $is_public
 * @property bool $is_downloadable
 * @property bool $requires_registration
 * @property int $download_count
 * @property int $view_count
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Event $event
 * @property-read \App\Models\User $uploadedBy
 * @method static Builder<static>|EventResource active()
 * @method static Builder<static>|EventResource downloadable()
 * @method static \Database\Factories\EventResourceFactory factory($count = null, $state = [])
 * @method static Builder<static>|EventResource newModelQuery()
 * @method static Builder<static>|EventResource newQuery()
 * @method static Builder<static>|EventResource ofType(string $type)
 * @method static Builder<static>|EventResource public()
 * @method static Builder<static>|EventResource query()
 * @method static Builder<static>|EventResource whereCreatedAt($value)
 * @method static Builder<static>|EventResource whereDescription($value)
 * @method static Builder<static>|EventResource whereDownloadCount($value)
 * @method static Builder<static>|EventResource whereEventId($value)
 * @method static Builder<static>|EventResource whereFilePath($value)
 * @method static Builder<static>|EventResource whereFileSize($value)
 * @method static Builder<static>|EventResource whereFileType($value)
 * @method static Builder<static>|EventResource whereFilename($value)
 * @method static Builder<static>|EventResource whereId($value)
 * @method static Builder<static>|EventResource whereIsDownloadable($value)
 * @method static Builder<static>|EventResource whereIsPublic($value)
 * @method static Builder<static>|EventResource whereMimeType($value)
 * @method static Builder<static>|EventResource whereOriginalFilename($value)
 * @method static Builder<static>|EventResource whereRequiresRegistration($value)
 * @method static Builder<static>|EventResource whereResourceType($value)
 * @method static Builder<static>|EventResource whereStatus($value)
 * @method static Builder<static>|EventResource whereTitle($value)
 * @method static Builder<static>|EventResource whereUpdatedAt($value)
 * @method static Builder<static>|EventResource whereUploadedBy($value)
 * @method static Builder<static>|EventResource whereUuid($value)
 * @method static Builder<static>|EventResource whereViewCount($value)
 * @mixin \Eloquent
 */
class EventResource extends Model
{
    /** @use HasFactory<TFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'event_id', 'uploaded_by', 'title', 'description',
        'filename', 'original_filename', 'file_path', 'file_type', 'mime_type',
        'file_size', 'resource_type', 'is_public', 'is_downloadable',
        'requires_registration', 'download_count', 'view_count', 'status'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_downloadable' => 'boolean',
        'requires_registration' => 'boolean',
        'download_count' => 'integer',
        'view_count' => 'integer',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Scopes
    /**
     * @param Builder<EventResource> $query
     * @return Builder<EventResource>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param Builder<EventResource> $query
     * @return Builder<EventResource>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @param Builder<EventResource> $query
     * @return Builder<EventResource>
     */
    public function scopeDownloadable(Builder $query): Builder
    {
        return $query->where('is_downloadable', true);
    }

    /**
     * @param Builder<EventResource> $query
     * @param string $type
     * @return Builder<EventResource>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('resource_type', $type);
    }

    // Helper methods
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function findByUuid(string $uuid): ?EventResource
    {
        return static::where('uuid', $uuid)->first();
    }

    public function getFileSizeFormatted(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function canBeAccessedBy(?User $user): bool
    {
        // Public resources can be accessed by anyone
        if ($this->is_public) {
            return true;
        }

        // Private resources require authentication
        if (!$user) {
            return false;
        }

        // Event host and admin can always access
        if ($user->id === $this->event->host_id || $user->isAdmin()) {
            return true;
        }

        // Check if registration is required
        if ($this->requires_registration) {
            return $this->event->registrations()
                ->wherePivot('user_id', $user->id)
                ->wherePivot('status', 'registered')
                ->exists();
        }

        return true;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $user->id === $this->uploaded_by || 
               $user->id === $this->event->host_id || 
               $user->isAdmin();
    }

    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }
}
