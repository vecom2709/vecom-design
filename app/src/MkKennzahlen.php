<?php
declare(strict_types=1);

/* ==========================================================================
   MkKennzahlen.php — Marketing-Überblick (Growth Engine, Phase 2).
   30.09.2026, Uwe: „Alles ja, starte mit Phase 2“.

   WAS DIESE KLASSE IST

   Die erste Seite der neuen Tür „Marketing“. Sie beantwortet die Leitfrage
   des ganzen Bereichs — welche Marketingaktivität bringt Besucher, und wer
   davon wird Kunde — AUSSCHLIESSLICH aus Daten, die es schon gibt:

     besuche.csv        Aufrufe, Herkunft, Seite, utm_source (z.php)
     demo.csv           welcher Knopf zum Kontakt führte (d.php)
     zugaenge, anfragen, akq_checks, akq_termine, bedarf   Leads
     bedarf, questionnaires, angebote, orders, payments    Weg zum Kunden
     partner_provisionen   Umsatz über Partner
     ausgaben (Kategorie „werbung“)   Marketingkosten
     web_berichte, akq_audits, akq_einwilligungen          Website-Check, Akquise

   Nichts wird neu erfasst, nichts gespeichert, nichts geschätzt. Seit Phase 3
   kommen Kampagnen und Werbemittel (Kampagnenlinks /k/…, MkKampagne) dazu:
   beste Kampagne und bestes Werbemittel nach Leads, Umsatz über Kampagnen,
   Kampagnenkosten ohne Beleg in den Marketingkosten.

   WARUM „AUFRUFE“ UND NICHT „BESUCHER“

   z.php zählt ohne Keks und ohne IP. Jede geöffnete Seite ist eine Zeile.
   Wer drei Seiten liest, steht dreimal drin. Das Wort „Besucher“ wäre eine
   Behauptung, die die Datei nicht hergibt.

   WARUM „MEISTE AUFRUFE“ STATT „BESTE“ BEI QUELLEN

   Heute weiß nur die Partner-Spur, welcher Besuch später Kunde wurde. Für
   Google, Instagram & Co. gibt es die Verbindung Aufruf → Lead noch nicht.
   Die Karte sagt deshalb, was gemessen ist: welche Quelle die meisten
   Aufrufe brachte — nicht, welche die meisten Kunden.

   WARUM DIE WURZEL EIN PARAMETER IST

   Wie bei Statistik: Die Prüfkette liest ihre eigenen Probedateien.
   ========================================================================== */

final class MkKennzahlen
{
    public const ZEITRAEUME = ['heute' => 'Heute', 'gestern' => 'Gestern', '7' => '7 Tage', '30' => '30 Tage',
        'monat' => 'Dieser Monat', 'vormonat' => 'Letzter Monat', 'quartal' => 'Quartal', 'jahr' => 'Jahr', 'frei' => 'Zeitraum'];

    /** Unter so vielen Fällen ist ein „Bester“ Zufall — die Karte sagt es dazu. */
    public const WENIG = 20;

    /** Wie ein Lead hereinkam (erste Spur seiner E-Mail-Adresse). */
    public const WEGE = [
        'zugang_seite'    => 'E-Mail auf der Website',
        'zugang_vorschau' => '30-Sekunden-Vorschau',
        'zugang_akquise'  => 'Akquise mit Einwilligung',
        'anfrage'         => 'Kontaktformular',
        'check'           => 'Website-Check',
        'termin'          => 'Terminbuchung',
        'rechner'         => 'Preisrechner (Telefon, Telegram)',
    ];

    /** Knöpfe aus demo.csv, die zu einem Kontakt führen. */
    public const CTA = [
        'zugang-hero'       => 'E-Mail oben im Aufmacher',
        'zugang-kontakt'    => 'E-Mail unten im Kontakt',
        'zugang-vorschau'   => 'E-Mail nach der 30-Sekunden-Vorschau',
        'ka-gesendet'       => 'Anfrage aus dem Ablauf',
        'rueckruf-gesendet' => 'Rückruf-Wunsch',
        'whatsapp'          => 'WhatsApp-Knopf',
        'anruf'             => 'Anruf-Knopf',
    ];

    public const SOZIAL = ['Instagram', 'Facebook', 'TikTok', 'LinkedIn', 'YouTube', 'X', 'Pinterest', 'Threads', 'Telegram', 'WhatsApp'];

