<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';

/**
 * Einwilligung per Link, mit Double-Opt-in (26.09.2026, Uwe: „Einwilligung,
 * dann E-Mail“).
 *
 * WARUM DOUBLE-OPT-IN
 * Eine E-Mail an eine Firma ohne Einwilligung ist in Deutschland (§ 7 UWG)
 * und Italien (Art. 130 Codice Privacy) unzulaessig -- das Gate sagt dort
 * DO_NOT_EMAIL. Eine Einwilligung muss im Streitfall belegt werden: wer, wann,
 * wozu, fuer welche Adresse. Ein angeklicktes Kaestchen allein belegt nicht,
 * dass die Adresse dem Klickenden gehoert. Erst der Klick in der
 * Bestaetigungsmail an genau diese Adresse tut das. Keine Rechtsberatung --
 * das Verfahren ist das uebliche, der Wortlaut steht je Zeile gespeichert.
 *
 * DREI WEGE HINEIN
 *   analyse -- Kasten auf der Analyse-Seite (der QR-Code im Brief fuehrt dorthin)
 *   link    -- Uwe schickt nach einem Gespraech einen Link (einwilligung.php?t=…)
 *   check   -- Haekchen im oeffentlichen Website-Check (AkquiseCheck, 27.09.2026)
 * Beide enden gleich: Bestaetigung → akq_firmen.einwilligung + E-Mail-Adresse,
 * Protokoll, Pruefspur, Meldung. Danach zeigt die Ampel gruen, und die
 * Sperre der Zweitansprache ist fuer die E-Mail aufgehoben (AkquiseGate).
 */
final class AkquiseEinwilligung
{
    public const VERSION = 'v1-2026-09';
    /** So lange gilt ein unbestaetigter Bestaetigungslink. */
    public const DOI_TAGE = 7;
    /** Hoechstens so viele Bestaetigungsmails je Firma und Tag -- niemand soll ueber uns fremde Postfaecher fluten. */
    public const JE_TAG = 3;

    public const WORTLAUT = [
        'de' => '{firma} ({inhaber}) darf mir an diese E-Mail-Adresse Nachrichten zu meiner Website und zu passenden Angeboten schicken. Ich kann das jederzeit mit einem Klick oder einer kurzen Antwort widerrufen.',
        'it' => '{firma} ({inhaber}) può inviarmi a questo indirizzo e-mail messaggi sul mio sito e su offerte adatte. Posso revocare il consenso in qualsiasi momento con un clic o una breve risposta.',
        'en' => '{firma} ({inhaber}) may send me messages about my website and suitable offers at this email address. I can withdraw this at any time with one click or a short reply.',
    ];

    public static function wortlaut(string $sprache): string
    {
        $abs = AkquiseText::absender();
        return strtr(self::WORTLAUT[$sprache] ?? self::WORTLAUT['it'], ['{firma}' => $abs['firma'], '{inhaber}' => $abs['inhaber']]);
    }

