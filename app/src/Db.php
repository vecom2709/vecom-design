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

    /* ======================================================================
       WENN ZWEI BESUCHER IM SELBEN AUGENBLICK SCHREIBEN

       Fast jede Nummer und jeder Kunde entsteht in zwei Schritten: nachsehen,
       ob es das schon gibt oder was die hoechste Nummer ist -- und dann
       schreiben. Zwischen diese beiden Schritte passt ein zweiter Besucher.
       Beide lesen dieselbe hoechste Nummer, beide schreiben sie.

       Dagegen steht der eindeutige Schluessel in der Datenbank, und der tut
       genau, was er soll: Er laesst den zweiten nicht durch. Nur war das
       bisher das Ende der Geschichte -- der zweite Besucher bekam eine
       Ausnahme ins Gesicht, und seine Anfrage, seine Bestellung oder sein
       Beleg entstand nie.

       Der Schluessel ist die Sicherung, nicht die Loesung. Die Loesung ist,
       den Fehler zu erkennen und es noch einmal zu versuchen -- mit der
       Nummer, die jetzt frei ist. Diese beiden Helfer sind dafuer da.
       ====================================================================== */

    /**
     * Ist das ein „gibt es schon“-Fehler? Optional: an genau diesem Schluessel?
     *
     * Der Name des Schluessels steht im Text der Meldung -- das ist die
     * einzige Stelle, an der MySQL ihn verraet, und ohne ihn kann man
     * „diesen Beleg gibt es schon“ nicht von „diese Nummer ist vergeben“
     * unterscheiden. Die eine Lage ist in Ordnung, die andere muss wiederholt
     * werden.
     */
    public static function doppelt(Throwable $e, string $schluessel = ''): bool
    {
        $pdo = $e instanceof PDOException ? $e : null;
        $nr  = $pdo !== null ? (int) ($pdo->errorInfo[1] ?? 0) : 0;
        if ($nr !== 1062) { return false; }
        if ($schluessel === '') { return true; }
        return str_contains($e->getMessage(), $schluessel);
    }

    /**
     * Fuehrt etwas aus und wiederholt es, solange nur der eindeutige
     * Schluessel dazwischenkam.
     *
     * Fuenf Versuche sind grosszuegig: Bei zwoelf gleichzeitigen Schreibern
     * lag der schlechteste Fall in der Messung bei drei. Die kurze Pause
     * dazwischen waechst, damit sich zwei Wartende nicht ewig im selben
     * Takt behindern.
     *
     * @template T
     * @param callable():T $tun
     * @return T
     */
    public static function nochmal(callable $tun, string $schluessel = '', int $versuche = 5): mixed
    {
        for ($i = 1; ; $i++) {
            try {
                return $tun();
            } catch (Throwable $e) {
                /* Mit Schluesselnamen: nur genau dieser Zusammenstoss --
                   „diese Nummer ist vergeben“ wiederholen, „diesen Beleg
                   gibt es schon“ nicht. Ohne Namen: jeder Zusammenstoss,
                   also auch eine Verklemmung. */
                $wiederholbar = $schluessel !== ''
                    ? self::doppelt($e, $schluessel)
                    : self::andrang($e);
                if ($i >= $versuche || !$wiederholbar) { throw $e; }
                /* Ein paar Millisekunden, wachsend und leicht zufaellig: Zwei
                   Wartende, die exakt gleich lange warten, stossen wieder
                   zusammen. */
                usleep($i * 2000 + random_int(0, 3000));
            }
        }
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

    /**
     * Ein Zusammenstoss unter Andrang -- und nur der.
     *
     * Drei Meldungen bedeuten dasselbe: "Zwei wollten im selben Augenblick
     * dasselbe." Sie sind kein Fehler im Ablauf, sondern der Preis dafuer,
     * dass mehrere Besucher gleichzeitig bedient werden. Alles andere ist
     * ein wirklicher Fehler und darf nicht wiederholt werden.
     *
     *   1062  Der eindeutige Schluessel liess den zweiten nicht durch.
     *   1213  Zwei Transaktionen warteten ueber Kreuz aufeinander.
     *   1205  Eine wartete zu lange auf eine Sperre.
     */
    public static function andrang(Throwable $e): bool
    {
        if (!$e instanceof PDOException) { return false; }
        return in_array((int) ($e->errorInfo[1] ?? 0), [1062, 1213, 1205], true);
    }

    /**
     * @param int $versuche Wie oft die GANZE Transaktion wiederholt werden
     *        darf, wenn sie nur an einem Zusammenstoss gescheitert ist.
     *
     * WARUM DAS NICHT ueberall AN IST
     *
     * Eine zurueckgerollte Transaktion hat nichts hinterlassen -- in der
     * Datenbank. Verschickt der Rumpf aber auch eine E-Mail oder ruft Stripe,
     * dann ist das schon geschehen und passiert beim zweiten Anlauf noch
     * einmal. Deshalb muss jede Stelle selbst sagen, dass ihr Rumpf nichts
     * tut ausser schreiben. Standard bleibt: ein Versuch.
     */
    public static function transaktion(callable $fn, int $versuche = 1): mixed
    {
        for ($i = 1; ; $i++) {
            $pdo = self::pdo();
            $pdo->beginTransaction();
            try {
                $ergebnis = $fn();
                $pdo->commit();
                return $ergebnis;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                /* Nur ein Zusammenstoss wird wiederholt, und auch der nur
                   begrenzt oft. Ein Fehler, der beim fuenften Mal noch da
                   ist, ist keiner, der sich von selbst legt. */
                if ($i >= $versuche || !self::andrang($e)) { throw $e; }
                usleep($i * 3000 + random_int(0, 5000));
            }
        }
    }
}
