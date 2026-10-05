<?php
declare(strict_types=1);
/* ==========================================================================
   go.php — der Kurzlink: /go/name und /go/name/branche (Phase 4, 05.10.2026).

   Nur ein Übersetzer. Der Name wird zum Code des Partners, die Branche zum
   Kanal „go-<branche>“ — dann übernimmt p.php, genau wie bei /p/CODE/kanal.
   So zählen Klick, Spur, Zuordnung und Provision ohne eigenen Weg, und was
   p.php kann (Wege, Rückruf, Sprachwahl, WhatsApp), kann der Kurzlink auch.

   Unbekannt, gesperrt oder pausiert: still auf die Startseite, wie bei /p/.
   Eine unbekannte Branche führt auf die normale Seite des Partners — ein
   Tippfehler auf einem Flyer soll keinen Besucher verlieren.
   ========================================================================== */

/* MULTIVIEWS wie bei p.php: Liefert Apache /go/name direkt an go.php aus (ohne ?n=), steht alles in der Adresse. */
if (!isset($_GET['n']) && preg_match('~^/go/([A-Za-z0-9-]{3,30})(?:/([A-Za-z-]{2,20}))?/?(?:[?#]|$)~', (string) ($_SERVER['REQUEST_URI'] ?? ''), $pfad)) {
    $_GET['n'] = $pfad[1];
    if (isset($pfad[2]) && $pfad[2] !== '' && !isset($_GET['b'])) { $_GET['b'] = $pfad[2]; }
}
$goName = strtolower((string) ($_GET['n'] ?? ''));
$goSlug = strtolower((string) ($_GET['b'] ?? ''));
unset($_GET['n'], $_GET['b']);
$goCode = null;
if ((is_file(__DIR__ . '/app/config.local.php') || (class_exists('Config', false) && Config::steht())) && $goName !== '') {
    try {
        foreach (['Config', 'Db', 'Events', 'Texte', 'PartnerKurzlink'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        $goP = PartnerKurzlink::aufloesen($goName);
        if ($goP !== null) {
            $goCode = (string) $goP['code'];
            $goB = $goSlug !== '' ? PartnerKurzlink::branche($goSlug) : null;
            $_GET['k'] = PartnerKurzlink::kanal($goB[0] ?? null);
            // Das Wort sagt die Sprache (ristoranti → it), solange der Besucher nicht selbst gewählt hat.
            // Sprache::ausAnfrage liest $_REQUEST — beides setzen.
            if (!isset($_GET['lang']) && ($goB[1] ?? null) !== null) { $_GET['lang'] = $_REQUEST['lang'] = $goB[1]; }
        }
    } catch (Throwable $e) { $goCode = null; }
}
if ($goCode === null) {
    header('Cache-Control: no-store');
    header('Location: /', true, 302);
    exit;
}
$_GET['c'] = $goCode;
require __DIR__ . '/p.php';
