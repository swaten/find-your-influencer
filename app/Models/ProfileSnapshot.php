<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ProfileSnapshot extends Model
{
    use HasFactory;

    const UPDATED_AT = null; // snapshots are immutable - only created_at applies

    protected $fillable = [
        'profile_id',
        'provider',
        'followers_count',
        'following_count',
        'posts_count',
        'raw_payload',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
