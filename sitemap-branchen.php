<?php
declare(strict_types=1);
/* Sitemap der Branchen-Stadt-Seiten (28.09.2026, W1) — je Seite alle drei Sprachen. */
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$aus = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
if (is_file(__DIR__ . '/app/config.local.php')) {
    try {
        foreach (['Config', 'Db', 'Akquise', 'BranchenStatistik'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $x = static fn(string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $aus .= '  <url><loc>' . $x($basis . '/siti-web/') . "</loc></url>\n";
        foreach (BranchenStatistik::liste(null, 5000) as $s) {
            $alt = '';
            foreach (['it', 'de', 'en'] as $l) { $alt .= '<xhtml:link rel="alternate" hreflang="' . $l . '" href="' . $x(BranchenStatistik::adresse($s, $l)) . '"/>'; }
            foreach (['it', 'de', 'en'] as $l) {
                $aus .= '  <url><loc>' . $x(BranchenStatistik::adresse($s, $l)) . '</loc><lastmod>' . date('Y-m-d', strtotime($s['aktualisiert'])) . '</lastmod>' . $alt . "</url>\n";
            }
        }
    } catch (Throwable $e) { /* leere Sitemap statt Fehler */ }
}
echo $aus . '</urlset>' . "\n";
