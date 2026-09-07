<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Onboarding.php';
require_once __DIR__ . '/Fragen.php';
require_once __DIR__ . '/Baukasten.php';

/**
 * DEN FRAGEBOGEN AM TELEFON AUSFÜLLEN
 * ===========================================================================
 *
 * Der Fragebogen hat 48 Felder in sechs Abschnitten. Er ist der Engpass des
 * ganzen Ablaufs: Ohne ihn kann Uwe nicht anfangen, und ausgefüllt wird er
 * oft nicht — nicht aus Unwillen, sondern weil 48 Felder in einem Browser
 * eine Hürde sind, die man auf morgen verschiebt. Deshalb erinnert der Cron
 * daran; deshalb steht „Fragebogen" auf der Liste, woran es hakt.
 *
 * Am Telefon ist es kein Formular, sondern ein Gespräch. Jemand fragt, man
 * antwortet, und nach zehn Minuten ist ein Drittel fertig. Genau dafür ist
 * diese Klasse da.
 *
 * WAS DABEI ANDERS IST ALS IM BROWSER
 *
 * 1. NIEMAND LIEST ELF OPTIONEN VOR. Bei „Branche" stehen elf zur Auswahl.
 *    Am Telefon fragt sie offen — „was für ein Betrieb ist das?" — und
 *    ordnet die Antwort selbst zu. Passt nichts eindeutig, fragt sie mit
 *    zwei Vorschlägen nach. Wer elf Möglichkeiten vorliest, hat nach der
 *    vierten niemanden mehr am Hörer.
 *
 * 2. WAS IN DER AKTE STEHT, WIRD NICHT GEFRAGT. Firmenname, Ort, Rufnummer
 *    und E-Mail stehen dort schon. Sie bestätigt sie in einem Satz, statt
 *    sie abzufragen — vier Fragen weniger, und der Kunde merkt, dass er
 *    bekannt ist.
 *
 * 3. NACH JEDEM ABSCHNITT IST EIN AUSGANG. Sechs Abschnitte, jeder fünf bis
 *    acht Minuten. Danach sagt sie den Stand an und fragt, ob weitergemacht
 *    wird. Legt er auf, ist nichts verloren: Gespeichert wird nach jeder
 *    einzelnen Antwort, nicht am Ende.
 *
 * 4. ABGESCHICKT WIRD ERST NACH DEM DURCHGANG. Am Ende fasst sie jeden
 *    Abschnitt zusammen und fragt, ob etwas fehlt oder falsch ist. Erst
 *    danach geht er raus — und dann rückt das Projekt weiter, verschickt
 *    Mails und ändert seinen Status. Das ist kein Schritt, den man
 *    versehentlich tut.
 */
final class Telefonfragebogen
{
    /**
     * Felder, die aus der Kundenakte kommen.
     *
     * Sie werden vorbelegt und nur bestätigt. Wer seit einem halben Jahr
     * Kunde ist und am Telefon nach seinem eigenen Firmennamen gefragt
     * wird, fragt sich zu Recht, wozu er die Daten hinterlegt hat.
     */
    public const AUS_AKTE = [
        'firmenname' => 'company',
        'ort'        => 'city',
        'telefon'    => 'phone',
        'email_web'  => 'email',
        'ansprech'   => 'name',
    ];

    /** So viele Felder schafft ein Mensch am Telefon, bevor er müde wird. */
    public const ABSCHNITT_PAUSE = true;

    /** Grobe Schätzung: So viele Sekunden kostet eine Frage im Schnitt. */
    public const SEKUNDEN_JE_FRAGE = 35;

    /* ==================================================================== */
    /*  Den Fragebogen finden                                               */
    /* ==================================================================== */

