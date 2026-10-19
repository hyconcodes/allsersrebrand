<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'value'];

    /**
     * Mail driver values the admin toggle may set for config('mail.default').
     */
    public const MAIL_DRIVERS = ['smtp', 'mailtrap'];

    /**
     * Per-request memoization of resolved keys (Cache store is wiped by the
     * admin /clear-all-cache route, so settings must not live there).
     *
     * @var array<string, string|null>
     */
    protected static array $resolved = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, static::$resolved)) {
            return static::$resolved[$key] ?? $default;
        }

        try {
            $value = static::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            // Table missing (fresh install, mid-migration) — fall back.
            $value = null;
        }

        static::$resolved[$key] = $value;

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        static::$resolved[$key] = $value;
    }

    /**
     * Apply the persisted admin mail-driver toggle to config('mail.default').
     * Called at boot; when no setting row exists the configured default
     * (.env MAIL_MAILER) is left untouched.
     */
    public static function applyMailDriver(): void
    {
        $driver = static::get('mail_driver');

        if ($driver !== null && in_array($driver, self::MAIL_DRIVERS, true)) {
            config(['mail.default' => $driver]);
        }
    }

    /**
     * Clear the per-request memo (used by tests after seeding rows directly).
     */
    public static function flushResolved(): void
    {
        static::$resolved = [];
    }
}
