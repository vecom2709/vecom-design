<?php
declare(strict_types=1);

/**
 * QR-Code als SVG, auf dem Server gerechnet (26.09.2026).
 *
 * Die Druckseite des Briefs zeichnet ihren QR-Code im Browser (qrcode.js).
 * Fuer den Briefdienst geht das nicht: Er bekommt fertiges HTML und fuehrt
 * kein Skript aus. Dieselbe Bibliothek gibt es vom selben Autor fuer PHP
 * (app/lib/qrcode.php, MIT) -- also rechnet der Server denselben Code.
 * Keine fremde Adresse sieht die Analyse-Adresse.
 */
final class QrBild
{
    /** @return string <svg>…</svg>, quadratisch, eine einzige Pfad-Flaeche */
    public static function svg(string $inhalt, int $masse = 120, int $rand = 2): string
    {
        require_once dirname(__DIR__) . '/lib/qrcode.php';
        $qr = QRCode::getMinimumQRCode($inhalt, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount();
        $weg = '';
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($qr->isDark($r, $c)) { $weg .= 'M' . ($c + $rand) . ' ' . ($r + $rand) . 'h1v1h-1z'; }
            }
        }
        $g = $n + 2 * $rand;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $g . ' ' . $g . '" width="' . $masse . '" height="' . $masse
            . '" shape-rendering="crispEdges" role="img" aria-label="QR"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $weg . '"/></svg>';
    }
}
