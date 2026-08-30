<?php
/* Liefert nur die Besuchszahl. Keine personenbezogenen Daten. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
$d = __DIR__ . '/besuche.csv';
$gesamt = 0; $heute = 0; $t = date('Y-m-d');
if (is_readable($d)) {
    $fh = fopen($d, 'r');
    if ($fh) {
        while (($z = fgets($fh)) !== false) {
            if (trim($z) === '') continue;
            $gesamt++;
            if (strncmp($z, $t, 10) === 0) $heute++;
        }
        fclose($fh);
    }
}
echo json_encode(['gesamt' => $gesamt, 'heute' => $heute]);
