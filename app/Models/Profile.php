<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\ProfileStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'platform',
        'username',
        'username_normalized',
        'external_id',
        'display_name',
        'avatar_url',
        'status',
        'consecutive_failures',
        'last_fetch_attempted_at',
        'last_fetched_at',
        'last_error',
        'last_followers_count',
        'last_following_count',
        'last_posts_count',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => ProfileStatus::class,
            'last_fetch_attempted_at' => 'datetime',
            'last_fetched_at' => 'datetime',
        ];
    }

    // normalize username on every save so the partial unique index and lookups stay consistent
    protected static function booted(): void
    {
        static::saving(function (Profile $profile) {
            if ($profile->isDirty('username')) {
                $profile->username_normalized = mb_strtolower($profile->username);
            }
        });
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ProfileSnapshot::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
