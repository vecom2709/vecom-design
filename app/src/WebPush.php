<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Web-Push ohne fremde Bibliothek (26.09.2026, Partner-App).
 *
 * WARUM SELBST GESCHRIEBEN
 * Auf dem Webspace gibt es keinen Composer (siehe Pdf.php). Eine Push-
 * Bibliothek haette ein Dutzend Pakete mitgebracht; gebraucht werden drei
 * Dinge, die PHP mit openssl ohnehin kann:
 *   1. VAPID (RFC 8292): ein JWT, mit dem eigenen EC-Schluessel signiert.
 *   2. Verschluesselung (RFC 8291, aes128gcm): ECDH mit dem Schluessel des
 *      Geraets, HKDF, AES-128-GCM.
 *   3. Ein POST an den Endpoint des Browsers.
 * Die Kette prueft beides von der Gegenseite her: Sie entschluesselt, was
 * hier verschluesselt wurde, und prueft die Signatur -- so faellt ein
 * Rechenfehler nicht erst auf dem Handy eines Partners auf.
 *
 * Der private VAPID-Schluessel liegt in der Datenbank (settings), nicht im
 * Repository -- das Repository ist oeffentlich.
 */
final class WebPush
{
    /** Kopf einer DER-kodierten P-256-Adresse vor den 65 Bytes des Punkts. */
    private const DER_KOPF = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    /** Fuer die Kette: statt zu senden, bekommt diese Funktion Ziel, Kopf und Inhalt. */
    public static $probe = null;

