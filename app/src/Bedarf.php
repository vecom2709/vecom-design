<?php
declare(strict_types=1);

/* ==========================================================================
   Bedarf.php — Was der Kunde im Konfigurator angegeben hat.

   WARUM DER BEDARF KEINE ZWEITE ANFRAGE IST

   Es waere naheliegend, dafuer einen eigenen Posteingang zu bauen. Waere aber
   falsch: Uwe schaut an einer Stelle nach, was hereingekommen ist. Ein
   ausgefuellter Bedarf erzeugt deshalb ganz normal eine Anfrage — mit allem,
   was daran haengt: Kunde anlegen, Eingangsbestaetigung, Meldung, Zuruf aufs
   Handy. Der Bedarf haengt als Zusatz daran und traegt die Rechnung.

   OHNE KONTO, WIE UEBERALL SONST

   Ein langer Zufallsschluessel in der Adresse. Wer zwischendurch zumacht,
   kommt mit demselben Link an dieselbe Stelle zurueck. Das ist derselbe
   Gedanke wie beim Fragebogen und bei der Projektseite — die Kunden hier
   verwalten kein weiteres Passwort.

   DER BEDARF WIRD NIE UMGESCHRIEBEN

   Was der Kunde angegeben hat, bleibt stehen, auch wenn das Angebot spaeter
   ganz anders aussieht. Beides nebeneinander beantwortet die Frage, warum
   ein Preis so ist, wie er ist.
   ========================================================================== */
final class Bedarf
{
    /** So lange bleibt ein begonnener, nie abgesendeter Bedarf abrufbar. */
    public const GUELTIG_TAGE = 30;

    /* DIE AUSWAHL AUS DEN BRANCHEN-DEMOS
       ----------------------------------------------------------------------
       Auf der Startseite kann man einen Lack waehlen oder einen Schuh in
       Farbe und Groesse. Der Knopf darunter fuehrt hierher -- und bisher
       ging die Auswahl dabei verloren. Jetzt kommt sie als kurzer Schluessel
       mit (demo=auto-karmin, demo=schuh-blau-42).

       Nur was hier steht, kommt durch. Die Adresse ist oeffentlich; freier
       Text daraus landete sonst ungeprueft in Uwes Posteingang. */
    public const DEMOS = [
        'auto' => [
            'name' => ['it' => 'demo automotive', 'de' => 'Automotive-Demo', 'en' => 'automotive demo'],
            'art'  => ['it' => 'vernice', 'de' => 'Lack', 'en' => 'paint'],
            'varianten' => [
                'karmin'  => ['it' => 'rosso carminio', 'de' => 'Karminrot', 'en' => 'carmine red'],
                'perl'    => ['it' => 'bianco perla', 'de' => 'Perlweiß', 'en' => 'pearl white'],
                'graphit' => ['it' => 'grafite', 'de' => 'Graphit', 'en' => 'graphite'],
            ],
            'zweck' => ['zeigen', 'kontakt'],
        ],
        // Kleinwagen und Mittelklasse (23.09.2026): eigene Entwuerfe neben dem
        // Konzeptauto, gleiche Zwecke wie die Automotive-Demo.
        'kleinwagen' => [
            'name' => ['it' => 'demo automotive, utilitaria', 'de' => 'Automotive-Demo, Kleinwagen', 'en' => 'automotive demo, small car'],
            'art'  => ['it' => 'vernice', 'de' => 'Lack', 'en' => 'paint'],
            'varianten' => [
                'azzurro' => ['it' => 'azzurro metallizzato', 'de' => 'Azurblau Metallic', 'en' => 'azure blue metallic'],
                'bianco'  => ['it' => 'bianco pastello', 'de' => 'Uni-Weiß', 'en' => 'solid white'],
                'salvia'  => ['it' => 'verde salvia metallizzato', 'de' => 'Salbeigrün Metallic', 'en' => 'sage green metallic'],
            ],
            'zweck' => ['zeigen', 'kontakt'],
        ],
        'mittelklasse' => [
            'name' => ['it' => 'demo automotive, berlina', 'de' => 'Automotive-Demo, Mittelklasse', 'en' => 'automotive demo, mid-size saloon'],
            'art'  => ['it' => 'vernice', 'de' => 'Lack', 'en' => 'paint'],
            'varianten' => [
                'blunotte' => ['it' => 'blu notte metallizzato', 'de' => 'Nachtblau Metallic', 'en' => 'midnight blue metallic'],
                'argento'  => ['it' => 'argento metallizzato', 'de' => 'Silber Metallic', 'en' => 'silver metallic'],
                'rosso'    => ['it' => 'rosso metallizzato', 'de' => 'Rot Metallic', 'en' => 'red metallic'],
            ],
            'zweck' => ['zeigen', 'kontakt'],
        ],
        'schuh' => [
            'name' => ['it' => 'demo e-commerce', 'de' => 'E-Commerce-Demo', 'en' => 'e-commerce demo'],
            'art'  => ['it' => 'colore', 'de' => 'Farbe', 'en' => 'colour'],
            'varianten' => [
                'blau'      => ['it' => 'azzurro', 'de' => 'Hellblau', 'en' => 'light blue'],
                'rose'      => ['it' => 'rosa', 'de' => 'Rosé', 'en' => 'rose'],
                'anthrazit' => ['it' => 'antracite', 'de' => 'Anthrazit', 'en' => 'anthracite'],
            ],
            'groessen' => [38, 45],
            'zweck' => ['shop'],
        ],
        // Produktdemos der Galerie (24.09.2026, Wunsch A3): die gewaehlte
        // Variante kommt mit in die Anfrage.
        'wein' => [
            'name' => ['it' => 'demo vino e prodotti naturali', 'de' => 'Demo Wein & Naturprodukte', 'en' => 'wine & natural products demo'],
            'art'  => ['it' => 'prodotto', 'de' => 'Produkt', 'en' => 'product'],
            'varianten' => [
                'rosso' => ['it' => 'vino rosso', 'de' => 'Rotwein', 'en' => 'red wine'],
                'bianco' => ['it' => 'vino bianco', 'de' => 'Weißwein', 'en' => 'white wine'],
                'olio' => ['it' => 'olio d’oliva', 'de' => 'Olivenöl', 'en' => 'olive oil'],
            ],
            'zweck' => ['zeigen', 'shop'],
        ],
        'schmuck' => [
            'name' => ['it' => 'demo gioielli e orologi', 'de' => 'Demo Schmuck & Uhren', 'en' => 'jewellery & watches demo'],
            'art'  => ['it' => 'metallo', 'de' => 'Metall', 'en' => 'metal'],
            'varianten' => [
                'stahl' => ['it' => 'acciaio', 'de' => 'Edelstahl', 'en' => 'steel'],
                'gelbgold' => ['it' => 'oro giallo', 'de' => 'Gelbgold', 'en' => 'yellow gold'],
                'rosegold' => ['it' => 'oro rosa', 'de' => 'Roségold', 'en' => 'rose gold'],
            ],
            'zweck' => ['zeigen', 'shop'],
        ],
        'kueche' => [
            'name' => ['it' => 'progettatore cucina', 'de' => 'Küchenplaner', 'en' => 'kitchen planner'],
            'art'  => ['it' => '', 'de' => '', 'en' => ''],
            'varianten' => [
                'planer' => ['it' => 'progetto su misura', 'de' => 'eigene Planung', 'en' => 'own plan'],
            ],
            'zweck' => ['zeigen', 'kontakt'],
        ],
        'gastro' => [
            'name' => ['it' => 'demo ristorazione', 'de' => 'Demo Gastronomie', 'en' => 'restaurant demo'],
            'art'  => ['it' => 'tavola', 'de' => 'Tisch', 'en' => 'table'],
            'varianten' => [
                'weiss' => ['it' => 'lino bianco', 'de' => 'weißes Leinen', 'en' => 'white linen'],
                'terrakotta' => ['it' => 'terracotta', 'de' => 'Terrakotta', 'en' => 'terracotta'],
                'anthrazit' => ['it' => 'antracite', 'de' => 'Anthrazit', 'en' => 'charcoal'],
            ],
            'zweck' => ['zeigen', 'kontakt'],
        ],
        'lkw' => [
            'name' => ['it' => 'demo logistica', 'de' => 'Demo Logistik', 'en' => 'logistics demo'],
            'art'  => ['it' => 'colore flotta', 'de' => 'Flottenfarbe', 'en' => 'fleet colour'],
            'varianten' => [
                'rot' => ['it' => 'rosso', 'de' => 'Rot', 'en' => 'red'],
                'weiss' => ['it' => 'bianco', 'de' => 'Weiß', 'en' => 'white'],
                'blau' => ['it' => 'blu', 'de' => 'Blau', 'en' => 'blue'],
            ],
            'zweck' => ['zeigen', 'kontakt'],
        ],
    ];

