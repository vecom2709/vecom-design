<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Status.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Bausperre.php';

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
 * auf seiner Seite etwas Neues sieht. Seit 06.10.2026 meldet "freigeben" nur
 * noch, dass es so weit ist; freigeschaltet wird in der Verwaltung, per Klick.
 * Von hier draussen erreicht nichts mehr den Kunden.
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
    public const AKTIONEN = ['liste', 'auftrag', 'weiter', 'vorschau', 'stand', 'notiz', 'freigeben',
                             'dateien', 'datei', 'paket'];

    private const SCHLUESSEL = 'werkstatt_schluessel';

    /** Grosszuegig: Ein Bauvorgang fragt in Schueben, nicht im Dauerlauf. */
    private const DROSSEL_PRO_MINUTE = 120;

    /* ================================================================== */
    /*  Schluessel                                                        */
    /* ================================================================== */

    public static function schluessel(): string
    {
        require_once __DIR__ . '/Tresor.php';
        return (string) self::still(static fn() => Tresor::lesen(self::SCHLUESSEL), '');   // versiegelt (Etappe 0b)
    }

    public static function eingerichtet(): bool
    {
        return self::schluessel() !== '';
    }

    /** Erzeugt einen neuen und macht damit den alten wertlos. */
    public static function neuerSchluessel(): string
    {
        require_once __DIR__ . '/Tresor.php';
        $neu = bin2hex(random_bytes(24));
        Tresor::schreiben(self::SCHLUESSEL, $neu);
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
                'bau_erlaubt' => (bool) self::still(static fn() => Bausperre::darfBauen((int) $z['id'])['ok'], false),
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
        Bausperre::pruefenStopp($p);
        $bau = Bausperre::darfBauen($p);
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
            /* AutoBuild Phase 4: Solange die Bausperre gilt, darf analysiert und geplant werden — gebaut, geändert
               oder veröffentlicht wird nichts. Vorschau, Paket und Freigabe lehnt der Server dann ohnehin ab. */
            'bau_erlaubt' => $bau['ok'],
            'bausperre' => $bau['ok'] ? null : $bau['grund'] . ' Nur analysieren und planen — nichts bauen, nichts an einer Website ändern, nichts veröffentlichen.',
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
        Bausperre::pruefenStopp($p);   // Not-Aus: nichts mehr ausliefern (AutoBuild Phase 4)
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
        Bausperre::pruefen($p);        // erst nach Angebotsannahme + Anzahlung, nie beim Not-Aus (AutoBuild Phase 4)
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
        Bausperre::pruefenStopp($p);
        $neu = trim((string) ($d['stand'] ?? $d['status'] ?? ''));
        if (!isset(Status::PROJEKT[$neu])) {
            return ['ok' => false, 'hinweis' => 'Diesen Stand gibt es nicht.',
                    'moeglich' => array_keys(Status::PROJEKT)];
        }
        /* Abnahme, Veröffentlichung, Online und Abschluss setzt nur Uwe in der Verwaltung oder der
           Kunde mit seiner Abnahme — nie die Werkstatt (Prüfung 07.10.2026, Punkt 32). Sonst galt
           ein Stand ohne Annahme und Zahlung als abgenommen, und Vertrag, Rate und KAS liefen nie. */
        if (in_array($neu, Events::STATUS_NACH_ABNAHME, true)) {
            return ['ok' => false, 'hinweis' => '„' . Status::PROJEKT[$neu] . '“ setzt nur Uwe in der Verwaltung (oder der Kunde mit seiner Abnahme).',
                    'moeglich' => array_values(array_diff(array_keys(Status::PROJEKT), Events::STATUS_NACH_ABNAHME))];
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
    /**
     * „Fertig, bitte freischalten“ — gemeldet, nicht ausgeführt.
     *
     * BIS 06.10.2026 schaltete diese Aktion selbst frei: Mit dem Schlüssel und
     * „bestaetigt = ja“ bekam der Kunde eine E-Mail, ohne dass ein Mensch
     * geklickt hatte. Das widersprach der Regel aus CLAUDE.md („alles ab
     * Vorschau bleibt am Klick eines Menschen“), und die Systemanalyse für das
     * AI Office hat es als ersten Befund geführt. Uwe: „Claude meldet, Sie
     * klicken“.
     *
     * Jetzt: eine Freigabe unter „AI Freigaben“ (Stufe 1) und eine Zeile in der
     * Akte. Freigeschaltet wird per Genehmigen oder mit dem Knopf „Vorschau
     * freischalten“ — beides dieselbe Methode, beide mit der Rückfrage aus
     * Ablauf::TRAGWEITE. „bestaetigt“
     * wird angenommen und ignoriert, damit ältere Aufrufer nicht scheitern.
     */
    public static function freigeben(array $d): array
    {
        $p = self::projektFinden($d);
        Bausperre::pruefen($p);
        $pid = (int) $p['id'];

        $url = trim((string) ($p['preview_url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('Ohne Vorschau-Adresse gibt es nichts freizuschalten — '
                . 'erst „vorschau“ mit der Adresse melden.');
        }
        if (!empty($p['vorschau_frei_am'])) {
            return ['ok' => true, 'projekt' => $pid, 'vorschau' => $url, 'mail' => false, 'gemeldet' => false,
                    'hinweis' => 'Die Vorschau ist schon freigeschaltet. Der Kunde sieht sie auf seiner Seite.'];
        }

        $kunde = (string) self::still(static fn() => Db::wert(
            'SELECT name FROM customers WHERE id = ?', [(int) $p['customer_id']], ''), '');
        /* Seit Stufe 1 (06.10.2026) eine echte Freigabe unter „AI Freigaben“ — mit Knöpfen auch in
           Telegram. Genehmigt wird dieselbe Tat wie „Vorschau freischalten“ (Nachricht::vorschauFreischalten). */
        require_once __DIR__ . '/Freigabe.php';
        $fid = (int) Freigabe::vorschlagen('vorschau_frei', ['projekt' => $pid], [
            'titel' => 'Vorschau freischalten' . ($kunde !== '' ? ': ' . $kunde : ''),
            'system' => 'Werkstatt', 'von' => 'Claude Code',
            'grund' => 'Claude Code meldet die Vorschau als fertig gebaut.',
            'ist' => 'Die Vorschau liegt unter ' . $url . ' — der Kunde sieht sie noch nicht.',
            'soll' => 'Der Kunde sieht den Entwurf auf seiner Seite und bekommt eine E-Mail „Vorschau bereit“.',
            'kosten' => 'keine',
            'auswirkung' => 'Der Kunde wird gebeten, die Vorschau anzusehen und Änderungen zu melden.',
            'rollback' => '„Vorschau sperren“ im Projekt nimmt sie wieder weg; die E-Mail lässt sich nicht zurückholen.',
            'empfehlung' => 'Vorher selbst öffnen: Stimmen Texte, Bilder, Kontaktdaten? Dann freischalten.',
        ]);
        self::still(static fn() => Events::protokoll('werkstatt_bereit',
            'Werkstatt meldet: Vorschau bereit zum Freischalten', (int) $p['customer_id'], null, $pid));

        return ['ok' => true, 'projekt' => $pid, 'vorschau' => $url, 'mail' => false, 'gemeldet' => true, 'freigabe' => $fid,
                'hinweis' => 'Gemeldet. Uwe sieht es unter „AI Freigaben“ und schaltet frei — '
                           . 'erst dann bekommt der Kunde eine E-Mail.'];
    }

    /* ================================================================== */
    /*  Material und das fertige Paket                                    */
    /* ================================================================== */

    /** Was der Kunde hochgeladen hat — Logo, Schriften, Bilder, Texte. */
    public const ROLLE_MATERIAL = 'material';

    /** Die fertige Website als ZIP, in der Gegenrichtung. */
    public const ROLLE_PAKET = 'paket';

    /** Ein Paket ist gross. 200 MB sind eine Website mit Bildern und Video. */
    public const PAKET_MAX_BYTES = 200 * 1024 * 1024;

    /**
     * Die Dateien eines Projekts, mit dem Weg, sie zu holen.
     *
     * WARUM DAS NICHT NUR EINE LISTE VON NAMEN IST
     *
     * Bis zum 13.09.2026 stand im Briefing "Logo.ai, Schriften.zip" — und
     * damit war der Baumeister genauso schlau wie vorher: Er wusste, dass es
     * ein Logo gibt, und kam nicht daran. Die Dateien liegen hinter PHP
     * (app/uploads ist gesperrt), also gibt es genau einen Weg, und der
     * steht jetzt bei jeder Datei dabei.
     */
    public static function dateien(array $d): array
    {
        $p = self::projektFinden($d);
        $pid = (int) $p['id'];

        $rollen = ((string) ($d['rolle'] ?? '')) === self::ROLLE_PAKET
            ? [self::ROLLE_PAKET] : [self::ROLLE_MATERIAL];

        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT id, orig_name, mime, size_bytes, uploaded_by, rolle, created_at
               FROM files WHERE project_id = ? AND rolle = ? ORDER BY id DESC",
            [$pid, $rollen[0]]), []);

        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $liste = [];
        foreach ($zeilen as $z) {
            $liste[] = [
                'id'    => (int) $z['id'],
                'name'  => (string) $z['orig_name'],
                'art'   => (string) ($z['mime'] ?? ''),
                'bytes' => (int) $z['size_bytes'],
                'von'   => (string) $z['uploaded_by'],
                'wann'  => (string) $z['created_at'],
                'holen' => $basis . '/werkstatt.php?aktion=datei&id=' . (int) $z['id'],
            ];
        }

        return ['ok' => true, 'projekt' => $pid, 'anzahl' => count($liste), 'dateien' => $liste];
    }

    /**
     * Eine einzelne Datei ausliefern — die Bytes, nicht JSON.
     *
     * Der Aufrufer hat den Schluessel; werkstatt.php hat ihn bereits
     * geprueft, bevor diese Methode ueberhaupt drankommt. Geprueft wird hier,
     * dass die Nummer zu Material oder Paket eines Projekts gehoert.
     */
    public static function datei(array $d): never
    {
        $id = (int) ($d['id'] ?? 0);
        if ($id <= 0) { throw new RuntimeException('Sag, welche Datei: id.'); }

        /* NUR DATEIEN EINES PROJEKTS (06.10.2026). Vorher lieferte jede Nummer
           jede Datei aus — Rechnungen, Ausweise, Akquise-Nachweise. Ein
           verlorener Werkstattschlüssel hätte die ganze Ablage geöffnet.
           Jetzt nur, was `dateien` auch anbietet: Material und Paket eines
           Projekts. Alles andere sieht aus wie „gibt es nicht“. */
        $f = Db::one('SELECT * FROM files WHERE id = ? AND project_id IS NOT NULL AND rolle IN (?, ?)',
            [$id, self::ROLLE_MATERIAL, self::ROLLE_PAKET]);
        if (!$f) { throw new RuntimeException('Diese Datei gibt es nicht (mehr).'); }

        require_once __DIR__ . '/Ablage.php';
        Ablage::ausliefern($f);   // beendet die Anfrage
    }

    /**
     * Die fertige Website als Paket hinterlegen.
     *
     * WARUM DAS PAKET UEBERHAUPT IN DIE VERWALTUNG WANDERT
     *
     * Gebaut wird auf Uwes Rechner, veroeffentlicht wird auf Netlify — beides
     * gut, beides ausserhalb der Verwaltung. Damit hatte die Verwaltung von
     * der fertigen Seite nur die Adresse. Wer dem Kunden seine Seite geben
     * will ("das gehoert dir"), hatte nichts in der Hand.
     *
     * Jetzt liegt sie hier: einmal hochgeladen, danach herunterladbar, per
     * E-Mail weiterzugeben oder im Kundendashboard freizuschalten.
     *
     * Das Paket ersetzt NICHT die Vorschau. Die Vorschau ist zum Ansehen,
     * das Paket ist zum Mitnehmen.
     */
    public static function paket(array $d, array $datei = []): array
    {
        $p = self::projektFinden($d);
        Bausperre::pruefen($p);
        $pid = (int) $p['id'];

        if (!$datei || (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Es kam keine Datei an. Das Paket gehört als Datei '
                . 'in das Feld "datei" einer multipart/form-data-Anfrage.');
        }
        if ((int) $datei['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Die Datei kam nicht vollständig an (Fehler '
                . (int) $datei['error'] . ').');
        }
        if ((int) ($datei['size'] ?? 0) > self::PAKET_MAX_BYTES) {
            throw new RuntimeException('Das Paket ist größer als '
                . (int) (self::PAKET_MAX_BYTES / 1024 / 1024) . ' MB.');
        }

        /* Nur ZIP. Ein Ordner voller Einzeldateien waere eine zweite
           Ablagelogik, und ein tar.gz kann unter Windows niemand oeffnen. */
        $name = (string) ($datei['name'] ?? 'website.zip');
        if (!preg_match('~\.zip$~i', $name)) {
            throw new RuntimeException('Das Paket muss eine .zip sein — so kann es jeder öffnen.');
        }

        require_once __DIR__ . '/Ablage.php';
        $dateiId = Ablage::annehmen($datei, $pid, (int) $p['customer_id'], 'werkstatt', self::ROLLE_PAKET);
        /* AutoBuild Phase 6: jede Fassung bekommt eine Nummer (V1, V2 …). */
        require_once __DIR__ . '/Versionen.php';
        $vId = Versionen::erfassen($pid, (int) $dateiId, 'werkstatt', (string) ($d['notiz'] ?? ''));
        $vNr = (int) Db::wert('SELECT nummer FROM projekt_versionen WHERE id = ?', [$vId], 0);

        Events::protokoll('paket_neu', 'Website-Paket V' . $vNr . ' hinterlegt: ' . $name,
            (int) $p['customer_id'], $p['order_id'] !== null ? (int) $p['order_id'] : null, $pid);
        Events::melden('paket_neu', 'Die fertige Website liegt als Paket bereit', 'gut',
            (string) $p['name'] . ' — ' . $name
                . '. Herunterladen, per E-Mail schicken oder dem Kunden freigeben.',
            '/projekte/' . $pid);

        $frei = ($p['paket_frei_am'] ?? null) !== null;

        return ['ok' => true, 'projekt' => $pid, 'datei' => $dateiId, 'name' => $name, 'version' => $vNr,
                'bytes' => (int) $datei['size'], 'freigegeben' => $frei,
                'hinweis' => $frei
                    ? 'Hinterlegt. Der Kunde sieht ab sofort diese Fassung auf seiner Seite.'
                    : 'Hinterlegt. Der Kunde sieht es noch nicht — dafür in der Verwaltung '
                      . 'auf „Dem Kunden freigeben" klicken.'];
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
