<?php
declare(strict_types=1);

final class Auth
{
    public const ADMIN = 'admin';
    public const KUNDE = 'kunde';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Config::basis() ?: '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('vecomadmin');
        session_start();
    }

    public static function anmelden(string $email, string $passwort): bool
    {
        $u = Db::one('SELECT * FROM users WHERE email = ? AND active = 1', [mb_strtolower(trim($email))]);
        // Auch bei unbekannter Adresse rechnen, damit die Antwortzeit nichts verraet.
        // Der Zugriff geht ueber $u === null, sonst schreibt PHP bei jeder
        // unbekannten Adresse eine Warnung ins Fehlerprotokoll.
        $hash = $u !== null
            ? (string) $u['password_hash']
            : '$2y$12$ungueltigungueltigungueltigungueltigungueltigungueltigun';
        if (!password_verify($passwort, $hash) || $u === null) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['uid']  = (int) $u['id'];
        $_SESSION['rolle']= $u['role'];
        $_SESSION['name'] = $u['name'];
        $_SESSION['kunde']= $u['customer_id'] !== null ? (int) $u['customer_id'] : null;
        Db::update('users', (int) $u['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        return true;
    }

    public static function abmelden(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function angemeldet(): bool { return !empty($_SESSION['uid']); }
    public static function rolle(): ?string   { return $_SESSION['rolle'] ?? null; }
    public static function name(): string     { return (string) ($_SESSION['name'] ?? ''); }
    public static function id(): ?int         { return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null; }
    public static function istAdmin(): bool   { return self::rolle() === self::ADMIN; }

    /** Riegel vor jeder Admin-Seite. */
    public static function nurAdmin(): void
    {
        if (!self::angemeldet()) {
            header('Location: ' . Config::basis() . '/anmelden');
            exit;
        }
        if (!self::istAdmin()) {
            http_response_code(403);
            exit('Kein Zugriff.');
        }
    }
}
