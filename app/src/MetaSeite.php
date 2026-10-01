<?php
declare(strict_types=1);
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';

/**
 * Facebook-Seite und Instagram (28.09.2026, Uwe: Ja zu Z4 und Z5).
 *
 * Z4 BEITRÄGE
 * Zweimal die Woche (Montag und Donnerstag) legt das System einen Beitrag als
 * ENTWURF an: ein Thema aus den zwölf Punkten des Kurz-Checks, ein Bild
 * 1080×1080 mit dem Satz darauf, darunter der Link auf die kostenlose
 * Analyse. Nichts geht von selbst raus: Uwe drückt „Freigeben & posten“,
 * dann postet das System auf die Facebook-Seite und auf Instagram.
 *
 * Z5 WERBEFORMULAR (Lead Ads)
 * Meta meldet ein ausgefülltes Formular an wa-webhook.php (Objekt „page“,
 * Feld „leadgen“). Hier wird es abgeholt: Website, E-Mail, WhatsApp und das
 * Häkchen des Einwilligungskästchens. Mit Häkchen geht genau das raus, was
 * auch analisi.php auslöst -- nur die Bestätigungsmail. Ohne Häkchen wird
 * nichts gespeichert außer dem Hinweis, dass das Formular falsch gebaut ist.
 *
 * GEHEIMES
 * Der Seiten-Schlüssel liegt verschlüsselt in settings (Hosting::versiegeln).
 */
final class MetaSeite
{
    public const API = 'https://graph.facebook.com/v21.0';
    /** @var null|callable(string $methode, string $url, ?array $body, string $token): array{status:int, json:?array} Für die Kette. */
    public static $netz = null;

