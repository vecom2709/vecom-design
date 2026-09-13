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
        /* Woran sich die Menge bemisst: 'stueck' oder 'seite'. Ohne das
           stuende hinter der Sprache „je Stueck" — richtig fuer eine weitere
           Seite, falsch fuer eine Sprache, die je Seite gerechnet wird, und
           falsch genau dort, wo der Kunde nachrechnet. */
        'einheit'   => (string) ($b['einheit'] ?? 'stueck'),
        'anfrage'   => $nurAnfrage,
    ];
}

/* --------------------------------------------------------------------------
   Die vier typischen Faelle.

   WARUM SIE DURCH DEN KONFIGURATOR LAUFEN

   Hier standen bis zum 13.09.2026 vier Rezepte: Bausteinnamen mit Mengen, von
   Hand zusammengestellt. Der Gedanke war, dass rechnen() Vorschlaege
   mitbringt, die hier niemand sieht — das stimmt, sie stehen aber gesondert
   und nie in der Summe.

   Was der Gedanke kostete, zeigte sich am Tag von Migration 047. Der Deploy
   bringt den neuen Code sofort, die Migration laeuft erst beim naechsten
   Cronlauf — dazwischen lagen drei Minuten, in denen hier die NEUE Menge
   (zehn uebersetzte Seiten) auf die ALTEN Preise traf. Die Startseite zeigte
   1.900 bis 2.450 Euro, wo 800 bis 1.000 richtig gewesen waeren; im
   Konfigurator daneben stand die ganze Zeit die richtige Zahl. Zwei
   Rechenwege fuer dieselbe Frage laufen genau dann auseinander, wenn einer
   von beiden gerade geaendert wird.

   Deshalb beschreiben die vier Faelle jetzt keine Posten mehr, sondern
   ANTWORTEN — dieselben, die ein Kunde im Konfigurator gaebe. Gerechnet wird
   damit durch Baukasten::rechnen(), also durch denselben Weg, den auch das
   Angebot geht. Die Preisseite kann seither nicht mehr etwas anderes
   behaupten als der Konfigurator: Sie fragt ihn.

   Die Antworten sind so gewaehlt, dass nur die Posten anfallen, die in der
   Beschriftung stehen: Material vollstaendig (also keine Texte, keine Bilder
   zu machen), Seite neu (keine Uebernahme), Zeit offen (kein Express),
   Betreuung nein (sie steht als eigene Zahl daneben).

   Gerundet wird ueber Baukasten::spanne(), also mit derselben Staffel wie im
   Angebot. Sonst stuende auf der Preisseite eine andere Zahl als im Angebot,
   und das faellt genau dem Kunden auf, der beides gelesen hat.
   -------------------------------------------------------------------------- */
$grundantwort = [
    'material'  => ['texte', 'fotos', 'logo'],
    'bestand'   => 'neu',
    'zeit'      => 'offen',
    'betreuung' => 'nein',
];
$faelle = [];
$faelleAntworten = [
    /* Eine Seite, eine Sprache: nur das Grundgeruest. */
    'f1' => ['zweck' => ['zeigen'], 'umfang' => 'eine',   'sprachen' => 1],
    /* Fuenf Seiten — das Grundgeruest bringt die erste mit, vier kommen dazu. */
    'f2' => ['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 1],
    /* Dieselben fuenf Seiten in drei Sprachen. Wie viele uebersetzte Seiten
       das sind, entscheidet rechnen() — nicht diese Datei. */
    'f3' => ['zweck' => ['zeigen'], 'umfang' => 'wenige', 'sprachen' => 3],
    'f4' => ['zweck' => ['zeigen', 'shop'], 'umfang' => 'wenige', 'sprachen' => 1],
];
foreach ($faelleAntworten as $schluessel => $antworten) {
    try {
        $r = Baukasten::rechnen($antworten + $grundantwort, $katalog);
    } catch (Throwable $e) {
        continue;   // ein Beispiel weniger, aber keine falsche Zahl
    }
    $von = (int) $r['von_cents'];
    if ($von <= 0) { continue; }
    $g = Baukasten::spanne($von, (int) $r['bis_cents']);
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
