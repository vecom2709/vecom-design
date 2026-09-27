<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';
require_once __DIR__ . '/AkquiseAnalyse.php';
require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Firma.php';

/**
 * Brief per Klick (26.09.2026, Uwe: Ja): Der freigegebene Brief geht über
 * die Schnittstelle von ufficiopostale.com (Openapi, Poste Italiane) als
 * Posta Ordinaria raus -- gedruckt, kuvertiert, frankiert. Nur Italien.
 *
 * ZWEI SCHRITTE, WIE BEIM BELEG:
 *   1. vorschau(): Auftrag mit autoconfirm=false anlegen. Der Dienst prüft
 *      das PDF, nennt Preis und Seitenzahl und zeigt das gedruckte Blatt.
 *      Es kostet noch nichts und geht nirgends hin.
 *   2. senden(): erst Uwes Klick bestätigt (PATCH confirmed=true). Ab da ist
 *      der Brief bezahlt und unterwegs -- deshalb steht die Tat in
 *      Ablauf::TRAGWEITE, und das Gate wird hier noch einmal gefragt.
 *
 * TESTBETRIEB: Solange „Test“ an ist, geht alles an die Sandbox
 * (test.ws.ufficiopostale.com) -- kein Brief, keine Kosten, und der Betrieb
 * gilt NICHT als angeschrieben.
 *
 * Das PDF entsteht hier (Pdf.php, ohne Fremdbibliothek), mit dem QR-Code zur
 * Analyse-Seite als Vektorfläche. HTML an den Dienst zu schicken hieße, sich
 * darauf zu verlassen, dass dessen Renderer unseren QR-Code zeichnet.
 */
final class AkquiseBriefdienst
{
    public const PROD = 'https://ws.ufficiopostale.com';
    public const TEST = 'https://test.ws.ufficiopostale.com';

    /** @var null|callable(string $methode, string $url, ?array $body): array{status:int, json:?array} Für die Kette. */
    public static $netz = null;

    /* ------------------------------ Einstellungen ------------------------ */

    /** Für die Kette: fester Schlüssel statt des verschlüsselten aus settings (die Kette schreibt nie config.local.php). */
    public static ?string $tokenFest = null;

    public static function token(): string
    {
        if (self::$tokenFest !== null) { return self::$tokenFest; }
        $blob = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_brief_token'", [], '');
        if ($blob === '') { return ''; }
        require_once __DIR__ . '/Hosting.php';
        return (string) (Hosting::entsiegeln($blob)['token'] ?? '');
    }

    public static function tokenSetzen(string $token): void
    {
        $token = trim($token);
        require_once __DIR__ . '/Hosting.php';
        $blob = $token === '' ? '' : (string) Hosting::versiegeln(['token' => $token]);
        if ($token !== '' && $blob === '') { throw new RuntimeException('Der Schlüssel ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).'); }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_brief_token', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$blob]);
    }

    public static function test(): bool
    {
        return (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_brief_test'", [], '1') !== '0';
    }

    public static function testSetzen(bool $an): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_brief_test', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$an ? '1' : '0']);
    }

    public static function bereit(): bool { return self::token() !== ''; }

    /* ------------------------------ Adressen ----------------------------- */

    public const DUG = ['via', 'viale', 'piazza', 'piazzale', 'corso', 'largo', 'vicolo', 'contrada', 'c.da', 'strada', 'lungomare', 'salita',
                        'traversa', 'vico', 'borgo', 'località', 'loc.', 'frazione', 'fraz.', 'circonvallazione', 'rotonda', 'galleria', 'piazzetta'];

