<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';
require_once __DIR__ . '/AkquiseVersand.php';

/**
 * Folge-Mails an Betriebe mit bestätigter Einwilligung (27.09.2026, Uwe:
 * „Texte freigeben, dann automatisch“).
 *
 * WER SIE BEKOMMT
 * Nur ein Betrieb, der per Double-Opt-in eingewilligt hat. Die Folge beginnt
 * mit dem Klick in der Bestätigungsmail (AkquiseEinwilligung::bestaetigen)
 * und läuft in fünf Schritten: Tag 0, 3, 7, 14, 30. Vor JEDER Mail wird neu
 * geprüft -- das Gate muss „Ja, erlaubt“ sagen, die Grenzen müssen passen,
 * und es darf nichts passiert sein, das die Folge beendet:
 *
 *   Antwort eingegangen          → pausiert (ab dann schreibt ein Mensch)
 *   abgemeldet oder gesperrt     → beendet
 *   Einwilligung weg             → beendet
 *   Kunde geworden               → beendet (ab dann läuft der Kundenweg)
 *
 * WAS AUTOMATISCH IST -- UND WAS NICHT
 * Automatisch ist nur der Zeitpunkt. Die Texte gibt Uwe je Schritt und
 * Sprache einmal frei; jede Änderung setzt sie zurück auf Entwurf. Ohne
 * freigegebenen Text wartet die Folge. Ohne Schalter „Folge-Mails“ (und
 * Hauptschalter „Automatik“) wartet sie ebenfalls. Die Notbremse stoppt alles.
 *
 * TESTBETRIEB
 * Er schiebt die echte Folge nie weiter: Der fällige Schritt wird einmal
 * simuliert und vermerkt (Spalte simuliert), mehr nicht. Wird später auf
 * Echtbetrieb gestellt, geht genau dieser Schritt wirklich raus.
 */
final class AkquiseFolge
{
    /** Schritt => Tag nach dem Start. */
    public const TAGE = [1 => 0, 2 => 3, 3 => 7, 4 => 14, 5 => 30];
    /** Höchstens so viele Folge-Mails je Lauf -- die Grenzen der Akquise gelten zusätzlich. */
    public const JE_LAUF = 10;

    public const PLATZHALTER = ['{anrede}', '{firma}', '{website}', '{analyse}', '{bedarf}', '{beispiele}', '{termin}', '{inhaber}', '{absender}', '{telefon}', '{dashboard}'];

