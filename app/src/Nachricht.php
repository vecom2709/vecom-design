<?php
declare(strict_types=1);

require_once __DIR__ . '/Mail.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Onboarding.php';

/**
 * Nachrichten zwischen Kunde und Verwaltung — an einem Ort statt verstreut
 * ueber E-Mail, WhatsApp und Telefon.
 *
 * Die E-Mail bleibt trotzdem der Weg, auf dem jemand erfaehrt, dass etwas
 * da ist: Niemand schaut aus Gewohnheit in eine Projektseite. Also geht bei
 * jeder Nachricht eine Benachrichtigung raus, und der Text steht gleich
 * darin — wer nur lesen will, muss nirgends klicken.
 */
final class Nachricht
{
    public const MAX_LAENGE = 5000;

    /**
     * Schreibt eine Nachricht und sagt der Gegenseite Bescheid.
     *
     * @param string $von 'admin' oder 'kunde'
     */
    public static function schreiben(int $projektId, string $text, string $von): int
    {
        $text = trim($text);
        if ($text === '') { throw new RuntimeException('Die Nachricht ist leer.'); }
        $text = mb_substr($text, 0, self::MAX_LAENGE);

        $p = Db::one(
            'SELECT p.*, c.name AS kunde, c.company AS firma, c.email AS kunde_email, c.sprache AS kunde_sprache
             FROM projects p JOIN customers c ON c.id = p.customer_id WHERE p.id = ?',
            [$projektId]
        );
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }

        $vomKunden = $von === 'kunde';
        $id = Db::insert('messages', [
            'project_id'  => $projektId,
            'customer_id' => (int) $p['customer_id'],
            'sender'      => $vomKunden ? 'kunde' : 'admin',
            'user_id'     => $vomKunden ? null : Auth::id(),
            'body'        => $text,
            // Was ich selbst schreibe, habe ich gelesen.
            'read_at'     => $vomKunden ? null : date('Y-m-d H:i:s'),
        ]);

        // Der Versand steht ausserhalb: Eine Nachricht ist geschrieben, auch
        // wenn der Mailserver gerade nicht mag.
        try {
            $vomKunden ? self::anUwe($p, $text) : self::anKunden($p, $text);
        } catch (Throwable $e) { /* im Protokoll steht der Fehlschlag */ }

        return $id;
    }

    private static function anKunden(array $p, string $text): void
    {
        $sprache = strtolower((string) ($p['kunde_sprache'] ?? 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        [$betreff, $inhalt] = Texte::mail('nachricht', $sprache, [
            'name' => (string) $p['kunde'],
            'text' => $text,
            'link' => self::link((int) $p['id']) ?? rtrim((string) Config::get('website', ''), '/'),
        ]);
        Mail::senden('nachricht', (string) $p['kunde_email'], $betreff, $inhalt, [
            'customer_id' => (int) $p['customer_id'],
            'project_id'  => (int) $p['id'],
            'order_id'    => $p['order_id'] !== null ? (int) $p['order_id'] : null,
            'antwortAn'   => Mail::eigeneAdresse(),
        ]);
        Events::protokoll('nachricht_raus', 'Nachricht an ' . ($p['firma'] ?: $p['kunde']),
            (int) $p['customer_id'], $p['order_id'] !== null ? (int) $p['order_id'] : null, (int) $p['id']);
    }

    private static function anUwe(array $p, string $text): void
    {
        $wer = (string) ($p['firma'] ?: $p['kunde']);
        Events::protokoll('nachricht_rein', 'Nachricht von ' . $wer,
            (int) $p['customer_id'], $p['order_id'] !== null ? (int) $p['order_id'] : null, (int) $p['id']);
        Events::melden('nachricht_rein', 'Neue Nachricht von ' . $wer, 'info',
            mb_substr($text, 0, 200), '/projekte/' . (int) $p['id']);

        Mail::senden('nachricht_rein', Mail::eigeneAdresse(),
            'Nachricht von ' . $wer,
            $wer . " hat zum Projekt \"" . (string) $p['name'] . "\" geschrieben:\n\n" . $text
            . "\n\nAntworten in der Verwaltung: " . rtrim((string) Config::get('website', ''), '/')
            . Config::basis() . '/projekte/' . (int) $p['id'] . "\n",
            [
                'customer_id' => (int) $p['customer_id'],
                'project_id'  => (int) $p['id'],
                'antwortAn'   => (string) $p['kunde_email'],
            ]);
    }

    /**
     * Die Adresse, unter der der Kunde sein Projekt sieht. Sie benutzt
     * denselben Schluessel wie der Fragebogen — es ist derselbe Mensch, und
     * ein zweiter Schluessel waere ein zweiter, der verloren gehen kann.
     */
    public static function link(int $projektId): ?string
    {
        $f = Db::one('SELECT id FROM questionnaires WHERE project_id = ?', [$projektId]);
        if (!$f) { return null; }
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/projekt.php?t=' . rawurlencode(Onboarding::token((int) $f['id']));
    }

    /** Alles vom Kunden auf gelesen setzen. */
    public static function gelesen(int $projektId): int
    {
        return Db::run(
            "UPDATE messages SET read_at = NOW() WHERE project_id = ? AND read_at IS NULL AND sender = 'kunde'",
            [$projektId]
        )->rowCount();
    }
}
