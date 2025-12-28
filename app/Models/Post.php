<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'content',
        'images',
        'video',
        'repost_of_id',
    ];

    public function repostOf()
    {
        return $this->belongsTo(Post::class, 'repost_of_id');
    }

    protected $casts = [
        // Images will be stored as comma-separated string
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->post_id)) {
                $model->post_id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function isLikedBy($user)
    {
        if (!$user) {
            return false;
        }

        if ($this->relationLoaded('likes')) {
            return $this->likes->contains('user_id', $user->id);
        }

        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id'); // Only top-level comments
    }

    public function allComments()
    {
        return $this->hasMany(Comment::class);
    }

    public function likesCount()
    {
        return $this->likes_count ?? $this->likes()->count();
    }

    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function isBookmarkedBy($user)
    {
        if (!$user) {
            return false;
        }

        if ($this->relationLoaded('bookmarks')) {
            return $this->bookmarks->contains('user_id', $user->id);
        }

        return $this->bookmarks()->where('user_id', $user->id)->exists();
    }

    public function commentsCount()
    {
        return $this->all_comments_count ?? $this->allComments()->count();
    }

    /**
     * Get the formatted content with clickable links.
     */
    public function getFormattedContentAttribute(): string
    {
        return $this->formatContent($this->content);
    }

    /**
     * Get the formatted content summary (truncated) with clickable links.
     */
    public function getFormattedContentSummaryAttribute(): string
    {
        return $this->formatContent(\Illuminate\Support\Str::limit($this->content, 300));
    }

    /**
     * Helper to format content: trim, escape, and autolink.
     */
    private function formatContent(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        $escaped = e(trim($content));

        // 1. Convert URLs to links
        $urlPattern = '/(https?:\/\/[^\s<]+)/i';
        $urlReplacement = '<a href="$1" target="_blank" class="text-blue-500 hover:underline break-all">$1</a>';
        $escaped = preg_replace($urlPattern, $urlReplacement, $escaped);

        // 2. Convert @mentions to links
        // Matches @username at start of string or after whitespace
        $mentionPattern = '/(^|\s)@([a-zA-Z0-9_]+)/';

        // We use a callback to generate the route properly
        $escaped = preg_replace_callback($mentionPattern, function ($matches) {
            $whitespace = $matches[1];
            $username = $matches[2];
            $url = route('artisan.profile', $username);
            return $whitespace . '<a href="' . $url . '" class="text-[var(--color-brand-purple)] font-bold hover:underline">@' . $username . '</a>';
        }, $escaped);

        return $escaped;
    }
}
