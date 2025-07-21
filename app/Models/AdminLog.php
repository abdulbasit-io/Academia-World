<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'admin_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'changes',
        'metadata',
        'ip_address',
        'severity',
    ];

    protected $casts = [
        'changes' => 'array',
        'metadata' => 'array',
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

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Scope for filtering by admin
    public function scopeByAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    // Scope for filtering by action
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    // Scope for filtering by target
    public function scopeForTarget($query, $targetType, $targetId = null)
    {
        $query = $query->where('target_type', $targetType);
        
        if ($targetId) {
            $query->where('target_id', $targetId);
        }
        
        return $query;
    }

    // Scope for filtering by severity
    public function scopeSeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    // Scope for recent logs
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
