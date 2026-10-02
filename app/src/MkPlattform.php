<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/MkInhalt.php';

/* ==========================================================================
   MkPlattform.php — LinkedIn, Google-Unternehmensprofil, YouTube und TikTok
   voll automatisch (01.10.2026, Uwe: Ja zu P4).

   Alle vier verlangen eine Prüfung bzw. Freischaltung durch die Plattform
   (recherchiert 01.10.2026, Quellen in PROJEKT.md). Bis dahin bleibt der
   Handy-Weg (MkHandy). Danach: Uwe trägt Client-ID und -Secret ein, drückt
   „Verbinden“ (OAuth bei der Plattform), setzt den Haken „Freigabe der
   Plattform erhalten“ — ab dann postet Vecom selbst.

     LinkedIn   Posts API + Images API, Seite urn:li:organization:ID, w_organization_social
     Google     My Business API v4 localPosts, accounts/{a}/locations/{l}, business.manage
     YouTube    Data API v3 videos.insert (Resumable Upload), youtube.upload — Hochformat = Short
     TikTok     Content Posting API Direct Post, FILE_UPLOAD in einem Stück, video.publish —
                nie automatisch, sondern je Video über die Bestätigungsseite (s. unten, 02.10.2026)

   Schlüssel (Client-Secret, Zugangs- und Erneuerungsschlüssel) liegen nur
   versiegelt in settings (Hosting::versiegeln) und verlassen den Server nie.
   ========================================================================== */
final class MkPlattform
{
    /** Schlüssel => [Name, Formate, die automatisch gehen, braucht Medium] */
    public const ALLE = [
        'linkedin' => ['LinkedIn-Unternehmensseite', ['beitrag', 'karussell'], null],
        'google'   => ['Google-Unternehmensprofil', ['profil'], null],
        'youtube'  => ['YouTube Shorts', ['reel'], 'video'],
        'tiktok'   => ['TikTok', ['reel'], 'video'],
    ];

    /** OAuth je Plattform. */
    public const OAUTH = [
        'linkedin' => ['auth' => 'https://www.linkedin.com/oauth/v2/authorization', 'token' => 'https://www.linkedin.com/oauth/v2/accessToken',
                       'scope' => 'w_organization_social r_organization_social', 'id_wort' => 'Organisations-ID der Unternehmensseite (Zahl aus linkedin.com/company/…/admin)'],
        'google'   => ['auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token',
                       'scope' => 'https://www.googleapis.com/auth/business.manage', 'id_wort' => 'accountId/locationId (z. B. 1234567890/9876543210)'],
        'youtube'  => ['auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token',
                       'scope' => 'https://www.googleapis.com/auth/youtube.upload', 'id_wort' => ''],
        'tiktok'   => ['auth' => 'https://www.tiktok.com/v2/auth/authorize/', 'token' => 'https://open.tiktokapis.com/v2/oauth/token/',
                       'scope' => 'user.info.basic,video.publish,video.upload', 'id_wort' => ''],
    ];

    public const LINKEDIN_VERSION = '202609';

    /** Für die Kette: Netz ersetzen. fn(string $methode, string $url, array $kopf, string|array|null $body): array{status:int, json:?array, kopf:array} */
    public static $netz = null;

    /* ------------------------------------------------------------------ */
    /* Einstellungen                                                       */
    /* ------------------------------------------------------------------ */

