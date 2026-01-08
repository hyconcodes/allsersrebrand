<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable //implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_admin',
        'username',
        'profile_picture',
        'gender',
        'work',
        'bio',
        'experience_year',
        'work_status',
        'phone_number',
        'address',
        'latitude',
        'longitude',
        'status',
        'last_activity',
        'banned_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'last_activity' => 'datetime',
            'banned_until' => 'datetime',
            'is_admin' => 'boolean',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the URL to the user's profile picture.
     */
    public function getProfilePictureUrlAttribute(): ?string
    {
        return $this->profile_picture
            ? asset('storage/' . $this->profile_picture)
            : null;
    }

    /**
     * Check if the user is a guest (customer).
     */
    public function isGuest(): bool
    {
        return $this->role === 'guest';
    }

    /**
     * Check if the user is an artisan (provider).
     */
    public function isArtisan(): bool
    {
        return $this->role === 'artisan';
    }

    /**
     * Get the user's posts.
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the user's likes.
     */
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    /**
     * Get the user's comments.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get the user's bookmarks.
     */
    public function bookmarks()
    {
        return $this->hasMany(Bookmark::class);
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function unreadMessagesCount(): int
    {
        return Message::whereHas('conversation', function ($query) {
            $query->whereHas('users', function ($q) {
                $q->where('users.id', $this->id);
            });
        })
            ->where('user_id', '!=', $this->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Check if the user is currently banned.
     */
    public function isBanned(): bool
    {
        return $this->banned_until && $this->banned_until->isFuture();
    }

    /**
     * Get the user's reports.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function challenges()
    {
        return $this->hasMany(Challenge::class, 'creator_id');
    }

    public function participatingChallenges()
    {
        return $this->belongsToMany(Challenge::class, 'challenge_participants')
            ->withPivot('joined_at');
    }

    public function judgingChallenges()
    {
        return $this->belongsToMany(Challenge::class, 'challenge_judges')
            ->withPivot('status')
            ->withTimestamps();
    }

    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /**
     * Get profile completion statistics for artisans.
     */
    public function profileCompletion(): array
    {
        $fields = [
            'profile_picture' => 'Profile Picture',
            'gender' => 'Gender',
            'work' => 'Work Category',
            'bio' => 'Short Bio',
            'experience_year' => 'Years of Experience',
            'work_status' => 'Availability Status',
            'phone_number' => 'Phone Number',
            'address' => 'Home/Store Address',
        ];

        $completed = [];
        $missing = [];
        $filledCount = 0;

        foreach ($fields as $field => $label) {
            if (!empty($this->{$field})) {
                $completed[] = ['field' => $field, 'label' => $label];
                $filledCount++;
            } else {
                $missing[] = ['field' => $field, 'label' => $label];
            }
        }

        $percentage = ($filledCount / count($fields)) * 100;

        return [
            'percentage' => round($percentage),
            'completed' => $completed,
            'missing' => $missing,
            'is_complete' => $filledCount === count($fields),
        ];
    }
}
