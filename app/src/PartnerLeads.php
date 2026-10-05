<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Texte.php';

/* ==========================================================================
   PartnerLeads.php — Kunden & Leads des Partners (Phase 2, 05.10.2026,
   Uwe: „Ja, wie empfohlen“; Spezifikation Punkt 6–10).

   EINE Liste für alles, was der Partner selbst bearbeitet: Betriebe aus dem
   Firmen-Finder und der Anrufliste, Menschen, die auf seiner Seite um Rückruf
   gebeten haben, Vorab-Festpreise, eigene Kontakte. Jeder hat eine Stufe,
   eine Priorität, eine Quelle und einen Verlauf (Notizen, Aufgaben, Kontakte).

   WAS HIER NICHT STEHT: Kunden, die über Link, QR oder „Melden“ zu Vecom kamen.
   Die sieht der Partner weiter nur als Nummer, Ort und Stufe (PartnerPost::
   empfehlungen) — ohne Namen, wie überall im Partnerbereich.

   REGELN, die hier gelten und geprüft werden (Kette):
   - Jede Abfrage enthält die Partner-ID. Ein fremder Lead ist für diese Klasse
     „nicht vorhanden“ (null), nie ein Fehler mit Inhalt.
   - Dubletten sind ein HINWEIS, nie ein Zusammenführen (Spezifikation Punkt 9).
     Ein Betrieb, den ein anderer Partner reserviert hat, heißt „wird schon
     betreut“ — ohne Namen des anderen.
   - Die Priorität rechnet das System; der Partner kann sie jederzeit setzen
     und wieder auf „automatisch“ stellen.
   - Gelöscht wird nichts: archivieren (Spezifikation Punkt 73).
   ========================================================================== */
final class PartnerLeads
{
    public const STUFEN = ['neu', 'kontaktiert', 'interesse', 'termin', 'angebot', 'auftrag', 'verloren'];
    /** Stufen, die noch „laufen“ (für Zahlen, Sortierung, Startseite). */
    public const OFFEN = ['neu', 'kontaktiert', 'interesse', 'termin', 'angebot'];
    public const PRIO = ['heiss', 'warm', 'normal', 'spaeter'];
    /** Quellen nach Spezifikation Punkt 10, dazu die Wege, die das System selbst kennt. */
    public const QUELLEN = ['eigen', 'empfehlung', 'messe', 'partnerlink', 'qr', 'flyer', 'visitenkarte', 'instagram', 'facebook',
        'telegram', 'whatsapp', 'email', 'landingpage', 'kampagne', 'finder', 'anrufliste', 'vorab'];
    /** Was der Partner beim Eintragen selbst auswählen kann. */
    public const QUELLEN_HAND = ['eigen', 'empfehlung', 'messe', 'visitenkarte', 'flyer', 'qr', 'instagram', 'facebook', 'telegram',
        'whatsapp', 'email', 'kampagne'];
    public const ARTEN = ['notiz', 'anruf', 'whatsapp', 'email', 'aufgabe', 'stufe', 'uebergabe', 'system'];
    /** Gegen Missbrauch: so viele neue Leads und Einträge am Tag. */
    public const NEU_JE_TAG = 60;
    public const EINTRAEGE_JE_TAG = 300;
    /** „Meine Kontakte“ aus dem Browser: höchstens so viele auf einmal (dort sind es höchstens 30). */
    public const IMPORT_HOECHSTENS = 40;
    /** Quellen, die nicht der Partner tippt, sondern ein vorhandener Weg erzeugt — für sie gilt die Tagesgrenze nicht,
        und sie behalten das Datum ihres Ursprungs (sonst wäre eine 40 Tage alte Rückruf-Bitte heute ein „neuer Lead“). */
    private const SYSTEM = ['landingpage', 'vorab', 'finder', 'anrufliste'];
    /** Alte Status aus „Meine Kontakte“ → Stufe. */
    public const IMPORT_STUFE = ['neu' => 'neu', 'angeschrieben' => 'kontaktiert', 'interessiert' => 'interesse', 'kunde' => 'auftrag', 'nein' => 'verloren'];
    /** Branchen aus „Meine Kontakte“ → die zwölf (PartnerBranche, seit Phase 3). */
    public const IMPORT_BRANCHE = ['gastro' => 'gastronomie', 'unterkunft' => 'unterkunft', 'handwerk' => 'handwerk', 'laden' => 'einzelhandel', 'praxis' => 'gesundheit'];