    /** Provinz-Kürzel aus dem Namen (auch „Libero consorzio comunale di Agrigento“, „Città metropolitana di Palermo“). */
    public const PROVINZEN = [
        'agrigento' => 'AG', 'alessandria' => 'AL', 'ancona' => 'AN', 'aosta' => 'AO', 'arezzo' => 'AR', 'ascoli piceno' => 'AP', 'asti' => 'AT',
        'avellino' => 'AV', 'bari' => 'BA', 'barletta-andria-trani' => 'BT', 'belluno' => 'BL', 'benevento' => 'BN', 'bergamo' => 'BG', 'biella' => 'BI',
        'bologna' => 'BO', 'bolzano' => 'BZ', 'brescia' => 'BS', 'brindisi' => 'BR', 'cagliari' => 'CA', 'caltanissetta' => 'CL', 'campobasso' => 'CB',
        'caserta' => 'CE', 'catania' => 'CT', 'catanzaro' => 'CZ', 'chieti' => 'CH', 'como' => 'CO', 'cosenza' => 'CS', 'cremona' => 'CR', 'crotone' => 'KR',
        'cuneo' => 'CN', 'enna' => 'EN', 'fermo' => 'FM', 'ferrara' => 'FE', 'firenze' => 'FI', 'foggia' => 'FG', 'forlì-cesena' => 'FC', 'frosinone' => 'FR',
        'genova' => 'GE', 'gorizia' => 'GO', 'grosseto' => 'GR', 'imperia' => 'IM', 'isernia' => 'IS', 'la spezia' => 'SP', "l'aquila" => 'AQ', 'latina' => 'LT',
        'lecce' => 'LE', 'lecco' => 'LC', 'livorno' => 'LI', 'lodi' => 'LO', 'lucca' => 'LU', 'macerata' => 'MC', 'mantova' => 'MN', 'massa-carrara' => 'MS',
        'matera' => 'MT', 'messina' => 'ME', 'milano' => 'MI', 'modena' => 'MO', 'monza e brianza' => 'MB', 'napoli' => 'NA', 'novara' => 'NO', 'nuoro' => 'NU',
        'oristano' => 'OR', 'padova' => 'PD', 'palermo' => 'PA', 'parma' => 'PR', 'pavia' => 'PV', 'perugia' => 'PG', 'pesaro e urbino' => 'PU', 'pescara' => 'PE',
        'piacenza' => 'PC', 'pisa' => 'PI', 'pistoia' => 'PT', 'pordenone' => 'PN', 'potenza' => 'PZ', 'prato' => 'PO', 'ragusa' => 'RG', 'ravenna' => 'RA',
        'reggio calabria' => 'RC', 'reggio emilia' => 'RE', 'rieti' => 'RI', 'rimini' => 'RN', 'roma' => 'RM', 'rovigo' => 'RO', 'salerno' => 'SA', 'sassari' => 'SS',
        'savona' => 'SV', 'siena' => 'SI', 'siracusa' => 'SR', 'sondrio' => 'SO', 'sud sardegna' => 'SU', 'taranto' => 'TA', 'teramo' => 'TE', 'terni' => 'TR',
        'torino' => 'TO', 'trapani' => 'TP', 'trento' => 'TN', 'treviso' => 'TV', 'trieste' => 'TS', 'udine' => 'UD', 'varese' => 'VA', 'venezia' => 'VE',
        'verbano-cusio-ossola' => 'VB', 'vercelli' => 'VC', 'verona' => 'VR', 'vibo valentia' => 'VV', 'vicenza' => 'VI', 'viterbo' => 'VT',
    ];
    /** Rückfall für Sizilien, wo die Recherche herkommt: die ersten zwei Ziffern der PLZ. */
    public const PLZ_SIZILIEN = ['90' => 'PA', '91' => 'TP', '92' => 'AG', '93' => 'CL', '94' => 'EN', '95' => 'CT', '96' => 'SR', '97' => 'RG', '98' => 'ME'];

    public static function provinz(array $f): ?string
    {
        foreach ([(string) ($f['stadt'] ?? ''), (string) ($f['adresse'] ?? '')] as $s) {
            if (preg_match('~\(([A-Z]{2})\)~', $s, $m) && in_array($m[1], self::PROVINZEN, true)) { return $m[1]; }
        }
        $kreis = mb_strtolower(trim((string) ($f['kreis'] ?? '')));
        $kreis = trim((string) preg_replace('~^(libero consorzio comunale di|città metropolitana di|citta metropolitana di|provincia di|provincia autonoma di)\s+~u', '', $kreis));
        if ($kreis !== '') {
            if (isset(self::PROVINZEN[$kreis])) { return self::PROVINZEN[$kreis]; }
            foreach (self::PROVINZEN as $name => $kz) { if (str_contains($kreis, $name)) { return $kz; } }
        }
        $plz = (string) ($f['plz'] ?? '');
        return preg_match('~^\d{5}$~', $plz) ? (self::PLZ_SIZILIEN[substr($plz, 0, 2)] ?? null) : null;
    }

