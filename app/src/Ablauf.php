<?php
declare(strict_types=1);

/**
 * DER ABLAUF ALS EINE WAHRHEIT
 * =============================================================================
 *
 * Drei Dinge hingen bisher in der Luft und widersprachen sich gelegentlich:
 *
 *   1. Was jetzt dran ist        -- wusste Vorgang::stufeBestimmen().
 *   2. Was ein Knopf anrichtet   -- wusste der, der das data-frage= tippte.
 *   3. Was zu einer Stufe gehoert-- wusste niemand ausser Uwes Gedaechtnis.
 *
 * Das Erste war gut aufgehoben. Das Zweite war eine Sammlung von
 * Einzelentscheidungen: An "Abnahme freischalten" stand eine Rueckfrage, an
 * "Fuer den Kunden freischalten" nicht -- obwohl beide eine E-Mail ausloesen.
 * Das Dritte gab es schlicht nicht.
 *
 * Diese Datei ist die eine Stelle, an der alle drei stehen. Sie entscheidet
 * nichts neu; sie schreibt auf, was der Ablauf ohnehin ist, damit die
 * Verwaltung es zeigen kann, statt es vorauszusetzen.
 *
 * WAS SIE AUSDRUECKLICH NICHT TUT
 *
 * Sie verschickt nichts, sie veroeffentlicht nichts, sie legt nichts an.
 * Der einzige Schreibzugriff steht in nachziehen(), und der bewegt genau ein
 * Statuswort um genau einen Schritt nach vorn -- und nur dann, wenn die
 * Tatsache dafuer schon in der Datenbank steht und nichts das Haus verlaesst.
 */
final class Ablauf
{
    /* =====================================================================
       PUNKT 11a -- DIE HANDBREMSE

       Nicht jeder Knopf wiegt gleich viel. Drei Gewichte reichen:

         STILL   Es bleibt im Haus. Ein Feld, eine Notiz, eine Adresse, die
                 nur Uwe sieht. Falsch geklickt? Nochmal klicken.
         RAUS    Es erreicht den Kunden. Eine E-Mail laesst sich nicht
                 zurueckholen; peinlich ist sie trotzdem nur einmal.
         SCHWER  Es steht danach in der Welt oder in den Buechern. Eine
                 Rechnungsnummer aus einem fortlaufenden Kreis, eine
                 veroeffentlichte Seite, eine Abnahme, an der Geld haengt.

       Nur RAUS und SCHWER fragen nach. Und sie fragen nicht "Sind Sie
       sicher?" -- diese Frage beantwortet jeder mit Ja, ohne sie zu lesen.
       Sie sagen, WAS passiert und WEM. Das ist die einzige Rueckfrage, die
       jemanden je aufgehalten hat.
       ===================================================================== */

    public const STILL  = 'still';
    public const RAUS   = 'raus';
    public const SCHWER = 'schwer';

