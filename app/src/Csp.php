<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   Csp.php — Content-Security-Policy erst nur melden (Etappe 0b, 05.10.2026,
   Uwe: „Ja“ zu Etappe 0, Sicherheit zuerst).

   p.php, analisi.php, website-check.php und analyse.php haben schon eine
   scharfe CSP. Verwaltung und Partnerbereich hatten keine — dort hätte ein
   eingeschleustes Skript alles lesen dürfen: Provisionen, Kunden, Schlüssel.

   WARUM ERST NUR MELDEN: Beide Bereiche sind über Jahre gewachsen, mit
   Inline-Skripten, onclick-Attributen und eingebetteten Daten. Eine scharfe
   Regel ab heute würde Knöpfe still lahmlegen. Report-Only blockiert nichts
   und sagt, was blockiert WÜRDE. Erst wenn die Liste in der Verwaltung
   (Monitoring) leer ist oder nur Bekanntes zeigt, wird scharf geschaltet.

   DATENSCHUTZ: Gespeichert werden nur Bereich, Regel, Herkunft (Schema und
   Host, ohne Pfad) und der Pfad der Seite ohne Abfrage — der Partnerlink
   trägt seinen Schlüssel in ?t=…, der darf hier nie landen. Keine IP.
   ========================================================================== */
final class Csp
{
    public const BEREICHE = ['verwaltung', 'partner'];
    /** Was erlaubt wäre. Stripe nur als Formularziel (Weiterleitung zur Einrichtung/Zahlung). */
    public const REGELN = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; "
        . "font-src 'self'; connect-src 'self'; media-src 'self' blob:; frame-src 'self'; worker-src 'self'; manifest-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self' https://checkout.stripe.com https://connect.stripe.com; frame-ancestors 'self'";
    /** Höchstens so viele verschiedene Meldungen — danach zählen nur noch die bekannten hoch (der Endpunkt ist öffentlich). */
    public const HOECHSTENS = 400;
    public const GROESSE = 16384;

    public static function kopf(string $bereich): string
    {
        $bereich = in_array($bereich, self::BEREICHE, true) ? $bereich : 'verwaltung';
        return 'Content-Security-Policy-Report-Only: ' . self::REGELN . '; report-uri /csp-bericht.php?b=' . $bereich;
    }

    public static function melden(string $bereich): void
    {
        if (!headers_sent()) { header(self::kopf($bereich)); }
    }

    /** Herkunft ohne Pfad und Abfrage: „https://evil.example“, „inline“, „eval“, „data“ … */
    public static function herkunft(string $uri): string
    {
        $uri = trim($uri);
        if ($uri === '' ) { return 'leer'; }
        if (in_array($uri, ['inline', 'eval', 'self', 'data', 'blob', 'wasm-eval', 'trusted-types-policy', 'trusted-types-sink'], true)) { return $uri; }
        $teile = parse_url($uri);
        if (!is_array($teile) || empty($teile['scheme'])) { return mb_substr((string) preg_replace('~[^a-z0-9:._-]~i', '', $uri), 0, 40) ?: 'unbekannt'; }
        $schema = strtolower((string) $teile['scheme']);
        if (in_array($schema, ['data', 'blob', 'about', 'chrome-extension', 'moz-extension', 'safari-extension'], true)) { return $schema; }
        return mb_substr($schema . '://' . strtolower((string) ($teile['host'] ?? '')) . (isset($teile['port']) ? ':' . (int) $teile['port'] : ''), 0, 120);
    }

    /** Pfad der Seite ohne Abfrage und Anker („/partner.php“, „/app/kunden“). */
    public static function seite(string $uri): string
    {
        $pfad = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
        $pfad = (string) preg_replace('~/\d+(?=/|$)~', '/N', $pfad);   // Nummern zusammenfassen: /app/kunden/17 → /app/kunden/N
        return mb_substr($pfad !== '' ? $pfad : '/', 0, 120);
    }

    /**
     * Eine Meldung des Browsers aufnehmen (alter report-uri-Weg oder Reporting API).
     * @return int wie viele Einträge gezählt wurden
     */
    public static function bericht(string $roh, string $bereich): int
    {
        if ($roh === '' || strlen($roh) > self::GROESSE || !in_array($bereich, self::BEREICHE, true)) { return 0; }
        $j = json_decode($roh, true);
        if (!is_array($j)) { return 0; }
        // report-uri: {"csp-report": {...}}; Reporting API: [{"type":"csp-violation","body":{...}}]
        $liste = isset($j['csp-report']) ? [$j['csp-report']] : array_map(static fn($x) => is_array($x) ? ($x['body'] ?? []) : [], array_is_list($j) ? $j : []);
        $n = 0;
        foreach (array_slice($liste, 0, 10) as $r) {
            if (!is_array($r)) { continue; }
            $direktive = (string) ($r['effective-directive'] ?? $r['effectiveDirective'] ?? $r['violated-directive'] ?? $r['violatedDirective'] ?? '');
            $direktive = mb_substr((string) preg_replace('~[^a-z-]~', '', strtolower((string) strtok($direktive, ' '))), 0, 40);
            if ($direktive === '') { continue; }
            $quelle = self::herkunft((string) ($r['blocked-uri'] ?? $r['blockedURL'] ?? ''));
            $seite = self::seite((string) ($r['document-uri'] ?? $r['documentURL'] ?? ''));
            $da = Db::run('UPDATE csp_berichte SET anzahl = anzahl + 1, zuletzt = NOW() WHERE bereich = ? AND direktive = ? AND quelle = ? AND seite = ?',
                [$bereich, $direktive, $quelle, $seite])->rowCount();
            if ($da === 0) {
                if ((int) Db::wert('SELECT COUNT(*) FROM csp_berichte', [], 0) >= self::HOECHSTENS) { continue; }
                Db::run('INSERT IGNORE INTO csp_berichte (bereich, direktive, quelle, seite, anzahl, zuerst, zuletzt) VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
                    [$bereich, $direktive, $quelle, $seite]);
            }
            $n++;
        }
        return $n;
    }

    /** @return list<array<string,mixed>> häufigste zuerst */
    public static function liste(int $max = 60): array
    {
        try {
            return Db::all('SELECT bereich, direktive, quelle, seite, anzahl, zuerst, zuletzt FROM csp_berichte ORDER BY anzahl DESC, zuletzt DESC LIMIT ' . max(1, min(500, $max)));
        } catch (Throwable $e) { return []; }
    }
}
