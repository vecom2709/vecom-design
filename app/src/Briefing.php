<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Standard.php';

/**
 * Der Auftrag an den Baumeister, aus dem gebaut, was ohnehin dasteht.
 *
 * WARUM ES DAS BRAUCHT
 *
 * Der Fragebogen hat 35 Felder in vier Abschnitten: Branche, Zielgruppe,
 * Standort, gewuenschte Seiten, Funktionen, Ziel, Mitbewerber, Farben, Stil,
 * Schriften, Vorbilder, Abneigungen, Tonfall, Bildrechte. Dazu der bezahlte
 * Umfang aus dem Angebot, die Sprachen, die Domain, die Deadline. Das alles
 * stand schon in der Datenbank — und nahm trotzdem den Umweg ueber Uwes Kopf
 * in ein Chatfenster. Dabei wurde es jedes Mal kuerzer, und jedes Mal anders
 * kurz: Beim ersten Kunden erwaehnt man die Abneigungen, beim vierten nicht
 * mehr.
 *
 * WARUM DIE ERSTE ZEILE EIN TITEL IST
 *
 * Ein Gespraech bei claude.ai wird nach seiner ersten Nachricht benannt.
 * Steht dort "Kundenprojekt K-2026-0007 · Ristorante Boulevard · Website",
 * heisst das Gespraech danach so — und laesst sich in einer langen Liste
 * wiederfinden. Deshalb ist die Titelzeile kein Schmuck, sondern der Grund,
 * warum die Zuordnung spaeter noch klappt.
 *
 * WARUM ES GESPEICHERT WIRD
 *
 * Nicht um es nachzulesen, sondern fuer Monat 14: Wenn ein Betreuungskunde
 * eine Aenderung will, steht hier noch, woraus die Seite gebaut ist. Ohne
 * das faengt jede spaetere Aenderung wieder bei null an — und das ist genau
 * der Punkt, an dem eine Betreuung teurer wird, als sie einbringt.
 */
final class Briefing
{
    /** Fragebogenfelder, die woanders schon stehen — die spart der Auftrag aus. */
    /* Felder, die weiter oben schon in eigener Form stehen. Zweimal dasselbe
       macht einen Auftrag nicht genauer, nur laenger -- und in einem langen
       Auftrag wird das Wichtige mitgelesen statt gelesen. */
    private const DOPPELT = ['firmenname', 'seiten_zahl', 'sprachen_zahl', 'sprachen_welche',
                             'funktionen_wahl', 'domain',
                             // stehen im Block DESIGN-DNA
                             'wirkung', 'abneigung', 'stil', 'farben', 'schriften',
                             'vorbilder', 'tonfall', 'stoert'];

    /** Die Abschnitte des Fragebogens unter ihren Ueberschriften im Auftrag. */
    private const ABSCHNITTE = [
        'unternehmen' => 'DAS UNTERNEHMEN, IN SEINEN WORTEN',
        'website'     => 'WAS DIE SEITE LEISTEN SOLL',
        'design'      => 'GESTALTUNG',
        'inhalte'     => 'INHALTE',
    ];

    /* KURZE WOERTER STATT GANZER FRAGEN
       ------------------------------------------------------------------
       Im Fragebogen steht die ausformulierte Frage — sie muss den Kunden
       abholen ("Angaben fuers Impressum: genaue Firmierung, Anschrift,
       Steuernummer oder Umsatzsteuer-Identifikationsnummer (USt-IdNr.)").
       In einem Auftrag gelesen ist das Laerm: Die Frage kennt jeder, die
       Antwort ist das Neue. Deshalb hier kurze Marken; was fehlt, faellt
       auf die Frage zurueck. */
    private const MARKEN = [
        'branche'         => 'Branche',
        'beschreibung'    => 'Was sie machen',
        'zielgruppe'      => 'Ihre Kunden',
        'standort'        => 'Standort',
        'kontakt'         => 'Kontakt und Öffnungszeiten',
        'impressum'       => 'Impressumsangaben',
        'ansprechpartner' => 'Ansprechpartner beim Kunden',
        'sprachen_welche' => 'Welche Sprachen, welche zuerst',
        'seiten'          => 'Gewünschte Seiten',
        'funktionen'      => 'Zusätzlich gewünscht',
        'ziel'            => 'Ziel der Seite',
        'inhalte'         => 'Vorhandene Inhalte',
        'beispiele'       => 'Gefällt ihnen',
        'handlung'        => 'Gewünschte Handlung',
        'mitbewerber'     => 'Mitbewerber',
        'erhalten'        => 'Muss erhalten bleiben',
        'stoert'          => 'Stört am jetzigen Auftritt',
        'suchwoerter'     => 'Suchwörter',
        'karte'           => 'Karte und Google-Eintrag',
        'farben'          => 'Farben',
        'stil'            => 'Stil',
        'schriften'       => 'Schriften',
        'logo'            => 'Logo',
        'vorbilder'       => 'Vorbilder',
        'wirkung'         => 'Gewünschte Wirkung',
        'abneigung'       => 'Auf keinen Fall',
        'texte'           => 'Texte',
        'bilder'          => 'Bilder',
        'videos'          => 'Videos',
        'social'          => 'Social Media',
        'bildrechte'      => 'Bildrechte',
        'tonfall'         => 'Tonfall',
        'sonstiges'       => 'Sonstiges',
    ];

