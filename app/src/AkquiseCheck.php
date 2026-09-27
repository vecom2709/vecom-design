<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/PartnerCheck.php';

/**
 * Der öffentliche Website-Check auf vecom-design.it (27.09.2026).
 *
 * WOZU
 * Bisher kamen Betriebe nur über die eigene Suche in die Akquise -- und eine
 * E-Mail war erst nach einem Brief, einem Anruf und einer Einwilligung
 * erlaubt. Der Check dreht die Richtung um: Der Betrieb kommt selbst, sieht
 * sofort sechs Punkte zu seiner Seite und entscheidet dabei ZWEI Dinge
 * getrennt voneinander:
 *
 *   ausfuehrlich -- „Bitte melden Sie sich mit der ausführlichen Analyse“.
 *                   Das ist eine Anfrage. Uwe darf darauf antworten, einmal,
 *                   zu genau dieser Sache. Keine Werbeeinwilligung.
 *   marketing    -- das Häkchen für Nachrichten zu passenden Angeboten, nie
 *                   vorausgewählt. Daraus wird erst mit dem Klick in der
 *                   Bestätigungsmail eine Einwilligung (AkquiseEinwilligung,
 *                   Quelle „check“) -- vorher ist sie nichts.
 *
 * Wer das eine will, hat damit nicht das andere gewählt. Genau diese
 * Trennung verlangt die Rechtslage in beiden Ländern (keine Rechtsberatung).
 *
 * WAS NICHT PASSIERT
 * Name, E-Mail und Telefon landen nicht in akq_firmen. Die Firma bekommt
 * ihre E-Mail-Adresse nur über eine bestätigte Einwilligung -- sonst stünde
 * da eine Adresse, an die das Gate irgendwann schreiben dürfte, weil jemand
 * eine Regel ändert. Nach FRIST_TAGE werden die persönlichen Felder geleert,
 * wenn weder Einwilligung noch Auftrag daraus wurde.
 *
 * MISSBRAUCH
 * Unser Server ruft eine Adresse ab, die ein Fremder eingetippt hat. Das
 * sichert PartnerCheck ab (nur öffentliche Adressen, feste IP, Größe und
 * Zeit begrenzt). Hier kommen die Mengen dazu: je Absender, je Domain, je
 * Tag. Die Bestätigungsmail hat eine eigene Grenze in AkquiseEinwilligung.
 */
final class AkquiseCheck
{
    public const JE_ADRESSE = 5;
    public const JE_DOMAIN = 3;
    public const JE_TAG = 200;
    public const FRIST_TAGE = 180;
    /** Frühestens so viele Sekunden nach dem Aufruf darf das Formular zurückkommen -- Roboter sind schneller. */
    public const MIN_SEKUNDEN = 3;

    public static function an(): bool
    {
        return AkquiseGate::einstellung('akq_check_an', '1') === '1';
    }

    public static function schalten(bool $an): void
    {
        AkquiseGate::setzen('akq_check_an', $an ? '1' : '0');
    }

    /** Unterschriebener Zeitstempel fürs Formular: kommt es zu schnell oder zu alt zurück, war es kein Mensch auf dieser Seite. */
    public static function stempel(?int $zeit = null): string
    {
        $zeit ??= time();
        return $zeit . '.' . substr(hash_hmac('sha256', 'check|' . $zeit, self::geheim()), 0, 24);
    }

    public static function stempelGut(string $stempel, ?int $jetzt = null): bool
    {
        $jetzt ??= time();
        if (!preg_match('~^(\d{9,11})\.([a-f0-9]{24})$~', $stempel, $m)) { return false; }
        if (!hash_equals(substr(hash_hmac('sha256', 'check|' . $m[1], self::geheim()), 0, 24), $m[2])) { return false; }
        $alter = $jetzt - (int) $m[1];
        return $alter >= self::MIN_SEKUNDEN && $alter <= 7200;
    }

    private static function geheim(): string
    {
        return (string) Config::get('app_geheim', 'vecom') . '|akq_check';
    }

    public static function ipHash(string $ip): ?string
    {
        return $ip !== '' ? hash('sha256', $ip . '|' . Config::get('app_geheim', 'vecom')) : null;
    }