    /** Ausgangstexte. Keine Zahl, keine Behauptung, die nicht aus der Analyse selbst kommt. */
    public const TEXTE = [
        1 => [
            /* Mit dem persönlichen Bereich (28.09.2026, Uwe: Ja zu V2 „Dashboard vorbereitet“). */
            'de' => ['Die Analyse Ihrer Website', "{anrede}\n\ndanke für Ihre Bestätigung. Hier finden Sie die Analyse Ihrer Website {website}:\n\n{analyse}\n\nDort steht, was wir gemessen haben – mit der Ansicht auf dem Handy und den Punkten, die Besucher und Google zuerst bemerken.\n\nIhr persönlicher Bereich bei uns ist schon vorbereitet, mit der Analyse und unserem Vorschlag für {firma}. Ein Klick öffnet ihn:\n\n{dashboard}\n\nWenn Sie Fragen dazu haben, antworten Sie einfach auf diese Mail.\n\nViele Grüße\n{inhaber}\n{absender}{telefon}"],
            'it' => ['L’analisi del suo sito', "{anrede}\n\ngrazie per la conferma. Qui trova l’analisi del suo sito {website}:\n\n{analyse}\n\nC’è quello che abbiamo misurato, con la vista da telefono e i punti che visitatori e Google notano per primi.\n\nIl suo spazio personale da noi è già pronto, con l’analisi e la nostra proposta per {firma}. Si apre con un clic:\n\n{dashboard}\n\nSe ha domande, risponda semplicemente a questa e-mail.\n\nCordiali saluti\n{inhaber}\n{absender}{telefon}"],
            'en' => ['The analysis of your website', "{anrede}\n\nthank you for confirming. Here is the analysis of your website {website}:\n\n{analyse}\n\nIt shows what we measured – including the mobile view and the points visitors and Google notice first.\n\nYour personal area with us is already set up, with the analysis and our proposal for {firma}. One click opens it:\n\n{dashboard}\n\nIf you have any questions, simply reply to this email.\n\nKind regards\n{inhaber}\n{absender}{telefon}"],
        ],
        2 => [
            'de' => ['Kurze Frage zu {website}', "{anrede}\n\nhaben Sie schon in die Analyse geschaut?\n\n{analyse}\n\nWenn Sie möchten, zeige ich Ihnen, wie wir die Startseite von {firma} anders aufbauen würden – als Skizze, unverbindlich. Eine kurze Antwort „Ja, gern“ genügt.\n\nViele Grüße\n{inhaber}"],
            'it' => ['Una breve domanda su {website}', "{anrede}\n\nha già dato un’occhiata all’analisi?\n\n{analyse}\n\nSe vuole, le mostro come imposteremmo la pagina iniziale di {firma}: una bozza, senza impegno. Basta rispondere «Sì, volentieri».\n\nCordiali saluti\n{inhaber}"],
            'en' => ['A quick question about {website}', "{anrede}\n\nhave you had a chance to look at the analysis?\n\n{analyse}\n\nIf you like, I can show you how we would build the home page of {firma} differently – as a sketch, with no obligation. A short “Yes, please” is enough.\n\nKind regards\n{inhaber}"],
        ],
        3 => [
            'de' => ['Wie das aussehen kann', "{anrede}\n\nBeispiele unserer Arbeit finden Sie hier:\n{beispiele}\n\nUnd was eine neue Seite für {firma} kosten würde, sehen Sie in zwei Minuten – den Preis kennen Sie, bevor Sie sich entscheiden:\n{bedarf}\n\nViele Grüße\n{inhaber}"],
            'it' => ['Come può essere', "{anrede}\n\nqui trova alcuni esempi del nostro lavoro:\n{beispiele}\n\nE quanto costerebbe un sito nuovo per {firma} lo vede in due minuti, con il prezzo prima di decidere:\n{bedarf}\n\nCordiali saluti\n{inhaber}"],
            'en' => ['What it can look like', "{anrede}\n\nyou can find examples of our work here:\n{beispiele}\n\nAnd what a new site for {firma} would cost, you can see in two minutes – with the price before you decide:\n{bedarf}\n\nKind regards\n{inhaber}"],
        ],
        4 => [
            'de' => ['Ein kurzes Gespräch?', "{anrede}\n\nwenn Sie mögen, sprechen wir 15 Minuten über Ihre Website – am Telefon oder per Video, unverbindlich. Antworten Sie einfach mit einem Zeitpunkt, der Ihnen passt.{termin}\n\nViele Grüße\n{inhaber}{telefon}"],
            'it' => ['Una breve chiacchierata?', "{anrede}\n\nse le va, parliamo 15 minuti del suo sito, al telefono o in video, senza impegno. Mi risponda semplicemente con un orario che le fa comodo.{termin}\n\nCordiali saluti\n{inhaber}{telefon}"],
            'en' => ['A short call?', "{anrede}\n\nif you like, we can talk about your website for 15 minutes – by phone or video, with no obligation. Just reply with a time that suits you.{termin}\n\nKind regards\n{inhaber}{telefon}"],
        ],
        5 => [
            'de' => ['Meine letzte Nachricht dazu', "{anrede}\n\nich möchte Ihnen nicht zur Last fallen – deshalb ist das meine letzte Nachricht zu diesem Thema. Die Analyse bleibt hier abrufbar:\n{analyse}\n\nWenn Sie später einmal an Ihrer Website arbeiten möchten, antworten Sie einfach auf diese Mail.\n\nViele Grüße\n{inhaber}\n{absender}"],
            'it' => ['Il mio ultimo messaggio', "{anrede}\n\nnon voglio disturbarla oltre: questo è il mio ultimo messaggio sull’argomento. L’analisi resta disponibile qui:\n{analyse}\n\nSe in futuro vorrà lavorare al suo sito, risponda semplicemente a questa e-mail.\n\nCordiali saluti\n{inhaber}\n{absender}"],
            'en' => ['My last message on this', "{anrede}\n\nI don’t want to be a bother, so this is my last message on the subject. The analysis remains available here:\n{analyse}\n\nIf you’d like to work on your website at some point, simply reply to this email.\n\nKind regards\n{inhaber}\n{absender}"],
        ],
    ];

    public const SCHRITT_NAME = [1 => 'Analyse zusenden', 2 => 'Kurze Nachfrage', 3 => 'Beispiele und Preis', 4 => 'Einladung zum Gespräch', 5 => 'Letzte Nachricht'];

    /** Der alte Wortlaut von Schritt 1 (ohne persönlichen Bereich): Wer ihn nie geändert hat, bekommt den neuen. */
    private const SCHRITT1_ALT = [
        'de' => '9d63a652', 'it' => 'dde5575e', 'en' => 'fea4d86a',
    ];

