<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Telegram.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/TelegramKunde.php';
require_once __DIR__ . '/TelegramAdmin.php';

/* ==========================================================================
   TelegramBot.php — was der Bot sagt und tut (30.09.2026, Stufe 1:
   Interessenten).

   KEINE ZWEITE WELT

   Der Bot hat keinen eigenen Preisrechner, keinen eigenen Fragebogen und
   keinen eigenen Posteingang:

     Fragen und Antworten   Baukasten::FRAGEN / SCHRITTE (wie bedarf.php)
     Antworten speichern    Bedarf::starten / speichern (Tabelle bedarf)
     Richtwert              Baukasten::live / rechnen / spanne
     Absenden               Bedarf::absenden -> Anfrage::annehmen
                            (Kunde, Eingangsmail, Meldung, Zuruf aufs Handy)
     Beratungswunsch        Anfrage::annehmen
     Partner / Empfehlung   Partner::zuordnen / Empfehlung über Bedarf::absenden
     Datenschutz-Nachweis   Zustimmung::festhalten

   Ändert Uwe im Baukasten einen Preis oder eine Frage, zeigt der Bot es beim
   nächsten Klick — ohne dass hier eine Zeile geändert wird.

   KEINE ERFUNDENEN ZAHLEN

   Eine Zahl erscheint nur, wenn der Baukasten sie rechnet, die Einstellung
   bedarf_spanne_zeigen an ist und genug gesagt wurde (Baukasten::genugGesagt).
   Sonst: der Satz, dass wir keinen falschen Preis nennen wollen.

   KEINE KI-SCHLEIFE

   Stufe 1 arbeitet mit Knöpfen. Freier Text, der nach einem Menschen fragt
   („Mensch“, „Beratung“, „Uwe“ …), führt sofort zur persönlichen Beratung;
   anderer freier Text bekommt einen Satz und das Menü. Eine KI (Manuela)
   kommt erst, wenn ein Anbieter dafür entschieden ist.

   SPRACHE

   Beim ersten Kontakt schlagen wir die Sprache der Telegram-Oberfläche vor.
   Gewählt wird per Knopf, und danach bleibt sie — sie wechselt nur über den
   Sprachknopf oder /sprache, nie anhand einzelner Wörter.
   ========================================================================== */
final class TelegramBot
{
    /** Fassung des Datenschutzhinweises. Ändert sich der Wortlaut, hier hochzählen. */
    public const FASSUNG = 'tg1';

    /** Flutbremse: Updates je Minute und Chat, und für alle Chats zusammen. */
    public const GRENZE_CHAT = 30;
    public const GRENZE_ALLE = 600;

    /** Längster freier Text, den wir annehmen. */
    public const TEXT_MAX = 1000;

    /** So lange bleibt ein Chat stehen, der nie etwas abgeschickt hat. */
    public const AUFHEBEN_TAGE = 90;

    /** Wörter, die sofort zu einem Menschen führen (kleingeschrieben, ganze Wörter). */
    public const MENSCH = ['mensch', 'menschen', 'beratung', 'mitarbeiter', 'uwe', 'persönlich', 'persoenlich', 'rückruf', 'rueckruf',
        'operatore', 'persona', 'umano', 'consulenza', 'richiamata', 'human', 'person', 'agent', 'advice', 'callback', 'someone'];

    /* ============================ TEXTE ================================
       Du oder Sie: Die Fragen kommen aus dem Baukasten und siezen (Lei/Sie).
       Damit der Bot nicht mitten im Gespräch wechselt, siezt er auch. */
    /** Die Texte stehen, wie alle Kundentexte, in Texte.php (CLAUDE.md: „Kundentexte dreisprachig, gesammelt in Texte.php“). */
    public const T = Texte::TELEGRAM;

    /** Die Sprachwahl beim ersten Kontakt — in allen drei Sprachen, weil wir sie noch nicht kennen. */
    public const SPRACHWAHL = "<b>Vecom Design</b> 👋\n\n🇮🇹 In quale lingua preferisce continuare?\n🇩🇪 In welcher Sprache möchten Sie weitermachen?\n🇬🇧 Which language would you like to continue in?";
    public const SPRACHEN = ['it' => '🇮🇹 Italiano', 'de' => '🇩🇪 Deutsch', 'en' => '🇬🇧 English'];

    /** WhatsApp nur für Nachrichten — derselbe Link wie auf der Website. */
    public const WHATSAPP = 'https://wa.me/message/ZA5MG7SV4WEQM1';

    /** Die Vorbelegung je Einstieg: Wer „Neue Website“ drückt, hat noch keine. */
    private const EINSTIEG = ['neu' => ['bestand' => 'neu'], 'besser' => [], 'preis' => []];

    /** Letzte Antwort-ID beim Aufruf — nur für die Kette. */
    public static array $gesendet = [];

    /* ======================== EINGANG =============================== */

    /**
     * Ein Update von Telegram verarbeiten.
     *
     * @return string kurzer Vermerk, was geschehen ist (für Protokoll und Kette)
     */
    public static function verarbeiten(array $u): string
    {
        $cq  = is_array($u['callback_query'] ?? null) ? $u['callback_query'] : null;
        $msg = $cq ? (array) ($cq['message'] ?? []) : (is_array($u['message'] ?? null) ? $u['message'] : null);
        $von = $cq ? (array) ($cq['from'] ?? []) : (array) ($msg['from'] ?? []);
        if ($msg === null || !$von) { return 'ignoriert'; }
        if (!empty($von['is_bot'])) { return 'ignoriert'; }

        // Nur private Chats. In einer Gruppe hätte der Bot fremde Menschen vor sich.
        $chatTyp = (string) ($msg['chat']['type'] ?? ($cq ? 'private' : ''));
        if ($chatTyp !== 'private') { return 'ignoriert'; }
        $chatId = (int) ($msg['chat']['id'] ?? $von['id'] ?? 0);
        if ($chatId === 0) { return 'ignoriert'; }

        if (!self::alleDuerfen()) {
            if ($cq) { self::antwortKnopf((string) ($cq['id'] ?? '')); }
            return 'gebremst';
        }

        $vorschlag = substr(strtolower((string) ($von['language_code'] ?? '')), 0, 2);
        $c = self::chat($chatId, in_array($vorschlag, ['it', 'de', 'en'], true) ? $vorschlag : null);

        $takt = self::takt($c);
        if ($takt !== 'ok') {
            if ($cq) { self::antwortKnopf((string) ($cq['id'] ?? '')); }
            if ($takt === 'erstmals') { self::senden($c, self::t($c, 'langsamer')); }
            return 'gebremst';
        }

        if ($cq) {
            $daten = (string) ($cq['data'] ?? '');
            $msgId = isset($cq['message']['message_id']) ? (int) $cq['message']['message_id'] : null;
            return self::knopf($c, $daten, (string) ($cq['id'] ?? ''), $msgId);
        }
        return self::nachricht($c, $msg);
    }

    /* ======================== NACHRICHTEN =========================== */

    private static function nachricht(array $c, array $m): string
    {
        if (!isset($m['text']) || !is_string($m['text'])) {
            if (!empty($c['kunde_verbunden']) && $c['sprache'] !== null) { return self::dateiAngekommen($c, $m); }
            self::zeigen($c, self::t($c, 'nurText'), [[self::k($c, 'k_menu', 'm:menu')]]);
            return 'kein_text';
        }
        $text = trim(str_replace("\0", '', $m['text']));
        if (mb_strlen($text) > self::TEXT_MAX) {
            self::senden($c, self::t($c, 'zuLang'));
            return 'zu_lang';
        }

        // Befehle: /start, /menu, … — auch mit @botname dahinter.
        if (preg_match('~^/([a-z_]{1,32})(?:@\w+)?(?:\s+(.*))?$~is', $text, $b)) {
            $cmd = strtolower($b[1]);
            $arg = trim((string) ($b[2] ?? ''));
            return self::befehl($c, $cmd, $arg);
        }

        // Noch nie eine Sprache gewählt? Dann zuerst das.
        if ($c['sprache'] === null) {
            self::zeigeSprachwahl($c);
            return 'sprache';
        }

        switch ($c['stand']) {
            case 'name':      return self::eingabeName($c, $text);
            case 'email':     return self::eingabeEmail($c, $text);
            case 'nachricht': return self::eingabeNachricht($c, $text);
            case 'kundennachricht':
                if (!empty($c['kunde_verbunden'])) { return self::kundenNachricht($c, $text); }
                break;
        }

        if (self::willMenschen($text)) {
            self::zeigeMensch($c);
            return 'mensch';
        }
        self::zeigen($c, self::t($c, 'verstehe'), self::menuKnoepfe($c));
        return 'verstehe';
    }

