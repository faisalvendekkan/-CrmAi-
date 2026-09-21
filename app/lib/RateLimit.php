<?php
declare(strict_types=1);

final class RateLimit
{
    /** Login throttling: 5 failures per account or 20 per IP within 15 minutes. */
    public static function loginBlocked(string $email): bool
    {
        $since = date("Y-m-d H:i:s", time() - 900);
        $byEmail = (int) DB::val("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND created_at > ?", [$email, $since]);
        $byIp    = (int) DB::val("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND created_at > ?", [client_ip(), $since]);
        return $byEmail >= 5 || $byIp >= 20;
    }

    public static function recordLogin(string $email, bool $success): void
    {
        DB::insert("login_attempts", ["email" => mb_substr($email, 0, 190), "ip" => client_ip(), "success" => $success ? 1 : 0]);
        if ($success) {
            DB::run("DELETE FROM login_attempts WHERE email = ? AND success = 0", [$email]);
        }
        if (random_int(1, 50) === 1) {
            DB::run("DELETE FROM login_attempts WHERE created_at < ?", [date("Y-m-d H:i:s", time() - 86400 * 30)]);
        }
    }

    /** AI requests: 40 per user per 10 minutes. */
    public static function aiAllowed(int $userId): bool
    {
        $since = date("Y-m-d H:i:s", time() - 600);
        return (int) DB::val("SELECT COUNT(*) FROM ai_usage WHERE user_id = ? AND created_at > ?", [$userId, $since]) < 40;
    }

    public static function recordAi(int $userId, string $kind): void
    {
        DB::insert("ai_usage", ["user_id" => $userId, "kind" => mb_substr($kind, 0, 30)]);
    }
}