    /* ==================================================================== */
    /*  Die Fallhoehe: wie hoch gebaut wird                                  */
    /* ==================================================================== */

    /**
     * Branchen, die von Stimmung leben.
     *
     * Bei einem Schlosser entscheidet die Telefonnummer, bei einem Hotel das
     * Bild vom Fruehstueck auf der Terrasse. Beides ist gute Arbeit, aber es
     * ist nicht dieselbe Arbeit -- und wer das nicht im Auftrag stehen hat,
     * baut fuer beide dasselbe.
     */
    private const STIMMUNG = [
        'hotel', 'albergo', 'b&b', 'bed', 'agriturismo', 'ristorante', 'restaurant',
        'trattoria', 'osteria', 'pizzeria', 'bar', 'cafe', 'caffè', 'gastronom',
        'cantina', 'wein', 'vino', 'weingut', 'tourism', 'turismo', 'reise', 'viagg',
        'hochzeit', 'wedding', 'matrimoni', 'event', 'kunst', 'arte', 'galer',
        'fotograf', 'foto', 'mode', 'moda', 'boutique', 'schmuck', 'gioiell',
        'wellness', 'spa', 'beauty', 'friseur', 'parrucchier', 'architek', 'design',
    ];

    /** Was die Stufen bedeuten — dieselben Worte wie im Skill. */
    private const STUFEN = [
        'A' => ['Klar und schnell',
                'Inhalt und Erreichbarkeit vor allem. Semantisches HTML, sauberes '
                . 'Raster, keine Bewegung ohne Grund.'],
        'B' => ['Premium-UI',
                'Design-Tokens, feine Micro-Interactions, sorgfältige Zustände. '
                . 'Das ist die Stufe, auf der die meisten Seiten richtig liegen.'],
        'C' => ['Editorial mit Bewegung',
                'Scroll-Choreografie als Drehbuch mit Beats, kinetische Typografie, '
                . 'Übergänge. Jeder Beat muss auch als Standbild funktionieren.'],
        'D' => ['Immersiv, 3D',
                'Canvas oder WebGPU mit echtem Fallback, adaptive Qualität von '
                . 'Anfang an, Kamera über ein Rig. Nur, wenn das Ergebnis es trägt.'],
    ];