    /**
     * Tat => [Gewicht, was passiert, Beschriftung des Ja-Knopfes].
     *
     * Was hier nicht steht, ist STILL und fragt nicht nach. Das ist Absicht:
     * Eine Liste, die jeden Handgriff aufzaehlt, waere in einem halben Jahr
     * unvollstaendig und niemand merkte es. So faellt nur auf, was fehlt --
     * naemlich dann, wenn eine E-Mail ohne Rueckfrage rausging.
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    public const TRAGWEITE = [
        'fragebogen_einladen' => [self::RAUS,
            'Der Kunde bekommt jetzt die E-Mail mit dem Fragebogen.',
            'Ja, Fragebogen verschicken'],
        'zahlungslink_senden' => [self::RAUS,
            'Der Kunde bekommt jetzt eine E-Mail mit dem Zahlungslink.',
            'Ja, Link verschicken'],
        'restzahlung_anfordern' => [self::RAUS,
            'Der Kunde bekommt jetzt die Aufforderung zur Restzahlung.',
            'Ja, Restzahlung anfordern'],
        'vorschau_frei' => [self::RAUS,
            'Der Kunde bekommt jetzt die E-Mail mit der Adresse und sieht den Entwurf. '
            . 'Abnehmen kann er ihn damit noch nicht.',
            'Ja, freischalten'],
        'abnahme_frei' => [self::SCHWER,
            'Der Kunde bekommt „die Seite ist fertig“ und den Knopf „Passt so“. '
            . 'An seiner Abnahme hängen Restzahlung und Veröffentlichung.',
            'Ja, Abnahme freischalten'],
        'rechnung_erzeugen' => [self::SCHWER,
            'Die Rechnung bekommt eine Nummer aus dem fortlaufenden Kreis. '
            . 'Eine vergebene Nummer bleibt vergeben — auch wenn die Rechnung storniert wird.',
            'Ja, Rechnung erzeugen'],
        'rechnung_schicken' => [self::SCHWER,
            'Die Rechnung geht als Beleg an den Kunden.',
            'Ja, Rechnung schicken'],
        'abo_anlegen' => [self::SCHWER,
            'Die Betreuung beginnt zu laufen und wird ab jetzt regelmäßig abgerechnet.',
            'Ja, Betreuung anlegen'],
        'abo_abrechnen' => [self::SCHWER,
            'Für die Betreuung wird jetzt abgerechnet.',
            'Ja, abrechnen'],
        'kunde_loeschen' => [self::SCHWER,
            'Der Kunde und alles, was an ihm hängt, wird gelöscht. Das lässt sich nicht rückgängig machen.',
            'Ja, endgültig löschen'],
        'kunde_anonymisieren' => [self::SCHWER,
            'Name, E-Mail und Anschrift werden unkenntlich gemacht. Das lässt sich nicht rückgängig machen.',
            'Ja, anonymisieren'],
    ];

    /**
     * Was der Statuswechsel anrichtet. „projekt_status“ ist eine Tat mit
     * vielen Gesichtern: Der eine Wert verschiebt ein Wort, der andere
     * stellt eine Seite ins Netz.
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    public const TRAGWEITE_STATUS = [
        'vorschau' => [self::RAUS,
            'Der Stand springt auf „Vorschau“. Der Kunde sieht den Entwurf erst, '
            . 'wenn du ihn danach auch freischaltest.',
            'Ja, Vorschau setzen'],
        'online' => [self::SCHWER,
            'Die Seite gilt ab jetzt als veröffentlicht — der Kunde wird benachrichtigt.',
            'Ja, die Seite ist online'],
        'abgeschlossen' => [self::SCHWER,
            'Der Vorgang wird geschlossen. Er verschwindet aus „Du bist dran“.',
            'Ja, abschließen'],
    ];

    /**
     * Die Rueckfrage zu einer Tat -- oder null, wenn keine noetig ist.
     *
     * @return array{gewicht:string,frage:string,ja:string}|null
     */
    public static function rueckfrage(string $tat, string $status = ''): ?array
    {
        $eintrag = null;
        if ($tat === 'projekt_status' && $status !== '') {
            $eintrag = self::TRAGWEITE_STATUS[$status] ?? null;
        } else {
            $eintrag = self::TRAGWEITE[$tat] ?? null;
        }
        if ($eintrag === null) { return null; }
        return ['gewicht' => $eintrag[0], 'frage' => $eintrag[1], 'ja' => $eintrag[2]];
    }

    /** Wiegt diese Tat schwer genug fuer eine Rueckfrage? */
    public static function wiegt(string $tat, string $status = ''): string
    {
        $r = self::rueckfrage($tat, $status);
        return $r === null ? self::STILL : $r['gewicht'];
    }

