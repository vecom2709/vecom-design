<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';

/**
 * Der eine Weg zur Sprach-KI für Texte an Kunden, Betriebe und Partner
 * (07.10.2026, Uwe: „jede email oder whatsapp soll individuell angepasst und intelligent sein“).
 *
 * WAS HIER GILT
 *  - Schlüssel nur aus app/config.local.php („ki_schluessel“) — den trägt Uwe selbst ein, nie Claude.
 *  - Ein kleines Modell (Standard claude-haiku-4-5), kurze Antworten, 8 s Zeitlimit: Eine Mail darf nie
 *    an der KI hängen. Kommt nichts, geht die feste Vorlage — still und ohne Fehler beim Kunden.
 *  - Monatsdeckel (Einstellung ki_budget_cent, Standard 500 = 5 €). Ist er erreicht, schreibt bis
 *    zum Monatsende wieder nur die Vorlage, und Uwe bekommt einmal eine Meldung.
 *  - Jede Anfrage wird gezählt (ki_verbrauch) — sichtbar unter Einstellungen → Claude.
 */
final class Ki
{
    private const API = 'https://api.anthropic.com/v1/messages';

    /** Prüfnaht: fn(string $system, string $nutzer, string $zweck): ?string — ersetzt die API in der Kette. */
    public static $antwort = null;

    public static function schluessel(): string { return trim((string) Config::get('ki_schluessel', '')); }
    public static function modell(): string { return (string) Config::get('ki_modell', 'claude-haiku-4-5'); }

