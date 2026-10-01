<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Texte.php';

/* ==========================================================================
   Verzeichnisse.php — Telegram Growth Engine, Schritt T5: Verzeichnisse und
   Kooperationen (01.10.2026, Uwe: „ist es drin, dass sich die Seite in
   Wegweiser-Seiten eintragen lässt und sich bei Kanälen bewirbt, wo es
   erlaubt ist?“ → Plan → „ja“).

   WAS HIER PASSIERT — UND WAS NICHT

   Eine Liste der Stellen, an denen Vecom Design stehen kann: Karten (Google,
   Apple, Bing), italienische Branchenverzeichnisse, Telegram-Kataloge,
   Agentur-Verzeichnisse und Kanäle, die Kooperationen ausdrücklich anbieten.
   Zu jeder Stelle: was dort erlaubt ist (selbst nachgesehen, mit Datum und
   Quelle), was es kostet, ob ein Konto oder Captcha nötig ist, wie weit der
   Eintrag ist — und was er bringt.

   Automatisch eingetragen wird NICHTS. Fast überall braucht es ein Konto,
   oft ein Captcha und das Annehmen von Bedingungen; das bleibt bei Uwe.
   Und ein Bot kann bei Telegram niemanden von sich aus anschreiben — viele
   Admins auf einmal anzuschreiben wäre ohnehin Spam (telegram.org/faq_spam)
   und kann den eigenen Kanal kosten. Deshalb nimmt die Liste Kanäle nur
   auf, wenn bestätigt ist, dass deren Beschreibung oder Regeln Kooperationen
   erlauben; die Anfrage schickt Uwe selbst, an einen Admin nach dem anderen.

   KEINE ZWEITE ZÄHLUNG

   Jeder Eintrag bekommt beim Vorbereiten eine gewöhnliche Kampagne
   (Code vz-…). Damit zählen /k/CODE (Website), der Fenster-Link m_CODE
   (Mini-App) und der Kanal-Einladungslink genau dort, wo sie heute schon
   zählen (Spur, tg_tage, tg_einladungen) — hier wird nur zusammengelesen.
   Ehrliche Grenze: Wer einen Kanal über seinen öffentlichen Namen findet
   (so verlinken die meisten Kataloge), kommt ohne Link an und lässt sich
   keinem Katalog zuordnen.

   GELD

   Vorgeschlagen werden nur Stellen mit kostenlosem Grundeintrag. Bezahlte
   Zusätze stehen als „nicht buchen“ dabei. Diese Klasse gibt nie Geld aus.
   ========================================================================== */
final class Verzeichnisse
{
    /** Art => [Überschrift, ein Satz, wofür]. */
    public const ARTEN = [
        'karte'    => ['Karten & Suche', 'Google, Apple, Bing — dort suchen Betriebe aus der Gegend zuerst.'],
        'branche'  => ['Branchenverzeichnisse', 'Italienische Firmenverzeichnisse — überall dieselben Angaben helfen auch bei Google.'],
        'telegram' => ['Telegram-Kataloge', 'Verzeichnisse öffentlicher Kanäle — dort sucht man Kanäle zu einem Thema.'],
        'agentur'  => ['Agentur-Verzeichnisse', 'Listen von Webdesign-Studios — dort suchen Firmen einen Dienstleister.'],
        'kanal'    => ['Kanäle & Gruppen', 'Kooperation mit Kanälen, die sie ausdrücklich anbieten — deren Admin postet selbst.'],
    ];

    public const STATUS = ['offen' => 'Offen', 'eingereicht' => 'Eingereicht', 'online' => 'Online', 'abgelehnt' => 'Abgelehnt',
        'spaeter' => 'Später', 'nein' => 'Nicht eintragen'];

    public const SPRACHEN = ['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'];

    /** Nach so vielen Tagen erinnert die Liste an einen offenen oder eingereichten Eintrag. */
    public const FAELLIG_TAGE = 7;

    /** Steigt diese Fassung, kommen neue Vorschläge dazu (vorhandene bleiben, wie Uwe sie gesetzt hat). */
    public const VORSCHLAEGE_FASSUNG = '2026-10-01';

