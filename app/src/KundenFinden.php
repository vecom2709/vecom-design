<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';

/* ==========================================================================
   Kunden finden, ohne doppelt zu finden (07.10.2026, Uwe: „wenn Kunden aussortiert
   worden sind oder schon Kunden sind, nicht nochmal auf die Liste — bzw. markieren“).

   1  Abgleich mit der Kundenliste: E-Mail, Domain (Website, veröffentlichte Seite,
      Firmen-Mail), Telefon, Partita IVA, Name + Ort. Neue Funde, die schon Kunde
      sind, kommen gar nicht erst in die Liste; vorhandene werden „Schon Kunde“.
   2  Ausschluss mit Grund (akq_aussortiert.grund) — zurück kommt nur „ohne Kontaktweg“.
   4  Dubletten-Verdacht (Telefon, E-Mail, P.IVA, ähnlicher Name am selben Ort) und
      Zusammenführen mit einem Klick.
   6  Bereinigung der ganzen Liste in Paketen (Cron), bis alles einmal geprüft ist.
   7–12 Zusätze zur Priorität: Neueröffnung, Saison, „wie gewonnene Kunden“,
      lernende Branchenzahlen, fremde Agentur.
   13 Gebietsplan: das nächste Gebiet automatisch suchen lassen.
   14 „Später“ statt löschen. 15 Tagesliste „Heute ansprechen“.

   Nichts hier schreibt jemanden an. Die Tagesliste ist ein Vorschlag.
   ========================================================================== */
final class KundenFinden
{
    public const GRUENDE = [
        'ohne_kontakt' => 'Ohne E-Mail und WhatsApp', 'kunde' => 'Schon Kunde', 'kein_interesse' => 'Kein Interesse',
        'abgemeldet' => 'Abgemeldet', 'gesperrt' => 'Gesperrt', 'verloren' => 'Verloren', 'zusammengefuehrt' => 'Zusammengeführt',
    ];

    /** Ansichten über der Liste (Vorschlag 3). */
    public const ANSICHTEN = [
        'arbeit' => 'Arbeitsliste', 'kunde' => 'Schon Kunde', 'dublette' => 'Mögliche Dublette',
        'aus' => 'Ausgeschlossen', 'neu_eroeffnet' => 'Neueröffnungen', 'spaeter' => 'Später',
    ];

    /** Freie Mailanbieter: deren Domain sagt nichts über die Firma. */
    private const FREEMAIL = [
        'gmail.com', 'googlemail.com', 'libero.it', 'hotmail.com', 'hotmail.it', 'outlook.com', 'outlook.it', 'live.com', 'live.it',
        'yahoo.com', 'yahoo.it', 'alice.it', 'tiscali.it', 'virgilio.it', 'tin.it', 'icloud.com', 'me.com', 'msn.com', 'email.it',
        'fastwebnet.it', 'pec.it', 'legalmail.it', 'arubapec.it', 'aruba.it', 'gmx.de', 'gmx.net', 'web.de', 't-online.de',
        'posteo.de', 'freenet.de', 'arcor.de', 'mail.de', 'proton.me', 'protonmail.com',
    ];

    /** Tage, nach denen ein Gebiet wieder gesucht wird. */
    public const GEBIET_TAGE = 90;
    public const GEBIET_PLAN = "Provinz Agrigento\nProvinz Caltanissetta\nProvinz Trapani\nProvinz Palermo\nProvinz Ragusa\nProvinz Enna\nProvinz Catania\nProvinz Siracusa\nProvinz Messina";

    /** @var array<string,int>|null */
    private static ?array $index = null;
    private static ?array $lernen = null;

    public static function vergessen(): void { self::$index = null; self::$lernen = null; }

