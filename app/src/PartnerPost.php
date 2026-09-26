<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Partner.php';
require_once __DIR__ . '/PartnerWege.php';
require_once __DIR__ . '/WebPush.php';

/**
 * Was zwischen Vecom und einem Partner hin und her geht (26.09.2026):
 * Nachrichten, Hinweise aufs Handy, die Erinnerung an den Auszahlungsweg
 * und der Stand je Empfehlung.
 *
 * Getrennt von Partner.php, weil das die Geldseite ist (Provision, Stripe,
 * Auszahlung) und schon lang genug; hier steht nur Kommunikation. Kein
 * Kundenname verlaesst diese Klasse -- die Partnerseite zeigt Nummern.
 */
final class PartnerPost
{
    public const MAX_LAENGE = 4000;
    /** Hoechstens so viele Partner-Nachrichten je Stunde -- gegen ein haengendes Formular oder Spielerei. */
    public const JE_STUNDE = 12;
    /** Die Erinnerung an den Auszahlungsweg kommt hoechstens so oft. */
    public const ERINNERN_TAGE = 14;

    /* ==================================================================== */
    /*  Nachrichten                                                         */
    /* ==================================================================== */

    /**
     * @param string $von partner | vecom
     * @return int Nachrichten-ID
     */
    public static function schreiben(int $partnerId, string $text, string $von, ?int $userId = null): int
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') { throw new InvalidArgumentException('leer'); }
        $text = mb_substr($text, 0, self::MAX_LAENGE);
        if (!in_array($von, ['partner', 'vecom'], true)) { throw new InvalidArgumentException('von'); }
        $p = Partner::laden($partnerId);
        if (!$p) { throw new RuntimeException('Partner nicht gefunden.'); }
        if ($von === 'partner') {
            $zuletzt = (int) Db::wert("SELECT COUNT(*) FROM partner_nachrichten WHERE partner_id = ? AND von = 'partner'
                                        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)", [$partnerId], 0);
            if ($zuletzt >= self::JE_STUNDE) { throw new LengthException('zuviel'); }
        }
        $id = (int) Db::insert('partner_nachrichten', [
            'partner_id' => $partnerId, 'von' => $von, 'text' => $text,
            'user_id' => $von === 'vecom' ? $userId : null,
        ]);