    /* =====================================================================
       PUNKT 12 -- DIE CHECKLISTE JE STUFE

       Eine Stufe ist kein Zustand, sondern eine Handvoll Tatsachen. „In
       Arbeit“ heisst: bezahlt, Fragebogen da, Briefing erzeugt, Gespraech
       vermerkt, Vorschau eingetragen, Abnahme gelaufen. Sechs Dinge, von
       denen die Fuehrung immer nur eines nennt -- naemlich das naechste.

       Das ist richtig fuer den Handgriff und falsch fuer den Ueberblick. Wer
       einen Vorgang nach zwei Wochen wieder aufmacht, will nicht wissen, was
       als Naechstes kommt, sondern wo er steht.

       Die Liste rechnet nichts aus, was nicht ohnehin dasteht. Jeder Haken
       ist eine Datenbankzeile, kein Gedaechtnis: Deshalb kann er auch nicht
       falsch sein.
       ===================================================================== */

    /**
     * Die Punkte der aktuellen Stufe.
     *
     * @param array<string,mixed> $v Ein Vorgang, wie Vorgang::laden() ihn liefert
     * @return list<array{was:string,da:bool,wer:string}>
     */
    public static function checkliste(array $v): array
    {
        $prj  = (array) ($v['projekt'] ?? []);
        $fb   = $v['fragebogen'] ?? null;
        $az   = $v['anzahlung'] ?? null;
        $rest = $v['restzahlung'] ?? null;

        $hat = static fn(mixed $x): bool => $x !== null && trim((string) $x) !== '';
        $du    = 'du';
        $kunde = 'kunde';

        $punkte = [];
        $P = static function (string $was, bool $da, string $wer) use (&$punkte): void {
            $punkte[] = ['was' => $was, 'da' => $da, 'wer' => $wer];
        };

        switch ((string) ($v['stufe'] ?? '')) {

            case 'gespraech':
                $P('Anfrage liegt vor', $v['anfrage_id'] !== null || $v['bestell_id'] !== null, $kunde);
                $P('Bedarf ist erfasst', !empty($v['bedarf']), $kunde);
                $P('Preis ist genannt', !empty($v['angebot']), $du);
                $P('Angebot ist verschickt',
                    !empty($v['angebot']) && $hat($v['angebot']['gesendet_am'] ?? null), $du);
                $P('Kunde hat zugesagt', $v['bestell_id'] !== null, $kunde);
                break;

            case 'angebot':
                $P('Bestellung ist angelegt', $v['bestell_id'] !== null, $du);
                $P('Anzahlung ist angelegt', $az !== null, $du);
                $P('Zahlungslink ist erzeugt', $az !== null && !empty($az['link_url']), $du);
                $P('Kunde hat den Link', $az !== null
                    && self::mailGing('zahlungslink', 'payment_id', (int) ($az['id'] ?? 0)), $du);
                $P('Anzahlung ist eingegangen',
                    $az !== null && (string) $az['status'] === 'bezahlt', $kunde);
                break;

            case 'onboarding':
                $P('Anzahlung ist eingegangen',
                    $az !== null && (string) $az['status'] === 'bezahlt', $kunde);
                $P('Fragebogen ist angelegt', $fb !== null, $du);
                $P('Fragebogen ist verschickt', $fb !== null && $hat($fb['eingeladen_am'] ?? null), $du);
                $P('Fragebogen ist zurück',
                    $fb !== null && (string) $fb['status'] !== 'offen', $kunde);
                /* „Umfang passt zum Angebot“ stand hier auch -- und war
                   abgehakt, bevor der Fragebogen zurueck war. Nichts zu
                   vergleichen ist nicht dasselbe wie „passt“. Der Punkt
                   gehoert in die naechste Stufe, wo es etwas zu vergleichen
                   gibt. Ein Haken, der nur bedeutet „noch keine Daten“, ist
                   schlimmer als kein Haken: Er beruhigt falsch. */
                break;

            case 'arbeit':
                $P('Fragebogen ist zurück', $fb === null || (string) $fb['status'] !== 'offen', $kunde);
                $P('Umfang passt zum Angebot', empty($v['mehrbedarf']), $du);
                $P('Briefing ist erzeugt', $hat($prj['briefing_am'] ?? null), $du);
                $P('Gespräch ist vermerkt', $hat($prj['chat_url'] ?? null), $du);
                $P('Vorschau-Adresse steht', $hat($prj['vorschau'] ?? null), $du);
                $P('Abnahme ist gelaufen', self::abnahmeSauber($prj), $du);
                break;

            case 'vorschau':
                $P('Vorschau-Adresse steht', $hat($prj['vorschau'] ?? null), $du);
                $P('Abnahme ist gelaufen', self::abnahmeSauber($prj), $du);
                $P('Kunde darf ansehen', $hat($prj['vorschau_frei'] ?? null), $du);
                $P('Kunde darf abnehmen', $hat($prj['abnahme_frei'] ?? null), $du);
                $P('Kunde hat abgenommen', false, $kunde);
                break;

            case 'freigabe':
                $P('Kunde hat abgenommen', true, $kunde);
                $P('Restzahlung ist angefordert', $rest === null
                    || self::mailGing('restzahlung', 'payment_id', (int) ($rest['id'] ?? 0)), $du);
                $P('Alles ist bezahlt',
                    $rest === null || (string) $rest['status'] === 'bezahlt', $kunde);
                $P('Seite ist veröffentlicht', false, $du);
                break;

            case 'online':
                $P('Seite ist veröffentlicht', true, $du);
                $P('Alles ist bezahlt',
                    $rest === null || (string) $rest['status'] === 'bezahlt', $kunde);
                $P('Rechnung ist geschrieben', !empty($v['belege']), $du);
                $P('Betreuung läuft', self::betreuungLaeuft($v), $du);
                break;

            case 'betreuung':
                $P('Betreuung läuft', true, $du);
                $P('Keine Rate offen', (int) ($v['offen_cent'] ?? 0) === 0, $kunde);
                break;

            case 'fertig':
                $P('Alles bezahlt', (int) ($v['offen_cent'] ?? 0) === 0, $kunde);
                $P('Rechnung ist geschrieben', !empty($v['belege']), $du);
                $P('Vorgang ist geschlossen', true, $du);
                break;
        }

        return $punkte;
    }

