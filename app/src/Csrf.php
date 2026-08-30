<?php
declare(strict_types=1);

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function feld(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Bricht ab, wenn das Formular nicht von uns kam. */
    public static function pruefen(): void
    {
        $gesendet = (string) ($_POST['_csrf'] ?? '');
        if ($gesendet === '' || !hash_equals(self::token(), $gesendet)) {
            http_response_code(419);
            exit('Sitzung abgelaufen. Bitte die Seite neu laden und noch einmal versuchen.');
        }
    }
}
