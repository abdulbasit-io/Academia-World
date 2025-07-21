<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
