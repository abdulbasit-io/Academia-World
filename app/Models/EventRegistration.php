<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $event_id
 * @property int $user_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $registered_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Event $event
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\EventRegistrationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereRegisteredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EventRegistration whereUuid($value)
 * @mixin \Eloquent
 */
class EventRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'event_id',
        'user_id',
        'status',
        'registered_at',
        'notes',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
