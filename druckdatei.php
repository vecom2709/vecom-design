<?php
declare(strict_types=1);
/* ==========================================================================
   druckdatei.php — hier holt der Druckanbieter (Gelato) die Druckdatei ab
   (Marketing Center, Phase 4, 03.10.2026).

   Nur mit unterschriebenem, befristetem Link aus Gelato::dateiLink(). Es
   wird genau die Datei ausgeliefert, die beim Freigeben entstanden ist —
   und nur, wenn der Entwurf freigegeben ist oder zu einer Bestellung gehört.
   Ohne gültigen Link: 404, ohne Hinweis warum.
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(404); exit; }
foreach (['Config', 'Db', 'Status', 'Fmt', 'Events', 'Gelato', 'Druckerei'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }

/* Fassungen (04.10.2026): f=druck (4 mm, Gelato), f=frei (genau die
   freigegebene Datei, HelloPrint u. a.), f=pf_vorn/pf_hinten (Printful,
   eingepasst, JPEG). Links ohne f sind die älteren Gelato-Links und liefern
   die Druckfassung. */
if (isset($_GET['f'])) {
    [$id, $fassung] = Druckerei::linkPruefen((string) ($_GET['e'] ?? ''), (string) ($_GET['x'] ?? ''), (string) $_GET['f'], (string) ($_GET['s'] ?? ''));
} else {
    $id = Gelato::linkPruefen((string) ($_GET['e'] ?? ''), (string) ($_GET['x'] ?? ''), (string) ($_GET['s'] ?? ''));
    $fassung = 'druck';
}
// Probe-Entwurf: die Musterkarte (kein Partner) — nur mit gültigem Link, Entwurfs-id 0.
// ($fassung ist nur gesetzt, wenn die Unterschrift stimmt — linkPruefen liefert sonst '').
if (str_starts_with((string) $fassung, 'probe_') && $id === 0) {
    $muster = Druckerei::musterDatei((string) $fassung);
    if ($muster === '') { http_response_code(404); exit; }
    $istPdf = $fassung === 'probe_druck';
    header('Content-Type: ' . ($istPdf ? 'application/pdf' : 'image/jpeg'));
    header('Content-Disposition: inline; filename="vecom-probe-' . substr((string) $fassung, 6) . ($istPdf ? '.pdf' : '.jpg') . '"');
    header('Content-Length: ' . strlen($muster));
    echo $muster;
    exit;
}
$spalte = ['frei' => 'datei', 'druck' => 'datei_druck', 'pf_vorn' => 'datei_pf_vorn', 'pf_hinten' => 'datei_pf_hinten'][$fassung] ?? '';
if ($spalte === '') { http_response_code(404); exit; }
// Druckfassung = Ansicht (Wandkalender): nur einmal gespeichert, erkennbar am gleichen Hash.
$wert = $spalte === 'datei_druck' ? 'COALESCE(e.datei_druck, IF(e.datei_druck_hash = e.datei_hash, e.datei, NULL))' : "e.$spalte";
$d = $id > 0 ? Db::one("SELECT e.id, $wert AS datei_druck FROM wm_entwuerfe e
                         WHERE e.id = ? AND $wert IS NOT NULL
                           AND (e.status IN ('freigegeben', 'ersetzt') OR EXISTS (SELECT 1 FROM wm_positionen x WHERE x.entwurf_id = e.id))", [$id]) : null;
if (!$d) { http_response_code(404); exit; }

// Printful bekommt je Seite ein JPEG (eingepasst 90 × 50 mm), alle anderen das PDF.
$bild = str_starts_with($fassung, 'pf_');
header('Content-Type: ' . ($bild ? 'image/jpeg' : 'application/pdf'));
header('Content-Disposition: inline; filename="vecom-druck-' . (int) $d['id'] . ($bild ? '-' . substr($fassung, 3) . '.jpg' : '.pdf') . '"');
header('Content-Length: ' . strlen((string) $d['datei_druck']));
echo $d['datei_druck'];