    /** Themen: die zwölf Punkte des Kurz-Checks und ein allgemeiner. Titel = Satz auf dem Bild. */
    public const THEMEN = [
        'tempo' => [
            'it' => ['Il suo sito si apre in fretta sul telefono?', "Chi cerca un ristorante, un hotel o un negozio dal telefono non aspetta a lungo: se la pagina tarda, torna indietro e sceglie il risultato successivo.\n\nVuole sapere com’è messo il suo sito? Lo verifichi gratis in pochi secondi, senza registrazione:"],
            'de' => ['Öffnet sich Ihre Website schnell auf dem Handy?', "Wer vom Handy aus ein Restaurant, ein Hotel oder ein Geschäft sucht, wartet nicht lange: Lädt die Seite zu langsam, geht es zurück zum nächsten Ergebnis.\n\nWie steht Ihre Website da? Prüfen Sie sie kostenlos in wenigen Sekunden, ohne Anmeldung:"]],
        'sicher' => [
            'it' => ['«Non sicuro» accanto al suo indirizzo?', "Se il browser scrive «Non sicuro» accanto al sito, molti visitatori non compilano il modulo e non prenotano. La soluzione è un collegamento cifrato (https): di solito è una modifica piccola.\n\nControlli gratis il suo sito:"],
            'de' => ['„Nicht sicher“ neben Ihrer Adresse?', "Steht im Browser „Nicht sicher“ neben der Website, füllen viele Besucher kein Formular aus und buchen nicht. Abhilfe schafft eine verschlüsselte Verbindung (https) – meist eine kleine Änderung.\n\nPrüfen Sie Ihre Website kostenlos:"]],
        'handy' => [
            'it' => ['Sul telefono il suo sito si legge bene?', "Oggi la maggior parte delle persone guarda un sito dal telefono. Se testo e pulsanti appaiono minuscoli, bisogna allargare con le dita, e molti rinunciano.\n\nVeda in pochi secondi come appare il suo sito:"],
            'de' => ['Lässt sich Ihre Website auf dem Handy gut lesen?', "Heute schauen die meisten Menschen eine Website auf dem Handy an. Erscheinen Text und Knöpfe winzig, muss man mit den Fingern vergrößern – und viele geben auf.\n\nSehen Sie in wenigen Sekunden, wie Ihre Website dasteht:"]],
        'google' => [
            'it' => ['Come la presenta Google?', "Su Google ogni sito appare con un titolo e due righe di descrizione. Se mancano, Google mostra un pezzo di testo a caso, e il suo sito si nota meno degli altri.\n\nVerifichi gratis titolo e descrizione del suo sito:"],
            'de' => ['Wie stellt Google Ihre Website vor?', "Bei Google erscheint jede Website mit einem Titel und zwei Zeilen Beschreibung. Fehlen sie, zeigt Google irgendeinen Textschnipsel – und Ihre Seite fällt weniger auf als die anderen.\n\nPrüfen Sie Titel und Beschreibung Ihrer Website kostenlos:"]],
        'aktuell' => [
            'it' => ['In fondo alla pagina c’è un anno vecchio?', "Un anno vecchio nel piè di pagina fa pensare che il sito, e magari anche orari e prezzi, non siano aggiornati. È un dettaglio che i visitatori notano.\n\nControlli gratis il suo sito in pochi secondi:"],
            'de' => ['Steht unten auf der Seite eine alte Jahreszahl?', "Eine alte Jahreszahl in der Fußzeile lässt vermuten, dass die Website – und vielleicht auch Öffnungszeiten und Preise – nicht aktuell ist. Besucher bemerken so etwas.\n\nPrüfen Sie Ihre Website kostenlos in wenigen Sekunden:"]],
        'teilen' => [
            'it' => ['Condiviso su WhatsApp, appare un’immagine?', "Quando un cliente manda il suo sito a un amico su WhatsApp o Facebook, un’anteprima con immagine invita a toccare. Senza, resta solo un link grigio.\n\nVeda gratis se il suo sito ha l’anteprima:"],
            'de' => ['Erscheint beim Teilen in WhatsApp ein Bild?', "Schickt ein Kunde Ihre Website per WhatsApp oder Facebook weiter, lädt eine Vorschau mit Bild zum Antippen ein. Ohne bleibt nur ein grauer Link.\n\nSehen Sie kostenlos, ob Ihre Website eine Vorschau hat:"]],
        'allgemein' => [
            'it' => ['Analisi gratuita del suo sito', "Velocità, sicurezza, telefono, Google, orari, immagini e altro: dodici punti con semaforo, in pochi secondi. Gratis, senza registrazione. Se vuole, poi le mandiamo l’analisi completa.\n\nProvi qui:"],
            'de' => ['Kostenlose Analyse Ihrer Website', "Ladezeit, Sicherheit, Handy, Google, Öffnungszeiten, Bilder und mehr: zwölf Punkte als Ampel, in wenigen Sekunden. Kostenlos, ohne Anmeldung. Wenn Sie möchten, schicken wir Ihnen danach die ausführliche Analyse.\n\nHier ausprobieren:"]],
    ];
    public const TAGE = [1, 4];   // Montag, Donnerstag

    /* ------------------------------ Einstellungen ------------------------ */

    public static function einstellungen(): array
    {
        return [
            'seite_id' => AkquiseGate::einstellung('meta_seite_id', ''),
            'ig_id' => AkquiseGate::einstellung('meta_ig_id', ''),
            'token' => self::geheim() !== '',
            'sprache' => AkquiseGate::einstellung('meta_sprache', 'beide'),   // beide | it | de (D1, 28.09.2026)
        ];
    }

    public static function bereit(): bool
    {
        $e = self::einstellungen();
        return $e['seite_id'] !== '' && $e['token'];
    }

