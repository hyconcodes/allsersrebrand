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
     * Settings key holding the JSON list of admin ping-alert recipients.
     * Each entry: { email: string, admin_id: int } — one email per admin.
     */
    public const PING_ALERT_EMAILS_KEY = 'ping_alert_emails';

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

    /**
     * All registered ping-alert recipients.
     *
     * @return array<int, array{email: string, admin_id: int}>
     */
    public static function getPingAlertEmails(): array
    {
        $raw = static::get(self::PING_ALERT_EMAILS_KEY);

        if (blank($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, fn ($entry) => is_array($entry) && filled($entry['email'] ?? null)));
    }

    /**
     * Plain list of email addresses to alert on every ping.
     *
     * @return array<int, string>
     */
    public static function pingAlertEmailAddresses(): array
    {
        return array_values(array_unique(array_map(
            fn (array $entry) => strtolower(trim($entry['email'])),
            static::getPingAlertEmails(),
        )));
    }

    /**
     * Attach a ping-alert email for an admin. One email per admin.
     *
     * @return array{ok: bool, message: string}
     */
    public static function addPingAlertEmail(string $email, int $adminId): array
    {
        $email = strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'That is not a valid email address.'];
        }

        $entries = static::getPingAlertEmails();

        foreach ($entries as $entry) {
            if (strtolower($entry['email']) === $email) {
                return ['ok' => false, 'message' => 'That email is already on the ping alert list.'];
            }

            if ((int) ($entry['admin_id'] ?? 0) === $adminId) {
                return ['ok' => false, 'message' => 'You already have a ping alert email. Remove it first to add a new one.'];
            }
        }

        $entries[] = ['email' => $email, 'admin_id' => $adminId];
        static::set(self::PING_ALERT_EMAILS_KEY, json_encode($entries));

        return ['ok' => true, 'message' => 'Ping alert email added.'];
    }

    /**
     * Remove a ping-alert email from the list.
     *
     * @return array{ok: bool, message: string}
     */
    public static function removePingAlertEmail(string $email): array
    {
        $email = strtolower(trim($email));
        $entries = static::getPingAlertEmails();
        $filtered = array_values(array_filter(
            $entries,
            fn (array $entry) => strtolower($entry['email']) !== $email,
        ));

        if (count($filtered) === count($entries)) {
            return ['ok' => false, 'message' => 'That email is not on the ping alert list.'];
        }

        static::set(self::PING_ALERT_EMAILS_KEY, json_encode($filtered));

        return ['ok' => true, 'message' => 'Ping alert email removed.'];
    }
}