    /**
     * Geprüfte Vorschläge (am 01.10.2026 auf den Eintrags- und Regelseiten
     * selbst nachgesehen). „teilweise geprüft“ steht dabei, wo eine Seite den
     * Abruf gesperrt hat. Reihenfolge = Nutzen für ein kleines Studio in
     * Aragona und Aussicht auf Annahme mit einem noch kleinen Kanal.
     * captcha: 1 ja, 0 keins gesehen, null unbekannt.
     */
    public const VORSCHLAEGE = [
        ['google', 'karte', 'Google Unternehmensprofil', 'https://business.google.com', 'https://support.google.com/business/answer/3038177',
            'Nur, wenn du Kunden persönlich triffst (bei ihnen oder bei dir) — reine Online-Firmen sind ausgeschlossen. Als Firma mit Servicegebiet die Adresse ausblenden; Gebiet höchstens etwa 2 Stunden Fahrt. Bestätigung per Telefon/SMS, E-Mail, Video oder Postkarte — Google wählt.',
            'kostenlos', 'Google-Konto', null, 'it', 'offen'],
        ['apple', 'karte', 'Apple Business Connect', 'https://businessconnect.apple.com', 'https://support.apple.com/guide/business',
            'Organisation bestätigen (Code per Telefon oder Unterlagen), dann den Ort mit Adresse und Kartenmarker. Werbung in Apple Karten kostet — nicht buchen.',
            'kostenlos (Werbung kostet)', 'Apple-Account', null, 'it', 'offen'],
        ['bing', 'karte', 'Bing Places', 'https://www.bing.com/forbusiness', null,
            'Kann ein bestehendes Google-Profil übernehmen (Import). Sonst Bestätigung per E-Mail, Telefon oder Postkarte.',
            'kostenlos', 'Microsoft-Konto', null, 'it', 'offen'],
        ['paginegialle', 'branche', 'PagineGialle (Italiaonline)', 'https://www.paginegialle.it/inserisci_attivita', null,
            'Basisprofil laut Italiaonline kostenlos; Zusatzpakete („Priorità“ u. a.) kosten — nicht buchen. Teilweise geprüft: Das Formular war für den Abruf gesperrt.',
            'kostenlos (Zusatzpakete kosten)', 'unklar', null, 'it', 'offen'],
        ['misterimprese', 'branche', 'MisterImprese', 'https://www.misterimprese.it', 'https://www.misterimprese.it/content/faq',
            '„Completamente gratuito“. Konto per E-Mail oder Facebook anlegen, E-Mail bestätigen, dann im Profil „Rivendica o inserisci“.',
            'kostenlos', 'E-Mail oder Facebook', null, 'it', 'offen'],
        ['cylex', 'branche', 'Cylex Italia', 'https://www.cylex-italia.it/register-company', 'https://www.cylex-italia.it/note-legali.htm',
            'Eintrag kostenlos; Premium (9,90–11,90 € im Monat) nicht buchen.',
            'kostenlos (Premium kostet)', 'E-Mail', null, 'it', 'offen'],
        ['hotfrog', 'branche', 'Hotfrog Italia', 'https://www.hotfrog.it/register', 'https://legal.hotfrog.com/content-policy',
            'Rubrik „Web Design“. Prüfung automatisch und von Hand. Ein Preis steht auf der Seite nicht — nur den kostenlosen Eintrag nehmen.',
            'kostenlos (teilweise geprüft)', 'Name, E-Mail, Passwort', 0, 'it', 'offen'],
        ['opendi', 'branche', 'Opendi Italia', 'https://service.opendi.it/listings', 'https://www.opendi.it/content/contact_information.html',
            'Kostenloser Eintrag mit Konto.',
            'kostenlos', 'Konto', null, 'it', 'offen'],
        ['tgstat', 'telegram', 'TGStat', 'https://tgstat.com/add/channel', 'https://tgstat.com/p/agreement',
            'Größter Kanal-Katalog. Land „Italy“, Sprache „Italian“, Kategorie „Design“. Kein Login fürs Melden; die Inhaberschaft bestätigt man getrennt. Kanäle ohne Beitrag seit 30 Tagen werden ausgeblendet.',
            'kostenlos', 'kein Konto', 1, 'it', 'offen'],
        ['canalitelegram', 'telegram', 'CanaliTelegram.it', 'https://www.canalitelegram.it/segnala-canale', 'https://www.canalitelegram.it/termini',
            'Kanal muss öffentlich sein. Beschreibung höchstens 250 Zeichen, Kategorie „Arte & Design“. Prüfung 24–48 Stunden.',
            'kostenlos', 'kein Konto', 0, 'it', 'offen'],
        ['gruppitelegram', 'telegram', 'GruppiTelegram.it', 'https://www.gruppitelegram.it/aggiungi-il-tuo-telegram-alla-directory/', 'https://www.gruppitelegram.it/termini-e-condizioni-duso/',
            'Kategorie „Architettura e Design“. Nichts Illegales oder Anstößiges.',
            'kostenlos', 'nicht angegeben', 1, 'it', 'offen'],
        ['telegramchannels', 'telegram', 'TelegramChannels.me', 'https://telegramchannels.me/cp/media/create', 'https://telegramchannels.me/tos',
            'Italienischer und deutscher Bereich. Moderation vor der Freigabe. „Featured“ kostet — nicht buchen.',
            'kostenlos (Featured kostet)', 'E-Mail oder Google', null, 'it', 'offen'],
        ['telemetrio', 'telegram', 'Telemetrio', 'https://telemetr.io/en/add-channel', null,
            'Öffentlicher Kanal, Italien als Filter. Inhaberschaft über @telemetr_io_bot. Kein Preis genannt.',
            'kostenlos (kein Preis genannt)', 'unklar', null, 'it', 'offen'],
        ['techbehemoths', 'agentur', 'TechBehemoths', 'https://techbehemoths.com/companies/get-listed', 'https://techbehemoths.com/faq',
            '„100% free“. Nur mit Firmen-E-Mail (@vecom-design.it) — Gmail und Ähnliche werden abgelehnt. Rubriken für Italien.',
            'kostenlos', 'Firmen-E-Mail', null, 'en', 'offen'],
        ['clutch', 'agentur', 'Clutch (mit The Manifest)', 'https://clutch.co/get-listed', 'https://clutch.co/methodology',
            'Grundeintrag kostenlos, „Verified“ kostet 499 $ im Jahr — nicht buchen. Bewertungen entstehen über Interviews mit Kunden. The Manifest übernimmt das Profil.',
            'kostenlos („Verified“ kostet)', 'Konto', null, 'en', 'offen'],
        ['sortlist', 'agentur', 'Sortlist', 'https://www.sortlist.com/providers/pricing', null,
            'Kostenloses Profil mit begrenzter Sichtbarkeit; Sortlist+ kostet — nicht buchen.',
            'kostenlos (Sortlist+ kostet)', 'Konto', null, 'it', 'offen'],
        ['trovatelegram', 'telegram', 'TrovaTelegram.it', 'https://www.trovatelegram.it/aggiungi-canale', 'https://www.trovatelegram.it/termini',
            'Kostenlos, ohne Konto, Prüfung bis 24 Stunden — aber klein (rund 150 Kanäle) und ohne Design-Kategorie.',
            'kostenlos', 'kein Konto', 0, 'it', 'spaeter'],
        ['italle', 'telegram', 'Italle.com', 'https://italle.com/telegram/aggiungere-un-nuovo-canale-gruppo', null,
            'Erst ab 200 Abonnenten; verlangt einen Pflicht-Beitrag mit Link zu Italle im eigenen Kanal.',
            'kostenlos', 'E-Mail', 1, 'it', 'spaeter'],
        ['goodfirms', 'agentur', 'GoodFirms', 'https://www.goodfirms.co/get-listed', null,
            'Kostenlos, Prüfung in vier Schritten — nur ein kleiner Teil der kostenlosen Anträge wird angenommen. Besser mit ersten Kundenbewertungen.',
            'kostenlos (PRO kostet)', 'Konto', null, 'en', 'spaeter'],
        ['designrush', 'agentur', 'DesignRush', 'https://www.designrush.com/submit/agency', 'https://www.designrush.com/methodology',
            'Prüft Website, Portfolio und Bewertungen von Hand. Ein Preis steht auf der Seite nicht (teilweise geprüft).',
            'unklar', 'Name, E-Mail, Telefon', null, 'en', 'spaeter'],
        ['kompass', 'branche', 'Kompass Italia', 'https://it.kompass.com/registerNewCompany/identity', 'https://it.kompass.com/l/terms-of-use',
            'Kostenlos, aber auf Geschäftskunden (B2B) ausgerichtet; Geschäfts-E-Mail und Telefon nötig.',
            'kostenlos (Premium kostet)', 'Geschäfts-E-Mail und Telefon', 1, 'it', 'spaeter'],
        ['prontopro', 'branche', 'ProntoPro', 'https://www.prontopro.it/prosignup', null,
            'Vermittlungsplattform, kein reines Verzeichnis: Profil kostenlos, Angebote auf Anfragen kosten Credits.',
            'Profil kostenlos, Angebote kosten', 'Konto', null, 'it', 'spaeter'],
        ['webgram', 'telegram', 'WebGram.it', 'https://www.webgram.it/aggiungi/', 'https://www.webgram.it/termini-e-condizioni-duso/',
            'Nur gegen Gegenleistung: Ihr Werbebeitrag muss dauerhaft im eigenen Kanal stehen — Fremdwerbung im Firmenkanal.',
            'Gegenleistung im Kanal', 'Telegram-Kontakt', 1, 'it', 'nein'],
        ['awwwards', 'agentur', 'Awwwards Directory', 'https://www.awwwards.com/plans/creative-pro-plans', null,
            'Kein kostenloser Eintrag (ab 18 $ im Monat).',
            'kostet', '', null, 'en', 'nein'],
        ['tlgrm', 'telegram', 'tlgrm.eu', 'https://forms.tlgrm.eu/channels/suggest', null,
            'Nimmt nur englischsprachige Kanäle auf.',
            'kostenlos', '', null, 'en', 'nein'],
    ];