    /**
     * Der offene Fragebogen dieses Kunden -- oder null.
     *
     * Nur offene: Ein abgeschlossener wird am Telefon nicht wieder
     * aufgemacht. Was danach noch geändert werden soll, geht über Uwe --
     * sonst ändert ein Anruf stillschweigend eine Grundlage, auf der schon
     * gearbeitet wird.
     *
     * @return array<string,mixed>|null
     */
    public static function offener(int $kundeId): ?array
    {
        if ($kundeId <= 0) { return null; }
        return self::still(static fn() => Db::one(
            "SELECT q.*, p.name AS projekt
               FROM questionnaires q
               JOIN projects p ON p.id = q.project_id
              WHERE q.customer_id = ? AND q.status <> 'abgeschlossen'
              ORDER BY q.id DESC LIMIT 1", [$kundeId]), null);
    }

    /** @return array<string,mixed> Die bisherigen Antworten. */
    public static function antworten(array $f): array
    {
        $d = json_decode((string) ($f['data'] ?? ''), true);
        return is_array($d) ? $d : [];
    }

    /* ==================================================================== */
    /*  Die Reihenfolge                                                     */
    /* ==================================================================== */

    /**
     * Alle Felder in der Reihenfolge des Fragebogens.
     *
     * Dieselbe wie im Browser. Wer sie am Telefon anders sortiert, bekommt
     * einen Fragebogen, den man nachher nicht mehr mit dem Formular
     * vergleichen kann.
     *
     * WAS NICHT GEFRAGT WIRD
     *
     * Drei Fragen zur alten Seite und drei Wunschadressen haengen an einer
     * frueheren Antwort. Wer nie eine Website hatte, wird nicht gefragt, was
     * an ihr stoert. Im Browser blendet das Formular sie aus; am Telefon
     * waere es schlimmer, sie trotzdem zu stellen -- ein Mensch antwortet
     * hoeflich auf eine sinnlose Frage und wundert sich still.
     *
     * @return list<array{name:string,abschnitt:string,feld:array}>
     */
    public static function reihe(array $antworten = []): array
    {
        $aus = [];
        foreach (Texte::FRAGEBOGEN as $abschnitt => $inhalt) {
            foreach ((array) ($inhalt['felder'] ?? []) as $name => $feld) {
                $feld = (array) $feld;
                if ($antworten !== [] && !Fragen::zeigen($feld, $antworten)) { continue; }
                $aus[] = ['name' => (string) $name, 'abschnitt' => (string) $abschnitt,
                          'feld' => $feld];
            }
        }
        return $aus;
    }

    /** Ist dieses Feld beantwortet? */
    public static function beantwortet(array $antworten, string $name): bool
    {
        $w = $antworten[$name] ?? null;
        if (is_array($w)) { return $w !== []; }
        return trim((string) $w) !== '';
    }

    /**
     * Das naechste offene Feld -- oder null, wenn nichts mehr offen ist.
     *
     * WARUM „NACH DIESEM FELD" UND NICHT „DAS ERSTE OFFENE"
     *
     * Der erste Entwurf nahm immer das erste unbeantwortete Feld. Damit
     * konnte ein Gespraech nicht vorankommen: Bleibt eine Frage offen --
     * weil der Anrufer es nicht weiss, weil nichts eindeutig passt --, dann
     * kaeme sie beim naechsten Aufruf sofort wieder. Zweimal ist Nachfassen,
     * beim dritten Mal legt jeder auf.
     *
     * Also laeuft der Fragebogen vorwaerts. Was offen bleibt, bleibt offen
     * und steht am Ende in der Durchsicht -- dort, wo es hingehoert.
     *
     * @return array{name:string,abschnitt:string,feld:array}|null
     */
    public static function naechstes(array $antworten, string $nach = ''): ?array
    {
        $reihe = self::reihe($antworten);
        $ab = $nach === '';
        foreach ($reihe as $eintrag) {
            if (!$ab) {
                if ($eintrag['name'] === $nach) { $ab = true; }
                continue;
            }
            if (!self::beantwortet($antworten, $eintrag['name'])) { return $eintrag; }
        }
        /* Der Feldname war unbekannt (umbenannt, ausgeblendet): dann von
           vorn, sonst bliebe das Gespraech stehen. */
        if (!$ab) { return self::naechstes($antworten); }
        return null;
    }

    /** Was noch fehlt, in der Reihenfolge des Fragebogens. @return list<string> */
    public static function offeneFelder(array $antworten): array
    {
        $aus = [];
        foreach (self::reihe($antworten) as $e) {
            if (!self::beantwortet($antworten, $e['name'])) { $aus[] = $e['name']; }
        }
        return $aus;
    }

    /** Wie weit er ist: beantwortet, gesamt, geschaetzte Restzeit in Minuten. */
    public static function stand(array $antworten): array
    {
        $reihe = self::reihe($antworten);
        $fertig = 0;
        foreach ($reihe as $e) { if (self::beantwortet($antworten, $e['name'])) { $fertig++; } }
        $offen = max(0, count($reihe) - $fertig);
        return ['fertig' => $fertig, 'gesamt' => count($reihe), 'offen' => $offen,
                'minuten' => (int) ceil($offen * self::SEKUNDEN_JE_FRAGE / 60)];
    }

    /** Der Name eines Abschnitts in der Sprache des Anrufers. */
    public static function abschnittName(string $abschnitt, string $sprache): string
    {
        $i = Texte::FRAGEBOGEN[$abschnitt] ?? [];
        return (string) ($i[$sprache] ?? $i['it'] ?? $abschnitt);
    }

    /**
     * Alles Beantwortete, abschnittsweise und in Worten.
     *
     * Das ist der Durchgang vor dem Abschicken: Sie liest vor, was
     * dasteht, und er sagt, was falsch ist. Gespeichert sind Schluessel;
     * vorgelesen werden Saetze -- „gastronomie" versteht am Telefon
     * niemand.
     *
     * @return list<array{abschnitt:string,titel:string,zeilen:list<array{feld:string,frage:string,antwort:string}>}>
     */
    public static function zusammenfassung(array $antworten, string $sprache): array
    {
        $aus = [];
        foreach (Texte::FRAGEBOGEN as $abschnitt => $inhalt) {
            $zeilen = [];
            foreach ((array) ($inhalt['felder'] ?? []) as $name => $feld) {
                $feld = (array) $feld;
                if (!Fragen::zeigen($feld, $antworten)) { continue; }
                if (!self::beantwortet($antworten, (string) $name)) { continue; }
                $wert = self::lesbar((string) $name, $feld, $antworten, $sprache);
                if ($wert === '') { continue; }
                $zeilen[] = ['feld' => (string) $name,
                             'frage' => (string) ($feld[$sprache] ?? $feld['it'] ?? $name),
                             'antwort' => $wert];
            }
            if ($zeilen) {
                $aus[] = ['abschnitt' => (string) $abschnitt,
                          'titel' => self::abschnittName((string) $abschnitt, $sprache),
                          'zeilen' => $zeilen];
            }
        }
        return $aus;
    }

    /** Ein gespeicherter Wert, so wie man ihn vorliest. */
    public static function lesbar(string $name, array $feld, array $antworten, string $sprache): string
    {
        $roh = trim((string) ($antworten[$name] ?? ''));
        if ($roh === '') { return ''; }

        if ((string) ($feld['art'] ?? '') === 'wahl') {
            $namen = [];
            foreach (explode(',', $roh) as $slug) {
                $slug = trim($slug);
                if ($slug === '') { continue; }
                $b = self::bausteine()[$slug] ?? null;
                $namen[] = $b ? Baukasten::name($b, $sprache) : $slug;
            }
            $wert = implode(' · ', $namen);
        } else {
            $wert = (string) self::still(static fn() => Fragen::worte($name, $roh, $sprache), $roh);
        }

        $frei = trim((string) ($antworten[$name . '__frei'] ?? ''));
        if ($frei !== '') { $wert .= ' (' . $frei . ')'; }
        return trim($wert);
    }

    /* ==================================================================== */
    /*  Der Ausweg                                                          */
    /* ==================================================================== */

    /**
     * Der Schluessel, unter dem „passt nichts davon" verbucht wird.
     *
     * Jede Auswahl, die einen hat, hat ihn aus gutem Grund: Ein Sattler ist
     * keine der zehn Branchen, und „ich weiss nicht" ist bei der Frage nach
     * Schriften eine ehrliche Antwort. Am Telefon ist das doppelt wichtig --
     * eine Frage, aus der es keinen Ausgang gibt, haelt das ganze Gespraech
     * an.
     */
    public const AUSWEGE = ['anders', 'andere', 'weissnicht', 'unsicher', 'offen', 'egal'];

    public static function ausweg(array $feld): string
    {
        foreach (self::AUSWEGE as $k) {
            if (isset($feld['optionen'][$k])) { return $k; }
        }
        return '';
    }

    /** Die Bausteine als Auswahl, damit „wahl" wie jede andere Frage laeuft. */
    public static function bausteine(): array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = (array) self::still(static fn() => Baukasten::katalog(), []);
        }
        return $cache;
    }

