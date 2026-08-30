<?php
declare(strict_types=1);
/* ==========================================================================
   Liefert die oeffentlichen Pakete aus der Verwaltung an die Website.

   Damit erscheint ein Paket, das in der Verwaltung angelegt wird, auch auf
   vecom-design.it — und ein geloeschtes verschwindet dort wieder.

   Gibt ausschliesslich das aus, was ohnehin oeffentlich auf der Seite steht:
   Name, Untertitel, Preis, Leistungen. Keine Kundendaten, keine internen
   Felder. Ist die Verwaltung noch nicht eingerichtet oder die Datenbank
   nicht erreichbar, kommt eine leere Liste zurueck — die Website behaelt
   dann ihre fest eingebauten Karten.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

$leer = static function (string $grund = ''): never {
    echo json_encode(['pakete' => [], 'grund' => $grund], JSON_UNESCAPED_UNICODE);
    exit;
};

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { $leer('nicht eingerichtet'); }

require_once __DIR__ . '/app/src/Config.php';
require_once __DIR__ . '/app/src/Db.php';

$sprache = strtolower((string) ($_GET['lang'] ?? 'it'));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

/* Der Kaufknopf zeigt sich nur, wenn Stripe wirklich einsatzbereit ist und
   der Livemodus laeuft. Zum Ausprobieren laesst er sich in der Verwaltung
   voruebergehend auch fuer den Testmodus freigeben. */
require_once __DIR__ . '/app/src/Zahlung/Anbieter.php';
require_once __DIR__ . '/app/src/Zahlung/Stripe.php';
$kaufbar = false;
try {
    $stripe = new StripeAnbieter();
    $testSichtbar = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'direktkauf_test'", [], '0') === '1';
    $kaufbar = $stripe->bereit() && $stripe->webhookBereit()
        && ($stripe->modus() === 'live' || $testSichtbar);
} catch (Throwable $e) { $kaufbar = false; }

$kaufText = ['it' => 'Prenota ora', 'de' => 'Jetzt buchen', 'en' => 'Book now'][$sprache];

try {
    $reihen = Db::all(
        'SELECT slug, name, description, sub, ideal, price_cents, monthly_cents, currency,
                features, texte, popular, detail_url, direktkauf
         FROM packages
         WHERE active = 1 AND oeffentlich = 1
         ORDER BY sort, price_cents'
    );
} catch (Throwable $e) {
    $leer('Datenbank nicht erreichbar');
}

$pakete = [];
foreach ($reihen as $r) {
    $t = $r['texte'] ? (json_decode((string) $r['texte'], true) ?: []) : [];
    $s = $t[$sprache] ?? [];
    $grund = json_decode((string) ($r['features'] ?? '[]'), true) ?: [];

    $pakete[] = [
        'slug'      => $r['slug'],
        'name'      => trim((string) ($s['name'] ?? '')) !== '' ? $s['name'] : $r['name'],
        'sub'       => trim((string) ($s['sub'] ?? '')) !== '' ? $s['sub'] : (string) ($r['sub'] ?? ''),
        'ideal'     => trim((string) ($s['ideal'] ?? '')) !== '' ? $s['ideal'] : (string) ($r['ideal'] ?? ''),
        'features'  => (isset($s['features']) && is_array($s['features']) && $s['features']) ? array_values($s['features']) : $grund,
        'preis'     => (int) $r['price_cents'] / 100,
        'monat'     => (int) $r['monthly_cents'] / 100,
        'waehrung'  => $r['currency'],
        'beliebt'   => (bool) $r['popular'],
        'detail'    => (string) ($r['detail_url'] ?? ('pakete.html#' . $r['slug'])),
        'kaufbar'   => $kaufbar && (bool) $r['direktkauf'],
        'kauf_url'  => '/buchen.php?paket=' . rawurlencode((string) $r['slug']) . '&lang=' . $sprache,
    ];
}

echo json_encode([
    'pakete' => $pakete, 'sprache' => $sprache,
    'kauf_text' => $kaufText,
], JSON_UNESCAPED_UNICODE);