    /* EINFACHER (01.10.2026, Uwe: Ja zu V1 und V2): oben immer nur der nächste
       Eintrag; getrennt nach Land; zuerst die Stellen, die wirklich Kunden
       bringen (Karten, die großen Verzeichnisse, Agentur-Listen) — der Rest
       eingeklappt. Reihenfolge = Nutzen. */
    public const WIRKT = ['google', 'apple', 'bing', 'paginegialle', 'misterimprese', 'clutch', 'techbehemoths', 'sortlist'];
    /** Gelten in beiden Ländern (international oder mit deutschem Bereich). */
    public const BEIDE = ['clutch', 'techbehemoths', 'sortlist', 'goodfirms', 'designrush', 'telegramchannels', 'awwwards'];

    /** Für welches Land eine Stelle zählt: IT, DE oder beide. */
    public static function land(array $e): string
    {
        if (in_array((string) ($e['schluessel'] ?? ''), self::BEIDE, true) || (string) ($e['sprache'] ?? '') === 'en') { return 'beide'; }
        return (string) ($e['sprache'] ?? 'it') === 'de' ? 'DE' : 'IT';
    }

    /** Die Stellen eines Landes, geteilt in „bringt Kunden“ und „weitere“. @return array{wirkt:list<array>, weitere:list<array>} */
    public static function fuerLand(array $liste, string $land): array
    {
        $aus = ['wirkt' => [], 'weitere' => []];
        foreach ($liste as $e) {
            $l = self::land($e);
            if ($l !== 'beide' && $l !== $land) { continue; }
            $aus[in_array((string) ($e['schluessel'] ?? ''), self::WIRKT, true) ? 'wirkt' : 'weitere'][] = $e;
        }
        $rang = array_flip(self::WIRKT);
        usort($aus['wirkt'], static fn($a, $b) => ($rang[$a['schluessel']] ?? 99) <=> ($rang[$b['schluessel']] ?? 99));
        return $aus;
    }

    /** Der nächste Eintrag für dieses Land: der nützlichste, der noch offen ist. */
    public static function naechster(array $liste, string $land): ?array
    {
        $t = self::fuerLand($liste, $land);
        foreach (array_merge($t['wirkt'], $t['weitere']) as $e) { if ($e['status'] === 'offen') { return $e; } }
        return null;
    }

    /* ================================================================== */
    /*  Anlegen                                                           */
    /* ================================================================== */