    /** „Via Roma, 12“ → dug, indirizzo, civico. Ohne Hausnummer: „snc“ (senza numero civico). */
    public static function strasse(string $adresse): ?array
    {
        $a = trim(preg_replace('~\s+~u', ' ', $adresse) ?? '');
        if ($a === '') { return null; }
        $civico = 'snc';
        if (preg_match('~^(.*?)[,\s]+(n\.?\s*)?(\d+[a-zA-Z]?(?:/[0-9a-zA-Z]+)?|snc|s\.n\.c\.)$~iu', $a, $m)) { $a = trim($m[1], " ,"); $civico = strtolower($m[3]); }
        $dug = 'via';
        foreach (self::DUG as $d) {
            if (preg_match('~^' . preg_quote($d, '~') . '\s+(.+)$~iu', $a, $m)) { $dug = mb_strtolower($d); $a = $m[1]; break; }
        }
        return $a === '' ? null : ['dug' => $dug, 'indirizzo' => $a, 'civico' => $civico];
    }

    /** @return array{ok:bool, grund?:string, daten?:array} */
    public static function empfaenger(array $f): array
    {
        if (strtoupper((string) $f['land']) !== 'IT') { return ['ok' => false, 'grund' => 'Der Briefdienst liefert nur innerhalb Italiens. Bitte von Hand drucken.']; }
        $s = self::strasse((string) ($f['adresse'] ?? ''));
        if (!$s) { return ['ok' => false, 'grund' => 'Die Straße fehlt. Bitte die Adresse der Firma ergänzen.']; }
        if (!preg_match('~^\d{5}$~', (string) ($f['plz'] ?? ''))) { return ['ok' => false, 'grund' => 'Die PLZ fehlt oder ist nicht fünfstellig.']; }
        if (trim((string) ($f['stadt'] ?? '')) === '') { return ['ok' => false, 'grund' => 'Der Ort fehlt.']; }
        $prov = self::provinz($f);
        if ($prov === null) { return ['ok' => false, 'grund' => 'Die Provinz ist nicht erkennbar. Bitte den Kreis der Firma eintragen (z. B. Agrigento).']; }
        // Empfänger ist der Betrieb; nome/cognome verlangt der Dienst -- ohne Ansprechpartner „Titolare“.
        $person = preg_split('~\s+~u', trim((string) ($f['ansprechpartner'] ?? ''))) ?: [];
        [$nome, $cognome] = count($person) >= 2 ? [array_shift($person), implode(' ', $person)] : ['Titolare', (string) $f['name']];
        return ['ok' => true, 'daten' => [
            'nome' => mb_substr($nome, 0, 60), 'cognome' => mb_substr($cognome, 0, 80), 'ragione_sociale' => mb_substr((string) $f['name'], 0, 80),
            'dug' => $s['dug'], 'indirizzo' => mb_substr($s['indirizzo'], 0, 80), 'civico' => $s['civico'],
            'comune' => trim((string) preg_replace('~\s*\([A-Z]{2}\)\s*$~', '', (string) $f['stadt'])), 'cap' => (string) $f['plz'],
            'provincia' => $prov, 'nazione' => 'Italia',
        ]];
    }

    /** @return array{ok:bool, grund?:string, daten?:array} */
    public static function absender(): array
    {
        $abs = AkquiseText::absender();
        $s = self::strasse(Firma::get('strasse'));
        $ort = Firma::get('ort', '');
        $plz = Firma::get('plz');
        $prov = self::provinz(['stadt' => $ort, 'plz' => $plz]);
        if (!$s || !preg_match('~^\d{5}$~', $plz) || $prov === null) {
            return ['ok' => false, 'grund' => 'Für den Absender fehlen Straße, PLZ oder Provinz — bitte unter Einstellungen → Firma eintragen (Ort z. B. „Aragona (AG)“).'];
        }
        $inh = preg_split('~\s+~u', trim($abs['inhaber'])) ?: ['Vecom'];
        return ['ok' => true, 'daten' => [
            'nome' => (string) array_shift($inh), 'cognome' => implode(' ', $inh) ?: 'Design', 'ragione_sociale' => $abs['firma'],
            'dug' => $s['dug'], 'indirizzo' => $s['indirizzo'], 'civico' => $s['civico'],
            'comune' => trim((string) preg_replace('~\s*\([A-Z]{2}\)\s*$~', '', $ort)), 'cap' => $plz, 'provincia' => $prov, 'nazione' => 'Italia',
            'email' => $abs['email'],
        ]];
    }

    /* ------------------------------ Das Blatt ---------------------------- */

