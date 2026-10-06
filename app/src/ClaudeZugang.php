<?php
declare(strict_types=1);

/**
 * Claudes Zugang zur Verwaltung (AI Office Stufe 2, 07.10.2026).
 *
 * Uwe: „Alles, mit Kundennamen“ lesen, Verbindung „30 Tage“, Briefing per
 * Telegram. Gebaut nach der MCP-Autorisierung (Fassung 2025-11-25, die auch
 * für 2026-07-28 gilt): OAuth 2.1 mit PKCE (nur S256), Protected Resource
 * Metadata (RFC 9728), Authorization Server Metadata (RFC 8414), Dynamic
 * Client Registration (RFC 7591) und Resource Indicators (RFC 8707).
 *
 * WARUM OAUTH UND KEIN FESTER SCHLÜSSEL
 * Ein fester Schlüssel steht irgendwo im Klartext — in einer Einstellung bei
 * Claude, in einer Adresse, in einem Protokoll — und gilt, bis jemand daran
 * denkt, ihn zu tauschen. Hier sagt Uwe in seiner eigenen, angemeldeten
 * Verwaltung „Erlauben“. Was dabei herauskommt, gilt eine Stunde und wird
 * erneuert, längstens 30 Tage; danach fragt Claude neu. Ein Klick in den
 * Einstellungen entzieht es sofort.
 *
 * WARUM NUR ZWEI RÜCKWEGE
 * Wer sich anmelden darf, ist offen (das verlangt Claude so). Wohin der Code
 * nach dem „Erlauben“ geht, ist es nicht: ausschließlich an Claudes eigene
 * Rücksprungadresse. Ein Fremder kann sich zwar als Programm anmelden, aber
 * den Code bekäme immer nur Claude — und ohne den passenden PKCE-Schlüssel
 * kann auch Claude ihn nur für die Anfrage einlösen, die es selbst gestellt hat.
 *
 * NUR LESEN
 * Diese Stufe kennt einen einzigen Umfang: verwaltung.lesen. Schreibende
 * Werkzeuge kommen erst in Stufe 3, mit eigenem Umfang und eigener Erlaubnis.
 */
final class ClaudeZugang
{
    public const UMFANG = 'verwaltung.lesen';
    public const TAGE = 30;                     // Uwe, 07.10.2026: „30 Tage“
    public const ZUGANG_SEKUNDEN = 3600;
    public const CODE_SEKUNDEN = 300;
    public const ANFRAGE_SEKUNDEN = 600;
    /** Claudes Rücksprungadressen (claude.com/docs: „Custom connectors“ — Callback-URL). */
    public const RUECKWEGE = ['https://claude.ai/api/mcp/auth_callback', 'https://claude.com/api/mcp/auth_callback'];
    /** Woher ein Browser die Schnittstelle rufen darf (DNS-Rebinding-Schutz der MCP-Transportregeln). */
    public const HERKUENFTE = ['https://claude.ai', 'https://claude.com'];
    /** So viele Werkzeug-Aufrufe je Verbindung in zehn Minuten, dann Pause. */
    public const HOECHSTENS = 300;
    /** Anmeldungen je Adresse und Stunde — mehr braucht kein Mensch. */
    public const ANMELDUNGEN_JE_STUNDE = 20;

