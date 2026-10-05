<?php
declare(strict_types=1);

/* ==========================================================================
   AcademySimulator — Gesprächssimulator der Partner Academy (Etappe 3,
   05.10.2026, Uwe: „Mit KI, vorbereitet aber aus“).

   Der Partner übt ein Verkaufsgespräch mit einem KI-Betriebsinhaber (Szenen
   aus app/data/academy/*.json, Schlüssel „sim“) und bekommt am Ende eine
   Rückmeldung nach den Regeln der Academy.

   AUS, BIS ALLES DA IST — drei Schalter zugleich:
   1. Einstellung academy_sim_an = 1 (Verwaltung)
   2. Einstellung academy_sim_datenschutz = 1 (Datenschutzprüfung bestätigt)
   3. KI-Schlüssel in app/config.local.php („ki_schluessel“, nie im Repository)
   Fehlt eines, gibt es den Simulator für Partner nicht.

   DATENSPARSAM
   Der Verlauf lebt nur in der Sitzung des Partners, nie in der Datenbank.
   E-Mail-Adressen und Telefonnummern werden vor dem Senden entfernt; der
   Partner wird gebeten, keine echten Namen einzugeben. Gezählt wird nur die
   Zahl der Nachrichten je Partner und Tag (Begrenzung, partner_zaehler „sim“).
   ========================================================================== */
final class AcademySimulator
{
    public const ZUEGE_MAX = 12;       // Nachrichten des Partners je Gespräch
    public const TAG_MAX = 40;         // Nachrichten je Partner und Tag
    public const ZEICHEN_MAX = 500;
    private const API = 'https://api.anthropic.com/v1/messages';

    public static function schluessel(): string { return trim((string) Config::get('ki_schluessel', '')); }

    public static function modell(): string { return (string) Config::get('ki_modell', 'claude-haiku-4-5'); }

