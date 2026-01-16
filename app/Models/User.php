<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
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
        'country_code',
        'status',
        'last_activity',
        'banned_until',
        'smart_rating', // Weighted rating
        'onesignal_player_id',
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
            ? route('images.show', ['path' => $this->profile_picture])
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
        $adminEmails = ['hello@allsers.com', 'support@allsers.com'];

        return $this->is_admin === true || in_array($this->email, $adminEmails);
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
    public function reviews()
    {
        return $this->hasMany(Review::class, 'artisan_id');
    }

    /**
     * Recalculate and update the smart rating for this artisan.
     * Uses a weighted Bayesian average favoring recent reviews.
     */
    public function recalculateSmartRating()
    {
        $reviews = $this->reviews;
        $totalWeight = 0;
        $weightedSum = 0;

        // Constants for Bayesian smoothing (prevents 1 review of 5.0 from beating 100 reviews of 4.8)
        $C = 3.5; // Average rating of all artisans (Assumed constant for now)
        $m = 2;   // Minimum votes required to be listed (Weight of the prior)

        // Add the "prior" belief
        $weightedSum += $C * $m;
        $totalWeight += $m;

        foreach ($reviews as $review) {
            $daysOld = $review->created_at->diffInDays(now());

            // Time decay weights: Recent reviews matter more
            $weight = match (true) {
                $daysOld <= 30 => 1.2,  // High impact for last month
                $daysOld <= 90 => 1.0,  // Standard impact for last quarter
                default => 0.8          // Lower impact for older reviews
            };

            $weightedSum += $review->rating * $weight;
            $totalWeight += $weight;
        }

        $newRating = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;

        $this->update(['smart_rating' => min(5, max(0, $newRating))]);
    }

    public function averageRating()
    {
        return $this->reviews()->avg('rating') ?? 0;
    }

    /**
     * Get the currency symbol based on the user's country code.
     */
    public function getCurrencySymbolAttribute(): string
    {
        $symbols = [
            'AF' => '؋',
            'AL' => 'L',
            'DZ' => 'د.ج',
            'AS' => '$',
            'AD' => '€',
            'AO' => 'Kz',
            'AI' => '$',
            'AG' => '$',
            'AR' => '$',
            'AM' => '֏',
            'AW' => 'ƒ',
            'AU' => '$',
            'AT' => '€',
            'AZ' => '₼',
            'BS' => '$',
            'BH' => '.د.ب',
            'BD' => '৳',
            'BB' => '$',
            'BY' => 'Br',
            'BE' => '€',
            'BZ' => 'BZ$',
            'BJ' => 'Fr',
            'BM' => '$',
            'BT' => 'Nu.',
            'BO' => '$b',
            'BA' => 'KM',
            'BW' => 'P',
            'BV' => 'kr',
            'BR' => 'R$',
            'IO' => '$',
            'BN' => '$',
            'BG' => 'лв',
            'BF' => 'Fr',
            'BI' => 'Fr',
            'KH' => '៛',
            'CM' => 'Fr',
            'CA' => '$',
            'CV' => '$',
            'KY' => '$',
            'CF' => 'Fr',
            'TD' => 'Fr',
            'CL' => '$',
            'CN' => '¥',
            'CX' => '$',
            'CC' => '$',
            'CO' => '$',
            'KM' => 'Fr',
            'CG' => 'Fr',
            'CD' => 'Fr',
            'CK' => '$',
            'CR' => '₡',
            'CI' => 'Fr',
            'HR' => 'kn',
            'CU' => '₱',
            'CY' => '€',
            'CZ' => 'Kč',
            'DK' => 'kr',
            'DJ' => 'Fr',
            'DM' => '$',
            'DO' => 'RD$',
            'EC' => '$',
            'EG' => '£',
            'SV' => '$',
            'GQ' => 'Fr',
            'ER' => 'Nfk',
            'EE' => '€',
            'ET' => 'Br',
            'FK' => '£',
            'FO' => 'kr',
            'FJ' => '$',
            'FI' => '€',
            'FR' => '€',
            'GF' => '€',
            'PF' => 'Fr',
            'TF' => '€',
            'GA' => 'Fr',
            'GM' => 'D',
            'GE' => '₾',
            'DE' => '€',
            'GH' => '₵',
            'GI' => '£',
            'GR' => '€',
            'GL' => 'kr',
            'GD' => '$',
            'GP' => '€',
            'GU' => '$',
            'GT' => 'Q',
            'GG' => '£',
            'GN' => 'Fr',
            'GW' => 'Fr',
            'GY' => '$',
            'HT' => 'G',
            'HM' => '$',
            'VA' => '€',
            'HN' => 'L',
            'HK' => '$',
            'HU' => 'Ft',
            'IS' => 'kr',
            'IN' => '₹',
            'ID' => 'Rp',
            'IR' => '﷼',
            'IQ' => 'ع.د',
            'IE' => '€',
            'IM' => '£',
            'IL' => '₪',
            'IT' => '€',
            'JM' => 'J$',
            'JP' => '¥',
            'JE' => '£',
            'JO' => 'JD',
            'KZ' => '₸',
            'KE' => 'KSh',
            'KI' => '$',
            'KP' => '₩',
            'KR' => '₩',
            'KW' => 'KD',
            'KG' => 'лв',
            'LA' => '₭',
            'LV' => '€',
            'LB' => '£',
            'LS' => 'L',
            'LR' => '$',
            'LY' => 'LD',
            'LI' => 'CHF',
            'LT' => '€',
            'LU' => '€',
            'MO' => 'P',
            'MK' => 'ден',
            'MG' => 'Ar',
            'MW' => 'MK',
            'MY' => 'RM',
            'MV' => 'Rf',
            'ML' => 'Fr',
            'MT' => '€',
            'MH' => '$',
            'MQ' => '€',
            'MR' => 'UM',
            'MU' => '₨',
            'YT' => '€',
            'MX' => '$',
            'FM' => '$',
            'MD' => 'L',
            'MC' => '€',
            'MN' => '₮',
            'ME' => '€',
            'MS' => '$',
            'MA' => 'MAD',
            'MZ' => 'MT',
            'MM' => 'K',
            'NA' => '$',
            'NR' => '$',
            'NP' => '₨',
            'NL' => '€',
            'NC' => 'Fr',
            'NZ' => '$',
            'NI' => 'C$',
            'NE' => 'Fr',
            'NG' => '₦',
            'NU' => '$',
            'NF' => '$',
            'MP' => '$',
            'NO' => 'kr',
            'OM' => '﷼',
            'PK' => '₨',
            'PW' => '$',
            'PS' => '₪',
            'PA' => 'B/.',
            'PG' => 'K',
            'PY' => 'Gs',
            'PE' => 'S/.',
            'PH' => '₱',
            'PN' => '$',
            'PL' => 'zł',
            'PT' => '€',
            'PR' => '$',
            'QA' => '﷼',
            'RE' => '€',
            'RO' => 'lei',
            'RU' => '₽',
            'RW' => 'Fr',
            'BL' => '€',
            'SH' => '£',
            'KN' => '$',
            'LC' => '$',
            'MF' => '€',
            'PM' => '€',
            'VC' => '$',
            'WS' => 'T',
            'SM' => '€',
            'ST' => 'Db',
            'SA' => '﷼',
            'SN' => 'Fr',
            'RS' => 'Дин.',
            'SC' => '₨',
            'SL' => 'Le',
            'SG' => '$',
            'SK' => '€',
            'SI' => '€',
            'SB' => '$',
            'SO' => 'S',
            'ZA' => 'R',
            'GS' => '£',
            'ES' => '€',
            'LK' => '₨',
            'SD' => '£',
            'SR' => '$',
            'SJ' => 'kr',
            'SZ' => 'L',
            'SE' => 'kr',
            'CH' => 'CHF',
            'SY' => '£',
            'TW' => 'NT$',
            'TJ' => 'SM',
            'TZ' => 'TSh',
            'TH' => '฿',
            'TL' => '$',
            'TG' => 'Fr',
            'TK' => '$',
            'TO' => 'T$',
            'TT' => 'TT$',
            'TN' => 'د.ت',
            'TR' => '₺',
            'TM' => 'T',
            'TC' => '$',
            'TV' => '$',
            'UG' => 'USh',
            'UA' => '₴',
            'AE' => 'د.إ',
            'GB' => '£',
            'US' => '$',
            'UM' => '$',
            'UY' => '$U',
            'UZ' => 'лв',
            'VU' => 'Vt',
            'VE' => 'Bs',
            'VN' => '₫',
            'VG' => '$',
            'VI' => '$',
            'WF' => 'Fr',
            'EH' => 'MAD',
            'YE' => '﷼',
            'ZM' => 'ZK',
            'ZW' => 'Z$',
        ];

        return $symbols[strtoupper($this->country_code)] ?? '₦';
    }
}