    /**
     * Ein Check aus dem Formular.
     *
     * @param array{name?:string,firma?:string,url?:string,email?:string,telefon?:string,sprache?:string,sprache_seite?:string,land?:string,ausfuehrlich?:bool,marketing?:bool,whatsapp?:?string} $e
     * @return array{ok:bool, grund?:string, token?:string}
     *         grund: aus | angaben | adresse | email | whatsapp | zuviel
     */
    public static function anlegen(array $e, string $ip = ''): array
    {
        if (!self::an()) { return ['ok' => false, 'grund' => 'aus']; }
        $name = trim((string) ($e['name'] ?? ''));
        $firma = trim((string) ($e['firma'] ?? ''));
        $sprache = in_array($e['sprache'] ?? '', ['de', 'it', 'en'], true) ? (string) $e['sprache'] : 'it';
        $land = in_array($e['land'] ?? '', ['DE', 'IT'], true) ? (string) $e['land'] : ($sprache === 'de' ? 'DE' : 'IT');
        $email = Akquise::normEmail($e['email'] ?? null);
        $telefonRoh = trim((string) ($e['telefon'] ?? ''));
        $telefon = $telefonRoh !== '' ? Akquise::normTelefon($telefonRoh, $land) : null;
        if (mb_strlen($name) < 2 || mb_strlen($firma) < 2 || mb_strlen($name) > 120 || mb_strlen($firma) > 190) { return ['ok' => false, 'grund' => 'angaben']; }
        if ($email === null) { return ['ok' => false, 'grund' => 'email']; }
        /* WhatsApp gewünscht (27.09.2026): Nummer vor jeder Arbeit prüfen -- sonst läuft die Analyse, und die Einwilligung scheitert still. */
        $wa = null;
        if (array_key_exists('whatsapp', $e) && $e['whatsapp'] !== null) {
            $wa = Akquise::normTelefon((string) $e['whatsapp'], $land);
            if ($wa === null || strlen((string) preg_replace('~\D~', '', $wa)) < 8) { return ['ok' => false, 'grund' => 'whatsapp']; }
        }
        $url = PartnerCheck::adresse((string) ($e['url'] ?? ''));
        if ($url === null) { return ['ok' => false, 'grund' => 'adresse']; }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $hostNorm = (string) (Akquise::normDomain($url) ?? $host);

        $ipHash = self::ipHash($ip);
        if ($ipHash !== null && (int) Db::wert('SELECT COUNT(*) FROM akq_checks WHERE ip_hash = ? AND created_at >= CURDATE()', [$ipHash], 0) >= self::JE_ADRESSE) {
            return ['ok' => false, 'grund' => 'zuviel'];
        }
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_checks WHERE host = ? AND created_at >= CURDATE()', [$hostNorm], 0) >= self::JE_DOMAIN
            || (int) Db::wert('SELECT COUNT(*) FROM akq_checks WHERE created_at >= CURDATE()', [], 0) >= self::JE_TAG) {
            return ['ok' => false, 'grund' => 'zuviel'];
        }

        $erg = PartnerCheck::pruefen($url);
        if ($erg['fehler'] === 'adresse') { return ['ok' => false, 'grund' => 'adresse']; }
        $schlecht = count(array_filter($erg['punkte'], static fn($p) => $p['stand'] === 'schlecht'));

