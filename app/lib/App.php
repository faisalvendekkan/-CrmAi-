<?php
declare(strict_types=1);

final class App
{
    public static string $route = "";
    public static string $nonce = "";

    /** [method, pattern, handler]. {id} = digits, {key} = module key. */
    private const ROUTES = [
        ["GET",  "setup",                         "SetupController@index"],
        ["POST", "setup/check-database",          "SetupController@checkDatabase"],
        ["POST", "setup",                         "SetupController@install"],

        ["GET",  "login",                         "AuthController@loginForm"],
        ["POST", "login",                         "AuthController@login"],
        ["POST", "logout",                        "AuthController@logout"],
        ["GET",  "password",                      "AuthController@passwordForm"],
        ["POST", "password",                      "AuthController@passwordSave"],

        ["GET",  "",                              "DashboardController@index"],
        ["GET",  "profile",                       "ProfileController@index"],
        ["POST", "profile",                       "ProfileController@save"],

        ["GET",  "employees/import-template",        "ModuleController@employeeImportTemplate"],
        ["POST", "employees/import",                 "ModuleController@employeeImport"],
        ["GET",  "employees/{id}",                "EmployeeController@show"],
        ["POST", "leave/{id}/decide",             "LeaveController@decide"],

        ["GET",  "attendance",                    "AttendanceController@roster"],
        ["POST", "attendance/mark",               "AttendanceController@mark"],
        ["POST", "attendance/mark-all",           "AttendanceController@markAll"],

        ["GET",  "candidates",                    "CandidateController@index"],
        ["GET",  "candidates/new",                "CandidateController@create"],
        ["POST", "candidates/save",               "CandidateController@store"],
        ["GET",  "candidates/{id}",               "CandidateController@show"],
        ["POST", "candidates/{id}/status",        "CandidateController@status"],
        ["POST", "candidates/{id}/analyse",       "CandidateController@analyse"],
        ["POST", "candidates/{id}/delete",        "CandidateController@delete"],

        ["GET",  "alerts",                        "NotificationController@index"],
        ["POST", "alerts/settings",               "NotificationController@save"],
        ["GET",  "api/alerts/browser",            "NotificationController@browser"],

        ["GET",  "workspace",                     "WorkspaceController@index"],
        ["POST", "workspace/save",                "WorkspaceController@save"],
        ["POST", "workspace/{id}/pin",            "WorkspaceController@pin"],
        ["POST", "workspace/{id}/delete",         "WorkspaceController@delete"],

        ["GET",  "ai-tools",                      "ToolController@ai"],
        ["GET",  "apps",                          "ToolController@apps"],
        ["POST", "tools/save",                    "ToolController@save"],
        ["POST", "tools/{id}/delete",             "ToolController@delete"],

        ["GET",  "users",                         "UserController@index"],
        ["GET",  "users/new",                     "UserController@form"],
        ["GET",  "users/{id}/edit",               "UserController@form"],
        ["POST", "users/save",                    "UserController@save"],
        ["POST", "users/{id}/delete",             "UserController@delete"],
        ["POST", "users/{id}/sign-out",           "UserController@signOut"],

        ["POST", "mcp",                           "McpController@handle"],

        ["GET",  "settings",                      "SettingsController@index"],
        ["POST", "settings",                      "SettingsController@save"],
        ["POST", "settings/ai-models",            "SettingsController@aiModels"],
        ["POST", "settings/ai-test",              "SettingsController@aiTest"],
        ["GET",  "settings/backup",               "SettingsController@backup"],
        ["GET",  "activity",                      "SettingsController@activity"],

        ["GET",  "api/search",                    "SearchController@search"],
        ["POST", "api/ai/chat",                   "AiController@chat"],

        // Generic record modules (employees, documents, emp-documents, assets, tasks, leave, attendance/records)
        ["GET",  "{key}",                         "ModuleController@index"],
        ["GET",  "{key}/new",                     "ModuleController@form"],
        ["GET",  "{key}/export",                  "ModuleController@export"],
        ["GET",  "{key}/{id}/edit",               "ModuleController@form"],
        ["POST", "{key}/save",                    "ModuleController@save"],
        ["POST", "{key}/{id}/delete",             "ModuleController@delete"],
    ];

    public static function run(): void
    {
        self::$nonce = base64_encode(random_bytes(16));
        self::headers();
        Session::start();

        $path   = self::path();
        $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
        if ($method === "HEAD") {
            $method = "GET";
        }

        // First run: everything goes to the setup wizard until it is finished.
        if (!Config::installed()) {
            if (!str_starts_with($path, "setup")) {
                redirect("setup");
            }
        } else {
            if (str_starts_with($path, "setup")) {
                abort(404); // setup is permanently closed after installation
            }
            date_default_timezone_set((string) setting("timezone", "Asia/Qatar"));
            Migrator::ensure();
        }

        if ($method === "POST" && $path !== "mcp") {
            Csrf::verify();
        }

        foreach (self::ROUTES as [$m, $pattern, $handler]) {
            if ($m !== $method) {
                continue;
            }
            $params = self::match($pattern, $path);
            if ($params === null) {
                continue;
            }
            self::$route = $pattern;
            [$class, $action] = explode("@", $handler);
            (new $class())->$action(...$params);
            return;
        }
        abort(404);
    }

    private static function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace(
            ["#\\\\\{id\\\\\}#", "#\\\\\{key\\\\\}#"],
            ["(\\d+)", "([a-z][a-z\\-]*(?:/records)?)"],
            preg_quote($pattern, "#")
        );
        if (!preg_match("#^" . $regex . "$#", $path, $m)) {
            return null;
        }
        array_shift($m);
        $args = [];
        preg_match_all("/\{(id|key)\}/", $pattern, $names);
        foreach ($names[1] as $i => $name) {
            $args[] = $name === "id" ? (int) $m[$i] : $m[$i];
        }
        return $args;
    }

    private static function path(): string
    {
        $uri  = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?: "/";
        $uri  = rawurldecode($uri);
        $base = base_path();
        if ($base !== "" && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = preg_replace("#^/?index\.php#", "", $uri);
        return trim((string) $uri, "/");
    }

    private static function headers(): void
    {
        header_remove("X-Powered-By");
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");
        header("Cross-Origin-Opener-Policy: same-origin");
        header(
            "Content-Security-Policy: default-src \x27self\x27; " .
            "script-src \x27self\x27 \x27nonce-" . self::$nonce . "\x27; " .
            "style-src \x27self\x27 \x27unsafe-inline\x27; " .
            "img-src \x27self\x27 data:; font-src \x27self\x27; connect-src \x27self\x27; " .
            "worker-src \x27self\x27 blob:; object-src \x27none\x27; base-uri \x27self\x27; " .
            "form-action \x27self\x27; frame-ancestors \x27none\x27"
        );
        if (is_https()) {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
    }
}