    /* DER KUECHENPLAN
       ----------------------------------------------------------------------
       Der Planer auf der Startseite gibt seinen Plan als kurzen Code mit
       (plan=l-300-240-240_salbei-eiche-messing-1_e110a60s80g60_a60k80c60_).
       Geprueft wird Zeichen fuer Zeichen gegen dieselbe Grammatik wie im
       Planer; lesbar gemacht wird er hier, damit in der Anfrage nicht ein
       Code steht, sondern eine Kueche. */
    private const PLAN_RE = '/^([zli])-(\d{3})-(\d{3})-(\d{3})_(salbei|weiss|nussbaum|graphit)-(eiche|marmor|keramik)-(messing|edelstahl|schwarz|grifflos)-([01])_((?:[atskgobehcv]\d{1,3}){0,24})_((?:[atskgobehcv]\d{1,3}){0,24})_((?:[atskgobehcv]\d{1,3}){0,24})$/';
    private const PLAN_WOERTER = [
        'form'  => ['z' => ['it' => 'lineare', 'de' => 'Küchenzeile', 'en' => 'single wall'], 'l' => ['it' => 'ad angolo', 'de' => 'L-Form', 'en' => 'L-shaped'], 'i' => ['it' => 'con isola', 'de' => 'mit Insel', 'en' => 'with island']],
        'wand'  => ['it' => 'parete', 'de' => 'Wand', 'en' => 'wall'],
        'insel' => ['it' => 'isola', 'de' => 'Insel', 'en' => 'island'],
        'moebel'=> ['it' => 'mobili', 'de' => 'Schränke', 'en' => 'cabinets'],
        'ober'  => ['it' => 'con pensili', 'de' => 'mit Hängeschränken', 'en' => 'with wall cabinets'],
        'stil'  => ['salbei' => ['it' => 'ante salvia', 'de' => 'Fronten Salbei', 'en' => 'sage fronts'], 'weiss' => ['it' => 'ante bianche', 'de' => 'Fronten Weiß', 'en' => 'white fronts'], 'nussbaum' => ['it' => 'ante noce', 'de' => 'Fronten Nussbaum', 'en' => 'walnut fronts'], 'graphit' => ['it' => 'ante grafite', 'de' => 'Fronten Graphit', 'en' => 'graphite fronts'],
                    'eiche' => ['it' => 'piano rovere', 'de' => 'Platte Eiche', 'en' => 'oak worktop'], 'marmor' => ['it' => 'piano marmo', 'de' => 'Platte Marmor', 'en' => 'marble worktop'], 'keramik' => ['it' => 'piano ceramica', 'de' => 'Platte Keramik', 'en' => 'ceramic worktop'],
                    'messing' => ['it' => 'maniglie ottone', 'de' => 'Griffe Messing', 'en' => 'brass handles'], 'edelstahl' => ['it' => 'maniglie acciaio', 'de' => 'Griffe Edelstahl', 'en' => 'steel handles'], 'schwarz' => ['it' => 'maniglie nere', 'de' => 'Griffe Schwarz', 'en' => 'black handles'], 'grifflos' => ['it' => 'senza maniglie', 'de' => 'grifflos', 'en' => 'handleless']],
    ];

    public static function planPruefen(string $roh): string
    {
        $roh = strtolower(trim($roh));
        return (strlen($roh) <= 300 && preg_match(self::PLAN_RE, $roh)) ? $roh : '';
    }

