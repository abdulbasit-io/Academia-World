<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $admin_id
 * @property string $action
 * @property string $target_type
 * @property int $target_id
 * @property string $description
 * @property array<array-key, mixed>|null $changes
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string $severity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $admin
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog action($action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog byAdmin($adminId)
 * @method static \Database\Factories\AdminLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog forTarget($targetType, $targetId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog recent($days = 7)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog severity($severity)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereChanges($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereTargetType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminLog whereUuid($value)
 * @mixin \Eloquent
 */
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