    /**
     * Wohin ein Knopf im Kanal-Menü springen darf (?start=kanal-WORT).
     * Links das Wort im Link, rechts der Menüpunkt. Nur Buchstaben, weil
     * das Wort durch dieselbe Prüfung geht wie jede andere Quelle.
     */
    public const SPRUENGE = [
        'neu' => 'neu', 'besser' => 'besser', 'preis' => 'preis', 'pruefen' => 'pruefen',
        'logo' => 'logo', 'dreid' => '3d', 'hosting' => 'hosting', 'kunde' => 'kunde', 'mensch' => 'mensch',
    ];

    private static function befehl(array $c, string $cmd, string $arg): string
    {
        switch ($cmd) {
            case 'start':
                // Verbindungslink aus dem persönlichen Bereich: t.me/bot?start=k_CODE (Stufe 2).
                if (preg_match('/^k_([0-9a-f]{32})$/', $arg, $km)) { return self::verbinden($c, $km[1]); }
                // Verbindungslink aus der Verwaltung: t.me/bot?start=a_CODE (Stufe 3).
                if (preg_match('/^a_([0-9a-f]{32})$/', $arg, $am)) { return self::verwaltungVerbinden($c, $am[1]); }
                // Start-Link: t.me/bot?start=p_CODE (Partner) oder e_CODE (Empfehlung).
                // Die erste Quelle zählt — wie auf der Website.
                if ($arg !== '' && preg_match('/^[pe]_[A-Za-z0-9]{5,16}$/', $arg) && empty($c['quelle_code'])) {
                    $c = self::setzen($c, ['quelle_code' => strtolower($arg[0]) . '_' . strtoupper(substr($arg, 2))]);
                }
                // Knopf aus dem Menü-Beitrag im Kanal: ?start=kanal-preis — Quelle
                // „kanal“, und der Bot springt direkt zu diesem Punkt (30.09.2026).
                $sprung = null;
                if (preg_match('/^([a-z]{2,12})-([a-z]{2,10})$/', $arg, $sm) && isset(self::SPRUENGE[$sm[2]])) {
                    $arg = $sm[1];
                    $sprung = self::SPRUENGE[$sm[2]];
                }
                // Ein einfaches Wort (?start=web, ?start=kanal) sagt, woher jemand kam.
                if ($arg !== '' && preg_match('/^[a-z]{2,12}$/', $arg) && empty($c['quelle_code'])) {
                    $c = self::setzen($c, ['quelle_code' => $arg]);
                }
                if ($c['sprache'] === null) {
                    // Die Sprache wählt der Mensch — der Sprung wartet so lange.
                    if ($sprung !== null) { $c = self::setzen($c, ['stand' => 'sp_' . $sprung]); }
                    self::zeigeSprachwahl($c);
                    return 'sprache';
                }
                $c = self::setzen($c, ['stand' => 'menu']);
                if ($sprung !== null) { return self::menuPunkt($c, $sprung, null); }
                self::zeigeMenu($c);
                return 'menu';
            case 'menu': case 'menue': case 'abbrechen': case 'annulla': case 'cancel':
                if ($c['sprache'] === null) { self::zeigeSprachwahl($c); return 'sprache'; }
                $c = self::setzen($c, ['stand' => 'menu']);
                self::zeigeMenu($c);
                return 'menu';
            case 'sprache': case 'lingua': case 'language':
                self::zeigeSprachwahl($c);
                return 'sprache';
            case 'preis': case 'prezzo': case 'price':
                if ($c['sprache'] === null) { self::zeigeSprachwahl($c); return 'sprache'; }
                return self::frageStarten($c, 'preis', null);
            case 'hilfe': case 'aiuto': case 'help':
                if ($c['sprache'] === null) { self::zeigeSprachwahl($c); return 'sprache'; }
                self::zeigen($c, self::t($c, 'hilfe'), self::menuKnoepfe($c));
                return 'hilfe';
            case 'delete': case 'loeschen': case 'cancella':
                $c = self::setzen($c, ['stand' => 'loeschen']);
                self::zeigen($c, self::t($c, 'loeschenFrage'), [[self::k($c, 'k_loeschen', 'k:ja'), self::k($c, 'k_behalten', 'k:nein')]]);
                return 'loeschen_frage';
        }
        if ($c['sprache'] === null) { self::zeigeSprachwahl($c); return 'sprache'; }
        self::zeigeMenu($c);
        return 'menu';
    }

    /* ========================== KNÖPFE ============================== */

    private static function knopf(array $c, string $daten, string $cqId, ?int $msgId): string
    {
        if (!preg_match('/^[a-z]:[a-z0-9_:]{0,60}$/', $daten)) {
            self::antwortKnopf($cqId);
            return 'unbekannt';
        }
        [$art, $rest] = explode(':', $daten, 2);

        // Sprache ist immer erlaubt.
        if ($art === 'l') {
            self::antwortKnopf($cqId);
            if (!isset(self::SPRACHEN[$rest])) { return 'unbekannt'; }
            $war = $c['sprache'];
            $c = self::setzen($c, ['sprache' => $rest]);
            // Mitten im Fragebogen: dieselbe Frage in der neuen Sprache.
            if ($war !== null && $c['stand'] === 'frage') { self::zeigeFrage($c, $msgId); return 'sprache_gesetzt'; }
            // Kam jemand über einen Knopf im Kanal, geht es nach der Sprache dorthin.
            $warteSprung = str_starts_with((string) $c['stand'], 'sp_') ? substr((string) $c['stand'], 3) : '';
            $c = self::setzen($c, ['stand' => 'menu']);
            if ($war === null && in_array($warteSprung, self::SPRUENGE, true)) { return self::menuPunkt($c, $warteSprung, $msgId); }
            self::zeigeMenu($c, $msgId, $war !== null ? self::t($c, 'spracheGesetzt') . "\n\n" : '');
            return 'sprache_gesetzt';
        }
        if ($c['sprache'] === null) { self::antwortKnopf($cqId); self::zeigeSprachwahl($c, $msgId); return 'sprache'; }

        switch ($art) {
            case 'm': self::antwortKnopf($cqId); return self::menuPunkt($c, $rest, $msgId);
            case 'v':
                self::antwortKnopf($cqId);
                if (!TelegramAdmin::darfChat($c)) { self::zeigeStand($c, $msgId); return 'kein_admin'; }
                return self::verwaltungKnopf($c, $rest, $msgId);
            case 'c': case 'f':
                self::antwortKnopf($cqId);
                if (empty($c['kunde_verbunden'])) { self::zeigeStand($c, $msgId); return 'nicht_verbunden'; }
                return $art === 'c' ? self::kundeKnopf($c, $rest, $msgId) : self::dateiKnopf($c, $rest, $msgId);
            case 'q': case 't': case 'w': return self::frageKnopf($c, $art, $rest, $cqId, $msgId);
            case 'z': self::antwortKnopf($cqId); return self::zurueck($c, $msgId);
            case 'x': self::antwortKnopf($cqId); return self::abbrechen($c, $msgId);
            case 'r':
                self::antwortKnopf($cqId);
                if ($rest === 'senden') { return self::datenschutz($c, 'anfrage', null, $msgId); }
                if ($rest === 'aendern') { $c = self::setzen($c, ['stand' => 'frage', 'frage' => 0]); self::zeigeFrage($c, $msgId); return 'frage'; }
                break;
            case 'b':
                self::antwortKnopf($cqId);
                if (isset(self::T['de']['thema'][$rest])) { return self::datenschutz($c, 'beratung', $rest, $msgId); }
                break;
            case 'd':
                self::antwortKnopf($cqId);
                if ($c['stand'] === 'ds' && $rest === 'ja') {
                    $c = self::setzen($c, ['stand' => 'name', 'datenschutz_fassung' => self::FASSUNG, 'datenschutz_am' => date('Y-m-d H:i:s')]);
                    self::zeigen($c, self::t($c, 'fragName'), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId);
                    return 'name';
                }
                break;
            case 's':
                self::antwortKnopf($cqId);
                if ($c['stand'] === 'pruefen' && $rest === 'ja') { return self::absenden($c, $msgId); }
                if ($c['stand'] === 'pruefen' && $rest === 'aendern') {
                    $c = self::setzen($c, ['stand' => 'name']);
                    self::zeigen($c, self::t($c, 'fragName'), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId);
                    return 'name';
                }
                break;
            case 'k':
                self::antwortKnopf($cqId);
                if ($c['stand'] === 'loeschen' && $rest === 'ja') { return self::loeschen($c, $msgId); }
                $c = self::setzen($c, ['stand' => 'menu']);
                self::zeigeMenu($c, $msgId);
                return 'menu';
            default:
                self::antwortKnopf($cqId);
        }
        // Veralteter oder unpassender Knopf: zeigen, wo wir gerade stehen.
        self::zeigeStand($c, $msgId);
        return 'veraltet';
    }