    /** Eine Abfrage, die scheitert (Tabelle fehlt noch), macht eine Kachel leer, nicht die Seite kaputt. */
    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /* ------------------------------------------------------------------ */
    /* Zeitraum                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0:string,1:string,2:string,3:string,4:string} von, bis, Schlüssel, Vergleich-von, Vergleich-bis
     */
    public static function zeitraum(string $k, string $von = '', string $bis = '', ?string $heute = null): array
    {
        $heute ??= date('Y-m-d');
        $t = strtotime($heute);
        [$v, $b, $k] = match ($k) {
            'heute'    => [$heute, $heute, 'heute'],
            'gestern'  => [date('Y-m-d', strtotime('-1 day', $t)), date('Y-m-d', strtotime('-1 day', $t)), 'gestern'],
            '7'        => [date('Y-m-d', strtotime('-6 days', $t)), $heute, '7'],
            'monat'    => [date('Y-m-01', $t), $heute, 'monat'],
            'vormonat' => [date('Y-m-01', strtotime('first day of last month', $t)), date('Y-m-t', strtotime('last day of last month', $t)), 'vormonat'],
            'quartal'  => [date('Y', $t) . '-' . sprintf('%02d', intdiv((int) date('n', $t) - 1, 3) * 3 + 1) . '-01', $heute, 'quartal'],
            'jahr'     => [date('Y-01-01', $t), $heute, 'jahr'],
            'frei'     => (static function () use ($von, $bis, $heute): array {
                $ok = static fn(string $d): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
                $a = $ok($von) ? $von : date('Y-m-d', strtotime("$heute -29 days"));
                $e = $ok($bis) ? $bis : $heute;
                if ($a > $e) { [$a, $e] = [$e, $a]; }
                /* Mehr als drei Jahre am Stück liest niemand — und die CSV hält ohnehin nur gut ein Jahr. */
                if ((strtotime($e) - strtotime($a)) > 1100 * 86400) { $a = date('Y-m-d', strtotime("$e -1100 days")); }
                return [$a, $e, 'frei'];
            })(),
            default    => [date('Y-m-d', strtotime('-29 days', $t)), $heute, '30'],
        };
        $tage = (int) round((strtotime($b) - strtotime($v)) / 86400) + 1;
        $vb = date('Y-m-d', strtotime("$v -1 day"));
        $vv = date('Y-m-d', strtotime("$vb -" . ($tage - 1) . ' days'));
        return [$v, $b, $k, $vv, $vb];
    }

    /* ------------------------------------------------------------------ */
    /* Aufrufe aus besuche.csv                                             */
    /* ------------------------------------------------------------------ */

    /** Wer hat verwiesen? Name und Art aus der Herkunfts-Domain. */
    public static function plattform(string $host): array
    {
        $h = strtolower(trim($host));
        if ($h === '') { return ['Direkt / unbekannt', 'direkt']; }
        $ist = static fn(string $muster): bool => preg_match($muster, $h) === 1;
        return match (true) {
            $ist('~(^|\.)(chatgpt\.com|chat\.openai\.com|perplexity\.ai|gemini\.google\.com|copilot\.microsoft\.com|claude\.ai)$~') => ['KI-Assistent', 'ki'],
            $ist('~^com\.google\.android\.gm$|(^|\.)(mail\.google\.com|outlook\.(live|office)\.com|webmail\.|mail\.)~') => ['E-Mail', 'email'],
            $ist('~(^|\.)(instagram\.com)$~') => ['Instagram', 'sozial'],
            $ist('~(^|\.)(facebook\.com|fb\.me|fb\.com)$~') => ['Facebook', 'sozial'],
            $ist('~(^|\.)tiktok\.com$~') => ['TikTok', 'sozial'],
            $ist('~(^|\.)(linkedin\.com|lnkd\.in)$~') => ['LinkedIn', 'sozial'],
            $ist('~(^|\.)(youtube\.com|youtu\.be)$~') => ['YouTube', 'sozial'],
            $ist('~(^|\.)(t\.co|x\.com|twitter\.com)$~') => ['X', 'sozial'],
            $ist('~(^|\.)(pinterest\.[a-z.]+|pin\.it)$~') => ['Pinterest', 'sozial'],
            $ist('~(^|\.)threads\.(net|com)$~') => ['Threads', 'sozial'],
            $ist('~(^|\.)(t\.me|telegram\.org|telegram\.me)$~') => ['Telegram', 'sozial'],
            $ist('~(^|\.)(wa\.me|whatsapp\.com)$~') => ['WhatsApp', 'sozial'],
            $ist('~(^|\.)google\.[a-z.]+$|^com\.google\.android\.googlequicksearchbox$~') => ['Google', 'suche'],
            $ist('~(^|\.)(bing\.com|duckduckgo\.com|ecosia\.org|yahoo\.[a-z.]+|qwant\.com|startpage\.com|search\.brave\.com)$~') => ['andere Suchmaschine', 'suche'],
            default => [$h, 'verweis'],
        };
    }

    /** Ist die Seite eine Branchen- oder Landeseite? */
    public static function istLandingpage(string $pfad): bool
    {
        return preg_match('~^/(?:siti-web|sito-o-booking|(?:de|en)/websites?-|de/eigene-website|en/own-website)~', $pfad) === 1;
    }

