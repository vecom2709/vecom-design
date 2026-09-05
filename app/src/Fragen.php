<?php
declare(strict_types=1);

/**
 * Alles, was der Fragebogen an Auswahlen, Vorschlaegen und Lesbarkeit
 * braucht — getrennt von Onboarding, das den Ablauf fuehrt.
 *
 * Drei Aufgaben:
 *
 *  1. WORTE   Aus "gastronomie" wird "Gastronomie — Restaurant, Bar,
 *             Pizzeria". Gespeichert wird der Schluessel, gezeigt wird der
 *             Satz. Sonst stuende im Briefing eine Vokabel, die niemand
 *             uebersetzt, und in drei Monaten wuesste ich nicht mehr, was
 *             "boden" heissen sollte.
 *
 *  2. VORSCHLAG  Seitennamen und Suchwoerter kommen vorbelegt aus Branche
 *             und Ort. Korrigieren koennen alle, erfinden fast niemand:
 *             Auf die Frage "mit welchen Woertern sollen Leute euch
 *             finden" kam bisher "gute Pizza". Auf einen Vorschlag kommt
 *             "pizzeria agrigento, pizza al taglio agrigento".
 *
 *  3. LUECKEN Nach dem Absenden pruefe ich nicht 40 Antworten durch,
 *             sondern lese eine Liste: was fehlt, was sich widerspricht.
 */
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Onboarding.php';

final class Fragen
{
    /* ---------- 1: Schluessel in Worte ---------------------------------- */

    /** Ein gespeicherter Wert, lesbar in der Sprache des Kunden. */
    public static function worte(string $feldName, string $wert, string $sprache): string
    {
        $feld = self::feld($feldName);
        if ($feld === null || $wert === '') { return $wert; }
        $art = (string) ($feld['art'] ?? 'text');

        if ($art === 'eins') {
            $o = $feld['optionen'][$wert] ?? null;
            return $o ? Texte::h($o, $sprache) : $wert;
        }

        if ($art === 'mehr') {
            $aus = [];
            foreach (explode(',', $wert) as $k) {
                $k = trim($k);
                if ($k === '') { continue; }
                $o = $feld['optionen'][$k] ?? null;
                $aus[] = $o ? Texte::h($o, $sprache) : $k;
            }
            return implode(' · ', $aus);
        }

        if ($art === 'stand') {
            $aus = [];
            foreach (Onboarding::standWerte($wert) as $zeile => $zustand) {
                $name = isset($feld['zeilen'][$zeile]) ? Texte::h($feld['zeilen'][$zeile], $sprache) : $zeile;
                $aus[] = $name . ': ' . Texte::h(self::ZUSTANDWORT[$zustand] ?? [], $sprache, $zustand);
            }
            return implode(' · ', $aus);
        }

        return $wert;
    }

    /* Kurz, weil vier Zustaende nebeneinander stehen muessen. "Brauchen wir
       nicht" sprengte die Zeile und der vierte Knopf rutschte allein in die
       naechste -- als waere er etwas anderes als die drei davor. */
    public const ZUSTANDWORT = [
        'haben' => ['it' => 'c’è già',   'de' => 'haben wir',   'en' => 'have it'],
        'kommt' => ['it' => 'arriva',    'de' => 'kommt noch',  'en' => 'coming'],
        'du'    => ['it' => 'lo fai tu', 'de' => 'machst du',   'en' => 'you do it'],
        'nein'  => ['it' => 'non serve', 'de' => 'nicht nötig', 'en' => 'not needed'],
    ];

    /** Die Definition eines Feldes, ueber alle Abschnitte gesucht. */
    public static function feld(string $name): ?array
    {
        foreach (Texte::FRAGEBOGEN as $inhalt) {
            if (isset($inhalt['felder'][$name])) { return $inhalt['felder'][$name]; }
        }
        return null;
    }

    /**
     * Soll ein Feld ueberhaupt gezeigt werden?
     *
     * Die Fragen zur alten Seite standen frueher immer da — auch bei Kunden,
     * die noch nie eine hatten. Zwei leere Kaesten, die sagen: Hier ist
     * etwas, das du nicht beantwortest.
     */
    public static function zeigen(array $feld, array $daten): bool
    {
        if (!isset($feld['wenn'])) { return true; }
        $ist = (string) ($daten[$feld['wenn']['feld']] ?? '');
        return in_array($ist, (array) $feld['wenn']['ist'], true);
    }