    /** @return array<string,array{it:string,de:string,en:string}> */
    public static function bausteinOptionen(): array
    {
        $aus = [];
        foreach (self::bausteine() as $slug => $b) {
            $aus[(string) $slug] = ['it' => (string) ($b['name_it'] ?? $slug),
                                    'de' => (string) ($b['name_de'] ?? $b['name_it'] ?? $slug),
                                    'en' => (string) ($b['name_en'] ?? $b['name_it'] ?? $slug)];
        }
        return $aus;
    }

    /* ==================================================================== */
    /*  Fragen stellen                                                      */
    /* ==================================================================== */

    /**
     * Eine Frage, so wie sie am Telefon gestellt wird.
     *
     * WARUM DIE OPTIONEN NICHT MITGESCHICKT WERDEN, UM VORGELESEN ZU WERDEN
     *
     * Sie stehen in „optionen" -- aber der Hinweis sagt ausdrücklich, dass
     * sie nicht vorgelesen werden. Sie sind da, damit Manuela weiss, worauf
     * die Frage hinauswill, und damit sie bei einer unklaren Antwort zwei
     * passende vorschlagen kann. Elf Möglichkeiten vorzulesen ist der
     * sicherste Weg, ein Gespräch zu beenden.
     *
     * @return array<string,mixed>
     */
    public static function frage(array $eintrag, string $sprache): array
    {
        $f = $eintrag['feld'];
        $art = (string) ($f['art'] ?? 'text');
        $titel = (string) ($f[$sprache] ?? $f['it'] ?? $eintrag['name']);

        $aus = [
            'frage_zu'  => $eintrag['name'],
            'abschnitt' => $eintrag['abschnitt'],
            'art'       => $art,
            'frage'     => $titel,
        ];

        $optionen = self::optionenVon($f);
        if ($optionen) {
            $o = [];
            foreach ($optionen as $schl => $wort) {
                $o[(string) $schl] = (string) (is_array($wort) ? ($wort[$sprache] ?? $wort['it'] ?? $schl) : $wort);
            }
            $aus['optionen'] = $o;
            $aus['hinweis'] = 'Frag OFFEN — lies die Möglichkeiten NICHT vor. Er antwortet in '
                            . 'seinen Worten, und ich ordne zu. Passt nichts eindeutig, '
                            . 'schlage ich dir zwei vor, die du ihm nennst.';
        }

        if ($art === 'mehr') {
            $aus['hinweis'] = 'Mehreres ist möglich. Frag offen und gib alles mit, was er '
                            . 'genannt hat — ich ordne zu. Lies die Möglichkeiten nicht vor.';
        }
        if ($art === 'lang') {
            $aus['hinweis'] = 'Lass ihn erzählen. Fass danach in einem Satz zusammen, lies es '
                            . 'ihm zurück und gib mit, was er bestätigt hat.';
        }
        if ($art === 'zahl') {
            $aus['hinweis'] = 'Eine Zahl. Weiss er sie nicht, nenne die Spanne, die üblich ist, '
                            . 'und lass ihn wählen — rate sie nicht für ihn.';
        }
        if ($art === 'stand') {
            $zeilen = [];
            foreach ((array) ($f['zeilen'] ?? []) as $schl => $wort) {
                $zeilen[(string) $schl] = (string) (is_array($wort) ? ($wort[$sprache] ?? $wort['it'] ?? $schl) : $wort);
            }
            $aus['zeilen'] = $zeilen;
            $aus['zustaende'] = ['haben', 'kommt', 'du', 'nein'];
            $aus['hinweis'] = 'Das ist EINE Frage, keine neun: „Was habt ihr schon — Logo, Fotos, '
                            . 'Texte, Preise, Öffnungszeiten?" Er zählt auf, du gibst mit, was er '
                            . 'hat, was noch kommt und was wir machen sollen.';
        }
        if ($art === 'wahl') {
            $aus['hinweis'] = 'Frag offen, was die Seite können soll. Gib die genannten Wünsche '
                            . 'mit — ich ordne sie den Bausteinen zu, die es gibt. Mehreres ist '
                            . 'möglich, und „nichts davon" ist eine gültige Antwort.';
        }

        $weg = self::ausweg($f);
        if ($weg !== '') {
            $aus['ausweg'] = $weg;
            $aus['hinweis'] = ($aus['hinweis'] ?? '') . ' Weiß er es nicht oder passt nichts, '
                            . 'ist das in Ordnung — sag es mir, ich vermerke es. Bohr nicht nach.';
        }

        return $aus;
    }

