<?php
declare(strict_types=1);

/* ==========================================================================
   Umfang.php — Was beauftragt ist, was der Kunde im Fragebogen ankreuzt,
   und was dazwischen liegt.

   DAS PROBLEM, DAS ES OHNE DIESE DATEI GAB

   Der Preis entsteht aus acht strukturierten Fragen: "Wenige Seiten (3-5)",
   "Speisekarte zeigen", "Termine". Daraus wird eine Zeile mit Menge und
   Betrag, und die friert beim Zusagen ein -- ein Festpreis, der sich hinter
   dem Kunden bewegt, waere keiner.

   Der Fragebogen fragte dieselben Dinge danach ein zweites Mal, nur als
   Freitext: "Welche Seiten sollen es sein". Der Kunde, der vier Seiten
   bezahlt hat, schrieb dort in aller Ruhe neun hin -- nicht aus Absicht, er
   beantwortete ja bloss die Frage, die dastand. Niemand merkte es. Der Preis
   blieb richtig, der Umfang wurde falsch, und der Unterschied fiel erst auf,
   als die Arbeit schon getan war.

   WAS SICH GEAENDERT HAT

   Der Fragebogen zeigt jetzt dieselbe Liste, aus der der Preis kam --
   angehakt, was beauftragt ist. Ein zusaetzlicher Haken ist damit ein
   exaktes Signal statt eines Satzes, den jemand auslegen muss. Und weil das
   Bezahlte danebensteht, beantwortet die Liste nebenbei eine Frage, die
   Kunden wirklich haben: Was habe ich eigentlich bestellt?

   WARUM DER PREIS TROTZDEM NICHT NACHRECHNET

   Weil das eine Nachforderung waere, die niemand vereinbart hat. Der Kunde
   sieht, dass etwas nicht im Angebot steht, und dass sich jemand dazu
   meldet. Was es kostet, sagt ein Mensch -- hier steht nur, was es waere.
   ========================================================================== */
final class Umfang
{
    /** Bausteine, die im Fragebogen als Zaehler stehen statt als Kaestchen. */
    public const ZAEHLER = [
        'seite'   => 'seiten_zahl',
        'sprache' => 'sprachen_zahl',
    ];

    /** Das Feld, in dem die angekreuzten Bausteine stehen. */
    public const FELD_WAHL = 'funktionen_wahl';

    /** Gruppen, die als Kaestchen erscheinen -- in dieser Reihenfolge. */
    public const GRUPPEN = ['funktion', 'inhalt', 'betreuung'];

    /* ----------------------------------------------------------------------
       Was beauftragt ist
       ---------------------------------------------------------------------- */

