<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';

/**
 * Bauregeln (AutoBuild Phase 10, 07.10.2026, Vorschläge 7 und 8).
 *
 * Was Claude beim Bauen zusätzlich zu den Hausregeln (Standard) beachten muss — von Uwe an einer
 * Stelle gepflegt, jede Änderung eine neue Version (bau_regeln_log.id). Der PC bekommt die Regeln
 * mit jedem Auftrag (BauAuftrag::fuerPc hängt sie an die Hausregeln) — es gilt also immer die
 * aktuelle Fassung, ohne dass auf dem PC etwas geändert wird.
 *
 * „Aus Fehlern lernen“: Findet der Reviewer (oder die Tests) dasselbe Problem bei mindestens zwei
 * verschiedenen Projekten, entsteht ein Vorschlag. Eine Regel wird daraus NUR mit Uwes Ja.
 */
final class BauRegeln
{
    /** Ab so vielen verschiedenen Projekten wird aus einem wiederkehrenden Mangel ein Vorschlag. */
    public const AB_PROJEKTEN = 2;
    /** So weit zurück wird gesucht. */
    public const TAGE = 120;

    /**
     * Wiederkehrende Mängel → vorgeschlagene Regel. Erkannt an Testnamen (BauPruefung) oder Stichworten im Mangeltext.
     * schluessel => [Muster (Regex auf Testname/Mangel), Regeltext]
     */
    public const MUSTER = [
        'alt'          => ['~alt-?text|alt=|bilder? ohne alt|alle bilder mit alt~iu', 'Jedes <img> bekommt einen beschreibenden alt-Text; reine Schmuckbilder alt="".'],
        'h1'           => ['~genau eine h1|mehrere h1|keine h1|\bh1\b~iu', 'Jede Seite hat genau eine H1 — die Hauptaussage der Seite.'],
        'beschreibung' => ['~meta description|seo: beschreibung|beschreibung fehlt~iu', 'Jede Seite bekommt eine eigene meta description (120–160 Zeichen), keine doppelte.'],
        'titel'        => ['~titel nicht doppelt|doppelte? titel|title doppelt~iu', 'Jede Seite bekommt einen eigenen <title> (Seite · Betrieb · Ort).'],
        'tote_links'   => ['~tote[nr]? (interne[nr]? )?links?|fehlende datei|404|link führt ins leere~iu', 'Vor dem Abgeben jeden internen Link und jede Datei gegen die Dateiliste prüfen — kein Verweis auf etwas, das nicht mitgeliefert wird.'],
        'platzhalter'  => ['~platzhalter|lorem|todo|example\.com~iu', 'Nie Platzhalter: fehlt ein Inhalt, den Abschnitt weglassen statt ihn zu füllen.'],
        'kontrast'     => ['~kontrast|contrast~iu', 'Textkontrast mindestens 4.5:1 — auch auf Bildern (Abdunklung) und bei hellem Gold auf Weiß.'],
        'mobil'        => ['~mobil|handy|viewport|responsive|überläuft|horizontal scroll~iu', 'Vor dem Abgeben bei 360 px Breite denken: nichts läuft seitlich über, Tippflächen mindestens 44 px.'],
        'impressum'    => ['~datenschutz|impressum|privacy|note legali~iu', 'Datenschutz und Impressum sind von jeder Seite aus im Fuß verlinkt.'],
        'fremdskript'  => ['~fremden servern|externe[sn]? skript|cdn~iu', 'Keine Skripte von fremden Servern — alles als eigene Datei mitliefern.'],
        'lang'         => ['~sprache gesetzt|html lang|lang-attribut~iu', '<html lang> passt zur Sprache des Kunden (it/de/en).'],
        'kontakt'      => ['~tel:|mailto|whatsapp|kontaktweg|telefonnummer~iu', 'Kontaktwege als Links (tel:, mailto:, wa.me) und nur mit den Angaben des Kunden — fehlt eine, den Weg weglassen.'],
    ];

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /** @return list<array<string,mixed>> */
    public static function liste(bool $auchAus = false): array
    {
        return (array) self::still(static fn() => Db::all('SELECT * FROM bau_regeln' . ($auchAus ? '' : ' WHERE aktiv = 1') . ' ORDER BY aktiv DESC, id'), []);
    }

