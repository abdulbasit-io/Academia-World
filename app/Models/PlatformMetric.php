<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $metric_type
 * @property string $metric_key
 * @property array<array-key, mixed> $value
 * @property \Illuminate\Support\Carbon $metric_date
 * @property string $period
 * @property array<array-key, mixed>|null $breakdown
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric dateRange($startDate, $endDate)
 * @method static \Database\Factories\PlatformMetricFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric key($metricKey)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric latest($days = 30)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric ofType($metricType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric period($period)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereBreakdown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereMetricDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereMetricKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereMetricType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformMetric whereValue($value)
 * @mixin \Eloquent
 */
class PlatformMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'metric_type',
        'metric_key',
        'value',
        'metric_date',
        'period',
        'breakdown',
    ];

    protected $casts = [
        'value' => 'array',
        'breakdown' => 'array',
        'metric_date' => 'date',
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

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Scope for filtering by metric type
    public function scopeOfType($query, $metricType)
    {
        return $query->where('metric_type', $metricType);
    }

    // Scope for filtering by metric key
    public function scopeKey($query, $metricKey)
    {
        return $query->where('metric_key', $metricKey);
    }

    // Scope for filtering by date range
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
    }

    // Scope for filtering by period
    public function scopePeriod($query, $period)
    {
        return $query->where('period', $period);
    }

    // Scope for latest metrics
    public function scopeLatest($query, $days = 30)
    {
        return $query->where('metric_date', '>=', now()->subDays($days));
    }
}
