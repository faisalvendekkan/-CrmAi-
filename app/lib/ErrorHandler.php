<?php
declare(strict_types=1);

final class ErrorHandler
{
    public static function php(int $no, string $str, string $file, int $line): bool
    {
        if (!(error_reporting() & $no)) {
            return false;
        }
        throw new ErrorException($str, 0, $no, $file, $line);
    }

    public static function handle(Throwable $e): void
    {
        self::log($e);
        if (headers_sent()) {
            echo "\n<!-- error -->";
            return;
        }
        http_response_code(500);
        $json = str_contains($_SERVER["HTTP_ACCEPT"] ?? "", "application/json")
            || ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "") === "fetch";
        if ($json) {
            header("Content-Type: application/json");
            echo json_encode(["ok" => false, "error" => "Server error. The details were written to the error log."]);
            return;
        }
        $msg = $e instanceof PDOException
            ? "The database is not reachable right now. Check the MySQL server in hPanel and try again."
            : "Something went wrong on the server. The details were written to the error log.";
        try {
            View::render("errors/error", ["code" => 500, "message" => $msg], "layouts/bare");
        } catch (Throwable) {
            echo "<h1>Server error</h1><p>" . htmlspecialchars($msg) . "</p>";
        }
    }

    public static function log(Throwable $e): void
    {
        $line = sprintf("[%s] %s: %s in %s:%d\n%s\n\n", date("c"), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        $dir  = Config::dataDir() ?? Config::writableDir();
        if ($dir && (is_dir($dir . "/logs") || @mkdir($dir . "/logs", 0700, true))) {
            @file_put_contents($dir . "/logs/error.log", $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }
}
