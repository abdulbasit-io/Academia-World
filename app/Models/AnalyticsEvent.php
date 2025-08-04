<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $event_type
 * @property string $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property int|null $user_id
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $session_id
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent action($action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent dateRange($startDate, $endDate)
 * @method static \Database\Factories\AnalyticsEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent forEntity($entityType, $entityId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent ofType($eventType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnalyticsEvent whereUuid($value)
 * @mixin \Eloquent
 */
class AnalyticsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'event_type',
        'action',
        'entity_type',
        'entity_id',
        'user_id',
        'metadata',
        'ip_address',
        'user_agent',
        'session_id',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
            if (empty($model->occurred_at)) {
                $model->occurred_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Scope for filtering by date range
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('occurred_at', [$startDate, $endDate]);
    }

    // Scope for filtering by event type
    public function scopeOfType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    // Scope for filtering by action
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    // Scope for filtering by entity
    public function scopeForEntity($query, $entityType, $entityId = null)
    {
        $query = $query->where('entity_type', $entityType);
        
        if ($entityId) {
            $query->where('entity_id', $entityId);
        }
        
        return $query;
    }
}
