<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Engagement extends Model
{
    protected $fillable = [
        'user_id',
        'artisan_id',
        'conversation_id',
        'status',
        'is_public',
        'title',
        'location_context',
        'urgency_level',
        'inquiry_photos',
        'showcase_description',
        'showcase_photos',
        'price_estimate',
        'completion_estimate',
        'confirmed_at',
        'completed_at',
        'review_id',
    ];

    protected $casts = [
        'inquiry_photos' => 'array',
        'showcase_photos' => 'array',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Voice notice calls placed for this ping (usually one row that
     * carries the whole attempt chain).
     */
    public function voiceAnnouncements(): HasMany
    {
        return $this->hasMany(VoiceAnnouncement::class);
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