    private static function menuPunkt(array $c, string $was, ?int $msgId): string
    {
        $sp = (string) $c['sprache'];
        $web = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        switch ($was) {
            case 'neu': case 'besser': case 'preis':
                return self::frageStarten($c, $was, $msgId);
            case 'pruefen':
                self::zeigen($c, self::t($c, 'pruefenText'), [[self::url(self::t($c, 'k_check'), $web . '/analisi.php?lang=' . $sp)], [self::k($c, 'k_menu', 'm:menu')]], $msgId);
                return 'pruefen';
            case 'logo':
                self::zeigen($c, self::t($c, 'logoText'), [[self::k($c, 'k_anfragen', 'b:logo')], [self::k($c, 'k_menu', 'm:menu')]], $msgId);
                return 'logo';
            case '3d':
                self::zeigen($c, self::t($c, 'dreiDText'), [[self::url(self::t($c, 'k_beispiel'), $web . ($sp === 'it' ? '/' : '/' . $sp . '/'))],
                    [self::k($c, 'k_anfragen', 'b:3d')], [self::k($c, 'k_menu', 'm:menu')]], $msgId);
                return '3d';
            case 'hosting':
                self::zeigen($c, self::t($c, 'hostingText'), [[self::url(self::t($c, 'k_hosting_seite'), $web . '/hosting.php?lang=' . $sp)], [self::k($c, 'k_menu', 'm:menu')]], $msgId);
                return 'hosting';
            case 'kunde':
                if (!empty($c['kunde_verbunden'])) { self::zeigeKunde($c, $msgId); return 'kunde_projekt'; }
                self::zeigen($c, self::t($c, 'kundeText'), [[self::url(self::t($c, 'k_zugang'), $web . '/zugang.php?lang=' . $sp)], [self::k($c, 'k_menu', 'm:menu')]], $msgId);
                return 'kunde';
            case 'mensch':
                self::zeigeMensch($c, $msgId);
                return 'mensch';
            case 'sprache':
                self::zeigeSprachwahl($c, $msgId);
                return 'sprache';
        }
        $c = self::setzen($c, ['stand' => 'menu']);
        self::zeigeMenu($c, $msgId);
        return 'menu';
    }

    /* ========================= FRAGEBOGEN =========================== */

    /**
     * Die Fragen in der Reihenfolge der Website (Baukasten::SCHRITTE).
     * Was der Einstieg schon beantwortet, wird übersprungen.
     *
     * @return list<string>
     */
    public static function fragen(?string $einstieg = null): array
    {
        require_once __DIR__ . '/Baukasten.php';
        $vorgabe = self::EINSTIEG[$einstieg ?? 'preis'] ?? [];
        $aus = [];
        foreach (Baukasten::SCHRITTE as $namen) {
            foreach ($namen as $n) {
                if (isset(Baukasten::FRAGEN[$n]) && !array_key_exists($n, $vorgabe)) { $aus[] = $n; }
            }
        }
        return $aus;
    }

    /** In welchem Seitenschritt der Website steht diese Frage (1-basiert)? */
    private static function schrittVon(string $frage): int
    {
        foreach (Baukasten::SCHRITTE as $i => $namen) {
            if (in_array($frage, $namen, true)) { return $i + 1; }
        }
        return 1;
    }

    private static function frageStarten(array $c, string $einstieg, ?int $msgId): string
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';
        Baukasten::sicherstellen();