    /** Der Brief als PDF: Briefkopf, Text, QR-Code zur Analyse-Seite. Eine Seite. */
    /**
     * Partner, der um diesen Brief gebeten hat (27.09.2026) -- dann trägt der
     * Brief unten seinen Namen, sein Foto und seinen QR statt der Auswertung.
     */
    public static function partnerFuerBrief(int $firmaId): ?array
    {
        require_once __DIR__ . '/PartnerAnschreiben.php';
        $w = PartnerAnschreiben::wunsch($firmaId);
        if ($w === null || $w['status'] !== 'offen') { return null; }
        require_once __DIR__ . '/Partner.php';
        return Partner::laden($w['partner_id']) ?: null;
    }

    public static function pdf(array $f, array $v, string $qrAdresse, ?array $partner = null): string
    {
        if ($partner !== null) {
            require_once __DIR__ . '/PartnerWerbung.php';
            require_once __DIR__ . '/Texte.php';
            $qrAdresse = PartnerWerbung::link($partner, 'brief');
        }
        $abs = AkquiseText::absender();
        $sp = (string) $v['sprache'];
        $pdf = new Pdf();
        $gold = [0.62, 0.47, 0.17]; $grau = [0.38, 0.36, 0.33];
        $links = 62.0; $breite = Pdf::A4_BREIT - 2 * $links;
        $pdf->text($links, 64, 'VECOM', 15, true, 'links', $gold);
        $pdf->text($links + 58, 64, 'DESIGN', 15, true, 'links', [0.08, 0.07, 0.06]);
        $pdf->text(Pdf::A4_BREIT - $links, 58, $abs['firma'] . ' · ' . $abs['inhaber'], 8.5, false, 'rechts', $grau);
        $pdf->text(Pdf::A4_BREIT - $links, 70, implode(' · ', array_filter([Firma::get('strasse'), trim(Firma::get('plz') . ' ' . Firma::get('ort'))])), 8.5, false, 'rechts', $grau);
        $pdf->text(Pdf::A4_BREIT - $links, 82, $abs['email'] . ($abs['telefon'] !== '' ? ' · ' . $abs['telefon'] : '') . ' · vecom-design.it', 8.5, false, 'rechts', $grau);
        $pdf->linie($links, 96, Pdf::A4_BREIT - $links, 96, 0.6, [0.85, 0.78, 0.6]);
        $y = 128.0;
        foreach (array_filter([(string) $f['name'], (string) ($f['adresse'] ?? ''), trim(($f['plz'] ?? '') . ' ' . ($f['stadt'] ?? ''))]) as $z) {
            $pdf->text($links, $y, $z, 10.5); $y += 14;
        }
        $monat = ['it' => ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'],
                  'de' => ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
                  'en' => ['January','February','March','April','May','June','July','August','September','October','November','December']][$sp] ?? null;
        $datum = $monat ? ((int) date('j') . ($sp === 'de' ? '. ' : ' ') . $monat[(int) date('n') - 1] . ' ' . date('Y')) : date('d.m.Y');
        $pdf->text(Pdf::A4_BREIT - $links, 128, preg_replace('~\s*\(.*\)$~', '', $abs['ort']) . ', ' . $datum, 10, false, 'rechts');
        $y = max($y + 22, 196.0);
        if (trim((string) $v['betreff']) !== '') { $pdf->text($links, $y, (string) $v['betreff'], 11.5, true); $y += 24; }
        // Schrift so groß wie möglich, bis alles auf eine Seite passt (Platz für den QR-Code unten).
        $unten = 700.0;
        foreach ([10.5, 10.0, 9.5, 9.0, 8.5] as $gr) {
            $zeilen = [];
            foreach (explode("\n", (string) $v['text']) as $absatz) {
                $zeilen = array_merge($zeilen, trim($absatz) === '' ? [''] : $pdf->umbrechen($absatz, $breite, $gr));
            }
            if ($y + count($zeilen) * $gr * 1.42 <= $unten) { break; }
        }
        foreach ($zeilen as $z) { if ($z !== '') { $pdf->text($links, $y, $z, $gr); } $y += $gr * 1.42; }
        if ($qrAdresse !== '') {
            require_once dirname(__DIR__) . '/lib/qrcode.php';
            $qr = QRCode::getMinimumQRCode($qrAdresse, QR_ERROR_CORRECT_LEVEL_M);
            $n = $qr->getModuleCount(); $masse = 92.0; $mod = $masse / $n; $qx = $links; $qy = 722.0;
            for ($r = 0; $r < $n; $r++) { for ($c = 0; $c < $n; $c++) {
                if ($qr->isDark($r, $c)) { $pdf->flaeche($qx + $c * $mod, $qy + $r * $mod, $mod + 0.05, $mod + 0.05, [0, 0, 0]); }
            } }
            $t = ['it' => ['La vostra analisi personale', 'Inquadrate il codice con lo smartphone: senza registrazione, solo per voi.'],
                  'de' => ['Ihre persönliche Auswertung', 'Mit dem Handy scannen — ohne Anmeldung, nur für Sie.'],
                  'en' => ['Your personal analysis', 'Scan with your phone — no sign-up, just for you.']][$sp] ?? ['', ''];
            $tx = $qx + $masse + 16;
            if ($partner !== null) {
                // Brief auf Wunsch eines Partners: sein Foto, sein Name, sein Link.
                $B = Texte::PARTNER_ANSCHREIBEN['brief'];
                $t = [strtr(Texte::h($B['empf'], $sp), ['{name}' => Partner::anzeigeName($partner)]), Texte::h($B['scan'], $sp)];
                $foto = PartnerAnschreiben::fotoJpeg((int) $partner['id']);
                if ($foto !== null && $pdf->bild($foto, $tx, $qy + 16, 56, 56)) { $tx += 70; }
            }
            $pdf->text($tx, $qy + 34, $t[0], 11, true, 'links', $gold);
            $pdf->text($tx, $qy + 52, $t[1], 9, false, 'links', $grau);
        }
        return $pdf->fertig();
    }

