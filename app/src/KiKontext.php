<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Status.php';
require_once __DIR__ . '/Texte.php';

/**
 * Was die KI über einen Empfänger wissen darf — und nur das (07.10.2026, Vorschläge 1–5, 10).
 *
 * Die Fakten sind zugleich die Messlatte des Prüfers: Eine Zahl, ein Betrag oder eine Adresse im
 * KI-Text muss hier vorkommen. Darum stehen hier nur Dinge aus der Akte, nie Vermutungen.
 * Keine Passwörter, keine Zugangsdaten, keine Notizen aus der Verwaltung, keine Daten anderer Kunden.
 */
final class KiKontext
{
    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    private static function kurz(string $t, int $max): string
    {
        $t = trim((string) preg_replace('~\s+~u', ' ', strip_tags($t)));
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
    }

    /**
     * @return array{fakten:string, stil:string, naechster:string, verlauf:string, name:string}|null
     */
    public static function kunde(int $kundeId, string $anlass, string $sprache): ?array
    {
        $k = self::still(static fn() => Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]));
        if (!$k || !empty($k['anonym_am'])) { return null; }
        $f = [];
        $f[] = 'Kunde: ' . trim((string) ($k['name'] ?? '')) . (trim((string) ($k['company'] ?? '')) !== '' ? ', Firma ' . trim((string) $k['company']) : '');
        if (trim((string) ($k['industry'] ?? '')) !== '') { $f[] = 'Branche: ' . trim((string) $k['industry']); }
        if (trim((string) ($k['city'] ?? '')) !== '') { $f[] = 'Ort: ' . trim((string) $k['city']); }

        $p = self::still(static fn() => Db::one('SELECT * FROM projects WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]));
        $naechster = '';
        if ($p) {
            $f[] = 'Projekt: ' . (string) $p['name'] . ' — Stand: ' . (Status::PROJEKT[(string) $p['status']] ?? (string) $p['status']);
            if (!empty($p['veroeffentlicht_domain'])) { $f[] = 'Website online unter ' . (string) $p['veroeffentlicht_domain']; }
            if (!empty($p['vorschau_frei_am']) && in_array((string) $p['status'], ['vorschau', 'kundenfeedback', 'aenderungen'], true)) {
                $naechster = 'die Vorschau ansehen und Rückmeldung geben';
            }
            if (!empty($p['abnahme_frei_am']) && in_array((string) $p['status'], ['vorschau', 'kundenfeedback', 'aenderungen'], true)) {
                $naechster = 'die fertige Seite ansehen und abnehmen';
            }
        }

        /* Fragebogen: was genau noch fehlt (Vorschlag 5) — als Wörter in seiner Sprache. */
        $q = self::still(static fn() => Db::one('SELECT * FROM questionnaires WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]));
        if ($q && (string) $q['status'] !== 'abgeschlossen' && empty($q['submitted_at'])) {
            $daten = json_decode((string) ($q['data'] ?? ''), true) ?: [];
            $fehlt = self::fragebogenFehlt($daten, $sprache);
            $f[] = $fehlt ? 'Im Fragebogen fehlen noch: ' . implode(', ', $fehlt) : 'Der Fragebogen ist noch nicht abgeschickt.';
            if ($daten) { $f[] = 'Er hat den Fragebogen schon angefangen.'; }
            if ($naechster === '') { $naechster = $fehlt ? 'die fehlenden Angaben im Fragebogen ergänzen' : 'den Fragebogen abschicken'; }
        }

        /* Offene Zahlungen — mit dem, was danach passiert (Vorschlag 5). */
        $offen = self::still(static fn() => Db::all("SELECT p.art, p.bezeichnung, p.amount_cents, p.currency, p.faellig_am FROM payments p
              LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN abos a ON a.id = p.abo_id
             WHERE COALESCE(o.customer_id, a.customer_id) = ? AND p.status IN ('ausstehend','in_bearbeitung','fehlgeschlagen') ORDER BY p.id LIMIT 3", [$kundeId]), []);
        foreach ($offen as $z) {
            $f[] = 'Offene Zahlung: ' . (string) $z['bezeichnung'] . ', ' . Fmt::geld((int) $z['amount_cents'], (string) $z['currency'])
                . (!empty($z['faellig_am']) ? ', fällig am ' . Fmt::datum((string) $z['faellig_am']) : '');
            $danach = ['anzahlung' => 'Mit der Anzahlung beginnt die Arbeit an der Website.', 'restzahlung' => 'Nach der Restzahlung geht die Website online.',
                       'gesamt' => 'Mit der Zahlung beginnt die Arbeit an der Website.'][(string) $z['art']] ?? '';
            if ($danach !== '') { $f[] = $danach; }
            if ($naechster === '') { $naechster = 'die offene Zahlung über den Link in dieser Mail begleichen'; }
        }

        /* Was er zuletzt geschickt hat (Dateien) — „Danke für die Fotos vom Gastraum“. */
        $dateien = self::still(static fn() => Db::all("SELECT orig_name, created_at FROM files WHERE customer_id = ? AND uploaded_by = 'kunde' ORDER BY id DESC LIMIT 3", [$kundeId]), []);
        foreach ($dateien as $d) { $f[] = 'Hat am ' . Fmt::datum((string) $d['created_at']) . ' eine Datei geschickt: ' . self::kurz((string) $d['orig_name'], 80); }

        /* Gedächtnis (Vorschlag 2): die letzten Nachrichten in beide Richtungen und die letzten Mails. */
        $verlauf = [];
        $laengen = [];
        foreach (array_reverse(self::still(static fn() => Db::all('SELECT sender, body, created_at FROM messages WHERE customer_id = ? ORDER BY id DESC LIMIT 6', [$kundeId]), [])) as $n) {
            $vonKunde = (string) $n['sender'] === 'kunde';
            if ($vonKunde) { $laengen[] = mb_strlen(trim((string) $n['body'])); }
            $verlauf[] = Fmt::datum((string) $n['created_at']) . ' ' . ($vonKunde ? 'Kunde schrieb' : 'Uwe schrieb') . ': „' . self::kurz((string) $n['body'], 400) . '“';
        }
        foreach (array_reverse(self::still(static fn() => Db::all("SELECT anlass, betreff, ki_teil, created_at FROM mails WHERE customer_id = ? AND status = 'gesendet' ORDER BY id DESC LIMIT 5", [$kundeId]), [])) as $m) {
            $verlauf[] = Fmt::datum((string) $m['created_at']) . ' Mail „' . self::kurz((string) $m['betreff'], 90) . '“'
                . (trim((string) ($m['ki_teil'] ?? '')) !== '' ? ' — darin stand schon: „' . self::kurz((string) $m['ki_teil'], 300) . '“' : '');
        }

        /* Ton (Vorschlag 3): an seinen eigenen Nachrichten gemessen. */
        $stil = 'normal';
        if ($laengen) {
            $schnitt = array_sum($laengen) / count($laengen);
            $stil = $schnitt < 90 ? 'knapp' : ($schnitt > 350 ? 'ausführlich' : 'normal');
        }
        return ['fakten' => implode("\n", $f), 'stil' => $stil, 'naechster' => $naechster, 'verlauf' => implode("\n", $verlauf),
                'name' => trim((string) ($k['name'] ?? ''))];
    }

    /** Fehlende Kernfragen in der Sprache des Kunden, höchstens vier. @return list<string> */
    public static function fragebogenFehlt(array $daten, string $sprache): array
    {
        $aus = [];
        try {
            require_once __DIR__ . '/Fragen.php';
            foreach (Texte::FRAGEBOGEN as $abschnitt) {
                foreach ((array) ($abschnitt['felder'] ?? []) as $name => $feld) {
                    if (!Fragen::istKern((string) $name) || !Fragen::zeigen($feld, $daten) || Fragen::beantwortet((string) $name, $feld, $daten)) { continue; }
                    $aus[] = (string) ($feld[$sprache] ?? $feld['it'] ?? $name);
                    if (count($aus) >= 4) { return $aus; }
                }
            }
        } catch (Throwable $e) { }
        return $aus;
    }

    /** Partner (Vorschlag 10): Zahlen der letzten sieben Tage und seit Monatsbeginn. */
    public static function partner(int $partnerId): ?array
    {
        $p = self::still(static fn() => Db::one('SELECT * FROM partner WHERE id = ?', [$partnerId]));
        if (!$p) { return null; }
        $f = ['Partner: ' . (string) $p['name']];
        try {
            require_once __DIR__ . '/Partner.php';
            $w = Partner::kennzahlen($partnerId, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
            $f[] = 'Letzte 7 Tage: ' . (int) ($w['klicks'] ?? 0) . ' Besuche über seinen Link, ' . (int) ($w['kunden'] ?? 0) . ' neue Interessenten, ' . (int) ($w['verkaeufe'] ?? 0) . ' Verkäufe';
            $m = Partner::kennzahlen($partnerId, date('Y-m-01'), date('Y-m-d'));
            $f[] = 'Seit Monatsbeginn: ' . (int) ($m['klicks'] ?? 0) . ' Besuche, ' . (int) ($m['kunden'] ?? 0) . ' Interessenten, ' . (int) ($m['verkaeufe'] ?? 0) . ' Verkäufe';
        } catch (Throwable $e) { }
        /* Wer gerade im Konfigurator war und noch nicht gekauft hat — ohne Namen, nur Ort. */
        $frisch = self::still(static fn() => Db::all("SELECT ort, created_at FROM partner_leads WHERE partner_id = ? AND stufe IN ('neu','interesse') AND created_at >= NOW() - INTERVAL 7 DAY ORDER BY id DESC LIMIT 2", [$partnerId]), []);
        foreach ($frisch as $l) { $f[] = 'Frischer Interessent' . (trim((string) ($l['ort'] ?? '')) !== '' ? ' aus ' . trim((string) $l['ort']) : '') . ' seit ' . Fmt::datum((string) $l['created_at']); }
        return ['fakten' => implode("\n", $f), 'name' => (string) $p['name']];
    }
}
