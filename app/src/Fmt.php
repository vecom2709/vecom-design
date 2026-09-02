<?php
declare(strict_types=1);

/** Darstellung: Betraege, Datum, Text. Betraege liegen intern als Cent vor. */
final class Fmt
{
    public static function geld(?int $cents, string $waehrung = 'EUR'): string
    {
        $cents ??= 0;
        $zeichen = $waehrung === 'EUR' ? '€' : $waehrung;
        return number_format($cents / 100, 2, ',', '.') . ' ' . $zeichen;
    }

    public static function datum(?string $wert): string
    {
        if (!$wert) { return '—'; }
        $t = strtotime($wert);
        return $t ? date('d.m.Y', $t) : '—';
    }

    /**
     * "Montag, 2. September 2026" — auf Deutsch, ohne Locale-Einstellung.
     *
     * strftime() ist seit PHP 8.1 abgekuendigt und IntlDateFormatter setzt
     * eine Erweiterung voraus, die auf diesem Webspace nicht da sein muss.
     * Zwoelf Woerter sind billiger als eine Abhaengigkeit.
     */
    public static function langesDatum(?string $wert = null): string
    {
        $t = $wert ? strtotime($wert) : time();
        if ($t === false) { return '—'; }
        $tage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $monate = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                   'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        return $tage[(int) date('w', $t)] . ', ' . (int) date('j', $t) . '. '
             . $monate[(int) date('n', $t)] . ' ' . date('Y', $t);
    }

    public static function zeit(?string $wert): string
    {
        if (!$wert) { return '—'; }
        $t = strtotime($wert);
        return $t ? date('d.m.Y H:i', $t) : '—';
    }

    /** "vor 3 Stunden" — fuer den Aktivitaetsverlauf. */
    public static function seit(?string $wert): string
    {
        if (!$wert) { return '—'; }
        $t = strtotime($wert);
        if (!$t) { return '—'; }
        $d = time() - $t;
        if ($d < 60)    { return 'gerade eben'; }
        if ($d < 3600)  { return 'vor ' . (int) ($d / 60) . ' Min.'; }
        if ($d < 86400) { return 'vor ' . (int) ($d / 3600) . ' Std.'; }
        if ($d < 604800){ return 'vor ' . (int) ($d / 86400) . ' Tagen'; }
        return date('d.m.Y', $t);
    }

    public static function bytes(int $b): string
    {
        $e = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
        return round($b, $i ? 1 : 0) . ' ' . $e[$i];
    }

    public static function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}