    /**
     * Die Vorschläge einmal je Fassung anlegen. INSERT IGNORE über den
     * eindeutigen Schlüssel: zwei gleichzeitige Aufrufe legen nichts doppelt
     * an, und was Uwe schon geändert hat, bleibt, wie es ist.
     */
    public static function sicherstellen(): void
    {
        if ((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'verzeichnisse_vorschlaege'", [], '') === self::VORSCHLAEGE_FASSUNG) { return; }
        require_once __DIR__ . '/Firma.php';
        /* Ein Bewertungslink (g.page/r/…) entsteht nur aus einem bestehenden Google-Profil — dann steht es nicht als offen da. */
        $googleDa = preg_match('~^https://(g\.page/r/|search\.google\.com/local/writereview)~', Firma::get('google_bewertung')) === 1;
        foreach (self::VORSCHLAEGE as $i => [$sl, $art, $name, $url, $regelnUrl, $regeln, $kosten, $konto, $captcha, $sprache, $status]) {
            $online = $sl === 'google' && $googleDa;
            Db::run('INSERT IGNORE INTO mk_verzeichnisse (schluessel, art, name, url, regeln_url, regeln, kosten, kostenlos, konto, captcha, sprache, status, eintrag_url, notiz, reihenfolge, geprueft_am, status_am)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$sl, $art, $name, $url, $regelnUrl, $regeln, $kosten, str_starts_with($kosten, 'kostenlos') || str_starts_with($kosten, 'Profil kostenlos') ? 1 : 0, $konto, $captcha, $sprache,
                 $online ? 'online' : $status, $online ? Firma::get('google_bewertung') : null,
                 $online ? 'In den Firmendaten steht ein Google-Bewertungslink — das Profil besteht also schon.' : '', ($i + 1) * 10, self::VORSCHLAEGE_FASSUNG]);
        }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('verzeichnisse_vorschlaege', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [self::VORSCHLAEGE_FASSUNG]);
    }

    /**
     * Einen eigenen Eintrag aufnehmen. Ein Kanal oder eine Gruppe nur mit
     * Bestätigung, dass deren Beschreibung oder Regeln Kooperationen
     * erlauben — und mit dem Wortlaut, damit es später nachprüfbar ist.
     *
     * @return int|string neue ID oder Fehlertext
     */
    public static function anlegen(array $d): int|string
    {
        $art = (string) ($d['art'] ?? '');
        $name = trim((string) ($d['name'] ?? ''));
        $url = trim((string) ($d['url'] ?? ''));
        $regeln = trim((string) ($d['regeln'] ?? ''));
        $sprache = (string) ($d['sprache'] ?? 'it');
        $kostenlos = !empty($d['kostenlos']);
        $kosten = $kostenlos ? 'kostenlos' : mb_substr(trim((string) ($d['kosten'] ?? '')), 0, 160);
        $captchaRoh = (string) ($d['captcha'] ?? '');
        if (!isset(self::ARTEN[$art])) { return 'Bitte eine Art wählen.'; }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) { return 'Bitte einen Namen (2 bis 120 Zeichen) eingeben.'; }
        if (!self::urlOk($url)) { return 'Bitte die Adresse vollständig eingeben, mit https://.'; }
        if (!isset(self::SPRACHEN[$sprache])) { $sprache = 'it'; }
        if (!$kostenlos && $kosten === '') { return 'Kostet der Eintrag etwas? Dann bitte kurz, was — sonst „kostenlos“ ankreuzen.'; }
        if ($art === 'kanal') {
            if (!preg_match('~^https://t\.me/[A-Za-z0-9_]{4,64}/?$~', $url)) { return 'Bei Kanälen und Gruppen bitte die öffentliche Adresse, z. B. https://t.me/name.'; }
            if (empty($d['erlaubt'])) { return 'Nur Kanäle und Gruppen, deren Beschreibung oder Regeln Kooperationen oder Geschäftsbeiträge ausdrücklich erlauben — bitte bestätigen.'; }
            if (mb_strlen($regeln) < 10) { return 'Bitte eintragen, was dort zu Kooperationen steht (der Wortlaut aus Beschreibung oder Regeln).'; }
        }
        $id = Db::insert('mk_verzeichnisse', [
            'art' => $art, 'name' => $name, 'url' => $url, 'regeln' => mb_substr($regeln, 0, 700), 'kosten' => $kosten, 'kostenlos' => $kostenlos ? 1 : 0,
            'konto' => mb_substr(trim((string) ($d['konto'] ?? '')), 0, 80), 'captcha' => $captchaRoh === '1' ? 1 : ($captchaRoh === '0' ? 0 : null),
            'sprache' => $sprache, 'status' => 'offen', 'reihenfolge' => 900, 'geprueft_am' => date('Y-m-d'), 'status_am' => date('Y-m-d H:i:s'),
        ]);
        Events::pruefspur('verzeichnis_angelegt', 'mk_verzeichnisse', $id, [], ['art' => $art, 'name' => $name, 'url' => $url, 'kosten' => $kosten]);
        return $id;
    }

