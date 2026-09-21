<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION["_csrf"])) {
            $_SESSION["_csrf"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["_csrf"];
    }

    public static function verify(): void
    {
        $sent = $_POST["_csrf"] ?? ($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "");
        if (!is_string($sent) || empty($_SESSION["_csrf"]) || !hash_equals($_SESSION["_csrf"], $sent)) {
            abort(419);
        }
        // Same-origin check as a second layer
        $origin = $_SERVER["HTTP_ORIGIN"] ?? "";
        if ($origin !== "") {
            $host = parse_url($origin, PHP_URL_HOST);
            if ($host && strcasecmp($host, (string) parse_url("//" . ($_SERVER["HTTP_HOST"] ?? ""), PHP_URL_HOST)) !== 0) {
                abort(419);
            }
        }
    }
}