    public static function b64(string $roh): string { return rtrim(strtr(base64_encode($roh), '+/', '-_'), '='); }
    public static function unb64(string $s): string { return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

    /**
     * Der eigene VAPID-Schluessel -- beim ersten Aufruf erzeugt.
     *
     * @return array{privat:string,oeffentlich:string}  PEM und Base64url (65 Bytes)
     */
    public static function schluessel(): array
    {
        $privat = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'webpush_privat'", [], '');
        $offen  = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'webpush_oeffentlich'", [], '');
        if ($privat !== '' && $offen !== '') { return ['privat' => $privat, 'oeffentlich' => $offen]; }
        [$pem, $punkt] = self::paar();
        foreach (['webpush_privat' => $pem, 'webpush_oeffentlich' => self::b64($punkt)] as $k => $v) {
            // INSERT IGNORE: laufen zwei Aufrufe gleichzeitig, gewinnt der erste -- beide lesen danach dasselbe.
            Db::run('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)', [$k, $v]);
        }
        return [
            'privat' => (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'webpush_privat'", [], ''),
            'oeffentlich' => (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'webpush_oeffentlich'", [], ''),
        ];
    }

    /** @return array{0:string,1:string} PEM des privaten Schluessels, oeffentlicher Punkt (65 Bytes, 0x04|x|y) */
    public static function paar(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($k === false) { throw new RuntimeException('openssl kann keinen EC-Schlüssel erzeugen.'); }
        openssl_pkey_export($k, $pem);
        $d = openssl_pkey_get_details($k)['ec'];
        return [(string) $pem, "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)];
    }

    /** Oeffentlichen Punkt (65 Bytes) als openssl-Schluessel. */
    public static function punktAlsSchluessel(string $punkt)
    {
        $der = hex2bin(self::DER_KOPF) . $punkt;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $k = openssl_pkey_get_public($pem);
        if ($k === false) { throw new RuntimeException('Ungültiger Geräteschlüssel.'); }
        return $k;
    }

    /**
     * Verschluesselt nach RFC 8291 (aes128gcm).
     *
     * @return string Kopf (Salz, Satzgroesse, Absenderschluessel) + Chiffrat
     */
    public static function verschluesseln(string $inhalt, string $geraetPunkt, string $geraetAuth, ?array $einmal = null, ?string $salz = null): string
    {
        [$ePem, $ePunkt] = $einmal ?? self::paar();
        $salz ??= random_bytes(16);
        $ecdh = openssl_pkey_derive(self::punktAlsSchluessel($geraetPunkt), openssl_pkey_get_private($ePem), 32);
        if ($ecdh === false) { throw new RuntimeException('ECDH fehlgeschlagen.'); }
        $ikm = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $geraetPunkt . $ePunkt, $geraetAuth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salz);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salz);
        $tag = '';
        $chiffrat = openssl_encrypt($inhalt . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($chiffrat === false) { throw new RuntimeException('AES-GCM fehlgeschlagen.'); }
        return $salz . pack('N', 4096) . chr(strlen($ePunkt)) . $ePunkt . $chiffrat . $tag;
    }

    /** Die Gegenrichtung -- nur fuer die Kette, die damit die Verschluesselung prueft. */
    public static function entschluesseln(string $paket, string $geraetPem, string $geraetPunkt, string $geraetAuth): string
    {
        $salz = substr($paket, 0, 16);
        $idLen = ord($paket[20]);
        $ePunkt = substr($paket, 21, $idLen);
        $rest = substr($paket, 21 + $idLen);
        $ecdh = openssl_pkey_derive(self::punktAlsSchluessel($ePunkt), openssl_pkey_get_private($geraetPem), 32);
        $ikm = hash_hkdf('sha256', (string) $ecdh, 32, "WebPush: info\0" . $geraetPunkt . $ePunkt, $geraetAuth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salz);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salz);
        $klar = openssl_decrypt(substr($rest, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($rest, -16));
        if ($klar === false || !str_ends_with($klar, "\x02")) { throw new RuntimeException('Entschlüsseln fehlgeschlagen.'); }
        return substr($klar, 0, -1);
    }

    /** VAPID-JWT fuer den Ursprung des Endpoints. */
    public static function jwt(string $endpoint, string $privatPem, int $jetzt): string
    {
        $u = parse_url($endpoint);
        $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
        $kopf = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $koerper = self::b64(json_encode(['aud' => $aud, 'exp' => $jetzt + 12 * 3600, 'sub' => 'mailto:kontakt@vecom-design.it']));
        $daten = $kopf . '.' . $koerper;
        if (!openssl_sign($daten, $der, $privatPem, OPENSSL_ALGO_SHA256)) { throw new RuntimeException('VAPID-Signatur fehlgeschlagen.'); }
        return $daten . '.' . self::b64(self::derZuRoh($der));
    }

    /** ECDSA-Signatur: DER (SEQUENCE aus r und s) → 64 Bytes r|s, wie JWS sie verlangt. */
    public static function derZuRoh(string $der): string
    {
        $pos = 2;
        if ((ord($der[1]) & 0x80) !== 0) { $pos += ord($der[1]) & 0x7f; }
        $teile = [];
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $wert = ltrim(substr($der, $pos + 2, $len), "\0");
            $teile[] = str_pad($wert, 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }
        return $teile[0] . $teile[1];
    }

    /** Die Gegenrichtung (fuer openssl_verify in der Kette). */
    public static function rohZuDer(string $roh): string
    {
        $ein = static function (string $z): string {
            $z = ltrim($z, "\0");
            if ($z === '' || (ord($z[0]) & 0x80)) { $z = "\0" . $z; }
            return "\x02" . chr(strlen($z)) . $z;
        };
        $inhalt = $ein(substr($roh, 0, 32)) . $ein(substr($roh, 32, 32));
        return "\x30" . chr(strlen($inhalt)) . $inhalt;
    }

    /**
     * Schickt eine Nachricht an ein Geraet.
     *
     * @param array{endpoint:string,p256dh:string,auth:string} $abo
     * @param array{titel:string,text:string,link?:string} $nachricht
     * @return array{ok:bool,status:int,weg:bool} weg = der Browser hat das Abo aufgegeben (404/410)
     */
    public static function senden(array $abo, array $nachricht, int $ttl = 86400): array
    {
        $s = self::schluessel();
        $paket = self::verschluesseln(json_encode($nachricht, JSON_UNESCAPED_UNICODE),
            self::unb64((string) $abo['p256dh']), self::unb64((string) $abo['auth']));
        $kopf = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . $ttl,
            'Urgency: normal',
            'Authorization: vapid t=' . self::jwt((string) $abo['endpoint'], $s['privat'], time()) . ', k=' . $s['oeffentlich'],
        ];
        if (self::$probe !== null) {
            $status = (int) (self::$probe)((string) $abo['endpoint'], $kopf, $paket);
        } else {
            $status = self::post((string) $abo['endpoint'], $kopf, $paket);
        }
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'weg' => in_array($status, [404, 410], true)];
    }

    private static function post(string $ziel, array $kopf, string $inhalt): int
    {
        if (!preg_match('~^https://~', $ziel)) { return 0; }
        if (function_exists('curl_init')) {
            $c = curl_init($ziel);
            curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $inhalt, CURLOPT_HTTPHEADER => $kopf,
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5]);
            curl_exec($c);
            $status = (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE);
            curl_close($c);
            return $status;
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $kopf), 'content' => $inhalt,
            'timeout' => 10, 'ignore_errors' => true]]);
        @file_get_contents($ziel, false, $ctx);
        $zeile = $http_response_header[0] ?? '';
        return preg_match('~\s(\d{3})\s~', $zeile . ' ', $m) ? (int) $m[1] : 0;
    }
}