    /* ==================================================================== */
    /*  Antworten verstehen                                                 */
    /* ==================================================================== */

    /**
     * Eine frei gesprochene Antwort einer Option zuordnen.
     *
     * WARUM DAS HIER STEHT UND NICHT IM MODELL
     *
     * Man könnte das Sprachmodell die Option wählen lassen. Dann steht im
     * Fragebogen ein Schlüssel, den es sich ausgedacht hat -- und der
     * Konfigurator, das Angebot und die Verwaltung kennen ihn nicht. Was in
     * einer Auswahl steht, muss aus der Auswahl kommen.
     *
     * Erkannt wird über die Wörter der Option in ALLEN drei Sprachen: Wer
     * auf Deutsch „Ristorante" sagt, meint dasselbe.
     *
     * @return array{treffer:list<string>,unklar:bool,vorschlaege:array<string,string>}
     */
    public static function zuordnen(array $feld, string $antwort, string $sprache): array
    {
        $t = mb_strtolower(trim($antwort));
        $optionen = self::optionenVon($feld);
        if ($t === '' || !$optionen) { return ['treffer' => [], 'unklar' => true, 'vorschlaege' => []]; }

        $punkte = [];
        foreach ($optionen as $schl => $wort) {
            $schl = (string) $schl;
            $texte = is_array($wort) ? array_values($wort) : [(string) $wort];
            $texte[] = $schl;
            $p = 0;
            foreach ($texte as $text) {
                foreach (self::woerter((string) $text) as $w) {
                    if (mb_strlen($w) < 4) { continue; }
                    if (str_contains($t, $w)) { $p += mb_strlen($w); }
                }
            }
            if ($p > 0) { $punkte[$schl] = $p; }
        }

        if (!$punkte) {
            /* Nichts erkannt: zwei Vorschläge, die sie ihm nennen kann. */
            $vor = [];
            foreach (array_slice($optionen, 0, 3, true) as $schl => $wort) {
                $vor[(string) $schl] = (string) (is_array($wort) ? ($wort[$sprache] ?? $wort['it'] ?? $schl) : $wort);
            }
            return ['treffer' => [], 'unklar' => true, 'vorschlaege' => $vor];
        }

        arsort($punkte);
        $beste = array_keys($punkte);

        /* Bei „mehr" zählt alles, was getroffen hat. Bei „eins" nur das
           beste -- und wenn zwei gleich gut sind, ist es unklar, und dann
           entscheidet der Anrufer, nicht die Rechnung. */
        if (in_array((string) ($feld['art'] ?? ''), ['mehr', 'wahl'], true)) {
            return ['treffer' => $beste, 'unklar' => false, 'vorschlaege' => []];
        }

        $werte = array_values($punkte);
        if (count($werte) > 1 && $werte[0] === $werte[1]) {
            $vor = [];
            foreach (array_slice($beste, 0, 2) as $schl) {
                $w = $optionen[$schl];
                $vor[$schl] = (string) (is_array($w) ? ($w[$sprache] ?? $w['it'] ?? $schl) : $w);
            }
            return ['treffer' => [], 'unklar' => true, 'vorschlaege' => $vor];
        }
        return ['treffer' => [$beste[0]], 'unklar' => false, 'vorschlaege' => []];
    }