    public static function basis(): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
    }

    /** Die eine Adresse, die Uwe bei Claude einträgt — und die jeder Schlüssel als Ziel trägt (RFC 8707). */
    public static function ressource(): string { return self::basis() . '/mcp'; }

    public static function an(): bool
    {
        return (string) self::einstellung('claude_zugang', 'an') !== 'aus';
    }

    /** Aus heißt: alle Verbindungen sofort entzogen, und neue Erlaubnisse gibt es nicht. */
    public static function schalten(bool $an, string $wer): void
    {
        self::setzen('claude_zugang', $an ? 'an' : 'aus');
        if (!$an) {
            Db::run("UPDATE claude_verbindungen SET entzogen_am = NOW(), entzogen_grund = 'Zugang ausgeschaltet'
                      WHERE entzogen_am IS NULL");
            Db::run('DELETE FROM claude_schluessel');
        }
        self::pruefspur($an ? 'claude_zugang_an' : 'claude_zugang_aus', null, ['wer' => $wer]);
    }

    /* ================================================================== */
    /*  Die beiden Steckbriefe                                             */
    /* ================================================================== */

    /** RFC 9728: Wer schützt diese Schnittstelle, und mit welchem Umfang? */
    public static function steckbriefRessource(): array
    {
        return [
            'resource' => self::ressource(),
            'authorization_servers' => [self::basis()],
            'scopes_supported' => [self::UMFANG],
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Vecom Verwaltung (nur lesen)',
        ];
    }

    /** RFC 8414: Wo wird erlaubt, wo eingelöst, wo angemeldet? */
    public static function steckbriefServer(): array
    {
        $b = self::basis();
        return [
            'issuer' => $b,
            'authorization_endpoint' => $b . '/oauth/authorize',
            'token_endpoint' => $b . '/oauth/token',
            'registration_endpoint' => $b . '/oauth/register',
            'revocation_endpoint' => $b . '/oauth/revoke',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'scopes_supported' => [self::UMFANG],
            'authorization_response_iss_parameter_supported' => true,
            'service_documentation' => $b . '/',
        ];
    }

    /* ================================================================== */
    /*  Anmelden eines Programms (RFC 7591)                                */
    /* ================================================================== */

    /** @return array{0:int,1:array} HTTP-Status und Antwort */
    public static function registrieren(array $d, string $ip): array
    {
        if (!self::an()) { return [403, ['error' => 'access_denied', 'error_description' => 'Der Claude-Zugang ist ausgeschaltet.']]; }
        $n = (int) Db::wert('SELECT COUNT(*) FROM claude_clients WHERE ip = ? AND created_at > NOW() - INTERVAL 1 HOUR', [$ip], 0);
        if ($n >= self::ANMELDUNGEN_JE_STUNDE) { return [429, ['error' => 'slow_down', 'error_description' => 'Zu viele Anmeldungen.']]; }

        $wege = $d['redirect_uris'] ?? null;
        if (!is_array($wege) || $wege === [] || count($wege) > 4) {
            return [400, ['error' => 'invalid_redirect_uri', 'error_description' => 'redirect_uris fehlt.']];
        }
        foreach ($wege as $w) {
            if (!is_string($w) || !in_array($w, self::RUECKWEGE, true)) {
                return [400, ['error' => 'invalid_redirect_uri', 'error_description' => 'Nur Claudes eigene Rücksprungadresse ist erlaubt.']];
            }
        }
        $methode = (string) ($d['token_endpoint_auth_method'] ?? 'client_secret_basic');
        if (!in_array($methode, ['none', 'client_secret_post', 'client_secret_basic'], true)) {
            return [400, ['error' => 'invalid_client_metadata', 'error_description' => 'token_endpoint_auth_method wird nicht unterstützt.']];
        }
        $arten = $d['grant_types'] ?? ['authorization_code'];
        if (!is_array($arten) || array_diff($arten, ['authorization_code', 'refresh_token']) !== []) {
            return [400, ['error' => 'invalid_client_metadata', 'error_description' => 'grant_types: nur authorization_code und refresh_token.']];
        }
        $antworten = $d['response_types'] ?? ['code'];
        if (!is_array($antworten) || array_diff($antworten, ['code']) !== []) {
            return [400, ['error' => 'invalid_client_metadata', 'error_description' => 'response_types: nur code.']];
        }
        $name = trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', (string) ($d['client_name'] ?? 'Claude')) ?? 'Claude');
        $name = mb_substr($name !== '' ? $name : 'Claude', 0, 120);

        $clientId = 'vcl_' . bin2hex(random_bytes(16));
        $geheim = $methode === 'none' ? null : 'vcs_' . bin2hex(random_bytes(32));
        Db::insert('claude_clients', [
            'client_id' => $clientId, 'geheim_hash' => $geheim !== null ? hash('sha256', $geheim) : null,
            'name' => $name, 'redirect_uris' => json_encode(array_values($wege), JSON_UNESCAPED_SLASHES),
            'auth_methode' => $methode, 'ip' => mb_substr($ip, 0, 45),
        ]);
        $antwort = [
            'client_id' => $clientId, 'client_id_issued_at' => time(), 'client_name' => $name,
            'redirect_uris' => array_values($wege), 'grant_types' => array_values(array_unique(array_merge($arten, ['authorization_code']))),
            'response_types' => ['code'], 'token_endpoint_auth_method' => $methode, 'scope' => self::UMFANG,
        ];
        if ($geheim !== null) { $antwort['client_secret'] = $geheim; $antwort['client_secret_expires_at'] = 0; }
        return [201, $antwort];
    }

    public static function client(string $clientId): ?array
    {
        if (!preg_match('/^vcl_[a-f0-9]{32}$/', $clientId)) { return null; }
        return Db::one('SELECT * FROM claude_clients WHERE client_id = ?', [$clientId]);
    }

    /* ================================================================== */
    /*  Erlauben                                                           */
    /* ================================================================== */

    /**
     * Die Anfrage von Claude prüfen und merken. Danach geht es in die Verwaltung,
     * wo Uwe angemeldet „Erlauben“ sagt.
     *
     * Ist das Programm oder die Rücksprungadresse falsch, wird NICHT zurückgeleitet
     * (OAuth 2.1, 4.1.2.1) — sonst ließe sich die Seite als Weiche missbrauchen.
     *
     * @return array{art:'weiter'|'fehler'|'zurueck', ziel?:string, text?:string}
     */
    public static function anfrageAnnehmen(array $q): array
    {
        $c = self::client((string) ($q['client_id'] ?? ''));
        if ($c === null) { return ['art' => 'fehler', 'text' => 'Unbekanntes Programm. Bitte die Verbindung in Claude neu anlegen.']; }
        $wege = json_decode((string) $c['redirect_uris'], true) ?: [];
        $weg = (string) ($q['redirect_uri'] ?? '');
        if ($weg === '' && count($wege) === 1) { $weg = (string) $wege[0]; }
        if (!in_array($weg, $wege, true)) { return ['art' => 'fehler', 'text' => 'Die Rücksprungadresse passt nicht zum Programm.']; }

        $state = (string) ($q['state'] ?? '');
        $zurueck = static fn(string $fehler, string $text): array => ['art' => 'zurueck',
            'ziel' => self::mitAntwort($weg, ['error' => $fehler, 'error_description' => $text, 'state' => $state])];

        if (!self::an()) { return $zurueck('access_denied', 'Der Claude-Zugang ist in der Verwaltung ausgeschaltet.'); }
        if ((string) ($q['response_type'] ?? '') !== 'code') { return $zurueck('unsupported_response_type', 'Nur response_type=code.'); }
        $challenge = (string) ($q['code_challenge'] ?? '');
        if ((string) ($q['code_challenge_method'] ?? '') !== 'S256' || !preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge)) {
            return $zurueck('invalid_request', 'PKCE mit S256 ist Pflicht.');
        }
        $ressource = (string) ($q['resource'] ?? self::ressource());
        if (!self::istRessource($ressource)) { return $zurueck('invalid_target', 'Dieser Server vergibt nur Schlüssel für ' . self::ressource() . '.'); }
        $umfang = trim((string) ($q['scope'] ?? ''));
        if ($umfang === '') { $umfang = self::UMFANG; }
        foreach (preg_split('/\s+/', $umfang) ?: [] as $u) {
            if ($u !== self::UMFANG) { return $zurueck('invalid_scope', 'Diese Stufe erlaubt nur ' . self::UMFANG . '.'); }
        }
        if (mb_strlen($state) > 500) { return $zurueck('invalid_request', 'state ist zu lang.'); }

        $id = bin2hex(random_bytes(16));
        Db::insert('claude_anfragen', [
            'id' => $id, 'client_id' => $c['client_id'], 'redirect_uri' => $weg, 'state' => $state !== '' ? $state : null,
            'code_challenge' => $challenge, 'scope' => self::UMFANG, 'resource' => self::ressource(),
            'bis' => date('Y-m-d H:i:s', time() + self::ANFRAGE_SEKUNDEN),
        ]);
        return ['art' => 'weiter', 'ziel' => self::basis() . Config::basis() . '/claude-erlauben?a=' . $id];
    }

    /** Eine offene Anfrage samt Programm, oder null (abgelaufen, schon beantwortet, unbekannt). */
    public static function anfrage(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) { return null; }
        $a = Db::one('SELECT a.*, c.name AS client_name FROM claude_anfragen a JOIN claude_clients c ON c.client_id = a.client_id
                        WHERE a.id = ? AND a.bis > NOW()', [$id]);
        return $a ?: null;
    }

    /** Uwe sagt Ja: Verbindung anlegen, Einmal-Code erzeugen, Ziel der Rückleitung zurückgeben. */
    public static function erlauben(string $anfrageId, int $userId, string $wer): ?string
    {
        if (!self::an()) { return null; }
        $a = self::anfrage($anfrageId);
        if ($a === null) { return null; }
        if (Db::run('DELETE FROM claude_anfragen WHERE id = ?', [$anfrageId])->rowCount() !== 1) { return null; }

        // Eine Verbindung zur Zeit: Erlaubt Uwe neu, enden alle älteren. Claude meldet sich bei jedem
        // neu angelegten Connector als neues Programm an — ohne das stünden nach dem dritten Versuch
        // drei offene Verbindungen da, von denen zwei niemand mehr benutzt (gesehen 07.10.2026).
        foreach (Db::all('SELECT id FROM claude_verbindungen WHERE entzogen_am IS NULL') as $alt) {
            self::entziehen((int) $alt['id'], 'neu erlaubt');
        }
        $vid = Db::insert('claude_verbindungen', [
            'client_id' => $a['client_id'], 'user_id' => $userId, 'scope' => $a['scope'],
            'erlaubt_am' => date('Y-m-d H:i:s'), 'bis' => date('Y-m-d H:i:s', time() + self::TAGE * 86400),
        ]);
        $code = 'vcc_' . bin2hex(random_bytes(32));
        Db::insert('claude_schluessel', [
            'hash' => hash('sha256', $code), 'art' => 'code', 'verbindung_id' => $vid, 'client_id' => $a['client_id'],
            'redirect_uri' => $a['redirect_uri'], 'code_challenge' => $a['code_challenge'],
            'bis' => date('Y-m-d H:i:s', time() + self::CODE_SEKUNDEN),
        ]);
        self::pruefspur('claude_erlaubt', $vid, ['programm' => $a['client_name'], 'bis' => date('d.m.Y', time() + self::TAGE * 86400), 'wer' => $wer]);
        return self::mitAntwort((string) $a['redirect_uri'], ['code' => $code, 'state' => (string) ($a['state'] ?? ''), 'iss' => self::basis()]);
    }

    /** Uwe sagt Nein. */
    public static function ablehnen(string $anfrageId, string $wer): ?string
    {
        $a = self::anfrage($anfrageId);
        if ($a === null) { return null; }
        Db::run('DELETE FROM claude_anfragen WHERE id = ?', [$anfrageId]);
        self::pruefspur('claude_abgelehnt', null, ['programm' => $a['client_name'], 'wer' => $wer]);
        return self::mitAntwort((string) $a['redirect_uri'], ['error' => 'access_denied', 'error_description' => 'In der Verwaltung abgelehnt.',
            'state' => (string) ($a['state'] ?? ''), 'iss' => self::basis()]);
    }

    /* ================================================================== */
    /*  Einlösen (Token-Endpunkt)                                          */
    /* ================================================================== */

    /** @return array{0:int,1:array} */
    public static function einloesen(array $p, string $authKopf = ''): array
    {
        $fehler = static fn(string $e, string $t, int $s = 400): array => [$s, ['error' => $e, 'error_description' => $t]];
        if (!self::an()) { return $fehler('invalid_grant', 'Der Claude-Zugang ist ausgeschaltet.'); }

        // Wer fragt? Bei client_secret_basic steht es im Kopf, sonst im Rumpf.
        $clientId = (string) ($p['client_id'] ?? '');
        $geheim = (string) ($p['client_secret'] ?? '');
        if (stripos($authKopf, 'basic ') === 0) {
            $teile = explode(':', (string) base64_decode(substr($authKopf, 6), true), 2);
            $clientId = rawurldecode((string) ($teile[0] ?? ''));
            $geheim = rawurldecode((string) ($teile[1] ?? ''));
        }
        $c = self::client($clientId);
        if ($c === null) { return $fehler('invalid_client', 'Unbekanntes Programm.', 401); }
        if ((string) $c['auth_methode'] !== 'none'
            && ($geheim === '' || !hash_equals((string) $c['geheim_hash'], hash('sha256', $geheim)))) {
            return $fehler('invalid_client', 'Programm nicht ausgewiesen.', 401);
        }
        if (isset($p['resource']) && !self::istRessource((string) $p['resource'])) {
            return $fehler('invalid_target', 'Dieser Server vergibt nur Schlüssel für ' . self::ressource() . '.');
        }

        $art = (string) ($p['grant_type'] ?? '');
        if ($art === 'authorization_code') {
            $h = hash('sha256', (string) ($p['code'] ?? ''));
            $s = Db::one("SELECT * FROM claude_schluessel WHERE hash = ? AND art = 'code'", [$h]);
            if ($s === null || (string) $s['client_id'] !== $clientId) { return $fehler('invalid_grant', 'Code unbekannt.'); }
            if ($s['benutzt_am'] !== null) {
                // Ein zweites Einlösen heißt: jemand anders hat den Code (OAuth 2.1, 4.1.3) — alles zu.
                self::entziehen((int) $s['verbindung_id'], 'Code zweimal eingelöst');
                return $fehler('invalid_grant', 'Code schon benutzt.');
            }
            if (strtotime((string) $s['bis']) < time()) { return $fehler('invalid_grant', 'Code abgelaufen.'); }
            if (isset($p['redirect_uri']) && (string) $p['redirect_uri'] !== (string) $s['redirect_uri']) {
                return $fehler('invalid_grant', 'Rücksprungadresse passt nicht.');
            }
            $pruefer = (string) ($p['code_verifier'] ?? '');
            if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $pruefer)
                || !hash_equals((string) $s['code_challenge'], self::b64url(hash('sha256', $pruefer, true)))) {
                return $fehler('invalid_grant', 'PKCE stimmt nicht.');
            }
            if (Db::run('UPDATE claude_schluessel SET benutzt_am = NOW() WHERE hash = ? AND benutzt_am IS NULL', [$h])->rowCount() !== 1) {
                return $fehler('invalid_grant', 'Code schon benutzt.');
            }
            $v = self::verbindung((int) $s['verbindung_id']);
            if ($v === null) { return $fehler('invalid_grant', 'Die Erlaubnis gilt nicht mehr.'); }
            return [200, self::schluesselPaar($v)];
        }

        if ($art === 'refresh_token') {
            $h = hash('sha256', (string) ($p['refresh_token'] ?? ''));
            $s = Db::one("SELECT * FROM claude_schluessel WHERE hash = ? AND art = 'erneuern'", [$h]);
            if ($s === null || (string) $s['client_id'] !== $clientId) { return $fehler('invalid_grant', 'Unbekannt.'); }
            if ($s['benutzt_am'] !== null) {
                // Ein erneuerter Schlüssel kommt ein zweites Mal: Er ist in fremder Hand (OAuth 2.1, 4.3.1).
                self::entziehen((int) $s['verbindung_id'], 'Erneuerungsschlüssel zweimal benutzt');
                try {
                    require_once __DIR__ . '/Events.php';
                    Events::melden('claude_entzogen', 'Claude-Verbindung vorsorglich entzogen', 'warnung',
                        'Ein schon benutzter Erneuerungsschlüssel kam ein zweites Mal. Die Verbindung ist zu; Claude fragt beim nächsten Mal neu um Erlaubnis.',
                        '/einstellungen?b=claude');
                } catch (Throwable $e) { }
                return $fehler('invalid_grant', 'Schon benutzt.');
            }
            if (strtotime((string) $s['bis']) < time()) { return $fehler('invalid_grant', 'Abgelaufen — bitte neu erlauben.'); }
            if (Db::run('UPDATE claude_schluessel SET benutzt_am = NOW() WHERE hash = ? AND benutzt_am IS NULL', [$h])->rowCount() !== 1) {
                return $fehler('invalid_grant', 'Schon benutzt.');
            }
            $v = self::verbindung((int) $s['verbindung_id']);
            if ($v === null) { return $fehler('invalid_grant', 'Die Erlaubnis gilt nicht mehr — bitte neu erlauben.'); }
            return [200, self::schluesselPaar($v)];
        }
        return $fehler('unsupported_grant_type', 'Nur authorization_code und refresh_token.');
    }

    /** RFC 7009: Ein Schlüssel wird zurückgegeben. Antwortet immer 200, auch bei Unbekanntem. */
    public static function zurueckgeben(string $token): void
    {
        if ($token === '') { return; }
        $s = Db::one('SELECT * FROM claude_schluessel WHERE hash = ?', [hash('sha256', $token)]);
        if ($s === null) { return; }
        if ((string) $s['art'] === 'erneuern') { self::entziehen((int) $s['verbindung_id'], 'von Claude zurückgegeben'); return; }
        Db::run('DELETE FROM claude_schluessel WHERE hash = ?', [hash('sha256', $token)]);
    }

    /** Zugang (eine Stunde) und Erneuerung (bis zum Ende der Verbindung). */
    private static function schluesselPaar(array $v): array
    {
        $zugang = 'vca_' . bin2hex(random_bytes(32));
        $neu = 'vcr_' . bin2hex(random_bytes(32));
        $zugangBis = min(time() + self::ZUGANG_SEKUNDEN, strtotime((string) $v['bis']));
        Db::insert('claude_schluessel', ['hash' => hash('sha256', $zugang), 'art' => 'zugang', 'verbindung_id' => (int) $v['id'],
            'client_id' => $v['client_id'], 'bis' => date('Y-m-d H:i:s', $zugangBis)]);
        Db::insert('claude_schluessel', ['hash' => hash('sha256', $neu), 'art' => 'erneuern', 'verbindung_id' => (int) $v['id'],
            'client_id' => $v['client_id'], 'bis' => (string) $v['bis']]);
        return ['access_token' => $zugang, 'token_type' => 'Bearer', 'expires_in' => max(1, $zugangBis - time()),
                'refresh_token' => $neu, 'scope' => (string) $v['scope']];
    }

    /* ================================================================== */
    /*  An der Schnittstelle: gilt der Schlüssel?                          */
    /* ================================================================== */

    /** Die Verbindung zum Schlüssel, oder null. Prüft Ziel, Frist, Entzug und den Hauptschalter. */
    public static function pruefen(string $token): ?array
    {
        if ($token === '' || !str_starts_with($token, 'vca_') || !self::an()) { return null; }
        $s = Db::one("SELECT * FROM claude_schluessel WHERE hash = ? AND art = 'zugang' AND bis > NOW()", [hash('sha256', $token)]);
        if ($s === null) { return null; }
        $v = self::verbindung((int) $s['verbindung_id']);
        if ($v === null) { return null; }
        Db::run('UPDATE claude_verbindungen SET zuletzt_am = NOW(), aufrufe = aufrufe + 1 WHERE id = ?', [(int) $v['id']]);
        return $v;
    }

    /** Eine noch gültige Verbindung, oder null. */
    public static function verbindung(int $id): ?array
    {
        return Db::one('SELECT * FROM claude_verbindungen WHERE id = ? AND entzogen_am IS NULL AND bis > NOW()', [$id]) ?: null;
    }

    public static function entziehen(int $id, string $grund): bool
    {
        $n = Db::run('UPDATE claude_verbindungen SET entzogen_am = NOW(), entzogen_grund = ? WHERE id = ? AND entzogen_am IS NULL',
            [mb_substr($grund, 0, 200), $id])->rowCount();
        Db::run('DELETE FROM claude_schluessel WHERE verbindung_id = ?', [$id]);
        if ($n > 0) { self::pruefspur('claude_entzogen', $id, ['grund' => $grund]); }
        return $n > 0;
    }

    /** Hat diese Verbindung in den letzten zehn Minuten zu oft gefragt? */
    public static function zuViel(int $verbindungId): bool
    {
        return (int) Db::wert('SELECT COUNT(*) FROM claude_spur WHERE verbindung_id = ? AND created_at > NOW() - INTERVAL 10 MINUTE',
            [$verbindungId], 0) >= self::HOECHSTENS;
    }

    /** Jeder Griff landet hier — die Argumente gekürzt, nie eine Antwort. */
    public static function spur(?int $verbindungId, string $werkzeug, array $argumente, bool $ok, int $zeichen, int $ms): void
    {
        try {
            Db::insert('claude_spur', ['verbindung_id' => $verbindungId, 'werkzeug' => mb_substr($werkzeug, 0, 60),
                'argumente' => $argumente ? mb_substr((string) json_encode($argumente, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 500) : null,
                'ok' => $ok ? 1 : 0, 'zeichen' => max(0, $zeichen), 'ms' => max(0, $ms)]);
        } catch (Throwable $e) { }
    }

    /* ================================================================== */
    /*  Für die Einstellungen                                              */
    /* ================================================================== */

    public static function verbindungen(): array
    {
        return Db::all('SELECT v.*, c.name AS programm, u.name AS erlaubt_von
                          FROM claude_verbindungen v
                          LEFT JOIN claude_clients c ON c.client_id = v.client_id
                          LEFT JOIN users u ON u.id = v.user_id
                         ORDER BY (v.entzogen_am IS NULL AND v.bis > NOW()) DESC, v.id DESC LIMIT 12');
    }

    public static function letzteGriffe(int $n = 25): array
    {
        return Db::all('SELECT * FROM claude_spur ORDER BY id DESC LIMIT ' . max(1, min(200, $n)));
    }

    /** Täglich im Cron: Abgelaufenes weg. Die Spur bleibt ein halbes Jahr. */
    public static function aufraeumen(): array
    {
        return [
            'anfragen' => Db::run('DELETE FROM claude_anfragen WHERE bis < NOW()')->rowCount(),
            'schluessel' => Db::run('DELETE FROM claude_schluessel WHERE bis < NOW() - INTERVAL 1 DAY')->rowCount(),
            'programme' => Db::run('DELETE FROM claude_clients WHERE created_at < NOW() - INTERVAL 2 DAY
                                     AND client_id NOT IN (SELECT client_id FROM claude_verbindungen)')->rowCount(),
            'spur' => Db::run('DELETE FROM claude_spur WHERE created_at < NOW() - INTERVAL 180 DAY')->rowCount(),
        ];
    }

    /* ================================================================== */

    /** RFC 8707: dieselbe Adresse, Schema und Host ohne Rücksicht auf Groß/klein, ohne Schrägstrich am Ende. */
    public static function istRessource(string $r): bool
    {
        $norm = static function (string $u): string {
            $t = parse_url(rtrim($u, '/'));
            if (!is_array($t) || !isset($t['scheme'], $t['host']) || isset($t['fragment'])) { return ''; }
            return strtolower($t['scheme']) . '://' . strtolower($t['host']) . (isset($t['port']) ? ':' . $t['port'] : '') . ($t['path'] ?? '');
        };
        $n = $norm($r);
        return $n !== '' && ($n === $norm(self::ressource()) || $n === $norm(self::basis()));
    }

    public static function b64url(string $roh): string { return rtrim(strtr(base64_encode($roh), '+/', '-_'), '='); }

    private static function mitAntwort(string $weg, array $teile): string
    {
        $teile = array_filter($teile, static fn($w) => $w !== '');
        return $weg . (str_contains($weg, '?') ? '&' : '?') . http_build_query($teile, '', '&', PHP_QUERY_RFC3986);
    }

    private static function einstellung(string $k, string $ersatz): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], $ersatz); } catch (Throwable $e) { return $ersatz; }
    }

    private static function setzen(string $k, string $w): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $w]);
    }

    private static function pruefspur(string $aktion, ?int $id, array $nachher): void
    {
        try { require_once __DIR__ . '/Events.php'; Events::pruefspur($aktion, 'claude_verbindungen', $id, [], $nachher); } catch (Throwable $e) { }
    }
}
