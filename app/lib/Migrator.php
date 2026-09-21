<?php
declare(strict_types=1);

/**
 * Database schema versioning.
 *
 * To change the database after going live, add a new file in app/migrations
 * (e.g. 002_add_salary.php) and push to GitHub. The next page load applies it once.
 */
final class Migrator
{
    public static function files(): array
    {
        $files = glob(APP_PATH . "/migrations/*.php") ?: [];
        sort($files);
        $out = [];
        foreach ($files as $f) {
            if (preg_match("/^(\d+)_/", basename($f), $m)) {
                $out[(int) $m[1]] = $f;
            }
        }
        return $out;
    }

    public static function latest(): int
    {
        $f = self::files();
        return $f ? max(array_keys($f)) : 0;
    }

    public static function ensure(): void
    {
        $current = (int) Settings::get("schema_version", 0);
        if ($current >= self::latest()) {
            return;
        }
        self::migrate($current);
    }

    public static function migrate(int $from = 0): void
    {
        $pdo    = DB::pdo();
        $locked = (int) DB::val("SELECT GET_LOCK(\x27meridian_migrate\x27, 10)") === 1;
        try {
            $from = max($from, (int) (DB::val("SELECT v FROM settings WHERE k = \x27schema_version\x27") ?? 0));
        } catch (Throwable) {
            // settings table does not exist yet
        }
        try {
            foreach (self::files() as $version => $file) {
                if ($version <= $from) {
                    continue;
                }
                $steps = require $file;
                foreach ($steps as $step) {
                    is_callable($step) ? $step($pdo) : $pdo->exec($step);
                }
                Settings::set("schema_version", (string) $version);
            }
        } finally {
            if ($locked) {
                DB::val("SELECT RELEASE_LOCK(\x27meridian_migrate\x27)");
            }
        }
    }
}