    /**
     * Die Auswahl eines Feldes -- egal, woher sie kommt.
     *
     * Bei „wahl" steht sie nicht im Text, sondern im Baukasten: Was
     * bestellbar ist, entscheidet die Preisliste, nicht eine zweite Liste
     * daneben, die man vergessen wuerde nachzuziehen.
     */
    public static function optionenVon(array $feld): array
    {
        if ((string) ($feld['art'] ?? '') === 'wahl') { return self::bausteinOptionen(); }
        return (array) ($feld['optionen'] ?? []);
    }

    /**
     * Aus Treffern wird der Wert, der im Fragebogen steht.
     *
     * Genau die Form, die das Formular auch schickt -- Onboarding::saeubern
     * prueft sie danach noch einmal gegen die Auswahl. Zwei Netze, weil
     * hier ein Sprachmodell am anderen Ende sitzt.
     *
     * @param list<string> $treffer
     * @return array<string,mixed> Was gespeichert werden soll.
     */
    public static function speicherwert(string $name, array $feld, array $treffer, string $antwort): array
    {
        $art = (string) ($feld['art'] ?? 'text');
        $antwort = trim($antwort);

        if ($art === 'eins') {
            $aus = [$name => $treffer[0] ?? ''];
        } elseif ($art === 'mehr' || $art === 'wahl') {
            $aus = [$name => implode(',', $treffer)];
        } elseif ($art === 'zahl') {
            $aus = [$name => (string) self::zahl($antwort)];
        } else {
            $aus = [$name => mb_substr($antwort, 0, 4000)];
        }

        /* Die freie Zeile unter einer Auswahl. Sie traegt den Wortlaut --
           „Sattlerei" ist mehr wert als „anders". */
        if (!empty($feld['frei']) && $antwort !== ''
            && in_array((string) ($treffer[0] ?? ''), self::AUSWEGE, true)) {
            $aus[$name . '__frei'] = mb_substr($antwort, 0, 500);
        }
        return $aus;
    }