    /** "L-Form, Wand A 300 cm, Wand B 240 cm, 11 Schränke, Fronten Salbei, …" */
    public static function planText(string $plan, string $sprache): string
    {
        $plan = self::planPruefen($plan);
        if ($plan === '' || !preg_match(self::PLAN_RE, $plan, $t)) { return ''; }
        $w = self::PLAN_WOERTER;
        $h = static fn (array $x): string => Texte::h($x, $sprache);
        $teile = [$h($w['form'][$t[1]]), $h($w['wand']) . ' A ' . (int) $t[2] . ' cm'];
        if ($t[1] === 'l') { $teile[] = $h($w['wand']) . ' B ' . (int) $t[3] . ' cm'; }
        $zahl = preg_match_all('/[a-z]\d+/', $t[9] . $t[10] . $t[11]);
        if ($t[1] === 'i') {
            $insel = array_sum(array_map('intval', preg_split('/[a-z]/', $t[11], -1, PREG_SPLIT_NO_EMPTY)));
            $teile[] = $h($w['insel']) . ' ' . $insel . ' cm';
        }
        $teile[] = $zahl . ' ' . $h($w['moebel']);
        foreach ([5, 6, 7] as $i) { $teile[] = $h($w['stil'][$t[$i]]); }
        if ($t[8] === '1') { $teile[] = $h($w['ober']); }
        return implode(', ', $teile);
    }


    /** Prueft einen Demo-Schluessel und gibt ihn bereinigt zurueck — oder ''. */
    public static function demoPruefen(string $roh): string
    {
        if (!preg_match('/^([a-z]+)-([a-z]+)(?:-(\d{2}))?$/', $roh, $t)) { return ''; }
        $d = self::DEMOS[$t[1]] ?? null;
        if (!$d || !isset($d['varianten'][$t[2]])) { return ''; }
        $g = $t[3] ?? '';
        if ($g !== '') {
            if (!isset($d['groessen'])) { return ''; }
            [$von, $bis] = $d['groessen'];
            if ((int) $g < $von || (int) $g > $bis) { return ''; }
        }
        return $t[1] . '-' . $t[2] . ($g !== '' ? '-' . $g : '');
    }

    /** "Automotive-Demo, Lack Karminrot" — in der Sprache des Kunden. */
    public static function demoText(string $demo, string $sprache): string
    {
        $demo = self::demoPruefen($demo);
        if ($demo === '') { return ''; }
        $t = explode('-', $demo);
        $d = self::DEMOS[$t[0]];
        $art = Texte::h($d['art'], $sprache);
        $text = Texte::h($d['name'], $sprache) . ', ' . ($art !== '' ? $art . ' ' : '')
              . Texte::h($d['varianten'][$t[1]], $sprache);
        if (isset($t[2])) { $text .= ', ' . ($sprache === 'it' ? 'taglia' : ($sprache === 'de' ? 'Größe' : 'size')) . ' ' . $t[2]; }
        return $text;
    }

    /** Welche Zwecke eine Demo nahelegt — zum Vorbelegen der ersten Frage. */
    public static function demoZweck(string $demo): array
    {
        $demo = self::demoPruefen($demo);
        return $demo === '' ? [] : self::DEMOS[explode('-', $demo)[0]]['zweck'];
    }

    /* ----------------------------------------------------------------------
       Anlegen, laden, speichern
       ---------------------------------------------------------------------- */

    /** Beginnt einen neuen Bedarf und gibt die Zeile zurueck. */
    public static function starten(string $sprache): array
    {
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        $token   = bin2hex(random_bytes(24));
        $id = Db::insert('bedarf', [
            'token'   => $token,
            'sprache' => $sprache,
            'status'  => 'offen',
            'schritt' => 1,
        ]);
        return (array) Db::one('SELECT * FROM bedarf WHERE id = ?', [$id]);
    }

    /** Laedt einen Bedarf ueber seinen Schluessel. Null, wenn es ihn nicht gibt. */
    public static function laden(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) { return null; }
        $z = Db::one('SELECT * FROM bedarf WHERE token = ?', [$token]);
        if (!$z) { return null; }

