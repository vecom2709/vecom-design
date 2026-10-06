<?php
declare(strict_types=1);

/**
 * AutoBuild Phase 7 — die automatischen Tests einer Fassung (06.10.2026).
 *
 * Laufen auf dem Server über den Dateien des Pakets, ohne Browser: was sich
 * am Quelltext sicher feststellen lässt. „schwer“ = muss stimmen, sonst ist
 * die Fassung nicht bestanden; die anderen sind Hinweise für Review und Uwe.
 *
 * Die Tests ersetzen keinen Blick auf die Testfassung — sie fangen nur das
 * Offensichtliche ab, bevor ein Mensch Zeit hineinsteckt.
 */
final class BauPruefung
{
    public const ENDUNGEN = ['html', 'htm', 'css', 'js', 'svg', 'txt', 'xml', 'json', 'webmanifest', 'ico', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'woff', 'woff2', 'pdf'];
    public const TEXT = ['html', 'htm', 'css', 'js', 'svg', 'txt', 'xml', 'json', 'webmanifest'];
    public const MAX_DATEIEN = 120;
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const PLATZHALTER = '~lorem ipsum|dolor sit amet|\[(platzhalter|placeholder|todo|text hier|bild hier)[^\]]*\]|\bTODO\b|\bFIXME\b|example\.(com|org)|via\.placeholder|placehold\.(co|it)|picsum\.photos|dummyimage~iu';