    /**
     * Der eingefrorene Umfang aus dem angenommenen Angebot.
     *
     * Nicht aus dem Bedarf und nicht aus dem Katalog: Der Bedarf ist, was der
     * Kunde einmal angeklickt hat, der Katalog aendert sich mit jeder
     * Preisrunde. Verbindlich ist allein das Blatt, auf dem die Zusage steht.
     *
     * @return array{angebot_id:int,nummer:string,seiten:int,sprachen:int,
     *               slugs:array<string,int>,frei:array<int,string>}|null
     */
    public static function bezahlt(int $projektId): ?array
    {
        $a = self::still(static fn() => Db::one(
            'SELECT a.id, a.nummer
               FROM angebote a
               JOIN projects p ON p.order_id = a.order_id
              WHERE p.id = ? AND a.status = ?
              ORDER BY a.id DESC LIMIT 1',
            [$projektId, 'angenommen']));
        if (!$a) { return null; }

        $slugs = []; $frei = [];
        foreach (self::still(static fn() => Db::all(
            'SELECT baustein_slug, bezeichnung, menge FROM angebot_positionen
              WHERE angebot_id = ? ORDER BY sortierung, id', [(int) $a['id']]), []) as $p) {
            $slug = trim((string) ($p['baustein_slug'] ?? ''));
            // Eine freie Zeile hat keinen Baustein. Sie laesst sich nicht
            // vergleichen -- aber verschweigen darf man sie auch nicht, sonst
            // sieht die Verwaltung einen Umfang, der kleiner ist als der
            // verkaufte.
            if ($slug === '') { $frei[] = (string) $p['bezeichnung']; continue; }
            $slugs[$slug] = max(1, (int) $p['menge']);
        }

        return [
            'angebot_id' => (int) $a['id'],
            'nummer'     => (string) $a['nummer'],
            // Das Grundgeruest enthaelt die erste Seite und die erste
            // Sprache; die Bausteine zaehlen nur, was darueber hinausgeht.
            'seiten'     => 1 + (int) ($slugs['seite'] ?? 0),
            'sprachen'   => 1 + (int) ($slugs['sprache'] ?? 0),
            'slugs'      => $slugs,
            'frei'       => $frei,
        ];
    }

    /* ----------------------------------------------------------------------
       Die Liste fuer den Fragebogen
       ---------------------------------------------------------------------- */

    /**
     * Die ankreuzbaren Bausteine, nach Gruppen sortiert.
     *
     * Ohne das Grundgeruest -- das ist keine Wahl -- und ohne die beiden
     * Bausteine, die als Zaehler erscheinen.
     *
     * @return array<string,array<string,array>> gruppe => slug => Katalogzeile
     */
    public static function katalogWahl(): array
    {
        require_once __DIR__ . '/Baukasten.php';
        $aus = array_fill_keys(self::GRUPPEN, []);
        foreach (self::still(static fn() => Baukasten::katalog(), []) as $slug => $b) {
            $gruppe = (string) ($b['gruppe'] ?? '');
            if (!isset($aus[$gruppe])) { continue; }
            if (in_array($slug, array_keys(self::ZAEHLER), true)) { continue; }
            if (in_array($slug, Baukasten::FEST, true)) { continue; }
            $aus[$gruppe][$slug] = $b;
        }
        return array_filter($aus);
    }

    /**
     * Dieselbe Liste flach, als slug => Katalogzeile.
     *
     * Der Vergleich darf ausschliesslich hierueber laufen. Alles andere, was
     * in einem Angebot stehen kann -- das Grundgeruest, eine frei getippte
     * Zeile, ein aus dem Katalog genommener Baustein --, hat im Fragebogen
     * nie ein Kaestchen gehabt. Es als "abgewaehlt" zu zaehlen, weil kein
     * Haken zurueckkam, waere ein Vorwurf an den Kunden fuer etwas, das er
     * gar nicht sehen konnte.
     */
    public static function waehlbar(): array
    {
        $aus = [];
        foreach (self::katalogWahl() as $bausteine) {
            foreach ($bausteine as $slug => $b) { $aus[$slug] = $b; }
        }
        return $aus;
    }

    /**
     * Was im Fragebogen angekreuzt ist.
     *
     * Steht das Feld noch gar nicht in den Antworten, hat der Kunde die Liste
     * nie gesehen -- dann gilt das Beauftragte, sonst stuende beim ersten
     * Oeffnen alles auf null und jede Zeile sae aus wie eine Abbestellung.
     *
     * @return array{seiten:int,sprachen:int,slugs:array<string,bool>,gesehen:bool}
     */
    public static function gewaehlt(array $daten, ?array $bezahlt): array
    {
        $gesehen = array_key_exists(self::FELD_WAHL, $daten);

        if (!$gesehen) {
            return [
                'seiten'   => (int) ($bezahlt['seiten'] ?? 1),
                'sprachen' => (int) ($bezahlt['sprachen'] ?? 1),
                'slugs'    => array_fill_keys(array_keys(array_intersect_key(
                    (array) ($bezahlt['slugs'] ?? []), self::waehlbar())), true),
                'gesehen'  => false,
            ];
        }

        $slugs = [];
        foreach (explode(',', (string) $daten[self::FELD_WAHL]) as $s) {
            $s = trim($s);
            if ($s !== '') { $slugs[$s] = true; }
        }

        return [
            'seiten'   => self::zahl($daten['seiten_zahl']   ?? null, (int) ($bezahlt['seiten'] ?? 1)),
            'sprachen' => self::zahl($daten['sprachen_zahl'] ?? null, (int) ($bezahlt['sprachen'] ?? 1), 6),
            'slugs'    => $slugs,
            'gesehen'  => true,
        ];
    }

    /** Eine Zahl aus dem Formular, in vernuenftigen Grenzen. */
    private static function zahl(mixed $roh, int $ersatz, int $hoechstens = 60): int
    {
        $w = (int) trim((string) $roh);
        if ($w < 1) { return max(1, $ersatz); }
        return min($hoechstens, $w);
    }

    /**
     * Eine gespeicherte Hakenliste als lesbare Aufzaehlung.
     *
     * Gespeichert werden Bausteinnamen wie "speisekarte,termine" -- kurz,
     * eindeutig und unabhaengig von jeder Uebersetzung. Lesen will das
     * niemand so, weder der Kunde in seiner Zusammenfassung noch Uwe in der
     * Verwaltung. Ein Slug, den es im Katalog nicht mehr gibt, bleibt
     * stehen wie er ist: lieber ein raues Wort als eine verschwundene Zeile.
     */
    public static function worte(string $csv, string $sprache = 'de'): string
    {
        $csv = trim($csv);
        if ($csv === '') { return ''; }

        require_once __DIR__ . '/Baukasten.php';
        $katalog = self::still(static fn() => Baukasten::katalog(false), []);

        $aus = [];
        foreach (explode(',', $csv) as $slug) {
            $slug = trim($slug);
            if ($slug === '') { continue; }
            $aus[] = isset($katalog[$slug]) ? Baukasten::name($katalog[$slug], $sprache) : $slug;
        }
        return implode(', ', $aus);
    }

    /* ----------------------------------------------------------------------
       Der Vergleich
       ---------------------------------------------------------------------- */

    /**
     * Was der Kunde will und noch nicht bezahlt hat -- und was er bezahlt hat
     * und nicht mehr will.
     *
     * Gibt null zurueck, wenn es nichts zu klaeren gibt: kein Angebot, kein
     * Fragebogen, keine Abweichung, oder die Abweichung ist schon abgehakt.
     * Der Normalfall bleibt damit still, und genau darum darf die Meldung,
     * wenn sie kommt, ernst genommen werden.
     */
    public static function mehrbedarf(int $projektId): ?array
    {
        $f = self::still(static fn() => Db::one(
            'SELECT id, data, status, umfang_geklaert FROM questionnaires WHERE project_id = ?',
            [$projektId]));
        if (!$f) { return null; }

        $bezahlt = self::bezahlt($projektId);
        if ($bezahlt === null) { return null; }

        $daten = [];
        if (($f['data'] ?? null) !== null && $f['data'] !== '') {
            $daten = json_decode((string) $f['data'], true) ?: [];
        }
        $will = self::gewaehlt($daten, $bezahlt);
        // Nie geoeffnet heisst nie widersprochen.
        if (!$will['gesehen']) { return null; }

        require_once __DIR__ . '/Baukasten.php';
        $katalog  = self::still(static fn() => Baukasten::katalog(), []);
        $waehlbar = self::waehlbar();
        $sprache  = 'de';   // Die Verwaltung liest deutsch.

        $mehr = []; $summe = 0; $monatlich = 0; $aufAnfrage = [];

        /* --- Seiten und Sprachen: Zahlen, keine Haken ------------------ */
        foreach (self::ZAEHLER as $slug => $feld) {
            $istZahl  = $slug === 'seite' ? $will['seiten']    : $will['sprachen'];
            $sollZahl = $slug === 'seite' ? $bezahlt['seiten'] : $bezahlt['sprachen'];
            $diff = $istZahl - $sollZahl;
            if ($diff <= 0 || !isset($katalog[$slug])) { continue; }

            $b = $katalog[$slug];
            $einzel = Baukasten::mitte((int) $b['preis_cents'],
                                       (int) $b['preis_bis_cents'] ?: (int) $b['preis_cents']);
            $summe += $einzel * $diff;
            $mehr[$slug] = [
                'slug' => $slug, 'name' => Baukasten::name($b, $sprache),
                'menge' => $diff, 'einzel_cents' => $einzel,
                'summe_cents' => $einzel * $diff, 'monatlich' => 0,
                'war' => $sollZahl, 'wird' => $istZahl,
            ];
        }

        /* --- Angekreuzt, aber nicht beauftragt -------------------------- */
        foreach (array_keys($will['slugs']) as $slug) {
            if (isset($bezahlt['slugs'][$slug])) { continue; }
            $b = $waehlbar[$slug] ?? null;
            if (!$b) { continue; }

            /* Was nur auf Anfrage geht, wandert ohne Zahl mit: Der Kunde sagt,
               dass er es will, den Preis nennt Uwe. Dieselbe Regel wie beim
               Gegenvorschlag -- zwei verschiedene Antworten auf dieselbe Frage
               waeren eine zu viel. */
            if (in_array($slug, Baukasten::NUR_AUF_ANFRAGE, true)) {
                $aufAnfrage[$slug] = Baukasten::name($b, $sprache);
                continue;
            }

            $einzel = Baukasten::mitte((int) $b['preis_cents'],
                                       (int) $b['preis_bis_cents'] ?: (int) $b['preis_cents']);
            if ((int) $b['monatlich']) { $monatlich += $einzel; } else { $summe += $einzel; }
            $mehr[$slug] = [
                'slug' => $slug, 'name' => Baukasten::name($b, $sprache),
                'menge' => 1, 'einzel_cents' => $einzel, 'summe_cents' => $einzel,
                'monatlich' => (int) $b['monatlich'],
            ];
        }

        /* --- Beauftragt, aber abgewaehlt --------------------------------
           Ohne Betrag. Wer etwas abwaehlt, bekommt kein Geld zurueck, weil
           eine Zahl in einer Liste steht -- das ist ein Gespraech, keine
           Rechnung. Aufleuchten muss es trotzdem: Sonst baut Uwe eine Woche
           an einem Shop, den der Kunde nicht mehr will. */
        $weniger = [];
        foreach ($bezahlt['slugs'] as $slug => $menge) {
            // Was nie ein Kaestchen hatte, kann niemand abgewaehlt haben.
            if (!isset($waehlbar[$slug])) { continue; }
            if (isset($will['slugs'][$slug])) { continue; }
            $weniger[$slug] = Baukasten::name($waehlbar[$slug], $sprache);
        }
        foreach (self::ZAEHLER as $slug => $feld) {
            $istZahl  = $slug === 'seite' ? $will['seiten']    : $will['sprachen'];
            $sollZahl = $slug === 'seite' ? $bezahlt['seiten'] : $bezahlt['sprachen'];
            if ($istZahl >= $sollZahl || !isset($katalog[$slug])) { continue; }
            $weniger[$slug] = Baukasten::name($katalog[$slug], $sprache)
                . ' (' . $sollZahl . ' beauftragt, ' . $istZahl . ' gewünscht)';
        }

        if (!$mehr && !$weniger && !$aufAnfrage) { return null; }

        $signatur = self::signatur($mehr, $weniger, $aufAnfrage);
        if ($signatur === trim((string) ($f['umfang_geklaert'] ?? ''))) { return null; }

        return [
            'fragebogen_id'   => (int) $f['id'],
            'projekt_id'      => $projektId,
            'angebot_id'      => $bezahlt['angebot_id'],
            'nummer'          => $bezahlt['nummer'],
            'abgeschlossen'   => (string) $f['status'] === 'abgeschlossen',
            'mehr'            => $mehr,
            'weniger'         => $weniger,
            'auf_anfrage'     => $aufAnfrage,
            'summe_cents'     => $summe,
            'monatlich_cents' => $monatlich,
            'signatur'        => $signatur,
        ];
    }

    /**
     * Der Fingerabdruck genau dieses Unterschieds.
     *
     * Er entscheidet, ob eine abgehakte Meldung abgehakt bleibt. Kreuzt der
     * Kunde spaeter etwas Weiteres an, passt der Abdruck nicht mehr, und die
     * Fuehrung meldet sich von selbst wieder -- ohne dass jemand daran denken
     * musste.
     */
    private static function signatur(array $mehr, array $weniger, array $aufAnfrage): string
    {
        $teile = [];
        foreach ($mehr as $slug => $z)   { $teile[] = '+' . $slug . ':' . (int) $z['menge']; }
        foreach ($aufAnfrage as $slug => $_) { $teile[] = '?' . $slug; }
        foreach (array_keys($weniger) as $slug) { $teile[] = '-' . $slug; }
        sort($teile);
        return md5(implode('|', $teile));
    }

    /* ----------------------------------------------------------------------
       Erledigen
       ---------------------------------------------------------------------- */

    /** Haken dran: Dieser Unterschied ist besprochen. */
    public static function abhaken(int $fragebogenId, string $signatur): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $signatur)) { return; }
        self::still(static fn() => Db::update('questionnaires', $fragebogenId,
            ['umfang_geklaert' => $signatur]));
    }

    /**
     * Aus dem Mehrbedarf wird eine zweite Rate auf derselben Bestellung.
     *
     * WARUM KEIN ZWEITES ANGEBOT
     *
     * Ein Angebot wird angenommen, und aus einer Annahme entsteht in diesem
     * System eine Bestellung -- mit eigenem Projekt, eigenem Fragebogen,
     * eigener Anzahlung. Fuer zusaetzliche Arbeit an einem laufenden Projekt
     * waere das ein zweites Projekt fuer dieselbe Website: zwei Zeilen, wo
     * eine Sache ist.
     *
     * Zusaetzliche Arbeit ist eine zusaetzliche Rechnung, kein zweiter
     * Auftrag. Also entsteht hier genau das: eine Zahlung auf der bestehenden
     * Bestellung, mit einer Bezeichnung, die sagt, wofuer. Der Zahlungslink,
     * die Mail und der Beleg laufen danach durch dieselben Knoepfe wie
     * Anzahlung und Restzahlung -- nichts Neues zu lernen.
     *
     * @return int|null Die Kennung der neuen Zahlung.
     */
    public static function nachtrag(int $projektId): ?int
    {
        $m = self::mehrbedarf($projektId);
        if ($m === null || $m['summe_cents'] <= 0) { return null; }

        $b = self::still(static fn() => Db::one(
            'SELECT o.* FROM orders o JOIN projects p ON p.order_id = o.id WHERE p.id = ?',
            [$projektId]));
        if (!$b) { return null; }

        $worte = [];
        foreach ($m['mehr'] as $z) {
            if ((int) $z['monatlich']) { continue; }
            $worte[] = ((int) $z['menge'] > 1 ? $z['menge'] . '× ' : '') . $z['name'];
        }
        $bezeichnung = mb_substr('Nachtrag: ' . implode(', ', $worte), 0, 190);

        $zahlungId = (int) Db::transaktion(static function () use ($b, $m, $bezeichnung, $projektId) {
            $zid = Db::insert('payments', [
                'order_id'    => (int) $b['id'],
                'art'         => 'nachtrag',
                'bezeichnung' => $bezeichnung,
                'provider'    => 'offen',
                'amount_cents'=> (int) $m['summe_cents'],
                'currency'    => (string) $b['currency'],
                'status'      => 'ausstehend',
            ]);

            /* Der Gesamtpreis der Bestellung waechst mit. Sonst stuende auf
               dem Beleg und in jeder Uebersicht ein Betrag, der die Haelfte
               der Arbeit verschweigt -- und die Pruefung "vollstaendig
               bezahlt" waere schon erfuellt, bevor der Nachtrag bezahlt ist. */
            Db::update('orders', (int) $b['id'], [
                'price_cents' => (int) $b['price_cents'] + (int) $m['summe_cents'],
            ]);

            return $zid;
        });

        self::abhaken($m['fragebogen_id'], $m['signatur']);

        self::still(static fn() => Events::protokoll('nachtrag',
            $bezeichnung . ' — ' . Fmt::geld((int) $m['summe_cents'], (string) $b['currency']),
            (int) $b['customer_id'], (int) $b['id'], $projektId));

        return $zahlungId > 0 ? $zahlungId : null;
    }

    /* ---------------------------------------------------------------------- */

    /** Eine fehlende Wanderung darf keine Seite umbringen. */
    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