    /* ------------------------------ Aufträge ----------------------------- */

    /** @return array{status:int, json:?array} */
    private static function anfrage(string $methode, string $pfad, ?array $body = null): array
    {
        $url = (self::test() ? self::TEST : self::PROD) . $pfad;
        if (self::$netz) { return (self::$netz)($methode, $url, $body); }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . self::token(), 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $j = is_string($roh) ? json_decode($roh, true) : null;
        return ['status' => $status, 'json' => is_array($j) ? $j : null];
    }

    private static function fehlertext(array $a): string
    {
        $j = $a['json'] ?? [];
        $m = trim((string) ($j['message'] ?? '') . ' ' . (is_string($j['error'] ?? null) ? $j['error'] : json_encode($j['error'] ?? '')));
        return mb_substr('HTTP ' . $a['status'] . ($m !== '' && $m !== '""' ? ': ' . $m : ''), 0, 250);
    }

    /**
     * Schritt 1: Auftrag anlegen, noch nicht bestätigt.
     * @return int akq_briefe.id
     */
    public static function vorschau(int $firmaId): int
    {
        if (!self::bereit()) { throw new RuntimeException('Für den Briefdienst fehlt der Schlüssel (Regeln & Versand → Briefdienst).'); }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        $gate = AkquiseGate::pruefen($f, 'brief');
        if (in_array($gate['status'], [AkquiseGate::NICHT, AkquiseGate::UNKLAR], true)) {
            throw new RuntimeException('Ein Brief ist hier nicht erlaubt: ' . ($gate['gruende'][0] ?? AkquiseGate::STATUS[$gate['status']]));
        }
        $v = Db::one("SELECT * FROM akq_vorlagen WHERE firma_id = ? AND kanal = 'brief' AND status = 'freigegeben' ORDER BY id DESC LIMIT 1", [$firmaId]);
        if (!$v) { throw new RuntimeException('Es gibt keinen freigegebenen Brieftext.'); }
        $an = self::empfaenger($f);
        if (!$an['ok']) { throw new RuntimeException($an['grund']); }
        $von = self::absender();
        if (!$von['ok']) { throw new RuntimeException($von['grund']); }
        $analyse = Db::one('SELECT * FROM akq_analysen WHERE firma_id = ? AND aktiv = 1 ORDER BY id DESC LIMIT 1', [$firmaId]);
        $pdf = self::pdf($f, $v, $analyse ? AkquiseAnalyse::adresse($analyse) : '', self::partnerFuerBrief($firmaId));
        $a = self::anfrage('POST', '/ordinarie/', [
            'mittente' => $von['daten'], 'destinatari' => [$an['daten']],
            'documento' => ['data:application/pdf;base64,' . base64_encode($pdf)],
            'opzioni' => ['fronteretro' => false, 'colori' => true, 'autoconfirm' => false],
        ]);
        $d = $a['json']['data'][0] ?? ($a['json']['data'] ?? null);
        $test = self::test() ? 1 : 0;
        if ($a['status'] < 200 || $a['status'] >= 300 || !is_array($d) || empty($d['id'])) {
            Db::insert('akq_briefe', ['firma_id' => $firmaId, 'vorlage_id' => (int) $v['id'], 'test' => $test, 'status' => 'fehler',
                'fehler' => self::fehlertext($a), 'actor' => Auth::angemeldet() ? Auth::name() : 'System']);
            throw new RuntimeException('Der Briefdienst hat den Auftrag nicht angenommen (' . self::fehlertext($a) . ').');
        }
        $euro = (float) ($d['pricing']['totale']['importo_totale'] ?? 0);
        $id = (int) Db::insert('akq_briefe', [
            'firma_id' => $firmaId, 'vorlage_id' => (int) $v['id'], 'test' => $test, 'auftrag' => mb_substr((string) $d['id'], 0, 80),
            'status' => 'vorschau', 'kosten_cents' => (int) round($euro * 100), 'seiten' => (int) ($d['documento_validato']['pagine'] ?? 0) ?: null,
            'pdf_url' => mb_substr((string) ($d['documento_validato']['pdf'] ?? ''), 0, 500) ?: null,
            'actor' => Auth::angemeldet() ? Auth::name() : 'System',
        ]);
        Akquise::protokoll($firmaId, 'brief', 'Brief beim Briefdienst vorbereitet' . ($test ? ' (TEST)' : '') . ' — ' . number_format($euro, 2, ',', '.') . ' €, noch nicht verschickt');
        return $id;
    }