    /**
     * Die Ambitionsstufe fuer dieses Projekt.
     *
     * WARUM SIE GERECHNET WIRD UND NICHT GERATEN
     *
     * "Bau die Seite nach dem Vecom-Standard" liess offen, wie hoch. Ohne
     * Angabe entsteht immer dieselbe mittlere Seite -- die dem Kunden fuer
     * 3.000 Euro zu wenig ist und dem fuer 499 zu viel Zeit kostet.
     *
     * Der Preis ist das ehrlichste Mass, das es hier gibt: Er sagt, wie viel
     * Arbeit bezahlt ist. Die Branche verschiebt ihn, weil Stimmung bei
     * manchen Gewerben die Sache selbst ist. Und was der Kunde ausdruecklich
     * will, schlaegt beides.
     *
     * D wird nie gerechnet. Wer 3D will, sagt es -- und dann setzt Uwe die
     * Stufe von Hand. Eine wacklige Stufe D ist schlechter als eine saubere C.
     *
     * @return array{stufe:string,wort:string,was:string,warum:string,gesetzt:bool}
     */
    public static function stufe(array $p, array $k, array $antworten = [], ?array $b = null): array
    {
        /* Von Hand gesetzt schlaegt alles: Du hast mit dem Menschen geredet. */
        $hand = strtoupper(trim((string) ($p['ambition'] ?? '')));
        if (isset(self::STUFEN[$hand])) {
            return [
                'stufe' => $hand,
                'wort'  => self::STUFEN[$hand][0],
                'was'   => self::STUFEN[$hand][1],
                'warum' => 'Von Hand gesetzt.',
                'gesetzt' => true,
            ];
        }

        $preis = (int) ($b['price_cents'] ?? 0);
        if ($preis === 0) {
            $preis = (int) self::still(static fn() => Db::wert(
                "SELECT summe_cents FROM angebote
                  WHERE customer_id = ? AND status IN ('angenommen','gesendet')
                  ORDER BY id DESC LIMIT 1", [(int) $k['id']], 0), 0);
        }

        $branche = mb_strtolower(trim((string) ($k['industry'] ?? '')) . ' '
                 . trim((string) ($antworten['branche'] ?? '')));
        $stimmung = false;
        foreach (self::STIMMUNG as $wort) {
            if ($wort !== '' && str_contains($branche, $wort)) { $stimmung = true; break; }
        }

        /* Was der Kunde selbst gesagt hat. Drei Woerter zur Wirkung und die
           Vorbilder verraten mehr ueber die gewuenschte Fallhoehe als jede
           Preisgrenze -- wer "ruhig, sachlich, schnell" schreibt, will kein
           Scroll-Kino, auch wenn er es bezahlen koennte. */
        $wunsch = mb_strtolower(trim((string) ($antworten['wirkung'] ?? '')) . ' '
                . trim((string) ($antworten['stil'] ?? '')) . ' '
                . trim((string) ($antworten['funktionen'] ?? '')));
        $willBewegung = (bool) preg_match(
            '~animat|bewegung|scroll|video|3d|immersiv|kino|erlebnis|emozion|movimento~u', $wunsch);
        $willRuhe = (bool) preg_match(
            '~ruhig|schlicht|sachlich|minimal|nüchtern|nuechtern|sobrio|essenziale|clean~u', $wunsch);

        $stufe = 'B';
        $warum = [];

        if ($preis > 0 && $preis < 70000 && !$stimmung) {
            $stufe = 'A';
            $warum[] = 'kleiner Auftrag (' . Fmt::geld($preis, 'EUR') . ')';
        }
        if ($preis >= 200000 || ($stimmung && $preis >= 120000)) {
            $stufe = 'C';
            $warum[] = $stimmung
                ? 'Branche lebt von Stimmung, und der Auftrag trägt es (' . Fmt::geld($preis, 'EUR') . ')'
                : 'großer Auftrag (' . Fmt::geld($preis, 'EUR') . ')';
        }
        if ($willBewegung && $stufe !== 'C') {
            $stufe = 'C';
            $warum[] = 'der Kunde hat ausdrücklich nach Bewegung gefragt';
        }
        if ($willRuhe && $stufe === 'C') {
            $stufe = 'B';
            $warum[] = 'der Kunde will es ausdrücklich ruhig — das schlägt den Preis';
        }
        if (!$warum) {
            $warum[] = $preis > 0
                ? 'normaler Auftrag (' . Fmt::geld($preis, 'EUR') . ')'
                : 'noch kein Preis hinterlegt';
        }

        return [
            'stufe' => $stufe,
            'wort'  => self::STUFEN[$stufe][0],
            'was'   => self::STUFEN[$stufe][1],
            'warum' => ucfirst(implode('; ', $warum)) . '.',
            'gesetzt' => false,
        ];
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /**
     * Die Titelzeile — und damit der Name, den das Gespraech bekommt.
     */
    public static function titel(array $p, array $k): string
    {
        $knr  = trim((string) ($k['kundennr'] ?? ''));
        $wer  = trim((string) ($k['company'] ?: $k['name']));
        $was  = trim((string) ($p['name'] ?? ''));
        /* Das Projekt heisst oft schon nach der Firma ("Bar Ultimo — Sito").
           Zweimal derselbe Name im Titel liest sich wie ein Fehler. */
        if ($wer !== '' && $was !== '' && mb_stripos($was, $wer) !== false) {
            $was = trim(str_ireplace($wer, '', $was), " \t·—-–");
        }
        if ($was === '') { $was = 'Website'; }
        return trim('Kundenprojekt ' . ($knr !== '' ? $knr . ' · ' : '') . $wer . ' · ' . $was);
    }

    /**
     * Der ganze Auftrag als Text.
     *
     * @param bool $mitStandard Hausregeln anhaengen. null heisst: wie eingestellt.
     */
    public static function bauen(int $projektId, ?bool $mitStandard = null): string
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $p['customer_id']]);
        if (!$k) { throw new RuntimeException('Zu diesem Projekt gibt es keinen Kunden.'); }

        $b = $p['order_id'] !== null
            ? self::still(static fn() => Db::one('SELECT * FROM orders WHERE id = ?', [(int) $p['order_id']]))
            : null;
        $f = self::still(static fn() => Db::one(
            'SELECT * FROM questionnaires WHERE project_id = ?', [$projektId]));
        $w = self::still(static fn() => Db::one(
            'SELECT * FROM websites WHERE project_id = ?', [$projektId]));

        $antworten = [];
        if ($f && trim((string) ($f['data'] ?? '')) !== '') {
            $antworten = (array) (json_decode((string) $f['data'], true) ?: []);
        }

        $zeilen = [];
        $zeilen[] = self::titel($p, $k);
        $zeilen[] = str_repeat('=', min(78, mb_strlen(self::titel($p, $k))));
        $zeilen[] = '';

        /* ---------- Rolle, Stufe, Werkzeug ----------------------------
           WARUM DAS GANZ OBEN STEHT

           Hier standen frueher drei Zeilen, und zwar am Ende: "Bau die Seite
           nach dem Vecom-Standard." Ein Auftrag, der erst nach zwei Seiten
           Fakten sagt, was er will, wird gelesen wie ein Anhang.

           Jetzt zuerst: wer du bist, wie hoch gebaut wird, welches Werkzeug
           dafuer da ist und in welcher Reihenfolge. Die Fakten kommen
           danach — sie sind das Material, nicht der Auftrag. */
        $st = self::stufe($p, $k, $antworten, $b);
        $zeilen[] = 'ROLLE';
        $zeilen[] = '  Du bist Art Director und Entwickler dieser Seite, nicht ein Werkzeug,';
        $zeilen[] = '  das Vorgaben ausfuellt. Der Kunde hat schon Seiten gesehen, die';
        $zeilen[] = '  aussehen wie alle anderen; er zahlt fuer eine, die das nicht tut.';
        $zeilen[] = '  Triff Entscheidungen und begruende sie kurz, statt Varianten';
        $zeilen[] = '  anzubieten, zwischen denen ich nicht waehlen kann.';
        $zeilen[] = '';

        $zeilen[] = 'AMBITIONSSTUFE ' . $st['stufe'] . ' — ' . $st['wort'];
        foreach (explode("\n", wordwrap($st['was'], 72, "\n")) as $z) { $zeilen[] = '  ' . $z; }
        $zeilen[] = '  Warum: ' . $st['warum'];
        $zeilen[] = '  Hoeher als noetig ist ein Fehler, kein Ehrgeiz: Eine saubere Stufe';
        $zeilen[] = '  ' . $st['stufe'] . ' schlaegt eine wacklige daruber immer.';
        $zeilen[] = '';

        $zeilen[] = 'WERKZEUG — IN DIESER REIHENFOLGE';
        $zeilen[] = '  1. Skill "web-design-studio" laden. Erst die Design-DNA schreiben';
        $zeilen[] = '     (Haltung, Farbe, Typografie, Raster, Materialitaet, Bildsprache,';
        $zeilen[] = '     Motion, und die Zeile "Nicht:"), zeig sie mir, dann erst Code.';
        if (in_array($st['stufe'], ['C', 'D'], true)) {
            $zeilen[] = '  2. Zusaetzlich "cinematic-web-experience-architect": Beat-Drehbuch';
            $zeilen[] = '     vor jeder Animation. Jeder Beat muss als Standbild funktionieren.';
            $zeilen[] = '  3. Fuer Tiefe und Elevation "schatteneffekte" statt geratener';
            $zeilen[] = '     Schatten — Schatten ohne Lichtquelle sieht man sofort.';
            $zeilen[] = '  4. Vor dem Ausbau drei bewusst verschiedene Richtungen zeigen, je';
            $zeilen[] = '     mit einem Hero-Ausschnitt. Ich waehle eine, dann baust du sie';
            $zeilen[] = '     fertig — nicht alle drei halb.';
        } else {
            $zeilen[] = '  2. Fuer Tiefe und Elevation "schatteneffekte" statt geratener';
            $zeilen[] = '     Schatten — Schatten ohne Lichtquelle sieht man sofort.';
            $zeilen[] = '  3. Kein Scroll-Kino und kein 3D auf dieser Stufe. Was hier zaehlt,';
            $zeilen[] = '     sind Abstaende, Typo-Skala, Zustaende und Ladezeit.';
        }
        $zeilen[] = '';

        /* ---------- Womit gebaut wird ---------------------------------
           WARUM NICHT DIE GANZE LISTE

           Es gibt eine lange Liste dessen, was das Web kann. Vollstaendig in
           einen Auftrag gelegt macht sie das Ergebnis schlechter: Wer alles
           nennt, hat nichts gesagt, und gebaut wird dann, was am
           eindrucksvollsten klingt — Bewegung auf einer Seite, auf der jemand
           die Oeffnungszeiten sucht.

           Technik::fuer() gibt genau die Scheibe zurueck, die zu Stufe und
           Branche passt. Was die Branche NICHT braucht, steht mit dabei: Bei
           einer Werkstatt ist Scroll-Kino nicht zu wenig Aufwand, sondern der
           falsche. */
        $tk = self::still(static function () use ($st, $k, $antworten) {
            require_once __DIR__ . '/Technik.php';
            return Technik::fuer((string) $st['stufe'], (string) ($k['industry'] ?? ''), $antworten);
        });
        if ($tk) {
            $ausgeben = static function (array $eintraege) use (&$zeilen): void {
                foreach ($eintraege as $e) {
                    foreach (explode("\n", wordwrap((string) $e, 72, "\n")) as $i => $t) {
                        $zeilen[] = ($i === 0 ? '  - ' : '    ') . $t;
                    }
                }
            };

            $zeilen[] = 'TECHNIK — DAS GILT IMMER';
            $ausgeben($tk['immer']);
            $zeilen[] = '';

            if ($tk['stufe']) {
                $zeilen[] = 'TECHNIK — WAS STUFE ' . $st['stufe'] . ' DAZUNIMMT';
                $ausgeben($tk['stufe']);
                $zeilen[] = '';
            }

            if ($tk['branche'] !== null) {
                require_once __DIR__ . '/Technik.php';
                $zeilen[] = 'FÜR DIESE BRANCHE — ' . mb_strtoupper(Technik::brancheWort($tk['branche']['name']));
                $ausgeben($tk['branche']['braucht']);
                if ($tk['branche']['nicht']) {
                    $ausgeben(['Hier hat nichts zu suchen: '
                        . implode(', ', $tk['branche']['nicht']) . '.']);
                }
                $zeilen[] = '';
            }

            $zeilen[] = 'OHNE AUSDRÜCKLICHEN GRUND NICHT';
            $ausgeben($tk['nie']);
            $zeilen[] = '';
        }

        /* ---------- Die DNA, in den Worten des Kunden ---------- */
        $dna = [];
        $dnaPaar = static function (string $wort, string $wert) use (&$dna): void {
            $wert = trim($wert);
            if ($wert === '') { return; }
            $marke = $wort . ':';
            $luecke = max(1, 18 - mb_strlen($marke));
            $dna[] = '  ' . $marke . str_repeat(' ', $luecke) . self::umbruch($wert);
        };
        $dnaPaar('Wirkung', (string) ($antworten['wirkung'] ?? ''));
        $dnaPaar('Stil', (string) ($antworten['stil'] ?? ''));
        $dnaPaar('Farben', (string) ($antworten['farben'] ?? ''));
        $dnaPaar('Schriften', (string) ($antworten['schriften'] ?? ''));
        $dnaPaar('Vorbilder', (string) ($antworten['vorbilder'] ?? ''));
        $dnaPaar('Tonfall', (string) ($antworten['tonfall'] ?? ''));
        if ($dna) {
            $zeilen[] = 'DESIGN-DNA — IN SEINEN WORTEN';
            foreach ($dna as $z) { $zeilen[] = $z; }
            $zeilen[] = '';
        }

        /* ---------- Was diese Seite NICHT ist ----------------------------
           Der Skill nennt diese Zeile die wichtigste der ganzen DNA, und er
           hat recht: Ohne sie entsteht der Look, den man an jeder zweiten
           Seite erkennt. Das Material dafuer liegt laengst vor -- der Kunde
           hat es im Fragebogen aufgeschrieben, es stand nur zwischen dreissig
           anderen Zeilen. */
        $nicht = [];
        $ab = trim((string) ($antworten['abneigung'] ?? ''));
        if ($ab !== '') { $nicht[] = 'Der Kunde ausdruecklich: ' . self::umbruch($ab); }
        $st2 = trim((string) ($antworten['stoert'] ?? ''));
        if ($st2 !== '') { $nicht[] = 'Stoert ihn am jetzigen Auftritt: ' . self::umbruch($st2); }
        $nicht[] = 'Kein Baukasten-Look: keine mittige Hero mit zwei Knoepfen, '
                 . 'keine drei gleichen Karten nebeneinander, kein Verlauf lila-blau '
                 . 'ohne Grund, kein Glas ueberall, keine Emoji als Symbole, kein '
                 . 'Karussell fuer Wichtiges, kein 3D-Objekt, das nichts erzaehlt.';
        $zeilen[] = 'DIESE SEITE IST NICHT';
        foreach ($nicht as $z) {
            foreach (explode("\n", wordwrap($z, 72, "\n")) as $i => $t) {
                $zeilen[] = ($i === 0 ? '  - ' : '    ') . $t;
            }
        }
        $zeilen[] = '';

        /* ---------- Wer ---------- */
        $zeilen[] = 'WER';
        /* str_pad zaehlt Bytes, nicht Zeichen — bei "Ansprechpartner" mit
           genau 16 Zeichen fiele ausserdem das trennende Leerzeichen weg
           und es staende "Ansprechpartner:Salvatore". Deshalb von Hand. */
        $paar = static function (string $wort, string $wert) use (&$zeilen): void {
            $wert = trim($wert);
            if ($wert === '') { return; }
            $marke = $wort . ':';
            $luecke = max(1, 18 - mb_strlen($marke));
            $zeilen[] = '  ' . $marke . str_repeat(' ', $luecke) . $wert;
        };
        $paar('Firma', (string) ($k['company'] ?: $antworten['firmenname'] ?? ''));
        $paar('Ansprechpartner', (string) $k['name']);
        $paar('Kundennummer', (string) ($k['kundennr'] ?? ''));
        /* NICHT DIE SPRACHE DER SEITE
           ------------------------------------------------------------
           customers.sprache ist die Sprache, in der ICH mit dem Kunden
           schreibe. Welche Sprache seine SEITE fuehrt, ist eine ganz
           andere Frage: Ein deutscher Handwerker in Sizilien bekommt seine
           Post auf Deutsch und braucht trotzdem eine italienische
           Startseite, weil seine Kunden Italiener sind. Stand hier nur
           "Sprache", war die Verwechslung eingebaut. */
        $paar('Post an ihn auf', self::sprachwort((string) ($k['sprache'] ?? '')));
        $paar('Branche', (string) ($k['industry'] ?? ''));
        $ort = trim(implode(' ', array_filter([
            trim((string) ($k['zip'] ?? '')), trim((string) ($k['city'] ?? ''))])));
        $paar('Ort', $ort);
        $zeilen[] = '';

        /* ---------- Auftrag ---------- */
        $zeilen[] = 'AUFTRAG';
        if ($b) {
            $paar('Bestellung', (string) $b['order_no'] . ' vom ' . Fmt::datum((string) $b['ordered_at']));
            $paar('Paket', (string) $b['package_name']);
            $paar('Preis', Fmt::geld((int) $b['price_cents'], (string) $b['currency']));
        }
        $bezahlt = self::still(static function () use ($projektId) {
            require_once __DIR__ . '/Umfang.php';
            return Umfang::bezahlt($projektId);
        });
        if ($bezahlt) {
            $paar('Angebot', (string) $bezahlt['nummer']);
            $paar('Seiten', (string) $bezahlt['seiten']);
            $paar('Sprachen bezahlt', (string) $bezahlt['sprachen']);
            /* Direkt daneben, was der Kunde dazu geschrieben hat — die Zahl
               allein sagt nicht, welche Sprachen und welche fuehrt. */
            $paar('Welche', trim((string) ($antworten['sprachen_welche'] ?? '')));
            $mehr = self::bausteinworte($bezahlt);
            $paar('Enthalten', $mehr);
        }
        /* WENN FRAGEBOGEN UND ANGEBOT AUSEINANDERLAUFEN
           ------------------------------------------------------------
           Der Fragebogen kann mehr Umfang beschreiben, als das Angebot
           deckt. Stuende hier nur der Fragebogen, entstuende Arbeit, die
           niemand bezahlt hat; stuende nur das Angebot da, faende der
           Widerspruch nie statt. Also beides, mit klarer Ansage, was gilt. */
        $mehr = self::still(static function () use ($projektId) {
            require_once __DIR__ . '/Umfang.php';
            return Umfang::mehrbedarf($projektId);
        });
        if ($mehr && !empty($mehr['mehr'])) {
            $worte = [];
            foreach ((array) $mehr['mehr'] as $z) {
                $worte[] = trim((string) ($z['name'] ?? ''));
            }
            $worte = array_filter($worte);
            if ($worte) {
                $paar('Ungeklärt', 'Der Fragebogen nennt mehr als das Angebot: '
                    . implode(', ', $worte) . '.');
                $zeilen[] = '                    Das ist NICHT bezahlt. Bau nach dem Angebot oben und sag mir,';
                $zeilen[] = '                    was dadurch fehlt.';
            }
        }
        $paar('Domain', (string) ($antworten['domain'] ?? ($w['domain'] ?? '')));
        $paar('Deadline', $p['deadline'] ? Fmt::datum((string) $p['deadline']) : '');
        $paar('Stand', self::stand($p));
        $paar('Vorschau', (string) ($p['preview_url'] ?? ''));
        $paar('Live', (string) ($w['url'] ?? ''));
        $zeilen[] = '';

        /* ---------- Die Antworten des Kunden ---------- */
        $felder = self::still(static fn() => Texte::FRAGEBOGEN, []);
        foreach (self::ABSCHNITTE as $abschnitt => $ueberschrift) {
            if (!isset($felder[$abschnitt]['felder'])) { continue; }
            $block = [];
            foreach ($felder[$abschnitt]['felder'] as $name => $feld) {
                if (in_array($name, self::DOPPELT, true)) { continue; }
                $wert = trim((string) ($antworten[$name] ?? ''));
                if ($wert === '') { continue; }
                $marke = self::MARKEN[$name] ?? (string) ($feld['de'] ?? $name);
                $block[] = '  ' . $marke . ': ' . self::umbruch($wert);
            }
            if (!$block) { continue; }
            $zeilen[] = $ueberschrift;
            foreach ($block as $z) { $zeilen[] = $z; }
            $zeilen[] = '';
        }

        /* Steht der Fragebogen noch aus, ist das eine Angabe fuer sich —
           sonst baut man auf Vermutungen und merkt es erst beim Kunden. */
        if (!$f || trim((string) ($f['data'] ?? '')) === '') {
            $zeilen[] = 'ACHTUNG';
            $zeilen[] = '  Der Fragebogen ist noch nicht ausgefüllt. Alles ab hier ist';
            $zeilen[] = '  meine Annahme, nicht die Aussage des Kunden.';
            $zeilen[] = '';
        } elseif ((string) ($f['status'] ?? '') === 'offen') {
            $zeilen[] = 'ACHTUNG';
            $zeilen[] = '  Der Fragebogen ist begonnen, aber nicht abgeschickt. Es kann';
            $zeilen[] = '  noch etwas dazukommen.';
            $zeilen[] = '';
        }

        /* ---------- Bausteine, die passen koennten ---------- */
        $vorschlaege = self::still(static function () use ($antworten, $k) {
            require_once __DIR__ . '/Muster.php';
            return Muster::passend($antworten, (string) ($k['industry'] ?? ''));
        }, []);
        if ($vorschlaege) {
            $zeilen[] = 'BAUSTEINE, DIE SCHON LAUFEN';
            foreach ($vorschlaege as $m) {
                $zeilen[] = '  ' . (string) $m['name']
                    . (trim((string) ($m['laeuft_bei'] ?? '')) !== ''
                        ? ' — läuft bei ' . (string) $m['laeuft_bei'] : '');
            }
            $zeilen[] = '';
        }

        /* ---------- Woran gemessen wird -------------------------------
           Diese sechzehn Punkte prueft die Verwaltung spaeter ohnehin, jede
           Nacht, an der fertigen Adresse. Sie erst danach zu nennen, heisst
           Punkte nachreichen zu lassen, die von Anfang an haetten dastehen
           koennen. Also stehen sie im Auftrag. */
        $zeilen[] = 'ABNAHME — DAGEGEN WIRD GEPRUEFT, BEVOR DER KUNDE SIE SIEHT';
        $zeilen[] = '  Erreichbar über HTTPS, keine gemischten Inhalte.';
        $zeilen[] = '  Titel und Beschreibung je Seite, eigen und nicht abgeschnitten.';
        $zeilen[] = '  viewport gesetzt, Handy-Ansicht sauber.';
        $zeilen[] = '  <html lang> richtig ausgezeichnet.';
        $zeilen[] = '  Bild beim Teilen (og:image) und Favicon vorhanden.';
        if ($bezahlt && (int) ($bezahlt['sprachen'] ?? 1) > 1) {
            $zeilen[] = '  hreflang für alle ' . (int) $bezahlt['sprachen'] . ' Sprachfassungen, x-default gesetzt.';
        }
        $zeilen[] = '  Jedes Bild mit width und height, Alt-Text, und als WebP oder AVIF.';
        $zeilen[] = '  robots.txt und sitemap.xml liegen da.';
        $zeilen[] = '';

        /* ---------- Was ich will ---------- */
        $zeilen[] = 'AUFTRAG AN DICH';
        $zeilen[] = '  Erst die Design-DNA, dann das Inhalts-Skelett in semantischem HTML,';
        $zeilen[] = '  dann die Startseite fertig — Hero zuerst und ganz fertig, denn der';
        $zeilen[] = '  erste Bildschirm entscheidet, wie die ganze Seite wirkt. Zeig sie';
        $zeilen[] = '  mir, dann weiter.';
        $zeilen[] = '  Baue gegen echte Inhalte, nie gegen Blindtext: Wenn Texte fehlen,';
        $zeilen[] = '  schreib realistische in der richtigen Länge und markier sie als';
        $zeilen[] = '  Platzhalter. Wo im Fragebogen etwas fehlt, frag — rate nicht.';
        $zeilen[] = '';

        $text = implode("\n", $zeilen);

        $mit = $mitStandard ?? Standard::anhaengen();
        if ($mit) {
            $text .= "\n" . str_repeat('-', 60) . "\n" . Standard::text() . "\n";
        }
        return rtrim($text) . "\n";
    }

