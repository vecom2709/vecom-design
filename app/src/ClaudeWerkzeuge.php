<?php
declare(strict_types=1);

/**
 * Die Lesewerkzeuge für Claude (AI Office Stufe 2, 07.10.2026).
 *
 * Uwe: „Alles, mit Kundennamen“ — Kunden, Projekte, Geld, Meldungen,
 * Akquise, Überwachung, AI Freigaben, Prüfspur und das Wissen aus
 * PROJEKT.md. Nur lesen: Kein Werkzeug hier schreibt eine Zeile in die
 * Datenbank (außer der Spur, wer was gelesen hat) und keins verschickt etwas.
 * Die Prüfung (kette.php) sucht in dieser Datei nach schreibendem SQL und
 * nach den Versandklassen — findet sie etwas, reißt sie.
 *
 * WAS NIE HERAUSGEHT, AUCH WENN ES IN DER TABELLE STEHT
 * Kundenlinks und Tokens (wer sie hat, ist drin), Stripe-Kennungen,
 * Zahlmittel, KAS-Logins, verschlüsselte Zugangsdaten, IP-Adressen,
 * Passwörter, Schlüssel. Deshalb stehen die Spalten unten einzeln da und
 * nie mit Sternchen — eine neue Spalte kommt erst heraus, wenn jemand sie
 * hier hinschreibt.
 *
 * Beispieldaten (demo = 1) bleiben draußen: Claude soll über das echte
 * Geschäft reden, nicht über „Beispiel GmbH“.
 */
final class ClaudeWerkzeuge
{
    /** Länger wird keine Antwort — sonst füllt eine Liste Claudes ganzes Gedächtnis. */
    public const HOECHSTENS_ZEICHEN = 60000;

