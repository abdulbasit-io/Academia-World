<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $requester_id
 * @property int $addressee_id
 * @property string $status
 * @property string|null $message
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $addressee
 * @property-read \App\Models\User $requester
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection accepted()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection blocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection declined()
 * @method static \Database\Factories\UserConnectionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereAddresseeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereRequesterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereRespondedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserConnection whereUuid($value)
 * @mixin \Eloquent
 */
class UserConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_id',
        'addressee_id',
        'status',
        'message',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Get the user who sent the connection request
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the user who received the connection request
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'addressee_id');
    }

    /**
     * Accept the connection request
     */
    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    /**
     * Decline the connection request
     */
    public function decline(): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    /**
     * Block the connection
     */
    public function block(): void
    {
        $this->update([
            'status' => 'blocked',
            'responded_at' => now(),
        ]);
    }

    /**
     * Check if connection is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if connection is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if connection is declined
     */
    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    /**
     * Check if connection is blocked
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Scope to get pending connections
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get accepted connections
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope to get declined connections
     */
    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    /**
     * Scope to get blocked connections
     */
    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }
}