    /** Fehlende Ausgangstexte als Entwurf anlegen. Vorhandene bleiben unangetastet -- außer Schritt 1 im unveränderten alten Wortlaut. */
    public static function vorlagenAnlegen(): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM akq_folge_vorlagen WHERE schritt = 1 AND text NOT LIKE '%{dashboard}%'") as $z) {
            $sp = (string) $z['sprache'];
            if (!isset(self::TEXTE[1][$sp]) || substr(hash('crc32b', (string) $z['text']), 0, 8) !== (self::SCHRITT1_ALT[$sp] ?? '')) { continue; }
            Db::update('akq_folge_vorlagen', (int) $z['id'], ['text' => self::TEXTE[1][$sp][1], 'status' => 'entwurf', 'fassung' => (int) $z['fassung'] + 1,
                'freigegeben_von' => null, 'freigegeben_am' => null]);
            Akquise::protokoll(null, 'folge', 'Folge-Mail Schritt 1 (' . strtoupper($sp) . '): Link zum persönlichen Bereich ergänzt, neue Fassung ' . ((int) $z['fassung'] + 1));
        }
        foreach (self::TEXTE as $schritt => $je) {
            foreach ($je as $sp => [$betreff, $text]) {
                $n += Db::run('INSERT IGNORE INTO akq_folge_vorlagen (schritt, sprache, betreff, text) VALUES (?, ?, ?, ?)', [$schritt, $sp, $betreff, $text])->rowCount();
            }
        }
        return $n;
    }

    /** @return array<int, array<string, array>> schritt => sprache => Zeile */
    public static function vorlagen(): array
    {
        self::vorlagenAnlegen();
        $out = [];
        foreach (Db::all('SELECT * FROM akq_folge_vorlagen ORDER BY schritt, FIELD(sprache, "it", "de", "en")') as $z) { $out[(int) $z['schritt']][(string) $z['sprache']] = $z; }
        return $out;
    }

    public static function vorlageSpeichern(int $id, string $betreff, string $text): void
    {
        $betreff = trim($betreff); $text = trim(str_replace("\r\n", "\n", $text));
        if (mb_strlen($betreff) < 3 || mb_strlen($text) < 20) { throw new RuntimeException('Betreff und Text dürfen nicht leer sein.'); }
        if (preg_match_all('~\{[a-z_]+\}~', $betreff . ' ' . $text, $m)) {
            $fremd = array_diff(array_unique($m[0]), self::PLATZHALTER);
            if ($fremd) { throw new RuntimeException('Unbekannter Platzhalter: ' . implode(', ', $fremd)); }
        }
        $alt = Db::one('SELECT * FROM akq_folge_vorlagen WHERE id = ?', [$id]);
        if (!$alt) { throw new RuntimeException('Vorlage nicht gefunden.'); }
        if ($alt['betreff'] === mb_substr($betreff, 0, 190) && $alt['text'] === $text) { return; }
        Db::update('akq_folge_vorlagen', $id, ['betreff' => mb_substr($betreff, 0, 190), 'text' => $text, 'status' => 'entwurf',
            'fassung' => (int) $alt['fassung'] + 1, 'freigegeben_von' => null, 'freigegeben_am' => null]);
        Events::pruefspur('akquise_folge_vorlage', 'akq_folge_vorlagen', $id, ['betreff' => $alt['betreff'], 'fassung' => $alt['fassung']], ['betreff' => $betreff, 'fassung' => (int) $alt['fassung'] + 1]);
    }

    public static function freigeben(int $id): void
    {
        $v = Db::one('SELECT * FROM akq_folge_vorlagen WHERE id = ?', [$id]);
        if (!$v) { throw new RuntimeException('Vorlage nicht gefunden.'); }
        $wer = Auth::angemeldet() ? Auth::name() : 'System';
        Db::update('akq_folge_vorlagen', $id, ['status' => 'freigegeben', 'freigegeben_von' => $wer, 'freigegeben_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('akquise_folge_freigabe', 'akq_folge_vorlagen', $id, [], ['schritt' => $v['schritt'], 'sprache' => $v['sprache'], 'fassung' => $v['fassung']]);
        Akquise::protokoll(null, 'folge', 'Folge-Mail freigegeben: Schritt ' . $v['schritt'] . ' (' . strtoupper((string) $v['sprache']) . '), Fassung ' . $v['fassung']);
    }

    /**
     * Folge starten -- nach der bestätigten Einwilligung. Läuft schon eine
     * (oder lief eine), passiert nichts: Eine zweite Folge an denselben
     * Betrieb gibt es nicht.
     */
    public static function starten(int $firmaId): bool
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f || (int) $f['gesperrt'] === 1 || trim((string) $f['einwilligung']) === '' || empty($f['email'])) { return false; }
        if (Db::wert('SELECT id FROM akq_folgen WHERE firma_id = ?', [$firmaId], null) !== null) { return false; }
        $sprache = AkquiseText::spracheFuer($f);
        $ew = Db::one("SELECT sprache FROM akq_einwilligungen WHERE firma_id = ? AND status = 'bestaetigt' ORDER BY id DESC LIMIT 1", [$firmaId]);
        if ($ew && in_array($ew['sprache'], ['de', 'it', 'en'], true)) { $sprache = (string) $ew['sprache']; }
        try {
            Db::insert('akq_folgen', ['firma_id' => $firmaId, 'sprache' => $sprache, 'status' => 'laeuft', 'schritt' => 0,
                'naechst_am' => date('Y-m-d H:i:s'), 'gestartet_am' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            if (Db::doppelt($e, 'uq_akq_folge_firma')) { return false; }
            throw $e;
        }
        Akquise::protokoll($firmaId, 'folge', 'Folge-Mails gestartet (' . strtoupper($sprache) . ') — Schritt 1 ist fällig');
        return true;
    }

    /** Was beendet oder pausiert die Folge gerade? null = darf weiter. @return null|array{0:string,1:string} [status, grund] */
    public static function hindernis(array $folge, array $f): ?array
    {
        if ((int) $f['gesperrt'] === 1 || in_array((string) $f['kontakt_status'], ['abgelehnt', 'gesperrt'], true)) { return ['beendet', 'Abgemeldet oder gesperrt']; }
        if ((string) $f['kontakt_status'] === 'kunde' || (int) $f['bestandskunde'] === 1) { return ['beendet', 'Kunde geworden — der Kundenweg übernimmt']; }
        if (!empty($f['dashboard_am'])) { return ['beendet', 'Persönlichen Bereich geöffnet — der Kundenweg übernimmt']; }
        if (trim((string) $f['einwilligung']) === '' || empty($f['email'])) { return ['beendet', 'Keine Einwilligung mehr']; }
        $antwort = Db::wert('SELECT COUNT(*) FROM akq_antworten WHERE firma_id = ? AND eingang_am > ?', [(int) $f['id'], (string) $folge['gestartet_am']], 0);
        if ((int) $antwort > 0) { return ['pausiert', 'Antwort erhalten — ab jetzt schreibst du selbst']; }
        return null;
    }

    /** Platzhalter füllen. */
    public static function fuellen(string $s, array $f, string $sprache): string
    {
        $abs = AkquiseText::absender();
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $pfad = ['it' => '/', 'de' => '/de/', 'en' => '/en/'][$sprache] ?? '/';
        return strtr($s, [
            '{anrede}' => self::anrede($f, $sprache),
            '{firma}' => (string) $f['name'],
            '{website}' => (string) ($f['domain'] ?: ($f['url'] ?: $f['name'])),
            '{analyse}' => self::analyseLink($f, $sprache),
            '{bedarf}' => $basis . '/bedarf.php?lang=' . $sprache,
            '{beispiele}' => $basis . $pfad . '#work',
            '{termin}' => self::terminSatz($sprache),
            '{inhaber}' => (string) $abs['inhaber'],
            '{absender}' => (string) $abs['firma'],
            '{telefon}' => trim((string) $abs['telefon']) !== '' ? "\n" . trim((string) $abs['telefon']) : '',
            '{dashboard}' => str_contains($s, '{dashboard}') ? self::dashboardLink($f, $sprache) : '',
        ]);
    }

    /** Der persönliche Bereich (V2): vorbereitet für genau diese Adresse; ohne E-Mail der Analyse-Link. */
    public static function dashboardLink(array $f, string $sprache): string
    {
        try {
            require_once __DIR__ . '/Zugang.php';
            $l = !empty($f['email']) && !empty($f['id']) ? Zugang::vorbereiten((string) $f['email'], $sprache, (int) $f['id'], (string) ($f['name'] ?? '')) : null;
            if ($l !== null) { return $l; }
        } catch (Throwable $e) { }
        return self::analyseLink($f, $sprache);
    }

    /**
     * Automatische Freigabe (28.09.2026, Uwe: Ja zu V4): nur Italienisch, nur
     * wenn der Schalter an ist und derselbe Prüfer wie bei jeder Erstansprache
     * nichts findet. Geprüft wird mit neutralen Beispielwerten statt echter
     * Daten -- sonst zählte eine Uhrzeit aus {termin} als unbelegte Zahl.
     * @return list<string> Beanstandungen, leer = freigabefähig
     */
    public static function autoMaengel(array $v): array
    {
        $sp = (string) $v['sprache'];
        $abs = AkquiseText::absender();
        $bsp = ['{anrede}' => 'Buongiorno Maria Rossi,', '{firma}' => 'Trattoria Esempio', '{website}' => 'trattoriaesempio.it',
                '{analyse}' => 'https://vecom-design.it/analyse.php?t=esempio', '{bedarf}' => 'https://vecom-design.it/bedarf.php?lang=' . $sp,
                '{beispiele}' => 'https://vecom-design.it/#work', '{termin}' => '', '{inhaber}' => (string) $abs['inhaber'], '{absender}' => (string) $abs['firma'],
                '{telefon}' => "\n" . (string) $abs['telefon'], '{dashboard}' => 'https://vecom-design.it/zugang.php?t=esempio'];
        $zusatz = ['de' => "\n\nKeine weiteren Nachrichten: https://vecom-design.it/widerspruch.php", 'it' => "\n\nNon ricevere altri messaggi (non desiderate): https://vecom-design.it/widerspruch.php",
                   'en' => "\n\nNo further messages (unsubscribe): https://vecom-design.it/widerspruch.php"][$sp] ?? '';
        /* „15 minuti“ ist keine Behauptung über den Betrieb -- die einzige Zahl, die ein Folge-Text ohne Beleg tragen darf. */
        $text = (string) preg_replace('~\b15 (minuti|Minuten|minutes)\b~u', 'un quarto d’ora', strtr((string) $v['text'], $bsp)) . $zusatz;
        return AkquiseText::pruefen(strtr((string) $v['betreff'], $bsp), $text, $sp, [], 'email');
    }

    /** Freigabe ohne Klick, wenn erlaubt und ohne Beanstandung. @return bool freigegeben */
    public static function autoFreigeben(array $v): bool
    {
        if ((string) $v['sprache'] !== 'it' || $v['status'] === 'freigegeben' || !AkquiseGate::schalterSelbst('autofrei')) { return false; }
        if (self::autoMaengel($v) !== []) { return false; }
        Db::update('akq_folge_vorlagen', (int) $v['id'], ['status' => 'freigegeben', 'freigegeben_von' => 'System (automatisch, Prüfung ohne Beanstandung)', 'freigegeben_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('akquise_folge_freigabe', 'akq_folge_vorlagen', (int) $v['id'], [], ['schritt' => $v['schritt'], 'sprache' => $v['sprache'], 'fassung' => $v['fassung'], 'automatisch' => true]);
        Akquise::protokoll(null, 'folge', 'Folge-Mail automatisch freigegeben: Schritt ' . $v['schritt'] . ' (IT), Fassung ' . $v['fassung'] . ' — die Textprüfung fand nichts');
        return true;
    }

    /**
     * Anrede mit Namen (27.09.2026, Uwe: „Anrede mit Namen einbauen“). Wie in
     * den Erstansprachen: „Guten Tag Maria Rossi,“ / „Buongiorno …,“ / „Hello …,“ --
     * ohne Herr/Frau, weil das Geschlecht nicht bekannt ist. Der Name kommt vom
     * Ansprechpartner an der Firma, sonst von dem, der den Website-Check
     * angefragt hat. Ohne brauchbaren Namen bleibt es beim bloßen Gruß.
     */
    public static function anrede(array $f, string $sprache): string
    {
        $name = self::name(trim((string) ($f['ansprechpartner'] ?? '')));
        if ($name === '' && !empty($f['id'])) {
            try { $name = self::name((string) Db::wert('SELECT name FROM akq_checks WHERE firma_id = ? AND name IS NOT NULL ORDER BY id DESC LIMIT 1', [(int) $f['id']], '')); }
            catch (Throwable $e) { $name = ''; }
        }
        $gruss = ['de' => 'Guten Tag', 'it' => 'Buongiorno', 'en' => 'Hello'][$sprache] ?? 'Buongiorno';
        return $gruss . ($name !== '' ? ' ' . $name : '') . ',';
    }

    /** Ein Name, der in eine Anrede passt -- oder ''. Keine Adressen, keine Ziffern, nichts Überlanges. */
    public static function name(string $roh): string
    {
        $n = trim((string) preg_replace('~\s+~u', ' ', $roh));
        if (mb_strlen($n) < 2 || mb_strlen($n) > 60 || preg_match('~[0-9@/:<>{}]|www\.~u', $n)) { return ''; }
        /* Ganz klein oder ganz groß eingetippt: „maria rossi“ → „Maria Rossi“. */
        if ($n === mb_strtolower($n) || $n === mb_strtoupper($n)) { $n = mb_convert_case(mb_strtolower($n), MB_CASE_TITLE, 'UTF-8'); }
        return $n;
    }

    /** Eingeschaltete Analyse-Seite, sonst der letzte Website-Check, sonst der Check selbst. */
    public static function analyseLink(array $f, string $sprache): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        try {
            $a = Db::one('SELECT * FROM akq_analysen WHERE firma_id = ? AND aktiv = 1 AND (gueltig_bis IS NULL OR gueltig_bis >= CURDATE()) ORDER BY id DESC LIMIT 1', [(int) $f['id']]);
            if ($a) { require_once __DIR__ . '/AkquiseAnalyse.php'; return AkquiseAnalyse::adresse($a); }
        } catch (Throwable $e) { }
        $t = Db::wert("SELECT token FROM akq_checks WHERE firma_id = ? ORDER BY id DESC LIMIT 1", [(int) $f['id']], null);
        if ($t !== null) { return $basis . '/website-check.php?t=' . $t . '&lang=' . $sprache; }
        return $basis . '/website-check.php?lang=' . $sprache;
    }

    /** Satz mit dem Buchungslink -- leer, solange es keine freie Zeit gibt. Wird von der Terminbuchung ersetzt. */
    public static function terminSatz(string $sprache): string
    {
        if (!is_file(__DIR__ . '/AkquiseTermin.php')) { return ''; }
        require_once __DIR__ . '/AkquiseTermin.php';
        return AkquiseTermin::satzFuerMail($sprache);
    }

    /**
     * Der Cronlauf. Schickt fällige Schritte, so weit Schalter, Gate und
     * Grenzen es zulassen.
     * @return array{geschickt:int, simuliert:int, beendet:int, pausiert:int, wartet:int, hinweis?:string}
     */
    public static function lauf(): array
    {
        $bilanz = ['geschickt' => 0, 'simuliert' => 0, 'beendet' => 0, 'pausiert' => 0, 'wartet' => 0];
        /* Erst aufräumen -- auch bei ausgeschaltetem Schalter: Eine Antwort
           pausiert sofort, eine Abmeldung beendet sofort, nicht erst, wenn
           der nächste Schritt fällig wäre. Das verschickt nichts. */
        foreach (Db::all("SELECT fo.*, f.gesperrt, f.kontakt_status, f.bestandskunde, f.einwilligung, f.email, f.dashboard_am, f.id AS fid
                            FROM akq_folgen fo JOIN akq_firmen f ON f.id = fo.firma_id WHERE fo.status = 'laeuft'") as $fo) {
            $h = self::hindernis($fo, ['id' => $fo['fid']] + $fo);
            if ($h === null) { continue; }
            Db::update('akq_folgen', (int) $fo['id'], ['status' => $h[0], 'grund' => $h[1], 'naechst_am' => null]);
            Akquise::protokoll((int) $fo['firma_id'], 'folge', 'Folge-Mails ' . ($h[0] === 'pausiert' ? 'pausiert' : 'beendet') . ': ' . $h[1]);
            $bilanz[$h[0]]++;
        }
        if (!AkquiseGate::schalter('folge')) { return $bilanz + ['hinweis' => 'Folge-Mails sind ausgeschaltet.']; }
        if (AkquiseGate::grenzen()['stop']) { return $bilanz + ['hinweis' => 'Notbremse gezogen.']; }
        $test = AkquiseGate::testbetrieb();
        /* Zeit aus PHP, nicht NOW(): Die Datenbank läuft in UTC, gespeichert wird Ortszeit. */
        $faellig = Db::all("SELECT * FROM akq_folgen WHERE status = 'laeuft' AND naechst_am <= ? ORDER BY naechst_am LIMIT " . (self::JE_LAUF * 3), [date('Y-m-d H:i:s')]);
        $geschickt = 0;
        foreach ($faellig as $fo) {
            if ($geschickt >= self::JE_LAUF) { break; }
            $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $fo['firma_id']]);
            if (!$f) { continue; }
            $h = self::hindernis($fo, $f);
            if ($h !== null) {
                Db::update('akq_folgen', (int) $fo['id'], ['status' => $h[0], 'grund' => $h[1], 'naechst_am' => null]);
                Akquise::protokoll((int) $f['id'], 'folge', 'Folge-Mails ' . ($h[0] === 'pausiert' ? 'pausiert' : 'beendet') . ': ' . $h[1]);
                $bilanz[$h[0]]++;
                continue;
            }
            $schritt = (int) $fo['schritt'] + 1;
            if (!isset(self::TAGE[$schritt])) {
                Db::update('akq_folgen', (int) $fo['id'], ['status' => 'beendet', 'grund' => 'Alle Schritte verschickt', 'naechst_am' => null]);
                $bilanz['beendet']++;
                continue;
            }
            if ($test && (int) $fo['simuliert'] >= $schritt) { continue; }   // diesen Schritt schon durchgespielt
            $v = Db::one("SELECT * FROM akq_folge_vorlagen WHERE schritt = ? AND sprache = ? AND status = 'freigegeben'", [$schritt, (string) $fo['sprache']]);
            if (!$v && ($roh = Db::one('SELECT * FROM akq_folge_vorlagen WHERE schritt = ? AND sprache = ?', [$schritt, (string) $fo['sprache']])) && self::autoFreigeben($roh)) {
                $v = Db::one('SELECT * FROM akq_folge_vorlagen WHERE id = ?', [(int) $roh['id']]);
            }
            /* WhatsApp statt Mail (28.09.2026, V3): wer auch WhatsApp erlaubt hat und für
               den Schritt eine bei Meta genehmigte Vorlage da ist. Sonst die Mail. */
            $wa = null;
            if (AkquiseGate::schalter('whatsapp') && (int) ($fo['wa_schritt'] ?? 0) < $schritt && AkquiseGate::einwilligungDeckt($f, 'whatsapp')) {
                require_once __DIR__ . '/WhatsAppCloud.php';
                if (WhatsAppCloud::bereit() && AkquiseGate::versandSperre($f, true) === null) {
                    $wa = WhatsAppCloud::folgeSenden($f, $schritt, (string) $fo['sprache'],
                        $schritt === 4 ? rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/termin.php?lang=' . $fo['sprache'] : self::dashboardLink($f, (string) $fo['sprache']));
                }
            }
            if ($wa !== null && $wa['ok']) {
                if (!empty($wa['simuliert'])) {
                    Db::update('akq_folgen', (int) $fo['id'], ['simuliert' => $schritt, 'grund' => 'Testbetrieb: Schritt ' . $schritt . ' per WhatsApp simuliert']);
                    $bilanz['simuliert']++;
                    continue;
                }
                $jetzt = time(); $naechster = $schritt + 1;
                Db::update('akq_folgen', (int) $fo['id'], [
                    'schritt' => $schritt, 'wa_schritt' => $schritt, 'letzte_am' => date('Y-m-d H:i:s', $jetzt), 'grund' => isset(self::TAGE[$naechster]) ? null : 'Alle Schritte verschickt',
                    'status' => isset(self::TAGE[$naechster]) ? 'laeuft' : 'beendet',
                    'naechst_am' => isset(self::TAGE[$naechster]) ? date('Y-m-d H:i:s', $jetzt + (self::TAGE[$naechster] - self::TAGE[$schritt]) * 86400) : null,
                ]);
                Db::update('akq_firmen', (int) $f['id'], ['versand_status' => 'gesendet', 'kontakt_status' => in_array((string) $f['kontakt_status'], ['geantwortet', 'kunde'], true) ? $f['kontakt_status'] : 'kontaktiert']);
                Akquise::protokoll((int) $f['id'], 'folge', 'Folge ' . $schritt . '/5 per WhatsApp verschickt: „' . self::SCHRITT_NAME[$schritt] . '“', ['versand' => $wa['id'] ?? null]);
                $bilanz['geschickt']++;
                $geschickt++;
                continue;
            }
            if ($wa !== null && !empty($wa['grund'])) { Akquise::protokoll((int) $f['id'], 'folge', 'WhatsApp für Schritt ' . $schritt . ' nicht möglich (' . $wa['grund'] . ') — es geht die Mail.'); }
            if (!$v) {
                Db::update('akq_folgen', (int) $fo['id'], ['grund' => 'Wartet: Text für Schritt ' . $schritt . ' (' . strtoupper((string) $fo['sprache']) . ') ist nicht freigegeben']);
                $bilanz['wartet']++;
                continue;
            }
            $gate = AkquiseGate::pruefen($f, 'email');
            if ($gate['status'] !== AkquiseGate::ERLAUBT) {
                Db::update('akq_folgen', (int) $fo['id'], ['status' => 'pausiert', 'grund' => 'Gate: ' . AkquiseGate::STATUS[$gate['status']] . ' — ' . ($gate['gruende'][0] ?? ''), 'naechst_am' => null]);
                $bilanz['pausiert']++;
                continue;
            }
            $sperre = AkquiseGate::versandSperre($f, true);
            if ($sperre !== null) {
                Db::update('akq_folgen', (int) $fo['id'], ['grund' => 'Wartet: ' . $sperre]);
                $bilanz['wartet']++;
                break;   // Grenzen gelten für alle -- der nächste Lauf versucht es wieder
            }
            try {
                $r = AkquiseVersand::rausschicken($f, self::fuellen((string) $v['betreff'], $f, (string) $fo['sprache']),
                    self::fuellen((string) $v['text'], $f, (string) $fo['sprache']), (string) $fo['sprache'], null, $gate['status'],
                    'Folge-Mail ' . $schritt . '/5 (' . self::SCHRITT_NAME[$schritt] . ', Fassung ' . $v['fassung'] . ')');
            } catch (Throwable $e) {
                Db::update('akq_folgen', (int) $fo['id'], ['grund' => 'Versand gescheitert: ' . mb_substr($e->getMessage(), 0, 180)]);
                $bilanz['wartet']++;
                break;
            }
            if ($r['simuliert']) {
                Db::update('akq_folgen', (int) $fo['id'], ['simuliert' => $schritt, 'grund' => 'Testbetrieb: Schritt ' . $schritt . ' simuliert']);
                $bilanz['simuliert']++;
                continue;
            }
            $jetzt = time();
            $naechster = $schritt + 1;
            Db::update('akq_folgen', (int) $fo['id'], [
                'schritt' => $schritt, 'letzte_am' => date('Y-m-d H:i:s', $jetzt), 'grund' => null,
                'status' => isset(self::TAGE[$naechster]) ? 'laeuft' : 'beendet',
                'naechst_am' => isset(self::TAGE[$naechster]) ? date('Y-m-d H:i:s', $jetzt + (self::TAGE[$naechster] - self::TAGE[$schritt]) * 86400) : null,
            ]);
            if (!isset(self::TAGE[$naechster])) { Db::update('akq_folgen', (int) $fo['id'], ['grund' => 'Alle Schritte verschickt']); }
            Db::update('akq_firmen', (int) $f['id'], ['versand_status' => 'gesendet', 'kontakt_status' => in_array((string) $f['kontakt_status'], ['geantwortet', 'kunde'], true) ? $f['kontakt_status'] : 'kontaktiert']);
            Akquise::protokoll((int) $f['id'], 'folge', 'Folge-Mail ' . $schritt . '/5 verschickt: „' . self::SCHRITT_NAME[$schritt] . '“', ['versand' => $r['id']]);
            $bilanz['geschickt']++;
            $geschickt++;
        }
        return $bilanz;
    }

    public static function pausieren(int $id, string $grund = 'Von Hand pausiert'): void
    {
        Db::run("UPDATE akq_folgen SET status = 'pausiert', grund = ?, naechst_am = NULL WHERE id = ? AND status = 'laeuft'", [mb_substr($grund, 0, 255), $id]);
    }

    /** Weiter nach einer Pause: der nächste Schritt ist ab jetzt fällig. */
    public static function fortsetzen(int $id): void
    {
        $fo = Db::one('SELECT * FROM akq_folgen WHERE id = ?', [$id]);
        if (!$fo || $fo['status'] !== 'pausiert') { return; }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $fo['firma_id']]);
        /* Nach einer Antwort zählt nur eine Antwort ab jetzt als neue -- sonst hielte die alte die Folge ewig an. */
        $h = $f ? self::hindernis(['gestartet_am' => date('Y-m-d H:i:s')] + $fo, $f) : ['beendet', 'Betrieb fehlt'];
        if ($h !== null) { throw new RuntimeException('Kann nicht weiterlaufen: ' . $h[1]); }
        Db::update('akq_folgen', $id, ['status' => 'laeuft', 'grund' => null, 'naechst_am' => date('Y-m-d H:i:s'), 'gestartet_am' => date('Y-m-d H:i:s')]);
        Akquise::protokoll((int) $fo['firma_id'], 'folge', 'Folge-Mails von Hand fortgesetzt');
    }

    public static function beenden(int $id, string $grund = 'Von Hand beendet'): void
    {
        $fo = Db::one('SELECT * FROM akq_folgen WHERE id = ?', [$id]);
        if (!$fo || $fo['status'] === 'beendet') { return; }
        Db::update('akq_folgen', $id, ['status' => 'beendet', 'grund' => mb_substr($grund, 0, 255), 'naechst_am' => null]);
        Akquise::protokoll((int) $fo['firma_id'], 'folge', 'Folge-Mails beendet: ' . $grund);
    }

    /** @return list<array> für die Verwaltung */
    public static function liste(int $n = 50): array
    {
        return Db::all('SELECT fo.*, f.name, f.stadt, f.email FROM akq_folgen fo JOIN akq_firmen f ON f.id = fo.firma_id
                         ORDER BY FIELD(fo.status, "laeuft", "pausiert", "beendet"), fo.naechst_am IS NULL, fo.naechst_am, fo.id DESC LIMIT ' . max(1, min(200, $n)));
    }
}
