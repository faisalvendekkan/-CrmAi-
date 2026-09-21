<?php
declare(strict_types=1);

final class Auth
{
    /** Permission keys users can be granted, with display labels. */
    public const PERMISSIONS = [
        "employees"     => "Employees",
        "candidates"    => "CV screening",
        "leave"         => "Leave",
        "attendance"    => "Attendance",
        "documents"     => "Company documents",
        "emp_documents" => "Employee documents",
        "assets"        => "Vehicles & assets",
        "tasks"         => "Admin tasks",
        "notifications" => "Expiry alerts",
        "ai_tools"      => "AI integrations",
        "apps"          => "Applications",
        "assistant"     => "AI assistant",
    ];

    public const ROLES = [
        "admin"   => "Administrator",
        "manager" => "Manager",
        "viewer"  => "Viewer",
    ];

    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;
        $id = $_SESSION["uid"] ?? null;
        if (!$id || !Config::installed()) {
            return null;
        }

        $idle = max(10, (int) setting("session_minutes", 120)) * 60;
        $now  = time();
        if (($now - (int) ($_SESSION["last_seen"] ?? 0)) > $idle
            || ($now - (int) ($_SESSION["login_at"] ?? 0)) > 43200
            || ($_SESSION["ua"] ?? "") !== self::agentHash()) {
            self::logout(false);
            flash("info", "You were signed out after a period of inactivity. Sign in again to continue.");
            return null;
        }

        $u = DB::one("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$u || $u["status"] !== "active" || (int) $u["session_version"] !== (int) ($_SESSION["sv"] ?? -1)) {
            self::logout(false);
            return null;
        }
        $u["perms"] = json_decode((string) $u["permissions"], true) ?: [];
        $_SESSION["last_seen"] = $now;
        if ($now - strtotime((string) ($u["last_active_at"] ?? "2000-01-01")) > 120) {
            DB::run("UPDATE users SET last_active_at = NOW() WHERE id = ?", [$id]);
        }
        return self::$user = $u;
    }

    public static function attempt(string $email, string $password): string
    {
        $email = mb_strtolower(trim($email));
        if (RateLimit::loginBlocked($email)) {
            return "locked";
        }
        $u = DB::one("SELECT * FROM users WHERE email = ?", [$email]);
        // Verify against a dummy hash when the user does not exist, to keep timing uniform.
        $hash = $u["password_hash"] ?? "\$2y\$12\$usesomesillystringforsaltuVsO2oW6i0HWRmZ2eKLk3T5b5YfUy";
        $ok   = password_verify($password, $hash) && $u;
        RateLimit::recordLogin($email, (bool) $ok);
        if (!$ok) {
            return "invalid";
        }
        if ($u["status"] !== "active") {
            return "suspended";
        }
        if (password_needs_rehash($u["password_hash"], self::algo())) {
            DB::run("UPDATE users SET password_hash = ? WHERE id = ?", [self::hash($password), $u["id"]]);
        }
        Session::regenerate();
        $_SESSION["uid"]       = (int) $u["id"];
        $_SESSION["sv"]        = (int) $u["session_version"];
        $_SESSION["login_at"]  = time();
        $_SESSION["last_seen"] = time();
        $_SESSION["ua"]        = self::agentHash();
        DB::run("UPDATE users SET last_login_at = NOW(), last_active_at = NOW() WHERE id = ?", [$u["id"]]);
        self::$resolved = false;
        Activity::log("signed_in", "auth", null, "Signed in", (int) $u["id"]);
        return "ok";
    }

    public static function logout(bool $log = true): void
    {
        if ($log && ($id = $_SESSION["uid"] ?? null)) {
            Activity::log("signed_out", "auth", null, "Signed out", (int) $id);
        }
        unset($_SESSION["uid"], $_SESSION["sv"], $_SESSION["login_at"], $_SESSION["last_seen"], $_SESSION["ua"]);
        Session::regenerate();
        self::$user = null;
        self::$resolved = true;
    }

    public static function isAdmin(): bool
    {
        return (self::user()["role"] ?? "") === "admin";
    }

    public static function can(string $perm, string $level = "view"): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        if ($u["role"] === "admin") {
            return true;
        }
        if ($perm === "admin") {
            return false;
        }
        $granted = $u["perms"][$perm] ?? "none";
        if ($u["role"] === "viewer" && $granted === "edit") {
            $granted = "view";
        }
        return $level === "view" ? in_array($granted, ["view", "edit"], true) : $granted === "edit";
    }

    public static function require(string $perm = "", string $level = "view"): array
    {
        $u = self::user();
        if (!$u) {
            if (is_post() || ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch") {
                abort(401, "Your session ended. Sign in again.");
            }
            $_SESSION["intended"] = $_SERVER["REQUEST_URI"] ?? null;
            redirect("login");
        }
        if ((int) $u["must_change_password"] === 1 && !in_array(App::$route, ["password", "logout"], true)) {
            redirect("password");
        }
        if ($perm !== "" && !self::can($perm, $level)) {
            abort(403);
        }
        return $u;
    }

    public static function algo(): string|int
    {
        return defined("PASSWORD_ARGON2ID") ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo());
    }

    /** Returns an error message, or null if the password is strong enough. */
    public static function passwordProblem(string $password, string $email = ""): ?string
    {
        if (mb_strlen($password) < 10) {
            return "Use at least 10 characters for the password.";
        }
        if (!preg_match("/[A-Za-z]/", $password) || !preg_match("/\d/", $password)) {
            return "Use both letters and numbers in the password.";
        }
        $lower = mb_strtolower($password);
        foreach (["password", "123456", "qwerty", "admin", "letmein", "welcome"] as $weak) {
            if (str_contains($lower, $weak)) {
                return "This password is too easy to guess. Avoid common words like \"{$weak}\".";
            }
        }
        if ($email !== "" && str_contains($lower, mb_strtolower(strtok($email, "@")))) {
            return "Do not include your email name in the password.";
        }
        return null;
    }

    private static function agentHash(): string
    {
        return hash("sha256", (string) ($_SERVER["HTTP_USER_AGENT"] ?? ""));
    }
}
