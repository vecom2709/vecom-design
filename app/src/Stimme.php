<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Events.php';

/**
 * Kundenstimmen — vom Kunden geschrieben, von Uwe freigegeben, auf der
 * Website angezeigt.
 *
 * Der Weg ist kurz und automatisch bis auf einen Schritt: Der Kunde schreibt
 * zwei Saetze auf seiner Seite, sie stehen sofort in der Verwaltung, und ein
 * Klick stellt sie auf die Website. Von da an holt die Startseite sie sich
 * selbst — wie die Preise.
 *
 * Der eine Schritt bleibt mit Absicht. Was ungeprueft auf einer Verkaufsseite
 * erscheint, hat dort irgendwann gestanden, was niemand wollte. Fuenf Sekunden
 * Klick gegen einen Ruf ist kein Tausch, ueber den man lange nachdenkt.
 */
final class Stimme
{
    public const MAX_LAENGE = 1200;

    /**
     * Der Kunde gibt seine Stimme ab. Ohne Erlaubnis wird sie gespeichert,
     * aber ohne Namen — dann ist sie fuer die Website unbrauchbar, und das
     * sagt die Verwaltung auch.
     */
    public static function abgeben(int $kundeId, string $text, bool $erlaubnis, ?int $sterne = null): int
    {
        $text = trim($text);
        if ($text === '') { throw new RuntimeException('Die Stimme ist leer.'); }
        $text = mb_substr($text, 0, self::MAX_LAENGE);

        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Kunde nicht gefunden.'); }

        $sprache = strtolower((string) ($k['sprache'] ?: 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $id = Db::insert('stimmen', [
            'customer_id' => $kundeId,
            'name'        => (string) $k['name'],
            'firma'       => (string) ($k['company'] ?? '') ?: null,
            'ort'         => (string) ($k['city'] ?? '') ?: null,
            'text'        => $text,
            'sterne'      => $sterne !== null && $sterne >= 1 && $sterne <= 5 ? $sterne : null,
            'sprache'     => $sprache,
            'erlaubnis'   => $erlaubnis ? 1 : 0,
            'status'      => 'neu',
        ]);

        $wer = (string) ($k['company'] ?: $k['name']);
        self::still(fn() => Events::protokoll('stimme_neu', 'Kundenstimme von ' . $wer, $kundeId));
        self::still(fn() => Events::melden('stimme_neu', 'Eine Kundenstimme ist da', 'gut',
            $wer . ' — ' . mb_substr($text, 0, 160)
            . ($erlaubnis ? ' · darf mit Namen erscheinen' : ' · OHNE Erlaubnis zur Veröffentlichung'),
            '/stimmen'));

        return $id;
    }

    /** Auf die Website. Ohne Erlaubnis geht das nicht. */
    public static function veroeffentlichen(int $id): void
    {
        $s = Db::one('SELECT * FROM stimmen WHERE id = ?', [$id]);
        if (!$s) { throw new RuntimeException('Stimme nicht gefunden.'); }
        if ((int) $s['erlaubnis'] !== 1) {
            throw new RuntimeException('Diese Stimme darf nicht mit Namen erscheinen — '
                . 'der Kunde hat dem nicht zugestimmt. Frag ihn, dann setze das Häkchen hier.');
        }
        Db::update('stimmen', $id, [
            'status' => 'veroeffentlicht',
            'veroeffentlicht_am' => (string) ($s['veroeffentlicht_am'] ?: date('Y-m-d H:i:s')),
        ]);
        self::still(fn() => Events::protokoll('stimme_frei', 'Kundenstimme veröffentlicht: ' . $s['name'],
            $s['customer_id'] !== null ? (int) $s['customer_id'] : null));
    }

    public static function verstecken(int $id): void
    {
        Db::update('stimmen', $id, ['status' => 'versteckt']);
    }

    /** Erlaubnis nachtragen, wenn der Kunde sie mündlich gegeben hat. */
    public static function erlaubnisSetzen(int $id, bool $ja): void
    {
        Db::update('stimmen', $id, ['erlaubnis' => $ja ? 1 : 0]);
    }

    /**
     * Was auf der Website erscheint. Sprache zuerst, damit ein italienischer
     * Besucher italienische Stimmen sieht — reichen die nicht, kommen die
     * uebrigen dazu, denn drei echte Saetze in einer fremden Sprache sind
     * besser als keine.
     *
     * @return list<array<string,mixed>>
     */
    public static function oeffentliche(string $sprache = 'it', int $hoechst = 6): array
    {
        $alle = (array) self::still(fn() => Db::all(
            "SELECT * FROM stimmen WHERE status = 'veroeffentlicht' AND erlaubnis = 1
              ORDER BY sort, veroeffentlicht_am DESC, id DESC"), []);

        $passend = array_values(array_filter($alle, static fn($s) => (string) $s['sprache'] === $sprache));
        $rest    = array_values(array_filter($alle, static fn($s) => (string) $s['sprache'] !== $sprache));
        return array_slice(array_merge($passend, $rest), 0, $hoechst);
    }

    /** Alles, fuer die Verwaltung. */
    public static function alle(): array
    {
        return (array) self::still(fn() => Db::all(
            "SELECT * FROM stimmen ORDER BY FIELD(status,'neu','veroeffentlicht','versteckt'), id DESC"), []);
    }

    public static function offene(): int
    {
        return (int) self::still(fn() => Db::wert(
            "SELECT COUNT(*) FROM stimmen WHERE status = 'neu'", [], 0), 0);
    }

    /** Hat dieser Kunde schon eine abgegeben? Dann fragt die Seite nicht noch einmal. */
    public static function vonKunde(int $kundeId): ?array
    {
        return self::still(fn() => Db::one(
            'SELECT * FROM stimmen WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]), null);
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