    private static function einstellung(string $k, string $ersatz): string
    {
        try { $w = Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], null); return $w === null ? $ersatz : (string) $w; }
        catch (Throwable $e) { return $ersatz; }
    }

    /** Ist die KI für Texte eingeschaltet (Schalter + Schlüssel)? */
    public static function an(): bool
    {
        if (self::einstellung('ki_texte_an', '1') !== '1') { return false; }
        return self::$antwort !== null || self::schluessel() !== '';
    }

    /** Ein einzelner Bereich (kunde, partner, folge, antwort, whatsapp) — einzeln abschaltbar. */
    public static function bereichAn(string $bereich): bool
    {
        return self::an() && self::einstellung('ki_bereich_' . $bereich, '1') === '1';
    }

    public static function budgetCent(): int { return max(0, (int) self::einstellung('ki_budget_cent', '500')); }

    public static function monat(): string { return date('Y-m'); }

    /** @return array{anfragen:int, angenommen:int, abgelehnt:int, fehler:int, tokens_ein:int, tokens_aus:int, kosten_cent:float} */
    public static function verbrauch(?string $monat = null): array
    {
        $z = null;
        try { $z = Db::one('SELECT * FROM ki_verbrauch WHERE monat = ?', [$monat ?? self::monat()]); } catch (Throwable $e) { }
        return ['anfragen' => (int) ($z['anfragen'] ?? 0), 'angenommen' => (int) ($z['angenommen'] ?? 0), 'abgelehnt' => (int) ($z['abgelehnt'] ?? 0),
                'fehler' => (int) ($z['fehler'] ?? 0), 'tokens_ein' => (int) ($z['tokens_ein'] ?? 0), 'tokens_aus' => (int) ($z['tokens_aus'] ?? 0),
                'kosten_cent' => round(((int) ($z['kosten_hcent'] ?? 0)) / 100, 2)];
    }

    public static function budgetErreicht(): bool
    {
        $b = self::budgetCent();
        return $b > 0 && self::verbrauch()['kosten_cent'] >= $b;
    }

    private static function zaehlen(array $spalten): void
    {
        $set = [];
        $werte = [self::monat()];
        foreach ($spalten as $k => $v) { $set[] = "$k = $k + ?"; $werte[] = (int) $v; }
        try {
            Db::run('INSERT IGNORE INTO ki_verbrauch (monat) VALUES (?)', [self::monat()]);
            Db::run('UPDATE ki_verbrauch SET ' . implode(', ', $set) . ' WHERE monat = ?', array_merge(array_slice($werte, 1), [$werte[0]]));
        } catch (Throwable $e) { /* Zählen ist Beiwerk */ }
    }

    /** Der Prüfer hat einen Text angenommen oder verworfen — für die Statistik. */
    public static function vermerken(bool $angenommen): void
    {
        self::zaehlen([$angenommen ? 'angenommen' : 'abgelehnt' => 1]);
    }

    /**
     * Einen Text schreiben lassen. null = nichts gekommen (aus, Deckel, Netz, Fehler) — dann gilt die Vorlage.
     */
    public static function schreiben(string $zweck, string $system, string $nutzer, int $maxTokens = 350): ?string
    {
        if (!self::an()) { return null; }
        if (self::budgetErreicht()) {
            self::deckelMelden();
            return null;
        }
        if (self::$antwort !== null) {
            self::zaehlen(['anfragen' => 1]);
            $t = (self::$antwort)($system, $nutzer, $zweck);
            return is_string($t) && trim($t) !== '' ? trim($t) : null;
        }
        if (!function_exists('curl_init')) { return null; }
        self::zaehlen(['anfragen' => 1]);
        $c = curl_init(self::API);
        curl_setopt_array($c, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => ['content-type: application/json', 'anthropic-version: 2023-06-01', 'x-api-key: ' . self::schluessel()],
            CURLOPT_POSTFIELDS => json_encode(['model' => self::modell(), 'max_tokens' => max(60, min(900, $maxTokens)), 'system' => $system,
                'messages' => [['role' => 'user', 'content' => $nutzer]]], JSON_UNESCAPED_UNICODE),
        ]);
        $roh = curl_exec($c);
        $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        $j = is_string($roh) ? json_decode($roh, true) : null;
        if ($code !== 200 || !is_array($j)) { self::zaehlen(['fehler' => 1]); return null; }
        $ein = (int) ($j['usage']['input_tokens'] ?? 0);
        $aus = (int) ($j['usage']['output_tokens'] ?? 0);
        /* Preise je Million Tokens in Cent (Standard Haiku 4.5: 100 / 500) — in der Konfiguration änderbar. */
        $hcent = (int) ceil(($ein * (float) Config::get('ki_preis_ein', 100) + $aus * (float) Config::get('ki_preis_aus', 500)) / 1_000_000 * 100);
        self::zaehlen(['tokens_ein' => $ein, 'tokens_aus' => $aus, 'kosten_hcent' => $hcent]);
        $text = '';
        foreach ((array) ($j['content'] ?? []) as $teil) { if (($teil['type'] ?? '') === 'text') { $text .= (string) $teil['text']; } }
        return trim($text) !== '' ? trim($text) : null;
    }

    /** Die Bereiche, die einzeln abschaltbar sind — mit Uwes Worten. */
    public const BEREICHE = [
        'kunde' => 'Kunden-Mails: persönlicher Absatz und nächster Schritt',
        'partner' => 'Partner-Mails: Zahlen und ein Tipp',
        'folge' => 'Folge-Mails der Akquise: je ein neuer Befund',
        'antwort' => 'Antwortentwürfe in AI Freigaben',
        'whatsapp' => 'WhatsApp-Vorlagen: persönlicher Satz',
        'whatsapp_direkt' => 'WhatsApp im 24-Stunden-Fenster: Anruf- und Info-Wunsch sofort beantworten',
    ];

    /** Für die Einstellungsseite. */
    public static function stand(): array
    {
        $b = [];
        foreach (array_keys(self::BEREICHE) as $k) { $b[$k] = self::einstellung('ki_bereich_' . $k, '1') === '1'; }
        return ['an' => self::einstellung('ki_texte_an', '1') === '1', 'schluessel' => self::schluessel() !== '', 'modell' => self::modell(),
                'budget_cent' => self::budgetCent(), 'verbrauch' => self::verbrauch(), 'vormonat' => self::verbrauch(date('Y-m', strtotime('first day of last month'))),
                'bereiche' => $b];
    }

    /** Uwe speichert Schalter und Budget. */
    public static function speichern(array $d): array
    {
        $vorher = self::stand();
        $setzen = static fn(string $k, string $v) => Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        $setzen('ki_texte_an', !empty($d['an']) ? '1' : '0');
        foreach (array_keys(self::BEREICHE) as $k) { $setzen('ki_bereich_' . $k, !empty($d['bereich'][$k]) ? '1' : '0'); }
        $euro = (float) str_replace(',', '.', (string) ($d['budget_euro'] ?? '5'));
        $setzen('ki_budget_cent', (string) max(0, min(100000, (int) round($euro * 100))));
        return [$vorher, self::stand()];
    }

    private static function deckelMelden(): void
    {
        $k = 'ki_deckel_gemeldet_' . self::monat();
        try {
            if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], '') !== '') { return; }
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, date('Y-m-d H:i:s')]);
            require_once __DIR__ . '/Events.php';
            Events::melden('ki_deckel', 'KI-Texte: Monatsbudget erreicht', 'info',
                'Bis zum Monatsende gehen wieder die festen Vorlagen raus. Das Budget lässt sich unter Einstellungen → Claude erhöhen.', '/einstellungen?b=claude');
        } catch (Throwable $e) { }
    }
}