        // Ein offener Bedarf aus diesem Chat wird weitergeführt — seine
        // Antworten bleiben stehen, wie auf der Website mit demselben Link.
        $b = self::bedarf($c);
        if ($b === null) {
            $b = Bedarf::starten((string) $c['sprache']);
        }
        foreach (self::EINSTIEG[$einstieg] ?? [] as $frage => $wert) {
            Bedarf::speichern((int) $b['id'], [$frage => $wert], self::schrittVon($frage));
        }
        // „Website verbessern“ heißt: Es gibt eine. Eine frühere Antwort
        // „alles neu“ passt dann nicht mehr und wird noch einmal gefragt.
        if ($einstieg === 'besser' && (Bedarf::antworten($b)['bestand'] ?? '') === 'neu') {
            Bedarf::speichern((int) $b['id'], ['bestand' => ''], self::schrittVon('bestand'));
        }
        $c = self::setzen($c, ['stand' => 'frage', 'frage' => 0, 'bedarf_id' => (int) $b['id'], 'einstieg' => $einstieg]);
        self::zeigeFrage($c, $msgId);
        return 'frage';
    }

    /** Der offene Bedarf dieses Chats — oder null, wenn es keinen (mehr) gibt. */
    private static function bedarf(array $c): ?array
    {
        if (empty($c['bedarf_id'])) { return null; }
        $b = Db::one('SELECT * FROM bedarf WHERE id = ?', [(int) $c['bedarf_id']]);
        if (!$b || $b['status'] !== 'offen') { return null; }
        // Dieselbe Frist wie auf der Website.
        if (time() - strtotime((string) $b['created_at']) > Bedarf::GUELTIG_TAGE * 86400) { return null; }
        return $b;
    }

    private static function frageKnopf(array $c, string $art, string $rest, string $cqId, ?int $msgId): string
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';
        $teile = explode(':', $rest, 2);
        $idx = (int) $teile[0];
        $wahl = (string) ($teile[1] ?? '');
        $fragen = self::fragen($c['einstieg']);
        $b = self::bedarf($c);

        // Veraltet: anderer Stand, andere Frage oder Bedarf weg.
        if ($c['stand'] !== 'frage' || $idx !== (int) $c['frage'] || !isset($fragen[$idx]) || $b === null) {
            self::antwortKnopf($cqId);
            if ($b === null && $c['stand'] === 'frage') { return self::frageStarten($c, (string) ($c['einstieg'] ?: 'preis'), $msgId); }
            self::zeigeStand($c, $msgId);
            return 'veraltet';
        }
        $name = $fragen[$idx];
        $f = Baukasten::FRAGEN[$name];
        $mehrfach = ($f['art'] ?? 'einfach') === 'mehrfach';
        $erlaubt = array_map('strval', array_keys($f['optionen']));
        $schritt = self::schrittVon($name);

        if ($art === 't' && $mehrfach && in_array($wahl, $erlaubt, true)) {
            $jetzt = (array) (Bedarf::antworten($b)[$name] ?? []);
            $jetzt = in_array($wahl, $jetzt, true) ? array_values(array_diff($jetzt, [$wahl])) : array_merge($jetzt, [$wahl]);
            Bedarf::speichern((int) $b['id'], [$name => $jetzt], $schritt);
            self::antwortKnopf($cqId);
            self::zeigeFrage($c, $msgId);
            return 'umgeschaltet';
        }
        if ($art === 'w' && $mehrfach) {
            $jetzt = (array) (Bedarf::antworten($b)[$name] ?? []);
            // Ohne Zweck gibt es keine Zahl (Baukasten::genugGesagt) — also nachfragen.
            if ($name === 'zweck' && $jetzt === []) {
                self::antwortKnopf($cqId, self::t($c, 'mindEins'));
                return 'mind_eins';
            }
            Bedarf::speichern((int) $b['id'], [$name => $jetzt], $schritt);
            self::antwortKnopf($cqId);
            return self::naechste($c, $idx + 1, $msgId);
        }
        if ($art === 'q' && !$mehrfach && in_array($wahl, $erlaubt, true)) {
            Bedarf::speichern((int) $b['id'], [$name => $wahl], $schritt);
            self::antwortKnopf($cqId);
            return self::naechste($c, $idx + 1, $msgId);
        }
        self::antwortKnopf($cqId);
        self::zeigeFrage($c, $msgId);
        return 'veraltet';
    }

    private static function naechste(array $c, int $idx, ?int $msgId): string
    {
        $fragen = self::fragen($c['einstieg']);
        if ($idx >= count($fragen)) {
            $c = self::setzen($c, ['stand' => 'ergebnis', 'frage' => 0]);
            self::zeigeErgebnis($c, $msgId);
            return 'ergebnis';
        }
        $c = self::setzen($c, ['frage' => $idx]);
        self::zeigeFrage($c, $msgId);
        return 'frage';
    }

    private static function zurueck(array $c, ?int $msgId): string
    {
        if ($c['stand'] === 'frage' && (int) $c['frage'] > 0) {
            $c = self::setzen($c, ['frage' => (int) $c['frage'] - 1]);
            self::zeigeFrage($c, $msgId);
            return 'frage';
        }
        if ($c['stand'] === 'ergebnis') {
            $c = self::setzen($c, ['stand' => 'frage', 'frage' => max(0, count(self::fragen($c['einstieg'])) - 1)]);
            self::zeigeFrage($c, $msgId);
            return 'frage';
        }
        $c = self::setzen($c, ['stand' => 'menu']);
        self::zeigeMenu($c, $msgId);
        return 'menu';
    }

    private static function abbrechen(array $c, ?int $msgId): string
    {
        // Halb eingegebene Kontaktdaten werden nicht aufgehoben.
        $c = self::setzen($c, ['stand' => 'menu', 'ziel' => null, 'thema' => null, 'name' => null, 'email' => null, 'nachricht' => null]);
        self::zeigeMenu($c, $msgId, self::t($c, 'abgebrochen') . "\n\n");
        return 'abgebrochen';
    }

    private static function zeigeFrage(array $c, ?int $msgId = null): void
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';
        require_once __DIR__ . '/Texte.php';
        $sp = (string) $c['sprache'];
        $fragen = self::fragen($c['einstieg']);
        $idx = min((int) $c['frage'], count($fragen) - 1);
        $name = $fragen[$idx];
        $f = Baukasten::FRAGEN[$name];
        $b = self::bedarf($c);
        $antworten = $b ? Bedarf::antworten($b) : [];
        $mehrfach = ($f['art'] ?? 'einfach') === 'mehrfach';
        $gewaehlt = $antworten[$name] ?? ($mehrfach ? [] : '');

        $text = '<i>' . self::h(strtr(self::t($c, 'frageKopf'), ['{n}' => (string) ($idx + 1), '{gesamt}' => (string) count($fragen)])) . '</i>'
              . "\n\n<b>" . self::h(Texte::h($f['frage'], $sp)) . '</b>';
        if (!empty($f['hilfe'])) { $text .= "\n" . self::h(Texte::h($f['hilfe'], $sp)); }
        if ($mehrfach) { $text .= "\n\n<i>" . self::h(self::t($c, 'mehrfach')) . '</i>'; }
        $live = self::liveZeile($c, $antworten, self::schrittVon($name));
        if ($live !== '') { $text .= "\n\n💶 " . self::h($live); }

        $knoepfe = [];
        foreach ($f['optionen'] as $schl => $label) {
            $schl = (string) $schl;
            $an = $mehrfach ? in_array($schl, (array) $gewaehlt, true) : ((string) $gewaehlt === $schl);
            $knoepfe[] = [['text' => ($an ? '✅ ' : '') . Texte::h($label, $sp), 'callback_data' => ($mehrfach ? 't:' : 'q:') . $idx . ':' . $schl]];
        }
        if ($mehrfach) { $knoepfe[] = [self::k($c, 'k_weiter', 'w:' . $idx)]; }
        $knoepfe[] = [self::k($c, 'k_zurueck', 'z:'), self::k($c, 'k_abbrechen', 'x:')];
        self::zeigen($c, $text, $knoepfe, $msgId);
    }

    /** Der laufende Richtwert — dieselbe Rechnung wie auf der Website (Baukasten::live). */
    private static function liveZeile(array $c, array $antworten, int $gesehenBis): string
    {
        if (!self::preiseZeigen()) { return ''; }
        try {
            $kat = Baukasten::katalog();
            $r = Baukasten::live($antworten, $gesehenBis, $kat);
            $sp = (string) $c['sprache'];
            if (!Baukasten::genugGesagt($antworten)) {
                return strtr(Texte::h(Texte::BEDARF['liveAb'], $sp), ['{betrag}' => Baukasten::geldText(Baukasten::ab($kat), $sp)]);
            }
            return strtr(self::t($c, 'liveBisher'), ['{betrag}' => Baukasten::geldText($r['von_cents'], $sp) . ' – ' . Baukasten::geldText($r['bis_cents'], $sp)]);
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function preiseZeigen(): bool
    {
        return (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'bedarf_spanne_zeigen'", [], '1') === '1';
    }

    /**
     * Das Ergebnis: Posten und Richtwert — oder ehrlich keine Zahl.
     *
     * @return array{zeigen:bool, text:string} Text ohne Knöpfe, auch für die Prüfansicht
     */
    public static function ergebnis(array $c): array
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';
        require_once __DIR__ . '/Texte.php';
        $sp = (string) $c['sprache'];
        $b = self::bedarf($c);
        $antworten = $b ? Bedarf::antworten($b) : [];
        $kat = Baukasten::katalog();
        $r = Baukasten::rechnen($antworten, $kat);
        $zeigen = self::preiseZeigen() && Baukasten::genugGesagt($antworten);

        $zeilen = [];
        foreach ($r['positionen'] as $p) {
            if ((int) $p['monatlich']) { continue; }
            $bst = $kat[$p['slug']] ?? null;
            if (!$bst) { continue; }
            $zeilen[] = '• ' . self::h(Baukasten::name($bst, $sp)) . ((int) $p['menge'] > 1 ? ' × ' . (int) $p['menge'] : '');
        }
        $text = '<b>' . self::h(self::t($c, 'ergebnisKopf')) . "</b>\n\n" . implode("\n", $zeilen);
        if ($zeigen) {
            $s = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
            $text .= "\n\n" . strtr(self::t($c, 'einmalig'), ['{spanne}' => self::h(Baukasten::geldText($s['von_cents'], $sp) . ' – ' . Baukasten::geldText($s['bis_cents'], $sp))]);
            if ((int) $r['monatlich_cents'] > 0) {
                $text .= "\n" . self::h(strtr(Texte::h(Texte::BEDARF['liveMonat'], $sp), ['{betrag}' => Baukasten::geldText((int) $r['monatlich_cents'], $sp)]));
            }
            if (in_array('logo', (array) ($r['vorschlaege'] ?? []), true)) { $text .= "\n\n" . self::h(self::t($c, 'logoHinweis')); }
            $text .= "\n\n<i>" . self::h(self::t($c, 'indikation')) . '</i>';
        } else {
            $text .= "\n\n" . self::h(self::t($c, 'keinPreis'));
        }
        return ['zeigen' => $zeigen, 'text' => $text,
                'spanne' => $zeigen ? Baukasten::geldText(Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents'])['von_cents'], $sp) . ' – '
                    . Baukasten::geldText(Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents'])['bis_cents'], $sp) : ''];
    }

    private static function zeigeErgebnis(array $c, ?int $msgId = null): void
    {
        $e = self::ergebnis($c);
        self::zeigen($c, $e['text'], [
            [self::k($c, 'k_senden', 'r:senden')],
            [self::k($c, 'k_aendern', 'r:aendern'), self::k($c, 'k_mensch', 'm:mensch')],
            [self::k($c, 'k_menu', 'm:menu')],
        ], $msgId);
    }

    /* ======================= KONTAKTDATEN =========================== */

    private static function datenschutz(array $c, string $ziel, ?string $thema, ?int $msgId): string
    {
        if ($ziel === 'anfrage' && self::bedarf($c) === null) { self::zeigeStand($c, $msgId); return 'veraltet'; }
        // Denselben Hinweis in derselben Fassung schon bestätigt? Dann nicht zweimal fragen.
        if ((string) ($c['datenschutz_fassung'] ?? '') === self::FASSUNG) {
            $c = self::setzen($c, ['stand' => 'name', 'ziel' => $ziel, 'thema' => $thema]);
            self::zeigen($c, self::t($c, 'fragName'), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId);
            return 'name';
        }
        $c = self::setzen($c, ['stand' => 'ds', 'ziel' => $ziel, 'thema' => $thema]);
        $sp = (string) $c['sprache'];
        $link = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/legal.html?lang=' . $sp . '#privacy';
        $text = strtr(self::t($c, 'ds'), ['{link}' => '<a href="' . self::h($link) . '">' . self::h(self::t($c, 'dsLink')) . '</a>']);
        self::zeigen($c, $text, [[self::k($c, 'k_ja', 'd:ja')], [self::k($c, 'k_abbrechen', 'x:')]], $msgId);
        return 'datenschutz';
    }

    private static function eingabeName(array $c, string $text): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $text));
        $ok = mb_strlen($name) >= 2 && mb_strlen($name) <= 120 && preg_match('/\p{L}/u', $name)
            && !preg_match('~https?://|www\.|@|<|>|\d{4,}~i', $name);
        if (!$ok) {
            self::zeigen($c, self::t($c, 'nameFalsch'), [[self::k($c, 'k_abbrechen', 'x:')]]);
            return 'name_falsch';
        }
        $c = self::setzen($c, ['name' => $name, 'stand' => 'email']);
        self::zeigen($c, strtr(self::t($c, 'fragEmail'), ['{name}' => self::h($name)]), [[self::k($c, 'k_abbrechen', 'x:')]]);
        return 'email';
    }

    private static function eingabeEmail(array $c, string $text): string
    {
        $email = mb_strtolower(trim($text));
        $ok = mb_strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL)
            && preg_match('/@[^@]+\.[a-z]{2,}$/i', $email);
        if (!$ok) {
            self::zeigen($c, self::t($c, 'emailFalsch'), [[self::k($c, 'k_abbrechen', 'x:')]]);
            return 'email_falsch';
        }
        $c = self::setzen($c, ['email' => $email]);
        if ($c['ziel'] === 'beratung') {
            $c = self::setzen($c, ['stand' => 'nachricht']);
            self::zeigen($c, self::t($c, 'fragNachricht'), [[self::k($c, 'k_abbrechen', 'x:')]]);
            return 'nachricht';
        }
        $c = self::setzen($c, ['stand' => 'pruefen']);
        self::zeigePruefen($c);
        return 'pruefen';
    }

    private static function eingabeNachricht(array $c, string $text): string
    {
        $t = trim($text);
        if (mb_strlen($t) < 3 || mb_strlen($t) > self::TEXT_MAX) {
            self::zeigen($c, self::t($c, 'nachrichtFalsch'), [[self::k($c, 'k_abbrechen', 'x:')]]);
            return 'nachricht_falsch';
        }
        $c = self::setzen($c, ['nachricht' => $t, 'stand' => 'pruefen']);
        self::zeigePruefen($c);
        return 'pruefen';
    }

    private static function zeigePruefen(array $c, ?int $msgId = null): void
    {
        $z = ['<b>' . self::h(self::t($c, 'pruefenKopf')) . '</b>', '',
              self::h(self::t($c, 'f_name')) . ': ' . self::h((string) $c['name']),
              self::h(self::t($c, 'f_email')) . ': ' . self::h((string) $c['email'])];
        if ($c['ziel'] === 'beratung') {
            $z[] = self::h(self::t($c, 'f_thema')) . ': ' . self::h(self::T[$c['sprache']]['thema'][$c['thema'] ?: 'allgemein'] ?? '');
            $z[] = self::h(self::t($c, 'f_anliegen')) . ': ' . self::h((string) $c['nachricht']);
        } else {
            $e = self::ergebnis($c);
            if ($e['spanne'] !== '') { $z[] = self::h(self::t($c, 'f_richtwert')) . ': ' . self::h($e['spanne']); }
        }
        self::zeigen($c, implode("\n", $z), [
            [self::k($c, 'k_jetzt', 's:ja')],
            [self::k($c, 'k_korrigieren', 's:aendern'), self::k($c, 'k_abbrechen', 'x:')],
        ], $msgId);
    }

    /* ========================== ABSENDEN ============================ */

    private static function absenden(array $c, ?int $msgId): string
    {
        $sp = (string) $c['sprache'];
        $code = (string) ($c['quelle_code'] ?? '');
        $empfehlCode = str_starts_with($code, 'e_') ? substr($code, 2) : '';
        $kundeId = null; $anfrageId = null; $bedarfId = null;

        try {
            if ($c['ziel'] === 'anfrage') {
                require_once __DIR__ . '/Bedarf.php';
                $b = self::bedarf($c);
                if ($b === null) { self::zeigeStand($c, $msgId); return 'veraltet'; }
                $bedarfId = (int) $b['id'];
                $ok = Bedarf::absenden($bedarfId, [
                    'name' => (string) $c['name'], 'email' => (string) $c['email'],
                    'sprache' => $sp, 'empfehl_code' => $empfehlCode, 'herkunft' => 'telegram',
                ]);
                if (!$ok) { throw new RuntimeException('Bedarf::absenden hat abgelehnt.'); }
                $z = Db::one('SELECT customer_id, anfrage_id FROM bedarf WHERE id = ?', [$bedarfId]);
                $kundeId = $z && $z['customer_id'] !== null ? (int) $z['customer_id'] : null;
                $anfrageId = $z && $z['anfrage_id'] !== null ? (int) $z['anfrage_id'] : null;
            } elseif ($c['ziel'] === 'beratung') {
                require_once __DIR__ . '/Anfrage.php';
                $thema = self::T[$sp]['thema'][$c['thema'] ?: 'allgemein'] ?? '';
                $anfrageId = Anfrage::annehmen([
                    'name' => (string) $c['name'], 'email' => (string) $c['email'],
                    'sprache' => $sp, 'sprache_gefragt' => true, 'herkunft' => 'telegram',
                    'nachricht' => strtr(self::T[$sp]['beratungKopf'], ['{thema}' => $thema]) . "\n\n" . (string) $c['nachricht'],
                ]);
                if (!$anfrageId) { throw new RuntimeException('Anfrage::annehmen hat abgelehnt.'); }
                $kundeId = (int) Db::wert('SELECT customer_id FROM anfragen WHERE id = ?', [$anfrageId], 0) ?: null;
            } else {
                self::zeigeStand($c, $msgId);
                return 'veraltet';
            }
        } catch (Throwable $e) {
            self::meldenFehler('Telegram: Anfrage ließ sich nicht absenden', $e->getMessage());
            self::zeigen($c, self::t($c, 'fehlerSenden'), [[self::k($c, 'k_jetzt', 's:ja')], [self::k($c, 'k_abbrechen', 'x:')]], $msgId);
            return 'fehler';
        }

        // Ab hier steht die Anfrage. Alles Weitere darf scheitern, ohne sie mitzunehmen.
        if ($kundeId && $code !== '' && !preg_match('/^[pe]_/', $code)) {
            try { Events::protokoll('anfrage_quelle', 'Telegram-Anfrage kam über: ' . $code, $kundeId); } catch (Throwable $e) { }
        }
        if ($kundeId) {
            // Der Nachweis, welchem Wortlaut er zugestimmt hat — in seiner Sprache, mit Fassung.
            try {
                require_once __DIR__ . '/Zustimmung.php';
                Zustimmung::festhalten('datenschutz', $kundeId, strip_tags(strtr(self::T[$sp]['ds'], ['{link}' => 'legal.html#privacy'])),
                    $sp, (string) ($c['datenschutz_fassung'] ?: self::FASSUNG));
            } catch (Throwable $e) { /* nachtragbar */ }
            // Kam er über einen Partner-Link (t.me/bot?start=p_CODE)?
            if (str_starts_with($code, 'p_')) {
                try {
                    require_once __DIR__ . '/Partner.php';
                    $p = Partner::ausCode(substr($code, 2));
                    if ($p) { Partner::zuordnen($kundeId, (int) $p['id'], 'link', $bedarfId, 'telegram'); }
                } catch (Throwable $e) { /* nachtragbar */ }
            }
        }

        $name = (string) $c['name']; $email = (string) $c['email']; $ziel = (string) $c['ziel'];
        // Name, E-Mail und Anliegen stehen jetzt in der Kundenakte — hier werden sie nicht mehr gebraucht.
        $c = self::setzen($c, ['stand' => 'menu', 'ziel' => null, 'thema' => null, 'name' => null, 'email' => null, 'nachricht' => null,
            'bedarf_id' => null, 'einstieg' => null, 'customer_id' => $kundeId, 'anfrage_id' => $anfrageId]);
        self::zeigen($c, strtr(self::t($c, $ziel === 'beratung' ? 'dankBeratung' : 'dankAnfrage'), ['{name}' => self::h($name), '{email}' => self::h($email)]),
            [[self::k($c, 'k_menu', 'm:menu')]], $msgId);
        return 'gesendet';
    }

    /* =========================== LÖSCHEN ============================ */

    private static function loeschen(array $c, ?int $msgId): string
    {
        require_once __DIR__ . '/Bedarf.php';
        $b = self::bedarf($c);
        // Ein offener, nie abgeschickter Bedarf gehört nur diesem Chat.
        if ($b !== null && $b['customer_id'] === null) { Bedarf::loeschen((int) $b['id']); }
        $text = self::t($c, 'geloescht');
        if (!empty($c['kunde_verbunden'])) { TelegramKunde::trennen((int) $c['kunde_verbunden'], 'kunde /delete'); }
        Db::run('DELETE FROM telegram_chats WHERE id = ?', [(int) $c['id']]);
        $c['id'] = 0; $c['nachricht_id'] = null;
        self::zeigen($c, $text, [], $msgId);
        return 'geloescht';
    }

    /**
     * Täglich aus dem Cron: Chats ohne Anfrage nach 90 stillen Tagen löschen,
     * liegengebliebene Kontaktdaten nach 30 Tagen leeren, alte Update-Vermerke weg.
     *
     * @return array{chats:int, entwuerfe:int, updates:int}
     */
    public static function aufraeumen(): array
    {
        // Verbundene Kunden bleiben: Ihr Chat ist ihr Kanal, auch wenn sie monatelang nichts schreiben.
        $chats = Db::run('DELETE FROM telegram_chats WHERE anfrage_id IS NULL AND kunde_verbunden IS NULL AND letzte_am < (NOW() - INTERVAL ' . self::AUFHEBEN_TAGE . ' DAY)')->rowCount();
        Db::run("UPDATE telegram_chats SET datei_id = NULL, datei_name = NULL, datei_groesse = NULL WHERE datei_id IS NOT NULL AND letzte_am < (NOW() - INTERVAL 1 DAY)");
        $entw = Db::run("UPDATE telegram_chats SET name = NULL, email = NULL, nachricht = NULL,
                                stand = IF(stand IN ('ds','name','email','nachricht','pruefen'), 'menu', stand)
                          WHERE (name IS NOT NULL OR email IS NOT NULL OR nachricht IS NOT NULL)
                            AND letzte_am < (NOW() - INTERVAL 30 DAY)")->rowCount();
        $upd = Db::run("DELETE FROM webhook_events WHERE provider = 'telegram' AND received_at < (NOW() - INTERVAL 14 DAY)")->rowCount();
        return ['chats' => $chats, 'entwuerfe' => $entw, 'updates' => $upd];
    }

    /* =================== STUFE 3: VERWALTUNG (nur Uwe) ================
       Deutsch, weil die Verwaltung deutsch ist. Nur Zahlen und Verweise —
       keine Kundennamen in einem fremden Dienst (wie beim Zuruf). */

    private static function verwaltungVerbinden(array $c, string $code): string
    {
        $uid = TelegramAdmin::einloesen((int) $c['id'], $code);
        if ($uid === null) {
            self::senden($c, 'Dieser Verbindungslink der Verwaltung gilt nicht mehr (30 Minuten, einmal). Bitte in der Verwaltung unter Einstellungen → Telegram neu erzeugen.');
            return 'code_ungueltig';
        }
        $werte = ['stand' => 'menu'];
        if ($c['sprache'] === null) { $werte['sprache'] = 'de'; }
        self::setzen($c, $werte);
        $c = (array) Db::one('SELECT * FROM telegram_chats WHERE id = ?', [(int) $c['id']]);
        self::senden($c, "✅ <b>Verwaltung verbunden.</b>\nHier kommen ab jetzt dieselben Zurufe wie per WhatsApp (neue Anfrage, Störung …) — ohne Kundennamen. Unter „🛠 Verwaltung“ im Menü siehst du die Lage.");
        self::zeigeLage($c);
        return 'admin_verbunden';
    }

    private static function verwaltungKnopf(array $c, string $was, ?int $msgId): string
    {
        if ($was === 'trennen') {
            TelegramAdmin::trennen((int) $c['admin_verbunden']);
            $c['admin_verbunden'] = null;
            self::zeigen($c, 'Die Verwaltung ist von diesem Chat getrennt. Neu verbinden: Einstellungen → Telegram.', self::menuKnoepfe($c), $msgId);
            return 'admin_getrennt';
        }
        self::zeigeLage($c, $msgId);
        return 'lage';
    }

    private static function zeigeLage(array $c, ?int $msgId = null): void
    {
        $l = TelegramAdmin::lage();
        $b = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . rtrim(Config::basis(), '/');
        $text = "🛠 <b>Lage in der Verwaltung</b> · " . date('d.m. H:i') . "\n\n"
              . "Du bist dran: <b>" . $l['du'] . "</b>\n"
              . "Wartet auf Kunden: " . $l['kunde'] . "\n"
              . "Offene Anfragen: <b>" . $l['anfragen'] . "</b>\n"
              . "Ungelesene Nachrichten: <b>" . $l['nachrichten'] . "</b>\n"
              . "Neue Dateien (24 h): " . $l['dateien'] . "\n"
              . "Ungelesene Meldungen: " . $l['meldungen'];
        self::zeigen($c, $text, [
            [['text' => '📋 Heute', 'url' => $b . '/heute'], ['text' => '📥 Anfragen', 'url' => $b . '/anfragen']],
            [['text' => '💬 Nachrichten', 'url' => $b . '/nachrichten'], ['text' => '🔄 Aktualisieren', 'callback_data' => 'v:lage']],
            [['text' => '🔌 Verwaltung trennen', 'callback_data' => 'v:trennen'], self::k($c, 'k_menu', 'm:menu')],
        ], $msgId);
    }

    /* ====================== STUFE 2: KUNDEN =========================== */

    private static function verbinden(array $c, string $code): string
    {
        $kid = TelegramKunde::einloesen((int) $c['id'], $code);
        if ($kid === null) {
            $c = self::setzen($c, ['stand' => $c['sprache'] === null ? 'sprache' : 'menu']);
            self::senden($c, self::t($c, 'codeUngueltig'));
            if ($c['sprache'] === null) { self::zeigeSprachwahl($c); } else { self::zeigeMenu($c); }
            return 'code_ungueltig';
        }
        $k = Db::one('SELECT name, sprache FROM customers WHERE id = ?', [$kid]);
        $werte = ['stand' => 'menu'];
        // Die Sprache aus der Kundenakte, falls der Chat noch keine hat.
        if ($c['sprache'] === null) {
            $werte['sprache'] = in_array((string) ($k['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
        }
        self::setzen($c, $werte);
        $c = (array) Db::one('SELECT * FROM telegram_chats WHERE id = ?', [(int) $c['id']]);
        $vorname = trim((string) strtok((string) ($k['name'] ?? ''), ' '));
        self::senden($c, strtr(self::t($c, 'verbunden'), ['{name}' => self::h($vorname)]));
        self::zeigeKunde($c);
        return 'verbunden';
    }

    private static function zeigeKunde(array $c, ?int $msgId = null): void
    {
        $kid = (int) $c['kunde_verbunden'];
        $sp = (string) $c['sprache'];
        require_once __DIR__ . '/Kundenzugang.php';
        $st = TelegramKunde::stand($kid, $sp);
        $text = '📂 <b>' . self::h(self::t($c, 'kundeKopf')) . "</b>\n\n<b>" . self::h($st['titel']) . "</b>\n" . self::h($st['text']);
        self::zeigen($c, $text, [
            [self::url(self::t($c, 'k_dashboard'), Kundenzugang::linkFuer($kid, $sp))],
            [self::k($c, 'k_schreiben', 'c:schreiben'), self::k($c, 'k_datei', 'c:datei')],
            [self::k($c, (int) $c['benachrichtigen'] ? 'k_hinweise_an' : 'k_hinweise_aus', 'c:hinweise'), self::k($c, 'k_trennen', 'c:trennen')],
            [self::k($c, 'k_menu', 'm:menu')],
        ], $msgId);
    }

    private static function kundeKnopf(array $c, string $was, ?int $msgId): string
    {
        switch ($was) {
            case 'schreiben':
                $c = self::setzen($c, ['stand' => 'kundennachricht']);
                self::zeigen($c, self::t($c, 'fragKundenNachricht'), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId);
                return 'kundennachricht';
            case 'datei':
                require_once __DIR__ . '/Ablage.php';
                require_once __DIR__ . '/Fmt.php';
                $max = Fmt::bytes(min(Ablage::grenze(), 20 * 1024 * 1024));
                self::zeigen($c, strtr(self::t($c, 'dateiTipp'), ['{max}' => $max]), [[self::k($c, 'k_projekt', 'm:kunde')]], $msgId);
                return 'datei_tipp';
            case 'hinweise':
                $an = (int) $c['benachrichtigen'] ? 0 : 1;
                $c = self::setzen($c, ['benachrichtigen' => $an]);
                self::zeigeKunde($c, $msgId);
                return $an ? 'hinweise_an' : 'hinweise_aus';
            case 'trennen':
                TelegramKunde::trennen((int) $c['kunde_verbunden'], 'kunde über Telegram');
                $c = self::setzen($c, ['stand' => 'menu']);
                $c['kunde_verbunden'] = null; $c['verbunden_am'] = null;
                self::zeigen($c, self::t($c, 'getrennt'), self::menuKnoepfe($c), $msgId);
                return 'getrennt';
        }
        self::zeigeKunde($c, $msgId);
        return 'kunde_projekt';
    }

    private static function kundenNachricht(array $c, string $text): string
    {
        $t = trim($text);
        if (mb_strlen($t) < 2) {
            self::zeigen($c, self::t($c, 'nachrichtFalsch'), [[self::k($c, 'k_abbrechen', 'x:')]]);
            return 'nachricht_falsch';
        }
        try {
            TelegramKunde::nachricht((int) $c['kunde_verbunden'], $t);
        } catch (Throwable $e) {
            self::meldenFehler('Telegram: Kundennachricht ließ sich nicht ablegen', $e->getMessage());
            self::zeigen($c, self::t($c, 'fehlerSenden'), [[self::k($c, 'k_projekt', 'm:kunde')]]);
            return 'fehler';
        }
        $c = self::setzen($c, ['stand' => 'menu']);
        self::zeigen($c, self::t($c, 'kundenNachrichtOk'), [[self::k($c, 'k_projekt', 'm:kunde'), self::k($c, 'k_menu', 'm:menu')]]);
        return 'kundennachricht_gesendet';
    }

    /** Ein verbundener Kunde schickt eine Datei: erst fragen, dann ablegen. */
    private static function dateiAngekommen(array $c, array $m): string
    {
        $id = ''; $name = ''; $groesse = 0;
        if (!empty($m['document']['file_id'])) {
            $id = (string) $m['document']['file_id']; $name = (string) ($m['document']['file_name'] ?? 'datei'); $groesse = (int) ($m['document']['file_size'] ?? 0);
        } elseif (!empty($m['photo']) && is_array($m['photo'])) {
            $gross = end($m['photo']);                                  // Telegram liefert mehrere Größen, die größte zuletzt
            $id = (string) ($gross['file_id'] ?? ''); $groesse = (int) ($gross['file_size'] ?? 0);
            $name = 'telegram-foto-' . date('Ymd-His') . '.jpg';
        } elseif (!empty($m['video']['file_id'])) {
            $id = (string) $m['video']['file_id']; $name = (string) ($m['video']['file_name'] ?? ('telegram-video-' . date('Ymd-His') . '.mp4')); $groesse = (int) ($m['video']['file_size'] ?? 0);
        } elseif (!empty($m['audio']['file_id'])) {
            $id = (string) $m['audio']['file_id']; $name = (string) ($m['audio']['file_name'] ?? ('telegram-audio-' . date('Ymd-His') . '.mp3')); $groesse = (int) ($m['audio']['file_size'] ?? 0);
        }
        if ($id === '' || strlen($id) > 200) {
            self::zeigen($c, self::t($c, 'nurText'), [[self::k($c, 'k_projekt', 'm:kunde')]]);
            return 'kein_text';
        }
        if ($groesse > 20 * 1024 * 1024) {
            self::zeigen($c, self::t($c, 'dateiGross'), [[self::k($c, 'k_projekt', 'm:kunde')]]);
            return 'datei_zu_gross';
        }
        $name = mb_substr(trim((string) preg_replace('~[\x00-\x1f\x7f/\\\\]~u', '', $name)) ?: 'datei', 0, 200);
        $c = self::setzen($c, ['datei_id' => $id, 'datei_name' => $name, 'datei_groesse' => $groesse]);
        self::zeigen($c, strtr(self::t($c, 'dateiFrage'), ['{name}' => self::h($name)]),
            [[self::k($c, 'k_datei_ja', 'f:ja'), self::k($c, 'k_abbrechen', 'f:nein')]]);
        return 'datei_frage';
    }

    private static function dateiKnopf(array $c, string $was, ?int $msgId): string
    {
        if ($was !== 'ja' || empty($c['datei_id'])) {
            $c = self::setzen($c, ['datei_id' => null, 'datei_name' => null, 'datei_groesse' => null]);
            self::zeigeKunde($c, $msgId);
            return 'datei_verworfen';
        }
        $id = (string) $c['datei_id']; $name = (string) $c['datei_name'];
        // Zuerst vergessen: Ein zweiter Klick soll dieselbe Datei nicht zweimal ablegen.
        $st = Db::run('UPDATE telegram_chats SET datei_id = NULL, datei_name = NULL, datei_groesse = NULL WHERE id = ? AND datei_id = ?', [(int) $c['id'], $id]);
        if ($st->rowCount() !== 1) { self::zeigeKunde($c, $msgId); return 'veraltet'; }
        try {
            TelegramKunde::dateiAblegen((int) $c['kunde_verbunden'], $id, $name);
        } catch (Throwable $e) {
            require_once __DIR__ . '/Ablage.php';
            require_once __DIR__ . '/Fmt.php';
            $schl = $e->getMessage() === 'voll' ? 'dateiVoll' : (str_contains($e->getMessage(), 'zu groß') ? 'dateiGross' : 'dateiFehler');
            self::zeigen($c, strtr(self::t($c, $schl), ['{max}' => Fmt::bytes(min(Ablage::grenze(), 20 * 1024 * 1024))]),
                [[self::k($c, 'k_projekt', 'm:kunde')]], $msgId);
            return 'datei_fehler';
        }
        self::zeigen($c, strtr(self::t($c, 'dateiOk'), ['{name}' => self::h($name)]), [[self::k($c, 'k_projekt', 'm:kunde'), self::k($c, 'k_menu', 'm:menu')]], $msgId);
        return 'datei_abgelegt';
    }

    /* ========================= ANSICHTEN ============================ */

    private static function zeigeSprachwahl(array $c, ?int $msgId = null): void
    {
        // Ein wartender Sprung aus dem Kanal (sp_…) bleibt stehen, bis die Sprache gewählt ist.
        $warte = $c['sprache'] === null && !str_starts_with((string) $c['stand'], 'sp_');
        $c = self::setzen($c, ['stand' => $warte ? 'sprache' : $c['stand']]);
        // Der Vorschlag (Oberflächensprache von Telegram) steht oben.
        $reihe = self::SPRACHEN;
        $vor = $c['sprache'] ?? $c['sprache_vorschlag'];
        if ($vor !== null && isset($reihe[$vor])) { $reihe = [$vor => $reihe[$vor]] + $reihe; }
        $k = [];
        foreach ($reihe as $sp => $label) { $k[] = [['text' => $label, 'callback_data' => 'l:' . $sp]]; }
        self::zeigen($c, self::SPRACHWAHL, $k, $msgId);
    }

    private static function menuKnoepfe(array $c): array
    {
        return array_merge([
            [self::k($c, 'k_neu', 'm:neu'), self::k($c, 'k_besser', 'm:besser')],
            [self::k($c, 'k_preis', 'm:preis'), self::k($c, 'k_pruefen', 'm:pruefen')],
            [self::k($c, 'k_logo', 'm:logo'), self::k($c, 'k_3d', 'm:3d')],
            [self::k($c, 'k_hosting', 'm:hosting'), self::k($c, !empty($c['kunde_verbunden']) ? 'k_projekt' : 'k_kunde', 'm:kunde')],
            [self::k($c, 'k_mensch', 'm:mensch'), self::k($c, 'k_sprache', 'm:sprache')],
        ],
        // Der Kanal (30.09.2026): nur, wenn einer hinterlegt ist.
        Telegram::einstellung('tg_kanal_link') !== '' ? [[self::url(self::t($c, 'k_kanal'), Telegram::einstellung('tg_kanal_link'))]] : [],
        TelegramAdmin::darfChat($c)
            ? [[['text' => '🛠 Verwaltung', 'callback_data' => 'v:lage']]] : []);
    }

    private static function zeigeMenu(array $c, ?int $msgId = null, string $vorspann = ''): void
    {
        self::zeigen($c, self::h($vorspann) . self::t($c, 'menu'), self::menuKnoepfe($c), $msgId);
    }

    private static function zeigeMensch(array $c, ?int $msgId = null): void
    {
        self::zeigen($c, self::t($c, 'menschText'), [
            [self::url(self::t($c, 'k_whatsapp'), self::WHATSAPP)],
            [self::k($c, 'k_rueckmeldung', 'b:allgemein')],
            [self::k($c, 'k_menu', 'm:menu')],
        ], $msgId);
    }

    /** Dort weitermachen, wo der Chat steht — für veraltete Knöpfe. */
    private static function zeigeStand(array $c, ?int $msgId = null): void
    {
        switch ($c['stand']) {
            case 'frage':
                if (self::bedarf($c) !== null) { self::zeigeFrage($c, $msgId); return; }
                break;
            case 'ergebnis':
                if (self::bedarf($c) !== null) { self::zeigeErgebnis($c, $msgId); return; }
                break;
            case 'pruefen':
                self::zeigePruefen($c, $msgId); return;
            case 'name':
                self::zeigen($c, self::t($c, 'fragName'), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId); return;
            case 'email':
                self::zeigen($c, strtr(self::t($c, 'fragEmail'), ['{name}' => self::h((string) $c['name'])]), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId); return;
            case 'nachricht':
                self::zeigen($c, self::t($c, 'fragNachricht'), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId); return;
            case 'kundennachricht':
                if (!empty($c['kunde_verbunden'])) { self::zeigen($c, self::t($c, 'fragKundenNachricht'), [[self::k($c, 'k_abbrechen', 'x:')]], $msgId); return; }
                break;
        }
        $c = self::setzen($c, ['stand' => 'menu']);
        self::zeigeMenu($c, $msgId);
    }

    /* ========================== TECHNIK ============================= */

    /** Chat laden oder anlegen. */
    private static function chat(int $chatId, ?string $vorschlag): array
    {
        $c = Db::one('SELECT * FROM telegram_chats WHERE chat_id = ?', [$chatId]);
        if ($c) { return $c; }
        try {
            Db::insert('telegram_chats', ['chat_id' => $chatId, 'sprache_vorschlag' => $vorschlag, 'stand' => 'neu']);
        } catch (Throwable $e) {
            if (!Db::andrang($e) && !Db::doppelt($e)) { throw $e; }
        }
        return (array) Db::one('SELECT * FROM telegram_chats WHERE chat_id = ?', [$chatId]);
    }

    /** Felder schreiben und die neue Fassung zurückgeben. */
    private static function setzen(array $c, array $werte): array
    {
        if ((int) ($c['id'] ?? 0) > 0) { Db::update('telegram_chats', (int) $c['id'], $werte + ['letzte_am' => date('Y-m-d H:i:s')]); }
        return $werte + $c;
    }

    /** @return string ok | erstmals | still */
    private static function takt(array $c): string
    {
        $minute = date('YmdHi');
        $zahl = $c['takt_minute'] === $minute ? (int) $c['takt_zahl'] + 1 : 1;
        Db::update('telegram_chats', (int) $c['id'], ['takt_minute' => $minute, 'takt_zahl' => min($zahl, 65000), 'letzte_am' => date('Y-m-d H:i:s')]);
        if ($zahl <= self::GRENZE_CHAT) { return 'ok'; }
        return $zahl === self::GRENZE_CHAT + 1 ? 'erstmals' : 'still';
    }

    /** Die Bremse für alle zusammen — gegen eine Flut, die nicht aus einem Chat kommt. */
    private static function alleDuerfen(): bool
    {
        $schl = 'tg_takt_' . date('YmdHi');
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, '1') ON DUPLICATE KEY UPDATE svalue = CAST(svalue AS UNSIGNED) + 1", [$schl]);
        $stand = (int) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schl], 0);
        if ($stand === 1) {
            Db::run("DELETE FROM settings WHERE skey LIKE 'tg\\_takt\\_%' AND skey < ?", ['tg_takt_' . date('YmdHi', time() - 3600)]);
        }
        return $stand <= self::GRENZE_ALLE;
    }

    private static function willMenschen(string $text): bool
    {
        $t = mb_strtolower($text);
        foreach (self::MENSCH as $w) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($w, '/') . '(?![\p{L}])/u', $t)) { return true; }
        }
        return false;
    }

    /** Text in der Sprache des Chats. */
    private static function t(array $c, string $schl): string
    {
        $sp = (string) ($c['sprache'] ?? $c['sprache_vorschlag'] ?? 'it');
        $T = self::T[$sp] ?? self::T['it'];
        $w = $T[$schl] ?? self::T['it'][$schl] ?? '';
        return is_string($w) ? $w : '';
    }

    private static function k(array $c, string $schl, string $daten): array
    {
        return ['text' => self::t($c, $schl), 'callback_data' => $daten];
    }

    private static function url(string $text, string $url): array { return ['text' => $text, 'url' => $url]; }

    public static function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /**
     * Anzeigen: Kam es von einem Knopf, wird dessen Nachricht ersetzt — der
     * Chat bleibt übersichtlich. Sonst eine neue Nachricht; die Knöpfe der
     * vorigen werden dabei entfernt, damit keine alten Knöpfe herumstehen.
     */
    private static function zeigen(array $c, string $text, array $knoepfe, ?int $msgId = null): void
    {
        $chatId = (int) $c['chat_id'];
        $daten = ['chat_id' => $chatId, 'text' => mb_substr($text, 0, 4000), 'parse_mode' => 'HTML',
                  'link_preview_options' => ['is_disabled' => true]];
        if ($knoepfe) { $daten['reply_markup'] = ['inline_keyboard' => $knoepfe]; }

        if ($msgId !== null) {
            $r = Telegram::rufen('editMessageText', $daten + ['message_id' => $msgId]);
            if ($r['ok'] || str_contains($r['beschreibung'], 'not modified')) {
                self::$gesendet[] = ['editMessageText', $daten];
                if ((int) ($c['id'] ?? 0) > 0) { Db::update('telegram_chats', (int) $c['id'], ['nachricht_id' => $msgId]); }
                return;
            }
        }
        if (!empty($c['nachricht_id']) && (int) $c['nachricht_id'] !== $msgId) {
            Telegram::rufen('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => (int) $c['nachricht_id']]);
        }
        $r = Telegram::rufen('sendMessage', $daten);
        self::$gesendet[] = ['sendMessage', $daten];
        $neu = $r['ok'] && isset($r['result']['message_id']) ? (int) $r['result']['message_id'] : null;
        if ((int) ($c['id'] ?? 0) > 0) { Db::update('telegram_chats', (int) $c['id'], ['nachricht_id' => $knoepfe ? $neu : null]); }
    }

    /** Eine Nachricht ohne Knöpfe, die die vorigen Knöpfe stehen lässt. */
    private static function senden(array $c, string $text): void
    {
        $daten = ['chat_id' => (int) $c['chat_id'], 'text' => mb_substr($text, 0, 4000), 'parse_mode' => 'HTML'];
        Telegram::rufen('sendMessage', $daten);
        self::$gesendet[] = ['sendMessage', $daten];
    }

    private static function antwortKnopf(string $cqId, string $hinweis = ''): void
    {
        if ($cqId === '') { return; }
        $d = ['callback_query_id' => $cqId];
        if ($hinweis !== '') { $d['text'] = mb_substr($hinweis, 0, 190); }
        Telegram::rufen('answerCallbackQuery', $d);
        self::$gesendet[] = ['answerCallbackQuery', $d];
    }

    /**
     * Wenn beim Verarbeiten etwas schiefgeht: dem Menschen einen ruhigen Satz,
     * Uwe eine Meldung (höchstens alle 15 Minuten, sonst läuft die Liste voll).
     */
    public static function panne(array $u, Throwable $e): void
    {
        self::meldenFehler('Telegram: Nachricht konnte nicht verarbeitet werden', $e->getMessage());
        try {
            $cq = $u['callback_query'] ?? null;
            $chatId = (int) ($cq['message']['chat']['id'] ?? $cq['from']['id'] ?? $u['message']['chat']['id'] ?? 0);
            if ($chatId === 0 || (($u['message']['chat']['type'] ?? 'private') !== 'private')) { return; }
            if ($cq && !empty($cq['id'])) { Telegram::rufen('answerCallbackQuery', ['callback_query_id' => (string) $cq['id']]); }
            $sp = null;
            try { $sp = Db::wert('SELECT sprache FROM telegram_chats WHERE chat_id = ?', [$chatId], null); } catch (Throwable $x) { }
            $vor = substr(strtolower((string) ($cq['from']['language_code'] ?? $u['message']['from']['language_code'] ?? '')), 0, 2);
            $sp = is_string($sp) && isset(self::T[$sp]) ? $sp : (isset(self::T[$vor]) ? $vor : 'it');
            Telegram::rufen('sendMessage', ['chat_id' => $chatId, 'text' => self::T[$sp]['panne']]);
        } catch (Throwable $x) { /* dann eben nicht */ }
    }

    private static function meldenFehler(string $titel, string $text): void
    {
        try {
            $schl = 'tg_fehler_gemeldet';
            $zuletzt = (int) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schl], 0);
            if ($zuletzt > time() - 900) { return; }
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$schl, (string) time()]);
            require_once __DIR__ . '/Events.php';
            Events::melden('telegram_fehler', $titel, 'schlecht', mb_substr($text, 0, 300), 'einstellungen?b=telegram');
        } catch (Throwable $e) { /* dann eben nicht */ }
    }
}
