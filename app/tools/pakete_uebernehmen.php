<?php
declare(strict_types=1);
/* Uebernimmt die drei Pakete, die auf vecom-design.it stehen, in die
   Verwaltung. Laeuft mehrfach ohne Schaden: vorhandene Pakete werden
   am Kuerzel erkannt und nicht doppelt angelegt. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Nur auf der Kommandozeile.'); }
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Db.php';

$pakete = [
    ['slug' => 'starter', 'name' => 'Starter', 'price_cents' => 49900, 'monthly_cents' => 3900,
     'sort' => 1, 'popular' => 0,
     'description' => 'Der Einstieg: eine klare, schnelle Seite, die Anfragen bringt.',
     'features' => ['Einseitige Website', 'Mobil optimiert', 'Kontaktformular', 'Grundlegende Suchmaschinenoptimierung']],
    ['slug' => 'business', 'name' => 'Business', 'price_cents' => 89900, 'monthly_cents' => 6900,
     'sort' => 2, 'popular' => 1,
     'description' => 'Der meistgewaehlte Umfang fuer Betriebe mit mehreren Leistungen.',
     'features' => ['Mehrere Unterseiten', 'Individuelles Design', 'Suchmaschinenoptimierung', 'Bildbearbeitung', 'Mehrsprachig moeglich']],
    ['slug' => 'premium', 'name' => 'Premium', 'price_cents' => 149900, 'monthly_cents' => 9900,
     'sort' => 3, 'popular' => 0,
     'description' => 'Der volle Auftritt mit filmischen Effekten und eigener Bildsprache.',
     'features' => ['Umfangreiche Website', 'Filmische Effekte', 'Eigene Bildsprache', 'Erweiterte Suchmaschinenoptimierung', 'Laufende Betreuung']],
];

foreach ($pakete as $p) {
    $daten = [
        'slug' => $p['slug'], 'name' => $p['name'], 'description' => $p['description'],
        'price_cents' => $p['price_cents'], 'monthly_cents' => $p['monthly_cents'], 'currency' => 'EUR',
        'features' => json_encode($p['features'], JSON_UNESCAPED_UNICODE),
        'active' => 1, 'popular' => $p['popular'], 'sort' => $p['sort'],
    ];
    $da = Db::one('SELECT id FROM packages WHERE slug = ?', [$p['slug']]);
    if ($da) { Db::update('packages', (int) $da['id'], $daten); echo "aktualisiert: {$p['name']}\n"; }
    else     { Db::insert('packages', $daten); echo "angelegt: {$p['name']}\n"; }
}