    /** Ein neuer Link fuer eine Firma (Verwaltung → „Einwilligungs-Link“). Gleicher offener Link wird wiederverwendet. */
    public static function link(int $firmaId, string $quelle = 'link'): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        if ((int) $f['gesperrt'] === 1 || in_array((string) $f['kontakt_status'], ['abgelehnt', 'gesperrt'], true)) {
            throw new RuntimeException('Die Firma hat abgelehnt oder ist gesperrt — kein Einwilligungs-Link.');
        }
        $offen = Db::one("SELECT * FROM akq_einwilligungen WHERE firma_id = ? AND quelle = ? AND status IN ('offen','angefragt') ORDER BY id DESC LIMIT 1",
            [$firmaId, $quelle]);
        if ($offen) { return $offen; }
        $id = (int) Db::insert('akq_einwilligungen', [
            'firma_id' => $firmaId, 'link_token' => bin2hex(random_bytes(20)), 'quelle' => $quelle,
            'sprache' => AkquiseText::spracheFuer($f),
        ]);
        return Db::one('SELECT * FROM akq_einwilligungen WHERE id = ?', [$id]) ?? [];
    }

    public static function adresse(array $e): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/einwilligung.php?t=' . $e['link_token'];
    }

    /** Zeile zum Formular-Schluessel -- nur solange die Firma ansprechbar ist. */
    public static function ausLink(string $token): ?array
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $token)) { return null; }
        $e = Db::one('SELECT * FROM akq_einwilligungen WHERE link_token = ?', [$token]);
        if (!$e) { return null; }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $e['firma_id']]);
        if (!$f || (int) $f['gesperrt'] === 1) { return null; }
        return ['e' => $e, 'f' => $f];
    }

    /**
     * Die Firma hat ihre Adresse eingetragen und das Kaestchen gesetzt.
     * Geht nur eine Bestaetigungsmail raus -- keine Werbung, keine Einwilligung.
     *
     * @return string ok | email | zuviel | gesperrt
     */
    public static function anfragen(string $linkToken, string $email, bool $haken, string $sprache, string $ip = ''): string
    {
        $x = self::ausLink($linkToken);
        if ($x === null) { return 'gesperrt'; }
        $email = Akquise::normEmail($email);
        if ($email === null || !$haken) { return 'email'; }
        $sprache = isset(AkquiseText::SPRACHEN[$sprache]) ? $sprache : (string) $x['e']['sprache'];
        $f = $x['f'];
        if (AkquiseGate::trifftSperrliste(['email' => $email] + $f) !== null) { return 'gesperrt'; }
        $heute = (int) Db::wert("SELECT COUNT(*) FROM akq_einwilligungen WHERE firma_id = ? AND angefragt_am >= CURDATE()", [(int) $f['id']], 0);
        if ($heute >= self::JE_TAG) { return 'zuviel'; }

        $doi = bin2hex(random_bytes(20));
        $e = $x['e'];
        if ($e['status'] !== 'offen') {
            // Schon benutzt (bestaetigt oder angefragt): neue Zeile, die alte bleibt als Beleg.
            $e = self::link((int) $f['id'], (string) $e['quelle']);
            if ($e['status'] !== 'offen') {
                $id = (int) Db::insert('akq_einwilligungen', ['firma_id' => (int) $f['id'], 'link_token' => bin2hex(random_bytes(20)),
                    'quelle' => (string) $x['e']['quelle'], 'sprache' => $sprache]);
                $e = Db::one('SELECT * FROM akq_einwilligungen WHERE id = ?', [$id]) ?? $e;
            }
        }
        Db::update('akq_einwilligungen', (int) $e['id'], [
            'email' => $email, 'doi_token' => $doi, 'sprache' => $sprache, 'status' => 'angefragt',
            'wortlaut' => self::wortlaut($sprache), 'wortlaut_version' => self::VERSION,
            'angefragt_am' => date('Y-m-d H:i:s'), 'ip_hash' => $ip !== '' ? hash('sha256', $ip . '|' . Config::get('app_geheim', 'vecom')) : null,
        ]);
        require_once __DIR__ . '/Mail.php';
        $link = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/einwilligung.php?b=' . $doi;
        $abs = AkquiseText::absender();
        [$betreff, $text] = self::mail($sprache, (string) $f['name'], $link, self::wortlaut($sprache), $abs);
        Mail::senden('akquise_einwilligung', $email, $betreff, $text, ['nurText' => true, 'sprache' => $sprache]);
        Akquise::protokoll((int) $f['id'], 'einwilligung', 'Einwilligung angefragt (Bestätigungsmail an ' . $email . ', Quelle ' . $e['quelle'] . ')');
        return 'ok';
    }

    /** @return array{0:string,1:string} */
    private static function mail(string $sp, string $firma, string $link, string $wortlaut, array $abs): array
    {
        return match ($sp) {
            'de' => ['Bitte bestätigen: Nachrichten von ' . $abs['firma'],
                "Guten Tag,\n\nfür {$firma} wurde gerade diese Adresse eingetragen, um Nachrichten von {$abs['firma']} zu bekommen. Wenn Sie das waren, bestätigen Sie bitte mit einem Klick:\n\n{$link}\n\nSie stimmen damit zu: „{$wortlaut}“\n\nWaren Sie es nicht, ignorieren Sie diese Mail einfach — ohne Klick passiert nichts, und wir schreiben Ihnen nicht.\n\n{$abs['inhaber']} · {$abs['firma']} · {$abs['ort']}"],
            'en' => ['Please confirm: messages from ' . $abs['firma'],
                "Hello,\n\nthis address was just entered for {$firma} to receive messages from {$abs['firma']}. If that was you, please confirm with one click:\n\n{$link}\n\nYou agree to: “{$wortlaut}”\n\nIf it wasn’t you, simply ignore this email — nothing happens without a click, and we won’t write to you.\n\n{$abs['inhaber']} · {$abs['firma']} · {$abs['ort']}"],
            default => ['Conferma: messaggi da ' . $abs['firma'],
                "Buongiorno,\n\nper {$firma} è stato appena inserito questo indirizzo per ricevere messaggi da {$abs['firma']}. Se è stato lei, confermi con un clic:\n\n{$link}\n\nCon la conferma accetta: «{$wortlaut}»\n\nSe non è stato lei, ignori semplicemente questa e-mail: senza clic non succede nulla e non le scriveremo.\n\n{$abs['inhaber']} · {$abs['firma']} · {$abs['ort']}"],
        };
    }

    /**
     * Der Klick in der Bestaetigungsmail.
     *
     * @return array{ok:bool,grund?:string,firma?:array,sprache?:string}
     */
    public static function bestaetigen(string $doiToken): array
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $doiToken)) { return ['ok' => false, 'grund' => 'unbekannt']; }
        $e = Db::one('SELECT * FROM akq_einwilligungen WHERE doi_token = ?', [$doiToken]);
        if (!$e) { return ['ok' => false, 'grund' => 'unbekannt']; }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $e['firma_id']]);
        if (!$f) { return ['ok' => false, 'grund' => 'unbekannt']; }
        if ($e['status'] === 'bestaetigt') { return ['ok' => true, 'firma' => $f, 'sprache' => (string) $e['sprache']]; }
        if ($e['status'] !== 'angefragt' || strtotime((string) $e['angefragt_am'] . ' +' . self::DOI_TAGE . ' days') < time()) {
            if ($e['status'] === 'angefragt') { Db::update('akq_einwilligungen', (int) $e['id'], ['status' => 'abgelaufen']); }
            return ['ok' => false, 'grund' => 'abgelaufen', 'sprache' => (string) $e['sprache']];
        }
        if ((int) $f['gesperrt'] === 1 || AkquiseGate::trifftSperrliste(['email' => $e['email']] + $f) !== null) {
            return ['ok' => false, 'grund' => 'gesperrt', 'sprache' => (string) $e['sprache']];
        }
        $jetzt = date('Y-m-d H:i:s');
        Db::update('akq_einwilligungen', (int) $e['id'], ['status' => 'bestaetigt', 'bestaetigt_am' => $jetzt]);
        $beleg = mb_substr('Double-Opt-in ' . date('d.m.Y H:i', strtotime($jetzt)) . ' für ' . $e['email']
            . ' über ' . (['analyse' => 'Analyse-Seite', 'check' => 'Website-Check'][$e['quelle']] ?? 'Einwilligungs-Link') . ', Wortlaut ' . $e['wortlaut_version']
            . ' (Nachweis #' . $e['id'] . ')', 0, 255);
        $alt = ['einwilligung' => $f['einwilligung'], 'email' => $f['email']];
        Db::update('akq_firmen', (int) $f['id'], ['einwilligung' => $beleg, 'email' => $e['email']]);
        Events::pruefspur('akquise_rechtsgrundlage', 'akq_firmen', (int) $f['id'], $alt, ['einwilligung' => $beleg, 'email' => $e['email']]);
        Akquise::protokoll((int) $f['id'], 'einwilligung', 'Einwilligung bestätigt: ' . $e['email']);
        AkquiseGate::statusSpeichern((int) $f['id']);
        try { Events::melden('akquise_einwilligung', 'Akquise: ' . $f['name'] . ' hat eingewilligt — E-Mail erlaubt', 'gut', $e['email'], 'akquise/' . $f['id']); }
        catch (Throwable $x) { }
        return ['ok' => true, 'firma' => Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $f['id']]) ?? $f, 'sprache' => (string) $e['sprache']];
    }
}
