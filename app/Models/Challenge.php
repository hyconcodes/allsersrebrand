<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'creator_id',
        'title',
        'hashtag',
        'guidelines',
        'prizes',
        'start_at',
        'end_at',
        'is_admin_challenge',
        'winner_id',
        'banner_url',
        'custom_link'
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_admin_challenge' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($challenge) {
            if (!$challenge->custom_link) {
                $challenge->custom_link = Str::slug($challenge->title) . '-' . Str::random(5);
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'challenge_participants')
            ->withPivot('joined_at');
    }

    public function judges()
    {
        return $this->belongsToMany(User::class, 'challenge_judges')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function badge()
    {
        return $this->hasOne(Badge::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function isOngoing()
    {
        return now()->between($this->start_at, $this->end_at);
    }

    public function hasEnded()
    {
        return $this->end_at !== null && now()->isAfter($this->end_at);
    }

    public function isJudge(User $user)
    {
        return $this->judges()->where('challenge_judges.user_id', $user->id)->where('challenge_judges.status', 'accepted')->exists();
    }

    public function isParticipant(User $user)
    {
        return $this->participants()->where('challenge_participants.user_id', $user->id)->exists();
    }
}
