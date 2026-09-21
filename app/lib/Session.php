<?php
declare(strict_types=1);

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set("session.use_strict_mode", "1");
        ini_set("session.use_only_cookies", "1");
        ini_set("session.cookie_httponly", "1");
        ini_set("session.sid_length", "48");
        ini_set("session.sid_bits_per_character", "6");
        ini_set("session.gc_maxlifetime", "43200");
        ini_set("session.gc_probability", "1");
        ini_set("session.gc_divisor", "100");

        self::usePrivatePath();

        session_name(is_https() ? "__Host-mhr" : "mhr_session");
        session_set_cookie_params([
            "lifetime" => 0,
            "path"     => "/",
            "secure"   => is_https(),
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        session_start();
    }

    /** Store sessions in the private data folder when it exists (isolated from other sites). */
    private static function usePrivatePath(): void
    {
        $dir = Config::dataDir();
        if ($dir && (is_dir($dir . "/sessions") || @mkdir($dir . "/sessions", 0700, true)) && is_writable($dir . "/sessions")) {
            session_save_path($dir . "/sessions");
        }
    }

    /** Called once by the setup wizard after the private folder is created. */
    public static function relocate(): void
    {
        $data = $_SESSION;
        $id   = session_id();
        session_write_close();
        self::usePrivatePath();
        session_id($id);
        session_start();
        $_SESSION = $data;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        unset($_SESSION["_csrf"]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), "", [
                "expires" => time() - 42000, "path" => $p["path"], "secure" => $p["secure"],
                "httponly" => true, "samesite" => $p["samesite"] ?? "Lax",
            ]);
        }
        session_destroy();
    }
}