    /* ---------- 2: Vorschlaege ------------------------------------------ */

    /* Seitennamen je Branche. Fuenf Stueck, in der Reihenfolge, in der sie
       im Menue stehen wuerden — der Kunde streicht, was er nicht braucht. */
    private const SEITEN = [
        'gastronomie'  => ['it' => 'Home · Menù · Chi siamo · Galleria · Contatti', 'de' => 'Start · Speisekarte · Über uns · Galerie · Kontakt', 'en' => 'Home · Menu · About us · Gallery · Contact'],
        'beherbergung' => ['it' => 'Home · Camere · Dintorni · Prezzi · Contatti', 'de' => 'Start · Zimmer · Umgebung · Preise · Kontakt', 'en' => 'Home · Rooms · The area · Rates · Contact'],
        'handwerk'     => ['it' => 'Home · Servizi · Lavori svolti · Chi siamo · Contatti', 'de' => 'Start · Leistungen · Referenzen · Über uns · Kontakt', 'en' => 'Home · Services · Past work · About us · Contact'],
        'schoenheit'   => ['it' => 'Home · Trattamenti · Prezzi · Il team · Contatti', 'de' => 'Start · Behandlungen · Preise · Team · Kontakt', 'en' => 'Home · Treatments · Prices · The team · Contact'],
        'wein'         => ['it' => 'Home · Prodotti · L’azienda · Visite e degustazioni · Contatti', 'de' => 'Start · Produkte · Der Betrieb · Besuch und Verkostung · Kontakt', 'en' => 'Home · Products · The estate · Visits and tastings · Contact'],
        'laden'        => ['it' => 'Home · Assortimento · Chi siamo · Come arrivare · Contatti', 'de' => 'Start · Sortiment · Über uns · Anfahrt · Kontakt', 'en' => 'Home · Range · About us · Finding us · Contact'],
        'praxis'       => ['it' => 'Home · Prestazioni · Chi sono · Orari · Contatti', 'de' => 'Start · Leistungen · Über mich · Sprechzeiten · Kontakt', 'en' => 'Home · Services · About me · Hours · Contact'],
        'immobilien'   => ['it' => 'Home · Immobili · Vendere con noi · Chi siamo · Contatti', 'de' => 'Start · Angebote · Verkaufen mit uns · Über uns · Kontakt', 'en' => 'Home · Listings · Selling with us · About us · Contact'],
        'dienst'       => ['it' => 'Home · Servizi · Referenze · Chi siamo · Contatti', 'de' => 'Start · Leistungen · Referenzen · Über uns · Kontakt', 'en' => 'Home · Services · References · About us · Contact'],
        'transport'    => ['it' => 'Home · Servizi · Mezzi · Chi siamo · Contatti', 'de' => 'Start · Leistungen · Fuhrpark · Über uns · Kontakt', 'en' => 'Home · Services · Fleet · About us · Contact'],
        'anders'       => ['it' => 'Home · Servizi · Chi siamo · Contatti', 'de' => 'Start · Leistungen · Über uns · Kontakt', 'en' => 'Home · Services · About us · Contact'],
    ];

    /* Zwei Suchwoerter je Branche, so wie ein Kunde sie eintippt — klein
       geschrieben, ohne Firmennamen, weil danach niemand sucht, der die
       Firma noch nicht kennt. Der Ort kommt dahinter. */
    private const SUCHE = [
        'gastronomie'  => ['it' => ['ristorante', 'pizzeria'],        'de' => ['restaurant', 'pizzeria'],       'en' => ['restaurant', 'pizzeria']],
        'beherbergung' => ['it' => ['b&b', 'dove dormire'],           'de' => ['hotel', 'ferienwohnung'],       'en' => ['hotel', 'bed and breakfast']],
        'handwerk'     => ['it' => ['idraulico', 'elettricista'],     'de' => ['handwerker', 'installateur'],   'en' => ['plumber', 'electrician']],
        'schoenheit'   => ['it' => ['parrucchiere', 'centro estetico'], 'de' => ['friseur', 'kosmetikstudio'],  'en' => ['hairdresser', 'beauty salon']],
        'wein'         => ['it' => ['cantina', 'olio extravergine'],  'de' => ['weingut', 'olivenöl'],          'en' => ['winery', 'olive oil']],
        'laden'        => ['it' => ['negozio', 'dove comprare'],      'de' => ['laden', 'geschäft'],            'en' => ['shop', 'store']],
        'praxis'       => ['it' => ['studio', 'commercialista'],      'de' => ['praxis', 'kanzlei'],            'en' => ['practice', 'office']],
        'immobilien'   => ['it' => ['agenzia immobiliare', 'case in vendita'], 'de' => ['immobilienmakler', 'haus kaufen'], 'en' => ['estate agent', 'houses for sale']],
        'dienst'       => ['it' => ['servizi alle imprese', 'consulenza'], 'de' => ['dienstleister', 'beratung'], 'en' => ['business services', 'consulting']],
        'transport'    => ['it' => ['trasporti', 'traslochi'],        'de' => ['transport', 'umzug'],           'en' => ['transport', 'removals']],
    ];

