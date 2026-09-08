<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Status.php';
require_once __DIR__ . '/Events.php';

/**
 * Die Werkstatt von aussen — damit Claude Code holen und melden kann.
 *
 * WARUM ES DAS BRAUCHT
 *
 * Der Weg zum Baumeister lief bisher ueber die Zwischenablage: Briefing
 * erzeugen, kopieren, Claude oeffnen, einfuegen, bauen -- und am Ende die
 * Vorschau-Adresse von Hand zurueck in die Verwaltung tippen. Das ist kein
 * schlechter Weg, solange ein Mensch dazwischensitzt und ohnehin liest, was
 * er kopiert.
 *
 * Claude Code sitzt nicht in einem Chatfenster. Es hat einen Ordner, eine
 * Kommandozeile und keinen Menschen, der ihm etwas einfuegt. Damit derselbe
 * Ablauf dort funktioniert, braucht es zwei Tueren: eine zum Holen (was ist
 * der Auftrag) und eine zum Melden (hier ist das Ergebnis).
 *
 * WAS HIER ABSICHTLICH NICHT PASSIERT
 *
 * Freischalten. Das ist der Moment, in dem der Kunde eine E-Mail bekommt und
 * auf seiner Seite etwas Neues sieht -- die einzige Aktion hier draussen mit
 * Wirkung nach aussen. Sie hat deshalb eine eigene Aktion und einen eigenen
 * Satz in der Antwort, statt als Nebenwirkung von "Vorschau eintragen" zu
 * passieren. Wer eine Adresse eintraegt, will eine Adresse eintragen.
 *
 * WARUM EIN EIGENER SCHLUESSEL
 *
 * Der Telefonschluessel liegt bei STRATO im Klartext. Ein Schluessel, der
 * schreiben darf, hat dort nichts zu suchen -- und umgekehrt soll ein
 * verlorener Werkstattschluessel nicht die Telefonassistentin mitreissen.
 * Zwei Schluessel, zwei Tueren, getrennt zu tauschen.
 */
final class Werkstatt
{
    /** Was von aussen aufgerufen werden darf. */
    public const AKTIONEN = ['liste', 'auftrag', 'weiter', 'vorschau', 'stand', 'notiz', 'freigeben'];

    private const SCHLUESSEL = 'werkstatt_schluessel';

    /** Grosszuegig: Ein Bauvorgang fragt in Schueben, nicht im Dauerlauf. */
    private const DROSSEL_PRO_MINUTE = 120;

    /* ================================================================== */
    /*  Schluessel                                                        */
    /* ================================================================== */

    public static function schluessel(): string
    {
        return (string) self::still(static fn() => Db::wert(
            'SELECT svalue FROM settings WHERE skey = ?', [self::SCHLUESSEL], ''), '');
    }

    public static function eingerichtet(): bool
    {
        return self::schluessel() !== '';
    }