    /** Wie viele Punkte der aktuellen Stufe stehen — fuer die kurze Anzeige. */
    public static function stand(array $v): array
    {
        $liste = self::checkliste($v);
        $da = 0;
        foreach ($liste as $p) { if ($p['da']) { $da++; } }
        return ['da' => $da, 'von' => count($liste), 'punkte' => $liste];
    }

    /* =====================================================================
       PUNKT 11b -- WAS VON SELBST LAUFEN DARF

       Der Projektstand ist ein gespeichertes Wort. Die Fuehrung dagegen
       rechnet aus Tatsachen -- deshalb stimmte sie immer, und deshalb fiel
       lange nicht auf, dass das Wort daneben oft falsch war: Der Kunde sah
       in seinem Bereich „Zahlung bestätigt, 8 %“, waehrend laengst gebaut
       wurde. Niemand log; es hatte nur nie jemand geklickt.

       Vier Schritte holen das Wort ein. Alle vier haben dieselben drei
       Eigenschaften, und nur deshalb duerfen sie von selbst laufen:

         - Die Tatsache steht bereits in der Datenbank. Es wird nichts
           beschlossen, nur nachgetragen.
         - Nichts verlaesst das Haus. Keine E-Mail, keine Rechnung, keine
           Veroeffentlichung.
         - Sie gehen nur vorwaerts, und nur von genau dem erwarteten
           Vorgaenger aus. Ein Stand, den Uwe von Hand gesetzt hat, kann so
           nie ueberschrieben werden.

       ALLES ANDERE BLEIBT AM KLICK. „Vorschau“, „online“, „abgeschlossen“
       stehen ausdruecklich nicht hier: Sie sind Entscheidungen, keine
       Feststellungen -- und jede von ihnen laesst etwas aus dem Haus.
       ===================================================================== */

