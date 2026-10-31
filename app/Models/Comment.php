<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'post_id',
        'parent_id',
        'content',
        'images',
        'video',
    ];

    /**
     * Comment images as an array (stored comma-separated like posts).
     */
    public function imageArray(): array
    {
        if (empty($this->images)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($img) => trim((string) $img),
            is_array($this->images) ? $this->images : explode(',', (string) $this->images)
        )));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }
}
