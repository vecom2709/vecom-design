<?php
/* Vorlage. Auf dem Server zu app/config.local.php kopieren und ausfuellen.
   Diese Datei kommt NIE ins Repository und wird vom Deploy nie ueberschrieben.
   Die Zugangsdaten der Datenbank legst du im KAS unter "Datenbanken" an. */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'dXXXXXXX',
        'user' => 'dXXXXXXX',
        'pass' => 'DEIN-DATENBANK-PASSWORT',
    ],
    'basis'   => '/app',                       // Unterverzeichnis auf dem Webspace
    'firma'   => 'Vecom Design',
    'mwst'    => 0.0,                          // Steuersatz in Prozent, 0 = keine
    'zeitzone'=> 'Europe/Rome',
    'website' => 'https://vecom-design.it',

    /* Stripe. Die Schluessel stehen ausschliesslich hier auf dem Server —
       nie im Repository, nie im Browser. Solange 'geheim' leer ist, bleibt
       alles Uebrige unberuehrt: Zahlungen lassen sich weiter von Hand buchen.
       Im Testmodus braucht es weder Partita IVA noch echtes Geld. */
    'stripe' => [
        'modus'          => 'test',          // 'test' oder 'live'
        'geheim'         => '',              // sk_test_… bzw. sk_live_…
        'webhook_geheim' => '',              // whsec_… aus "Entwickler → Webhooks"
    ],
];