    /** Die aktuelle Versionsnummer (0 = noch keine eigene Regel). */
    public static function version(): int
    {
        return (int) self::still(static fn() => Db::wert('SELECT COALESCE(MAX(id),0) FROM bau_regeln_log', [], 0), 0);
    }

    /** @return list<array<string,mixed>> */
    public static function verlauf(int $max = 30): array
    {
        return (array) self::still(static fn() => Db::all('SELECT * FROM bau_regeln_log ORDER BY id DESC LIMIT ' . max(1, min(200, $max))), []);
    }

    private static function sauber(string $text): string
    {
        $t = trim((string) preg_replace('~\s+~u', ' ', strip_tags($text)));
        if (mb_strlen($t) < 8) { throw new RuntimeException('Eine Regel braucht einen ganzen Satz (mindestens 8 Zeichen).'); }
        return mb_substr($t, 0, 500);
    }

    private static function log(int $regelId, string $aktion, ?string $vorher, ?string $text, string $wer): int
    {
        return (int) Db::insert('bau_regeln_log', ['regel_id' => $regelId, 'aktion' => $aktion, 'text_vorher' => $vorher, 'text' => $text, 'von' => mb_substr($wer, 0, 120)]);
    }

    public static function hinzufuegen(string $text, string $wer, string $quelle = 'uwe'): int
    {
        $t = self::sauber($text);
        if ((int) Db::wert('SELECT COUNT(*) FROM bau_regeln WHERE aktiv = 1 AND text = ?', [$t], 0) > 0) { throw new RuntimeException('Diese Regel gibt es schon.'); }
        $id = (int) Db::insert('bau_regeln', ['text' => $t, 'quelle' => $quelle === 'vorschlag' ? 'vorschlag' : 'uwe', 'von' => mb_substr($wer, 0, 120)]);
        $v = self::log($id, 'neu', null, $t, $wer);
        Events::pruefspur('bau_regel', 'bau_regeln', $id, [], ['text' => $t, 'version' => $v, 'von' => $wer]);
        return $id;
    }

    public static function aendern(int $id, string $text, string $wer): void
    {
        $r = Db::one('SELECT * FROM bau_regeln WHERE id = ?', [$id]);
        if (!$r) { throw new RuntimeException('Regel nicht gefunden.'); }
        $t = self::sauber($text);
        if ($t === (string) $r['text']) { return; }
        Db::update('bau_regeln', $id, ['text' => $t, 'geaendert_am' => date('Y-m-d H:i:s')]);
        $v = self::log($id, 'geaendert', (string) $r['text'], $t, $wer);
        Events::pruefspur('bau_regel', 'bau_regeln', $id, ['text' => $r['text']], ['text' => $t, 'version' => $v, 'von' => $wer]);
    }

    public static function schalten(int $id, bool $an, string $wer): void
    {
        $r = Db::one('SELECT * FROM bau_regeln WHERE id = ?', [$id]);
        if (!$r || (bool) (int) $r['aktiv'] === $an) { return; }
        Db::update('bau_regeln', $id, ['aktiv' => $an ? 1 : 0, 'geaendert_am' => date('Y-m-d H:i:s')]);
        $v = self::log($id, $an ? 'an' : 'aus', (string) $r['text'], (string) $r['text'], $wer);
        Events::pruefspur('bau_regel', 'bau_regeln', $id, ['aktiv' => (int) $r['aktiv']], ['aktiv' => $an ? 1 : 0, 'version' => $v, 'von' => $wer]);
    }

    /** Was der PC zusätzlich zu den Hausregeln bekommt. Leer, wenn es keine eigene Regel gibt. */
    public static function alsText(): string
    {
        $l = self::liste();
        if (!$l) { return ''; }
        return 'BAUREGELN VON UWE (Version ' . self::version() . ' — verbindlich, gehen allem anderen außer den harten Regeln vor)' . "\n"
            . implode("\n", array_map(static fn($r) => '- ' . $r['text'], $l));
    }

    /* ------------------------------------------------------------------ */
    /*  Aus Fehlern lernen                                                */
    /* ------------------------------------------------------------------ */