    /**
     * Ein Durchgang durch besuche.csv. Für [von, bis] alle Aufschlüsselungen,
     * dazu Tagessummen ab dem frühesten gebrauchten Tag (Vergleich, 7/30 Tage).
     */
    public static function aufrufe(array $z, ?string $wurzel = null, ?string $heute = null): array
    {
        [$von, $bis, , $vv, $vb] = $z;
        $heute ??= date('Y-m-d');
        $ab = min($vv, date('Y-m-d', strtotime("$heute -29 days")));
        $tage = []; $quellen = []; $plattformen = []; $arten = []; $seiten = []; $landing = []; $kampagnen = []; $geraete = ['Rechner' => 0, 'Handy' => 0];
        $summe = 0; $mitKampagne = 0;
        $datei = ($wurzel ?? dirname(__DIR__, 2)) . '/besuche.csv';
        $da = is_readable($datei);
        if ($da && ($fh = fopen($datei, 'r'))) {
            while (($zeile = fgets($fh)) !== false) {
                $t = explode("\t", rtrim($zeile, "\r\n"));
                if (count($t) < 4 || strlen($t[0]) !== 10 || $t[0] < $ab || $t[0] > $heute) { continue; }
                $tage[$t[0]] = ($tage[$t[0]] ?? 0) + 1;
                if ($t[0] < $von || $t[0] > $bis) { continue; }
                $summe++;
                [$name, $art] = self::plattform((string) $t[2]);
                $plattformen[$name] = ($plattformen[$name] ?? 0) + 1;
                $arten[$art] = ($arten[$art] ?? 0) + 1;
                if ($t[2] !== '') { $quellen[$t[2]] = ($quellen[$t[2]] ?? 0) + 1; }
                if (isset($geraete[$t[3]])) { $geraete[$t[3]]++; }
                $seite = (string) ($t[4] ?? '');
                if ($seite !== '') {
                    $seiten[$seite] = ($seiten[$seite] ?? 0) + 1;
                    if (self::istLandingpage($seite)) { $landing[$seite] = ($landing[$seite] ?? 0) + 1; }
                }
                $k = (string) ($t[5] ?? '');
                if ($k !== '') { $kampagnen[$k] = ($kampagnen[$k] ?? 0) + 1; $mitKampagne++; }
            }
            fclose($fh);
        }
        foreach ([&$quellen, &$plattformen, &$arten, &$seiten, &$landing, &$kampagnen] as &$liste) { arsort($liste); }
        unset($liste);
        $spanne = static function (string $a, string $b) use ($tage): int {
            $n = 0;
            foreach ($tage as $d => $c) { if ($d >= $a && $d <= $b) { $n += $c; } }
            return $n;
        };
        return [
            'da' => $da, 'summe' => $summe, 'vorher' => $spanne($vv, $vb), 'mit_kampagne' => $mitKampagne,
            'heute' => $tage[$heute] ?? 0,
            'tage7' => $spanne(date('Y-m-d', strtotime("$heute -6 days")), $heute),
            'tage30' => $spanne(date('Y-m-d', strtotime("$heute -29 days")), $heute),
            'tage' => $tage, 'quellen' => $quellen, 'plattformen' => $plattformen, 'arten' => $arten,
            'seiten' => $seiten, 'landing' => $landing, 'kampagnen' => $kampagnen, 'geraete' => $geraete,
        ];
    }

    /** Welcher Knopf führte zum Kontakt? (demo.csv) */
    public static function knoepfe(string $von, string $bis, ?string $wurzel = null): array
    {
        $zahl = array_fill_keys(array_keys(self::CTA), 0);
        $datei = ($wurzel ?? dirname(__DIR__, 2)) . '/demo.csv';
        if (is_readable($datei) && ($fh = fopen($datei, 'r'))) {
            while (($zeile = fgets($fh)) !== false) {
                $t = explode("\t", rtrim($zeile, "\r\n"));
                if (count($t) < 3 || $t[0] < $von || $t[0] > $bis || !isset($zahl[$t[2]])) { continue; }
                $zahl[$t[2]]++;
            }
            fclose($fh);
        }
        arsort($zahl);
        return array_filter($zahl);
    }

    /* ------------------------------------------------------------------ */
    /* Leads, Weg zum Kunden, Geld                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Alle Stellen, an denen jemand freiwillig eine E-Mail-Adresse
     * hinterlassen hat — bis zum Ende des Zeitraums. Ein Lead ist „neu“,
     * wenn seine Adresse zum ersten Mal im Zeitraum auftaucht; wer im März
     * schon eine Anfrage schrieb und im Mai den Rechner ausfüllt, ist im Mai
     * kein neuer Lead.
     */
    private static function leadSpuren(string $bisEnde): array
    {
        $sql = [
            "SELECT LOWER(TRIM(email)) AS e, created_at AS t, CONCAT('zugang_', quelle) AS weg FROM zugaenge WHERE email <> '' AND created_at <= ?",
            "SELECT LOWER(TRIM(email)) AS e, created_at AS t, 'anfrage' AS weg FROM anfragen WHERE demo = 0 AND email <> '' AND created_at <= ?",
            "SELECT LOWER(TRIM(email)) AS e, created_at AS t, 'check' AS weg FROM akq_checks WHERE email IS NOT NULL AND email <> '' AND created_at <= ?",
            "SELECT LOWER(TRIM(email)) AS e, created_at AS t, 'termin' AS weg FROM akq_termine WHERE email IS NOT NULL AND email <> '' AND created_at <= ?",
            "SELECT LOWER(TRIM(email)) AS e, created_at AS t, 'rechner' AS weg FROM bedarf WHERE demo = 0 AND email <> '' AND created_at <= ?",
        ];
        $zeilen = [];
        foreach ($sql as $q) {
            foreach ((array) self::still(static fn() => Db::all($q, [$bisEnde]), []) as $r) { $zeilen[] = $r; }
        }
        return $zeilen;
    }