        /* Den Betrieb über die Dublettenprüfung anlegen oder ergänzen. Ohne
           Name, E-Mail und Telefon des Anfragenden -- die stehen nur im Check. */
        $firmaId = null; $gesperrt = false;
        try {
            $m = Akquise::firmaMelden(['name' => $firma, 'land' => $land, 'url' => $erg['ok'] ? (string) $erg['url'] : $url,
                                       'quelle' => mb_substr('website-check:' . $hostNorm, 0, 80)]);
            $firmaId = (int) $m['id'];
            $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]) ?? [];
            $gesperrt = (int) ($f['gesperrt'] ?? 0) === 1 || AkquiseGate::trifftSperrliste(['email' => $email] + $f) !== null;
            $neu = [];
            if (empty($f['sprache'])) { $neu['sprache'] = $sprache; }
            /* Wer die ausführliche Analyse will, kommt in der Warteschlange des
               Workers nach vorn -- auch wenn die letzte Prüfung schon älter ist. */
            if (!empty($e['ausfuehrlich']) && !$gesperrt && in_array((string) ($f['audit_status'] ?? ''), ['fertig', 'fehler'], true)
                && strtotime((string) ($f['geprueft_am'] ?? '2000-01-01')) < strtotime('-1 day')) {
                $neu['audit_status'] = 'offen';
            }
            if ($neu) { Db::update('akq_firmen', $firmaId, $neu); }
        } catch (Throwable $x) {
            /* Ein Fehler beim Anlegen darf dem Besucher nicht das Ergebnis nehmen. */
            $firmaId = null;
        }

        $token = bin2hex(random_bytes(16));
        $id = (int) Db::insert('akq_checks', [
            'token' => $token, 'firma_id' => $firmaId, 'name' => mb_substr($name, 0, 120), 'firma' => mb_substr($firma, 0, 190),
            'url' => mb_substr($url, 0, 500), 'host' => mb_substr($hostNorm, 0, 190), 'email' => $email,
            'telefon' => $telefon !== null ? mb_substr($telefon, 0, 40) : null, 'sprache' => $sprache, 'land' => $land,
            'ausfuehrlich' => !empty($e['ausfuehrlich']) ? 1 : 0, 'marketing' => !empty($e['marketing']) ? 1 : 0,
            'ergebnis' => json_encode($erg + ['geprueft' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'schlecht' => $schlecht, 'ip_hash' => $ipHash, 'created_at' => date('Y-m-d H:i:s'),
        ]);

        /* Marketing-Häkchen: nur die Bestätigungsmail. Einwilligung erst mit dem Klick darin. */
        $stand = null;
        if (!empty($e['marketing']) && $firmaId !== null) {
            if ($gesperrt) {
                $stand = 'gesperrt';
            } else {
                try {
                    require_once __DIR__ . '/AkquiseEinwilligung.php';
                    $l = AkquiseEinwilligung::link($firmaId, 'check');
                    /* Der Wortlaut in der Sprache, in der er auf der Seite stand -- belegt wird, was der Mensch gelesen hat. */
                    $seite = in_array($e['sprache_seite'] ?? '', ['de', 'it', 'en'], true) ? (string) $e['sprache_seite'] : $sprache;
                    $stand = AkquiseEinwilligung::anfragen((string) $l['link_token'], $email, true, $seite, $ip, $wa);
                    $ew = Db::wert("SELECT id FROM akq_einwilligungen WHERE firma_id = ? AND quelle = 'check' AND email = ? ORDER BY id DESC LIMIT 1", [$firmaId, $email], null);
                    Db::update('akq_checks', $id, ['einwilligung_id' => $ew !== null ? (int) $ew : null]);
                } catch (Throwable $x) { $stand = 'gesperrt'; }
            }
            Db::update('akq_checks', $id, ['einwilligung_stand' => $stand]);
        }

        if ($firmaId !== null) {
            Akquise::protokoll($firmaId, 'anfrage', 'Website-Check über vecom-design.it: ' . $hostNorm . ' · '
                . (!empty($e['ausfuehrlich']) ? 'ausführliche Analyse gewünscht' : 'nur Kurz-Check')
                . (!empty($e['marketing']) ? ' · Einwilligung angefragt' . ($wa !== null ? ' (E-Mail + WhatsApp ' . $wa . ')' : '') . ' (' . ($stand ?? '—') . ')' : ''), ['check' => $id]);
        }
        try {
            Events::melden('akquise_check', 'Website-Check: ' . mb_substr($firma, 0, 80) . ' (' . $hostNorm . ')',
                !empty($e['ausfuehrlich']) && !$gesperrt ? 'gut' : 'info',
                $schlecht . ' von ' . count($erg['punkte']) . ' Punkten schlecht'
                    . (!empty($e['ausfuehrlich']) ? ' · möchte die ausführliche Analyse' : '')
                    . (!empty($e['marketing']) ? ' · Einwilligung angefragt' : '') . ($gesperrt ? ' · steht auf „Nie kontaktieren“' : ''),
                $firmaId !== null ? 'akquise/' . $firmaId : 'akquise');
        } catch (Throwable $x) { }
        return ['ok' => true, 'token' => $token];
    }

    /** Ergebnis zur Adresse -- ohne die persönlichen Felder. */
    public static function laden(string $token, bool $zaehlen = true): ?array
    {
        if (!preg_match('~^[a-f0-9]{32}$~', $token)) { return null; }
        $z = Db::one('SELECT id, token, firma, host, url, sprache, ausfuehrlich, marketing, einwilligung_stand, ergebnis, schlecht, created_at FROM akq_checks WHERE token = ?', [$token]);
        if (!$z) { return null; }
        if ($zaehlen) { Db::run('UPDATE akq_checks SET aufrufe = aufrufe + 1 WHERE id = ?', [(int) $z['id']]); }
        $z['ergebnis'] = json_decode((string) $z['ergebnis'], true) ?: ['punkte' => []];
        return $z;
    }

    public static function link(string $token): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/website-check.php?t=' . $token;
    }

    /** Für die Verwaltung: die offenen zuerst. */
    public static function liste(int $n = 15, bool $nurOffen = true): array
    {
        return Db::all('SELECT c.id, c.firma_id, c.name, c.firma, c.host, c.email, c.telefon, c.sprache, c.ausfuehrlich, c.marketing,
                               c.einwilligung_stand, c.schlecht, c.status, c.token, c.created_at,
                               (SELECT e.status FROM akq_einwilligungen e WHERE e.id = c.einwilligung_id) AS einwilligung
                          FROM akq_checks c ' . ($nurOffen ? "WHERE c.status = 'neu' " : '') . '
                         ORDER BY c.ausfuehrlich DESC, c.id DESC LIMIT ' . max(1, min(100, $n)));
    }

    public static function offen(): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM akq_checks WHERE status = 'neu'", [], 0);
    }

    public static function erledigen(int $id): void
    {
        $z = Db::one('SELECT * FROM akq_checks WHERE id = ?', [$id]);
        if (!$z) { throw new RuntimeException('Check nicht gefunden.'); }
        if ($z['status'] === 'neu') {
            Db::update('akq_checks', $id, ['status' => 'erledigt', 'erledigt_am' => date('Y-m-d H:i:s')]);
            if ($z['firma_id']) { Akquise::protokoll((int) $z['firma_id'], 'anfrage', 'Website-Check als erledigt markiert'); }
        }
    }

    /**
     * Frist: persönliche Felder leeren, wenn weder Einwilligung noch Auftrag
     * daraus wurde. Einmal am Tag reicht.
     */
    public static function aufraeumen(bool $erzwingen = false): int
    {
        $heute = date('Y-m-d');
        if (!$erzwingen && AkquiseGate::einstellung('akq_checks_aufgeraeumt', '') === $heute) { return 0; }
        AkquiseGate::setzen('akq_checks_aufgeraeumt', $heute);
        $grenze = date('Y-m-d H:i:s', strtotime('-' . self::FRIST_TAGE . ' days'));
        $n = Db::run("UPDATE akq_checks c
                         LEFT JOIN akq_firmen f ON f.id = c.firma_id
                         LEFT JOIN akq_einwilligungen e ON e.id = c.einwilligung_id
                         SET c.name = NULL, c.email = NULL, c.telefon = NULL, c.ip_hash = NULL, c.status = 'anonymisiert'
                       WHERE c.created_at < ? AND c.status <> 'anonymisiert'
                         AND COALESCE(e.status, '') <> 'bestaetigt'
                         AND COALESCE(f.kontakt_status, '') <> 'kunde' AND COALESCE(f.bestandskunde, 0) = 0", [$grenze])->rowCount();
        if ($n > 0) { Akquise::protokoll(null, 'frist', $n . ' Website-Checks nach ' . self::FRIST_TAGE . ' Tagen anonymisiert'); }
        return $n;
    }
}
