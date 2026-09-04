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
    private const DOPPELT = ['firmenname', 'seiten_zahl', 'sprachen_zahl', 'sprachen_welche',
                             'funktionen_wahl', 'domain'];

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

        /* ---------- Was ich will ---------- */
        $zeilen[] = 'AUFTRAG AN DICH';
        $zeilen[] = '  Bau die Seite nach dem Vecom-Standard. Fang mit der Struktur und';
        $zeilen[] = '  der Startseite an, zeig sie mir, dann weiter. Wo im Fragebogen';
        $zeilen[] = '  etwas fehlt, frag — rate nicht.';
        $zeilen[] = '';

        $text = implode("\n", $zeilen);

        $mit = $mitStandard ?? Standard::anhaengen();
        if ($mit) {
            $text .= "\n" . str_repeat('-', 60) . "\n" . Standard::text() . "\n";
        }
        return rtrim($text) . "\n";
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
