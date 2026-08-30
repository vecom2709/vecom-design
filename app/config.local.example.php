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
];