    /** Nur eine vollständige Web-Adresse, kein javascript: und Ähnliches. */
    public static function urlOk(string $u): bool
    {
        return mb_strlen($u) <= 255 && preg_match('~^https?://[^\s<>"\']+$~i', $u) === 1 && filter_var($u, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Den Eintrag vorbereiten: eine eigene Kampagne (Code vz-…), damit
     * Website-Link, Fenster-Link und Kanal-Link für genau diese Stelle zählen.
     * Gesperrt gelesen — zwei Klicks legen nicht zwei Kampagnen an.
     *
     * @return array{ok:bool, text:string, kampagne_id?:int}
     */
    public static function vorbereiten(int $id): array
    {
        require_once __DIR__ . '/MkKampagne.php';
        return Db::transaktion(static function () use ($id): array {
            $e = Db::one('SELECT * FROM mk_verzeichnisse WHERE id = ? FOR UPDATE', [$id]);
            if (!$e) { return ['ok' => false, 'text' => 'Diesen Eintrag gibt es nicht.']; }
            if ($e['kampagne_id'] !== null && MkKampagne::laden((int) $e['kampagne_id']) !== null) {
                return ['ok' => true, 'text' => 'Die Links für diesen Eintrag gibt es schon.', 'kampagne_id' => (int) $e['kampagne_id']];
            }
            $basis = 'vz-' . MkKampagne::slug((string) ($e['schluessel'] ?: $e['name']), 18);
            if (!MkKampagne::codeOk($basis)) { $basis = 'vz-eintrag'; }
            $code = $basis; $i = 2;
            while ((int) Db::wert('SELECT COUNT(*) FROM mk_kampagnen WHERE code = ?', [$code], 0) > 0) { $code = $basis . '-' . $i++; }
            $kid = MkKampagne::anlegen([
                'name' => 'Eintrag: ' . $e['name'],
                'plattform' => in_array($e['art'], ['telegram', 'kanal'], true) ? 'telegram' : 'verzeichnis',
                'code' => $code,
                'ziel' => ['de' => '/de/', 'en' => '/en/'][(string) $e['sprache']] ?? '/',
                'ziel_art' => 'leads',
                'notiz' => 'Verzeichnisse & Kooperationen: ' . $e['name'],
            ]);
            if (is_string($kid)) { return ['ok' => false, 'text' => $kid]; }
            Db::run('UPDATE mk_verzeichnisse SET kampagne_id = ? WHERE id = ?', [$kid, $id]);
            Events::pruefspur('verzeichnis_vorbereitet', 'mk_verzeichnisse', $id, [], ['kampagne_id' => $kid, 'code' => $code]);
            return ['ok' => true, 'text' => 'Eigene Links angelegt — was darüber kommt, zählt für „' . $e['name'] . '“.', 'kampagne_id' => (int) $kid];
        }, 5);
    }

    /**
     * Wie weit der Eintrag ist. Eine Adresse, unter der er zu sehen ist,
     * darf mit — nur eine vollständige Web-Adresse.
     *
     * @return ?string Fehler
     */
    public static function status(int $id, string $status, string $eintragUrl = '', ?string $notiz = null): ?string
    {
        $e = Db::one('SELECT * FROM mk_verzeichnisse WHERE id = ?', [$id]);
        if (!$e) { return 'Diesen Eintrag gibt es nicht.'; }
        if (!isset(self::STATUS[$status])) { return 'Unbekannter Stand.'; }
        $eintragUrl = trim($eintragUrl);
        if ($eintragUrl !== '' && !self::urlOk($eintragUrl)) { return 'Die Adresse des Eintrags bitte vollständig, mit https://.'; }
        $neu = ['status' => $status, 'status_am' => date('Y-m-d H:i:s')];
        if ($status === 'eingereicht' && $e['status'] !== 'eingereicht') { $neu['eingereicht_am'] = date('Y-m-d H:i:s'); }
        if ($eintragUrl !== '') { $neu['eintrag_url'] = $eintragUrl; }
        if ($notiz !== null) { $neu['notiz'] = mb_substr(trim($notiz), 0, 500); }
        Db::update('mk_verzeichnisse', $id, $neu);
        Events::pruefspur('verzeichnis_stand', 'mk_verzeichnisse', $id, array_intersect_key($e, $neu), $neu);
        return null;
    }

    /* ================================================================== */
    /*  Lesen                                                             */
    /* ================================================================== */

    public static function laden(int $id): ?array
    {
        return Db::one('SELECT * FROM mk_verzeichnisse WHERE id = ?', [$id]) ?: null;
    }

    /** Ist der Eintrag dran? Offen oder eingereicht, und seit einer Woche nichts passiert. */
    public static function istFaellig(array $e, ?int $jetzt = null): bool
    {
        $grenze = ($jetzt ?? time()) - self::FAELLIG_TAGE * 86400;
        if ($e['status'] === 'offen') { return strtotime((string) $e['angelegt_am']) <= $grenze && strtotime((string) ($e['status_am'] ?? $e['angelegt_am'])) <= $grenze; }
        if ($e['status'] === 'eingereicht') { return strtotime((string) ($e['eingereicht_am'] ?? $e['status_am'])) <= $grenze; }
        return false;
    }

    /** Wie viele Einträge dran sind (für die Zahl im Menü und den Zuruf). @return array{offen:int, eingereicht:int} */
    public static function faellig(): array
    {
        $aus = ['offen' => 0, 'eingereicht' => 0];
        foreach (Db::all("SELECT status, angelegt_am, status_am, eingereicht_am FROM mk_verzeichnisse WHERE status IN ('offen','eingereicht')") as $e) {
            if (self::istFaellig($e)) { $aus[(string) $e['status']]++; }
        }
        return $aus;
    }

    /**
     * Einmal am Tag (Cron): Sind Einträge seit einer Woche liegen geblieben,
     * ein Zuruf an Uwe — höchstens einer je Woche, ohne Namen von Kunden
     * (es gibt hier keine), nur wie viele und wo.
     */
    public static function erinnern(): int
    {
        self::sicherstellen();
        $f = self::faellig();
        $n = $f['offen'] + $f['eingereicht'];
        if ($n === 0) { return 0; }
        $teile = [];
        if ($f['offen'] > 0) { $teile[] = $f['offen'] . ' noch nicht eingetragen'; }
        if ($f['eingereicht'] > 0) { $teile[] = $f['eingereicht'] . ' seit über einer Woche eingereicht — nachsehen, ob sie online sind'; }
        require_once __DIR__ . '/Zuruf.php';
        Zuruf::vormerken('verzeichnisse', 'Verzeichnisse & Kooperationen: ' . implode(', ', $teile) . '. Alles unter Marketing → Verzeichnisse.', self::FAELLIG_TAGE * 24 * 60);
        return $n;
    }

    /**
     * Die Liste mit Zahlen: Website (Spur der Kampagne), Fenster und Beitritte
     * (Tageszahlen der Quelle m_CODE). Zeitraum: seit es die Einträge gibt.
     */
    public static function liste(): array
    {
        $alle = Db::all("SELECT v.*, k.code AS k_code, k.status AS k_status FROM mk_verzeichnisse v LEFT JOIN mk_kampagnen k ON k.id = v.kampagne_id
                          ORDER BY FIELD(v.status, 'offen', 'eingereicht', 'online', 'abgelehnt', 'spaeter', 'nein'), v.reihenfolge, v.id");
        $z = self::zahlen($alle, '2026-01-01', date('Y-m-d'));
        foreach ($alle as $i => $e) {
            $alle[$i]['zahl'] = $z[(int) ($e['kampagne_id'] ?? 0)] ?? null;
            $alle[$i]['faellig'] = self::istFaellig($e);
        }
        return $alle;
    }

    /**
     * Zahlen je Kampagne der Einträge im Zeitraum.
     * @return array<int, array{besuche:int, leads:int, kunden:int, umsatz:int, beitritte:int, fenster:int}>
     */
    public static function zahlen(array $eintraege, string $von, string $bis): array
    {
        $codes = [];
        foreach ($eintraege as $e) { if (!empty($e['kampagne_id']) && !empty($e['k_code'])) { $codes[(string) $e['k_code']] = (int) $e['kampagne_id']; } }
        if (!$codes) { return []; }
        require_once __DIR__ . '/MkKampagne.php';
        $web = MkKampagne::zahlen($von, $bis);
        $aus = [];
        foreach ($codes as $kid) {
            $w = $web[$kid] ?? MkKampagne::LEER;
            $aus[$kid] = ['besuche' => (int) $w['besuche'], 'leads' => (int) $w['leads'], 'kunden' => (int) $w['kunden'], 'umsatz' => (int) $w['umsatz'],
                          'beitritte' => 0, 'fenster' => 0];
        }
        try {
            foreach (Db::all("SELECT quelle, art, SUM(zahl) AS n FROM tg_tage WHERE tag BETWEEN ? AND ? AND quelle LIKE 'm\\_vz-%'
                               AND art IN ('kanal_bei', 'app_start', 'bot_start') GROUP BY quelle, art", [$von, $bis]) as $r) {
                if (!preg_match('/^m_([a-z0-9][a-z0-9-]{2,23})(?:_[a-z0-9-]+)?$/', (string) $r['quelle'], $m) || !isset($codes[$m[1]])) { continue; }
                $f = $r['art'] === 'kanal_bei' ? 'beitritte' : 'fenster';
                $aus[$codes[$m[1]]][$f] += (int) $r['n'];
            }
        } catch (Throwable $e) { /* Tageszahlen fehlen (Migration offen): dann ohne */ }
        return $aus;
    }

    /**
     * Für das Telegram-Dashboard: der Eintrag, der im Zeitraum am meisten
     * gebracht hat — nach Leads, solange es noch keine gibt nach Besuchen,
     * Fenstern und Beitritten.
     *
     * @return array{eintrag:?array, nach:string}
     */
    public static function beste(string $von, string $bis): array
    {
        try {
            $e = Db::all('SELECT v.name, v.kampagne_id, k.code AS k_code FROM mk_verzeichnisse v JOIN mk_kampagnen k ON k.id = v.kampagne_id');
        } catch (Throwable $x) { return ['eintrag' => null, 'nach' => '']; }
        $z = self::zahlen($e, $von, $bis);
        $leads = []; $wege = [];
        foreach ($e as $r) {
            $w = $z[(int) $r['kampagne_id']] ?? null;
            if ($w === null) { continue; }
            $leads[(string) $r['name']] = $w['leads'];
            $wege[(string) $r['name']] = $w['besuche'] + $w['fenster'] + $w['beitritte'];
        }
        require_once __DIR__ . '/MkKennzahlen.php';
        $l = MkKennzahlen::erster($leads, 5);
        return $l !== null ? ['eintrag' => $l, 'nach' => 'Leads'] : ['eintrag' => MkKennzahlen::erster($wege, 10), 'nach' => 'Besuche, Fenster und Beitritte (noch kein Lead)'];
    }

    /**
     * Die Links eines vorbereiteten Eintrags.
     * @return array{website:string, fenster:string, einladung:string, oeffentlich:string}
     */
    public static function links(array $e): array
    {
        require_once __DIR__ . '/Telegram.php';
        $aus = ['website' => '', 'fenster' => '', 'einladung' => '', 'oeffentlich' => (string) (Telegram::kanal()['link'] ?? '')];
        if (empty($e['kampagne_id'])) { return $aus; }
        require_once __DIR__ . '/MkKampagne.php';
        require_once __DIR__ . '/TelegramWachstum.php';
        $k = MkKampagne::laden((int) $e['kampagne_id']);
        if ($k === null) { return $aus; }
        $aus['website'] = MkKampagne::link($k);
        $aus['fenster'] = Telegram::einstellung('tg_name') !== '' ? TelegramWachstum::botLink($k) : '';
        $aus['einladung'] = (string) (TelegramWachstum::einladung((int) $k['id'])['link'] ?? '');
        return $aus;
    }

    /**
     * Die Texte zum Eintragen, in der Sprache der Stelle, mit den eigenen
     * Links. @return list<array{titel:string, text:string, hinweis:string}>
     */
    public static function texte(array $e, array $links): array
    {
        require_once __DIR__ . '/Firma.php';
        $sp = isset(self::SPRACHEN[(string) $e['sprache']]) ? (string) $e['sprache'] : 'it';
        $t = Texte::VERZEICHNIS;
        if ($e['art'] === 'kanal') {
            $link = $links['einladung'] !== '' ? $links['einladung'] : $links['oeffentlich'];
            return [['titel' => 'Anfrage an den Admin (' . self::SPRACHEN[$sp] . ')',
                     'text' => strtr($t['kooperation'][$sp], ['{kanal}' => (string) $e['name'], '{inhaber}' => Firma::get('inhaber', 'Vecom Design'), '{link}' => $link]),
                     'hinweis' => $links['einladung'] !== '' ? 'Mit dem eigenen Kanal-Link dieses Eintrags — Beitritte darüber zählen hier.' : 'Noch mit dem öffentlichen Kanal-Link: erst „Kanal-Link anlegen“, dann zählen Beitritte für diesen Eintrag.']];
        }
        $aus = [['titel' => 'Kurzbeschreibung (' . self::SPRACHEN[$sp] . ', ' . mb_strlen($t['kurz'][$sp]) . ' Zeichen)', 'text' => $t['kurz'][$sp], 'hinweis' => '']];
        if ($e['art'] !== 'telegram') {
            $aus[] = ['titel' => 'Beschreibung (' . self::SPRACHEN[$sp] . ')', 'text' => $t['lang'][$sp], 'hinweis' => ''];
        }
        return $aus;
    }

    /** Überall dieselben Firmendaten — für Karten und Verzeichnisse (und für Google ein Zeichen von Verlässlichkeit). */
    public static function firmendaten(): array
    {
        require_once __DIR__ . '/Firma.php';
        return array_filter([
            'Name' => Firma::get('name'), 'Straße' => Firma::get('strasse'), 'PLZ und Ort' => trim(Firma::get('plz') . ' ' . Firma::get('ort')),
            'Land' => Firma::get('land'), 'Telefon' => Firma::get('telefon'), 'E-Mail' => Firma::get('email'), 'Website' => Firma::get('web'),
        ], static fn($v) => trim((string) $v) !== '');
    }

    /* ================================================================== */
    /*  Ausfüll-Knopf (01.10.2026, Uwe: „nicht kopieren, sondern per       */
    /*  Knopfdruck“ → „ja“)                                               */
    /* ================================================================== */

    /*
     * WIE ER ARBEITET
     *
     * Ein Lesezeichen (javascript:) in Uwes Browser. Auf der Eintragsseite
     * eines Verzeichnisses geklickt, öffnet es ein kleines Fenster der
     * Verwaltung (Route „ausfuellen“, nur angemeldet). Das Fenster sucht die
     * Stelle zur Adresse, legt bei Bedarf ihre eigenen Links an und schickt
     * die Angaben per postMessage zurück — nur an genau die Seite, von der es
     * geöffnet wurde. Das Lesezeichen füllt damit die Felder aus und rahmt
     * sie gold. Absenden, Captcha und Bedingungen bleiben bei Uwe.
     *
     * WARUM SO
     *
     * Kein Server schickt Formulare an fremde Seiten (Captchas, Konten,
     * Bedingungen — und es wäre ein Bot). Das Lesezeichen enthält keine
     * Daten, nur den Weg zur Verwaltung: Es veraltet nicht, wenn sich Texte
     * oder Links ändern. Es lädt kein fremdes Skript nach (die Sicherheits-
     * regeln vieler Seiten würden das sperren), postMessage dagegen geht
     * überall. Übertragen werden nur öffentliche Firmenangaben, Texte und
     * die eigenen Links — kein Schlüssel, kein Token.
     */

    /** Die Domain, an der eine Stelle hängt: ohne www., die letzten zwei Teile (business.google.com → google.com). */
    public static function basisDomain(string $host): string
    {
        $h = strtolower(trim($host, ". \t\n\r"));
        if (str_starts_with($h, 'www.')) { $h = substr($h, 4); }
        if (filter_var($h, FILTER_VALIDATE_IP) !== false) { return $h; }
        $teile = explode('.', $h);
        return count($teile) > 2 ? implode('.', array_slice($teile, -2)) : $h;
    }

    /** Die Stelle zu einer Adresse — offene und eingereichte zuerst. */
    public static function fuerHost(string $host): ?array
    {
        $ziel = self::basisDomain($host);
        if ($ziel === '') { return null; }
        $treffer = null;
        foreach (Db::all("SELECT v.*, k.code AS k_code FROM mk_verzeichnisse v LEFT JOIN mk_kampagnen k ON k.id = v.kampagne_id
                           ORDER BY FIELD(v.status, 'offen', 'eingereicht', 'online', 'spaeter', 'abgelehnt', 'nein'), v.reihenfolge, v.id") as $e) {
            $h = (string) parse_url((string) $e['url'], PHP_URL_HOST);
            if ($h !== '' && self::basisDomain($h) === $ziel) { $treffer = $e; break; }
        }
        return $treffer;
    }

    /**
     * Darf das Fenster seine Angaben an diese Seite schicken? Nur an genau die
     * Seite, deren Adresse es kennt: https und derselbe Host (http nur für
     * die eigene Maschine beim Prüfen).
     */
    public static function zielOk(string $host, string $origin): bool
    {
        if (!preg_match('~^(https?)://([a-z0-9.-]+)(?::(\d{1,5}))?$~i', $origin, $m)) { return false; }
        if (strtolower($m[2]) !== strtolower($host) || $host === '') { return false; }
        return strtolower($m[1]) === 'https' || in_array(strtolower($host), ['127.0.0.1', 'localhost'], true);
    }

    /**
     * Was das Lesezeichen in die Felder schreibt — für eine Stelle (mit ihren
     * eigenen Links und ihrer Sprache) oder allgemein.
     */
    public static function ausfuellDaten(?array $e, string $sprache = 'it'): array
    {
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Telegram.php';
        $sp = $e !== null && isset(self::SPRACHEN[(string) $e['sprache']]) ? (string) $e['sprache'] : (isset(self::SPRACHEN[$sprache]) ? $sprache : 'it');
        $links = $e !== null ? self::links($e) : ['website' => '', 'fenster' => '', 'einladung' => '', 'oeffentlich' => (string) (Telegram::kanal()['link'] ?? '')];
        $ortRoh = Firma::get('ort');
        $prov = preg_match('/\(([A-Z]{2})\)/', $ortRoh, $m) ? $m[1] : '';
        $ort = trim((string) preg_replace('/\s*\([^)]*\)\s*/', ' ', $ortRoh));
        $inhaber = trim(Firma::get('inhaber'));
        $teile = $inhaber !== '' ? preg_split('/\s+/', $inhaber) : [];
        $web = $links['website'] !== '' ? $links['website'] : (string) Firma::get('web');
        if ($web !== '' && !preg_match('~^https?://~i', $web)) { $web = 'https://' . $web; }
        $kanal = (string) $links['oeffentlich'];
        $laender = ['it' => ['Italia', 'Italy', 'Italien'], 'de' => ['Italien', 'Italy', 'Italia'], 'en' => ['Italy', 'Italia', 'Italien']];
        $land = in_array(mb_strtolower(Firma::get('land')), ['', 'italien', 'italia', 'italy', 'it'], true) ? $laender[$sp][0] : Firma::get('land');
        return [
            'name' => Firma::get('name', 'Vecom Design'), 'inhaber' => $inhaber,
            'vorname' => $teile[0] ?? '', 'nachname' => count($teile) > 1 ? implode(' ', array_slice($teile, 1)) : '',
            'strasse' => Firma::get('strasse'), 'plz' => Firma::get('plz'), 'ort' => $ort,
            'provinz' => $prov, 'provinz_name' => $prov === 'AG' ? 'Agrigento' : $prov, 'region' => $prov === 'AG' ? 'Sicilia' : '',
            'land' => $land, 'land_namen' => $laender[$sp],
            'telefon' => Firma::get('telefon'), 'email' => Firma::get('email'), 'piva' => Firma::get('piva'),
            'web' => $web, 'kanal' => $kanal, 'kanal_name' => preg_match('~t\.me/([A-Za-z0-9_]{4,})~', $kanal, $k) ? '@' . $k[1] : '',
            'fenster' => (string) $links['fenster'],
            'kurz' => Texte::VERZEICHNIS['kurz'][$sp], 'lang' => Texte::VERZEICHNIS['lang'][$sp],
            'stichworte' => ['it' => 'siti web, web design, Agrigento, Sicilia, piccole imprese', 'de' => 'Webdesign, Websites, Sizilien, Agrigent, kleine Betriebe',
                             'en' => 'web design, websites, Sicily, Agrigento, small businesses'][$sp],
            'sprache' => $sp, 'sprache_namen' => ['it' => ['italiano', 'italian', 'italienisch'], 'de' => ['tedesco', 'german', 'deutsch'], 'en' => ['inglese', 'english', 'englisch']][$sp],
            'kategorien' => ['web design', 'webdesign', 'siti web', 'web agency', 'design', 'grafica', 'marketing', 'informatica', 'internet', 'tecnologia', 'technology', 'servizi'],
            'meiden' => ['gruppi', 'group', 'adult', '18'],
            'art' => $e !== null ? (string) $e['art'] : '',
        ];
    }

    /** Das Lesezeichen: ein javascript:-Link ohne Daten, nur mit dem Weg zur eigenen Verwaltung. */
    public static function lesezeichen(): string
    {
        require_once __DIR__ . '/Config.php';
        $web = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $teile = parse_url($web);
        $origin = ($teile['scheme'] ?? 'https') . '://' . ($teile['host'] ?? 'vecom-design.it') . (isset($teile['port']) ? ':' . $teile['port'] : '');
        $js = strtr((string) file_get_contents(__DIR__ . '/ausfueller.js'), ['__ORIGIN__' => $origin, '__APP__' => $origin . Config::basis()]);
        $js = (string) preg_replace('/\n\s*/', '', $js);
        return 'javascript:' . rawurlencode($js);
    }

    /** Ohne Konto und ohne Captcha — dort kann Claude nach Uwes Ja einreichen. */
    public static function ohneKonto(array $e): bool
    {
        return str_starts_with((string) $e['konto'], 'kein') && $e['captcha'] !== null && (int) $e['captcha'] === 0;
    }
}
