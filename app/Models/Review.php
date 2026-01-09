<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'reviewer_id',
        'artisan_id',
        'rating',
        'comment',
        'ip_address',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function artisan()
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}