    /** @return list<array<string,mixed>> */
    public static function liste(): array
    {
        $leer = ['type' => 'object', 'properties' => new stdClass(), 'additionalProperties' => false];
        $nur = static fn(string $titel): array => ['title' => $titel, 'readOnlyHint' => true, 'destructiveHint' => false,
                                                   'idempotentHint' => true, 'openWorldHint' => false];
        $zahl = static fn(string $was, int $min, int $max): array => ['type' => 'integer', 'minimum' => $min, 'maximum' => $max, 'description' => $was];
        return [
            ['name' => 'lage_heute', 'title' => 'Lage heute',
             'description' => 'Tagesüberblick der Vecom-Verwaltung — dieselben Zahlen wie Uwes Morgenbriefing: was auf Uwe wartet (AI Freigaben, zurückgehaltene Mails, Vorgänge, wichtige Meldungen), Geld (gestern eingegangen, offen, überfällig mit Kunde), Technik (gestörte Seiten, Zertifikate, Cronjob, Sicherung, Not-Aus), Akquise und heutige Termine. Guter erster Griff bei „Was ist los?“.',
             'inputSchema' => $leer, 'annotations' => $nur('Lage heute')],
            ['name' => 'meldungen', 'title' => 'Meldungen',
             'description' => 'Die Meldungen der Verwaltung (Störungen, Hinweise, Erfolge), neueste zuerst.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                 'nur_ungelesen' => ['type' => 'boolean', 'description' => 'Nur ungelesene (Standard: ja).'],
                 'stufe' => ['type' => 'string', 'enum' => ['schlecht', 'warnung', 'info', 'gut'], 'description' => 'Nur diese Stufe.'],
                 'anzahl' => $zahl('Wie viele (Standard 20).', 1, 100)]],
             'annotations' => $nur('Meldungen')],
            ['name' => 'ai_freigaben', 'title' => 'AI Freigaben',
             'description' => 'Was auf Uwes Ja wartet (offene und zurückgestellte AI Freigaben), was zuletzt entschieden wurde, und Mails, die der Not-Aus zurückgehalten hat.',
             'inputSchema' => $leer, 'annotations' => $nur('AI Freigaben')],
            ['name' => 'kunden_suchen', 'title' => 'Kunden suchen',
             'description' => 'Kunden nach Name, Firma, E-Mail, Telefon, Kundennummer oder Ort suchen. Liefert die Kunden-ID für kunde_akte.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['suche'], 'properties' => [
                 'suche' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100, 'description' => 'Suchwort.'],
                 'anzahl' => $zahl('Wie viele (Standard 10).', 1, 50)]],
             'annotations' => $nur('Kunden suchen')],
            ['name' => 'kunde_akte', 'title' => 'Kundenakte',
             'description' => 'Die Akte eines Kunden: Stammdaten, Notizen, Bestellungen, Projekte, Angebote, Zahlungen, Rechnungen, Verträge, Hosting, letzte Nachrichten und Ereignisse.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['kunde_id'], 'properties' => [
                 'kunde_id' => $zahl('ID aus kunden_suchen.', 1, 2147483647)]],
             'annotations' => $nur('Kundenakte')],
            ['name' => 'projekte', 'title' => 'Projekte',
             'description' => 'Projekte mit Stand, Fortschritt und Frist. Ohne Angabe alle, die noch nicht abgeschlossen sind.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                 'status' => ['type' => 'string', 'enum' => array_keys(Status::PROJEKT), 'description' => 'Nur dieser Stand.'],
                 'anzahl' => $zahl('Wie viele (Standard 30).', 1, 100)]],
             'annotations' => $nur('Projekte')],
            ['name' => 'projekt_akte', 'title' => 'Projektakte',
             'description' => 'Ein Projekt im Detail: Stand, Freigaben, Aufgaben, Website, Briefing (Anfang), Dateien, letzte Nachrichten.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['projekt_id'], 'properties' => [
                 'projekt_id' => $zahl('ID aus projekte oder kunde_akte.', 1, 2147483647)]],
             'annotations' => $nur('Projektakte')],
            ['name' => 'geld', 'title' => 'Geld',
             'description' => 'Eingänge der letzten Tage, offene und überfällige Zahlungen mit Kunde, ausgestellte Rechnungen im Zeitraum.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                 'tage' => $zahl('Zeitraum in Tagen (Standard 30).', 1, 366)]],
             'annotations' => $nur('Geld')],
            ['name' => 'umsatz_chancen', 'title' => 'Umsatz-Chancen',
             'description' => 'Was der Umsatz-Spürhund gefunden hat: fertige Seiten ohne Betreuung, Seiten ohne Hosting bei Vecom, Angebote ohne Antwort, wartende Interessenten — mit Grund, Richtwert und Vorschlag. Daraus lassen sich Vorschläge in AI Freigaben machen (freigabe_vorschlagen), wenn Eintragen erlaubt ist.',
             'inputSchema' => $leer, 'annotations' => $nur('Umsatz-Chancen')],
            ['name' => 'akquise', 'title' => 'Akquise',
             'description' => 'Akquise-Stand: Zahlen von heute, offene Antworten von Interessenten, fällige Wiedervorlagen, Termine der nächsten sieben Tage.',
             'inputSchema' => $leer, 'annotations' => $nur('Akquise')],
            ['name' => 'ueberwachung', 'title' => 'Überwachung',
             'description' => 'Technik: überwachte Kundenseiten mit Zustand und Zertifikat, Cronjob, Sicherung außer Haus, Not-Aus, Automationen mit Fehlern.',
             'inputSchema' => $leer, 'annotations' => $nur('Überwachung')],
            ['name' => 'pruefspur', 'title' => 'Prüfspur',
             'description' => 'Wer hat wann was in der Verwaltung getan (Prüfspur), neueste zuerst.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                 'tage' => $zahl('Zeitraum in Tagen (Standard 7).', 1, 365),
                 'aktion' => ['type' => 'string', 'maxLength' => 60, 'description' => 'Nur Aktionen, die so anfangen (z. B. „partner_“).'],
                 'anzahl' => $zahl('Wie viele (Standard 50).', 1, 200)]],
             'annotations' => $nur('Prüfspur')],
            ['name' => 'wissen_suchen', 'title' => 'Wissen durchsuchen',
             'description' => 'Durchsucht das Projektwissen (PROJEKT.md mit allen Entscheidungen, CLAUDE.md, VECOM-STANDARD.md, AKQUISE.md): Warum ist etwas so gebaut, was hat Uwe entschieden, welche Regel gilt? Liefert Kapitel-IDs für wissen_kapitel.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['frage'], 'properties' => [
                 'frage' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200, 'description' => 'Stichworte, z. B. „Mahnung Stufe 2“.'],
                 'anzahl' => $zahl('Wie viele Treffer (Standard 8).', 1, 20)]],
             'annotations' => $nur('Wissen durchsuchen')],
            ['name' => 'wissen_kapitel', 'title' => 'Wissen: Kapitel lesen',
             'description' => 'Ein Kapitel des Projektwissens im Wortlaut. Lange Kapitel kommen seitenweise: mit „ab“ weiterlesen.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['id'], 'properties' => [
                 'id' => ['type' => 'string', 'pattern' => '^[a-z]+-[0-9]{3}$', 'description' => 'Kapitel-ID, z. B. „projekt-042“.'],
                 'ab' => $zahl('Ab diesem Zeichen weiterlesen.', 0, 10000000)]],
             'annotations' => $nur('Wissen: Kapitel lesen')],
            ['name' => 'wissen_inhalt', 'title' => 'Wissen: Inhaltsverzeichnis',
             'description' => 'Das Inhaltsverzeichnis des Projektwissens, wahlweise nur eine Datei oder nur Kapitel ab einem Datum.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'properties' => [
                 'quelle' => ['type' => 'string', 'enum' => ['PROJEKT.md', 'CLAUDE.md', 'VECOM-STANDARD.md', 'AKQUISE.md']],
                 'seit' => ['type' => 'string', 'pattern' => '^20[0-9]{2}-[0-9]{2}-[0-9]{2}$', 'description' => 'JJJJ-MM-TT']]],
             'annotations' => $nur('Wissen: Inhaltsverzeichnis')],
        ];
    }

    /** @return list<string> */
    public static function namen(): array { return array_column(self::liste(), 'name'); }

    /**
     * Ein Werkzeug ausführen. Ein falsches Argument ist ein Werkzeugfehler (isError),
     * den Claude lesen und verbessern kann — kein Protokollfehler.
     *
     * @return array{ok:bool, daten:mixed, text:string}
     */
    public static function rufen(string $name, array $a): array
    {
        try {
            $daten = match ($name) {
                'lage_heute'     => self::lageHeute(),
                'meldungen'      => self::meldungen($a),
                'ai_freigaben'   => self::aiFreigaben(),
                'kunden_suchen'  => self::kundenSuchen($a),
                'kunde_akte'     => self::kundeAkte($a),
                'projekte'       => self::projekte($a),
                'projekt_akte'   => self::projektAkte($a),
                'geld'           => self::geld($a),
                'akquise'        => self::akquise(),
                'umsatz_chancen' => self::umsatzChancen(),
                'ueberwachung'   => self::ueberwachung(),
                'pruefspur'      => self::pruefspur($a),
                'wissen_suchen'  => self::wissenSuchen($a),
                'wissen_kapitel' => self::wissenKapitel($a),
                'wissen_inhalt'  => self::wissenInhalt($a),
                default          => throw new InvalidArgumentException('Unbekanntes Werkzeug: ' . $name),
            };
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'daten' => null, 'text' => $e->getMessage()];
        } catch (Throwable $e) {
            // Den Grund nicht nach draußen: eine Datenbankmeldung verrät Aufbau und Namen.
            try { require_once __DIR__ . '/Events.php'; Events::protokoll('claude_werkzeug_fehler', $name . ': ' . mb_substr($e->getMessage(), 0, 300)); } catch (Throwable $e2) { }
            return ['ok' => false, 'daten' => null, 'text' => 'Das ließ sich gerade nicht lesen. Später noch einmal versuchen.'];
        }
        $text = (string) json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        if (mb_strlen($text) > self::HOECHSTENS_ZEICHEN) {
            $text = mb_substr($text, 0, self::HOECHSTENS_ZEICHEN) . "\n… (gekürzt — enger fragen, z. B. mit „anzahl“)";
        }
        return ['ok' => true, 'daten' => $daten, 'text' => $text];
    }

    /* ================================================================== */

    private static function lageHeute(): array
    {
        require_once __DIR__ . '/Morgenbriefing.php';
        $d = Morgenbriefing::daten();
        $d['hinweis'] = 'Beträge in Cent. null heißt: ließ sich nicht lesen (nicht: null Stück).';
        return $d;
    }

    private static function meldungen(array $a): array
    {
        $nur = !array_key_exists('nur_ungelesen', $a) || (bool) $a['nur_ungelesen'];
        $stufe = (string) ($a['stufe'] ?? '');
        $n = self::zahl($a, 'anzahl', 20, 1, 100);
        $wo = ['demo = 0'];
        $p = [];
        if ($nur) { $wo[] = 'read_at IS NULL'; }
        if ($stufe !== '') {
            if (!in_array($stufe, ['schlecht', 'warnung', 'info', 'gut'], true)) { throw new InvalidArgumentException('stufe: schlecht, warnung, info oder gut.'); }
            $wo[] = 'level = ?'; $p[] = $stufe;
        }
        $zeilen = Db::all('SELECT id, type AS art, level AS stufe, title AS titel, body AS text, link, created_at AS am, read_at AS gelesen_am
                             FROM notifications WHERE ' . implode(' AND ', $wo) . ' ORDER BY id DESC LIMIT ' . $n, $p);
        foreach ($zeilen as &$z) {
            $z['text'] = mb_substr((string) $z['text'], 0, 600);
            if (!empty($z['link'])) { $z['link'] = self::verwaltung((string) $z['link']); }
        }
        unset($z);
        return ['ungelesen_gesamt' => (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND demo = 0', [], 0), 'meldungen' => $zeilen];
    }

    private static function aiFreigaben(): array
    {
        require_once __DIR__ . '/Freigabe.php';
        require_once __DIR__ . '/Ausgang.php';
        $kurz = static fn(array $f): array => [
            'id' => (int) $f['id'], 'art' => $f['art'], 'titel' => $f['titel'], 'status' => $f['status'],
            'grund' => $f['grund'], 'empfehlung' => $f['empfehlung'], 'vorgeschlagen_von' => $f['vorgeschlagen_von'],
            'kunde_id' => $f['kunde_id'], 'projekt_id' => $f['projekt_id'], 'am' => $f['created_at'],
            'zurueck_bis' => $f['zurueck_bis'] ?? null, 'entschieden_am' => $f['entschieden_am'] ?? null,
            'entschieden_von' => $f['entschieden_von'] ?? null, 'ergebnis' => isset($f['ergebnis']) ? mb_substr((string) $f['ergebnis'], 0, 300) : null,
            'link' => self::verwaltung('/ai-freigaben/' . (int) $f['id']),
        ];
        return [
            'offen' => array_map($kurz, Freigabe::offen(50)),
            'zurueckgestellt' => array_map($kurz, Freigabe::ruhend()),
            'zuletzt_entschieden' => array_map($kurz, Freigabe::entschieden(10)),
            'zurueckgehaltene_mails' => array_map(static fn($g) => ['id' => (int) $g['id'], 'betreff' => $g['betreff'],
                'an' => $g['empfaenger'], 'herkunft' => $g['herkunft'] ?? null, 'am' => $g['created_at']], Ausgang::offen(50)),
        ];
    }

    private static function kundenSuchen(array $a): array
    {
        $s = trim((string) ($a['suche'] ?? ''));
        if (mb_strlen($s) < 2) { throw new InvalidArgumentException('suche: mindestens zwei Zeichen.'); }
        $n = self::zahl($a, 'anzahl', 10, 1, 50);
        $wie = '%' . addcslashes(mb_substr($s, 0, 100), '%_\\') . '%';
        $zeilen = Db::all("SELECT c.id, c.kundennr, c.name, c.company AS firma, c.email, c.phone AS telefon, c.city AS ort, c.country AS land,
                                  c.sprache, c.created_at AS kunde_seit, c.anonym_am,
                                  (SELECT COUNT(*) FROM projects p WHERE p.customer_id = c.id AND p.demo = 0) AS projekte
                             FROM customers c
                            WHERE c.demo = 0 AND (c.name LIKE ? OR c.company LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.kundennr LIKE ? OR c.city LIKE ?)
                            ORDER BY c.id DESC LIMIT $n", [$wie, $wie, $wie, $wie, $wie, $wie]);
        return ['treffer' => count($zeilen), 'kunden' => $zeilen];
    }

    private static function kundeAkte(array $a): array
    {
        $id = self::zahl($a, 'kunde_id', 0, 1, 2147483647);
        $k = Db::one('SELECT id, kundennr, name, company AS firma, email, phone AS telefon, industry AS branche, street AS strasse, zip AS plz,
                             city AS ort, country AS land, vat_id AS ust_id, tax_code AS steuernummer, sdi, sprache, notes AS notizen,
                             created_at AS kunde_seit, anonym_am, rabatt_prozent, rabatt_bis, referenz_am
                        FROM customers WHERE id = ? AND demo = 0', [$id]);
        if ($k === null) { throw new InvalidArgumentException('Kein Kunde mit der ID ' . $id . '.'); }
        $still = static function (callable $fn): mixed { try { return $fn(); } catch (Throwable $e) { return null; } };
        $projekte = Db::all('SELECT id, name, status, progress AS fortschritt, deadline AS frist, preview_url AS vorschau, vorschau_frei_am,
                                    abnahme_am, veroeffentlicht_am, veroeffentlicht_domain, updated_at AS geaendert
                               FROM projects WHERE customer_id = ? AND demo = 0 ORDER BY id DESC', [$id]);
        foreach ($projekte as &$p) { $p['stand'] = Status::PROJEKT[$p['status']] ?? $p['status']; }
        unset($p);
        return [
            'kunde' => $k,
            'link' => self::verwaltung('/kunden/' . $id),
            'bestellungen' => Db::all('SELECT id, order_no AS nummer, package_name AS paket, price_cents AS preis_cent, monthly_cents AS monatlich_cent,
                                              status, ordered_at AS bestellt_am FROM orders WHERE customer_id = ? AND demo = 0 ORDER BY id DESC LIMIT 20', [$id]),
            'projekte' => $projekte,
            'angebote' => $still(static fn() => Db::all('SELECT id, nummer, titel, status, summe_cents AS summe_cent, monatlich_cents AS monatlich_cent,
                                              gueltig_bis, gesendet_am, angenommen_am, abgelehnt_am, abgelehnt_grund
                                         FROM angebote WHERE customer_id = ? AND demo = 0 ORDER BY id DESC LIMIT 10', [$id])),
            'zahlungen' => Db::all('SELECT p.id, p.bezeichnung, p.art, p.amount_cents AS betrag_cent, p.status, p.faellig_am, p.paid_at AS bezahlt_am, p.provider AS weg
                                      FROM payments p LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN abos b ON b.id = p.abo_id
                                     WHERE (o.customer_id = ? OR b.customer_id = ?) AND p.demo = 0 ORDER BY p.id DESC LIMIT 30', [$id, $id]),
            'rechnungen' => Db::all('SELECT id, invoice_no AS nummer, art, titel, total_cents AS brutto_cent, status, issued_at AS ausgestellt, due_at AS faellig
                                       FROM invoices WHERE customer_id = ? AND demo = 0 ORDER BY id DESC LIMIT 20', [$id]),
            'vertraege' => $still(static fn() => Db::all("SELECT id, paket_name AS paket, betrag_cents AS betrag_cent, zahlart, status, beginn, mindestlaufzeit_bis,
                                              naechste_abrechnung, gekuendigt_am, laeuft_bis FROM abos WHERE customer_id = ? AND demo = 0 ORDER BY id DESC", [$id])),
            'hosting' => $still(static fn() => Db::all('SELECT id, domain, status, speicher_mb, kas_speicher_mb, ssl_status, angelegt_am, gesperrt_am, preis_cents AS preis_cent
                                         FROM hosting_auftraege WHERE customer_id = ? AND demo = 0 ORDER BY id DESC', [$id])),
            'nachrichten' => array_map(static fn($m) => ['am' => $m['created_at'], 'von' => $m['sender'], 'betreff' => $m['betreff'],
                                'text' => mb_substr((string) $m['body'], 0, 400), 'projekt_id' => $m['project_id'], 'gelesen' => $m['read_at'] !== null],
                Db::all('SELECT created_at, sender, betreff, body, project_id, read_at FROM messages WHERE customer_id = ? AND demo = 0 ORDER BY id DESC LIMIT 10', [$id])),
            'ereignisse' => Db::all('SELECT created_at AS am, type AS art, title AS titel, actor AS wer FROM activities WHERE customer_id = ? AND demo = 0 ORDER BY id DESC LIMIT 15', [$id]),
        ];
    }

    private static function projekte(array $a): array
    {
        $n = self::zahl($a, 'anzahl', 30, 1, 100);
        $status = (string) ($a['status'] ?? '');
        $wo = 'p.demo = 0 AND ';
        $p = [];
        if ($status !== '') {
            if (!isset(Status::PROJEKT[$status])) { throw new InvalidArgumentException('status: unbekannter Stand.'); }
            $wo .= 'p.status = ?'; $p[] = $status;
        } else {
            $wo .= "p.status <> 'abgeschlossen'";
        }
        $zeilen = Db::all("SELECT p.id, p.name, p.status, p.progress AS fortschritt, p.deadline AS frist, p.priority AS prioritaet, p.projektart,
                                  p.ki_stopp, p.updated_at AS geaendert, c.id AS kunde_id, COALESCE(NULLIF(c.company,''), c.name) AS kunde
                             FROM projects p LEFT JOIN customers c ON c.id = p.customer_id
                            WHERE $wo ORDER BY p.updated_at DESC LIMIT $n", $p);
        foreach ($zeilen as &$z) { $z['stand'] = Status::PROJEKT[$z['status']] ?? $z['status']; }
        unset($z);
        return ['anzahl' => count($zeilen), 'projekte' => $zeilen];
    }

    private static function projektAkte(array $a): array
    {
        $id = self::zahl($a, 'projekt_id', 0, 1, 2147483647);
        $p = Db::one('SELECT p.id, p.name, p.status, p.progress AS fortschritt, p.priority AS prioritaet, p.start_date AS beginn, p.deadline AS frist,
                             p.preview_url AS vorschau, p.projektart, p.ambition, p.briefing, p.briefing_am, p.abnahme, p.abnahme_am,
                             p.vorschau_frei_am, p.paket_frei_am, p.abnahme_frei_am, p.bau_frei_am, p.veroeffentlicht_am, p.veroeffentlicht_domain,
                             p.ki_stopp, p.ki_stopp_grund, p.risiko, p.created_at AS angelegt, p.updated_at AS geaendert,
                             c.id AS kunde_id, c.name AS kunde, c.company AS firma
                        FROM projects p LEFT JOIN customers c ON c.id = p.customer_id WHERE p.id = ? AND p.demo = 0', [$id]);
        if ($p === null) { throw new InvalidArgumentException('Kein Projekt mit der ID ' . $id . '.'); }
        $p['stand'] = Status::PROJEKT[$p['status']] ?? $p['status'];
        $lang = mb_strlen((string) $p['briefing']);
        $p['briefing'] = $lang > 4000 ? mb_substr((string) $p['briefing'], 0, 4000) . ' … (' . $lang . ' Zeichen insgesamt)' : $p['briefing'];
        $still = static function (callable $fn): mixed { try { return $fn(); } catch (Throwable $e) { return null; } };
        return [
            'projekt' => $p,
            'link' => self::verwaltung('/projekte/' . $id),
            'aufgaben' => Db::all('SELECT title AS aufgabe, done AS erledigt, due_date AS faellig FROM tasks WHERE project_id = ? ORDER BY sort, id', [$id]),
            'website' => $still(static fn() => Db::one('SELECT domain, url, status, monitoring, published_at AS online_seit, ssl_expires_at AS zertifikat_bis,
                                                          last_ok_at, last_fail_at FROM websites WHERE project_id = ?', [$id])),
            'fragebogen' => $still(static fn() => Db::one('SELECT status, submitted_at AS abgeschickt, eingeladen_am, erinnert_am FROM questionnaires WHERE project_id = ?', [$id])),
            'dateien' => $still(static fn() => Db::all('SELECT rolle, uploaded_by AS von, COUNT(*) AS anzahl, MAX(created_at) AS zuletzt FROM files WHERE project_id = ? GROUP BY rolle, uploaded_by', [$id])),
            'nachrichten' => array_map(static fn($m) => ['am' => $m['created_at'], 'von' => $m['sender'], 'betreff' => $m['betreff'],
                                'text' => mb_substr((string) $m['body'], 0, 400), 'gelesen' => $m['read_at'] !== null],
                Db::all('SELECT created_at, sender, betreff, body, read_at FROM messages WHERE project_id = ? ORDER BY id DESC LIMIT 10', [$id])),
        ];
    }

    private static function geld(array $a): array
    {
        $tage = self::zahl($a, 'tage', 30, 1, 366);
        $ab = date('Y-m-d 00:00:00', time() - ($tage - 1) * 86400);
        $kunde = 'LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN abos b ON b.id = p.abo_id
                  LEFT JOIN customers c ON c.id = COALESCE(o.customer_id, b.customer_id)';
        $wer = "COALESCE(NULLIF(c.company,''), c.name) AS kunde, c.id AS kunde_id";
        $offen = "p.status IN ('ausstehend','in_bearbeitung','fehlgeschlagen') AND p.demo = 0";
        return [
            'zeitraum' => ['ab' => substr($ab, 0, 10), 'tage' => $tage],
            'eingegangen_summe_cent' => (int) Db::wert("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'bezahlt' AND demo = 0 AND paid_at >= ?", [$ab], 0),
            'eingegangen' => Db::all("SELECT p.paid_at AS am, p.amount_cents AS betrag_cent, p.bezeichnung, p.provider AS weg, $wer
                                        FROM payments p $kunde WHERE p.status = 'bezahlt' AND p.demo = 0 AND p.paid_at >= ? ORDER BY p.paid_at DESC LIMIT 30", [$ab]),
            'offen_summe_cent' => (int) Db::wert("SELECT COALESCE(SUM(p.amount_cents),0) FROM payments p WHERE $offen", [], 0),
            'offen' => Db::all("SELECT p.id, p.faellig_am, p.amount_cents AS betrag_cent, p.bezeichnung, p.status, $wer,
                                       (p.faellig_am IS NOT NULL AND p.faellig_am < CURDATE()) AS ueberfaellig
                                  FROM payments p $kunde WHERE $offen ORDER BY p.faellig_am IS NULL, p.faellig_am LIMIT 50"),
            'rechnungen_im_zeitraum' => Db::all('SELECT i.invoice_no AS nummer, i.art, i.total_cents AS brutto_cent, i.status, i.issued_at AS ausgestellt,
                                                        COALESCE(NULLIF(c.company,\'\'), c.name, i.empfaenger) AS kunde
                                                   FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id
                                                  WHERE i.demo = 0 AND i.issued_at >= ? ORDER BY i.issued_at DESC LIMIT 50', [$ab]),
            'hinweis' => 'Beträge in Cent.',
        ];
    }

    private static function umsatzChancen(): array
    {
        require_once __DIR__ . '/Spuerhund.php';
        $offen = array_map(static fn($c) => ['id' => (int) $c['id'], 'art' => Spuerhund::ARTEN[$c['art']][0] ?? $c['art'], 'titel' => $c['titel'],
            'grund' => $c['grund'], 'vorschlag' => $c['vorschlag'], 'richtwert_cent' => $c['wert_cents'] !== null ? (int) $c['wert_cents'] : null,
            'richtwert_je' => $c['wert_art'], 'kunde_id' => $c['kunde_id'], 'projekt_id' => $c['projekt_id'], 'angebot_id' => $c['angebot_id'],
            'betrieb_id' => $c['firma_id'], 'gefunden_am' => $c['gefunden_am']], Spuerhund::offen());
        return ['offen' => $offen, 'hinweis' => 'Richtwerte aus der eigenen Preisliste, in Cent; keine Zusage. Verworfene Chancen stehen hier nicht.',
                'seite' => self::verwaltung('/umsatz-chancen')];
    }

    private static function akquise(): array
    {
        require_once __DIR__ . '/TelegramAdmin.php';
        $h = TelegramAdmin::heute();
        $in = "'" . implode("','", TelegramAdmin::INTERESSE) . "'";
        $still = static function (callable $fn): mixed { try { return $fn(); } catch (Throwable $e) { return null; } };
        return [
            'heute' => $h['akquise'], 'schalter' => $h['schalter'],
            'antworten_offen' => $still(static fn() => array_map(static fn($r) => ['firma_id' => (int) $r['firma_id'], 'firma' => $r['name'], 'ort' => $r['stadt'],
                    'klasse' => $r['klasse'], 'betreff' => $r['betreff'], 'auszug' => mb_substr((string) $r['text'], 0, 300), 'am' => $r['eingang_am'],
                    'interesse' => in_array((string) $r['klasse'], TelegramAdmin::INTERESSE, true)],
                Db::all("SELECT a.firma_id, a.klasse, a.betreff, a.text, a.eingang_am, f.name, f.stadt FROM akq_antworten a
                           LEFT JOIN akq_firmen f ON f.id = a.firma_id WHERE a.erledigt = 0
                          ORDER BY (a.klasse IN ($in)) DESC, a.id DESC LIMIT 15"))),
            'wiedervorlagen_faellig' => $still(static fn() => Db::all('SELECT id, name AS firma, stadt AS ort, branche, wiedervorlage_am, naechster_schritt
                    FROM akq_firmen WHERE gesperrt = 0 AND wiedervorlage_am IS NOT NULL AND wiedervorlage_am <= CURDATE() ORDER BY wiedervorlage_am LIMIT 15')),
            'termine_7_tage' => $still(static fn() => Db::all("SELECT beginn, ende, COALESCE(NULLIF(firma,''), name) AS wer, thema, art
                    FROM akq_termine WHERE status = 'gebucht' AND beginn >= CURDATE() AND beginn < CURDATE() + INTERVAL 7 DAY ORDER BY beginn")),
        ];
    }

    private static function ueberwachung(): array
    {
        require_once __DIR__ . '/Cron.php';
        require_once __DIR__ . '/Automation.php';
        require_once __DIR__ . '/SicherungAussen.php';
        $still = static function (callable $fn): mixed { try { return $fn(); } catch (Throwable $e) { return null; } };
        $s = $still(static fn() => SicherungAussen::stand());
        return [
            'seiten' => Db::all("SELECT domain, status, last_status AS http, last_ms AS ms, last_ok_at, last_fail_at, ssl_expires_at AS zertifikat_bis
                                   FROM websites WHERE monitoring = 1 AND demo = 0 AND status <> 'nicht_veroeffentlicht' ORDER BY status = 'online', domain LIMIT 100"),
            'cron' => ['zuletzt' => Cron::zuletzt(),
                       'weg' => (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_weg'", [], '')],
            'sicherung_ausser_haus' => $s === null ? null : ['eingerichtet' => $s['eingerichtet'], 'abgeholt' => $s['abgeholt'], 'probe' => $s['probe']],
            'notaus' => $still(static fn() => Automation::notAusStand()),
            'automationen_mit_fehler' => $still(static fn() => array_values(array_map(static fn($r) => ['regel' => $r['regel'], 'titel' => $r['titel'],
                    'fehler' => $r['fehler'], 'fehler_folge' => $r['fehler_folge'], 'zuletzt' => $r['zuletzt_am']],
                array_filter(array_merge(...array_values(Automation::liste())), static fn($r) => !empty($r['fehler']))))),
        ];
    }

    private static function pruefspur(array $a): array
    {
        $tage = self::zahl($a, 'tage', 7, 1, 365);
        $n = self::zahl($a, 'anzahl', 50, 1, 200);
        $aktion = (string) ($a['aktion'] ?? '');
        if ($aktion !== '' && !preg_match('/^[a-z0-9_]{1,60}$/', $aktion)) { throw new InvalidArgumentException('aktion: nur a-z, 0-9 und _.'); }
        $p = [date('Y-m-d H:i:s', time() - $tage * 86400)];
        $wo = 'created_at >= ?';
        if ($aktion !== '') { $wo .= ' AND action LIKE ?'; $p[] = $aktion . '%'; }
        $zeilen = Db::all("SELECT created_at AS am, actor AS wer, action AS aktion, entity AS was, entity_id AS id, before_json AS vorher, after_json AS nachher
                             FROM audit_log WHERE $wo ORDER BY id DESC LIMIT $n", $p);
        foreach ($zeilen as &$z) {
            foreach (['vorher', 'nachher'] as $f) { if ($z[$f] !== null) { $z[$f] = mb_substr((string) $z[$f], 0, 300); } }
        }
        unset($z);
        return ['eintraege' => $zeilen];
    }

    private static function wissenSuchen(array $a): array
    {
        require_once __DIR__ . '/Wissen.php';
        $frage = trim((string) ($a['frage'] ?? ''));
        if (mb_strlen($frage) < 3) { throw new InvalidArgumentException('frage: mindestens drei Zeichen.'); }
        if (Wissen::stand() === null) { throw new InvalidArgumentException('Das Wissen ist noch nicht gebaut (entsteht beim nächsten Deploy).'); }
        $t = Wissen::suchen(mb_substr($frage, 0, 200), self::zahl($a, 'anzahl', 8, 1, 20));
        return ['frage' => $frage, 'treffer' => $t, 'weiter' => 'Mit wissen_kapitel und der id den ganzen Abschnitt lesen.'];
    }

    private static function wissenKapitel(array $a): array
    {
        require_once __DIR__ . '/Wissen.php';
        $id = (string) ($a['id'] ?? '');
        if (!preg_match('/^[a-z]+-[0-9]{3}$/', $id)) { throw new InvalidArgumentException('id: z. B. „projekt-042“.'); }
        $k = Wissen::kapitel($id, self::zahl($a, 'ab', 0, 0, 10000000));
        if ($k === null) { throw new InvalidArgumentException('Kein Kapitel ' . $id . '. Mit wissen_suchen oder wissen_inhalt nachsehen.'); }
        return $k;
    }

    private static function wissenInhalt(array $a): array
    {
        require_once __DIR__ . '/Wissen.php';
        $st = Wissen::stand();
        if ($st === null) { throw new InvalidArgumentException('Das Wissen ist noch nicht gebaut (entsteht beim nächsten Deploy).'); }
        $seit = (string) ($a['seit'] ?? '');
        if ($seit !== '' && !preg_match('/^20\d\d-\d\d-\d\d$/', $seit)) { throw new InvalidArgumentException('seit: JJJJ-MM-TT.'); }
        return ['stand' => $st['stand'], 'quellen' => $st['quellen'], 'kapitel' => Wissen::inhalt((string) ($a['quelle'] ?? ''), $seit)];
    }

    /* ================================================================== */

    private static function zahl(array $a, string $k, int $ersatz, int $min, int $max): int
    {
        if (!array_key_exists($k, $a) || $a[$k] === null) {
            if ($ersatz < $min) { throw new InvalidArgumentException($k . ' fehlt.'); }
            return $ersatz;
        }
        $v = $a[$k];
        if (!is_int($v) && !(is_string($v) && preg_match('/^-?\d{1,10}$/', $v)) && !(is_float($v) && floor($v) === $v)) {
            throw new InvalidArgumentException($k . ': eine ganze Zahl.');
        }
        $v = (int) $v;
        if ($v < $min || $v > $max) { throw new InvalidArgumentException($k . ': zwischen ' . $min . ' und ' . $max . '.'); }
        return $v;
    }

    /** Ein Link in die Verwaltung, so wie Uwe ihn anklicken kann. */
    private static function verwaltung(string $pfad): string
    {
        if (preg_match('~^https?://~', $pfad)) { return $pfad; }
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . Config::basis() . '/' . ltrim($pfad, '/');
    }
}
