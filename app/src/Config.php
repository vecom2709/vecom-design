<?php
declare(strict_types=1);

/** Laedt die lokale Konfiguration. Ohne sie laeuft nichts — das ist Absicht. */
final class Config
{
    private static ?array $data = null;

    public static function all(): array
    {
        if (self::$data === null) {
            $file = dirname(__DIR__) . '/config.local.php';
            if (!is_file($file)) {
                http_response_code(500);
                exit('Konfiguration fehlt: app/config.local.php anlegen (Vorlage: config.local.example.php).');
            }
            $cfg = require $file;
            $cfg += ['basis' => '/app', 'firma' => 'Vecom Design', 'mwst' => 0.0, 'zeitzone' => 'Europe/Rome'];
            self::$data = $cfg;
        }
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /** Basis-URL des Admin-Bereichs, ohne Schraegstrich am Ende. */
    public static function basis(): string
    {
        return rtrim((string) self::get('basis', '/app'), '/');
    }
}