    public static function speichern(array $d): void
    {
        foreach (['meta_seite_id' => 'seite_id', 'meta_ig_id' => 'ig_id'] as $k => $feld) {
            $v = preg_replace('~\D~', '', (string) ($d[$feld] ?? '')) ?? '';
            if ($v !== '' || !empty($d['leeren'])) { AkquiseGate::setzen($k, mb_substr($v, 0, 30)); }
        }
        if (isset($d['sprache']) && in_array($d['sprache'], ['beide', 'it', 'de'], true)) { AkquiseGate::setzen('meta_sprache', (string) $d['sprache']); }
        $t = trim((string) ($d['token'] ?? ''));
        if ($t !== '') {
            require_once __DIR__ . '/Hosting.php';
            $blob = (string) Hosting::versiegeln(['wert' => $t]);
            if ($blob === '') { throw new RuntimeException('Der Schlüssel ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).'); }
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['meta_seiten_token', $blob]);
        }
    }

    private static function geheim(): string
    {
        $blob = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', ['meta_seiten_token'], '');
        if ($blob === '') { return ''; }
        require_once __DIR__ . '/Hosting.php';
        return (string) (Hosting::entsiegeln($blob)['wert'] ?? '');
    }

    /** @return array{status:int, json:?array} */
    private static function anfrage(string $methode, string $url, ?array $body = null): array
    {
        $token = self::geheim();
        if (self::$netz) { return (self::$netz)($methode, $url, $body, $token); }
        if ($token === '') { return ['status' => 0, 'json' => ['error' => ['message' => 'Kein Seiten-Schlüssel hinterlegt.']]]; }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $methode,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'json' => is_string($roh) ? (json_decode($roh, true) ?: null) : null];
    }

    private static function fehlerText(array $r): string
    {
        return mb_substr((string) ($r['json']['error']['message'] ?? ('HTTP ' . $r['status'])), 0, 200);
    }

    /** Für das Content-Studio (01.10.2026): ein Graph-Aufruf mit dem Seiten-Schlüssel — der Schlüssel bleibt hier. */
    public static function graph(string $methode, string $pfad, ?array $body = null): array
    {
        return self::anfrage($methode, self::API . '/' . ltrim($pfad, '/'), $body);
    }

    public static function fehler(array $r): string { return self::fehlerText($r); }

    /* ------------------------------ Z4 Beiträge -------------------------- */

    public static function analyseLink(string $sprache): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/analisi.php?lang=' . $sprache;
    }

    /** Welches Thema ist dran: das, das am längsten nicht vorkam. */
    public static function naechstesThema(string $sprache = 'it'): string
    {
        $zuletzt = [];
        foreach (Db::all('SELECT code, MAX(created_at) AS am FROM akq_beitraege WHERE sprache = ? GROUP BY code', [$sprache]) as $z) { $zuletzt[(string) $z['code']] = (string) $z['am']; }
        $wahl = null; $alt = null;
        foreach (array_keys(self::THEMEN) as $c) {
            $am = $zuletzt[$c] ?? '';
            if ($wahl === null || $am < $alt) { $wahl = $c; $alt = $am; }
        }
        return (string) $wahl;
    }

    /** Einen Entwurf anlegen. @return int id */
    public static function entwurf(?string $code = null, ?string $sprache = null): int
    {
        $sprache = in_array($sprache, ['it', 'de'], true) ? $sprache : self::spracheAm();
        $code = isset(self::THEMEN[(string) $code]) ? (string) $code : self::naechstesThema($sprache);
        [$titel, $text] = self::THEMEN[$code][$sprache];
        return Db::insert('akq_beitraege', ['code' => $code, 'sprache' => $sprache, 'titel' => $titel,
            'text' => $text . "\n" . self::analyseLink($sprache) . "\n\n" . ($sprache === 'de' ? '#website #kleinunternehmen #vecomdesign' : '#sitoweb #piccoleimprese #vecomdesign'),
            'token' => bin2hex(random_bytes(16)), 'status' => 'entwurf']);
    }

    /**
     * Welche Sprache ein Beitrag bekommt (D1, 28.09.2026, Uwe: „Beiträge IT + DE“):
     * bei „beide“ montags Italienisch, donnerstags Deutsch -- Italien und
     * Deutschland bekommen je einen Beitrag die Woche. Sonst die feste Sprache.
     */
    public static function spracheAm(?int $jetzt = null): string
    {
        $s = self::einstellungen()['sprache'];
        if (in_array($s, ['it', 'de'], true)) { return $s; }
        return (int) date('N', $jetzt ?? time()) === 4 ? 'de' : 'it';
    }

    /** Sprache einer Formular-Meldung: fest eingestellt, sonst nach der Endung der Website. */
    public static function leadSprache(string $url): string
    {
        $s = self::einstellungen()['sprache'];
        if (in_array($s, ['it', 'de'], true)) { return $s; }
        $host = (string) (parse_url(str_contains($url, '://') ? $url : 'https://' . $url, PHP_URL_HOST) ?? '');
        return preg_match('~\.(de|at|ch)$~i', $host) ? 'de' : 'it';
    }

    /** Montag und Donnerstag je ein Entwurf, nie zwei offene Entwürfe desselben Tages. */
    public static function planen(?int $jetzt = null): ?int
    {
        $jetzt ??= time();
        if (!in_array((int) date('N', $jetzt), self::TAGE, true)) { return null; }
        if (AkquiseGate::einstellung('meta_geplant_am', '') === date('Y-m-d', $jetzt)) { return null; }
        AkquiseGate::setzen('meta_geplant_am', date('Y-m-d', $jetzt));
        $offen = (int) Db::wert("SELECT COUNT(*) FROM akq_beitraege WHERE status = 'entwurf'", [], 0);
        if ($offen >= 4) { return null; }   // Uwe kommt nicht nach -- nicht weiter stapeln
        $id = self::entwurf(null, self::spracheAm($jetzt));
        try { Events::melden('akquise_beitrag', 'Neuer Beitrag für Facebook/Instagram wartet auf dein Freigeben', 'info', null, 'akquise/beitraege'); } catch (Throwable $e) { }
        return $id;
    }

    public static function bildAdresse(array $b): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/beitrag.php?t=' . $b['token'];
    }

    /** Das Bild 1080×1080 als PNG: dunkel, goldene Linie, der Satz groß. */
    public static function bild(array $b): string
    {
        $g = 1080;
        $im = imagecreatetruecolor($g, $g);
        $grund = imagecolorallocate($im, 11, 10, 9);
        $flaeche = imagecolorallocate($im, 21, 19, 15);
        $gold = imagecolorallocate($im, 241, 211, 139);
        $hell = imagecolorallocate($im, 246, 241, 231);
        $leise = imagecolorallocate($im, 183, 175, 162);
        imagefill($im, 0, 0, $grund);
        imagefilledrectangle($im, 64, 64, $g - 64, $g - 64, $flaeche);
        imagefilledrectangle($im, 64, 64, $g - 64, 76, $gold);
        $schrift = dirname(__DIR__) . '/schrift/archivo-semibold.ttf';
        imagettftext($im, 26, 0, 120, 170, $gold, $schrift, 'VECOM DESIGN');
        /* Satz umbrechen: so groß wie möglich, höchstens fünf Zeilen. */
        $titel = (string) $b['titel'];
        foreach ([78, 70, 62, 56, 50] as $gr) {
            $zeilen = self::umbrechen($titel, $schrift, $gr, $g - 240);
            if (count($zeilen) <= 5) { break; }
        }
        $zh = (int) round($gr * 1.25);
        $y = (int) (560 - (count($zeilen) * $zh) / 2) + $gr;
        foreach ($zeilen as $z) { imagettftext($im, $gr, 0, 120, $y, $hell, $schrift, $z); $y += $zh; }
        $unten = ($b['sprache'] ?? 'it') === 'de' ? 'Kostenlose Analyse · vecom-design.it/analisi.php' : 'Analisi gratuita · vecom-design.it/analisi.php';
        imagefilledrectangle($im, 120, $g - 196, 220, $g - 190, $gold);
        $ug = 28;
        while ($ug > 18) { $bx = imagettfbbox($ug, 0, $schrift, $unten); if (($bx[2] - $bx[0]) <= $g - 240) { break; } $ug -= 2; }
        imagettftext($im, $ug, 0, 120, $g - 130, $leise, $schrift, $unten);
        ob_start(); imagepng($im, null, 6); $png = (string) ob_get_clean();
        imagedestroy($im);
        return $png;
    }

    /** @return list<string> */
    private static function umbrechen(string $s, string $schrift, int $gr, int $breite): array
    {
        $zeilen = []; $z = '';
        foreach (preg_split('~\s+~u', trim($s)) ?: [] as $w) {
            $probe = $z === '' ? $w : $z . ' ' . $w;
            $box = imagettfbbox($gr, 0, $schrift, $probe);
            if ($z !== '' && ($box[2] - $box[0]) > $breite) { $zeilen[] = $z; $z = $w; } else { $z = $probe; }
        }
        if ($z !== '') { $zeilen[] = $z; }
        return $zeilen;
    }

    /** Uwes Klick: posten auf Facebook und (wenn eingerichtet) Instagram. @return array{ok:bool, grund:?string} */
    public static function posten(int $id, ?string $text = null): array
    {
        $b = Db::one('SELECT * FROM akq_beitraege WHERE id = ?', [$id]);
        if (!$b || !in_array($b['status'], ['entwurf', 'fehler'], true)) { return ['ok' => false, 'grund' => 'Der Beitrag ist nicht mehr offen.']; }
        if ($text !== null && trim($text) !== '') { Db::update('akq_beitraege', $id, ['text' => mb_substr(trim($text), 0, 2000)]); $b['text'] = mb_substr(trim($text), 0, 2000); }
        if (!self::bereit()) { return ['ok' => false, 'grund' => 'Facebook-Seite ist noch nicht eingerichtet.']; }
        $e = self::einstellungen();
        $bild = self::bildAdresse($b);
        $fb = $b['fb_id'] ?: null; $ig = $b['ig_id'] ?: null; $gruende = [];
        if ($fb === null) {
            $r = self::anfrage('POST', self::API . '/' . $e['seite_id'] . '/photos', ['url' => $bild, 'caption' => (string) $b['text'], 'published' => true]);
            if ($r['status'] === 200 && !empty($r['json']['id'])) { $fb = (string) ($r['json']['post_id'] ?? $r['json']['id']); } else { $gruende[] = 'Facebook: ' . self::fehlerText($r); }
        }
        if ($ig === null && $e['ig_id'] !== '') {
            $r = self::anfrage('POST', self::API . '/' . $e['ig_id'] . '/media', ['image_url' => $bild, 'caption' => (string) $b['text']]);
            if ($r['status'] === 200 && !empty($r['json']['id'])) {
                $p = self::anfrage('POST', self::API . '/' . $e['ig_id'] . '/media_publish', ['creation_id' => (string) $r['json']['id']]);
                if ($p['status'] === 200 && !empty($p['json']['id'])) { $ig = (string) $p['json']['id']; } else { $gruende[] = 'Instagram: ' . self::fehlerText($p); }
            } else { $gruende[] = 'Instagram: ' . self::fehlerText($r); }
        }
        $ok = $gruende === [];
        Db::update('akq_beitraege', $id, ['fb_id' => $fb, 'ig_id' => $ig, 'status' => $ok ? 'gepostet' : 'fehler',
            'grund' => $ok ? null : mb_substr(implode(' · ', $gruende), 0, 250), 'gepostet_am' => $ok ? date('Y-m-d H:i:s') : null]);
        Events::pruefspur('akquise_beitrag_posten', 'akq_beitraege', $id, [], ['fb' => $fb, 'ig' => $ig, 'ok' => $ok]);
        return ['ok' => $ok, 'grund' => $ok ? null : implode(' · ', $gruende)];
    }

    /* ------------------------------ Z5 Werbeformular --------------------- */

    /** Die Seite für Formular-Meldungen anmelden (einmal, Uwes Klick). */
    public static function formulareAbonnieren(): array
    {
        if (!self::bereit()) { return ['ok' => false, 'grund' => 'Facebook-Seite ist noch nicht eingerichtet.']; }
        $r = self::anfrage('POST', self::API . '/' . self::einstellungen()['seite_id'] . '/subscribed_apps?subscribed_fields=leadgen', null);
        return !empty($r['json']['success']) ? ['ok' => true, 'grund' => null] : ['ok' => false, 'grund' => self::fehlerText($r)];
    }

    /** Aus den Feldern des Formulars das Nötige ziehen -- tolerant gegenüber den Feldnamen, die Uwe vergibt. */
    public static function felder(array $lead): array
    {
        $x = ['url' => '', 'email' => '', 'whatsapp' => '', 'betrieb' => '', 'ja' => false];
        foreach ((array) ($lead['field_data'] ?? []) as $fd) {
            $n = mb_strtolower((string) ($fd['name'] ?? ''));
            $v = trim((string) (($fd['values'] ?? [])[0] ?? ''));
            if ($v === '') { continue; }
            if ($n === 'email' || str_contains($n, 'mail')) { $x['email'] = $v; }
            elseif (in_array($n, ['phone_number', 'phone', 'telefono', 'whatsapp'], true) || str_contains($n, 'whatsapp')) { $x['whatsapp'] = $v; }
            elseif ($n === 'website' || str_contains($n, 'sito') || str_contains($n, 'web') || str_contains($n, 'url')) { $x['url'] = $v; }
            elseif (in_array($n, ['company_name', 'azienda', 'attivita', 'attività', 'betrieb'], true)) { $x['betrieb'] = $v; }
        }
        foreach ((array) ($lead['custom_disclaimer_responses'] ?? []) as $d) {
            if (!empty($d['is_checked']) && ($d['is_checked'] === true || $d['is_checked'] === 'true' || $d['is_checked'] === '1' || $d['is_checked'] === 1)) { $x['ja'] = true; }
        }
        return $x;
    }

    /** Eine Meldung aus dem Webhook. @return string ok|doppelt|fehler|ohne_haken|... */
    public static function lead(string $leadId, string $seiteId = ''): string
    {
        $leadId = preg_replace('~\D~', '', $leadId) ?? '';
        if ($leadId === '') { return 'fehler'; }
        $e = self::einstellungen();
        if ($seiteId !== '' && $e['seite_id'] !== '' && $seiteId !== $e['seite_id']) { return 'fremd'; }
        try { Db::insert('akq_meta_leads', ['lead_id' => $leadId, 'status' => 'neu']); }
        catch (Throwable $x) { if (Db::doppelt($x, 'uq_akq_meta_lead')) { return 'doppelt'; } throw $x; }
        $r = self::anfrage('GET', self::API . '/' . $leadId . '?fields=field_data,custom_disclaimer_responses,created_time,form_id', null);
        $zeile = (int) Db::wert('SELECT id FROM akq_meta_leads WHERE lead_id = ?', [$leadId], 0);
        if ($r['status'] !== 200 || !is_array($r['json'])) {
            Db::update('akq_meta_leads', $zeile, ['status' => 'fehler', 'grund' => self::fehlerText($r)]);
            return 'fehler';
        }
        $x = self::felder($r['json']);
        if (!$x['ja']) {
            Db::update('akq_meta_leads', $zeile, ['status' => 'ohne_haken', 'grund' => 'Formular ohne angehaktes Einwilligungs-Kästchen — nichts gespeichert.']);
            try { Events::melden('akquise_formular', 'Werbeformular ohne Einwilligungs-Häkchen', 'warnung', 'Bitte im Formular das Kästchen mit dem Wortlaut als Pflicht einbauen (Anleitung unter Akquise → Regeln → Wege zum Ja).', 'akquise/regeln#wege'); } catch (Throwable $y) { }
            return 'ohne_haken';
        }
        require_once __DIR__ . '/AkquiseKurz.php';
        $s = AkquiseKurz::einwilligen(['url' => $x['url'], 'betrieb' => $x['betrieb'], 'email' => $x['email'], 'whatsapp' => $x['whatsapp'] !== '' ? $x['whatsapp'] : null,
            'ja' => true, 'sprache' => self::leadSprache($x['url']), 'quelle' => 'anzeige']);
        Db::update('akq_meta_leads', $zeile, ['status' => $s, 'grund' => $s === 'ok' ? null : 'Einwilligung: ' . $s]);
        return $s;
    }

    /** Webhook-Nutzlast mit object=page. @return int verarbeitete Meldungen */
    public static function verarbeiten(array $nutzlast): int
    {
        $n = 0;
        foreach ((array) ($nutzlast['entry'] ?? []) as $eintrag) {
            foreach ((array) ($eintrag['changes'] ?? []) as $ae) {
                if (($ae['field'] ?? '') !== 'leadgen') { continue; }
                $v = (array) ($ae['value'] ?? []);
                if (self::lead((string) ($v['leadgen_id'] ?? ''), (string) ($v['page_id'] ?? '')) === 'ok') { $n++; }
            }
        }
        return $n;
    }
}