    /* ==================================================================== */
    /*  Der zweite Prompt: was seit dem letzten Mal dazugekommen ist         */
    /* ==================================================================== */

    /**
     * Der Weiter-Prompt.
     *
     * WARUM NICHT NOCHMAL DAS GANZE BRIEFING
     *
     * Fuer jede Folgerunde dasselbe lange Blatt zu kopieren hat zwei Kosten.
     * Die kleinere: Es ist laestig. Die groessere: Das Gespraech bei Claude
     * kennt den Auftrag laengst, und wer ihn zum vierten Mal danebenlegt,
     * begraebt darin genau das, was neu ist — die drei Saetze vom Kunden,
     * den offenen Abnahmepunkt, den geaenderten Wunsch.
     *
     * Dieser Prompt enthaelt deshalb nur die Veraenderung: was seit dem
     * letzten Briefing passiert ist, und was daraus folgt. Ist nichts
     * passiert, sagt er das auch — dann braucht es keine Runde.
     */
    public static function weiter(int $projektId): string
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$projektId]);
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $p['customer_id']]);
        if (!$k) { throw new RuntimeException('Zu diesem Projekt gibt es keinen Kunden.'); }

        /* Seit wann "neu" gilt: seit dem Briefing. Gibt es keins, seit dem
           Projektbeginn -- dann ist eben alles neu. */
        $seit = trim((string) ($p['briefing_am'] ?? '')) ?: (string) $p['created_at'];

        $zeilen = [];
        $zeilen[] = 'WEITER — ' . self::titel($p, $k);
        $zeilen[] = str_repeat('=', min(78, mb_strlen('WEITER — ' . self::titel($p, $k))));
        $zeilen[] = '';
        $zeilen[] = '  Das Briefing kennst du. Hier steht nur, was seit dem '
                  . Fmt::datum($seit) . ' dazugekommen ist.';
        $zeilen[] = '';

        $etwas = false;

        /* ---------- Was der Kunde geschrieben hat ---------- */
        $post = (array) self::still(static fn() => Db::all(
            "SELECT body, created_at FROM messages
              WHERE customer_id = ? AND sender = 'kunde' AND created_at > ?
              ORDER BY id", [(int) $k['id'], $seit]), []);
        if ($post) {
            $etwas = true;
            $zeilen[] = 'DER KUNDE HAT GESCHRIEBEN';
            foreach ($post as $m) {
                $zeilen[] = '  ' . Fmt::datum((string) $m['created_at']) . ':';
                foreach (explode("\n", wordwrap(trim((string) $m['body']), 70, "\n")) as $t) {
                    $zeilen[] = '    ' . $t;
                }
            }
            $zeilen[] = '';
        }

        /* ---------- Der Fragebogen ---------- */
        $f = self::still(static fn() => Db::one(
            'SELECT * FROM questionnaires WHERE project_id = ?', [$projektId]));
        if ($f && trim((string) ($f['updated_at'] ?? '')) > $seit) {
            $etwas = true;
            $zeilen[] = 'DER FRAGEBOGEN HAT SICH GEAENDERT';
            $zeilen[] = '  Stand: ' . ((string) ($f['status'] ?? '') === 'abgeschlossen'
                ? 'abgeschickt' : 'in Arbeit') . ', zuletzt ' . Fmt::datum((string) $f['updated_at']) . '.';
            $zeilen[] = '  Lass dir das aktuelle Briefing geben, bevor du weiterbaust.';
            $zeilen[] = '';
        }

        /* ---------- Neues Material ---------- */
        $dateien = (array) self::still(static fn() => Db::all(
            "SELECT orig_name, created_at FROM files
              WHERE customer_id = ? AND uploaded_by = 'kunde' AND created_at > ?
              ORDER BY id", [(int) $k['id'], $seit]), []);
        if ($dateien) {
            $etwas = true;
            $zeilen[] = 'NEUES MATERIAL';
            foreach ($dateien as $d) { $zeilen[] = '  ' . (string) $d['orig_name']; }
            $zeilen[] = '';
        }

        /* ---------- Offene Abnahmepunkte ----------
           Das ist der wertvollste Teil dieses Prompts: konkrete, gemessene
           Maengel an der Seite, die es schon gibt. Kein Geschmack, keine
           Meinung -- eine Liste, die sich abarbeiten laesst. */
        $ab = self::still(static function () use ($p) {
            $roh = trim((string) ($p['abnahme'] ?? ''));
            return $roh === '' ? null : (array) (json_decode($roh, true) ?: []);
        });
        if ($ab && !empty($ab['punkte'])) {
            $offen = [];
            foreach ((array) $ab['punkte'] as $pt) {
                if (($pt['stand'] ?? '') === 'gut') { continue; }
                $offen[] = '  ' . ($pt['stand'] === 'schlecht' ? '! ' : '- ')
                         . (string) ($pt['was'] ?? '') . ': ' . (string) ($pt['befund'] ?? '');
            }
            if ($offen) {
                $etwas = true;
                $zeilen[] = 'ABNAHME — DAS STEHT NOCH OFFEN';
                $zeilen[] = '  Geprüft an ' . (string) ($ab['url'] ?? 'der Vorschau')
                          . ($p['abnahme_am'] ? ', ' . Fmt::datum((string) $p['abnahme_am']) : '') . '.';
                foreach ($offen as $z) { $zeilen[] = $z; }
                $zeilen[] = '';
            }
        }

        /* ---------- Stand ---------- */
        $zeilen[] = 'STAND';
        $zeilen[] = '  ' . self::stand($p);
        if (trim((string) ($p['preview_url'] ?? '')) !== '') {
            $zeilen[] = '  Vorschau: ' . (string) $p['preview_url'];
        }
        $zeilen[] = '';

        if (!$etwas) {
            $zeilen[] = 'NICHTS NEUES';
            $zeilen[] = '  Seit dem letzten Briefing hat sich nichts gemeldet und nichts';
            $zeilen[] = '  geändert. Wenn du nicht selbst weiterbaust, braucht es hier';
            $zeilen[] = '  keine Runde.';
            $zeilen[] = '';
        } else {
            $zeilen[] = 'AUFTRAG';
            $zeilen[] = '  Arbeite das oben ein. Halt dich an die Design-DNA, auf die wir';
            $zeilen[] = '  uns geeinigt haben — was hier steht, ändert Inhalte und Mängel,';
            $zeilen[] = '  nicht die Gestaltung. Zeig mir danach, was sich geändert hat,';
            $zeilen[] = '  nicht die ganze Seite noch einmal.';
            $zeilen[] = '';
        }

        return rtrim(implode("\n", $zeilen)) . "\n";
    }

    /** Bauen und am Projekt festhalten. */
    public static function speichern(int $projektId, ?bool $mitStandard = null): string
    {
        $text = self::bauen($projektId, $mitStandard);
        self::still(static fn() => Db::update('projects', $projektId, [
            'briefing' => $text, 'briefing_am' => date('Y-m-d H:i:s')]));
        self::still(static function () use ($projektId, $text) {
            require_once __DIR__ . '/Events.php';
            Events::protokoll('briefing', 'Briefing erzeugt (' . mb_strlen($text) . ' Zeichen)',
                (int) Db::wert('SELECT customer_id FROM projects WHERE id = ?', [$projektId], 0) ?: null,
                null, $projektId);
        });
        return $text;
    }

    /** Die Adresse des Gespraechs am Projekt festhalten. */
    public static function chatMerken(int $projektId, string $url): void
    {
        $url = trim($url);
        if ($url !== '' && !preg_match('~^https://(www\.)?claude\.ai/~i', $url)) {
            throw new RuntimeException('Das muss eine Adresse bei claude.ai sein.');
        }
        Db::update('projects', $projektId, ['chat_url' => $url !== '' ? mb_substr($url, 0, 255) : null]);
    }

    /* ------------------------------------------------------------------ */

    private static function sprachwort(string $s): string
    {
        return ['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'][strtolower($s)] ?? '';
    }

    private static function stand(array $p): string
    {
        require_once __DIR__ . '/Status.php';
        $wort = self::still(static fn() => Status::label(Status::PROJEKT, (string) $p['status']), (string) $p['status']);
        return trim((string) $wort . ', ' . (int) $p['progress'] . ' %');
    }

    /** Die bezahlten Bausteine als Woerter, ohne die beiden Zaehler. */
    private static function bausteinworte(array $bezahlt): string
    {
        require_once __DIR__ . '/Umfang.php';
        $slugs = [];
        foreach ((array) ($bezahlt['slugs'] ?? []) as $slug => $menge) {
            if (in_array($slug, array_keys(Umfang::ZAEHLER), true)) { continue; }
            $slugs[] = (string) $slug;
        }
        $worte = $slugs ? (string) self::still(
            static fn() => Umfang::worte(implode(',', $slugs), 'de'), implode(', ', $slugs)) : '';
        $frei = array_filter(array_map('trim', (array) ($bezahlt['frei'] ?? [])));
        if ($frei) { $worte = trim($worte . ($worte !== '' ? ', ' : '') . implode(', ', $frei)); }
        return $worte;
    }

    /**
     * Lange Antworten umbrechen, aber eingerueckt.
     *
     * Ein Fragebogenfeld kann ein Absatz sein. Ohne Umbruch steht er als eine
     * Zeile von 600 Zeichen da, und niemand — Mensch wie Maschine — liest das
     * so genau wie einen gesetzten Absatz.
     */
    private static function umbruch(string $wert): string
    {
        $wert = trim(preg_replace('/[ \t]+/', ' ', str_replace(["\r\n", "\r"], "\n", $wert)) ?? $wert);
        if (mb_strlen($wert) <= 76 && !str_contains($wert, "\n")) { return $wert; }
        $aus = [];
        foreach (explode("\n", $wert) as $absatz) {
            $absatz = trim($absatz);
            if ($absatz === '') { continue; }
            $aus[] = wordwrap($absatz, 74, "\n    ", false);
        }
        return "\n    " . implode("\n    ", $aus);
    }
}
