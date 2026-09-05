<?php
declare(strict_types=1);

/** Eine einzige PDO-Verbindung fuer die gesamte Anwendung. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            /* Ueber einen Socket, wenn einer angegeben ist. Auf dem Webspace
               steht dort nichts und es bleibt beim Wirtsnamen; in der
               Werkstatt und im Kettentest laeuft MariaDB ueber einen Socket,
               und ohne diese Zeile waere der Test nicht auf demselben Weg zu
               fuehren wie die Anwendung. */
            $dsn = !empty($c['socket'])
                ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $c['socket'], $c['name'])
                : sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['name']);
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** @param array<string,mixed>|list<mixed> $args */
    public static function run(string $sql, array $args = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st;
    }

    public static function all(string $sql, array $args = []): array
    {
        return self::run($sql, $args)->fetchAll();
    }

    public static function one(string $sql, array $args = []): ?array
    {
        $r = self::run($sql, $args)->fetch();
        return $r === false ? null : $r;
    }

    public static function wert(string $sql, array $args = [], mixed $default = 0): mixed
    {
        $r = self::run($sql, $args)->fetchColumn();
        return $r === false || $r === null ? $default : $r;
    }

    public static function insert(string $table, array $daten): int
    {
        $spalten = array_keys($daten);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', array_map(static fn($s) => "`$s`", $spalten)),
            implode(',', array_map(static fn($s) => ":$s", $spalten))
        );
        self::run($sql, $daten);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, int $id, array $daten): void
    {
        if (!$daten) { return; }
        $sets = implode(',', array_map(static fn($s) => "`$s` = :$s", array_keys($daten)));
        $daten['__id'] = $id;
        self::run("UPDATE `$table` SET $sets WHERE id = :__id", $daten);
    }

    public static function transaktion(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $ergebnis = $fn();
            $pdo->commit();
            return $ergebnis;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