    /**
     * Welche Tatsache welchen Stand rechtfertigt -- von der staerksten zur
     * schwaechsten. Gelesen wird von oben: Die erste Tatsache, die zutrifft,
     * bestimmt den Stand.
     *
     * WARUM NICHT ALS KETTE VON SCHRITTEN
     *
     * Zuerst stand hier eine Liste von Uebergaengen: von A nach B, wenn X.
     * Das las sich sauber und hatte einen Fehler, den erst die Pruefung fand:
     * Wurde eine Zwischenstufe nie erreicht -- ein Fragebogen, der ausgefuellt
     * zurueckkam, ohne dass je eine Einladung vermerkt wurde --, blockierte
     * die fehlende schwaechere Tatsache die vorhandene staerkere. Der Stand
     * blieb stehen, obwohl laengst mehr wahr war.
     *
     * Tatsachen kennen keine Reihenfolge. Der Stand ist einfach der weiteste,
     * fuer den es einen Beleg gibt.
     *
     * @var list<array{stand:string,weil:string}>
     */
    private const BELEGT = [
        ['stand' => 'entwicklung',           'weil' => 'vorschau_eingetragen'],
        ['stand' => 'informationen_erhalten','weil' => 'fragebogen_zurueck'],
        ['stand' => 'onboarding',            'weil' => 'fragebogen_verschickt'],
        ['stand' => 'zahlung_bestaetigt',    'weil' => 'anzahlung_bezahlt'],
    ];

    /**
     * Staende, die von selbst verlassen werden duerfen.
     *
     * Alles ab „vorschau“ fehlt hier mit Absicht: Von dort an laesst jeder
     * Schritt etwas aus dem Haus -- eine E-Mail, eine Veroeffentlichung, eine
     * Rechnung. Diese Staende gehoeren einem Menschen, nicht einer Regel.
     *
     * @var list<string>
     */
    private const AUTOMATISCH = [
        'bestellung_eingegangen', 'zahlung_bestaetigt',
        'onboarding', 'informationen_erhalten', 'design',
    ];

    /**
     * Zieht den Projektstand nach, wenn die Tatsachen ihn ueberholt haben.
     * Gibt die ganze Strecke zurueck -- oder null, wenn nichts zu tun war.
     *
     * Idempotent: Zweimal aufgerufen passiert beim zweiten Mal nichts.
     *
     * @return array{von:string,nach:string,weil:string}|null
     */
    public static function nachziehen(array $v): ?array
    {
        $pid = $v['projekt_id'] ?? null;
        if ($pid === null) { return null; }
        $jetzt = (string) ($v['projekt']['status'] ?? '');
        if ($jetzt === '') { return null; }

        /* Ein Stand, den ein Mensch gesetzt hat, wird nie ueberschrieben. */
        if (!in_array($jetzt, self::AUTOMATISCH, true)) { return null; }

        $belegt = null;
        foreach (self::BELEGT as $eintrag) {
            if (self::tatsache($eintrag['weil'], $v)) { $belegt = $eintrag; break; }
        }
        if ($belegt === null) { return null; }

        /* Nur vorwaerts. Die Reihenfolge in Status::PROJEKT ist der
           Fortschritt; ein Rueckschritt waere immer ein Fehler -- und einer,
           den niemand bemerkt, weil er wie ein Zustand aussieht. */
        $reihe = array_keys(Status::PROJEKT);
        $a = array_search($jetzt, $reihe, true);
        $b = array_search($belegt['stand'], $reihe, true);
        if ($a === false || $b === false || $b <= $a) { return null; }

        /* Die Bedingung im WHERE ist die eigentliche Sicherung: Hat in der
           Zwischenzeit jemand anders den Stand gesetzt, passiert nichts. */
        $geaendert = Db::run(
            'UPDATE projects SET status = ?, updated_at = NOW() WHERE id = ? AND status = ?',
            [$belegt['stand'], (int) $pid, $jetzt])->rowCount();
        if ($geaendert === 0) { return null; }

        $schritt = ['von' => $jetzt, 'nach' => $belegt['stand'], 'weil' => $belegt['weil']];
        self::vermerken($v, $schritt);
        return $schritt;
    }