    /** Adressen, die weiter gegangen sind als bis zur E-Mail. */
    private static function qualifiziert(): array
    {
        $q = [
            "SELECT LOWER(TRIM(email)) AS e FROM bedarf WHERE demo = 0 AND abgesendet_am IS NOT NULL AND email <> ''",
            "SELECT LOWER(TRIM(c.email)) FROM bedarf b JOIN customers c ON c.id = b.customer_id WHERE b.demo = 0 AND b.abgesendet_am IS NOT NULL",
            "SELECT LOWER(TRIM(c.email)) FROM questionnaires q JOIN customers c ON c.id = q.customer_id WHERE q.demo = 0 AND q.submitted_at IS NOT NULL",
            "SELECT LOWER(TRIM(email)) FROM akq_termine WHERE status <> 'abgesagt' AND email IS NOT NULL AND email <> ''",
            "SELECT LOWER(TRIM(email)) FROM anfragen WHERE demo = 0 AND email <> ''",
            "SELECT LOWER(TRIM(c.email)) FROM angebote a JOIN customers c ON c.id = a.customer_id WHERE a.demo = 0 AND a.gesendet_am IS NOT NULL",
            "SELECT LOWER(TRIM(c.email)) FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.demo = 0",
        ];
        $set = [];
        foreach ($q as $s) {
            foreach ((array) self::still(static fn() => Db::all($s), []) as $r) { $set[(string) reset($r)] = true; }
        }
        return $set;
    }

    /** Leads im Zeitraum: neu, qualifiziert, nach Weg; dazu der Vergleichszeitraum. */
    public static function leads(array $z): array
    {
        [$von, $bis, , $vv, $vb] = $z;
        $erst = [];
        foreach (self::leadSpuren($bis . ' 23:59:59') as $r) {
            $e = (string) $r['e'];
            if ($e === '' || !str_contains($e, '@')) { continue; }
            if (!isset($erst[$e]) || (string) $r['t'] < $erst[$e]['t']) { $erst[$e] = ['t' => (string) $r['t'], 'weg' => (string) $r['weg']]; }
        }
        $qual = self::qualifiziert();
        $neu = 0; $vorher = 0; $gut = 0; $wege = []; $wegeGut = []; $jeTag = [];
        foreach ($erst as $e => $x) {
            $tag = substr($x['t'], 0, 10);
            if ($tag >= $vv && $tag <= $vb) { $vorher++; }
            if ($tag < $von || $tag > $bis) { continue; }
            $neu++;
            $jeTag[$tag] = ($jeTag[$tag] ?? 0) + 1;
            $w = self::WEGE[$x['weg']] ?? 'Sonstiges';
            $wege[$w] = ($wege[$w] ?? 0) + 1;
            if (isset($qual[$e])) { $gut++; $wegeGut[$w] = ($wegeGut[$w] ?? 0) + 1; }
        }
        arsort($wege); arsort($wegeGut);
        return ['neu' => $neu, 'vorher' => $vorher, 'qualifiziert' => $gut, 'wege' => $wege, 'wege_gut' => $wegeGut, 'tage' => $jeTag];
    }

    /** Schritte auf dem Weg zum Kunden, jeweils: was im Zeitraum passiert ist. */
    public static function schritte(string $von, string $bis): array
    {
        $a = [$von . ' 00:00:00', $bis . ' 23:59:59'];
        $n = static fn(string $sql): int => (int) self::still(static fn() => Db::wert($sql, $a, 0), 0);
        return [
            'checks'          => $n("SELECT COUNT(*) FROM web_berichte WHERE quelle IN ('analisi','check') AND created_at BETWEEN ? AND ?"),
            'checks_kontakt'  => $n("SELECT COUNT(*) FROM akq_checks WHERE created_at BETWEEN ? AND ?"),
            'zugaenge'        => $n("SELECT COUNT(*) FROM zugaenge WHERE created_at BETWEEN ? AND ?"),
            'rechner_begonnen'=> $n("SELECT COUNT(*) FROM bedarf WHERE demo = 0 AND antworten IS NOT NULL AND antworten NOT IN ('', '[]', '{}') AND created_at BETWEEN ? AND ?"),
            'rechner_fertig'  => $n("SELECT COUNT(*) FROM bedarf WHERE demo = 0 AND abgesendet_am BETWEEN ? AND ?"),
            'fragebogen_neu'  => $n("SELECT COUNT(*) FROM questionnaires WHERE demo = 0 AND created_at BETWEEN ? AND ?"),
            'fragebogen_fertig'=> $n("SELECT COUNT(*) FROM questionnaires WHERE demo = 0 AND submitted_at BETWEEN ? AND ?"),
            'anfragen'        => $n("SELECT COUNT(*) FROM anfragen WHERE demo = 0 AND created_at BETWEEN ? AND ?"),
            'termine'         => $n("SELECT COUNT(*) FROM akq_termine WHERE status <> 'abgesagt' AND created_at BETWEEN ? AND ?"),
            'angebote'        => $n("SELECT COUNT(*) FROM angebote WHERE demo = 0 AND gesendet_am BETWEEN ? AND ?"),
            'auftraege'       => $n("SELECT COUNT(*) FROM orders WHERE demo = 0 AND status <> 'storniert' AND ordered_at BETWEEN ? AND ?"),
        ];
    }

