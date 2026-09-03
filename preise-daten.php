<?php
declare(strict_types=1);
/* ==========================================================================
   preise-daten.php — die echten Preise fuer die oeffentliche Preisseite.

   WARUM ES DIESE DATEI GIBT

   Die Preisseite behauptet, sie zeige "die Preise, mit denen ich wirklich
   rechne". Das ist nur wahr, solange sie dieselbe Quelle benutzt wie der
   Baukasten. Fest ins HTML geschriebene Zahlen waeren spaetestens beim
   ersten Preisschritt eine Luege — und zwar eine, die monatelang niemandem
   auffaellt.

   Also kommen sie von hier: aus der Bausteintabelle, in ganzen Cent, in der
   Sprache des Lesers. Steigen die Preise, steigt die Seite mit.

   WAS HIER NICHT RAUSGEHT

   Nur was ohnehin auf jedem Angebot steht: Name, Beschreibung, Preis. Keine
   Kundendaten, keine internen Felder, keine Bausteine, die auf "inaktiv"
   stehen. Ist die Verwaltung nicht eingerichtet oder die Datenbank still,
   kommt eine leere Antwort zurueck — die Seite behaelt dann die Zahlen, die
   im HTML stehen.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$leer = static function (string $grund = ''): never {
    echo json_encode(['bausteine' => [], 'grund' => $grund], JSON_UNESCAPED_UNICODE);
    exit;
};

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { $leer('nicht eingerichtet'); }

require_once __DIR__ . '/app/src/Config.php';
require_once __DIR__ . '/app/src/Db.php';
require_once __DIR__ . '/app/src/Baukasten.php';

$sprache = strtolower((string) ($_GET['lang'] ?? 'it'));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

try {
    $katalog = Baukasten::katalog();
} catch (Throwable $e) {
    $leer('Datenbank nicht erreichbar');
}
if (!$katalog) { $leer('kein Baukasten'); }

/* Geld so schreiben, wie es im jeweiligen Land geschrieben wird. Auf der
   deutschen und italienischen Seite steht das Zeichen hinten, auf der
   englischen vorn — und der Tausenderpunkt ist nicht ueberall ein Punkt. */
$zahl = static function (int $cents) use ($sprache): string {
    $euro = (int) round($cents / 100);
    return $sprache === 'en'
        ? number_format($euro, 0, '.', ',')
        : number_format($euro, 0, ',', '.');
};

/* Eine Spanne wird zu einer Zeile. Das Zeichen steht einmal, nicht zweimal:
   "299 – 349 €" liest sich, "299 € – 349 €" wird gelesen. Ist die obere
   Grenze nicht gesetzt oder gleich der unteren, steht da nur eine Zahl —
   kein "299 bis 299". */
$spanneText = static function (int $von, int $bis) use ($zahl, $sprache): string {
    $links  = $zahl($von);
    $rechts = $bis > $von ? $zahl($bis) : '';
    if ($sprache === 'en') {
        return '€' . $links . ($rechts !== '' ? ' – ' . $rechts : '');
    }
    return $links . ($rechts !== '' ? ' – ' . $rechts : '') . ' €';
};

/* Einzelbetraege (Betreuung) gehen denselben Weg — ein Format, eine Stelle. */
$geld = static function (int $cents) use ($spanneText): string {
    return $spanneText($cents, 0);
};

/* --------------------------------------------------------------------------
   Die Bausteinliste.

   NUR_AUF_ANFRAGE bleibt drin, aber ohne Preis: Ein Logo wird nie automatisch
   gerechnet, und eine Zahl daneben wuerde genau das behaupten.
   -------------------------------------------------------------------------- */
$bausteine = [];
foreach ($katalog as $slug => $b) {
    if ((int) ($b['demo'] ?? 0) === 1) { continue; }
    $nurAnfrage = in_array((string) $slug, Baukasten::NUR_AUF_ANFRAGE, true);
    $von = (int) $b['preis_cents'];
    $bis = (int) $b['preis_bis_cents'];
    $bausteine[] = [
        'slug'      => (string) $slug,
        'gruppe'    => (string) $b['gruppe'],
        'name'      => Baukasten::name($b, $sprache),
        'text'      => Baukasten::text($b, $sprache),
        'preis'     => $nurAnfrage ? '' : $spanneText($von, $bis),
        'monatlich' => (int) $b['monatlich'] === 1,
        'je'        => (int) $b['je_einheit'] === 1,
        'anfrage'   => $nurAnfrage,
    ];
}

/* --------------------------------------------------------------------------
   Die vier typischen Faelle.

   Sie sind bewusst nicht aus Baukasten::rechnen() gezogen: rechnen() bringt
   Vorschlaege und Zuschlaege mit, die hier niemand sieht und die die Zahl
   unerklaerlich machen wuerden. Hier wird zusammengezaehlt, was in der
   Zeile daneben steht — wer nachrechnet, kommt auf dasselbe Ergebnis.

   Gerundet wird ueber Baukasten::spanne(), also mit derselben Staffel wie im
   Angebot. Sonst stuende auf der Preisseite eine andere Zahl als im Angebot,
   und das faellt genau dem Kunden auf, der beides gelesen hat.
   -------------------------------------------------------------------------- */
$stueck = static function (string $slug, int $menge = 1) use ($katalog): array {
    $b = $katalog[$slug] ?? null;
    if (!$b) { return [0, 0]; }
    $von = (int) $b['preis_cents'] * $menge;
    $bis = ((int) $b['preis_bis_cents'] ?: (int) $b['preis_cents']) * $menge;
    return [$von, $bis];
};

$faelle = [];
$rezepte = [
    'f1' => [['basis', 1]],
    'f2' => [['basis', 1], ['seite', 4]],
    'f3' => [['basis', 1], ['seite', 4], ['sprache', 2]],
    'f4' => [['basis', 1], ['seite', 4], ['shop', 1]],
];
foreach ($rezepte as $schluessel => $teile) {
    $von = 0; $bis = 0;
    $vollstaendig = true;
    foreach ($teile as [$slug, $menge]) {
        if (!isset($katalog[$slug])) { $vollstaendig = false; break; }
        [$v, $b] = $stueck($slug, $menge);
        $von += $v; $bis += $b;
    }
    if (!$vollstaendig || $von <= 0) { continue; }
    $g = Baukasten::spanne($von, $bis);
    $faelle[$schluessel] = $spanneText((int) $g['von_cents'], (int) $g['bis_cents']);
}

/* Die Betreuung steht als eigene Zahl daneben — sie ist ein zweiter Vertrag
   und keine Position der Website. */
$betreuung = '';
if (isset($katalog['betreuung_basis'])) {
    $betreuung = $geld((int) $katalog['betreuung_basis']['preis_cents']);
}

/* Einfuehrungspreise: Wie viele Projekte noch, bis alles teurer wird. Steht
   auf der Seite als Angabe, nicht als Countdown — es ist eine Tatsache, kein
   Druckmittel. */
$einfuehrung = null;
try {
    require_once __DIR__ . '/app/src/Einfuehrung.php';
    if (Einfuehrung::laeuft()) {
        $einfuehrung = [
            'ziel'   => Einfuehrung::ziel(),
            'fertig' => Einfuehrung::zaehler(),
            'offen'  => Einfuehrung::restplaetze(),
        ];
    }
} catch (Throwable $e) { $einfuehrung = null; }

echo json_encode([
    'sprache'     => $sprache,
    'bausteine'   => $bausteine,
    'faelle'      => $faelle,
    'betreuung'   => $betreuung,
    'einfuehrung' => $einfuehrung,
], JSON_UNESCAPED_UNICODE);