    /** Steht die Tatsache in den Daten? */
    private static function tatsache(string $welche, array $v): bool
    {
        $prj = (array) ($v['projekt'] ?? []);
        $fb  = $v['fragebogen'] ?? null;
        $az  = $v['anzahlung'] ?? null;

        return match ($welche) {
            'anzahlung_bezahlt'      => $az !== null && (string) $az['status'] === 'bezahlt',
            'fragebogen_verschickt'  => $fb !== null && trim((string) ($fb['eingeladen_am'] ?? '')) !== '',
            'fragebogen_zurueck'     => $fb !== null && (string) $fb['status'] !== 'offen',
            'vorschau_eingetragen'   => trim((string) ($prj['vorschau'] ?? '')) !== '',
            default                  => false,
        };
    }

    /** Erklaerender Satz zu einer nachgezogenen Tatsache. */
    public static function weilWort(string $welche): string
    {
        return match ($welche) {
            'anzahlung_bezahlt'     => 'die Anzahlung ist eingegangen',
            'fragebogen_verschickt' => 'der Fragebogen ist verschickt',
            'fragebogen_zurueck'    => 'der Fragebogen ist zurück',
            'vorschau_eingetragen'  => 'die Vorschau-Adresse steht',
            default                 => $welche,
        };
    }

    /**
     * Ein automatischer Schritt, den niemand sieht, ist ein Schritt, dem
     * niemand trauen kann. Jeder landet deshalb in der Aktivitaetenliste --
     * mit dem Grund, aus dem er lief.
     */
    private static function vermerken(array $v, array $schritt): void
    {
        try {
            require_once __DIR__ . '/Events.php';
            Events::protokoll(
                'stand_nachgezogen',
                'Stand nachgezogen: ' . (Status::PROJEKT[$schritt['nach']] ?? $schritt['nach']),
                $v['kunde_id'] ?? null,
                $v['bestell_id'] ?? null,
                $v['projekt_id'] ?? null,
                ['von' => $schritt['von'], 'nach' => $schritt['nach'],
                 'weil' => self::weilWort($schritt['weil'])]
            );
        } catch (Throwable $e) {
            /* Ein fehlender Vermerk darf den Vorgang nicht anhalten. */
        }
    }

    /* ---------------------------------------------------------------- */

    private static function abnahmeSauber(array $prj): bool
    {
        $roh = trim((string) ($prj['abnahme'] ?? ''));
        if ($roh === '') { return false; }
        $d = json_decode($roh, true);
        if (!is_array($d)) { return false; }
        return (int) ($d['zaehler']['schlecht'] ?? 0) === 0;
    }

    private static function betreuungLaeuft(array $v): bool
    {
        if (($v['kunde_id'] ?? null) === null) { return false; }
        try {
            return (int) Db::wert(
                "SELECT COUNT(*) FROM abos WHERE customer_id = ?
                  AND status IN ('angelegt','aktiv','gekuendigt')",
                [(int) $v['kunde_id']], 0) > 0;
        } catch (Throwable $e) { return false; }
    }

    /* Dieselbe Frage wie in der Fuehrung, also auch dieselbe Antwort:
       Mail::schonGeschickt() ist die eine Stelle, die weiss, was als
       "verschickt" gilt. Eine zweite Abfrage daneben waere ein zweiter
       Wahrheitsbegriff -- und irgendwann ein Haken, der etwas anderes
       behauptet als der Schritt daneben. */
    private static function mailGing(string $anlass, string $feld, int $id): bool
    {
        if ($id <= 0) { return false; }
        try {
            require_once __DIR__ . '/Mail.php';
            return Mail::schonGeschickt($anlass, $feld, $id);
        } catch (Throwable $e) { return false; }
    }
}
