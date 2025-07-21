<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
}space App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMetric extends Model
{
    //
}