    /**
     * Die Materialliste: neun Zeilen, vier Zustaende.
     *
     * WAS MIT EINER ZEILE PASSIERT, DIE NIEMAND GENANNT HAT
     *
     * Sie wird zu „machst du". Nicht, weil das wahrscheinlich waere,
     * sondern weil es die teure Annahme ist: Wer nicht sagt, dass er
     * Produktfotos hat, hat vermutlich keine, und dann muss sie jemand
     * machen. Die andere Richtung waere schlimmer -- ein Angebot, das
     * Fotos voraussetzt, die es nicht gibt. Uwe sieht jede solche Zeile in
     * der Lueckenliste wieder („ist das im Angebot?").
     *
     * @param array<string,string> $zeilen
     */
    public static function standwert(array $feld, array $zeilen): string
    {
        $erlaubt = array_keys(Fragen::ZUSTANDWORT);
        $aus = [];
        foreach ((array) ($feld['zeilen'] ?? []) as $schl => $_) {
            $z = trim((string) ($zeilen[(string) $schl] ?? ''));
            $aus[] = $schl . ':' . (in_array($z, $erlaubt, true) ? $z : 'du');
        }
        return implode(',', $aus);
    }

    /**
     * Eine Zahl aus einem gesprochenen Satz.
     *
     * „So acht bis zehn Seiten." Am Telefon sagt niemand „8" -- er sagt
     * acht, otto, eight. Der erste Entwurf suchte nur Ziffern und trug
     * daraufhin eine Null ein: eine Website mit null Seiten, und niemand
     * haette es gemerkt, bis das Angebot herausging.
     *
     * Genommen wird die ERSTE genannte Zahl. Bei „acht bis zehn" ist das
     * die untere -- die obere waere geraten, und geraten wird hier nicht.
     */
    public static function zahl(string $antwort): int
    {
        if (preg_match('/\d+/', $antwort, $m)) { return min(999, max(0, (int) $m[0])); }

        $worte = [
            'null' => 0, 'zero' => 0,
            'ein' => 1, 'eine' => 1, 'eins' => 1, 'uno' => 1, 'una' => 1, 'one' => 1,
            'zwei' => 2, 'due' => 2, 'two' => 2,
            'drei' => 3, 'tre' => 3, 'three' => 3,
            'vier' => 4, 'quattro' => 4, 'four' => 4,
            'fuenf' => 5, 'fünf' => 5, 'cinque' => 5, 'five' => 5,
            'sechs' => 6, 'sei' => 6, 'six' => 6,
            'sieben' => 7, 'sette' => 7, 'seven' => 7,
            'acht' => 8, 'otto' => 8, 'eight' => 8,
            'neun' => 9, 'nove' => 9, 'nine' => 9,
            'zehn' => 10, 'dieci' => 10, 'ten' => 10,
            'elf' => 11, 'undici' => 11, 'eleven' => 11,
            'zwoelf' => 12, 'zwölf' => 12, 'dodici' => 12, 'twelve' => 12,
            'fuenfzehn' => 15, 'fünfzehn' => 15, 'quindici' => 15, 'fifteen' => 15,
            'zwanzig' => 20, 'venti' => 20, 'twenty' => 20,
        ];
        foreach (self::woerter($antwort) as $w) {
            if (isset($worte[$w])) { return $worte[$w]; }
        }
        return 0;
    }

    /** @return list<string> */
    private static function woerter(string $text): array
    {
        $t = mb_strtolower($text);
        $t = preg_replace('~[^\p{L}\p{N}]+~u', ' ', $t) ?? $t;
        $aus = [];
        foreach (preg_split('~\s+~', trim($t)) ?: [] as $w) {
            if ($w !== '') { $aus[] = $w; }
        }
        return $aus;
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