        // Ein begonnener, nie abgesendeter Bedarf laeuft ab. Ein abgesendeter
        // bleibt — an ihm haengt eine Anfrage, und die ist nicht vergaenglich.
        if ($z['status'] === 'offen') {
            $alter = time() - strtotime((string) $z['created_at']);
            if ($alter > self::GUELTIG_TAGE * 86400) { return null; }
        }
        return $z;
    }

    /** Die Antworten als Feld. Leer, wenn noch nichts da ist. */
    public static function antworten(array $z): array
    {
        $roh = (string) ($z['antworten'] ?? '');
        if ($roh === '') { return []; }
        $a = json_decode($roh, true);
        return is_array($a) ? $a : [];
    }

    /**
     * Schreibt die Antworten eines Schritts dazu.
     *
     * Zusammengefuehrt statt ersetzt: Wer im dritten Schritt zurueckgeht und
     * eine Antwort aendert, soll nicht die anderen sieben verlieren.
     */
    public static function speichern(int $id, array $neu, int $schritt): void
    {
        $z = Db::one('SELECT * FROM bedarf WHERE id = ?', [$id]);
        if (!$z || $z['status'] !== 'offen') { return; }

        $alt = self::antworten($z);
        foreach ($neu as $schluessel => $wert) {
            if (!isset(Baukasten::FRAGEN[$schluessel])) { continue; }
            $alt[$schluessel] = self::saubern($schluessel, $wert);
        }

        Db::update('bedarf', $id, [
            'antworten' => json_encode($alt, JSON_UNESCAPED_UNICODE),
            'schritt'   => max(1, min(Baukasten::schrittZahl(), $schritt)),
        ]);
    }

    /**
     * Nimmt nur an, was in der Frage auch vorgesehen ist.
     *
     * Das Formular kommt von aussen. Was hier nicht durchkommt, landet nicht
     * in der Datenbank und schon gar nicht in einer Rechnung.
     */
    private static function saubern(string $schluessel, mixed $wert): mixed
    {
        $frage = Baukasten::FRAGEN[$schluessel];

        // Als Zeichenketten vergleichen, nicht als das, was PHP daraus macht.
        // Die Antworten auf "In wie vielen Sprachen?" heissen '1', '2', '3' —
        // und PHP verwandelt solche Schluessel beim Anlegen des Feldes still in
        // Ganzzahlen. Ein strenger Vergleich von '3' mit 3 ist dann falsch, und
        // die Antwort waere kommentarlos verschwunden.
        $erlaubt = array_map('strval', array_keys($frage['optionen']));

        if (($frage['art'] ?? 'einfach') === 'mehrfach') {
            $liste = is_array($wert) ? $wert : [];
            return array_values(array_intersect(array_map('strval', $liste), $erlaubt));
        }
        $w = (string) (is_array($wert) ? '' : $wert);
        return in_array($w, $erlaubt, true) ? $w : '';
    }

    /* ----------------------------------------------------------------------
       Absenden
       ---------------------------------------------------------------------- */

    /**
     * Schliesst den Bedarf ab: rechnen, Kunde anlegen, Anfrage erzeugen.
     *
     * Die Reihenfolge ist Absicht. Zuerst steht der Bedarf fest — er ist das,
     * was der Kunde gerade getan hat und darf unter keinen Umstaenden
     * verlorengehen. Erst danach kommt alles, was schiefgehen darf: Anfrage,
     * Meldung, Mail.
     */
    public static function absenden(int $id, array $kontakt): bool
    {
        $z = Db::one('SELECT * FROM bedarf WHERE id = ?', [$id]);
        if (!$z || $z['status'] !== 'offen') { return false; }

        $name  = trim((string) ($kontakt['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($kontakt['email'] ?? '')));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return false; }

        $antworten = self::antworten($z);

        /* DIE GEWAEHLTE SPRACHE SCHLAEGT DIE ANGEZEIGTE
           ------------------------------------------------------------------
           In $z steht, in welcher Fassung er den Konfigurator gelesen hat --
           und die kam aus dem Link, der bis heute ueberall "lang=it" trug.
           Im Kontaktblock steht jetzt die Frage, in welcher Sprache er
           schreiben soll. Seine Antwort gilt: fuer die Zusammenfassung, fuer
           die Kundenakte und fuer alles, was danach an ihn rausgeht. */
        $gewaehlt = strtolower(trim((string) ($kontakt['sprache'] ?? '')));
        $sprache  = in_array($gewaehlt, ['it', 'de', 'en'], true)
            ? $gewaehlt
            : (string) $z['sprache'];

        // Rechnen, bevor irgendetwas geschrieben wird: Aendern sich morgen die
        // Preise im Katalog, bleibt hier stehen, womit der Kunde gerechnet hat.
        $r      = Baukasten::rechnen($antworten);
        $spanne = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);

        Db::update('bedarf', $id, [
            'name'            => mb_substr($name, 0, 120),
            'email'           => mb_substr($email, 0, 190),
            'telefon'         => mb_substr(trim((string) ($kontakt['telefon'] ?? '')), 0, 60),
            'firma'           => mb_substr(trim((string) ($kontakt['firma'] ?? '')), 0, 160),
            'empfehl_code'    => mb_substr(strtoupper(trim((string) ($kontakt['empfehl_code'] ?? ''))), 0, 16),
            'empfehl_wer'     => mb_substr(trim((string) ($kontakt['empfehl_wer'] ?? '')), 0, 160),
            'von_cents'       => $spanne['von_cents'],
            'bis_cents'       => $spanne['bis_cents'],
            'monatlich_cents' => (int) $r['monatlich_cents'],
            'sprache'         => $sprache,
            'status'          => 'abgesendet',
            'abgesendet_am'   => date('Y-m-d H:i:s'),
        ]);

        // Kam er aus einer Demo, steht seine Auswahl als erste Zeile da.
        $demo = self::demoText((string) ($kontakt['demo'] ?? ''), $sprache);
        // Kuechenplan: lesbar plus Link, der genau diese Planung wieder oeffnet
        $plan = self::planPruefen((string) ($kontakt['plan'] ?? ''));
        if ($plan !== '') {
            $demo = ($demo !== '' ? $demo . ': ' : '') . self::planText($plan, $sprache)
                  . "\n" . rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/' . ($sprache === 'it' ? '' : $sprache . '/') . '?plan=' . $plan . '#kuechenplaner';
        }
        $vorspann = $demo === '' ? '' : strtr(Texte::h(Texte::BEDARF['demoAusgang'], $sprache), ['{wahl}' => $demo]) . "\n\n";

        // Ab hier darf alles scheitern, ohne den Bedarf mitzunehmen.
        try {
            require_once __DIR__ . '/Anfrage.php';
            $anfrageId = Anfrage::annehmen([
                'name'      => $name,
                'email'     => $email,
                'telefon'   => (string) ($kontakt['telefon'] ?? ''),
                // Der Betriebsname stand bisher nur am Bedarf. In der
                // Kundenakte blieb das Feld leer, und auf Vertragsblatt und
                // Beleg stand der Personenname statt der Firma.
                'firma'     => (string) ($kontakt['firma'] ?? ''),
                'sprache'   => $sprache,
                // Er hat sie im Formular ausgewaehlt — das ist eine Angabe,
                // keine Vermutung, und die Verwaltung soll den Unterschied
                // kennen.
                'sprache_gefragt' => true,
                'nachricht' => $vorspann . self::zusammenfassung($antworten, $sprache, $spanne, (int) $r['monatlich_cents']),
            ]);
            if ($anfrageId) {
                Db::update('bedarf', $id, ['anfrage_id' => $anfrageId]);
                $k = Db::one('SELECT customer_id FROM anfragen WHERE id = ?', [$anfrageId]);
                if ($k) { Db::update('bedarf', $id, ['customer_id' => (int) $k['customer_id']]); }
            }
        } catch (Throwable $e) {
            try {
                Events::melden('bedarf_fehler', 'Bedarf kam an, Anfrage nicht', 'schlecht',
                    $name . ' — ' . $e->getMessage(), '/bedarf/' . $id);
            } catch (Throwable $e2) { /* dann eben nicht */ }
        }

        // Zuletzt die Empfehlung. Sie ist das Entbehrlichste an diesem Vorgang
        // — eine fehlende Gutschrift laesst sich nachtragen, ein verlorener
        // Auftrag nicht. Deshalb steht sie am Ende und in eigenem Netz.
        $code = trim((string) ($kontakt['empfehl_code'] ?? ''));
        $wer  = trim((string) ($kontakt['empfehl_wer'] ?? ''));
        if ($code !== '' || $wer !== '') {
            try {
                require_once __DIR__ . '/Empfehlung.php';
                $frisch = Db::one('SELECT customer_id, anfrage_id FROM bedarf WHERE id = ?', [$id]);
                Empfehlung::vormerken(
                    $id,
                    $frisch && $frisch['anfrage_id'] !== null ? (int) $frisch['anfrage_id'] : null,
                    $frisch && $frisch['customer_id'] !== null ? (int) $frisch['customer_id'] : null,
                    $code, $wer
                );
            } catch (Throwable $e) { /* nachtragbar */ }
        }

        return true;
    }

    /**
     * Der Bedarf in Worten, fuer die Anfrage und fuer die Verwaltung.
     *
     * Bewusst als Text und nicht als Tabelle: Er landet in der Nachricht der
     * Anfrage und in einer E-Mail, und dort gibt es keine Tabelle.
     */
    /**
     * Was der Kunde im Konfigurator schon gesagt hat -- als Vorbelegung fuer
     * den Fragebogen.
     *
     * WARUM DAS SEIN MUSS
     *
     * Der Konfigurator fragt acht Dinge, bevor ein Preis entsteht. Der
     * Fragebogen fragt danach vierunddreissig, damit die Seite gebaut werden
     * kann. Sechs davon hat der Kunde schon beantwortet: seine Branche, wie
     * viele Seiten, welche Funktionen, ob Texte, Fotos und Logo da sind.
     * Ihn dasselbe ein zweites Mal zu fragen, kurz nachdem er bezahlt hat,
     * ist der schnellste Weg, einen zufriedenen Kunden zu aergern -- und der
     * haeufigste Grund, warum ein Fragebogen liegen bleibt.
     *
     * Also steht es schon drin, in seiner Sprache und in seinen Worten, und
     * er kann es aendern. Was er nicht gesagt hat, bleibt leer.
     *
     * @return array<string,string> Feldname => Vorbelegung
     */
    public static function alsFragebogen(int $kundeId): array
    {
        try {
            $b = Db::one(
                "SELECT * FROM bedarf
                  WHERE customer_id = ? AND status <> 'offen'
                  ORDER BY id DESC LIMIT 1", [$kundeId]);
        } catch (Throwable $e) {
            // Eine Vorbelegung ist Beiwerk. Faellt sie aus, ist der Fragebogen
            // leer -- aber er ist da.
            return [];
        }
        if (!$b) { return []; }

        /* Uebersetzt wird hier nichts mehr: Gespeichert werden Schluessel,
           und die uebersetzt der Fragebogen selbst in die Sprache, in der
           der Kunde gerade davorsitzt. Vorher stand der Satz fest, sobald
           er einmal geschrieben war -- und blieb italienisch, auch wenn der
           Kunde spaeter auf Deutsch weitermachte. */
        $a = self::antworten($b);

        $aus = [];

        /* DIE BRANCHE ALS SCHLUESSEL, NICHT ALS WORT
           ------------------------------------------------------------------
           Frueher stand hier der ausgeschriebene Satz ("Gastronomie"). Seit
           der Fragebogen eine Liste hat, waere das ein Wert, den die Liste
           nicht kennt -- das Feld bliebe leer, und der Kunde muesste noch
           einmal ankreuzen, was er schon angeklickt hat. Die Namen der
           beiden Listen sind nicht dieselben, also wird uebersetzt. */
        $brancheZu = [
            'gastro'    => 'gastronomie',
            'handel'    => 'laden',
            'handwerk'  => 'handwerk',
            'tourismus' => 'beherbergung',
        ];
        $branche = (string) ($a['branche'] ?? '');
        if (isset($brancheZu[$branche])) { $aus['branche'] = $brancheZu[$branche]; }

        /* SEITEN, SPRACHEN UND FUNKTIONEN STEHEN HIER NICHT MEHR
           ------------------------------------------------------------------
           Frueher wurden sie als Fliesstext vorbelegt: "Wenige Seiten (3-5) ·
           Zwei Sprachen". Das war gut gemeint und im Ergebnis schaedlich --
           dieselbe Auskunft stand danach an zwei Stellen, in zwei Formen, und
           welche galt, wusste niemand.

           Im Fragebogen stehen dafuer jetzt zwei Zaehler und eine Hakenliste,
           und die werden nicht vom Bedarf gefuellt, sondern vom angenommenen
           Angebot (siehe Umfang.php). Das ist die staerkere Quelle: Der Bedarf
           ist, was der Kunde einmal angeklickt hat; das Angebot ist, worauf
           sich beide geeinigt haben. */

        /* Material: Was da ist, steht als solches drin. Was fehlt, steht
           ebenfalls drin -- eine leere Zeile hiesse "nicht gefragt", und
           gefragt wurde sehr wohl. */
        /* MATERIAL: NUR WAS ER GESAGT HAT, NICHT WAS ICH VERMUTE
           ------------------------------------------------------------------
           Im Konfigurator hat er angeklickt, was da ist. Das steht hier als
           "haben wir". Was er NICHT angeklickt hat, bleibt offen -- ob es
           noch kommt oder ob ich es machen soll, hat er nie gesagt, und eine
           geratene Antwort in einem vorbelegten Feld wird bestaetigt statt
           korrigiert. Genau daran ist die alte Vorbelegung gescheitert:
           "Fehlt noch." stand im Feld, und niemand hat je danach gefragt,
           wer es besorgt. */
        $material = (array) ($a['material'] ?? []);
        $stand = [];
        foreach (['logo' => 'logo', 'fotos' => 'betrieb', 'texte' => 'texte'] as $quelle => $zeile) {
            if (in_array($quelle, $material, true)) { $stand[] = $zeile . ':haben'; }
        }
        if ($stand) { $aus['material'] = implode(',', $stand); }

        /* Beim Logo bleibt die eine Frage offen, die zaehlt: Vektor oder
           Bild. Nur wenn gar keines da ist, steht die Antwort schon fest. */
        if (!in_array('logo', $material, true)) { $aus['logo'] = 'neu'; }
        if (in_array('texte', $material, true)) { $aus['texte'] = 'selbst'; }

        /* Die Torfrage zur alten Seite. Sie entscheidet im Fragebogen
           darueber, ob zwei weitere Fragen ueberhaupt erscheinen -- vorher
           standen sie immer da, auch bei Kunden, die noch nie eine hatten. */
        $bestand = (string) ($a['bestand'] ?? '');
        if ($bestand === 'neu')      { $aus['altseite'] = 'nein'; }
        if ($bestand === 'erneuern' || $bestand === 'ueberarb') { $aus['altseite'] = 'ja'; }

        /* Firmenname und Ort stehen beim Kunden. Sie hier noch einmal zu
           fragen, ist der billigste Weg, jemanden zu verlieren, der gerade
           bezahlt hat. */
        try {
            $k = Db::one('SELECT company, city FROM customers WHERE id = ?', [$kundeId]);
            if ($k) {
                if (trim((string) ($k['company'] ?? '')) !== '') { $aus['firmenname'] = (string) $k['company']; }
                if (trim((string) ($k['city'] ?? '')) !== '')    { $aus['ort'] = (string) $k['city']; }
            }
        } catch (Throwable $e) { /* dann tippt er es eben selbst */ }

        return array_filter($aus, static fn($v) => trim((string) $v) !== '');
    }

    /* ==================================================================
       Aufraeumen
       ================================================================== */

    /**
     * Einen Bedarf loeschen.
     *
     * Haengt ein Angebot daran, passiert nichts: Das Angebot ist die Zusage
     * und muss sagen koennen, woraus es entstanden ist. Wer den Bedarf
     * trotzdem loswerden will, loescht erst das Angebot -- das ist eine
     * Entscheidung, die niemand nebenbei treffen soll.
     *
     * @return bool Ob geloescht wurde.
     */
    public static function loeschen(int $id): bool
    {
        $angebote = (int) Db::wert('SELECT COUNT(*) FROM angebote WHERE bedarf_id = ?', [$id], 0);
        if ($angebote > 0) { return false; }

        return (bool) Db::transaktion(static function () use ($id): bool {
            // Eine Empfehlung gehoert dem, der empfohlen hat -- sie bleibt
            // stehen und verliert nur den Verweis.
            try {
                Db::run('UPDATE empfehlungen SET bedarf_id = NULL WHERE bedarf_id = ?', [$id]);
            } catch (Throwable $e) { /* die Tabelle kann es noch nicht geben */ }
            return Db::run('DELETE FROM bedarf WHERE id = ?', [$id])->rowCount() > 0;
        });
    }

    /**
     * Aufraeumen.
     *
     * WARUM DAS NOETIG IST
     *
     * Eine Zeile entsteht schon beim Oeffnen des Konfigurators, nicht erst
     * beim Absenden. Anders ginge es nicht -- der Schluessel in der Adresse
     * ist das, was den Kunden zurueckfinden laesst. Die Folge: Nach einem Tag
     * stehen dort dreissig Zeilen "Schritt 1 von 5" ohne eine einzige
     * Antwort, und der eine echte Bedarf geht darin unter.
     *
     * WAS WEGGERAEUMT WIRD
     *
     * Ohne $alles: was nichts traegt und niemanden aussperrt -- angefangene
     * ohne jede Antwort, angefangene mit Antworten die seit ueber einem Tag
     * still sind, und verwaiste, deren Kunde geloescht wurde. Ein Kunde, der
     * gerade mittendrin ist, behaelt seinen Weg.
     *
     * Mit $alles: alles ausser dem, woran ein Angebot haengt.
     *
     * @return int Wie viele Zeilen weg sind.
     */
    public static function aufraeumen(bool $alles = false): int
    {
        $wo = $alles
            ? '1 = 1'
            /* Klammern ausgeschrieben: UND bindet staerker als ODER, und ein
               Loeschbefehl ist der falsche Ort fuer stille Vorfahrtsregeln. */
            : "(
                 b.status = 'offen' AND (
                       b.antworten IS NULL
                    OR b.antworten IN ('', '[]', '{}')
                    OR b.updated_at < (NOW() - INTERVAL 1 DAY)
                 )
               )
               OR (b.customer_id IS NOT NULL AND c.id IS NULL)";

        $ids = array_column((array) Db::all(
            "SELECT b.id FROM bedarf b
               LEFT JOIN customers c ON c.id = b.customer_id
               LEFT JOIN angebote a ON a.bedarf_id = b.id
              WHERE a.id IS NULL AND ($wo)
              LIMIT 5000"), 'id');

        $weg = 0;
        foreach ($ids as $id) { if (self::loeschen((int) $id)) { $weg++; } }
        return $weg;
    }

    public static function zusammenfassung(array $antworten, string $sprache, ?array $spanne = null, int $monatlich = 0): string
    {
        $zeilen = [];
        foreach (Baukasten::FRAGEN as $schluessel => $frage) {
            $wert = $antworten[$schluessel] ?? null;
            if ($wert === null || $wert === '' || $wert === []) { continue; }

            $frageText = Texte::h($frage['frage'], $sprache);
            $werte = is_array($wert) ? $wert : [$wert];
            $lesbar = [];
            foreach ($werte as $w) {
                $o = $frage['optionen'][$w] ?? null;
                if ($o) { $lesbar[] = Texte::h($o, $sprache); }
            }
            if ($lesbar) { $zeilen[] = $frageText . ' ' . implode(', ', $lesbar); }
        }

        if ($spanne) {
            $zeilen[] = '';
            $zeilen[] = strtr(Texte::h(Texte::BEDARF['fasseSpanne'], $sprache), [
                '{von}' => self::geld((int) $spanne['von_cents'], $sprache),
                '{bis}' => self::geld((int) $spanne['bis_cents'], $sprache),
            ]);
            if ($monatlich > 0) {
                $zeilen[] = strtr(Texte::h(Texte::BEDARF['fasseBetreuung'], $sprache), [
                    '{betrag}' => self::geld($monatlich, $sprache),
                ]);
            }
        }
        return implode("\n", $zeilen);
    }

    /**
     * Ein Betrag so, wie er im jeweiligen Land geschrieben wird.
     *
     * Vorher stand hier ein festes "1.234 EUR" fuer alle drei Sprachen. Auf
     * Englisch schreibt niemand so, und "EUR" statt des Zeichens liest sich
     * wie ein Kontoauszug.
     */
    private static function geld(int $cents, string $sprache): string
    {
        $euro = (int) round($cents / 100);
        if ($sprache === 'en') { return '€' . number_format($euro, 0, '.', ','); }
        return number_format($euro, 0, ',', '.') . ' €';
    }

    /**
     * Die fertige Preisnachricht an den Kunden.
     *
     * WARUM DAS HIER ENTSTEHT UND NICHT IN UWES KOPF
     *
     * Bisher stand in der Verwaltung nur die Spanne. Wer daraus eine Zahl
     * machen wollte, rechnete von Hand — und wer von Hand rechnet, rechnet
     * irgendwann anders als das Angebot, das die Anwendung spaeter selbst
     * erzeugt. Dann steht in der Nachricht eine Zahl und im Angebot eine
     * andere, und erklaeren muss es der, der beides geschrieben hat.
     *
     * Deshalb kommt die Zahl aus Baukasten::vorschlag(), also aus demselben
     * Rechenweg wie das Angebot, und der Text steht in der Sprache des
     * Kunden fertig da.
     *
     * @return array{betreff:string,text:string}
     */
    public static function preisnachricht(array $bedarf, array $vorschlag, array $katalog): array
    {
        require_once __DIR__ . '/Vorlage.php';

        $sprache = (string) ($bedarf['sprache'] ?? 'it');
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $name    = trim((string) ($bedarf['name'] ?? ''));
        $vorname = $name !== '' ? explode(' ', $name)[0] : '';

        $teile = [];
        $teile[] = strtr(Texte::h(Texte::BEDARF['preisEinleitung'], $sprache), [
            '{preis}' => self::geld((int) $vorschlag['summe_cents'], $sprache),
        ]);

        /* Die Posten mit ihren Einzelpreisen. Wer eine Summe ohne Aufstellung
           bekommt, fragt zurueck, wofuer sie ist — und dann schreibt Uwe die
           Aufstellung doch noch, nur einen Tag spaeter. */
        $zeilen = [];
        foreach ($vorschlag['positionen'] as $p) {
            if ((int) $p['monatlich']) { continue; }
            $b = $katalog[$p['slug']] ?? null;
            if (!$b) { continue; }
            $menge = (int) $p['menge'] > 1 ? ' (' . (int) $p['menge'] . ')' : '';
            $zeilen[] = '· ' . Baukasten::name($b, $sprache) . $menge
                      . ' — ' . self::geld((int) $p['summe_cents'], $sprache);
        }
        if ($zeilen) {
            $teile[] = Texte::h(Texte::BEDARF['preisInhalt'], $sprache) . "\n" . implode("\n", $zeilen);
        }

        if ((int) $vorschlag['monatlich_cents'] > 0) {
            $teile[] = strtr(Texte::h(Texte::BEDARF['preisBetreuung'], $sprache), [
                '{betrag}' => self::geld((int) $vorschlag['monatlich_cents'], $sprache),
            ]);
        }

        $teile[] = Texte::h(Texte::BEDARF['preisSchluss'], $sprache);

        return [
            'betreff' => Texte::h(Texte::BEDARF['preisBetreff'], $sprache),
            'text'    => Vorlage::rahmen($sprache, $vorname, implode("\n\n", $teile)),
        ];
    }

    /** Die Sprachen, die der Auftrag umfasst — in der Reihenfolge, in der gebaut wird. */
    private const SPRACHNAMEN = ['Italienisch', 'Deutsch', 'Englisch'];

    /**
     * Ein fertiges Briefing zum Kopieren — fuer Claude, zum Bauen der Seite.
     *
     * WARUM DAS HIER ENTSTEHT
     *
     * Im Konfigurator steht bereits alles, was ein Briefing braucht: Zweck,
     * Umfang, Sprachen, Funktionen, vorhandenes Material, Bestand, Termin,
     * Branche. Bisher las Uwe das ab und tippte es neu — und beim Abtippen
     * geht zuverlaessig genau das verloren, was der Kunde NICHT angekreuzt
     * hat.
     *
     * DER WICHTIGSTE ABSCHNITT IST DER MIT DEM "NICHT"
     *
     * Ein Sprachmodell, dem man ein Restaurant beschreibt, baut ungefragt
     * einen Tischreservierungs-Kalender dazu. Das ist nicht bezahlt, es
     * kostet Zeit, und wieder wegnehmen muss man es auch. Deshalb steht hier
     * ausdruecklich, was nicht dazugehoert — abgeleitet aus dem, was der
     * Kunde offen gelassen hat.
     *
     * Der Leistungsumfang kommt aus denselben Bausteinen, die den Preis
     * ergeben haben. Was gebaut wird, ist damit genau das, was bezahlt wurde.
     */
    public static function bauprompt(array $bedarf, array $antworten, array $vorschlag, array $katalog): string
    {
        $F = Baukasten::FRAGEN;
        $wahl = static function (string $frage) use ($antworten): array {
            $w = $antworten[$frage] ?? null;
            if ($w === null || $w === '' || $w === []) { return []; }
            return array_map('strval', is_array($w) ? $w : [$w]);
        };
        $wort = static function (string $frage, string $schluessel) use ($F): string {
            $o = $F[$frage]['optionen'][$schluessel] ?? null;
            return $o ? Texte::h($o, 'de') : $schluessel;
        };

        $firma = trim((string) ($bedarf['firma'] ?? ''));
        $name  = trim((string) ($bedarf['name'] ?? ''));
        $wer   = $firma !== '' ? $firma : $name;

        $z = [];
        $z[] = 'Nutze den Skill web-design-studio.';
        $z[] = '';
        $z[] = 'AUFTRAG';
        $z[] = 'Baue die Website für ' . ($wer !== '' ? $wer : 'einen Kunden') . '.';
        foreach ($wahl('branche') as $b) { $z[] = 'Branche: ' . $wort('branche', $b) . '.'; }
        $z[] = 'Auftraggeber sitzt in Sizilien, Provinz Agrigent; die Kundschaft ist örtlich.';
        $z[] = '';

        $z[] = 'WAS DIE SEITE LEISTEN MUSS';
        $zweck = $wahl('zweck');
        foreach ($zweck as $w) { $z[] = '- ' . $wort('zweck', $w); }
        if (!$zweck) { $z[] = '- (nicht angegeben — vor dem Bauen nachfragen)'; }
        $z[] = '';

        $z[] = 'UMFANG';
        foreach ($wahl('umfang') as $w) { $z[] = '- Seitenzahl: ' . $wort('umfang', $w); }
        $anzSprachen = max(1, min(3, (int) ($antworten['sprachen'] ?? 1)));
        $z[] = '- Sprachen: ' . implode(', ', array_slice(self::SPRACHNAMEN, 0, $anzSprachen))
             . ($anzSprachen > 1 ? ' — je eigene Adresse, nicht nur ein Umschalter im Browser' : '');
        $z[] = '';

        /* Der Leistungsumfang, wie er bezahlt wurde. */
        $z[] = 'LEISTUNGSUMFANG — das ist kalkuliert und bezahlt';
        foreach ($vorschlag['positionen'] as $pos) {
            if ((int) $pos['monatlich']) { continue; }
            $bs = $katalog[$pos['slug']] ?? null;
            if (!$bs) { continue; }
            $menge = (int) $pos['menge'] > 1 ? ' ×' . (int) $pos['menge'] : '';
            $text  = trim(Baukasten::text($bs, 'de'));
            $z[] = '- ' . Baukasten::name($bs, 'de') . $menge . ($text !== '' ? ' — ' . $text : '');
        }
        $z[] = '';

        /* Und ausdruecklich, was nicht. */
        $z[] = 'WAS AUSDRÜCKLICH NICHT DAZUGEHÖRT';
        $z[] = 'Danach wurde nicht gefragt. Bau es nicht ein, schlag es nicht vor, lass auch keinen Platz dafür:';
        $nicht = [];
        foreach (['speisekarte' => 'Speisekarte oder Angebotsliste',
                  'termine'     => 'Terminvereinbarung oder Tischreservierung',
                  'buchung'     => 'Buchungssystem für Zimmer oder Ferienwohnungen',
                  'shop'        => 'Onlineshop, Warenkorb, Zahlungsabwicklung'] as $slug => $wieHeisst) {
            if (!in_array($slug, $zweck, true)) { $nicht[] = $wieHeisst; }
        }
        if ($anzSprachen < 3) { $nicht[] = 'weitere Sprachen über die ' . $anzSprachen . ' genannten hinaus'; }
        if (!in_array('logo', $wahl('material'), true)) {
            $nicht[] = 'kein neues Logo entwerfen (steht separat zur Anfrage, ist nicht beauftragt)';
        }
        foreach ($nicht as $n) { $z[] = '- ' . $n; }
        $z[] = '';

        $z[] = 'MATERIAL';
        $material = $wahl('material');
        foreach (['texte' => 'Texte', 'fotos' => 'Fotos', 'logo' => 'Logo'] as $slug => $wieHeisst) {
            $z[] = '- ' . $wieHeisst . ': ' . (in_array($slug, $material, true)
                ? 'liegt vom Kunden vor'
                : 'fehlt — ich liefere es, arbeite bis dahin mit klar markiertem Platzhalter in richtiger Länge und Tonlage (kein Blindtext)');
        }
        $z[] = '';

        /* Eine Überschrift ohne Zeile darunter ist schlechter als keine
           Überschrift: Am 13.09.2026 an einem echten Durchlauf gesehen, der
           nur die erste Frage beantwortet hatte — im Briefing standen
           "BESTAND" und "TERMIN" nackt da. Wer das liest, hält es für einen
           Fehler des Briefings und rät den Rest zusammen. Deshalb dieselbe
           Auffangzeile wie oben bei "WAS DIE SEITE LEISTEN MUSS": Es steht
           ausdrücklich da, dass nichts gesagt wurde. */
        $z[] = 'BESTAND';
        $bestand = $wahl('bestand');
        foreach ($bestand as $w) {
            $z[] = '- ' . $wort('bestand', $w);
            if (in_array($w, ['erneuern', 'ueberarb'], true)) {
                $z[] = '- Inhalte werden von der alten Seite übernommen. Alte Adressen müssen weiter funktionieren,'
                     . ' sonst fällt die Seite aus dem Google-Index. Bestehende Titel und Beschreibungen vorher sichern.';
            }
        }
        if (!$bestand) {
            $z[] = '- (nicht angegeben — vor dem Bauen nachfragen, ob es eine alte Seite gibt.'
                 . ' Nicht annehmen, dass neu gebaut wird: eine übersehene alte Seite kostet den Google-Index.)';
        }
        $z[] = '';

        $z[] = 'TERMIN';
        $zeit = $wahl('zeit');
        foreach ($zeit as $w) { $z[] = '- ' . $wort('zeit', $w); }
        if (!$zeit) { $z[] = '- (nicht angegeben — es ist kein Termin zugesagt. Keine Eile erfinden.)'; }
        $z[] = '';

        $z[] = 'KONTAKTDATEN FÜR DIE SEITE';
        if ($firma !== '') { $z[] = '- Betrieb: ' . $firma; }
        if ($name !== '')  { $z[] = '- Ansprechpartner: ' . $name; }
        if (trim((string) ($bedarf['email'] ?? '')) !== '')   { $z[] = '- E-Mail: ' . trim((string) $bedarf['email']); }
        if (trim((string) ($bedarf['telefon'] ?? '')) !== '') { $z[] = '- Telefon: ' . trim((string) $bedarf['telefon']); }
        $z[] = '- Anschrift und Öffnungszeiten fehlen hier noch — erfrage sie bei mir, bevor du das Impressum baust.';
        $z[] = '';

        $z[] = 'WIE ICH ARBEITEN MÖCHTE';
        $z[] = '1. Zeig mir zuerst die Design-DNA und drei unterschiedliche Richtungen, bevor du Code schreibst.';
        $z[] = '2. Erst wenn ich eine gewählt habe: erster Bildschirm fertig bauen, ansehen, dann der Rest.';
        $z[] = '3. Mobil zuerst. Die Kundschaft kommt hier fast nur übers Telefon.';
        $z[] = '4. Keine gekauften Vorlagen und keine kostenpflichtigen Erweiterungen — das wird sonst jedes Jahr'
             . ' wieder fällig und gehört mir dann nicht mehr.';
        $z[] = '5. Texte in Bildern vermeiden: nicht übersetzbar, nicht auffindbar, auf dem Handy nicht lesbar.';
        $z[] = '6. Am Ende: Ladezeit, Kontrast und Tastaturbedienung prüfen und mir die Messwerte nennen.';

        return implode("\n", $z);
    }
}