    /** Mängel einer Fassung: Reviewer-Liste plus gerissene Tests. @return list<string> */
    public static function maengelVon(array $v): array
    {
        $m = array_values(array_filter(array_map('strval', (array) (json_decode((string) ($v['review_maengel'] ?? ''), true) ?: []))));
        foreach ((array) (json_decode((string) ($v['tests'] ?? ''), true) ?: []) as $t) {
            if (is_array($t) && empty($t['ok'])) { $m[] = 'Test: ' . (string) ($t['name'] ?? ''); }
        }
        return $m;
    }

    /**
     * Sucht wiederkehrende Mängel und legt Vorschläge an. Jeder Schlüssel höchstens einmal —
     * ein abgelehnter Vorschlag kommt nicht wieder. @return int neue Vorschläge
     */
    public static function vorschlaegeAktualisieren(): int
    {
        $vs = (array) self::still(static fn() => Db::all("SELECT v.id, v.project_id, v.nummer, v.tests, v.review_maengel, p.name AS projekt
              FROM projekt_versionen v JOIN projects p ON p.id = v.project_id
             WHERE v.created_at > NOW() - INTERVAL " . self::TAGE . " DAY AND (v.review_maengel IS NOT NULL OR v.tests IS NOT NULL)"), []);
        $treffer = [];   // schluessel => projekt => beleg
        foreach ($vs as $v) {
            foreach (self::maengelVon($v) as $mangel) {
                foreach (self::MUSTER as $schl => [$muster]) {
                    if (preg_match($muster, $mangel) && !isset($treffer[$schl][(int) $v['project_id']])) {
                        $treffer[$schl][(int) $v['project_id']] = ['projekt' => (string) $v['projekt'], 'fassung' => (int) $v['nummer'], 'mangel' => mb_substr($mangel, 0, 200)];
                    }
                }
            }
        }
        $neu = 0;
        $aktiv = array_map(static fn($r) => (string) $r['text'], self::liste());
        foreach ($treffer as $schl => $projekte) {
            if (count($projekte) < self::AB_PROJEKTEN) { continue; }
            $text = self::MUSTER[$schl][1];
            if (in_array($text, $aktiv, true)) { continue; }
            if (Db::wert('SELECT id FROM bau_regel_vorschlaege WHERE schluessel = ?', [$schl], null) !== null) {
                Db::run("UPDATE bau_regel_vorschlaege SET belege = ? WHERE schluessel = ? AND status = 'offen'", [json_encode(array_values($projekte), JSON_UNESCAPED_UNICODE), $schl]);
                continue;
            }
            Db::insert('bau_regel_vorschlaege', ['schluessel' => $schl, 'text' => $text, 'belege' => json_encode(array_values($projekte), JSON_UNESCAPED_UNICODE)]);
            $neu++;
        }
        if ($neu > 0) {
            self::still(static fn() => Events::melden('bauregel_vorschlag', $neu === 1 ? 'Ein Vorschlag für eine neue Bauregel' : "$neu Vorschläge für neue Bauregeln", 'info',
                'Derselbe Mangel kam bei mehreren Projekten vor. Aufgenommen wird nur, was Sie annehmen.', '/bauregeln'), null);
        }
        return $neu;
    }

    /** @return list<array<string,mixed>> */
    public static function vorschlaege(string $status = 'offen'): array
    {
        return (array) self::still(static fn() => Db::all('SELECT * FROM bau_regel_vorschlaege WHERE status = ? ORDER BY id DESC', [$status]), []);
    }

    public static function vorschlagEntscheiden(int $id, bool $annehmen, string $wer): ?int
    {
        $v = Db::one("SELECT * FROM bau_regel_vorschlaege WHERE id = ? AND status = 'offen'", [$id]);
        if (!$v) { throw new RuntimeException('Vorschlag nicht gefunden oder schon entschieden.'); }
        $regel = null;
        if ($annehmen) {
            try { $regel = self::hinzufuegen((string) $v['text'], $wer, 'vorschlag'); }
            catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'gibt es schon')) { throw $e; } }
        }
        Db::update('bau_regel_vorschlaege', $id, ['status' => $annehmen ? 'angenommen' : 'abgelehnt', 'entschieden_von' => mb_substr($wer, 0, 120), 'entschieden_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('bauregel_vorschlag', 'bau_regel_vorschlaege', $id, [], ['entscheidung' => $annehmen ? 'angenommen' : 'abgelehnt', 'von' => $wer]);
        return $regel;
    }
}
