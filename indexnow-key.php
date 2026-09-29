<?php
declare(strict_types=1);
/* IndexNow (29.09.2026): Schlüssel zum Nachweis, dass die Meldungen von dieser Website kommen. */
header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(404); exit; }
foreach (['Config', 'Db', 'Akquise', 'BranchenStatistik'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
try { echo BranchenStatistik::indexNowKey(); } catch (Throwable $e) { http_response_code(503); }
