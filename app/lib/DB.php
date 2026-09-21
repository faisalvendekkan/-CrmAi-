<?php
declare(strict_types=1);

final class DB
{
    private static ?PDO $pdo = null;

    public static function connect(array $c): PDO
    {
        $host = $c["host"] ?? "localhost";
        $port = (int) ($c["port"] ?? 3306);
        $dsn  = "mysql:host={$host};port={$port};dbname={$c["name"]};charset=utf8mb4";
        $pdo  = new PDO($dsn, (string) $c["user"], (string) $c["pass"], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        $offset = (new DateTimeImmutable())->format("P");
        $pdo->exec("SET time_zone = " . $pdo->quote($offset));
        $pdo->exec("SET SESSION sql_mode = \"STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION\"");
        return $pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            $db = Config::get("db");
            if (!$db) {
                throw new RuntimeException("Database is not configured.");
            }
            self::$pdo = self::connect($db);
        }
        return self::$pdo;
    }

    public static function use(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        self::guard($table, array_keys($data));
        $cols = implode(", ", array_map(fn ($c) => "`$c`", array_keys($data)));
        $ph   = implode(", ", array_fill(0, count($data), "?"));
        self::run("INSERT INTO `$table` ($cols) VALUES ($ph)", array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, int $id): void
    {
        self::guard($table, array_keys($data));
        $set = implode(", ", array_map(fn ($c) => "`$c` = ?", array_keys($data)));
        self::run("UPDATE `$table` SET $set WHERE id = ?", [...array_values($data), $id]);
    }

    public static function delete(string $table, int $id): void
    {
        self::guard($table, []);
        self::run("DELETE FROM `$table` WHERE id = ?", [$id]);
    }

    /** Identifiers are never user input, but validate anyway. */
    private static function guard(string $table, array $cols): void
    {
        foreach ([$table, ...$cols] as $name) {
            if (!preg_match("/^[a-z][a-z0-9_]{0,63}$/", (string) $name)) {
                throw new InvalidArgumentException("Invalid identifier.");
            }
        }
    }
}
