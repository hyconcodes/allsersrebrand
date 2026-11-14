<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceAnnouncement extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SPOKEN = 'spoken';

    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'engagement_id',
        'to_number',
        'message',
        'status',
        'call_id',
        'spoken_at',
        'expires_at',
        'attempt',
        'next_attempt_at',
        'answered_at',
        'pinger_notified',
        'pinger_notified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spoken_at' => 'datetime',
            'expires_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'answered_at' => 'datetime',
            'pinger_notified_at' => 'datetime',
            'attempt' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