    private static function wert(string $k): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''); } catch (Throwable $e) { return ''; }
    }

    private static function setzen(string $k, string $v): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
    }

    /** Versiegelte Daten je Plattform: secret, access, refresh, laeuft_ab. */
    private static function geheim(string $p): array
    {
        $blob = self::wert('pf_' . $p . '_geheim');
        if ($blob === '') { return []; }
        require_once __DIR__ . '/Hosting.php';
        return (array) (Hosting::entsiegeln($blob) ?? []);
    }

    private static function geheimSetzen(string $p, array $neu): void
    {
        require_once __DIR__ . '/Hosting.php';
        $blob = (string) Hosting::versiegeln(array_merge(self::geheim($p), $neu));
        if ($blob === '') { throw new RuntimeException('Der Schlüssel ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).'); }
        self::setzen('pf_' . $p . '_geheim', $blob);
    }

    /** Was die Verwaltung zeigen darf (keine Schlüssel). */
    public static function einstellungen(string $p): array
    {
        $g = self::geheim($p);
        return ['client_id' => self::wert('pf_' . $p . '_client'), 'konto' => self::wert('pf_' . $p . '_konto'),
                'freigabe' => self::wert('pf_' . $p . '_freigabe') === '1', 'secret' => !empty($g['secret']),
                'verbunden' => !empty($g['refresh']) || (!empty($g['access']) && (int) ($g['laeuft_ab'] ?? 0) > time()),
                'verbunden_am' => self::wert('pf_' . $p . '_verbunden_am')];
    }

    public static function speichern(string $p, array $d): ?string
    {
        if (!isset(self::ALLE[$p])) { return 'Unbekannte Plattform.'; }
        $id = trim((string) ($d['client_id'] ?? ''));
        /* 02.10.2026: Chrome hielt Client-ID und Secret für Benutzername und Passwort und füllte
           E-Mail und Kennwort ein — gespeichert stand „kontaktvecom-design.it“, Google meldete
           „client secret is invalid“. Eine E-Mail ist nie eine Client-ID. */
        if (str_contains($id, '@')) { return 'Im Feld Client-ID steht eine E-Mail-Adresse — vermutlich vom Browser eingesetzt. Bitte die Client-ID der Plattform einfügen und das Secret neu eintragen.'; }
        if ($id !== '') { self::setzen('pf_' . $p . '_client', mb_substr(preg_replace('~[^A-Za-z0-9._\-]~', '', $id) ?? '', 0, 200)); }
        if (array_key_exists('konto', $d)) { self::setzen('pf_' . $p . '_konto', mb_substr(preg_replace('~[^0-9/]~', '', (string) $d['konto']) ?? '', 0, 80)); }
        self::setzen('pf_' . $p . '_freigabe', !empty($d['freigabe']) ? '1' : '0');
        $s = trim((string) ($d['secret'] ?? ''));
        if ($s !== '') { self::geheimSetzen($p, ['secret' => $s]); }
        Events::pruefspur('plattform_einstellungen', 'settings', null, [], ['plattform' => $p, 'freigabe' => !empty($d['freigabe'])]);
        return $s !== '' ? self::secretPruefen($p) : null;
    }

    /** Passt das Secret zur Client-ID? (02.10.2026: „client secret is invalid“ zeigte sich erst nach
        dem Umweg über Google beim Verbinden.) Google prüft Client-ID und Secret, bevor es den Code
        ansieht: ein erfundener Code ergibt „invalid_grant“, wenn beide passen, sonst „invalid_client“.
        Es entsteht dabei kein Schlüssel. Nur Google — bei den anderen ist die Antwort nicht so eindeutig. */
    private static function secretPruefen(string $p): ?string
    {
        if (!in_array($p, ['google', 'youtube'], true)) { return null; }
        $e = self::einstellungen($p);
        if ($e['client_id'] === '') { return null; }
        $r = self::http('POST', self::OAUTH[$p]['token'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'grant_type' => 'authorization_code', 'code' => 'vecom-pruefung', 'redirect_uri' => self::rueckrufAdresse($p),
            'client_id' => $e['client_id'], 'client_secret' => (string) (self::geheim($p)['secret'] ?? '')]));
        $fehler = (string) (((array) ($r['json'] ?? []))['error'] ?? '');
        if ($fehler === 'invalid_client' || $fehler === 'unauthorized_client') {
            return self::ALLE[$p][0] . ': gespeichert, aber Google lehnt Client-ID und Secret ab — das Secret passt nicht zu dieser Client-ID. Bitte das Secret neu einfügen (Feld leeren, dann einfügen).';
        }
        return null;
    }

    public static function trennen(string $p): void
    {
        if (!isset(self::ALLE[$p])) { return; }
        self::geheimSetzen($p, ['access' => '', 'refresh' => '', 'laeuft_ab' => 0]);
        self::setzen('pf_' . $p . '_verbunden_am', '');
        Events::pruefspur('plattform_getrennt', 'settings', null, [], ['plattform' => $p]);
    }

    /** Geht diese Plattform jetzt automatisch? (verbunden, Konto bekannt, Freigabe der Plattform erhalten) */
    public static function bereit(string $p): bool
    {
        if (!isset(self::ALLE[$p])) { return false; }
        $e = self::einstellungen($p);
        $kontoNoetig = self::OAUTH[$p]['id_wort'] !== '';
        return $e['freigabe'] && $e['verbunden'] && $e['client_id'] !== '' && (!$kontoNoetig || $e['konto'] !== '');
    }

    /* ------------------------------------------------------------------ */
    /* OAuth                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Die fertigen Antworten für die Anträge bei den Plattformen (01.10.2026,
     * Uwe: „Ja“ — „Antragstexte fertig schreiben, Sie schicken nur ab“).
     * Englisch, weil die Formulare englisch sind. Nur Tatsachen aus den
     * Firmendaten und aus dem, was der Code wirklich tut — nichts erfunden:
     * eigene Seite/eigenes Profil/eigener Kanal, Posten erst nach Freigabe,
     * höchstens ein Beitrag am Tag, keine Mitgliederdaten.
     * Stand der Regeln recherchiert 01.10.2026 (Quellen in PROJEKT.md).
     *
     * @return array{voraus:list<string>, felder:list<array{0:string,1:string}>}
     */
    public static function antrag(string $p): array
    {
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Sprache.php';
        $web = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $name = Firma::get('name', 'Vecom Design');
        $inhaber = Firma::get('inhaber');
        $mail = Firma::get('email', 'kontakt@vecom-design.it');
        $adresse = implode(', ', array_filter([Firma::get('strasse'), trim(Firma::get('plz') . ' ' . Firma::get('ort')), 'Italy']));   // englisch wie das Formular
        $piva = Firma::get('piva');
        $privacy = Sprache::legal('en', 'privacy');
        $terms = Sprache::legal('en', 'agb');
        $wer = $name . ' is a small web design studio in ' . (Firma::get('ort') ?: 'Aragona (AG)') . ', Sicily, Italy, run by ' . ($inhaber ?: 'its owner') . '. It builds websites, online shops and logos for small local businesses.';
        $allgemein = [
            ['Legal name / organization', $name . ($inhaber !== '' ? ' (' . $inhaber . ')' : '') . ($piva !== '' ? ' — Partita IVA ' . $piva : '')],
            ['Address', $adresse],
            ['Website', $web],
            ['Business email', $mail],
            ['Privacy policy URL', $privacy],
            ['Terms of service URL', $terms],
        ];
        $voraus = [];
        $felder = [];
        switch ($p) {
            case 'linkedin':
                if ($piva === '') { $voraus[] = 'LinkedIn nimmt für diese Schnittstelle nur eingetragene Unternehmen an (geprüfte Firma, Website und Domain). Ohne Partita IVA unter Einstellungen › Firma ist eine Ablehnung wahrscheinlich — und ein abgelehnter Antrag lässt sich mit derselben App nicht wiederholen.'; }
                $voraus[] = 'Eine LinkedIn-Unternehmensseite für Vecom Design muss es geben; ihr Super-Admin bestätigt die App.';
                $voraus[] = 'Die App nur für diesen Antrag anlegen, ohne LinkedIn- oder Microsoft-Namen und -Logo.';
                $felder = array_merge($allgemein, [
                    ['App name', $name . ' Publisher'],
                    ['Use case description', $wer . ' We use this application only for our own LinkedIn Page. Our in-house content tool drafts posts about our work (practical tips, before/after examples, short case studies). '
                        . 'Every post is reviewed and approved by our page administrator before it is scheduled. At the scheduled time the application publishes the approved post (text, image or image carousel) '
                        . 'to our own organization page through the Posts API and Images API (w_organization_social) and reads it back (r_organization_social) to confirm it was published. '
                        . 'We do not access, store or share LinkedIn member data, we do not message members, and the application is not offered to third parties. Expected volume: at most one post per day.'],
                ]);
                break;
            case 'google':
                $voraus[] = 'Das Google-Unternehmensprofil muss bestätigt und mindestens 60 Tage alt sein, mit eingetragener Website — es steht in den Verzeichnissen noch als „offen“.';
                $voraus[] = 'Den Antrag mit dem Google-Konto stellen, das Inhaber des Profils ist; vorher ein Projekt in der Google Cloud anlegen (Projektnummer ins Formular).';
                $felder = array_merge($allgemein, [
                    ['Number of locations', '1 (our own business)'],
                    ['Use case', $wer . ' We manage exactly one Business Profile: our own. We want to publish our own local posts (short updates with a "Learn more" link to our website) from our in-house content tool, '
                        . 'only after manual approval by the owner, and read our own profile information to show it in our admin area. We do not manage profiles of other businesses and do not collect reviews or customer data through the API. '
                        . 'Volume: at most one post per day.'],
                ]);
                break;
            case 'youtube':
                $voraus[] = 'Ein YouTube-Kanal für Vecom Design und ein Google-Cloud-Projekt mit YouTube Data API v3; Zustimmungsbildschirm auf „In production“.';
                $voraus[] = 'Das Formular verlangt Bildschirmfotos dieser Seite (Verbinden & Posten) und der Freigabe — die Schlüssel dabei nicht zeigen.';
                $felder = array_merge($allgemein, [
                    ['API client description', 'Internal publishing tool of ' . $name . '. ' . $wer . ' The tool uploads short vertical videos (YouTube Shorts) that we produce ourselves to our own YouTube channel, after the channel owner has approved each video.'],
                    ['How the client uses YouTube API Services', 'OAuth 2.0 with the scope youtube.upload, authorized once by the channel owner for our own channel only. The client calls videos.insert (resumable upload) with title, description and tags; '
                        . 'nothing else. It does not read other channels, comments or analytics, and stores no YouTube data except the returned video ID, which links the upload to the post in our admin area. There is exactly one user: the owner.'],
                    ['Quota', 'The default quota is enough: at most one or two uploads per day. We ask for the audit so that our uploads are no longer restricted to private.'],
                ]);
                break;
            case 'tiktok':
                $voraus[] = 'TikTok verlangt beim „Direct Post“ für JEDEN Beitrag eine eigene Seite: Kontoname anzeigen, Sichtbarkeit ohne Vorauswahl wählen lassen, Kommentare/Duett/Stitch ankreuzen, Werbekennzeichnung, Vorschau und den Satz „By posting, you agree to TikTok\'s Music Usage Confirmation“. '
                    . 'Diese Seite gibt es seit dem 02.10.2026 (Inhalte › Stück › „Auf TikTok veröffentlichen“). Ganz ohne Klick geht TikTok also nie — dafür ist es ein Klick in der Verwaltung statt Video speichern, App öffnen, Text einfügen.';
                $voraus[] = 'Für den Antrag verlangt TikTok ein Demo-Video (max. 5 Videos, je 50 MB) vom ganzen Ablauf: Verbinden unter Kanäle › Verbinden & Posten, dann die Bestätigungsseite ausfüllen und senden. Vor dem ersten Antrag im Sandbox-Modus der App testen — dort darf das Konto nur privat posten.';
                $voraus[] = 'Ehrlich bleiben: TikTok lehnt Apps ab, die ausschließlich privat genutzt werden. Die Beschreibung sagt deshalb, was das Werkzeug ist — die Veröffentlichungsfunktion des eigenen Marketing-Werkzeugs eines Webdesign-Studios. Wird der Antrag abgelehnt, bleibt „Als Entwurf in die TikTok-App“: Das Video liegt dann in der TikTok-App bereit, ein Tipp auf „Posten“ genügt.';
                $felder = array_merge($allgemein, [
                    ['App description', 'Internal tool of ' . $name . ' to publish our own short videos to our own TikTok account. ' . $wer . ' Each video is reviewed by the account owner, who chooses privacy level, interaction settings and commercial content disclosure on a confirmation page before it is posted.'],
                    ['Products and scopes', 'Login Kit (user.info.basic) to show the account nickname on the confirmation page; Content Posting API with Direct Post (video.publish) and Upload to inbox as draft (video.upload) for the approved video file. One user: the account owner. No data of other users is accessed or stored.'],
                    ['How the confirmation page works', 'Before anything is sent, the page loads the latest creator info and shows the account nickname and avatar, a preview of the video and an editable caption. Privacy has no default and only offers the options returned by creator_info. Comment, Duet and Stitch are unchecked by default and greyed out when the account disables them. '
                        . 'Commercial content disclosure is off by default; when on, "Your brand" and/or "Branded content" must be chosen, and branded content cannot be private. The page shows "By posting, you agree to TikTok\'s Music Usage Confirmation" (or the Branded Content Policy variant), checks the maximum video duration, posts only after the owner clicks the button, '
                        . 'tells the owner that processing may take a few minutes and polls the publish status. Nothing is posted automatically or on a schedule.'],
                ]);
                break;
        }
        return ['voraus' => $voraus, 'felder' => array_values(array_filter($felder, static fn($f) => trim((string) $f[1]) !== ''))];
    }

    public static function rueckrufAdresse(string $p): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . Config::basis() . '/plattform-rueckruf?p=' . $p;
    }

    /** Adresse, auf der Uwe bei der Plattform zustimmt. $zustand kommt in die Sitzung. */
    public static function verbindenAdresse(string $p, string $zustand): ?string
    {
        $e = self::einstellungen($p);
        if (!isset(self::OAUTH[$p]) || $e['client_id'] === '' || !$e['secret']) { return null; }
        $o = self::OAUTH[$p];
        $q = ['response_type' => 'code', 'redirect_uri' => self::rueckrufAdresse($p), 'state' => $zustand, 'scope' => $o['scope']];
        if ($p === 'tiktok') { $q['client_key'] = $e['client_id']; } else { $q['client_id'] = $e['client_id']; }
        if (in_array($p, ['google', 'youtube'], true)) { $q['access_type'] = 'offline'; $q['prompt'] = 'consent'; }
        return $o['auth'] . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /** Rückruf: Code gegen Schlüssel tauschen. */
    public static function rueckruf(string $p, string $code): ?string
    {
        if (!isset(self::OAUTH[$p]) || $code === '') { return 'Kein Code von der Plattform.'; }
        $r = self::tokenAnfrage($p, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => self::rueckrufAdresse($p)]);
        if ($r !== null) { return $r; }
        self::setzen('pf_' . $p . '_verbunden_am', date('Y-m-d H:i'));
        Events::pruefspur('plattform_verbunden', 'settings', null, [], ['plattform' => $p]);
        return null;
    }

    /** Schlüssel holen bzw. erneuern. @return ?string Fehler */
    private static function tokenAnfrage(string $p, array $felder): ?string
    {
        $e = self::einstellungen($p);
        $g = self::geheim($p);
        if ($p === 'tiktok') { $felder += ['client_key' => $e['client_id'], 'client_secret' => (string) ($g['secret'] ?? '')]; }
        else { $felder += ['client_id' => $e['client_id'], 'client_secret' => (string) ($g['secret'] ?? '')]; }
        $r = self::http('POST', self::OAUTH[$p]['token'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query($felder));
        $j = (array) ($r['json'] ?? []);
        $access = (string) ($j['access_token'] ?? '');
        if ($r['status'] !== 200 || $access === '') {
            return self::ALLE[$p][0] . ': ' . mb_substr((string) ($j['error_description'] ?? $j['error']['message'] ?? $j['message'] ?? $j['error'] ?? ('HTTP ' . $r['status'])), 0, 200);
        }
        $neu = ['access' => $access, 'laeuft_ab' => time() + max(60, (int) ($j['expires_in'] ?? 3600)) - 120];
        if (!empty($j['refresh_token'])) { $neu['refresh'] = (string) $j['refresh_token']; }   // TikTok gibt ggf. einen neuen — immer den neuen merken
        if ($p === 'tiktok' && !empty($j['open_id'])) { self::setzen('pf_tiktok_konto', preg_replace('~[^A-Za-z0-9._\-]~', '', (string) $j['open_id']) ?? ''); }
        self::geheimSetzen($p, $neu);
        return null;
    }

    /** Ein gültiger Zugangsschlüssel (erneuert, wenn nötig). */
    private static function zugang(string $p): ?string
    {
        $g = self::geheim($p);
        if (!empty($g['access']) && (int) ($g['laeuft_ab'] ?? 0) > time()) { return (string) $g['access']; }
        if (empty($g['refresh'])) { return null; }
        if (self::tokenAnfrage($p, ['grant_type' => 'refresh_token', 'refresh_token' => (string) $g['refresh']]) !== null) { return null; }
        return (string) (self::geheim($p)['access'] ?? '') ?: null;
    }

    /** Cron: Schlüssel frisch halten (TikTok gilt 24 h, LinkedIn 60 Tage). @return array<string,string> */
    public static function auffrischen(): array
    {
        $aus = [];
        foreach (array_keys(self::ALLE) as $p) {
            $g = self::geheim($p);
            if (empty($g['refresh'])) { continue; }
            if ((int) ($g['laeuft_ab'] ?? 0) > time() + 6 * 3600) { continue; }
            $f = self::tokenAnfrage($p, ['grant_type' => 'refresh_token', 'refresh_token' => (string) $g['refresh']]);
            $aus[$p] = $f ?? 'ok';
            if ($f !== null) {
                try { Events::melden('plattform_schluessel', self::ALLE[$p][0] . ': Verbindung abgelaufen', 'warnung', $f . ' — unter Kanäle verbinden neu verbinden.', 'kanaele#voll'); } catch (Throwable $e) { }
            }
        }
        return $aus;
    }

    /* ------------------------------------------------------------------ */
    /* Posten                                                              */
    /* ------------------------------------------------------------------ */

    /** Geht dieses Stück auf seiner Plattform automatisch? @return array{auto:bool, grund:string, medium:?array} */
    public static function moeglich(array $x, ?array $bild, ?array $video): array
    {
        $p = (string) $x['plattform'];
        $nein = static fn(string $g) => ['auto' => false, 'grund' => $g, 'medium' => null];
        /* TikTok geht nie von selbst: Jedes Video braucht die Bestätigungsseite (TikTok Content
           Sharing Guidelines). Zur Sendezeit kommt es deshalb aufs Handy — mit Knopf zu dieser Seite. */
        if ($p === 'tiktok') { return $nein(self::einstellungen('tiktok')['verbunden'] ? 'TikTok: jedes Video bestätigst du auf der Seite „Auf TikTok veröffentlichen“ — Sichtbarkeit, Kommentare, Kennzeichnung.' : ''); }
        if (!isset(self::ALLE[$p]) || !self::bereit($p)) { return $nein(''); }
        [$name, $formate, $braucht] = self::ALLE[$p];
        if (!in_array($x['format'], $formate, true)) { return $nein((MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) . ' auf ' . $name . ' kommt per Handy.'); }
        if ($braucht === 'video') { return $video ? ['auto' => true, 'grund' => '', 'medium' => $video] : $nein('Für ' . $name . ' erst ein Video erzeugen und wählen.'); }
        return ['auto' => true, 'grund' => '', 'medium' => $bild];
    }

    /** @return array{ok:bool, grund?:string, ids?:array} */
    public static function posten(array $x, ?array $medium): array
    {
        $p = (string) $x['plattform'];
        $t = self::zugang($p);
        if ($t === null) { return ['ok' => false, 'grund' => self::ALLE[$p][0] . ': Verbindung abgelaufen — unter Kanäle verbinden neu verbinden.']; }
        return match ($p) {
            'linkedin' => self::linkedin($x, $medium, $t),
            'google'   => self::google($x, $medium, $t),
            'youtube'  => self::youtube($x, (array) $medium, $t),
            'tiktok'   => ['ok' => false, 'grund' => 'TikTok geht nur über die Bestätigungsseite (Auf TikTok veröffentlichen).'],
            default    => ['ok' => false, 'grund' => 'Unbekannte Plattform.'],
        };
    }

    private static function datei(array $m): ?string
    {
        require_once __DIR__ . '/MkMedium.php';
        $pfad = MkMedium::ordner() . '/' . basename((string) $m['datei']);
        return is_file($pfad) ? $pfad : null;
    }

    private static function kurz(string $t, int $max): string
    {
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
    }

    private static function linkedin(array $x, ?array $medium, string $t): array
    {
        $org = 'urn:li:organization:' . self::einstellungen('linkedin')['konto'];
        $kopf = ['Authorization: Bearer ' . $t, 'LinkedIn-Version: ' . self::LINKEDIN_VERSION, 'X-Restli-Protocol-Version: 2.0.0', 'Content-Type: application/json'];
        $post = ['author' => $org, 'commentary' => self::kurz(MkInhalt::kopiertext($x), 3000), 'visibility' => 'PUBLIC',
                 'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []],
                 'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false];
        if ($medium && $medium['art'] === 'bild' && ($pfad = self::datei($medium)) !== null) {
            $i = self::http('POST', 'https://api.linkedin.com/rest/images?action=initializeUpload', $kopf, ['initializeUploadRequest' => ['owner' => $org]]);
            $url = (string) ($i['json']['value']['uploadUrl'] ?? ''); $bild = (string) ($i['json']['value']['image'] ?? '');
            if ($url === '' || $bild === '') { return ['ok' => false, 'grund' => 'LinkedIn: Bild nicht angenommen (' . self::fehler($i) . ').']; }
            $u = self::http('PUT', $url, ['Authorization: Bearer ' . $t, 'Content-Type: ' . (string) ($medium['mime'] ?: 'image/jpeg')], ['@datei' => $pfad]);
            if ($u['status'] >= 300) { return ['ok' => false, 'grund' => 'LinkedIn: Bild-Upload HTTP ' . $u['status'] . '.']; }
            $post['content'] = ['media' => ['id' => $bild, 'altText' => self::kurz((string) $x['titel'], 120)]];
        }
        $r = self::http('POST', 'https://api.linkedin.com/rest/posts', $kopf, $post);
        $id = (string) ($r['kopf']['x-restli-id'] ?? '');
        return $r['status'] === 201 ? ['ok' => true, 'ids' => ['li' => $id]] : ['ok' => false, 'grund' => 'LinkedIn: ' . self::fehler($r)];
    }

    private static function google(array $x, ?array $medium, string $t): array
    {
        [$konto, $ort] = array_pad(explode('/', self::einstellungen('google')['konto'], 2), 2, '');
        if ($konto === '' || $ort === '') { return ['ok' => false, 'grund' => 'Google: accountId/locationId fehlt.']; }
        $f = $x['f'];
        $text = self::kurz(trim((string) ($f['text'] ?? '')) ?: MkInhalt::kopiertext($x), 1500);
        $body = ['languageCode' => (string) ($x['sprache'] ?: 'it'), 'summary' => $text, 'topicType' => 'STANDARD'];
        $link = MkInhalt::link($x);
        if ($link) { $body['callToAction'] = ['actionType' => 'LEARN_MORE', 'url' => $link]; }
        if ($medium && $medium['art'] === 'bild') {
            require_once __DIR__ . '/MkVeroeffentlichen.php';
            $body['media'] = [['mediaFormat' => 'PHOTO', 'sourceUrl' => MkVeroeffentlichen::oeffentlich($medium) . '&f=jpg']];
        }
        $r = self::http('POST', 'https://mybusiness.googleapis.com/v4/accounts/' . rawurlencode($konto) . '/locations/' . rawurlencode($ort) . '/localPosts',
            ['Authorization: Bearer ' . $t, 'Content-Type: application/json'], $body);
        $id = (string) ($r['json']['name'] ?? '');
        return $r['status'] === 200 && $id !== '' ? ['ok' => true, 'ids' => ['gbp' => $id]] : ['ok' => false, 'grund' => 'Google: ' . self::fehler($r)];
    }

    private static function youtube(array $x, array $m, string $t): array
    {
        $pfad = self::datei($m);
        if ($pfad === null) { return ['ok' => false, 'grund' => 'YouTube: Videodatei fehlt.']; }
        $f = $x['f'];
        $titel = self::kurz(trim((string) ($f['hook'] ?? '')) ?: (string) $x['titel'], 95);
        $meta = ['snippet' => ['title' => $titel . (str_contains(mb_strtolower($titel), '#shorts') ? '' : ' #Shorts'), 'description' => self::kurz(MkInhalt::kopiertext($x), 4900),
                               'categoryId' => '22', 'defaultLanguage' => (string) ($x['sprache'] ?: 'it')],
                 'status' => ['privacyStatus' => 'public', 'selfDeclaredMadeForKids' => false]];
        $i = self::http('POST', 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status',
            ['Authorization: Bearer ' . $t, 'Content-Type: application/json; charset=UTF-8', 'X-Upload-Content-Type: ' . ((string) $m['mime'] ?: 'video/mp4'), 'X-Upload-Content-Length: ' . filesize($pfad)], $meta);
        $ziel = (string) ($i['kopf']['location'] ?? '');
        if ($i['status'] !== 200 || $ziel === '') { return ['ok' => false, 'grund' => 'YouTube: ' . self::fehler($i)]; }
        $u = self::http('PUT', $ziel, ['Authorization: Bearer ' . $t, 'Content-Type: ' . ((string) $m['mime'] ?: 'video/mp4')], ['@datei' => $pfad]);
        $id = (string) ($u['json']['id'] ?? '');
        return in_array($u['status'], [200, 201], true) && $id !== '' ? ['ok' => true, 'ids' => ['yt' => $id]] : ['ok' => false, 'grund' => 'YouTube: ' . self::fehler($u)];
    }

    /* ------------------------------------------------------------------ */
    /* TikTok: Bestätigungsseite (02.10.2026, Uwe: „mache nun alles für    */
    /* TikTok“ → „Komplett einrichten“)                                    */
    /* ------------------------------------------------------------------ */

    /* TikTok verlangt für Direct Post je Video eine Seite, auf der der Inhaber
       selbst wählt (Content Sharing Guidelines, gelesen 02.10.2026): Kontoname,
       frische creator_info, Sichtbarkeit OHNE Vorauswahl nur aus den erlaubten
       Stufen, Kommentar/Duett/Stitch ohne Haken und gesperrt, wo das Konto sie
       abgeschaltet hat, Werbekennzeichnung aus mit „Your brand“/„Branded
       content“, Markenpartnerschaft nie privat, der Satz zur Music Usage
       Confirmation, Vorschau, Höchstdauer, und erst der Klick schickt. Vorher
       wählte der Code die Sichtbarkeit selbst (öffentlich, sonst die erste)
       und schaltete alles frei — damit wäre jeder Antrag abgelehnt worden. */

    public const TT_STUFEN = ['PUBLIC_TO_EVERYONE' => 'Alle', 'MUTUAL_FOLLOW_FRIENDS' => 'Freunde (gegenseitig gefolgt)',
                              'FOLLOWER_OF_CREATOR' => 'Follower', 'SELF_ONLY' => 'Nur ich'];
    public const TT_MUSIK = 'https://www.tiktok.com/legal/page/global/music-usage-confirmation/en';
    public const TT_MARKE = 'https://www.tiktok.com/legal/page/global/bc-policy/en';

    /** Der Satz unter dem Knopf — wörtlich, wie TikTok ihn verlangt. */
    public static function ttErklaerung(bool $markenpartner): string
    {
        return $markenpartner ? "By posting, you agree to TikTok's Branded Content Policy and Music Usage Confirmation"
                              : "By posting, you agree to TikTok's Music Usage Confirmation";
    }

    /**
     * Frische creator_info des verbundenen Kontos (jedes Mal neu, wie verlangt).
     * @return array{ok:bool, fehler:string, nickname:string, nutzer:string, bild:string, stufen:list<string>,
     *               kommentar_aus:bool, duett_aus:bool, stitch_aus:bool, max_sek:int}
     */
    public static function ttKonto(): array
    {
        $leer = ['ok' => false, 'fehler' => '', 'nickname' => '', 'nutzer' => '', 'bild' => '', 'stufen' => [],
                 'kommentar_aus' => false, 'duett_aus' => false, 'stitch_aus' => false, 'max_sek' => 0];
        $t = self::zugang('tiktok');
        if ($t === null) { return ['fehler' => 'TikTok ist nicht verbunden — unter Kanäle › Verbinden & Posten verbinden.'] + $leer; }
        $c = self::http('POST', 'https://open.tiktokapis.com/v2/post/publish/creator_info/query/',
            ['Authorization: Bearer ' . $t, 'Content-Type: application/json; charset=UTF-8'], []);
        $d = (array) ($c['json']['data'] ?? []);
        $code = (string) ($c['json']['error']['code'] ?? '');
        if ($c['status'] !== 200 || ($code !== '' && $code !== 'ok')) {
            /* „spam_risk_too_many_posts“ u. ä.: Das Konto darf gerade nicht posten — dann wird nicht gesendet. */
            return ['fehler' => 'TikTok lässt gerade nicht posten: ' . self::fehler($c) . ' — später noch einmal versuchen.'] + $leer;
        }
        $stufen = array_values(array_filter(array_map('strval', (array) ($d['privacy_level_options'] ?? [])), static fn($s) => isset(self::TT_STUFEN[$s])));
        if ($stufen === []) { return ['fehler' => 'TikTok nennt keine erlaubte Sichtbarkeit — später noch einmal versuchen.'] + $leer; }
        return ['ok' => true, 'fehler' => '', 'nickname' => mb_substr((string) ($d['creator_nickname'] ?? ''), 0, 80), 'nutzer' => mb_substr((string) ($d['creator_username'] ?? ''), 0, 80),
                'bild' => (string) ($d['creator_avatar_url'] ?? ''), 'stufen' => $stufen,
                'kommentar_aus' => !empty($d['comment_disabled']), 'duett_aus' => !empty($d['duet_disabled']), 'stitch_aus' => !empty($d['stitch_disabled']),
                'max_sek' => (int) ($d['max_video_post_duration_sec'] ?? 0)];
    }

    /** Länge eines MP4 in Sekunden aus dem mvhd-Kasten (ohne ffprobe, das es auf dem Webspace nicht gibt). */
    public static function mp4Dauer(string $pfad): ?float
    {
        $f = @fopen($pfad, 'rb');
        if ($f === false) { return null; }
        $kopf = (string) fread($f, 4 * 1024 * 1024);   // moov steht bei Kie- und ffmpeg-Dateien vorn; sonst am Ende suchen
        if (!str_contains($kopf, 'mvhd')) { $groesse = (int) filesize($pfad); fseek($f, max(0, $groesse - 4 * 1024 * 1024)); $kopf = (string) fread($f, 4 * 1024 * 1024); }
        fclose($f);
        $i = strpos($kopf, 'mvhd');
        if ($i === false || strlen($kopf) < $i + 40) { return null; }
        $version = ord($kopf[$i + 4]);
        if ($version === 1) {
            $skala = unpack('N', substr($kopf, $i + 24, 4))[1]; $dauer = unpack('J', substr($kopf, $i + 28, 8))[1];
        } else {
            $skala = unpack('N', substr($kopf, $i + 16, 4))[1]; $dauer = unpack('N', substr($kopf, $i + 20, 4))[1];
        }
        return $skala > 0 ? round($dauer / $skala, 2) : null;
    }

    /**
     * Prüft die Auswahl der Bestätigungsseite gegen die frische creator_info.
     * @return ?string Fehler — null heißt: darf gesendet werden
     */
    public static function ttPruefen(array $konto, array $w, ?float $dauer): ?string
    {
        if (!$konto['ok']) { return $konto['fehler']; }
        $titel = trim((string) ($w['titel'] ?? ''));
        if ($titel === '') { return 'Bitte einen Text für das Video eingeben.'; }
        if (mb_strlen($titel) > 2200) { return 'Der Text ist länger als 2.200 Zeichen.'; }
        $stufe = (string) ($w['sichtbarkeit'] ?? '');
        if ($stufe === '') { return 'Bitte wählen, wer das Video sehen darf.'; }
        if (!in_array($stufe, $konto['stufen'], true)) { return 'Diese Sichtbarkeit erlaubt TikTok für das Konto gerade nicht.'; }
        $werbung = !empty($w['werbung']);
        $eigen = $werbung && !empty($w['eigene_marke']);
        $partner = $werbung && !empty($w['markenpartner']);
        if ($werbung && !$eigen && !$partner) { return 'You need to indicate if your content promotes yourself, a third party, or both'; }
        if ($partner && $stufe === 'SELF_ONLY') { return 'Branded content visibility cannot be set to private'; }
        if ($konto['max_sek'] > 0 && $dauer !== null && $dauer > $konto['max_sek']) {
            return 'Das Video ist ' . (int) ceil($dauer) . ' Sekunden lang; dieses Konto darf höchstens ' . $konto['max_sek'] . ' Sekunden posten.';
        }
        if (empty($w['zustimmung'])) { return 'Bitte bestätigen, dass das Video jetzt an TikTok gehen soll.'; }
        return null;
    }

    /**
     * Sendet ein Video nach der Bestätigungsseite: Direct Post (Modus „posten“)
     * oder als Entwurf in die TikTok-App (Modus „entwurf“, der Inhaber postet
     * dort selbst). @return array{ok:bool, grund?:string, ids?:array}
     */
    public static function ttSenden(array $x, array $m, array $w, string $modus = 'posten'): array
    {
        $pfad = self::datei($m);
        if ($pfad === null) { return ['ok' => false, 'grund' => 'TikTok: Videodatei fehlt.']; }
        $groesse = (int) filesize($pfad);
        if ($groesse > 64 * 1024 * 1024) { return ['ok' => false, 'grund' => 'TikTok: Video größer als 64 MB.']; }
        $t = self::zugang('tiktok');
        if ($t === null) { return ['ok' => false, 'grund' => 'TikTok: Verbindung abgelaufen — unter Kanäle verbinden neu verbinden.']; }
        $kopf = ['Authorization: Bearer ' . $t, 'Content-Type: application/json; charset=UTF-8'];
        $quelle = ['source' => 'FILE_UPLOAD', 'video_size' => $groesse, 'chunk_size' => $groesse, 'total_chunk_count' => 1];
        if ($modus === 'entwurf') {
            $i = self::http('POST', 'https://open.tiktokapis.com/v2/post/publish/inbox/video/init/', $kopf, ['source_info' => $quelle]);
            $stufe = '';
        } else {
            $konto = self::ttKonto();
            $f = self::ttPruefen($konto, $w, self::mp4Dauer($pfad));
            if ($f !== null) { return ['ok' => false, 'grund' => $f]; }
            $stufe = (string) $w['sichtbarkeit'];
            $werbung = !empty($w['werbung']);
            $info = ['title' => mb_substr(trim((string) $w['titel']), 0, 2200), 'privacy_level' => $stufe,
                     /* Was das Konto abgeschaltet hat, bleibt aus — auch wenn jemand das Formular umgeht. */
                     'disable_comment' => $konto['kommentar_aus'] || empty($w['kommentare']),
                     'disable_duet' => $konto['duett_aus'] || empty($w['duett']),
                     'disable_stitch' => $konto['stitch_aus'] || empty($w['stitch']),
                     'brand_organic_toggle' => $werbung && !empty($w['eigene_marke']),
                     'brand_content_toggle' => $werbung && !empty($w['markenpartner']),
                     'is_aigc' => !empty($w['ki'])];
            $i = self::http('POST', 'https://open.tiktokapis.com/v2/post/publish/video/init/', $kopf, ['post_info' => $info, 'source_info' => $quelle]);
        }
        $url = (string) ($i['json']['data']['upload_url'] ?? ''); $pub = (string) ($i['json']['data']['publish_id'] ?? '');
        if ($url === '' || $pub === '') { return ['ok' => false, 'grund' => 'TikTok: ' . self::fehler($i)]; }
        $u = self::http('PUT', $url, ['Content-Type: ' . ((string) $m['mime'] ?: 'video/mp4'), 'Content-Range: bytes 0-' . ($groesse - 1) . '/' . $groesse], ['@datei' => $pfad]);
        if ($u['status'] >= 300) { return ['ok' => false, 'grund' => 'TikTok: Upload HTTP ' . $u['status'] . '.']; }
        $ids = ['tt' => $pub, 'tt_modus' => $modus];
        if ($stufe !== '' && $stufe !== 'PUBLIC_TO_EVERYONE') { $ids['tt_sichtbar'] = $stufe; }
        return ['ok' => true, 'ids' => $ids];
    }

    /**
     * Nach dem Senden: veröffentlicht (Direct Post) bzw. als Entwurf in der App
     * vermerkt — dann bleibt das Stück freigegeben, bis Uwe „Gepostet“ drückt.
     * @return string Meldung für die Seite
     */
    public static function ttAbschliessen(int $id, array $erg, string $modus): string
    {
        $x = MkInhalt::laden($id);
        $ids = array_merge(json_decode((string) ($x['post_ids'] ?? ''), true) ?: [], (array) ($erg['ids'] ?? []));
        if (empty($erg['ok'])) {
            Db::update('mk_inhalte', $id, ['post_fehler' => mb_substr((string) ($erg['grund'] ?? 'unbekannt'), 0, 300)]);
            return (string) ($erg['grund'] ?? 'Nicht gesendet.');
        }
        if ($modus === 'entwurf') {
            Db::update('mk_inhalte', $id, ['post_ids' => json_encode($ids), 'geplant_am' => null, 'post_fehler' => null]);
            Events::pruefspur('tiktok_entwurf', 'mk_inhalte', $id, [], ['publish_id' => $ids['tt'] ?? '']);
            return 'An die TikTok-App geschickt — dort in der Benachrichtigung öffnen und posten. Danach hier „Selbst gepostet“ drücken.';
        }
        Db::update('mk_inhalte', $id, ['status' => 'veroeffentlicht', 'veroeffentlicht_am' => date('Y-m-d H:i:s'), 'geplant_am' => null,
                                       'post_ids' => json_encode($ids), 'post_fehler' => null]);
        Events::pruefspur('inhalt_gepostet', 'mk_inhalte', $id, ['status' => 'freigegeben'], ['status' => 'veroeffentlicht', 'plattform' => 'tiktok'] + $ids);
        return 'An TikTok gesendet. Es kann einige Minuten dauern, bis das Video im Profil sichtbar ist.';
    }

    /** Stand eines gesendeten Videos (publish/status/fetch). @return array{status:string, text:string, fertig:bool} */
    public static function ttStand(string $publishId): array
    {
        $t = self::zugang('tiktok');
        if ($t === null || $publishId === '') { return ['status' => '', 'text' => 'Stand nicht abrufbar.', 'fertig' => false]; }
        $r = self::http('POST', 'https://open.tiktokapis.com/v2/post/publish/status/fetch/',
            ['Authorization: Bearer ' . $t, 'Content-Type: application/json; charset=UTF-8'], ['publish_id' => $publishId]);
        $s = (string) ($r['json']['data']['status'] ?? '');
        $text = match ($s) {
            'PROCESSING_UPLOAD', 'PROCESSING_DOWNLOAD' => 'TikTok verarbeitet das Video …',
            'SEND_TO_USER_INBOX' => 'Liegt als Entwurf in der TikTok-App — dort auf „Posten“ tippen.',
            'PUBLISH_COMPLETE' => 'Auf TikTok veröffentlicht.',
            'FAILED' => 'Fehlgeschlagen: ' . mb_substr((string) ($r['json']['data']['fail_reason'] ?? 'ohne Grund'), 0, 160),
            default => 'Stand unbekannt (' . self::fehler($r) . ').',
        };
        return ['status' => $s, 'text' => $text, 'fertig' => in_array($s, ['PUBLISH_COMPLETE', 'FAILED', 'SEND_TO_USER_INBOX'], true)];
    }

    public static function fehler(array $r): string
    {
        $j = (array) ($r['json'] ?? []);
        return mb_substr((string) ($j['error']['message'] ?? $j['message'] ?? $j['error_description'] ?? (is_string($j['error'] ?? null) ? $j['error'] : '') ?: ('HTTP ' . $r['status'])), 0, 200);
    }

    /** Ein Aufruf. Body: Array → JSON; ['@datei' => Pfad] → Rohdaten der Datei; String → so wie er ist. */
    private static function http(string $methode, string $url, array $kopf, string|array|null $body): array
    {
        if (self::$netz) { return (self::$netz)($methode, $url, $kopf, $body); }
        $ch = curl_init($url);
        $antwortKopf = [];
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_HTTPHEADER => $kopf,
            CURLOPT_HEADERFUNCTION => static function ($c, string $zeile) use (&$antwortKopf): int {
                $teile = explode(':', $zeile, 2);
                if (count($teile) === 2) { $antwortKopf[strtolower(trim($teile[0]))] = trim($teile[1]); }
                return strlen($zeile);
            }]);
        if (is_array($body) && isset($body['@datei'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, (string) file_get_contents((string) $body['@datei'])); }
        elseif (is_array($body)) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body === [] ? new stdClass() : $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
        elseif (is_string($body)) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'json' => is_string($roh) ? (json_decode($roh, true) ?: null) : null, 'kopf' => $antwortKopf];
    }
}