        if ($von === 'partner') {
            /* Nach innen zuerst: Die Meldung ueberlebt einen stummen Mailserver
               (wie bei Kundennachrichten, Nachricht::vorab). */
            self::still(static fn() => Events::melden('partner_nachricht', 'Nachricht von Partner ' . $p['name'], 'hinweis',
                mb_substr($text, 0, 300), '/partner/' . $partnerId));
            self::still(static function () use ($p, $text, $partnerId) {
                require_once __DIR__ . '/Mail.php';
                require_once __DIR__ . '/Config.php';
                $wo = rtrim((string) Config::get('website', ''), '/') . Config::basis() . '/partner/' . $partnerId;
                Mail::senden('partner_nachricht', Mail::eigeneAdresse(), 'Partner ' . $p['name'] . ' schreibt',
                    $p['name'] . " (Partner " . $p['code'] . ") schreibt:\n\n" . $text . "\n\nAntworten in der Verwaltung: " . $wo . "\n",
                    ['antwortAn' => (string) $p['email']]);
            });
        } else {
            self::still(static fn() => Partner::schreiben($partnerId, 'partner_antwort', ['text' => $text]));
            $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
            self::push($partnerId, self::t('push_antw_t', $sp), mb_substr($text, 0, 120), Partner::portalLink($p) . '#nachrichten');
        }
        return $id;
    }

    /** @return list<array<string,mixed>> aelteste zuerst */
    public static function verlauf(int $partnerId, int $anzahl = 40): array
    {
        $zeilen = Db::all('SELECT * FROM partner_nachrichten WHERE partner_id = ? ORDER BY id DESC LIMIT ' . max(1, min(200, $anzahl)), [$partnerId]);
        return array_reverse($zeilen);
    }

    /** Markiert, was die Gegenseite geschrieben hat, als gelesen. $leser: partner | vecom */
    public static function gelesen(int $partnerId, string $leser): int
    {
        $von = $leser === 'partner' ? 'vecom' : 'partner';
        return Db::run('UPDATE partner_nachrichten SET gelesen_am = NOW() WHERE partner_id = ? AND von = ? AND gelesen_am IS NULL',
            [$partnerId, $von])->rowCount();
    }

    /** Ungelesene Partner-Nachrichten fuer den Posteingang. @return list<array<string,mixed>> */
    public static function offeneFuerVecom(int $anzahl = 50): array
    {
        return self::still(static fn() => Db::all(
            "SELECT n.*, p.name AS partner, p.code FROM partner_nachrichten n JOIN partner p ON p.id = n.partner_id
              WHERE n.von = 'partner' ORDER BY n.gelesen_am IS NULL DESC, n.id DESC LIMIT " . max(1, $anzahl)), []);
    }

    /* ==================================================================== */
    /*  Hinweise aufs Handy                                                 */
    /* ==================================================================== */

    /** Oeffentlicher VAPID-Schluessel fuer die Seite -- leer, wenn openssl ihn nicht erzeugen kann (dann keine Hinweise). */
    public static function vapid(): string
    {
        return (string) self::still(static fn() => WebPush::schluessel()['oeffentlich'], '');
    }

    public static function aboSpeichern(int $partnerId, string $endpoint, string $p256dh, string $auth): void
    {
        if (!preg_match('~^https://[a-z0-9.-]+(:\d+)?/~i', $endpoint) || strlen($endpoint) > 700) { throw new InvalidArgumentException('endpoint'); }
        if (strlen(WebPush::unb64($p256dh)) !== 65 || strlen(WebPush::unb64($auth)) !== 16) { throw new InvalidArgumentException('schluessel'); }
        $hash = hash('sha256', $endpoint);
        // Dasselbe Geraet meldet sich nach jedem Neuinstallieren wieder -- ueberschreiben statt doppeln.
        Db::run('INSERT INTO partner_push (partner_id, endpoint, endpoint_hash, p256dh, auth) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE partner_id = VALUES(partner_id), p256dh = VALUES(p256dh), auth = VALUES(auth), fehler = 0',
            [$partnerId, $endpoint, $hash, $p256dh, $auth]);
    }

    public static function aboLoeschen(int $partnerId, string $endpoint): void
    {
        Db::run('DELETE FROM partner_push WHERE partner_id = ? AND endpoint_hash = ?', [$partnerId, hash('sha256', $endpoint)]);
    }

    /** @return int Zahl der zugestellten Hinweise */
    public static function push(int $partnerId, string $titel, string $text, string $link): int
    {
        $n = 0;
        foreach (self::still(static fn() => Db::all('SELECT * FROM partner_push WHERE partner_id = ?', [$partnerId]), []) as $abo) {
            $r = self::still(static fn() => WebPush::senden($abo, ['titel' => $titel, 'text' => $text, 'link' => $link]),
                ['ok' => false, 'status' => 0, 'weg' => false]);
            if ($r['ok']) {
                $n++;
                Db::run('UPDATE partner_push SET zuletzt_am = NOW(), fehler = 0 WHERE id = ?', [(int) $abo['id']]);
            } elseif ($r['weg'] || (int) $abo['fehler'] >= 4) {
                // Abo aufgegeben (App geloescht, Hinweise abgeschaltet) oder dauerhaft kaputt.
                Db::run('DELETE FROM partner_push WHERE id = ?', [(int) $abo['id']]);
            } else {
                Db::run('UPDATE partner_push SET fehler = fehler + 1 WHERE id = ?', [(int) $abo['id']]);
            }
        }
        return $n;
    }

    /** Neue Provision -- aus Partner::beiZahlung gerufen. */
    public static function neueProvision(int $partnerId, int $cents): void
    {
        $p = Partner::laden($partnerId);
        if (!$p) { return; }
        require_once __DIR__ . '/Fmt.php';
        $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        self::push($partnerId, strtr(self::t('push_prov_t', $sp), ['{betrag}' => Fmt::geld($cents)]), self::t('push_prov_x', $sp),
            Partner::portalLink($p));
    }

    /* ==================================================================== */
    /*  Erinnerung: Geld liegt bereit, der Weg fehlt                        */
    /* ==================================================================== */

    /**
     * Aus dem Cronlauf. Nur fuer Partner mit bestaetigter Vereinbarung --
     * ohne sie wird ohnehin nichts ausgezahlt, und die Seite sagt das zuerst.
     *
     * @return int Zahl der verschickten Erinnerungen
     */
    public static function wegErinnern(): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM partner WHERE status = 'aktiv' AND vereinbarung_am IS NOT NULL
                           AND (weg_erinnert_am IS NULL OR weg_erinnert_am < DATE_SUB(NOW(), INTERVAL " . self::ERINNERN_TAGE . " DAY))") as $p) {
            $bereit = Partner::auszahlbar((int) $p['id']);
            if ($bereit <= 0) { continue; }
            $weg = PartnerWege::weg($p);
            if ($weg !== null && PartnerWege::bereit($p, $weg)) { continue; }
            require_once __DIR__ . '/Fmt.php';
            // Erst vermerken, dann schicken: Ein haengender Mailserver darf keine Serie ausloesen.
            Db::run('UPDATE partner SET weg_erinnert_am = NOW() WHERE id = ?', [(int) $p['id']]);
            if (Partner::schreiben((int) $p['id'], 'partner_weg_fehlt', ['betrag' => Fmt::geld($bereit)])) { $n++; }
            $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
            self::push((int) $p['id'], strtr(self::t('geld_bereit', $sp), ['{betrag}' => Fmt::geld($bereit)]), self::t('geld_knopf', $sp),
                Partner::portalLink($p) . '#wege');
        }
        return $n;
    }

    /* ==================================================================== */
    /*  Stand je Empfehlung                                                 */
    /* ==================================================================== */

    public const STUFEN = ['zugeordnet', 'anfrage', 'angebot', 'bezahlt', 'online'];

    /**
     * Eine Zeile je Kunde, der ueber den Partner kam. KEIN Name, keine
     * E-Mail: laufende Nummer, Ort (wenn bekannt), Datum, Stufe, Provision.
     *
     * @return list<array{nr:int,seit:string,ort:string,stufe:string,provision:int,frei_ab:?string}>
     */
    public static function empfehlungen(int $partnerId): array
    {
        $aus = [];
        $zeilen = Db::all('SELECT z.customer_id, z.created_at, c.city FROM partner_zuordnungen z
                            LEFT JOIN customers c ON c.id = z.customer_id WHERE z.partner_id = ? ORDER BY z.created_at, z.customer_id', [$partnerId]);
        foreach ($zeilen as $i => $z) {
            $k = (int) $z['customer_id'];
            $stufe = 'zugeordnet';
            $anfrage = (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM bedarf WHERE customer_id = ? AND status <> 'offen'", [$k], 0), 0)
                     + (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM anfragen WHERE customer_id = ?', [$k], 0), 0);
            if ($anfrage > 0) { $stufe = 'anfrage'; }
            if ((int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM angebote WHERE customer_id = ? AND gesendet_am IS NOT NULL', [$k], 0), 0) > 0) {
                $stufe = 'angebot';
            }
            if ((int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM payments z JOIN orders o ON o.id = z.order_id
                                                           WHERE o.customer_id = ? AND z.status = 'bezahlt' AND COALESCE(z.demo,0) = 0", [$k], 0), 0) > 0) {
                $stufe = 'bezahlt';
            }
            if ((int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM projects WHERE customer_id = ? AND status = 'online'", [$k], 0), 0) > 0) {
                $stufe = 'online';
            }
            $prov = Db::one("SELECT COALESCE(SUM(provision_cents),0) AS s, MIN(CASE WHEN status = 'wartet' THEN frei_ab END) AS frei
                              FROM partner_provisionen WHERE partner_id = ? AND customer_id = ? AND status NOT IN ('storniert','zurueckgeholt')",
                [$partnerId, $k]) ?? ['s' => 0, 'frei' => null];
            $aus[] = ['nr' => $i + 1, 'seit' => (string) $z['created_at'], 'ort' => trim((string) ($z['city'] ?? '')),
                      'stufe' => $stufe, 'provision' => (int) $prov['s'], 'frei_ab' => $prov['frei'] !== null ? (string) $prov['frei'] : null];
        }
        return array_reverse($aus);   // neueste oben
    }

    /* ==================================================================== */

    private static function t(string $k, string $sp): string
    {
        require_once __DIR__ . '/Texte.php';
        return Texte::h(Texte::PARTNER[$k] ?? [], $sp);
    }

    /** @template T @param callable():T $f @param T $sonst @return T */
    private static function still(callable $f, mixed $sonst = null): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }
}