    /**
     * Vorbelegung fuer ein Feld. Leer, wenn nichts Vernuenftiges herauskommt
     * — ein falscher Vorschlag ist schlechter als keiner, weil er bestaetigt
     * wird, statt korrigiert zu werden.
     */
    public static function vorschlag(string $welcher, array $daten, string $sprache): string
    {
        $branche = (string) ($daten['branche'] ?? '');
        $ort     = trim((string) ($daten['ort'] ?? ''));

        if ($welcher === 'seiten') {
            $satz = self::SEITEN[$branche] ?? null;
            return $satz ? Texte::h($satz, $sprache) : '';
        }

        if ($welcher === 'suchwoerter') {
            $paar = self::SUCHE[$branche][$sprache] ?? null;
            if (!$paar || $ort === '') { return ''; }
            $ortKlein = mb_strtolower($ort);
            return $paar[0] . ' ' . $ortKlein . "\n"
                 . $paar[1] . ' ' . $ortKlein . "\n"
                 . $paar[0] . ' ' . self::NAEHE[$sprache];
        }

        return '';
    }

    private const NAEHE = ['it' => 'vicino a me', 'de' => 'in der nähe', 'en' => 'near me'];

    /* ---------- 3: Luecken und Widersprueche ---------------------------- */

    /**
     * Was fehlt oder sich widerspricht — in meiner Sprache, nicht in der des
     * Kunden. Sie steht im Briefing ganz oben, damit ich in fuenf Sekunden
     * sehe, was ich nachfragen muss, statt vierzig Antworten zu lesen.
     *
     * @param  array $daten   die Antworten
     * @param  array $bezahlt was beauftragt ist (aus Umfang::bezahlt), oder null
     * @return list<string>
     */
    public static function luecken(array $daten, ?array $bezahlt = null): array
    {
        $aus = [];
        $hat = static fn(string $k): bool => trim((string) ($daten[$k] ?? '')) !== '';
        $ist = static fn(string $k, string $w): bool => (string) ($daten[$k] ?? '') === $w;

        /* Was ohne Nachfrage nicht geht. */
        $pflicht = [
            'branche'    => 'Branche fehlt — davon hängen Ambitionsstufe und Seitenvorschlag ab',
            'ort'        => 'Ort fehlt — ohne ihn keine Suchwörter, keine Karte, kein Impressum',
            'ziel1'      => 'Ziel der Seite fehlt — ohne das ist der erste Bildschirm geraten',
            'impressum'  => 'Impressumsangaben fehlen — die Seite darf so nicht online',
            'telefon'    => 'Telefonnummer für die Seite fehlt',
        ];
        foreach ($pflicht as $feld => $satz) {
            if (!$hat($feld)) { $aus[] = $satz; }
        }

        /* Material, das ich brauche und das noch niemand hat. */
        $stand = $hat('material') ? Onboarding::standWerte((string) $daten['material']) : [];
        if (($stand['logo'] ?? '') === 'nein' && !$ist('logo', 'neu')) {
            $aus[] = 'Logo als „brauchen wir nicht“ markiert — aber kein neues bestellt';
        }
        if ($ist('logo', 'bild')) {
            $aus[] = 'Logo nur als Bild vorhanden — für Druck und scharfe Darstellung fehlt die Vektordatei';
        }
        if ($ist('logo', 'weissnicht')) {
            $aus[] = 'Kunde weiß nicht, welche Logodatei er hat — nachfragen und ansehen';
        }
        foreach (['betrieb' => 'Fotos vom Betrieb', 'produkt' => 'Produktfotos'] as $z => $wort) {
            if (($stand[$z] ?? '') === 'du') { $aus[] = $wort . ': soll ich machen — ist das im Angebot?'; }
        }

        /* Rechte. Nicht schön, aber billiger als eine Abmahnung. */
        if ($ist('bildrechte', 'fotograf') || $ist('bildrechte', 'unsicher')) {
            $aus[] = 'Bildrechte ungeklärt — vor Veröffentlichung schriftlich bestätigen lassen';
        }
        if ($ist('bildrechte', 'personen')) {
            $aus[] = 'Erkennbare Personen auf den Fotos — Einwilligung einholen';
        }

        /* Domain und Übergabe. */
        if ($ist('domain', 'fremd')) {
            $aus[] = 'Domain liegt bei einem Dritten — Zugang oder KK-Antrag klären, bevor irgendetwas umgestellt wird';
        }
        if ($ist('domain', 'weissnicht')) {
            $aus[] = 'Domain unklar — selbst nachsehen (whois), der Kunde weiß es nicht';
        }
        if ($ist('domain', 'neu')) {
            $wuensche = array_filter([
                trim((string) ($daten['wunsch1'] ?? '')),
                trim((string) ($daten['wunsch2'] ?? '')),
                trim((string) ($daten['wunsch3'] ?? '')),
            ]);
            if (!$wuensche) {
                $aus[] = 'Neue Domain gewünscht, aber keine Wunschadresse genannt';
            } elseif (count($wuensche) === 1) {
                /* Eine einzige Adresse ist meist die eine, die schon weg ist.
                   Lieber jetzt nachfragen als nach der Absage. */
                $aus[] = 'Nur eine Wunschdomain genannt (' . $wuensche[0]
                       . ') — nach Ausweichnamen fragen, bevor registriert wird';
            }
        }

        /* Widersprüche zwischen Wunsch und Auftrag. */
        if ($bezahlt !== null) {
            $slugs = (array) ($bezahlt['slugs'] ?? []);
            if ($ist('ziel1', 'verkauf') && !isset($slugs['shop'])) {
                $aus[] = 'Wichtigstes Ziel ist Online-Verkauf, aber kein Shop beauftragt';
            }
            if ($ist('ziel1', 'buchungen') && !isset($slugs['buchung']) && !isset($slugs['termin'])) {
                $aus[] = 'Wichtigstes Ziel sind Buchungen, aber keine Buchungsfunktion beauftragt';
            }
            $seitenSoll = (int) ($bezahlt['seiten'] ?? 0);
            $seitenIst  = (int) ($daten['seiten_zahl'] ?? 0);
            if ($seitenSoll > 0 && $seitenIst > $seitenSoll) {
                $aus[] = 'Kunde will ' . $seitenIst . ' Seiten, beauftragt sind ' . $seitenSoll;
            }
        }
        if ($ist('texte', 'du')) {
            $aus[] = 'Texte soll ich schreiben — prüfen, ob das im Preis steht';
        }

        /* Termin und Betreuung — beides Anlass, von selbst zu schreiben. */
        if (($ist('termin', 'saison') || $ist('termin', 'anlass')) && !$hat('termin__frei')) {
            $aus[] = 'Fester Termin genannt, aber kein Datum dazu';
        }
        if ($ist('pflege', 'du')) {
            $aus[] = 'Kunde möchte die Pflege abgeben — Betreuung anbieten';
        }
        if ($ist('pflege', 'offen')) {
            $aus[] = 'Pflege noch offen — beim Abschlussgespräch ansprechen';
        }

        /* Sprachen: die Zahl sagt, was bezahlt ist, die Liste, welche. */
        $wieViele = (int) ($daten['sprachen_zahl'] ?? 0);
        $welche   = $hat('sprachen_welche') ? count(array_filter(explode(',', (string) $daten['sprachen_welche']))) : 0;
        if ($wieViele > 0 && $welche > 0 && $welche !== $wieViele) {
            $aus[] = 'Sprachen: ' . $wieViele . ' bezahlt, aber ' . $welche . ' angekreuzt';
        }
        if ($welche > 0 && !$hat('sprache_erst')) {
            $aus[] = 'Mehrere Sprachen, aber keine als erste bestimmt';
        }

        return $aus;
    }
}
