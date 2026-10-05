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
        'webhook_geheim_connect' => '',      // wahlweise: whsec_… des Endpunkts für Ereignisse aus verbundenen Konten (account.updated der Partner)
        'oeffentlich'    => '',              // pk_test_… bzw. pk_live_… — Partnerkonto in der Sprache des Partners einrichten
    ],

    /* Marketing Center (03.10.2026): Druckanbieter Gelato. Den Schlüssel
       erzeugt Uwe im Gelato-Dashboard; er steht nur hier. Gesendet werden
       nur Entwürfe — gedruckt wird erst nach Bestätigung im Dashboard. */
    'gelato' => [
        'api' => '',                          // API-Schlüssel aus dem Gelato-Dashboard
    ],

    /* HelloPrint Connect (04.10.2026): Schlüssel über api@helloprint.com. Ein
       Konto liefert nur in sein eigenes Land. 'modus' bleibt 'test', bis der
       erste Testauftrag sauber durchlief — erst 'prod' druckt und berechnet. */
    'helloprint' => [
        'api'   => '',                        // x-api-key von HelloPrint
        'land'  => 'IT',                      // Land des Connect-Kontos
        'modus' => 'test',                    // 'test' oder 'prod'
    ],
    /* Printful (04.10.2026): privater Schlüssel aus dem Printful-Konto
       (Developers → Tokens). Konto-Schlüssel brauchen zusätzlich die Store-ID.
       Währung im Konto auf EUR. 'modus' bleibt 'entwurf' (Bestätigung im
       Printful-Dashboard), bis 'auftrag' gesetzt wird. */
    'printful' => [
        'api'   => '',                        // Bearer-Schlüssel
        'store' => '',                        // nur bei Konto-Schlüssel
        'modus' => 'entwurf',                 // 'entwurf' oder 'auftrag'
    ],
    // Gesprächssimulator der Partner Academy (Etappe 3, 05.10.2026): bleibt aus, solange leer.
    // Zusätzlich muss er in der Verwaltung eingeschaltet und die Datenschutzprüfung bestätigt sein.
    'ki_schluessel' => '',                     // Anthropic-API-Schlüssel
    'ki_modell'     => 'claude-haiku-4-5',
];
