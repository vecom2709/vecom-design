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
        if (self::gesperrt($email) > 0) {
            return false;
        }
        if (!password_verify($passwort, $hash) || $u === null) {
            self::fehlversuch($email);
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

    /* ------------------------------------------------------------------
       BREMSE FUER FEHLVERSUCHE (25.09.2026)

       Bis heute durfte man am Anmeldeformular beliebig oft raten; die
       Ist-Analyse fand keine Grenze. Jetzt: nach FEHL_GRENZE falschen
       Versuchen in einem Fenster von FEHL_FENSTER Sekunden ist Schluss --
       gezaehlt je Adresse UND je Absender (IP, nur als Hash gespeichert).
       Je Adresse, damit ein verteilter Angriff auf Uwes Konto nicht
       durchkommt; je Absender, damit einer nicht viele Adressen probiert.

       Gezaehlt wird in settings wie bei der Werkstatt-Drossel: kein neues
       Schema, und ein Zaehler, der verloren geht, sperrt niemanden aus.
       Das Fenster ist fest (nicht gleitend) -- im schlimmsten Fall sind es
       also 2 x FEHL_GRENZE Versuche in kurzer Folge, nie unbegrenzt viele.
       ------------------------------------------------------------------ */
    public const FEHL_GRENZE  = 5;
    public const FEHL_FENSTER = 900;

    /** @return list<string> die Zaehlerschluessel dieses Versuchs */
    private static function fehlSchluessel(string $email): array
    {
        $fenster = (string) intdiv(time(), self::FEHL_FENSTER);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return [
            'anm_fehl_m_' . substr(hash('sha256', mb_strtolower(trim($email))), 0, 20) . '_' . $fenster,
            'anm_fehl_i_' . substr(hash('sha256', 'ip:' . $ip), 0, 20) . '_' . $fenster,
        ];
    }

    /** Minuten bis zum naechsten erlaubten Versuch, oder 0. */
    public static function gesperrt(string $email): int
    {
        try {
            foreach (self::fehlSchluessel($email) as $k) {
                if ((int) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], 0) >= self::FEHL_GRENZE) {
                    $rest = self::FEHL_FENSTER - (time() % self::FEHL_FENSTER);
                    return max(1, (int) ceil($rest / 60));
                }
            }
        } catch (Throwable $e) { /* ohne Zaehler lieber anmelden lassen als aussperren */ }
        return 0;
    }

    private static function fehlversuch(string $email): void
    {
        try {
            foreach (self::fehlSchluessel($email) as $k) {
                Db::run("INSERT INTO settings (skey, svalue) VALUES (?, '1')
                         ON DUPLICATE KEY UPDATE svalue = CAST(svalue AS UNSIGNED) + 1", [$k]);
            }
            // Alte Fenster wegraeumen: Die Schluessel enden auf die Fensternummer.
            $alt = (string) (intdiv(time(), self::FEHL_FENSTER) - 1);
            Db::run("DELETE FROM settings WHERE skey LIKE 'anm\\_fehl\\_%'
                       AND CAST(SUBSTRING_INDEX(skey, '_', -1) AS UNSIGNED) < ?", [$alt]);
        } catch (Throwable $e) { /* Zaehlen darf die Anmeldung nie verhindern */ }
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
