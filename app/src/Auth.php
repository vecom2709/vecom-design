<?php
declare(strict_types=1);

final class Auth
{
    public const ADMIN = 'admin';
    public const KUNDE = 'kunde';
    /** Rollen mit Zugang zur Verwaltung (siehe Rechte.php). */
    public const VERWALTUNG = ['admin', 'mitarbeit', 'lesen'];

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
        self::ganz($u);
        return true;
    }

    /* ---------- Passwort vergessen (09.10.2026, Uwe: „kannst du mir es zurückschalten“) ----------
       Niemand setzt hier ein Passwort für jemand anderen. Die Adresse bekommt einen Link,
       30 Minuten gültig, einmal benutzbar, und die Person wählt ihr Passwort selbst.
       Die Antwort ist immer dieselbe — ob die Adresse bekannt ist, verrät die Seite nicht. */
    public const LINK_MINUTEN = 30;
    public const LINK_JE_STUNDE = 3;

    public static function linkAnfordern(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $u = Db::one('SELECT id, name, email, role FROM users WHERE email = ? AND active = 1', [$email]);
        require_once __DIR__ . '/Rechte.php';
        /* Die Seite sagt immer dasselbe -- also muss der Grund woanders hin, sonst sucht man
           blind (09.10.2026, Uwe: „es kam keine E-Mail an“). Jeder Ausgang wird gemeldet;
           „warnung“ klingelt auf dem Handy, dort steht nur der allgemeine Titel, nie die Adresse. */
        if ($u === null || !isset(Rechte::ROLLEN[(string) $u['role']])) {
            Events::melden('passwort_link', 'Passwort-Link: Adresse gehört zu keinem Verwaltungszugang', 'warnung',
                'Angefragt für: ' . mb_substr($email, 0, 190) . ($u !== null ? ' (Rolle ' . $u['role'] . ')' : ''));
            usleep(300000);
            return;
        }
        $zuletzt = (int) Db::wert('SELECT COUNT(*) FROM passwort_links WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [(int) $u['id']]);
        if ($zuletzt >= self::LINK_JE_STUNDE) {
            Events::melden('passwort_link', 'Passwort-Link: Grenze erreicht, eine Stunde warten', 'warnung', 'Für ' . $u['email']);
            return;
        }
        $token = bin2hex(random_bytes(24));
        Db::insert('passwort_links', [
            'user_id' => (int) $u['id'], 'token_hash' => hash('sha256', $token),
            'gueltig_bis' => date('Y-m-d H:i:s', time() + self::LINK_MINUTEN * 60),
        ]);
        $link = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . Config::basis() . '/passwort-neu?t=' . $token;
        require_once __DIR__ . '/Mail.php';
        $betreff = 'Neues Passwort für die Vecom-Verwaltung';
        $text = 'Hallo ' . trim((string) $u['name']) . ",\n\nmit diesem Link setzt du dir ein neues Passwort für die Verwaltung:\n\n"
            . $link . "\n\nEr gilt " . self::LINK_MINUTEN . ' Minuten und nur einmal. Wenn du das nicht angefordert hast, ignoriere diese Mail — dein Passwort bleibt, wie es ist.';
        /* Eigene Adressen nicht über Brevo (09.10.2026, gemessen im Brevo-Log): Der Mailserver
           von All-Inkl wies die Mail an kontakt@ ab — „451 4.7.1 … rate-limited due to a poor
           reputation“ der Brevo-IP. Brevo meldete trotzdem „versendet“, nur ein Soft Bounce
           später zeigte es. Liegt das Postfach auf unserem eigenen Webspace, geht die Mail
           deshalb über dessen Mailserver; Brevo nur, wenn der sie nicht annimmt. */
        $an = (string) $u['email'];
        $eigeneDomain = substr((string) strrchr(Mail::eigeneAdresse(), '@'), 1);
        $ok = false;
        if ($eigeneDomain !== '' && str_ends_with(mb_strtolower($an), '@' . mb_strtolower($eigeneDomain)) && function_exists('mail')) {
            $ok = @mail($an, '=?UTF-8?B?' . base64_encode($betreff) . '?=', $text,
                'From: ' . Mail::eigeneAdresse() . "\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit");
            if ($ok) { Events::protokoll('passwort_link', 'Passwort-Link über den eigenen Mailserver an ' . $an); }
        }
        if (!$ok) {
            $ok = Mail::senden('passwort_link', $an, $betreff, $text,
                ['nurText' => true, 'empfaengerArt' => 'admin', 'sprache' => 'de']);
        }
        if ($ok) {
            Events::melden('passwort_link', 'Passwort-Link verschickt — auch im Spam-Ordner nachsehen', 'warnung', 'An ' . $u['email']);
        } else {
            $grund = Mail::$letzteId ? (string) Db::wert('SELECT fehler FROM mails WHERE id = ?', [Mail::$letzteId], '') : '';
            Events::melden('passwort_link', 'Passwort-Link: Mail ging nicht raus', 'schlecht', 'An ' . $u['email'] . ': ' . $grund);
        }
        Events::protokoll('passwort_link', 'Link für neues Passwort angefordert: ' . $u['email'] . ($ok ? '' : ' (Mail gescheitert)'));
    }

    /** Der Benutzer zu einem noch gültigen, unbenutzten Link — sonst null. */
    public static function linkPruefen(string $token): ?array
    {
        if (!preg_match('~^[0-9a-f]{48}$~', $token)) { return null; }
        return Db::one('SELECT l.id AS link_id, u.id, u.email, u.name FROM passwort_links l JOIN users u ON u.id = l.user_id
            WHERE l.token_hash = ? AND l.benutzt_am IS NULL AND l.gueltig_bis > NOW() AND u.active = 1', [hash('sha256', $token)]);
    }

    /** Setzt das neue Passwort, entwertet alle offenen Links des Benutzers und löst die Anmeldebremse. */
    public static function passwortSetzen(string $token, string $neu): bool
    {
        $u = self::linkPruefen($token);
        if ($u === null || mb_strlen($neu) < 10) { return false; }
        Db::update('users', (int) $u['id'], ['password_hash' => password_hash($neu, PASSWORD_DEFAULT)]);
        Db::run('UPDATE passwort_links SET benutzt_am = NOW() WHERE user_id = ? AND benutzt_am IS NULL', [(int) $u['id']]);
        Db::run('DELETE FROM settings WHERE skey LIKE ?', ['anm\\_fehl\\_%']);
        Events::protokoll('passwort_neu', 'Neues Passwort gesetzt: ' . $u['email']);
        return true;
    }

    /** Abmeldung nach so vielen Sekunden ohne Klick (05.10.2026, Uwe: „Ja“). */
    public const LEERLAUF = 3600;
    /** Spätestens nach so vielen Sekunden neu anmelden, auch bei Betrieb. */
    public const HOECHSTENS = 43200;

    /** Der Lebenszeichen-Abruf (Route „puls“) zählt nicht als Klick — sonst liefe eine offene Seite nie ab. */
    public static bool $nurPuls = false;

    private static function ganz(array $u): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        $_SESSION['uid']  = (int) $u['id'];
        $_SESSION['rolle']= $u['role'];
        $_SESSION['name'] = $u['name'];
        $_SESSION['kunde']= $u['customer_id'] !== null ? (int) $u['customer_id'] : null;
        $_SESSION['seit'] = time();
        $_SESSION['zuletzt'] = time();
        Db::update('users', (int) $u['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
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

    /** Zu lange nichts getan oder zu lange insgesamt angemeldet? Sitzungen von vor dem 05.10.2026 haben keine Zeiten → gelten als abgelaufen. */
    public static function abgelaufen(?int $jetzt = null): bool
    {
        $jetzt ??= time();
        $seit = (int) ($_SESSION['seit'] ?? 0); $zuletzt = (int) ($_SESSION['zuletzt'] ?? 0);
        return $seit === 0 || $zuletzt === 0 || $jetzt - $zuletzt > self::LEERLAUF || $jetzt - $seit > self::HOECHSTENS;
    }
    public static function rolle(): ?string   { return $_SESSION['rolle'] ?? null; }
    public static function name(): string     { return (string) ($_SESSION['name'] ?? ''); }
    public static function id(): ?int         { return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : null; }
    public static function istAdmin(): bool   { return self::rolle() === self::ADMIN; }

    /** Riegel vor jeder Admin-Seite. */
    public static function nurAdmin(): void
    {
        if (self::angemeldet() && self::abgelaufen()) {
            self::abmelden();
            header('Location: ' . Config::basis() . '/anmelden?zeit=1');
            exit;
        }
        if (!self::angemeldet()) {
            header('Location: ' . Config::basis() . '/anmelden');
            exit;
        }
        if (!self::$nurPuls) { $_SESSION['zuletzt'] = time(); }
        // Rolle und Zustand bei jedem Aufruf frisch aus der Datenbank (05.10.2026): Ein abgeschalteter
        // Zugang oder eine geänderte Rolle gilt sofort — nicht erst nach der nächsten Anmeldung.
        try {
            $frisch = Db::one('SELECT role, active FROM users WHERE id = ?', [(int) self::id()]);
            if (!$frisch || (int) $frisch['active'] !== 1) {
                self::abmelden();
                header('Location: ' . Config::basis() . '/anmelden');
                exit;
            }
            $_SESSION['rolle'] = (string) $frisch['role'];
        } catch (PDOException $e) { /* ohne Datenbank zeigt die Seite ihren eigenen Fehler */ }
        // Rollen (05.10.2026): admin, mitarbeit, lesen kommen hinein — was sie dort dürfen, sagt Rechte.php.
        if (!in_array(self::rolle(), self::VERWALTUNG, true)) {
            http_response_code(403);
            exit('Kein Zugriff.');
        }
    }
}