    /**
     * @param array<string,string> $dateien  Pfad => Inhalt
     * @return list<array{name:string, ok:bool, schwer:bool, detail:string}>
     */
    public static function pruefen(array $dateien): array
    {
        $t = [];
        $add = static function (string $name, bool $ok, bool $schwer, string $detail = '') use (&$t): void {
            $t[] = ['name' => $name, 'ok' => $ok, 'schwer' => $schwer, 'detail' => mb_substr($detail, 0, 400)];
        };
        $html = array_filter($dateien, static fn($v, $k) => (bool) preg_match('~\.html?$~i', (string) $k), ARRAY_FILTER_USE_BOTH);
        $summe = array_sum(array_map('strlen', $dateien));

        $add('Startseite index.html vorhanden', isset($dateien['index.html']), true);
        $add('Umfang im Rahmen', count($dateien) <= self::MAX_DATEIEN && $summe <= self::MAX_BYTES, true, count($dateien) . ' Dateien, ' . round($summe / 1024) . ' KB');

        $ohneDoctype = []; $ohneLang = []; $ohneTitel = []; $ohneViewport = []; $ohneBeschr = []; $h1 = []; $alt = []; $tote = []; $extern = []; $platz = []; $titel = [];
        foreach ($html as $pfad => $inhalt) {
            if (!preg_match('~^\s*<!doctype html~i', $inhalt)) { $ohneDoctype[] = $pfad; }
            if (!preg_match('~<html[^>]*\slang=["\']?[a-z]{2}~i', $inhalt)) { $ohneLang[] = $pfad; }
            if (!preg_match('~<title>\s*([^<]{3,})</title>~i', $inhalt, $m)) { $ohneTitel[] = $pfad; } else { $titel[$pfad] = trim($m[1]); }
            if (!preg_match('~<meta[^>]+name=["\']viewport["\']~i', $inhalt)) { $ohneViewport[] = $pfad; }
            if (!preg_match('~<meta[^>]+name=["\']description["\'][^>]+content=["\'][^"\']{20,}~i', $inhalt)) { $ohneBeschr[] = $pfad; }
            $n = preg_match_all('~<h1[\s>]~i', $inhalt);
            if ($n !== 1) { $h1[] = $pfad . ' (' . $n . '×)'; }
            if (preg_match_all('~<img\b(?![^>]*\balt=)[^>]*>~i', $inhalt, $mm)) { $alt[] = $pfad . ' (' . count($mm[0]) . ')'; }
            if (preg_match_all('~<script\b[^>]*\bsrc=["\']\s*(https?:)?//~i', $inhalt)) { $extern[] = $pfad; }
            if (preg_match(self::PLATZHALTER, strip_tags($inhalt) . ' ' . $inhalt, $pm)) { $platz[] = $pfad . ' („' . mb_substr($pm[0], 0, 30) . '“)'; }
            /* Interne Verweise: href/src ohne Schema, ohne #, ohne mailto/tel. */
            if (preg_match_all('~\b(?:href|src)=["\']([^"\'#?]+)~i', $inhalt, $lm)) {
                foreach ($lm[1] as $ziel) {
                    $ziel = trim($ziel);
                    if ($ziel === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//)~i', $ziel)) { continue; }
                    $voll = self::aufloesen($pfad, $ziel);
                    if ($voll === null) { $tote[] = $pfad . ' → ' . $ziel; continue; }
                    if (!isset($dateien[$voll]) && !isset($dateien[rtrim($voll, '/') . '/index.html']) && !isset($dateien[$voll . 'index.html'])) { $tote[] = $pfad . ' → ' . $ziel; }
                }
            }
        }
        foreach (array_filter($dateien, static fn($v, $k) => (bool) preg_match('~\.(css|js)$~i', (string) $k), ARRAY_FILTER_USE_BOTH) as $pfad => $inhalt) {
            if (preg_match(self::PLATZHALTER, $inhalt, $pm)) { $platz[] = $pfad . ' („' . mb_substr($pm[0], 0, 30) . '“)'; }
        }
        $liste = static fn(array $a): string => implode(', ', array_slice(array_unique($a), 0, 8)) . (count($a) > 8 ? ' …' : '');
        $add('Jede Seite beginnt mit <!doctype html>', !$ohneDoctype, true, $liste($ohneDoctype));
        $add('Sprache gesetzt (<html lang>)', !$ohneLang, true, $liste($ohneLang));
        $add('Jede Seite hat einen Titel', !$ohneTitel, true, $liste($ohneTitel));
        $add('Mobil: meta viewport', !$ohneViewport, true, $liste($ohneViewport));
        $add('Keine Platzhalter (Lorem ipsum, TODO, example.com …)', !$platz, true, $liste($platz));
        $add('Keine toten internen Links oder fehlenden Dateien', !$tote, true, $liste($tote));
        $add('Keine Skripte von fremden Servern', !$extern, true, $liste($extern));
        $add('Genau eine H1 je Seite', !$h1, false, $liste($h1));
        $add('Alle Bilder mit alt-Text', !$alt, false, $liste($alt));
        $add('SEO: Beschreibung (meta description) je Seite', !$ohneBeschr, false, $liste($ohneBeschr));
        $doppelt = array_keys(array_filter(array_count_values($titel), static fn($n) => $n > 1));
        $add('Titel nicht doppelt', !$doppelt, false, $liste($doppelt));
        $alle = implode(' ', array_map('strtolower', array_keys($dateien))) . ' ' . strtolower(implode(' ', $html));
        $add('Datenschutz/Impressum verlinkt', (bool) preg_match('~privacy|datenschutz|impressum|note-legali|note legali|informativa~', $alle), false);
        return $t;
    }

    /** @param list<array{ok:bool,schwer:bool}> $t */
    public static function bestanden(array $t): bool
    {
        foreach ($t as $x) { if ($x['schwer'] && !$x['ok']) { return false; } }
        return true;
    }

    /** Relativen Verweis von einer Seite aus auflösen; null = führt aus dem Paket hinaus. */
    public static function aufloesen(string $von, string $ziel): ?string
    {
        $ziel = rawurldecode($ziel);
        $basis = str_starts_with($ziel, '/') ? [] : array_slice(explode('/', $von), 0, -1);
        foreach (explode('/', ltrim($ziel, '/')) as $teil) {
            if ($teil === '' || $teil === '.') { continue; }
            if ($teil === '..') { if (!$basis) { return null; } array_pop($basis); continue; }
            $basis[] = $teil;
        }
        $r = implode('/', $basis);
        return str_ends_with($ziel, '/') ? ($r === '' ? '' : $r . '/') : $r;
    }

    /** Pfad aus Claudes Lieferung prüfen. null = unzulässig. */
    public static function pfadOk(string $p): ?string
    {
        $p = str_replace('\\', '/', trim($p));
        $p = ltrim($p, '/');
        if ($p === '' || str_contains($p, "\0") || preg_match('~(^|/)\.\.?(/|$)~', $p) || preg_match('~(^|/)\.~', $p) || strlen($p) > 180) { return null; }
        if (!preg_match('~^[A-Za-z0-9._/\-]+$~', $p)) { return null; }
        $e = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        return in_array($e, self::ENDUNGEN, true) ? $p : null;
    }
}
