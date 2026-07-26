<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['last_message_at'];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(Engagement::class);
    }

    public function activeEngagement(): HasOne
    {
        return $this->hasOne(Engagement::class)->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function getOtherUserAttribute()
    {
        $userId = auth()->id();
        if (!$userId) {
            return $this->users->first();
        }
        return $this->users->where('id', '!=', $userId)->first();
    }
}
