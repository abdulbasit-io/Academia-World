<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $email
 * @property string $token
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailVerificationToken whereUuid($value)
 * @mixin \Eloquent
 */
class EmailVerificationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'email',
        'token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
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
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
}
