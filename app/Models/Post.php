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
        'challenge_id',
        'is_challenge_pinned',
        'price_min',
        'price_max',
    ];

    protected $withCount = ['likes', 'allComments'];

    public function repostOf()
    {
        return $this->belongsTo(Post::class, 'repost_of_id');
    }

    protected $casts = [
        'is_challenge_pinned' => 'boolean',
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function ratings()
    {
        return $this->hasMany(ChallengeRating::class);
    }

    public function averageRating()
    {
        return $this->ratings()->avg('rating');
    }

    /**
     * Check if this post can be reposted.
     * Challenge posts cannot be reposted per requirements.
     */
    public function canBeReposted(): bool
    {
        return is_null($this->challenge_id);
    }

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

        $escaped = e(trim($content), false);

        // 1. Convert URLs to links
        $urlPattern = '/(https?:\/\/[^\s<]+)/i';
        $urlReplacement = '<a href="$1" target="_blank" class="text-blue-500 hover:underline break-all">$1</a>';
        $escaped = preg_replace($urlPattern, $urlReplacement, $escaped);

        // 2. Convert @mentions to links
        $mentionPattern = '/(^|\s)@([a-zA-Z0-9_]+)/';
        $usernames = [];
        preg_match_all($mentionPattern, $content, $mentionMatches);
        foreach ($mentionMatches[2] as $username) {
            $usernames[] = $username;
        }
        $usernames = array_unique($usernames);

        $userSlugs = [];
        if (!empty($usernames)) {
            $userSlugs = \App\Models\User::whereIn('username', $usernames)
                ->pluck('slug', 'username')
                ->toArray();
        }

        $escaped = preg_replace_callback($mentionPattern, function ($matches) use ($userSlugs) {
            $whitespace = $matches[1];
            $username = $matches[2];

            if (isset($userSlugs[$username])) {
                $url = route('artisan.profile', ['user' => $userSlugs[$username]]);
                return $whitespace . '<a href="' . $url . '" class="text-[var(--color-brand-purple)] font-bold hover:underline">@' . $username . '</a>';
            }

            return $whitespace . '@' . $username;
        }, $escaped);

        // 3. Convert #hashtags to links
        $hashtagPattern = '/(^|\s)#([a-zA-Z0-9_]+)/';
        $hashtags = [];
        preg_match_all($hashtagPattern, $content, $hashtagMatches);
        foreach ($hashtagMatches[2] as $hashtag) {
            $hashtags[] = $hashtag;
        }
        $hashtags = array_unique($hashtags);

        $challengeLinks = [];
        if (!empty($hashtags)) {
            $challengeLinks = \App\Models\Challenge::whereIn('hashtag', $hashtags)
                ->pluck('custom_link', 'hashtag')
                ->toArray();
        }

        $escaped = preg_replace_callback($hashtagPattern, function ($matches) use ($challengeLinks) {
            $whitespace = $matches[1];
            $hashtag = $matches[2];

            if (isset($challengeLinks[$hashtag])) {
                $url = route('challenges.show', $challengeLinks[$hashtag]);
                return $whitespace . '<a href="' . $url . '" class="text-blue-500 font-bold hover:underline">#' . $hashtag . '</a>';
            }
            return $whitespace . '<span class="text-blue-400">#' . $hashtag . '</span>';
        }, $escaped);

        return $escaped;
    }
}
