<?php
declare(strict_types=1);

/**
 * Bewertungs-Bitte von selbst vorschlagen (AI Office Stufe 4, V6, 07.10.2026).
 *
 * Uwe: „14 Tage nach online“, „nur wenn nichts offen ist“. Das System schickt
 * nichts — es legt die Bitte als Vorschlag in AI Freigaben (mit Telegram-Knopf,
 * weil sie den Kunden erreicht). Raus geht sie erst mit Uwes Ja, über dieselbe
 * Methode wie der Knopf in der Kundenakte (Nachricht::bewertungBitten).
 *
 * NICHTS OFFEN HEISST
 * keine ungelesene Nachricht des Kunden, kein offener Änderungswunsch, keine
 * offene Zahlung. Wer gerade auf eine Antwort wartet oder Geld schuldet, wird
 * nicht um ein Lob gebeten.
 *
 * EINMAL JE KUNDE
 * Gefragt wird nie zweimal (Mail schon raus), und ein Vorschlag, den Uwe
 * abgelehnt hat, kommt nicht wieder.
 */
final class Bewertungsbitte
{
    public const TAGE = 14;

    /** Für die Prüfung: eine feste Uhr. */
    public static ?int $jetzt = null;

    /** @return list<array{kunde:int, name:string, projekt:int, online:string, tage:int}> */
    public static function kandidaten(): array
    {
        $jetzt = self::$jetzt ?? time();
        $grenze = date('Y-m-d H:i:s', $jetzt - self::TAGE * 86400);
        $offenWunsch = "('neu','im_umfang','zusatz','zusatz_angenommen','in_arbeit')";
        $zeilen = Db::all("SELECT c.id, c.name, c.company, MAX(p.id) AS projekt, MAX(COALESCE(p.veroeffentlicht_am, p.updated_at)) AS online
                             FROM customers c JOIN projects p ON p.customer_id = c.id AND p.demo = 0 AND p.status IN ('online','abgeschlossen')
                            WHERE c.demo = 0 AND c.anonym_am IS NULL AND c.email IS NOT NULL AND c.email <> ''
                              AND NOT EXISTS (SELECT 1 FROM mails m WHERE m.anlass = 'bewertung_bitte' AND m.customer_id = c.id AND m.status = 'gesendet')
                              AND NOT EXISTS (SELECT 1 FROM ai_freigaben f WHERE f.art = 'bewertung_bitten' AND f.kunde_id = c.id)
                              AND NOT EXISTS (SELECT 1 FROM messages n WHERE n.customer_id = c.id AND n.sender = 'kunde' AND n.read_at IS NULL)
                              AND NOT EXISTS (SELECT 1 FROM payments z LEFT JOIN orders o ON o.id = z.order_id LEFT JOIN abos a ON a.id = z.abo_id
                                               WHERE (o.customer_id = c.id OR a.customer_id = c.id) AND z.status IN ('ausstehend','in_bearbeitung','fehlgeschlagen'))
                            GROUP BY c.id, c.name, c.company
                           HAVING online <= ?", [$grenze]);
        $aus = [];
        foreach ($zeilen as $z) {
            // Offene Wünsche je Projekt des Kunden — eigene Abfrage, die Tabelle gibt es erst seit AutoBuild Phase 8.
            try {
                if ((int) Db::wert("SELECT COUNT(*) FROM projekt_wuensche w JOIN projects p ON p.id = w.project_id WHERE p.customer_id = ? AND w.status IN $offenWunsch",
                    [(int) $z['id']], 0) > 0) { continue; }
            } catch (Throwable $e) { }
            $aus[] = ['kunde' => (int) $z['id'], 'name' => trim((string) ($z['company'] ?: $z['name'])), 'projekt' => (int) $z['projekt'],
                      'online' => (string) $z['online'], 'tage' => (int) floor(($jetzt - strtotime((string) $z['online'])) / 86400)];
        }
        return $aus;
    }

    /**
     * Einmal am Tag (Cron): je Kandidat ein Vorschlag in AI Freigaben.
     * @return array{vorgeschlagen:int, link_fehlt?:bool}
     */
    public static function vorschlagen(): array
    {
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Freigabe.php';
        $kandidaten = self::kandidaten();
        if (!$kandidaten) { return ['vorgeschlagen' => 0]; }
        if (!str_starts_with(Firma::get('firma_google_bewertung'), 'https://')) {
            // Ohne Link gibt es nichts zu bitten — einmal sagen, was fehlt, statt still nichts zu tun.
            try {
                require_once __DIR__ . '/Events.php';
                Events::melden('bewertung_link_fehlt', 'Bewertungs-Bitte: der Google-Bewertungslink fehlt', 'hinweis',
                    count($kandidaten) . ' Kunde(n) wären jetzt dran. Den Link unter Einstellungen → Firma eintragen.', '/einstellungen?b=firma');
            } catch (Throwable $e) { }
            return ['vorgeschlagen' => 0, 'link_fehlt' => true];
        }
        $n = 0;
        foreach ($kandidaten as $k) {
            Freigabe::vorschlagen('bewertung_bitten', ['kunde' => $k['kunde']], [
                'titel' => 'Bitte um Google-Bewertung: ' . $k['name'],
                'grund' => 'Die Seite ist seit ' . date('d.m.Y', strtotime($k['online'])) . ' online (' . $k['tage'] . ' Tage), beim Kunden ist nichts offen — keine ungelesene Nachricht, kein offener Wunsch, keine offene Zahlung.',
                'soll' => 'Der Kunde bekommt die Mail „Bitte um eine Google-Bewertung“ in seiner Sprache, mit dem Link aus den Firmendaten.',
                'empfehlung' => 'Jetzt fragen, solange der Eindruck frisch ist. Einmal je Kunde.',
                'von' => 'Bewertungs-Bitte (V6)', 'system' => 'Verwaltung',
            ]);
            $n++;
        }
        return ['vorgeschlagen' => $n];
    }
}