    /** Erzeugt einen neuen und macht damit den alten wertlos. */
    public static function neuerSchluessel(): string
    {
        $neu = bin2hex(random_bytes(24));
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [self::SCHLUESSEL, $neu]);
        return $neu;
    }

    /** Zu, bis ein neuer erzeugt wird. */
    public static function schluesselEntfernen(): void
    {
        Db::run('DELETE FROM settings WHERE skey = ?', [self::SCHLUESSEL]);
    }

    public static function schluesselStimmt(string $eingabe): bool
    {
        $soll = self::schluessel();
        /* Ohne hinterlegten Schluessel ist der Endpunkt zu. Sonst stuende er
           offen, solange ihn niemand einmal erzeugt hat. */
        if ($soll === '' || $eingabe === '') { return false; }
        return hash_equals($soll, $eingabe);
    }

    public static function adresse(): string
    {
        $basis = rtrim((string) self::still(static fn() => Config::get('website', 'https://vecom-design.it'),
                                            'https://vecom-design.it'), '/');
        return $basis . '/werkstatt.php';
    }

    /** Nicht gegen Angreifer gedacht, sondern gegen eine Schleife. */
    public static function darfNoch(): bool
    {
        $takt = 'werkstatt_takt_' . date('YmdHi');
        try {
            Db::run("INSERT INTO settings (skey, svalue) VALUES (?, '1')
                     ON DUPLICATE KEY UPDATE svalue = CAST(svalue AS UNSIGNED) + 1", [$takt]);
            $stand = (int) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$takt], 0);
            if ($stand === 1) {
                Db::run("DELETE FROM settings WHERE skey LIKE 'werkstatt_takt_%' AND skey < ?",
                        ['werkstatt_takt_' . date('YmdHi', time() - 3600)]);
            }
            return $stand <= self::DROSSEL_PRO_MINUTE;
        } catch (Throwable $e) {
            return true;
        }
    }

    /* ================================================================== */
    /*  Das Projekt finden                                                */
    /* ================================================================== */

    /**
     * Findet das Projekt aus dem, was der Aufrufer nennt.
     *
     * Am Telefon nennt niemand eine Datenbank-ID, und auf der Kommandozeile
     * auch nicht: Dort steht die Kundennummer, die auf jedem Beleg steht.
     * Deshalb nimmt diese Stelle alles drei -- Projekt-ID, Kundennummer,
     * Bestellnummer -- und sagt klar, wenn nichts davon passt.
     */
    public static function projektFinden(array $d): array
    {
        $wert = trim((string) ($d['projekt'] ?? $d['kunde'] ?? $d['nummer'] ?? ''));
        if ($wert === '') {
            throw new RuntimeException('Sag, welches Projekt: Projekt-Nummer, Kundennummer oder Bestellnummer.');
        }

        // Reine Zahl: die Projekt-ID.
        if (ctype_digit($wert)) {
            $p = Db::one('SELECT * FROM projects WHERE id = ?', [(int) $wert]);
            if ($p) { return $p; }
        }

        // Kundennummer (K-2026-0001) — das jüngste Projekt dieses Kunden.
        $p = Db::one('SELECT p.* FROM projects p JOIN customers c ON c.id = p.customer_id
                       WHERE c.kundennr = ? ORDER BY p.id DESC LIMIT 1', [$wert]);
        if ($p) { return $p; }

        // Bestellnummer.
        $p = Db::one('SELECT p.* FROM projects p JOIN orders o ON o.id = p.order_id
                       WHERE o.order_no = ? ORDER BY p.id DESC LIMIT 1', [$wert]);
        if ($p) { return $p; }

        /* Ein Kunde ohne Projekt ist der haeufigste Fall dieser Meldung --
           und der einzige, bei dem der Aufrufer sofort weiss, was zu tun
           ist. Deshalb steht er hier und nicht in einem allgemeinen Satz. */
        $kunde = Db::one('SELECT id, name FROM customers WHERE kundennr = ?', [$wert]);
        if ($kunde) {
            throw new RuntimeException('Zu ' . $wert . ' (' . $kunde['name'] . ') gibt es noch kein Projekt. '
                . 'Ein Projekt entsteht aus einer Bestellung, sobald sie bezahlt ist.');
        }
        throw new RuntimeException('Nichts gefunden zu „' . $wert . '“.');
    }

    /* ================================================================== */
    /*  Holen                                                             */
    /* ================================================================== */

    /** Woran gerade gebaut wird — kurz, zum Auswählen. */
    public static function liste(array $d = []): array
    {
        $alle = !empty($d['alle']);
        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT p.id, p.name, p.status, p.progress, p.deadline,
                    p.preview_url, p.repo_url, p.vorschau_frei_am, p.briefing_am,
                    c.kundennr, c.name AS kunde, c.company AS firma, c.sprache
               FROM projects p JOIN customers c ON c.id = p.customer_id
              WHERE p.demo = 0" . ($alle ? '' : " AND p.status NOT IN ('abgeschlossen')") . "
              ORDER BY p.id DESC LIMIT 100"), []);

        $liste = [];
        foreach ($zeilen as $z) {
            $liste[] = [
                'projekt'    => (int) $z['id'],
                'kundennr'   => (string) ($z['kundennr'] ?? ''),
                'kunde'      => trim((string) ($z['firma'] ?: $z['kunde'])),
                'name'       => (string) $z['name'],
                'stand'      => (string) $z['status'],
                'stand_klar' => (string) (Status::PROJEKT[$z['status']] ?? $z['status']),
                'fortschritt' => (int) $z['progress'],
                'sprache'    => (string) ($z['sprache'] ?? 'de'),
                'deadline'   => $z['deadline'] ?: null,
                'vorschau'   => $z['preview_url'] ?: null,
                'freigegeben' => $z['vorschau_frei_am'] !== null,
                'repo'       => $z['repo_url'] ?: null,
                'briefing'   => $z['briefing_am'] !== null,
            ];
        }
        return ['ok' => true, 'anzahl' => count($liste), 'projekte' => $liste];
    }

    /**
     * Der volle Auftrag: Briefing, Hausregeln, Eckdaten, passende Bausteine.
     *
     * Das Briefing wird erzeugt, wenn es fehlt -- und dabei am Projekt
     * festgehalten, genau wie beim Knopf in der Verwaltung. Sonst haette
     * derselbe Auftrag zwei Fassungen: eine im Chat und eine hier.
     */
    public static function auftrag(array $d): array
    {
        $p = self::projektFinden($d);
        $pid = (int) $p['id'];
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $p['customer_id']]);
        if (!$k) { throw new RuntimeException('Zu diesem Projekt gibt es keinen Kunden.'); }

        $briefing = trim((string) ($p['briefing'] ?? ''));
        $neu = false;
        if ($briefing === '' || !empty($d['neu'])) {
            require_once __DIR__ . '/Briefing.php';
            $briefing = (string) self::still(static fn() => Briefing::speichern($pid), '');
            $neu = $briefing !== '';
            $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]) ?: $p;
        }

        require_once __DIR__ . '/Standard.php';
        $hausregeln = (string) self::still(static fn() => Standard::text(), '');

        $b = $p['order_id'] !== null
            ? self::still(static fn() => Db::one('SELECT * FROM orders WHERE id = ?', [(int) $p['order_id']]), null)
            : null;

        return [
            'ok'        => true,
            'projekt'   => $pid,
            'kundennr'  => (string) ($k['kundennr'] ?? ''),
            'titel'     => (string) $p['name'],
            'kunde'     => [
                'name'    => (string) $k['name'],
                'firma'   => (string) ($k['company'] ?? ''),
                'email'   => (string) ($k['email'] ?? ''),
                'ort'     => trim((string) ($k['city'] ?? '') . ' ' . (string) ($k['country'] ?? '')),
                'branche' => (string) ($k['industry'] ?? ''),
                'sprache' => (string) ($k['sprache'] ?? 'de'),
            ],
            'paket'     => $b ? (string) $b['package_name'] : null,
            'stand'     => (string) $p['status'],
            'stand_klar' => (string) (Status::PROJEKT[$p['status']] ?? $p['status']),
            'deadline'  => $p['deadline'] ?: null,
            'vorschau'  => $p['preview_url'] ?: null,
            'freigegeben' => ($p['vorschau_frei_am'] ?? null) !== null,
            'repo'      => $p['repo_url'] ?: null,
            'briefing'  => $briefing,
            'briefing_neu' => $neu,
            'hausregeln' => $hausregeln,
            'staende'   => array_keys(Status::PROJEKT),
        ];
    }

    /** Nur, was seit dem Briefing dazugekommen ist. */
    public static function weiter(array $d): array
    {
        $p = self::projektFinden($d);
        require_once __DIR__ . '/Briefing.php';
        return [
            'ok'      => true,
            'projekt' => (int) $p['id'],
            'text'    => (string) self::still(static fn() => Briefing::weiter((int) $p['id']), ''),
        ];
    }

    /* ================================================================== */
    /*  Melden                                                            */
    /* ================================================================== */

    /**
     * Vorschau-Adresse eintragen — und, wenn dabei, wo der Quelltext liegt.
     *
     * Freigeschaltet wird hier nichts. Der Kunde sieht danach denselben
     * grauen Kasten wie vorher; erst "freigeben" oeffnet ihn.
     */
    public static function vorschau(array $d): array
    {
        $p = self::projektFinden($d);
        $pid = (int) $p['id'];

        $url  = self::adressePruefen((string) ($d['vorschau'] ?? $d['url'] ?? ''), 'Vorschau-Adresse');
        $repo = self::adressePruefen((string) ($d['repo'] ?? ''), 'Repo-Adresse');

        $aendern = [];
        if ($url !== null)  { $aendern['preview_url'] = $url !== '' ? $url : null; }
        if ($repo !== null) { $aendern['repo_url']    = $repo !== '' ? $repo : null; }
        if (!$aendern) {
            throw new RuntimeException('Nichts zu ändern: weder „vorschau“ noch „repo“ dabei.');
        }
        Db::update('projects', $pid, $aendern);

        $teile = [];
        if ($url !== null)  { $teile[] = $url !== '' ? 'Vorschau: ' . $url : 'Vorschau entfernt'; }
        if ($repo !== null) { $teile[] = $repo !== '' ? 'Quelltext: ' . $repo : 'Quelltext entfernt'; }
        self::still(static fn() => Events::protokoll('werkstatt_vorschau',
            'Aus der Werkstatt gemeldet — ' . implode(' · ', $teile),
            (int) $p['customer_id'], null, $pid));
        self::still(static fn() => Events::pruefspur('aendern', 'project', $pid, [], $aendern));

        $frei = ($p['vorschau_frei_am'] ?? null) !== null;
        return [
            'ok'      => true,
            'projekt' => $pid,
            'vorschau' => $aendern['preview_url'] ?? ($p['preview_url'] ?: null),
            'repo'    => $aendern['repo_url'] ?? ($p['repo_url'] ?: null),
            'freigegeben' => $frei,
            'hinweis' => $frei
                ? 'Eingetragen. Der Kunde ist schon freigeschaltet und sieht ab sofort diese Adresse.'
                : 'Eingetragen. Der Kunde sieht sie noch nicht — dazu „freigeben“.',
        ];
    }

    /** Den Stand setzen. Ohne E-Mail — die haengt an der Freigabe. */
    public static function stand(array $d): array
    {
        $p = self::projektFinden($d);
        $neu = trim((string) ($d['stand'] ?? $d['status'] ?? ''));
        if (!isset(Status::PROJEKT[$neu])) {
            return ['ok' => false, 'hinweis' => 'Diesen Stand gibt es nicht.',
                    'moeglich' => array_keys(Status::PROJEKT)];
        }
        Events::projektStatus((int) $p['id'], $neu, false);
        return ['ok' => true, 'projekt' => (int) $p['id'], 'stand' => $neu,
                'stand_klar' => (string) Status::PROJEKT[$neu],
                'hinweis' => 'Stand gesetzt. Der Kunde bekommt deswegen keine E-Mail.'];
    }

    /** Eine Zeile in die Akte — für alles, was später jemand wissen will. */
    public static function notiz(array $d): array
    {
        $p = self::projektFinden($d);
        $text = trim((string) ($d['text'] ?? $d['notiz'] ?? ''));
        if ($text === '') { throw new RuntimeException('Ohne Text keine Notiz.'); }
        $text = mb_substr($text, 0, 500);
        Events::protokoll('werkstatt_notiz', 'Aus der Werkstatt: ' . $text,
                          (int) $p['customer_id'], null, (int) $p['id']);
        return ['ok' => true, 'projekt' => (int) $p['id'], 'notiert' => $text];
    }

    /**
     * Freischalten — der eine Schritt hier, der beim Kunden ankommt.
     *
     * Deshalb verlangt er ein ausdrueckliches "ja". Ein Tippfehler im
     * Rumpf soll keine E-Mail ausloesen, die sich nicht zurueckholen laesst.
     */
    public static function freigeben(array $d): array
    {
        $p = self::projektFinden($d);
        $pid = (int) $p['id'];

        if (!self::jaGesagt((string) ($d['bestaetigt'] ?? ''))) {
            return ['ok' => false, 'bestaetigung_noetig' => true,
                    'projekt' => $pid,
                    'hinweis' => 'Freischalten schickt dem Kunden eine E-Mail und öffnet ihm den Entwurf. '
                               . 'Wenn das so gewollt ist, noch einmal mit bestaetigt = ja.'];
        }
        $url = trim((string) self::still(static fn() => Db::wert(
            'SELECT preview_url FROM projects WHERE id = ?', [$pid], ''), ''));
        if ($url === '') {
            throw new RuntimeException('Ohne Vorschau-Adresse gibt es nichts freizuschalten — '
                . 'sonst bekommt der Kunde eine E-Mail und findet nichts.');
        }

        Db::update('projects', $pid, ['vorschau_frei_am' => date('Y-m-d H:i:s')]);
        self::still(static fn() => Events::projektStatus($pid, 'vorschau', false));
        self::still(static fn() => Events::protokoll('vorschau_frei',
            'Vorschau aus der Werkstatt freigeschaltet', (int) $p['customer_id'], null, $pid));

        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Nachricht.php';
        $schonMal = (bool) self::still(static fn() => Mail::schonGeschickt('vorschau', 'project_id', $pid), false);
        $raus = (bool) self::still(static fn() => Nachricht::vorschauBereit($pid), false);

        return ['ok' => true, 'projekt' => $pid, 'vorschau' => $url,
                'mail' => $raus, 'hinweis' => match (true) {
                    $raus     => 'Freigeschaltet. Der Kunde hat die E-Mail bekommen.',
                    $schonMal => 'Freigeschaltet. Eine zweite E-Mail bekommt er nicht — '
                               . 'die erste war schon draußen. Auf seiner Seite sieht er den Entwurf sofort.',
                    default   => 'Freigeschaltet. Die E-Mail ging nicht raus — '
                               . 'auf seiner Seite sieht er die Vorschau trotzdem.',
                }];
    }

    /* ================================================================== */
    /*  Kleinkram                                                         */
    /* ================================================================== */

    /**
     * Adressen pruefen: null heisst "war nicht dabei", '' heisst "raus damit".
     */
    private static function adressePruefen(string $roh, string $was): ?string
    {
        $roh = trim($roh);
        if ($roh === '') { return null; }
        if (strcasecmp($roh, 'weg') === 0 || strcasecmp($roh, '-') === 0) { return ''; }
        if (!preg_match('~^https?://~i', $roh)) { $roh = 'https://' . $roh; }
        if (!filter_var($roh, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Das sieht nicht nach einer Adresse aus: ' . $was . '.');
        }
        return mb_substr($roh, 0, 255);
    }

    private static function jaGesagt(string $wort): bool
    {
        $w = mb_strtolower(trim($wort));
        return in_array($w, ['ja', 'jawohl', 'ok', 'okay', 'yes', 'si', 'sì', 'true', '1', 'mach', 'passt'], true);
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
