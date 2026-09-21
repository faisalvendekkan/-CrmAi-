<?php
declare(strict_types=1);

final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        "company_name"        => "Your Company",
        "timezone"            => "Asia/Qatar",
        "annual_leave_days"   => "21",
        "session_minutes"     => "120",
        "alert_window"        => "30",
        "alert_include_expired" => "1",
        "alert_categories"    => "employees,documents,emp_documents,assets,tasks",
        "alert_browser"       => "0",
        "digest_enabled"      => "0",
        "digest_recipients"   => "",
        "ai_enabled"          => "0",
        "ai_provider"         => "anthropic",
        "ai_model"            => "",
        "ai_key"              => "",
        "ai_share_data"       => "1",
        "mcp_enabled"         => "0",
        "mcp_token_hash"      => "",
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::DEFAULTS;
            try {
                foreach (DB::all("SELECT k, v FROM settings") as $row) {
                    self::$cache[$row["k"]] = $row["v"];
                }
            } catch (Throwable) {
                // settings table not ready yet
            }
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        DB::run("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)", [$key, $value]);
        self::all();
        self::$cache[$key] = $value;
    }

    public static function many(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            self::set((string) $k, $v === null ? null : (string) $v);
        }
    }
}