    private static function einstellung(string $k): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''); } catch (Throwable $e) { return ''; }
    }

    /** @return array{an:bool, datenschutz:bool, schluessel:bool} */
    public static function stand(): array
    {
        return ['an' => self::einstellung('academy_sim_an') === '1', 'datenschutz' => self::einstellung('academy_sim_datenschutz') === '1', 'schluessel' => self::schluessel() !== ''];
    }

    public static function aktiv(): bool
    {
        $s = self::stand();
        return $s['an'] && $s['datenschutz'] && $s['schluessel'];
    }

    /** Verwaltung: Schalter setzen. Einschalten geht nur mit bestätigter Datenschutzprüfung. */
    public static function schalten(bool $an, bool $datenschutz): bool
    {
        if ($an && !$datenschutz) { return false; }
        foreach (['academy_sim_an' => $an ? '1' : '0', 'academy_sim_datenschutz' => $datenschutz ? '1' : '0'] as $k => $v) {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        }
        return true;
    }

    public static function szene(string $slug, string $sprache): ?array
    {
        foreach ((Academy::inhalte($sprache)['sim'] ?? []) as $s) { if (($s['slug'] ?? '') === $slug) { return $s; } }
        return null;
    }

    /** Entfernt Kontaktdaten aus einer Nachricht und kürzt sie. */
    public static function saeubern(string $t): string
    {
        $t = mb_substr(trim(preg_replace('~\s+~u', ' ', $t) ?? ''), 0, self::ZEICHEN_MAX);
        $t = (string) preg_replace('~[\w.+-]+@[\w-]+\.[\w.-]+~u', '[…]', $t);
        return (string) preg_replace('~\+?\d[\d\s/().-]{6,}\d~', '[…]', $t);
    }

    /** Systemanweisung für die Rolle oder für die Rückmeldung. */
    public static function anweisung(array $szene, string $sprache, bool $auswertung): string
    {
        $spr = ['de' => 'Deutsch', 'it' => 'Italienisch (Sie-Form: „Lei“)', 'en' => 'Englisch'][$sprache] ?? 'Italienisch';
        if ($auswertung) {
            return "Du bist ein erfahrener, freundlicher Verkaufstrainer von Vecom Design (Webdesign). Werte das folgende Übungsgespräch eines Vertriebspartners aus. "
                . "Antworte auf $spr, höchstens 120 Wörter: zuerst zwei Dinge, die gut waren, dann zwei konkrete Verbesserungen, dann der beste nächste Satz. "
                . "Regeln der Academy: Der Partner nennt keine Preise, Rabatte oder Termine (das entscheidet Vecom; den Richtpreis zeigt der Fragebogen über seinen Link), "
                . "stellt offene Fragen, hört zu, macht keinen Druck, redet nicht schlecht über andere und vereinbart einen kleinen nächsten Schritt. "
                . "Ziel der Übung: " . (string) ($szene['ziel'] ?? '');
        }
        return "Rollenspiel zum Üben. Du bist: " . (string) ($szene['rolle'] ?? '') . " Ein Vertriebspartner von Vecom Design (Webdesign) spricht dich an. "
            . "Bleib in deiner Rolle, antworte auf $spr, natürlich und kurz (höchstens drei Sätze), mit echten Einwänden, aber fair: Wenn der Partner gute Fragen stellt "
            . "und einen sinnvollen nächsten Schritt vorschlägt, darfst du offener werden. Wenn er Preise, Rabatte oder Termine verspricht, werde misstrauisch. "
            . "Frag nie nach persönlichen Daten und nenne selbst keine. Sag nicht, dass du eine KI bist, außer der Partner fragt direkt.";
    }

    /** Heute schon gesendete Nachrichten des Partners. */
    public static function heute(int $partnerId): int
    {
        try { return (int) Db::wert("SELECT anzahl FROM partner_zaehler WHERE partner_id = ? AND art = 'sim' AND tag = CURDATE()", [$partnerId], 0); }
        catch (Throwable $e) { return 0; }
    }

    /**
     * Eine Antwort des Gegenübers (oder die Rückmeldung). $verlauf: Liste [rolle, text]
     * aus der Sitzung. Ohne aktiven Simulator passiert nichts.
     * @return array{ok:bool, text:string, grund?:string}
     */
    public static function antwort(int $partnerId, array $szene, array $verlauf, string $sprache, bool $auswertung, ?callable $senden = null): array
    {
        if (!self::aktiv() && $senden === null) { return ['ok' => false, 'text' => '', 'grund' => 'aus']; }
        if (self::heute($partnerId) >= self::TAG_MAX) { return ['ok' => false, 'text' => '', 'grund' => 'tag']; }
        $nachrichten = [];
        if ($auswertung) {
            $protokoll = '';
            foreach ($verlauf as [$r, $t]) { $protokoll .= ($r === 'partner' ? 'Partner: ' : 'Betrieb: ') . $t . "\n"; }
            $nachrichten[] = ['role' => 'user', 'content' => $protokoll !== '' ? $protokoll : '(leer)'];
        } else {
            foreach ($verlauf as [$r, $t]) { $nachrichten[] = ['role' => $r === 'partner' ? 'user' : 'assistant', 'content' => (string) $t]; }
            if (!$nachrichten || $nachrichten[0]['role'] !== 'user') { return ['ok' => false, 'text' => '', 'grund' => 'leer']; }
        }
        $anfrage = ['model' => self::modell(), 'max_tokens' => $auswertung ? 400 : 200, 'system' => self::anweisung($szene, $sprache, $auswertung), 'messages' => $nachrichten];
        $roh = $senden ? $senden($anfrage) : self::senden($anfrage);
        Db::run("INSERT INTO partner_zaehler (partner_id, art, tag, anzahl) VALUES (?, 'sim', CURDATE(), 1) ON DUPLICATE KEY UPDATE anzahl = anzahl + 1", [$partnerId]);
        $text = '';
        foreach ((array) ($roh['content'] ?? []) as $teil) { if (($teil['type'] ?? '') === 'text') { $text .= (string) $teil['text']; } }
        $text = trim($text);
        return $text === '' ? ['ok' => false, 'text' => '', 'grund' => 'fehler'] : ['ok' => true, 'text' => mb_substr($text, 0, 2000)];
    }

    /** Der eigentliche Aufruf — nur mit Schlüssel, kurze Zeitgrenze, keine Partnerdaten im Kopf. */
    private static function senden(array $anfrage): array
    {
        if (!function_exists('curl_init') || self::schluessel() === '') { return []; }
        $c = curl_init(self::API);
        curl_setopt_array($c, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['content-type: application/json', 'anthropic-version: 2023-06-01', 'x-api-key: ' . self::schluessel()],
            CURLOPT_POSTFIELDS => json_encode($anfrage, JSON_UNESCAPED_UNICODE),
        ]);
        $r = curl_exec($c);
        $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return $code === 200 && is_string($r) ? (json_decode($r, true) ?: []) : [];
    }
}