    private static function einstellung(string $k, string $ersatz): string
    {
        try { $w = Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], null); return $w === null ? $ersatz : (string) $w; }
        catch (Throwable $e) { return $ersatz; }
    }

    private static function setzen(string $k, string $v): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
    }

    private static function ort(?string $s): string
    {
        return trim((string) preg_replace('~[^a-z0-9]+~', ' ', strtolower(Akquise::ohneAkzente(mb_strtolower(trim((string) $s))))));
    }

    /** Nur die Ziffern einer Partita IVA / USt-IdNr. — IT: 11 Ziffern, DE: 9. */
    public static function normPiva(?string $s): ?string
    {
        $z = preg_replace('~\D~', '', (string) $s) ?? '';
        if (strlen($z) === 11 || strlen($z) === 9) { return $z; }
        if (strlen($z) > 11 && preg_match('~(\d{11})$~', $z, $m)) { return $m[1]; }
        return null;
    }

    public static function freemail(string $domain): bool { return in_array(strtolower($domain), self::FREEMAIL, true); }

    /* ================================================================== */
    /*  1 — Abgleich mit der Kundenliste                                  */
    /* ================================================================== */

    /** @return array<string,int> Schlüssel => customer_id */
    private static function index(): array
    {
        if (self::$index !== null) { return self::$index; }
        $ix = [];
        try {
            /* „Kunde“ heißt: hat bestellt, ein Projekt, ein Abo oder eine Website bei uns. Ein Interessent mit persönlichem
               Bereich (Website-Check, Akquise-Dashboard) steht auch in customers — der ist noch keiner. */
            $echt = "(EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.id) OR EXISTS (SELECT 1 FROM projects p WHERE p.customer_id = c.id)
                      OR EXISTS (SELECT 1 FROM abos a WHERE a.customer_id = c.id) OR EXISTS (SELECT 1 FROM websites w WHERE w.customer_id = c.id))";
            foreach (Db::all("SELECT c.id, c.name, c.company, c.email, c.phone, c.city, c.country, c.vat_id FROM customers c
                               WHERE c.anonym_am IS NULL AND COALESCE(c.demo, 0) = 0 AND $echt") as $c) {
                $id = (int) $c['id'];
                $land = preg_match('~^(de|deu|deutschland|germany)~i', trim((string) $c['country'])) ? 'DE' : 'IT';
                $e = Akquise::normEmail($c['email'] ?? null);
                if ($e !== null) {
                    $ix['e:' . $e] ??= $id;
                    $dom = substr((string) strrchr($e, '@'), 1);
                    if ($dom !== '' && !self::freemail($dom)) { $ix['d:' . $dom] ??= $id; }
                }
                $t = Akquise::normTelefon($c['phone'] ?? null, $land);
                if ($t !== null) { $ix['t:' . $t] ??= $id; }
                $p = self::normPiva($c['vat_id'] ?? null);
                if ($p !== null) { $ix['p:' . $p] ??= $id; }
                $o = self::ort($c['city'] ?? '');
                foreach ([$c['company'] ?? '', $c['name'] ?? ''] as $n) {
                    $nn = Akquise::normName((string) $n);
                    if (mb_strlen($nn) >= 5 && $o !== '') { $ix['n:' . $nn . '|' . $o] ??= $id; }
                }
            }
            foreach (Db::all('SELECT domain, customer_id FROM websites WHERE customer_id IS NOT NULL') as $w) {
                $d = Akquise::normDomain((string) $w['domain']);
                if ($d !== null) { $ix['d:' . $d] ??= (int) $w['customer_id']; }
            }
        } catch (Throwable $e) { /* Tabelle fehlt (frische Installation) → kein Abgleich, aber kein Fehler */ }
        try {
            foreach (Db::all("SELECT veroeffentlicht_domain AS d, customer_id FROM projects WHERE veroeffentlicht_domain IS NOT NULL AND veroeffentlicht_domain <> ''") as $w) {
                $d = Akquise::normDomain((string) $w['d']);
                if ($d !== null) { $ix['d:' . $d] ??= (int) $w['customer_id']; }
            }
        } catch (Throwable $e) { }
        return self::$index = $ix;
    }

    /**
     * Ist dieser Betrieb schon Kunde? $f: Zeile aus akq_firmen oder normalisierte Rohdaten.
     * @return array{0:int,1:string}|null [customer_id, Grund in Worten]
     */
    public static function kundeFuer(array $f): ?array
    {
        $ix = self::index();
        if ((int) ($f['customer_id'] ?? 0) > 0 && in_array((int) $f['customer_id'], $ix, true)) { return [(int) $f['customer_id'], 'verknüpft']; }
        if (!$ix) { return null; }
        $land = strtoupper((string) ($f['land'] ?? 'IT'));
        $e = Akquise::normEmail($f['email'] ?? null);
        $proben = [];
        if ($e !== null) { $proben['e:' . $e] = 'gleiche E-Mail'; }
        $p = self::normPiva($f['piva'] ?? null);
        if ($p !== null) { $proben['p:' . $p] = 'gleiche Partita IVA'; }
        $dom = (string) ($f['domain'] ?? '') !== '' ? (string) $f['domain'] : (string) (Akquise::normDomain($f['url'] ?? null) ?? '');
        if ($dom !== '' && !Akquise::istPlattform($dom)) { $proben['d:' . $dom] = 'gleiche Website'; }
        if ($e !== null) {
            $md = substr((string) strrchr($e, '@'), 1);
            if ($md !== '' && !self::freemail($md)) { $proben['d:' . $md] = 'gleiche Firmen-Domain'; }
        }
        foreach (['telefon', 'mobil', 'whatsapp'] as $sp) {
            $t = Akquise::normTelefon($f[$sp] ?? null, $land);
            if ($t !== null) { $proben['t:' . $t] = 'gleiche Telefonnummer'; }
        }
        $nn = (string) ($f['name_norm'] ?? '') !== '' ? (string) $f['name_norm'] : Akquise::normName((string) ($f['name'] ?? ''));
        $o = self::ort($f['stadt'] ?? '');
        if (mb_strlen($nn) >= 5 && $o !== '') { $proben['n:' . $nn . '|' . $o] = 'gleicher Name am selben Ort'; }
        foreach ($proben as $k => $grund) {
            if (isset($ix[$k])) { return [$ix[$k], $grund]; }
        }
        return null;
    }

    /**
     * Einen Betrieb prüfen und markieren: Schon Kunde? Mögliche Dublette?
     * Schreibt nie über Uwes Hand: ein von Hand gesetzter Status bleibt, nur „Kunde“ wird ergänzt.
     * @return string|null 'kunde'|'dublette'|null
     */
    public static function abgleichen(int $id): ?string
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { return null; }
        $jetzt = date('Y-m-d H:i:s');
        $upd = ['abgeglichen_am' => $jetzt];
        $ergebnis = null;
        $k = self::kundeFuer($f);
        if ($k !== null) {
            [$cid, $grund] = $k;
            $text = mb_substr('Schon Kunde (#' . $cid . ', ' . $grund . ')', 0, 255);
            if ((string) $f['markierung'] !== 'kunde') {
                $upd += ['markierung' => 'kunde', 'markierung_grund' => $text, 'markierung_am' => $jetzt];
                Akquise::protokoll($id, 'abgleich', $text . ' — wird nicht mehr angesprochen');
            }
            if ((int) ($f['customer_id'] ?? 0) === 0 && Db::wert('SELECT id FROM customers WHERE id = ?', [$cid], null) !== null) { $upd['customer_id'] = $cid; }
            /* „kunde“ schließt den Betrieb überall aus (Folgen, Partner-Listen, Signale, Priorität „nie“) —
               bewusst NICHT „bestandskunde“: das ist eine Rechtsgrundlage für Werbung und bleibt Uwes Haken. */
            if ((string) $f['kontakt_status'] !== 'kunde') { $upd['kontakt_status'] = 'kunde'; }
            $ergebnis = 'kunde';
        } elseif ((string) $f['markierung'] !== 'geprueft') {   // „kein Doppel“ von Hand bleibt
            $d = self::dublettenVerdacht($f);
            if ($d !== null) {
                [$von, $prozent, $grund] = $d;
                if ((int) ($f['dublette_von'] ?? 0) !== $von || (int) ($f['dublette_prozent'] ?? 0) !== $prozent) {
                    $upd += ['markierung' => 'dublette', 'markierung_grund' => mb_substr('Vermutlich derselbe Betrieb wie #' . $von . ' (' . $grund . ', ' . $prozent . ' %)', 0, 255),
                             'markierung_am' => $jetzt, 'dublette_von' => $von, 'dublette_prozent' => $prozent];
                }
                $ergebnis = 'dublette';
            } elseif ((string) $f['markierung'] === 'dublette') {
                $upd += ['markierung' => null, 'markierung_grund' => null, 'dublette_von' => null, 'dublette_prozent' => null];
            }
        }
        Db::update('akq_firmen', $id, $upd);
        return $ergebnis;
    }

    /* ================================================================== */
    /*  4 — Dubletten-Verdacht und Zusammenführen                         */
    /* ================================================================== */

    /**
     * Vermutlich derselbe Betrieb unter anderem Eintrag? Der ältere Eintrag bleibt „das Original“.
     * @return array{0:int,1:int,2:string}|null [id des Originals, Prozent, Grund]
     */
    public static function dublettenVerdacht(array $f): ?array
    {
        $id = (int) $f['id'];
        $bed = []; $args = [$id];
        $tel = (string) ($f['telefon'] ?? '');
        if ($tel !== '') { $bed[] = 'telefon = ?'; $args[] = $tel; }
        $mail = (string) ($f['email'] ?? '');
        if ($mail !== '') { $bed[] = 'email = ?'; $args[] = $mail; }
        $piva = (string) ($f['piva'] ?? '');
        if ($piva !== '') { $bed[] = 'piva = ?'; $args[] = $piva; }
        $nn = (string) ($f['name_norm'] ?? '');
        if (mb_strlen($nn) >= 4 && ((string) ($f['stadt'] ?? '') !== '' || (string) ($f['plz'] ?? '') !== '')) {
            $bed[] = '(LEFT(name_norm, 4) = ? AND (stadt = ? OR plz = ?))';
            array_push($args, mb_substr($nn, 0, 4), (string) ($f['stadt'] ?? ''), (string) ($f['plz'] ?? ''));
        }
        if (!$bed) { return null; }
        $best = null;
        foreach (Db::all('SELECT id, name_norm, telefon, email, piva, filiale_von FROM akq_firmen WHERE id < ? AND (' . implode(' OR ', $bed) . ') ORDER BY id LIMIT 40', $args) as $z) {
            /* Filialen sind kein Doppel. */
            if ((int) ($z['filiale_von'] ?? 0) === $id || (int) ($f['filiale_von'] ?? 0) === (int) $z['id']) { continue; }
            $proz = self::aehnlich($nn, (string) $z['name_norm']);
            $kand = match (true) {
                $piva !== '' && $piva === (string) $z['piva'] => [99, 'gleiche Partita IVA'],
                $mail !== '' && $mail === (string) $z['email'] => [97, 'gleiche E-Mail'],
                $tel !== '' && $tel === (string) $z['telefon'] && $proz >= 60 => [95, 'gleiche Telefonnummer bei ähnlichem Namen'],
                $proz >= 85 => [$proz, 'fast gleicher Name am selben Ort'],
                default => null,
            };
            if ($kand !== null && ($best === null || $kand[0] > $best[1])) { $best = [(int) $z['id'], $kand[0], $kand[1]]; }
        }
        return $best;
    }

    /** Allgemeine Wörter zählen beim Namensvergleich nicht — „Bar Rossi“ und „Bar Bianchi“ sind zwei Betriebe. */
    private const ALLGEMEIN = ['bar', 'caffe', 'cafe', 'ristorante', 'trattoria', 'pizzeria', 'osteria', 'hotel', 'albergo', 'b&b', 'bnb', 'panificio',
        'pasticceria', 'gelateria', 'macelleria', 'parrucchiere', 'salone', 'studio', 'agenzia', 'farmacia', 'officina', 'autofficina', 'negozio',
        'bistro', 'restaurant', 'gasthaus', 'pension', 'friseur', 'salon', 'baeckerei', 'metzgerei', 'praxis', 'kanzlei', 'werkstatt', 'laden',
        'della', 'delle', 'dello', 'degli', 'dei', 'del', 'und', 'the', 'von', 'zum', 'zur'];

    /** Namensähnlichkeit 0–100: das Bessere aus Zeichenabstand und „alle eigenen Wörter des kürzeren Namens kommen im längeren vor“. */
    public static function aehnlich(string $a, string $b): int
    {
        $a = trim($a); $b = trim($b);
        if ($a === '' || $b === '') { return 0; }
        if ($a === $b) { return 100; }
        $max = max(strlen($a), strlen($b));
        $lev = $max <= 255 ? 1 - levenshtein($a, $b) / $max : 0.0;
        $w = static fn(string $s): array => array_values(array_unique(array_filter(explode(' ', $s), static fn($x) => strlen($x) >= 3 && !in_array($x, self::ALLGEMEIN, true))));
        $wa = $w($a); $wb = $w($b);
        $teil = 0.0;
        if ($wa && $wb) {
            [$kurz, $lang] = count($wa) <= count($wb) ? [$wa, $wb] : [$wb, $wa];
            $teil = count(array_intersect($kurz, $lang)) / count($kurz);
            if (count($kurz) === 1 && count($lang) > 2) { $teil *= 0.8; }   // ein einziges gemeinsames Wort bei langem Namen ist schwach
        }
        return (int) round(100 * max($lev, $teil));
    }

    /**
     * Zwei Einträge zu einem machen. $behalte bleibt, $weg geht — mit allem, was an ihm hängt.
     * Leere Felder von $behalte werden aus $weg ergänzt; Sperren gelten weiter (sicherer Weg).
     */
    public static function zusammenfuehren(int $behalte, int $weg): string
    {
        if ($behalte === $weg || $behalte <= 0 || $weg <= 0) { throw new RuntimeException('Bitte zwei verschiedene Betriebe wählen.'); }
        return Db::transaktion(static function () use ($behalte, $weg): string {
            $a = Db::one('SELECT * FROM akq_firmen WHERE id = ? FOR UPDATE', [$behalte]);
            $b = Db::one('SELECT * FROM akq_firmen WHERE id = ? FOR UPDATE', [$weg]);
            if (!$a || !$b) { throw new RuntimeException('Einen der beiden Betriebe gibt es nicht mehr.'); }
            $nie = ['id', 'kennung', 'name', 'name_norm', 'created_at', 'updated_at', 'markierung', 'markierung_grund', 'markierung_am',
                    'dublette_von', 'dublette_prozent', 'abgeglichen_am', 'prio_score', 'prio_stufe', 'prio_gruende', 'prio_am'];
            $neu = [];
            foreach ($b as $feld => $wert) {
                if (in_array($feld, $nie, true) || $wert === null || $wert === '') { continue; }
                if (!array_key_exists($feld, $a) || ($a[$feld] !== null && $a[$feld] !== '')) { continue; }
                $neu[$feld] = $wert;
            }
            if ((int) $b['gesperrt'] === 1) { $neu['gesperrt'] = 1; }
            $staerke = ['neu' => 0, 'qualifiziert' => 1, 'vorlage' => 2, 'freigegeben' => 3, 'kontaktiert' => 4, 'geantwortet' => 5, 'kunde' => 6, 'abgelehnt' => 7, 'gesperrt' => 8];
            if (($staerke[(string) $b['kontakt_status']] ?? 0) > ($staerke[(string) $a['kontakt_status']] ?? 0)) { $neu['kontakt_status'] = (string) $b['kontakt_status']; }
            /* Eindeutige Schlüssel (domain, quelle) erst beim alten Eintrag freigeben. */
            $frei = array_intersect_key($neu, ['domain' => 1, 'quelle' => 1]);
            if ($frei) { Db::update('akq_firmen', $weg, array_map(static fn() => null, $frei)); }

            $schluessel = Akquise::aussortierSchluessel($b);
            foreach (array_merge(Akquise::BEZUG, array_fill_keys(Akquise::EIGENE, 'firma_id')) as $t => $sp) {
                try {
                    Db::run("UPDATE IGNORE $t SET $sp = ? WHERE $sp = ?", [$behalte, $weg]);
                    Db::run("DELETE FROM $t WHERE $sp = ?", [$weg]);   // was übrig blieb, gibt es beim Original schon
                } catch (Throwable $e) { /* Tabelle fehlt auf dieser Installation */ }
            }
            Db::run('UPDATE akq_firmen SET filiale_von = ? WHERE filiale_von = ?', [$behalte, $weg]);
            Db::run('UPDATE akq_firmen SET dublette_von = NULL, dublette_prozent = NULL, markierung = NULL, markierung_grund = NULL WHERE dublette_von = ?', [$weg]);
            Db::run('DELETE FROM akq_firmen WHERE id = ?', [$weg]);
            $neu['markierung'] = (string) $a['markierung'] === 'dublette' ? null : $a['markierung'];
            if ((string) $a['markierung'] === 'dublette') { $neu += ['markierung_grund' => null, 'dublette_von' => null, 'dublette_prozent' => null]; }
            $neu['prio_am'] = null;   // neu rechnen
            Db::update('akq_firmen', $behalte, $neu);
            /* Der alte Eintrag soll bei der nächsten Suche nicht als „neu“ zurückkommen. */
            foreach ($schluessel as $k) {
                Db::run('INSERT IGNORE INTO akq_aussortiert (schluessel, name, grund, grund_text) VALUES (?, ?, ?, ?)',
                    [$k, mb_substr((string) $b['name'], 0, 190), 'zusammengefuehrt', 'In #' . $behalte . ' aufgegangen']);
            }
            Akquise::protokoll($behalte, 'zusammengefuehrt', 'Mit „' . mb_substr((string) $b['name'], 0, 120) . '“ (#' . $weg . ') zusammengeführt — '
                . count($neu) . ' Angaben übernommen', ['weg' => $weg, 'uebernommen' => array_keys($neu)]);
            return '„' . (string) $b['name'] . '“ ist in „' . (string) $a['name'] . '“ aufgegangen.';
        }, 3);
    }

    /* ================================================================== */
    /*  2 — Ausschluss mit Grund                                          */
    /* ================================================================== */

    /** Einen gefundenen, aber ausgeschlossenen Betrieb merken (nur für Funde, die nicht in die Liste kommen). */
    public static function merken(array $roh, string $grund, string $text = '', ?int $kundeId = null): void
    {
        if (!isset(self::GRUENDE[$grund])) { $grund = 'gesperrt'; }
        foreach (Akquise::aussortierSchluessel($roh) as $k) {
            try {
                Db::run('INSERT INTO akq_aussortiert (schluessel, name, grund, grund_text, customer_id) VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE grund = IF(grund = \'ohne_kontakt\', VALUES(grund), grund), grund_text = VALUES(grund_text), customer_id = COALESCE(customer_id, VALUES(customer_id))',
                    [$k, mb_substr((string) ($roh['name'] ?? ''), 0, 190), $grund, $text !== '' ? mb_substr($text, 0, 255) : null, $kundeId]);
            } catch (Throwable $e) { }
        }
    }

    /** @return array{zahlen:array<string,int>, zuletzt:list<array>} */
    public static function ausgeschlossen(int $n = 50): array
    {
        $z = [];
        try {
            foreach (Db::all('SELECT grund, COUNT(DISTINCT name) AS n FROM akq_aussortiert GROUP BY grund') as $r) { $z[(string) $r['grund']] = (int) $r['n']; }
            $liste = Db::all('SELECT MIN(id) AS id, name, grund, MAX(grund_text) AS grund_text, MAX(customer_id) AS customer_id, MAX(created_at) AS am
                                FROM akq_aussortiert GROUP BY name, grund ORDER BY am DESC LIMIT ' . max(1, $n));
        } catch (Throwable $e) { $liste = []; }
        /* Wer in der Liste steht, aber nicht angesprochen wird — mit Grund. */
        foreach (['kunde' => "kontakt_status = 'kunde'", 'kein_interesse' => "kontakt_status = 'abgelehnt'",
                  'gesperrt' => "(gesperrt = 1 OR kontakt_status = 'gesperrt' OR sperr_art IS NOT NULL)"] as $g => $w) {
            try { $z[$g . '_liste'] = (int) Db::wert("SELECT COUNT(*) FROM akq_firmen WHERE $w"); } catch (Throwable $e) { }
        }
        return ['zahlen' => $z, 'zuletzt' => $liste];
    }

    /* ================================================================== */
    /*  3 — Ansichten                                                     */
    /* ================================================================== */

    /** SQL-Bedingung über f = akq_firmen für eine Ansicht. '' = keine Einschränkung. */
    public static function ansichtSql(string $ansicht): string
    {
        $raus = "COALESCE(f.kontakt_status, '') NOT IN ('kunde','abgelehnt','gesperrt') AND f.sperr_art IS NULL AND COALESCE(f.markierung, '') <> 'kunde'";
        return match ($ansicht) {
            'arbeit' => $raus . " AND NOT (COALESCE(f.crm_stufe, '') = 'spaeter' AND COALESCE(f.naechster_am, '1970-01-01') > CURDATE())",   // NULL-sicher
            'kunde' => "(f.kontakt_status = 'kunde' OR f.markierung = 'kunde' OR f.bestandskunde = 1)",
            'dublette' => "f.markierung = 'dublette'",
            'aus' => "(f.kontakt_status IN ('abgelehnt','gesperrt') OR f.sperr_art IS NOT NULL OR f.gesperrt = 1)",
            'neu_eroeffnet' => $raus . ' AND f.osm_neu_am >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)',
            'spaeter' => "f.crm_stufe = 'spaeter'",
            default => '',
        };
    }

    /** Zahlen je Ansicht für die Reiter. @return array<string,int> */
    public static function ansichtZahlen(): array
    {
        $z = [];
        foreach (array_keys(self::ANSICHTEN) as $a) {
            try {
                $w = self::ansichtSql($a);
                $z[$a] = (int) Db::wert('SELECT COUNT(*) FROM akq_firmen f WHERE ' . ($w !== '' ? $w : '1=1') . ($a === 'aus' ? '' : ' AND f.gesperrt = 0'));
            } catch (Throwable $e) { $z[$a] = 0; }
        }
        return $z;
    }

    /* ================================================================== */
    /*  6 — Bereinigung in Paketen                                        */
    /* ================================================================== */

    /** Prüft die am längsten nicht geprüften Betriebe. @return array{geprueft:int, kunden:int, dubletten:int, offen:int} */
    public static function bereinigen(int $max = 400): array
    {
        $n = ['geprueft' => 0, 'kunden' => 0, 'dubletten' => 0];
        self::vergessen();
        /* Nie geprüfte zuerst, dann alles, was älter als eine Woche ist (neue Kunden kommen ja dazu). */
        $ids = array_map('intval', array_column(Db::all('SELECT id FROM akq_firmen WHERE abgeglichen_am IS NULL OR abgeglichen_am < DATE_SUB(NOW(), INTERVAL 7 DAY)
                                                          ORDER BY abgeglichen_am IS NOT NULL, abgeglichen_am, id LIMIT ' . max(1, $max)), 'id'));
        foreach ($ids as $id) {
            $r = self::abgleichen($id);
            $n['geprueft']++;
            if ($r === 'kunde') { $n['kunden']++; } elseif ($r === 'dublette') { $n['dubletten']++; }
        }
        $n['offen'] = (int) Db::wert('SELECT COUNT(*) FROM akq_firmen WHERE abgeglichen_am IS NULL');
        /* Erster vollständiger Durchgang: einmal ein Bericht an Uwe. */
        if ($n['offen'] === 0 && self::einstellung('akq_bereinigt_am', '') === '') {
            self::setzen('akq_bereinigt_am', date('Y-m-d H:i:s'));
            $k = (int) Db::wert("SELECT COUNT(*) FROM akq_firmen WHERE markierung = 'kunde'");
            $d = (int) Db::wert("SELECT COUNT(*) FROM akq_firmen WHERE markierung = 'dublette'");
            try {
                require_once __DIR__ . '/Events.php';
                Events::melden('akquise_bereinigt', 'Kunden finden: Liste bereinigt', 'info',
                    $k . ' Betriebe sind schon Kunde und werden nicht mehr angesprochen. ' . $d . ' mögliche Dubletten warten auf einen Blick.',
                    'akquise?ansicht=dublette');
            } catch (Throwable $e) { }
        }
        return $n;
    }

    /* ================================================================== */
    /*  7–12 — Zusätze zur Priorität                                      */
    /* ================================================================== */

    /** Branchenzahlen der eigenen Akquise (täglich gerechnet). */
    public static function lernen(): array
    {
        require_once __DIR__ . '/AkquisePrio.php';
        $pos = "'" . implode("','", AkquisePrio::POSITIV) . "'";
        $b = [];
        foreach (Db::all("SELECT f.branche, COUNT(DISTINCT v.firma_id) AS g FROM akq_versand v JOIN akq_firmen f ON f.id = v.firma_id
                           WHERE v.status IN ('gesendet','von_hand') AND v.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY) AND f.branche IS NOT NULL GROUP BY f.branche") as $r) {
            $b[(string) $r['branche']] = ['g' => (int) $r['g'], 'p' => 0, 'w' => 0];
        }
        try {
            foreach (Db::all("SELECT f.branche, COUNT(DISTINCT a.firma_id) AS p FROM akq_antworten a JOIN akq_firmen f ON f.id = a.firma_id
                               WHERE a.klasse IN ($pos) AND f.branche IS NOT NULL GROUP BY f.branche") as $r) {
                $b[(string) $r['branche']] ??= ['g' => 0, 'p' => 0, 'w' => 0];
                $b[(string) $r['branche']]['p'] = (int) $r['p'];
            }
        } catch (Throwable $e) { }
        foreach (Db::all("SELECT branche, COUNT(*) AS w FROM akq_firmen WHERE branche IS NOT NULL
                           AND (kontakt_status = 'kunde' OR pipeline = 'gewonnen' OR customer_id IS NOT NULL) GROUP BY branche") as $r) {
            $b[(string) $r['branche']] ??= ['g' => 0, 'p' => 0, 'w' => 0];
            $b[(string) $r['branche']]['w'] = (int) $r['w'];
        }
        $g = array_sum(array_column($b, 'g'));
        $p = array_sum(array_column($b, 'p'));
        $stand = ['branchen' => $b, 'quote' => $g > 0 ? round($p / $g, 4) : 0.0, 'am' => date('Y-m-d')];
        self::setzen('akq_lernen', json_encode($stand, JSON_UNESCAPED_UNICODE));
        self::$lernen = $stand;
        return ['branchen' => count($b), 'gesendet' => $g, 'positiv' => $p];
    }

    private static function lernStand(): array
    {
        if (self::$lernen === null) {
            $j = json_decode(self::einstellung('akq_lernen', ''), true);
            self::$lernen = is_array($j) ? $j : ['branchen' => [], 'quote' => 0.0];
        }
        return self::$lernen;
    }

    /**
     * Punkte für AkquisePrio. @return array{plus:list<array{0:int,1:string}>, minus:list<array{0:int,1:string}>}
     * $jetzt nur für die Kette (Saison).
     */
    public static function prioZusatz(array $f, ?int $jetzt = null): array
    {
        $plus = []; $minus = [];
        $jetzt ??= time();
        $neu = (string) ($f['osm_neu_am'] ?? '');
        if ($neu !== '' && strtotime($neu) >= $jetzt - 60 * 86400) { $plus[] = [12, 'Wohl neu eröffnet (seit ' . date('d.m.Y', strtotime($neu)) . ' auf OpenStreetMap)']; }
        if ((int) ($f['tourismus'] ?? 0) === 1 && (int) date('n', $jetzt) <= 3) { $plus[] = [8, 'Vor der Saison — jetzt ist die Zeit für eine neue Seite']; }
        $br = (string) ($f['branche'] ?? '');
        if ($br !== '') {
            $l = self::lernStand();
            $z = $l['branchen'][$br] ?? null;
            $name = Akquise::branchenName($br);
            if ($z) {
                if ((int) $z['w'] > 0) { $plus[] = [6, 'Wie gewonnene Kunden (' . (int) $z['w'] . '× ' . $name . ')']; }
                $q = (float) ($l['quote'] ?? 0);
                if ((int) $z['g'] >= 10 && (int) $z['p'] >= 2 && $q > 0 && $z['p'] / $z['g'] >= 2 * $q) {
                    $plus[] = [8, $name . ' antwortet überdurchschnittlich (' . (int) $z['p'] . ' von ' . (int) $z['g'] . ')'];
                } elseif ((int) $z['g'] >= 20 && (int) $z['p'] === 0) {
                    $minus[] = [5, $name . ': bisher keine Antwort (0 von ' . (int) $z['g'] . ')'];
                }
            }
        }
        $ag = trim((string) ($f['agentur'] ?? ''));
        if ($ag !== '') { $minus[] = [10, 'Website von „' . mb_substr($ag, 0, 60) . '“ betreut']; }
        return ['plus' => $plus, 'minus' => $minus];
    }

    /** Fußzeile der Website: „Realizzato da Studio X“ → „Studio X“. Keine Baukästen („Powered by WordPress“). */
    public static function agenturAusText(string $text): ?string
    {
        if (!preg_match('~\b(?:realizzat[oa]|sviluppat[oa]|progettat[oa]|design(?:ed)?|sito(?: web)?|web ?design|made|erstellt|gestaltet|umgesetzt|realisiert|entwickelt|credits?)\s*(?:da|by|von|:)\s*:?\s*([\p{Lu}0-9][\p{L}0-9&.\'\- ]{1,48})~iu', $text, $m)) { return null; }
        $n = trim(preg_replace('~\s+~u', ' ', $m[1]) ?? '', " .-'");
        if (mb_strlen($n) < 2 || preg_match('~^(wordpress|wix|jimdo|squarespace|shopify|joomla|webnode|ionos|aruba|godaddy|google|weebly|strato|elementor|divi)\b~i', $n)) { return null; }
        return mb_substr($n, 0, 120);
    }

    /* ================================================================== */
    /*  14 — Später statt löschen                                         */
    /* ================================================================== */

    public static function spaeter(int $id, int $monate = 6): string
    {
        $f = Db::one('SELECT id, name FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { throw new RuntimeException('Betrieb nicht gefunden.'); }
        $am = date('Y-m-d', strtotime('+' . max(1, min(24, $monate)) . ' months'));
        Db::update('akq_firmen', $id, ['crm_stufe' => 'spaeter', 'crm_stufe_am' => date('Y-m-d H:i:s'), 'naechster_am' => $am,
            'naechster_schritt' => 'Wieder ansprechen (war „jetzt nicht“)', 'wiedervorlage_am' => $am, 'prio_am' => null]);
        Akquise::protokoll($id, 'spaeter', 'Jetzt nicht — Wiedervorlage am ' . date('d.m.Y', strtotime($am)));
        return '„' . (string) $f['name'] . '“ kommt am ' . date('d.m.Y', strtotime($am)) . ' wieder.';
    }

    /** Fällige „Später“ zurück in die Arbeitsliste. @return int wie viele */
    public static function spaeterFaellig(): int
    {
        return Db::run("UPDATE akq_firmen SET crm_stufe = NULL, crm_stufe_am = NOW(), prio_am = NULL
                         WHERE crm_stufe = 'spaeter' AND naechster_am IS NOT NULL AND naechster_am <= CURDATE()")->rowCount();
    }

    /* ================================================================== */
    /*  15 — Tagesliste „Heute ansprechen“                                */
    /* ================================================================== */

    /** Welcher Weg passt — nur, was erlaubt ist. */
    public static function kanal(array $f, bool $partnerDa): ?string
    {
        require_once __DIR__ . '/AkquiseGate.php';
        if (trim((string) ($f['whatsapp'] ?? '')) !== '' && AkquiseGate::einwilligungDeckt($f, 'whatsapp')) { return 'whatsapp'; }
        if (trim((string) ($f['email'] ?? '')) !== '' && (AkquiseGate::einwilligungDeckt($f, 'email') || (int) ($f['email_send_allowed'] ?? 0) === 1)) { return 'email'; }
        $tel = trim((string) ($f['telefon'] ?? '')) !== '' || trim((string) ($f['mobil'] ?? '')) !== '';
        if ($tel) {
            /* Italien: nie eine Nummer im Registro delle Opposizioni — selbst anrufen nur mit frischer Prüfung
               (höchstens 15 Tage alt); sonst über die Partner-Anrufliste, die selbst prüft. */
            $rpoFrisch = !empty($f['rpo_frei_am']) && strtotime((string) $f['rpo_frei_am']) >= time() - 15 * 86400;
            if ((string) $f['land'] === 'IT' && !$rpoFrisch) { return $partnerDa ? 'partner' : null; }
            return $partnerDa ? 'partner' : 'telefon';
        }
        return trim((string) ($f['email'] ?? '')) !== '' ? 'email' : null;
    }

    public const KANAL_WORT = ['whatsapp' => 'WhatsApp (Einwilligung liegt vor)', 'email' => 'E-Mail', 'telefon' => 'Anruf', 'partner' => 'An einen Partner geben'];

    /** Die zehn besten für heute rechnen. @return array{angelegt:int} */
    public static function tageslisteRechnen(int $anzahl = 10): array
    {
        require_once __DIR__ . '/AkquisePrio.php';
        $heute = date('Y-m-d');
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_tagesliste WHERE datum = ?', [$heute]) > 0) { return ['angelegt' => 0, 'schon' => true]; }
        $partnerDa = (int) Db::wert("SELECT COUNT(*) FROM partner WHERE status = 'aktiv'", [], 0) > 0;
        $zeilen = Db::all("SELECT f.* FROM akq_firmen f
                            WHERE f.gesperrt = 0 AND f.prio_stufe IN ('jetzt','gut') AND " . self::ansichtSql('arbeit') . "
                              AND f.kontakt_status IN ('neu','qualifiziert','vorlage','freigegeben') AND COALESCE(f.markierung, '') = ''
                              AND NOT EXISTS (SELECT 1 FROM partner_reservierungen r WHERE r.firma_id = f.id AND r.bis >= CURDATE())
                              AND NOT EXISTS (SELECT 1 FROM akq_tagesliste t WHERE t.firma_id = f.id AND t.datum >= DATE_SUB(CURDATE(), INTERVAL 14 DAY))
                            ORDER BY f.prio_score DESC, f.osm_neu_am DESC, f.id LIMIT 80");
        $rang = 0;
        foreach ($zeilen as $f) {
            $k = self::kanal($f, $partnerDa);
            if ($k === null) { continue; }
            $grund = AkquisePrio::warum($f);
            Db::run('INSERT IGNORE INTO akq_tagesliste (datum, rang, firma_id, kanal, grund) VALUES (?, ?, ?, ?, ?)',
                [$heute, ++$rang, (int) $f['id'], $k, mb_substr($grund !== '' ? $grund : 'Hohe Priorität', 0, 255)]);
            if ($rang >= $anzahl) { break; }
        }
        Db::run('DELETE FROM akq_tagesliste WHERE datum < DATE_SUB(CURDATE(), INTERVAL 60 DAY)');
        return ['angelegt' => $rang];
    }

    /** @return list<array<string,mixed>> */
    public static function tagesliste(): array
    {
        try {
            return Db::all('SELECT t.*, f.name, f.stadt, f.branche, f.prio_stufe, f.prio_score FROM akq_tagesliste t JOIN akq_firmen f ON f.id = t.firma_id
                             WHERE t.datum = CURDATE() ORDER BY t.erledigt, t.rang');
        } catch (Throwable $e) { return []; }
    }

    public static function tageslisteErledigt(int $id): void
    {
        Db::run('UPDATE akq_tagesliste SET erledigt = 1 WHERE id = ?', [$id]);
    }

    /* ================================================================== */
    /*  13 — Gebiete                                                      */
    /* ================================================================== */

    /** @return list<string> */
    public static function plan(): array
    {
        $roh = self::einstellung('akq_gebiete_plan', self::GEBIET_PLAN);
        return array_values(array_filter(array_map(static fn($z) => mb_substr(trim($z), 0, 120), preg_split('~\R~u', $roh) ?: [])));
    }

    public static function automatik(): bool { return self::einstellung('akq_gebiete_auto', '1') === '1'; }

    public static function planSpeichern(string $text, bool $auto): void
    {
        $z = array_slice(array_values(array_filter(array_map(static fn($x) => mb_substr(trim($x), 0, 120), preg_split('~\R~u', $text) ?: []))), 0, 60);
        self::setzen('akq_gebiete_plan', implode("\n", $z));
        self::setzen('akq_gebiete_auto', $auto ? '1' : '0');
    }

    /** Stand je Plan-Gebiet und Abdeckung je Ort. */
    public static function gebiete(): array
    {
        $plan = [];
        foreach (self::plan() as $g) {
            $l = Db::one("SELECT status, beendet_am, gefunden, neu FROM akq_laeufe WHERE gebiet = ? ORDER BY id DESC LIMIT 1", [$g]);
            $fertig = Db::wert("SELECT MAX(beendet_am) FROM akq_laeufe WHERE gebiet = ? AND status = 'fertig'", [$g], null);
            $plan[] = ['gebiet' => $g, 'status' => $l['status'] ?? null, 'fertig_am' => $fertig, 'gefunden' => (int) ($l['gefunden'] ?? 0), 'neu' => (int) ($l['neu'] ?? 0),
                       'faellig' => $fertig === null || strtotime((string) $fertig) < time() - self::GEBIET_TAGE * 86400];
        }
        $orte = Db::all("SELECT land, COALESCE(kreis, '') AS kreis, COALESCE(stadt, '—') AS stadt, COUNT(*) AS betriebe,
                                SUM(url IS NULL OR url = '') AS ohne_web,
                                SUM(kontakt_status IN ('kontaktiert','geantwortet')) AS angesprochen,
                                SUM(kontakt_status = 'kunde') AS kunden,
                                SUM(prio_stufe IN ('jetzt','gut')) AS chancen,
                                MAX(recherchiert_am) AS zuletzt
                           FROM akq_firmen WHERE gesperrt = 0 GROUP BY land, kreis, stadt ORDER BY betriebe DESC LIMIT 200");
        return ['plan' => $plan, 'orte' => $orte, 'auto' => self::automatik()];
    }

    /**
     * Das nächste fällige Gebiet als Suchauftrag anlegen — nur, wenn die Suche eingeschaltet ist und nichts wartet.
     * @return array{angelegt:?string}
     */
    public static function naechstesGebiet(): array
    {
        if (!self::automatik()) { return ['angelegt' => null, 'grund' => 'aus']; }
        require_once __DIR__ . '/AkquiseGate.php';
        if (!AkquiseGate::schalter('recherche')) { return ['angelegt' => null, 'grund' => 'suche_aus']; }
        if ((int) Db::wert("SELECT COUNT(*) FROM akq_laeufe WHERE status IN ('wartet','laeuft')") > 0) { return ['angelegt' => null, 'grund' => 'wartet']; }
        $faellig = array_values(array_filter(self::gebiete()['plan'], static fn($g) => $g['faellig'] && $g['status'] !== 'fehler'));
        if (!$faellig) { return ['angelegt' => null, 'grund' => 'alles_aktuell']; }
        usort($faellig, static fn($a, $b) => strcmp((string) $a['fertig_am'], (string) $b['fertig_am']));   // nie gesucht (null) zuerst
        $g = $faellig[0]['gebiet'];
        $land = preg_match('~(landkreis|kreis|bundesland|deutschland)~i', $g) ? 'DE' : 'IT';
        $id = Db::insert('akq_laeufe', ['land' => $land, 'ebene' => 'auto', 'gebiet' => $g, 'quelle' => 'osm', 'angelegt_von' => 'Gebietsplan']);
        Akquise::protokoll(null, 'lauf', 'Gebietsplan: nächstes Gebiet „' . $g . '“ angelegt', [], $id);
        return ['angelegt' => $g];
    }
}