    private static function still(callable $f, mixed $sonst): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }

    /* ---------------------------------------------------------------------
       Normalisieren — dieselben Regeln wie die Akquise, damit „Bar Rossi Srl“
       und „bar rossi“ dasselbe sind.
       --------------------------------------------------------------------- */

    public static function nameNorm(string $name): string
    {
        require_once __DIR__ . '/Akquise.php';
        return mb_substr((string) self::still(static fn() => Akquise::normName($name), mb_strtolower(trim($name))), 0, 120);
    }

    /** Die letzten neun Ziffern — so sind +39 333 …, 0039333… und 333… dieselbe Nummer. */
    public static function telefonNorm(string $tel): string
    {
        $z = (string) preg_replace('~\D~', '', $tel);
        return strlen($z) >= 6 ? substr($z, -9) : '';
    }

    public static function domainAus(string $url): string
    {
        $url = trim($url);
        if ($url === '') { return ''; }
        if (!preg_match('~^https?://~i', $url)) { $url = 'https://' . $url; }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return mb_substr((string) preg_replace('~^www\.~', '', $host), 0, 190);
    }

    /** Ein Wert aus dem Formular, ohne Steuerzeichen, gekürzt. */
    private static function feld(array $d, string $k, int $max): string
    {
        return mb_substr(trim((string) preg_replace('~[\x00-\x1F\x7F]+~u', ' ', (string) ($d[$k] ?? ''))), 0, $max);
    }

    /** @return array{name:string,ansprechpartner:string,branche:string,ort:string,telefon:string,email:string,website:string} */
    private static function stammdaten(array $d): array
    {
        require_once __DIR__ . '/Akquise.php';
        require_once __DIR__ . '/PartnerBranche.php';
        $branche = self::feld($d, 'branche', 40);
        $email = mb_strtolower(self::feld($d, 'email', 190));
        $web = self::feld($d, 'website', 255);
        return [
            'name' => self::feld($d, 'name', 120),
            'ansprechpartner' => self::feld($d, 'ansprechpartner', 80),
            // Die zwölf (Formular, seit Phase 3) oder ein feinerer Schlüssel aus Finder/Anrufliste (bar_cafe …).
            'branche' => in_array($branche, PartnerBranche::ALLE, true) || isset(Akquise::branchen()[$branche]) ? $branche : '',
            'ort' => self::feld($d, 'ort', 80),
            'telefon' => self::feld($d, 'telefon', 40),
            'email' => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
            'website' => $web !== '' && self::domainAus($web) !== '' ? $web : '',
        ];
    }

    /* ---------------------------------------------------------------------
       Lesen — immer mit Partner-ID
       --------------------------------------------------------------------- */

    public static function laden(int $partnerId, int $id): ?array
    {
        return Db::one('SELECT * FROM partner_leads WHERE id = ? AND partner_id = ?', [$id, $partnerId]);
    }

    /**
     * Die Pipeline: alle nicht archivierten Leads, mit wirksamer Priorität und
     * offener Aufgabe. Sortiert: offene vor fertigen, dann heiß → später, dann
     * nach Fälligkeit.
     * @return list<array<string,mixed>>
     */
    public static function liste(int $partnerId, ?string $stufe = null, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $zeilen = Db::all("SELECT l.*,
                (SELECT MIN(v.faellig_am) FROM partner_lead_verlauf v WHERE v.lead_id = l.id AND v.art = 'aufgabe' AND v.erledigt_am IS NULL) AS aufgabe_am,
                (SELECT COUNT(*) FROM partner_lead_verlauf v WHERE v.lead_id = l.id AND v.art = 'aufgabe' AND v.erledigt_am IS NULL) AS aufgaben
              FROM partner_leads l WHERE l.partner_id = ? AND l.archiviert_am IS NULL"
              . ($stufe !== null && in_array($stufe, self::STUFEN, true) ? ' AND l.stufe = ' . Db::pdo()->quote($stufe) : '')
              . ' ORDER BY l.id DESC LIMIT 500', [$partnerId]);
        $heiss = self::heisseDomains($partnerId, $jetzt);
        foreach ($zeilen as &$z) { $z['prio'] = self::prioritaet($z, $heiss, $jetzt); }
        unset($z);
        $rang = array_flip(self::PRIO);
        usort($zeilen, static function (array $a, array $b) use ($rang): int {
            $oa = in_array($a['stufe'], self::OFFEN, true) ? 0 : 1;
            $ob = in_array($b['stufe'], self::OFFEN, true) ? 0 : 1;
            if ($oa !== $ob) { return $oa <=> $ob; }
            if ($rang[$a['prio']] !== $rang[$b['prio']]) { return $rang[$a['prio']] <=> $rang[$b['prio']]; }
            $fa = (string) ($a['aufgabe_am'] ?? $a['naechster_am'] ?? '9999');
            $fb = (string) ($b['aufgabe_am'] ?? $b['naechster_am'] ?? '9999');
            return $fa === $fb ? ((int) $b['id'] <=> (int) $a['id']) : strcmp($fa, $fb);
        });
        return $zeilen;
    }

    /** Wie viele Leads je Stufe (nicht archiviert). @return array<string,int> */
    public static function zaehlen(int $partnerId): array
    {
        $aus = array_fill_keys(self::STUFEN, 0);
        foreach (Db::all('SELECT stufe, COUNT(*) AS n FROM partner_leads WHERE partner_id = ? AND archiviert_am IS NULL GROUP BY stufe', [$partnerId]) as $r) {
            if (isset($aus[$r['stufe']])) { $aus[$r['stufe']] = (int) $r['n']; }
        }
        return $aus;
    }

    /** Neue Leads der letzten Tage (Startseite: „Neue Leads“). */
    public static function neu(int $partnerId, int $tage = 30): int
    {
        return (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM partner_leads WHERE partner_id = ? AND created_at >= NOW() - INTERVAL '
            . max(1, min(365, $tage)) . ' DAY', [$partnerId], 0), 0);
    }

    /** Offene Aufgaben, die heute oder früher fällig sind. @return list<array<string,mixed>> */
    public static function faelligeAufgaben(int $partnerId, ?string $heute = null): array
    {
        $heute ??= date('Y-m-d');
        return (array) self::still(static fn() => Db::all("SELECT v.id, v.lead_id, v.text, v.faellig_am, l.name FROM partner_lead_verlauf v
              JOIN partner_leads l ON l.id = v.lead_id AND l.partner_id = v.partner_id AND l.archiviert_am IS NULL
             WHERE v.partner_id = ? AND v.art = 'aufgabe' AND v.erledigt_am IS NULL AND v.faellig_am IS NOT NULL AND v.faellig_am <= ?
             ORDER BY v.faellig_am, v.id LIMIT 50", [$partnerId, $heute]), []);
    }

    /** @return list<array<string,mixed>> neueste zuerst */
    public static function verlauf(int $partnerId, int $leadId): array
    {
        return Db::all('SELECT * FROM partner_lead_verlauf WHERE lead_id = ? AND partner_id = ? ORDER BY created_at DESC, id DESC LIMIT 200',
            [$leadId, $partnerId]);
    }

    /** Hosts, deren Website-Check in den letzten 48 Stunden geöffnet wurde (PartnerMarketing::heisse). @return array<string,bool> */
    private static function heisseDomains(int $partnerId, int $jetzt): array
    {
        $aus = [];
        foreach ((array) self::still(static fn() => Db::all('SELECT host FROM partner_checks WHERE partner_id = ? AND zuletzt_am >= ?',
            [$partnerId, date('Y-m-d H:i:s', $jetzt - 48 * 3600)]), []) as $r) {
            $aus[(string) preg_replace('~^www\.~', '', strtolower((string) $r['host']))] = true;
        }
        return $aus;
    }

    /**
     * Wirksame Priorität (Spezifikation Punkt 8). Von Hand gesetzt gewinnt.
     * Sonst: heiß, wenn jemand selbst um Kontakt bat (48 h), sein Website-Check
     * gerade geöffnet wurde oder er Interesse/Termin hat; warm, wenn in den
     * letzten 7 Tagen Kontakt war oder der nächste Schritt fällig ist; später,
     * wenn der nächste Schritt über 14 Tage weg ist oder der Lead fertig ist.
     * @param array<string,bool> $heiss
     */
    public static function prioritaet(array $l, array $heiss = [], ?int $jetzt = null): string
    {
        $jetzt ??= time();
        if (in_array((string) ($l['prioritaet'] ?? ''), self::PRIO, true)) { return (string) $l['prioritaet']; }
        if (!in_array((string) $l['stufe'], self::OFFEN, true)) { return 'spaeter'; }
        $vor = static fn(?string $t, int $s): bool => $t !== null && $t !== '' && strtotime($t) >= $jetzt - $s;
        if (in_array($l['stufe'], ['interesse', 'termin'], true)
            || (!empty($l['freigabe_id']) && $vor((string) $l['created_at'], 48 * 3600))
            || ((string) $l['domain'] !== '' && isset($heiss[(string) $l['domain']]))) { return 'heiss'; }
        $faellig = (string) ($l['aufgabe_am'] ?? $l['naechster_am'] ?? '');
        if ($vor($l['kontakt_am'] ?? null, 7 * 86400) || ($faellig !== '' && $faellig <= date('Y-m-d', $jetzt))) { return 'warm'; }
        $naechst = (string) ($l['naechster_am'] ?? '');
        if ($naechst !== '' && $naechst > date('Y-m-d', $jetzt + 14 * 86400)) { return 'spaeter'; }
        return 'normal';
    }

    /* ---------------------------------------------------------------------
       Dubletten — ein Hinweis, nie ein Zusammenführen (Punkt 9)
       --------------------------------------------------------------------- */

    /**
     * Prüft Firma (Name + Ort), Domain, Telefon und E-Mail gegen die eigenen Leads
     * und gegen Betriebe, die ein ANDERER Partner gerade reserviert hat.
     * @return list<array{art:string, grund:string, id?:int, name?:string}>
     *         art = eigen (mit id und Name des eigenen Leads) | betreut (ohne jeden Namen)
     */
    public static function dubletten(int $partnerId, array $d, ?int $ohneId = null): array
    {
        $s = self::stammdaten($d);
        $norm = self::nameNorm($s['name']);
        $tel = self::telefonNorm($s['telefon']);
        $dom = self::domainAus($s['website']);
        $aus = []; $gesehen = [];
        $dazu = static function (array $r, string $grund) use (&$aus, &$gesehen, $ohneId): void {
            $id = (int) $r['id'];
            if ($id === $ohneId || isset($gesehen[$id])) { return; }
            $gesehen[$id] = true;
            $aus[] = ['art' => 'eigen', 'grund' => $grund, 'id' => $id, 'name' => (string) $r['name']];
        };
        $basis = 'SELECT id, name FROM partner_leads WHERE partner_id = ? AND archiviert_am IS NULL AND ';
        if ($dom !== '') { foreach (Db::all($basis . 'domain = ? LIMIT 3', [$partnerId, $dom]) as $r) { $dazu($r, 'domain'); } }
        if ($tel !== '') { foreach (Db::all($basis . 'telefon_norm = ? LIMIT 3', [$partnerId, $tel]) as $r) { $dazu($r, 'telefon'); } }
        if ($s['email'] !== '') { foreach (Db::all($basis . 'email = ? LIMIT 3', [$partnerId, $s['email']]) as $r) { $dazu($r, 'email'); } }
        if ($norm !== '') {
            $ort = mb_strtolower($s['ort']);
            foreach (Db::all($basis . 'name_norm = ? LIMIT 5', [$partnerId, $norm]) as $r) {
                $o = mb_strtolower((string) Db::wert('SELECT ort FROM partner_leads WHERE id = ?', [(int) $r['id']], ''));
                if ($ort === '' || $o === '' || $o === $ort) { $dazu($r, 'name'); }
            }
        }
        // Ein anderer Partner betreut den Betrieb gerade (aktive Reservierung) — ohne seinen Namen.
        $betreut = (int) self::still(static function () use ($partnerId, $dom, $norm, $s): int {
            $bed = []; $par = [];
            if ($dom !== '') { $bed[] = 'f.domain = ?'; $par[] = $dom; }
            if ($norm !== '' && $s['ort'] !== '') { $bed[] = '(f.name_norm = ? AND LOWER(f.stadt) = ?)'; $par[] = $norm; $par[] = mb_strtolower($s['ort']); }
            if (!$bed) { return 0; }
            return (int) Db::wert('SELECT COUNT(*) FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                                    WHERE r.partner_id <> ? AND r.bis >= CURDATE() AND (' . implode(' OR ', $bed) . ')', array_merge([$partnerId], $par), 0);
        }, 0);
        if ($betreut > 0) { $aus[] = ['art' => 'betreut', 'grund' => 'reserviert']; }
        return $aus;
    }

    /* ---------------------------------------------------------------------
       Schreiben — jede Tat prüft zuerst, dass der Lead dem Partner gehört
       --------------------------------------------------------------------- */

    /**
     * Einen Kontakt anlegen. Mit Dubletten und ohne $trotzdem kommt die Liste
     * zurück und nichts wird angelegt — der Partner entscheidet.
     * @return array{ok:bool, id?:int, grund?:string, dubletten?:list<array<string,mixed>>}
     */
    public static function anlegen(int $partnerId, array $d, bool $trotzdem = false, string $quelleSystem = ''): array
    {
        $s = self::stammdaten($d);
        if (mb_strlen($s['name']) < 2) { return ['ok' => false, 'grund' => 'name']; }
        $quelle = $quelleSystem !== '' && in_array($quelleSystem, self::QUELLEN, true) ? $quelleSystem
            : (in_array((string) ($d['quelle'] ?? ''), self::QUELLEN_HAND, true) ? (string) $d['quelle'] : 'eigen');
        $stufe = in_array((string) ($d['stufe'] ?? ''), self::STUFEN, true) ? (string) $d['stufe'] : 'neu';
        $system = in_array($quelleSystem, self::SYSTEM, true);
        $ph = implode(',', array_fill(0, count(self::SYSTEM), '?'));
        if (!$system && (int) Db::wert("SELECT COUNT(*) FROM partner_leads WHERE partner_id = ? AND created_at >= CURDATE() AND quelle NOT IN ($ph)",
                array_merge([$partnerId], self::SYSTEM), 0) >= self::NEU_JE_TAG) {
            return ['ok' => false, 'grund' => 'genug'];
        }
        $seit = $system && is_string($d['seit'] ?? null) && preg_match('~^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$~', $d['seit']) && strtotime($d['seit']) <= time()
            ? ['created_at' => $d['seit'], 'stufe_am' => $d['seit']] : [];
        if (!$trotzdem) {
            $dup = self::dubletten($partnerId, $d);
            if ($dup) { return ['ok' => false, 'grund' => 'dublette', 'dubletten' => $dup]; }
        }
        $id = (int) Db::insert('partner_leads', $s + [
            'partner_id' => $partnerId, 'name_norm' => self::nameNorm($s['name']), 'telefon_norm' => self::telefonNorm($s['telefon']),
            'domain' => self::domainAus($s['website']), 'quelle' => $quelle, 'stufe' => $stufe,
            'kampagne_id' => !empty($d['kampagne_id']) ? (int) $d['kampagne_id'] : null,
            'firma_id' => !empty($d['firma_id']) ? (int) $d['firma_id'] : null,
            'freigabe_id' => !empty($d['freigabe_id']) ? (int) $d['freigabe_id'] : null,
            'vorab_id' => !empty($d['vorab_id']) ? (int) $d['vorab_id'] : null,
        ] + $seit);
        $notiz = self::feld($d, 'notiz', 1000);
        if ($notiz !== '') { self::eintrag($partnerId, $id, 'notiz', $notiz); }
        return ['ok' => true, 'id' => $id];
    }

    /** Stammdaten ändern (Name, Kontakt, Branche, Ort, Website). */
    public static function aendern(int $partnerId, int $id, array $d): bool
    {
        $l = self::laden($partnerId, $id);
        if (!$l) { return false; }
        $s = self::stammdaten($d + ['name' => $l['name']]);
        if (mb_strlen($s['name']) < 2) { return false; }
        Db::run('UPDATE partner_leads SET name = ?, name_norm = ?, ansprechpartner = ?, branche = ?, ort = ?, telefon = ?, telefon_norm = ?,
                 email = ?, website = ?, domain = ? WHERE id = ? AND partner_id = ?',
            [$s['name'], self::nameNorm($s['name']), $s['ansprechpartner'], $s['branche'], $s['ort'], $s['telefon'], self::telefonNorm($s['telefon']),
             $s['email'], $s['website'], self::domainAus($s['website']), $id, $partnerId]);
        return true;
    }

    public static function stufeSetzen(int $partnerId, int $id, string $stufe, string $wer = 'partner'): bool
    {
        $l = self::laden($partnerId, $id);
        if (!$l || !in_array($stufe, self::STUFEN, true)) { return false; }
        if ($l['stufe'] === $stufe) { return true; }
        Db::run('UPDATE partner_leads SET stufe = ?, stufe_am = NOW() WHERE id = ? AND partner_id = ?', [$stufe, $id, $partnerId]);
        self::eintrag($partnerId, $id, 'stufe', $l['stufe'] . '→' . $stufe . ($wer !== 'partner' ? ' (' . $wer . ')' : ''));
        return true;
    }

    /** Nur vorwärts — für Wege, die eine Stufe belegen (Anruf zugestimmt, Vorab eingelöst …). Verloren ist kein „vorwärts“. */
    public static function stufeMindestens(int $partnerId, int $id, string $stufe, string $wer): bool
    {
        $l = self::laden($partnerId, $id);
        if (!$l || !in_array($stufe, self::STUFEN, true)) { return false; }
        $rang = array_flip(self::STUFEN);
        if ($stufe === 'verloren' && $l['stufe'] === 'auftrag') { return false; }   // ein Auftrag geht nie von allein verloren
        if ($stufe !== 'verloren' && $l['stufe'] !== 'verloren' && $rang[$stufe] <= $rang[$l['stufe']]) { return false; }
        return self::stufeSetzen($partnerId, $id, $stufe, $wer);
    }

    /** @param string|null $prio null = wieder automatisch */
    public static function prioritaetSetzen(int $partnerId, int $id, ?string $prio): bool
    {
        if ($prio !== null && !in_array($prio, self::PRIO, true)) { return false; }
        return Db::run('UPDATE partner_leads SET prioritaet = ? WHERE id = ? AND partner_id = ?', [$prio, $id, $partnerId])->rowCount() > 0
            || (bool) self::laden($partnerId, $id);
    }

    public static function naechsterSchritt(int $partnerId, int $id, string $text, ?string $datum): bool
    {
        if (!self::laden($partnerId, $id)) { return false; }
        $datum = $datum !== null && preg_match('~^\d{4}-\d{2}-\d{2}$~', $datum) && strtotime($datum) ? $datum : null;
        Db::run('UPDATE partner_leads SET naechster_schritt = ?, naechster_am = ? WHERE id = ? AND partner_id = ?',
            [mb_substr(trim($text), 0, 160), $datum, $id, $partnerId]);
        return true;
    }

    /** Notiz, Aufgabe oder Kontakt eintragen. @return int ID des Eintrags oder 0 */
    public static function eintrag(int $partnerId, int $leadId, string $art, string $text, ?string $faellig = null): int
    {
        if (!in_array($art, self::ARTEN, true) || !self::laden($partnerId, $leadId)) { return 0; }
        $text = mb_substr(trim((string) preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', $text)), 0, 1000);
        if (in_array($art, ['notiz', 'aufgabe'], true) && $text === '') { return 0; }
        if ((int) Db::wert('SELECT COUNT(*) FROM partner_lead_verlauf WHERE partner_id = ? AND created_at >= CURDATE()', [$partnerId], 0) >= self::EINTRAEGE_JE_TAG) {
            return 0;
        }
        $faellig = $art === 'aufgabe' && $faellig !== null && preg_match('~^\d{4}-\d{2}-\d{2}$~', $faellig) && strtotime($faellig) ? $faellig : null;
        return (int) Db::insert('partner_lead_verlauf', ['lead_id' => $leadId, 'partner_id' => $partnerId, 'art' => $art, 'text' => $text, 'faellig_am' => $faellig]);
    }

    public static function aufgabeErledigt(int $partnerId, int $eintragId): bool
    {
        return Db::run("UPDATE partner_lead_verlauf SET erledigt_am = NOW() WHERE id = ? AND partner_id = ? AND art = 'aufgabe' AND erledigt_am IS NULL",
            [$eintragId, $partnerId])->rowCount() > 0;
    }

    /**
     * Anrufen, WhatsApp oder E-Mail angetippt (Schnellfunktion): im Verlauf
     * vermerken, „letzter Kontakt“ setzen, und aus NEU wird KONTAKTIERT.
     */
    public static function kontakt(int $partnerId, int $id, string $art): bool
    {
        if (!in_array($art, ['anruf', 'whatsapp', 'email'], true) || !self::laden($partnerId, $id)) { return false; }
        // Doppeltippen zählt einmal: höchstens ein Eintrag derselben Art je 10 Minuten.
        $kurz = (int) Db::wert('SELECT COUNT(*) FROM partner_lead_verlauf WHERE lead_id = ? AND partner_id = ? AND art = ? AND created_at >= NOW() - INTERVAL 10 MINUTE',
            [$id, $partnerId, $art], 0);
        if ($kurz === 0) { self::eintrag($partnerId, $id, $art, ''); }
        Db::run('UPDATE partner_leads SET kontakt_am = NOW() WHERE id = ? AND partner_id = ?', [$id, $partnerId]);
        self::stufeMindestens($partnerId, $id, 'kontaktiert', 'schnellfunktion');
        return true;
    }

    public static function archivieren(int $partnerId, int $id, bool $zurueck = false): bool
    {
        return Db::run('UPDATE partner_leads SET archiviert_am = ' . ($zurueck ? 'NULL' : 'NOW()') . ' WHERE id = ? AND partner_id = ?',
            [$id, $partnerId])->rowCount() > 0;
    }

    /**
     * „An Vecom übergeben“ (Punkt 27): derselbe Weg wie „Melden“ — mit Haken
     * „einverstanden“, E-Mail Pflicht, höchstens 10 am Tag. Der Partner
     * erfährt nie, ob der Kunde schon bei Vecom war; der Lead merkt sich nur,
     * DASS er übergeben wurde.
     * @return array{ok:bool, grund?:string}
     */
    public static function uebergeben(int $partnerId, int $id, array $d, string $sprache): array
    {
        $l = self::laden($partnerId, $id);
        if (!$l) { return ['ok' => false, 'grund' => 'panne']; }
        if (!empty($l['uebergeben_am'])) { return ['ok' => true]; }
        require_once __DIR__ . '/Partner.php';
        $r = Partner::kundeMelden($partnerId, [
            'name' => (string) ($d['ansprechpartner'] ?? '') !== '' ? trim((string) $d['ansprechpartner']) . ' (' . $l['name'] . ')' : (string) $l['name'],
            'firma' => (string) $l['name'], 'email' => (string) ($d['email'] ?? $l['email']), 'telefon' => (string) ($d['telefon'] ?? $l['telefon']),
            'anliegen' => mb_substr(trim((string) ($d['anliegen'] ?? '')), 0, 1500), 'sprache' => (string) ($d['sprache'] ?? $sprache),
            'einverstanden' => !empty($d['einverstanden']),
        ], $sprache);
        if (!$r['ok']) { return $r; }
        Db::run('UPDATE partner_leads SET uebergeben_am = NOW() WHERE id = ? AND partner_id = ?', [$id, $partnerId]);
        self::eintrag($partnerId, $id, 'uebergabe', '');
        return ['ok' => true];
    }

    /**
     * „Meine Kontakte“ aus dem Browser übernehmen — nur auf Klick des Partners.
     * Dubletten (gleicher Name) werden übersprungen, nicht zusammengeführt.
     * @return array{neu:int, schon:int}
     */
    public static function importieren(int $partnerId, array $liste): array
    {
        $neu = 0; $schon = 0;
        foreach (array_slice($liste, 0, self::IMPORT_HOECHSTENS) as $k) {
            if (!is_array($k)) { continue; }
            $name = mb_substr(trim((string) ($k['name'] ?? '')), 0, 60);
            if (mb_strlen($name) < 2) { continue; }
            $norm = self::nameNorm($name);
            if ((int) Db::wert('SELECT COUNT(*) FROM partner_leads WHERE partner_id = ? AND name_norm = ?', [$partnerId, $norm], 0) > 0) { $schon++; continue; }
            $r = self::anlegen($partnerId, ['name' => $name, 'branche' => self::IMPORT_BRANCHE[(string) ($k['branche'] ?? '')] ?? '',
                'stufe' => self::IMPORT_STUFE[(string) ($k['status'] ?? '')] ?? 'neu', 'notiz' => mb_substr(trim((string) ($k['notiz'] ?? '')), 0, 120)], true, 'eigen');
            if ($r['ok']) { $neu++; }
        }
        return ['neu' => $neu, 'schon' => $schon];
    }

    /* ---------------------------------------------------------------------
       Mitschreiben aus den alten Wegen (Phase 2d)
       --------------------------------------------------------------------- */

    /** Reservierung (Finder oder Anrufliste) → Lead, falls es noch keinen gibt. @return int Lead-ID oder 0 */
    public static function ausFirma(int $partnerId, int $firmaId, string $quelle = 'finder'): int
    {
        $da = (int) Db::wert('SELECT id FROM partner_leads WHERE partner_id = ? AND firma_id = ?', [$partnerId, $firmaId], 0);
        if ($da > 0) {
            Db::run('UPDATE partner_leads SET archiviert_am = NULL WHERE id = ? AND partner_id = ?', [$da, $partnerId]);
            return $da;
        }
        $f = Db::one('SELECT name, name_norm, branche, stadt, telefon, email, url, domain FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { return 0; }
        // Ab der Reservierung zählen: Ein Anrufergebnis von gestern gilt so auch für einen Lead, den der Abgleich erst heute anlegt.
        $seit = (string) Db::wert('SELECT MIN(created_at) FROM partner_reservierungen WHERE partner_id = ? AND firma_id = ? AND bis >= CURDATE()', [$partnerId, $firmaId], '');
        $seit = $seit !== '' && strtotime($seit) <= time() ? $seit : date('Y-m-d H:i:s');
        try {
            return (int) Db::insert('partner_leads', ['partner_id' => $partnerId, 'name' => mb_substr((string) $f['name'], 0, 120),
                'name_norm' => mb_substr((string) ($f['name_norm'] ?? ''), 0, 120) ?: self::nameNorm((string) $f['name']),
                'branche' => mb_substr((string) ($f['branche'] ?? ''), 0, 40), 'ort' => mb_substr((string) ($f['stadt'] ?? ''), 0, 80),
                'telefon' => mb_substr((string) ($f['telefon'] ?? ''), 0, 40), 'telefon_norm' => self::telefonNorm((string) ($f['telefon'] ?? '')),
                'email' => mb_substr((string) ($f['email'] ?? ''), 0, 190), 'website' => mb_substr((string) ($f['url'] ?? ''), 0, 255),
                'domain' => mb_substr((string) ($f['domain'] ?? ''), 0, 190), 'quelle' => in_array($quelle, self::QUELLEN, true) ? $quelle : 'finder',
                'firma_id' => $firmaId, 'created_at' => $seit, 'stufe_am' => $seit]);
        } catch (PDOException $e) {
            if (!Db::doppelt($e, 'uq_pl_firma')) { throw $e; }
            return (int) Db::wert('SELECT id FROM partner_leads WHERE partner_id = ? AND firma_id = ?', [$partnerId, $firmaId], 0);
        }
    }

    /** Ein Besucher bat auf der Partnerseite um Rückruf (eigener Haken) → Lead. */
    public static function ausFreigabe(int $partnerId, int $freigabeId): int
    {
        $k = Db::one('SELECT * FROM partner_kontaktfreigaben WHERE id = ? AND partner_id = ?', [$freigabeId, $partnerId]);
        if (!$k) { return 0; }
        $da = (int) Db::wert('SELECT id FROM partner_leads WHERE freigabe_id = ?', [$freigabeId], 0);
        if ($da > 0) { return $da; }
        $r = self::anlegen($partnerId, ['name' => (string) $k['name'], 'telefon' => (string) ($k['telefon'] ?? ''), 'email' => (string) ($k['email'] ?? ''),
            'freigabe_id' => $freigabeId, 'seit' => (string) $k['created_at']], true, 'landingpage');
        return (int) ($r['id'] ?? 0);
    }

    /** Ein Vorab-Festpreis wurde angelegt → Lead in ANGEBOT. */
    public static function ausVorab(int $partnerId, int $vorabId, string $bezeichnung): int
    {
        $da = (int) Db::wert('SELECT id FROM partner_leads WHERE vorab_id = ?', [$vorabId], 0);
        if ($da > 0) { return $da; }
        $name = trim($bezeichnung) !== '' ? $bezeichnung : 'Festpreis ' . $vorabId;
        $seit = (string) Db::wert('SELECT created_at FROM partner_vorab WHERE id = ? AND partner_id = ?', [$vorabId, $partnerId], '');
        if ($seit === '') { return 0; }
        $r = self::anlegen($partnerId, ['name' => $name, 'stufe' => 'angebot', 'vorab_id' => $vorabId, 'seit' => $seit], true, 'vorab');
        return (int) ($r['id'] ?? 0);
    }

    /**
     * Abgleich mit den alten Wegen (Phase 2d) — EINE Stelle statt Haken in zehn
     * Formularen: Beim Öffnen von Start oder Kunden holt die Pipeline nach, was
     * Finder, Anrufliste, Rückruf-Freigaben und Vorab inzwischen erzeugt haben,
     * und übernimmt Ergebnisse, die NEUER sind als die letzte Stufenänderung
     * (so überschreibt ein altes „kein Interesse“ nie eine spätere Hand-Entscheidung).
     * @return array{neu:int, stufen:int}
     */
    public static function abgleich(int $partnerId): array
    {
        $neu = 0; $stufen = 0;
        $tun = static function (callable $f) { try { return $f(); } catch (Throwable $e) { error_log('PartnerLeads::abgleich: ' . $e->getMessage()); return null; } };
        // 1. Laufende Reservierungen ohne Lead
        foreach ((array) $tun(static fn() => Db::all('SELECT r.firma_id, r.herkunft FROM partner_reservierungen r
                 WHERE r.partner_id = ? AND r.bis >= CURDATE()
                   AND NOT EXISTS (SELECT 1 FROM partner_leads l WHERE l.partner_id = r.partner_id AND l.firma_id = r.firma_id) LIMIT 200', [$partnerId])) as $r) {
            if (self::ausFirma($partnerId, (int) $r['firma_id'], $r['herkunft'] === 'vecom' ? 'anrufliste' : 'finder') > 0) { $neu++; }
        }
        // 2. Offene Rückruf-Freigaben ohne Lead
        foreach ((array) $tun(static fn() => Db::all('SELECT k.id FROM partner_kontaktfreigaben k WHERE k.partner_id = ? AND k.erledigt_am IS NULL
                   AND NOT EXISTS (SELECT 1 FROM partner_leads l WHERE l.freigabe_id = k.id) LIMIT 100', [$partnerId])) as $r) {
            if (self::ausFreigabe($partnerId, (int) $r['id']) > 0) { $neu++; }
        }
        // 3. Laufende Vorab-Festpreise ohne Lead
        foreach ((array) $tun(static fn() => Db::all("SELECT v.id, v.bezeichnung FROM partner_vorab v WHERE v.partner_id = ? AND v.status IN ('offen','einloesen','eingeloest')
                   AND NOT EXISTS (SELECT 1 FROM partner_leads l WHERE l.vorab_id = v.id) LIMIT 100", [$partnerId])) as $r) {
            if (self::ausVorab($partnerId, (int) $r['id'], (string) $r['bezeichnung']) > 0) { $neu++; }
        }
        // 4. Anrufergebnis oder „angeschrieben“, neuer als die letzte Stufe
        $karte = ['zugestimmt' => 'interesse', 'nicht_erreicht' => 'kontaktiert', 'kein_interesse' => 'verloren', 'nicht_erreichbar' => 'verloren'];
        foreach ((array) $tun(static fn() => Db::all('SELECT l.id, l.stufe, l.stufe_am, r.anruf_status, r.anruf_am, r.angeschrieben_am FROM partner_leads l
                 JOIN partner_reservierungen r ON r.firma_id = l.firma_id AND r.partner_id = l.partner_id
                WHERE l.partner_id = ? AND l.archiviert_am IS NULL
                  AND ((r.anruf_am IS NOT NULL AND r.anruf_am > l.stufe_am) OR (r.angeschrieben_am IS NOT NULL AND r.angeschrieben_am > l.stufe_am))', [$partnerId])) as $r) {
            $ziel = ($r['anruf_am'] !== null && $r['anruf_am'] > $r['stufe_am']) ? ($karte[(string) $r['anruf_status']] ?? null) : 'kontaktiert';
            $wann = max((string) ($r['anruf_am'] ?? ''), (string) ($r['angeschrieben_am'] ?? ''));
            Db::run('UPDATE partner_leads SET kontakt_am = GREATEST(COALESCE(kontakt_am, ?), ?) WHERE id = ?', [$wann, $wann, (int) $r['id']]);
            if ($ziel !== null && self::stufeMindestens($partnerId, (int) $r['id'], $ziel, $ziel === 'kontaktiert' && $r['anruf_am'] === null ? 'angeschrieben' : 'anrufliste')) { $stufen++; }
            else { Db::run('UPDATE partner_leads SET stufe_am = ? WHERE id = ? AND stufe_am < ?', [$wann, (int) $r['id'], $wann]); }   // gesehen — nicht noch einmal prüfen
        }
        // 5. Vorab: angenommen → Auftrag; abgelehnt oder zurückgezogen → verloren
        foreach ((array) $tun(static fn() => Db::all("SELECT l.id, v.status, a.status AS angebot FROM partner_leads l JOIN partner_vorab v ON v.id = l.vorab_id
                 LEFT JOIN angebote a ON a.id = v.angebot_id
                WHERE l.partner_id = ? AND l.archiviert_am IS NULL AND l.stufe NOT IN ('auftrag','verloren')", [$partnerId])) as $r) {
            $ziel = $r['angebot'] === 'angenommen' ? 'auftrag' : ($r['status'] === 'zurueckgezogen' || $r['angebot'] === 'abgelehnt' ? 'verloren' : null);
            if ($ziel !== null && self::stufeMindestens($partnerId, (int) $r['id'], $ziel, 'vorab')) { $stufen++; }
        }
        return ['neu' => $neu, 'stufen' => $stufen];
    }

    /**
     * Datenschutz wie bei den Kontaktfreigaben (90 Tage): Wurde ein Lead aus einer
     * Freigabe nie bearbeitet (noch NEU, kein Kontakt, kein Eintrag), verschwinden
     * Name und Nummer mit der Freigabe. Bearbeitete Leads bleiben — dann ist es
     * ein laufender Kontakt des Partners.
     */
    public static function freigabenAufraeumen(): int
    {
        return (int) self::still(static fn() => Db::run("UPDATE partner_leads l
              LEFT JOIN partner_kontaktfreigaben k ON k.id = l.freigabe_id
                 SET l.name = '—', l.name_norm = '', l.telefon = '', l.telefon_norm = '', l.email = '', l.archiviert_am = COALESCE(l.archiviert_am, NOW())
               WHERE l.freigabe_id IS NOT NULL AND k.id IS NULL AND l.stufe = 'neu' AND l.kontakt_am IS NULL AND l.name <> '—'
                 AND NOT EXISTS (SELECT 1 FROM partner_lead_verlauf v WHERE v.lead_id = l.id)")->rowCount(), 0);
    }

    /* ---------------------------------------------------------------------
       Verwaltung: Leads eines Partners — ohne Notizen (Uwe: „wie empfohlen“)
       --------------------------------------------------------------------- */

    /** @return list<array{name:string, stufe:string, quelle:string, created_at:string, letzter:string, letzter_am:?string, uebergeben_am:?string}> */
    public static function fuerVerwaltung(int $partnerId, int $max = 200): array
    {
        $aus = [];
        foreach (Db::all('SELECT id, name, stufe, quelle, created_at, uebergeben_am, archiviert_am FROM partner_leads WHERE partner_id = ? ORDER BY updated_at DESC LIMIT '
            . max(1, min(500, $max)), [$partnerId]) as $l) {
            // Nur die ART des letzten Schritts und das Datum — nie der Text einer Notiz oder Aufgabe.
            $v = Db::one('SELECT art, created_at FROM partner_lead_verlauf WHERE lead_id = ? ORDER BY created_at DESC, id DESC LIMIT 1', [(int) $l['id']]);
            $aus[] = ['name' => (string) $l['name'], 'stufe' => (string) $l['stufe'], 'quelle' => (string) $l['quelle'],
                      'created_at' => (string) $l['created_at'], 'letzter' => (string) ($v['art'] ?? ''), 'letzter_am' => $v['created_at'] ?? null,
                      'uebergeben_am' => $l['uebergeben_am'], 'archiviert' => $l['archiviert_am'] !== null];
        }
        return $aus;
    }
}