    /** Schritt 2: bestätigen -- ab hier bezahlt und unterwegs. */
    public static function senden(int $briefId, string $begruendung): void
    {
        $b = Db::one("SELECT * FROM akq_briefe WHERE id = ? AND status = 'vorschau'", [$briefId]);
        if (!$b) { throw new RuntimeException('Dieser Brief ist nicht (mehr) in der Vorschau.'); }
        if ((int) $b['test'] !== (self::test() ? 1 : 0)) { throw new RuntimeException('Test/Echt wurde inzwischen umgestellt — bitte die Vorschau neu erzeugen.'); }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $b['firma_id']]);
        $gate = AkquiseGate::pruefen($f ?? [], 'brief');
        if (in_array($gate['status'], [AkquiseGate::NICHT, AkquiseGate::UNKLAR], true)) {
            Db::update('akq_briefe', $briefId, ['status' => 'verworfen', 'fehler' => 'Gate: ' . mb_substr((string) ($gate['gruende'][0] ?? ''), 0, 200)]);
            throw new RuntimeException('Inzwischen nicht mehr erlaubt: ' . ($gate['gruende'][0] ?? ''));
        }
        $a = self::anfrage('PATCH', '/ordinarie/' . rawurlencode((string) $b['auftrag']), ['confirmed' => true]);
        if ($a['status'] < 200 || $a['status'] >= 300) {
            Db::update('akq_briefe', $briefId, ['fehler' => self::fehlertext($a)]);
            throw new RuntimeException('Der Briefdienst hat die Bestätigung nicht angenommen (' . self::fehlertext($a) . '). Nichts wurde verschickt.');
        }
        Db::update('akq_briefe', $briefId, ['status' => 'verschickt', 'verschickt_am' => date('Y-m-d H:i:s')]);
        if ((int) $b['test'] !== 1) {
            require_once __DIR__ . '/PartnerAnschreiben.php';
            PartnerAnschreiben::verschickt((int) $b['firma_id']);
        }
        if ((int) $b['test'] === 1) {
            Akquise::protokoll((int) $b['firma_id'], 'brief', 'TEST-Brief bestätigt (Sandbox, nichts verschickt)');
            return;
        }
        require_once __DIR__ . '/AkquiseVersand.php';
        AkquiseVersand::vonHand((int) $b['firma_id'], 'brief', mb_substr('Brief über Briefdienst (Auftrag ' . $b['auftrag'] . ', '
            . number_format(((int) $b['kosten_cents']) / 100, 2, ',', '.') . ' €) — ' . trim($begruendung), 0, 255), (int) $b['vorlage_id']);
    }

    public static function verwerfen(int $briefId): void
    {
        Db::run("UPDATE akq_briefe SET status = 'verworfen' WHERE id = ? AND status = 'vorschau'", [$briefId]);
    }
}