    /** Umsatz, neue Kunden, Auftragswert, Partner, Kosten. */
    public static function geld(array $z): array
    {
        [$von, $bis, , $vv, $vb] = $z;
        $a = [$von . ' 00:00:00', $bis . ' 23:59:59'];
        $av = [$vv . ' 00:00:00', $vb . ' 23:59:59'];
        $w = static fn(string $sql, array $p): int => (int) self::still(static fn() => Db::wert($sql, $p, 0), 0);
        $umsatz = "SELECT COALESCE(SUM(p.amount_cents),0) FROM payments p WHERE p.status = 'bezahlt' AND p.demo = 0 AND p.paid_at BETWEEN ? AND ?";
        $neuKunden = "SELECT COUNT(*) FROM (SELECT o.customer_id, MIN(p.paid_at) AS erst FROM payments p JOIN orders o ON o.id = p.order_id
                       WHERE p.status = 'bezahlt' AND p.demo = 0 GROUP BY o.customer_id) k WHERE k.erst BETWEEN ? AND ?";
        $werbung = "SELECT COALESCE(SUM(CASE WHEN netto_cents > 0 THEN netto_cents ELSE brutto_cents END),0) FROM ausgaben WHERE kategorie = 'werbung' AND datum BETWEEN ? AND ?";
        /* Kampagnenkosten ohne Beleg kommen dazu; mit Beleg stecken sie schon in den Ausgaben (Phase 3). */
        $kampKosten = "SELECT COALESCE(SUM(betrag_cents),0) FROM mk_kosten WHERE ausgabe_id IS NULL AND datum BETWEEN ? AND ?";
        $auftrag = (array) self::still(static fn() => Db::one("SELECT COUNT(*) AS n, COALESCE(AVG(price_cents),0) AS schnitt FROM orders
                                   WHERE demo = 0 AND status <> 'storniert' AND ordered_at BETWEEN ? AND ?", $a), []);
        return [
            'umsatz' => $w($umsatz, $a), 'umsatz_vorher' => $w($umsatz, $av),
            'zahlungen' => $w("SELECT COUNT(*) FROM payments p WHERE p.status = 'bezahlt' AND p.demo = 0 AND p.paid_at BETWEEN ? AND ?", $a),
            'kunden' => $w($neuKunden, $a), 'kunden_vorher' => $w($neuKunden, $av),
            'auftraege' => (int) ($auftrag['n'] ?? 0), 'auftragswert' => (int) round((float) ($auftrag['schnitt'] ?? 0)),
            'partner' => $w("SELECT COALESCE(SUM(pp.basis_cents),0) FROM partner_provisionen pp JOIN payments p ON p.id = pp.payment_id
                              WHERE p.status = 'bezahlt' AND p.paid_at BETWEEN ? AND ?", $a),
            'kosten' => $w($werbung, [$von, $bis]) + $w($kampKosten, [$von, $bis]), 'kosten_vorher' => $w($werbung, [$vv, $vb]) + $w($kampKosten, [$vv, $vb]),
            'kosten_je_anbieter' => array_merge(
                (array) self::still(static fn() => Db::all("SELECT lieferant AS wert, SUM(CASE WHEN netto_cents > 0 THEN netto_cents ELSE brutto_cents END) AS n
                                   FROM ausgaben WHERE kategorie = 'werbung' AND datum BETWEEN ? AND ? GROUP BY lieferant ORDER BY n DESC LIMIT 6", [$von, $bis]), []),
                (array) self::still(static fn() => Db::all("SELECT CONCAT('Kampagne ', k.name) AS wert, SUM(o.betrag_cents) AS n FROM mk_kosten o JOIN mk_kampagnen k ON k.id = o.kampagne_id
                                   WHERE o.ausgabe_id IS NULL AND o.datum BETWEEN ? AND ? GROUP BY k.id, k.name ORDER BY n DESC LIMIT 6", [$von, $bis]), [])),
            'kampagnen' => $w("SELECT COALESCE(SUM(betrag_cents),0) FROM spur_ereignisse WHERE kampagne_id IS NOT NULL AND event_type = 'payment_completed' AND created_at BETWEEN ? AND ?", $a)
                         + $w("SELECT COALESCE(SUM(betrag_cents),0) FROM mk_tage WHERE event_type = 'payment_completed' AND tag BETWEEN ? AND ?", [$von, $bis]),
        ];
    }

    /** Branche, Ort und Partner mit dem meisten Umsatz im Zeitraum. */
    public static function umsatzNach(string $von, string $bis): array
    {
        $a = [$von . ' 00:00:00', $bis . ' 23:59:59'];
        $liste = static fn(string $feld): array => (array) self::still(static fn() => Db::all(
            "SELECT $feld AS wert, SUM(p.amount_cents) AS n FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id
             WHERE p.status = 'bezahlt' AND p.demo = 0 AND p.paid_at BETWEEN ? AND ? AND COALESCE($feld, '') <> ''
             GROUP BY wert ORDER BY n DESC LIMIT 5", $a), []);
        return [
            'branche' => $liste('c.industry'),
            'ort'     => $liste('c.city'),
            'partner' => (array) self::still(static fn() => Db::all(
                "SELECT pa.name AS wert, SUM(pp.basis_cents) AS n FROM partner_provisionen pp JOIN payments p ON p.id = pp.payment_id JOIN partner pa ON pa.id = pp.partner_id
                 WHERE p.status = 'bezahlt' AND p.paid_at BETWEEN ? AND ? GROUP BY pa.id, pa.name ORDER BY n DESC LIMIT 5", $a), []),
        ];
    }

    /**
     * Kampagnen und Werbemittel im Zeitraum (Phase 3): Leads je Kampagne,
     * Umsatz je Kampagne, Leads je Werbemittel, dazu Kosten und Namen für die
     * Hinweise. Gewertet wird nach Leads; ohne einen einzigen Lead nach Klicks.
     */
    public static function kampagnen(string $von, string $bis): array
    {
        $leer = ['leads' => [], 'klicks' => [], 'umsatz' => [], 'werbemittel' => [], 'werbemittel_klicks' => [], 'zeilen' => []];
        return (array) self::still(static function () use ($von, $bis, $leer): array {
            require_once __DIR__ . '/MkKampagne.php';
            $l = MkKampagne::liste($von, $bis);
            $aus = $leer;
            /* Gleiche Namen (zweimal „Restaurants Herbst“ auf zwei Plattformen) bekommen ihren Code dazu. */
            $namen = array_count_values(array_map(static fn($k) => (string) $k['name'], $l['kampagnen']));
            foreach ($l['kampagnen'] as $k) {
                $nm = $namen[(string) $k['name']] > 1 ? $k['name'] . ' · /k/' . $k['code'] : (string) $k['name'];
                $aus['leads'][$nm] = (int) $k['leads']; $aus['klicks'][$nm] = (int) $k['klicks']; $aus['umsatz'][$nm] = (int) $k['umsatz'];
                $aus['zeilen'][] = ['name' => $nm, 'leads' => (int) $k['leads'], 'kosten' => (int) $k['kosten'], 'klicks' => (int) $k['klicks'],
                    'budget' => $k['budget'] ?? null, 'laufzeit' => (string) ($k['laufzeit'] ?? 'offen'), 'status' => (string) ($k['status'] ?? 'aktiv'), 'ende_am' => $k['ende_am'] ?? null];
            }
            $zeit = [$von . ' 00:00:00', $bis . ' 23:59:59'];
            foreach (Db::all("SELECT CONCAT(cr.name, ' · ', k.name) AS wert, SUM(e.event_type = 'lead_created') AS leads, SUM(e.event_type = 'campaign_visit') AS klicks
                                FROM spur_ereignisse e JOIN mk_creatives cr ON cr.id = e.creative_id JOIN mk_kampagnen k ON k.id = e.kampagne_id
                               WHERE e.created_at BETWEEN ? AND ? GROUP BY cr.id, cr.name, k.name", $zeit) as $r) {
                $aus['werbemittel'][(string) $r['wert']] = (int) $r['leads']; $aus['werbemittel_klicks'][(string) $r['wert']] = (int) $r['klicks'];
            }
            return $aus;
        }, $leer);
    }

    /** Die Akquise im Zeitraum: geprüft, eingewilligt, Termine. */
    public static function akquise(string $von, string $bis): array
    {
        $a = [$von . ' 00:00:00', $bis . ' 23:59:59'];
        $n = static fn(string $sql): int => (int) self::still(static fn() => Db::wert($sql, $a, 0), 0);
        return [
            'geprueft'     => $n("SELECT COUNT(*) FROM akq_audits WHERE gestartet_am BETWEEN ? AND ?"),
            'eingewilligt' => $n("SELECT COUNT(*) FROM akq_einwilligungen WHERE status = 'bestaetigt' AND bestaetigt_am BETWEEN ? AND ?"),
            'kontakt'      => $n("SELECT COUNT(*) FROM akq_checks WHERE created_at BETWEEN ? AND ?"),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Bewertung                                                           */
    /* ------------------------------------------------------------------ */

    /** Veränderung gegenüber dem Vergleichszeitraum in Prozent, null wenn vorher 0. */
    public static function trend(int $jetzt, int $vorher): ?float
    {
        return $vorher > 0 ? round(($jetzt - $vorher) / $vorher * 100, 1) : null;
    }

    /** Anteil in Prozent, null wenn die Basis 0 ist. */
    public static function quote(int $teil, int $basis): ?float
    {
        return $basis > 0 ? round($teil / $basis * 100, 1) : null;
    }

    /**
     * Der Erste einer Liste — mit dem Zweiten und dem Hinweis, ob die Menge
     * für eine Aussage reicht.
     * @param array<string,int> $liste
     * @return array{name:string,zahl:int,zweiter:?string,zweiter_zahl:int,summe:int,wenig:bool}|null
     */
    public static function erster(array $liste, int $wenig = self::WENIG): ?array
    {
        $liste = array_filter($liste, static fn($n) => (int) $n > 0);
        if (!$liste) { return null; }
        arsort($liste);
        $namen = array_keys($liste);
        return ['name' => (string) $namen[0], 'zahl' => (int) $liste[$namen[0]],
            'zweiter' => isset($namen[1]) ? (string) $namen[1] : null, 'zweiter_zahl' => isset($namen[1]) ? (int) $liste[$namen[1]] : 0,
            'summe' => (int) array_sum($liste), 'wenig' => array_sum($liste) < $wenig];
    }

    /** Aus [{wert, n}] eine Liste wert => n. */
    public static function alsListe(array $zeilen): array
    {
        $aus = [];
        foreach ($zeilen as $r) { $aus[(string) $r['wert']] = (int) $r['n']; }
        return $aus;
    }

    /**
     * Was klemmt und was man tun kann — nur Regeln, jede mit ihrer Zahl.
     * Keine KI: Jeder Satz lässt sich auf eine Abfrage zurückführen.
     * @return array{probleme:list<string>,empfehlungen:list<string>}
     */
    public static function hinweise(array $d): array
    {
        $p = []; $e = [];
        $a = $d['aufrufe']; $s = $d['schritte']; $l = $d['leads']; $g = $d['geld']; $o = $d['offen'];

        if ($o['anfragen'] > 0) { $p[] = $o['anfragen'] . ' Kontaktanfrage' . ($o['anfragen'] === 1 ? '' : 'n') . ' seit über 24 Stunden unbeantwortet.'; }
        if ($o['angebote'] > 0) { $p[] = $o['angebote'] . ' Angebot' . ($o['angebote'] === 1 ? '' : 'e') . ' seit über 7 Tagen ohne Antwort — nachfassen.'; }
        if ($o['bedarf'] > 0) { $p[] = $o['bedarf'] . ' abgeschickte' . ($o['bedarf'] === 1 ? 'r Bedarf wartet' : ' Bedarfe warten') . ' auf dein Angebot.'; }
        if ($s['rechner_begonnen'] >= 3) {
            $q = self::quote($s['rechner_fertig'], $s['rechner_begonnen']);
            if ($q !== null && $q < 50) { $p[] = 'Preisrechner: ' . $s['rechner_begonnen'] . ' begonnen, ' . $s['rechner_fertig'] . ' abgeschlossen — ' . number_format(100 - $q, 0, ',', '.') . ' % hören vorher auf.'; }
        }
        $t = self::trend($a['summe'], $a['vorher']);
        if ($t !== null && $t <= -30 && $a['vorher'] >= 50) { $p[] = 'Aufrufe ' . number_format(abs($t), 0, ',', '.') . ' % unter dem Vergleichszeitraum (' . $a['summe'] . ' statt ' . $a['vorher'] . ').'; }
        if ($g['kosten'] > 0 && $l['neu'] === 0) { $p[] = 'Werbekosten von ' . Fmt::geld($g['kosten']) . ' im Zeitraum, aber kein neuer Lead.'; }

        foreach (($d['kampagnen']['zeilen'] ?? []) as $kz) {
            if ($kz['kosten'] > 0 && $kz['leads'] === 0) { $p[] = 'Kampagne „' . $kz['name'] . '“: ' . Fmt::geld($kz['kosten']) . ' Kosten, ' . $kz['klicks'] . ' Klicks, noch kein Lead.'; }
            $bu = $kz['budget'] ?? null;
            if (is_array($bu) && $bu['stufe'] === 'erreicht' && ($kz['status'] ?? 'aktiv') === 'aktiv') {
                $p[] = 'Kampagne „' . $kz['name'] . '“: Budget erreicht (' . Fmt::geld($bu['ausgegeben']) . ' von ' . Fmt::geld($bu['grenze']) . ') — die Anzeige bei der Plattform pausieren oder das Budget erhöhen.';
            }
            if (($kz['laufzeit'] ?? '') === 'vorbei' && ($kz['status'] ?? '') === 'aktiv') {
                $e[] = 'Kampagne „' . $kz['name'] . '“ ist seit ' . date('d.m.Y', strtotime((string) $kz['ende_am'])) . ' abgelaufen, der Link zählt aber weiter — auf „beendet“ setzen oder die Laufzeit verlängern.';
            }
        }
        $guenstig = array_filter($d['kampagnen']['zeilen'] ?? [], static fn($kz) => $kz['kosten'] > 0 && $kz['leads'] >= 3);
        if (count($guenstig) >= 2) {
            usort($guenstig, static fn($x, $y) => ($x['kosten'] / $x['leads']) <=> ($y['kosten'] / $y['leads']));
            $e[] = 'Günstigste Leads: Kampagne „' . $guenstig[0]['name'] . '“ mit ' . Fmt::geld(intdiv($guenstig[0]['kosten'], $guenstig[0]['leads'])) . ' je Lead (' . $guenstig[0]['leads'] . ' Leads) — dort zuerst mehr Budget prüfen.';
        }
        if ($a['summe'] >= 30 && self::quote($a['mit_kampagne'], $a['summe']) < 10) {
            $e[] = 'Nur ' . $a['mit_kampagne'] . ' von ' . $a['summe'] . ' Aufrufen kamen über einen Link mit Kampagnen-Kennung. Für jeden Beitrag, jede Anzeige und jeden Flyer unter Marketing → Kampagnen einen eigenen Link anlegen — dann zeigt diese Seite, welcher Beitrag Kunden bringt.';
        }
        if ($s['checks'] >= 5) {
            $q = self::quote($s['checks_kontakt'], $s['checks']);
            if ($q !== null && $q < 20) { $e[] = $s['checks'] . ' Website-Checks, aber nur ' . $s['checks_kontakt'] . ' mit Kontakt (' . number_format($q, 0, ',', '.') . ' %). Die Bitte um die ausführliche Analyse direkt unter der Ampel prüfen.'; }
        }
        $sozial = array_intersect_key($a['plattformen'], array_flip(self::SOZIAL));
        if ($a['summe'] >= 50 && array_sum($sozial) === 0) { $e[] = 'Kein einziger Aufruf aus sozialen Netzwerken im Zeitraum — die freigegebenen Facebook-/Instagram-Entwürfe regelmäßig posten.'; }
        if ($l['neu'] > 0 && $l['qualifiziert'] === 0) { $e[] = $l['neu'] . ' neue Leads, aber keiner ging weiter (Rechner, Termin, Anfrage). Die ersten Folge-Schritte nach der E-Mail prüfen.'; }
        if ($o['termine_morgen'] > 0) { $e[] = $o['termine_morgen'] . ' Termin' . ($o['termine_morgen'] === 1 ? '' : 'e') . ' morgen — vorher den Website-Bericht des Betriebs ansehen.'; }
        if ($g['kosten'] === 0 && $l['neu'] > 0) { $e[] = 'Keine Werbeausgaben erfasst: Belege unter Geld → Ausgaben mit der Kategorie „Werbung“ eintragen, dann rechnet diese Seite Kosten pro Lead und ROAS.'; }
        return ['probleme' => array_slice($p, 0, 6), 'empfehlungen' => array_slice($e, 0, 6)];
    }

    /** Offene Posten für die Hinweise (unabhängig vom Zeitraum). */
    public static function offen(): array
    {
        $n = static fn(string $sql): int => (int) self::still(static fn() => Db::wert($sql, [], 0), 0);
        return [
            'anfragen' => $n("SELECT COUNT(*) FROM anfragen WHERE demo = 0 AND status = 'neu' AND created_at < NOW() - INTERVAL 1 DAY"),
            'angebote' => $n("SELECT COUNT(*) FROM angebote WHERE demo = 0 AND status = 'gesendet' AND gesendet_am < NOW() - INTERVAL 7 DAY"),
            'bedarf'   => $n("SELECT COUNT(*) FROM bedarf WHERE demo = 0 AND status = 'abgesendet'"),
            'termine_morgen' => $n("SELECT COUNT(*) FROM akq_termine WHERE status = 'gebucht' AND DATE(beginn) = CURDATE() + INTERVAL 1 DAY"),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Alles zusammen                                                      */
    /* ------------------------------------------------------------------ */

    public static function ueberblick(array $z, ?string $wurzel = null): array
    {
        [$von, $bis] = $z;
        $d = [
            'aufrufe'  => self::aufrufe($z, $wurzel),
            'knoepfe'  => self::knoepfe($von, $bis, $wurzel),
            'leads'    => self::leads($z),
            'schritte' => self::schritte($von, $bis),
            'geld'     => self::geld($z),
            'nach'     => self::umsatzNach($von, $bis),
            'akquise'  => self::akquise($von, $bis),
            'kampagnen'=> self::kampagnen($von, $bis),
            'offen'    => self::offen(),
        ];
        $d['hinweise'] = self::hinweise($d);
        return $d;
    }
}
