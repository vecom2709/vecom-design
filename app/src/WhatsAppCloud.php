<?php
declare(strict_types=1);
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';

/**
 * WhatsApp Business über die Meta-Schnittstelle (28.09.2026, Uwe: Ja zu V3
 * „WhatsApp nach Ja“).
 *
 * WER ÜBERHAUPT EINE NACHRICHT BEKOMMT
 * Nur ein Betrieb, der per Double-Opt-in ausdrücklich auch WhatsApp an genau
 * diese Nummer erlaubt hat (AkquiseEinwilligung, Wortlaut v2wa). Das Gate
 * fragt das vor jeder Nachricht neu (Kanal whatsapp, Bedingung einwilligung).
 * Ohne Einwilligung gibt es keinen Weg hierher -- auch nicht von Hand.
 *
 * WARUM VORLAGEN
 * Meta lässt Nachrichten, die ein Betrieb von sich aus schickt, nur als
 * vorher genehmigte Vorlage zu. Die fünf Folge-Schritte stehen deshalb hier
 * als Vorlagen je Sprache; „Bei Meta anmelden“ reicht sie ein, „Stand
 * abrufen“ holt die Genehmigung. Erst APPROVED wird verschickt.
 *
 * GEHEIMES
 * Zugangsschlüssel und App-Geheimnis liegen verschlüsselt in settings
 * (Hosting::versiegeln, wie der Schlüssel des Briefdienstes) und erscheinen
 * nie wieder im Klartext. Nummern-ID und Konto-ID sind keine Geheimnisse.
 *
 * ANTWORTEN (wa-webhook.php)
 * „STOP“ und seine Geschwister sperren den Betrieb sofort für alle Kanäle.
 * Jede andere Antwort landet bei den Antworten der Akquise -- die Folge
 * pausiert, ab dann schreibt ein Mensch.
 */
final class WhatsAppCloud
{
    public const API = 'https://graph.facebook.com/v21.0';
    public const STOP = ['stop', 'basta', 'annulla', 'cancella', 'cancellami', 'disiscrivi', 'abmelden', 'unsubscribe', 'no grazie'];
    /** @var null|callable(string $methode, string $url, ?array $body, string $token): array{status:int, json:?array} Für die Kette. */
    public static $netz = null;

    /** Vorlagen: Schritt => Sprache => Text. {{1}} = Name des Betriebs, {{2}} = Link. */
    public const TEXTE = [
        1 => ['it' => 'Buongiorno, grazie per la conferma. Ecco l’analisi del sito di {{1}} e il suo spazio personale su Vecom Design: {{2}}',
              'de' => 'Guten Tag, danke für Ihre Bestätigung. Hier sind die Analyse der Website von {{1}} und Ihr persönlicher Bereich bei Vecom Design: {{2}}',
              'en' => 'Hello, thank you for confirming. Here are the analysis of the {{1}} website and your personal area at Vecom Design: {{2}}'],
        2 => ['it' => 'Buongiorno, ha già visto l’analisi di {{1}}? Se vuole, le mostriamo una bozza della nuova pagina iniziale, senza impegno. Il suo spazio personale: {{2}}',
              'de' => 'Guten Tag, haben Sie schon in die Analyse von {{1}} geschaut? Wenn Sie möchten, zeigen wir Ihnen eine Skizze der neuen Startseite, unverbindlich. Ihr persönlicher Bereich: {{2}}',
              'en' => 'Hello, have you had a look at the analysis for {{1}}? If you like, we can show you a sketch of a new home page, with no obligation. Your personal area: {{2}}'],
        3 => ['it' => 'Buongiorno, nel suo spazio personale trova esempi del nostro lavoro e, in due minuti, il prezzo per {{1}} prima di decidere: {{2}}',
              'de' => 'Guten Tag, in Ihrem persönlichen Bereich finden Sie Beispiele unserer Arbeit und in zwei Minuten den Preis für {{1}}, bevor Sie sich entscheiden: {{2}}',
              'en' => 'Hello, in your personal area you will find examples of our work and, in two minutes, the price for {{1}} before you decide: {{2}}'],
        4 => ['it' => 'Buongiorno, se le va parliamo un quarto d’ora del sito di {{1}}, al telefono o in video, senza impegno. Scelga un orario qui: {{2}}',
              'de' => 'Guten Tag, wenn Sie mögen, sprechen wir eine Viertelstunde über die Website von {{1}}, am Telefon oder per Video, unverbindlich. Einen Termin wählen Sie hier: {{2}}',
              'en' => 'Hello, if you like, we can talk for a quarter of an hour about the {{1}} website, by phone or video, with no obligation. Pick a time here: {{2}}'],
        5 => ['it' => 'Buongiorno, questo è il mio ultimo messaggio sul sito di {{1}}. Il suo spazio personale resta disponibile qui: {{2}}',
              'de' => 'Guten Tag, das ist meine letzte Nachricht zur Website von {{1}}. Ihr persönlicher Bereich bleibt hier erreichbar: {{2}}',
              'en' => 'Hello, this is my last message about the {{1}} website. Your personal area remains available here: {{2}}'],
    ];
    public const FUSS = ['it' => 'Risponda STOP per non ricevere altri messaggi.', 'de' => 'Antworten Sie STOP, um keine Nachrichten mehr zu bekommen.', 'en' => 'Reply STOP to receive no further messages.'];

    /* ------------------------------ Einstellungen ------------------------ */

    public static function einstellungen(): array
    {
        return [
            'nummer_id' => AkquiseGate::einstellung('wa_nummer_id', ''),
            'konto_id' => AkquiseGate::einstellung('wa_konto_id', ''),
            'token' => self::geheim('wa_token') !== '',
            'app_geheim' => self::geheim('wa_app_geheim') !== '',
            'pruefwort' => self::pruefwort(),
            'webhook' => rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/wa-webhook.php',
        ];
    }

    public static function bereit(): bool
    {
        $e = self::einstellungen();
        return $e['nummer_id'] !== '' && $e['konto_id'] !== '' && $e['token'];
    }

    /** Leere Felder lassen den gespeicherten Wert stehen -- so muss niemand den Schlüssel ein zweites Mal eintippen. */
    public static function speichern(array $d): void
    {
        foreach (['wa_nummer_id' => 'nummer_id', 'wa_konto_id' => 'konto_id'] as $k => $feld) {
            $v = preg_replace('~\D~', '', (string) ($d[$feld] ?? '')) ?? '';
            if ($v !== '' || !empty($d['leeren'])) { AkquiseGate::setzen($k, mb_substr($v, 0, 30)); }
        }
        foreach (['wa_token' => 'token', 'wa_app_geheim' => 'app_geheim'] as $k => $feld) {
            $v = trim((string) ($d[$feld] ?? ''));
            if ($v !== '') { self::geheimSetzen($k, $v); }
        }
    }

    private static function geheim(string $k): string
    {
        $blob = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], '');
        if ($blob === '') { return ''; }
        require_once __DIR__ . '/Hosting.php';
        return (string) (Hosting::entsiegeln($blob)['wert'] ?? '');
    }

    private static function geheimSetzen(string $k, string $wert): void
    {
        require_once __DIR__ . '/Hosting.php';
        $blob = (string) Hosting::versiegeln(['wert' => $wert]);
        if ($blob === '') { throw new RuntimeException('Der Schlüssel ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).'); }
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $blob]);
    }

    /** Das Prüfwort für die Webhook-Anmeldung bei Meta -- einmal erzeugt, dann fest. */
    public static function pruefwort(): string
    {
        $w = AkquiseGate::einstellung('wa_pruefwort', '');
        if ($w === '') { $w = bin2hex(random_bytes(12)); AkquiseGate::setzen('wa_pruefwort', $w); }
        return $w;
    }

    /* ------------------------------ Netz --------------------------------- */

    /** @return array{status:int, json:?array} */
    private static function anfrage(string $methode, string $url, ?array $body = null): array
    {
        $token = self::geheim('wa_token');
        if (self::$netz) { return (self::$netz)($methode, $url, $body, $token); }
        if ($token === '') { return ['status' => 0, 'json' => ['error' => ['message' => 'Kein Zugangsschlüssel hinterlegt.']]]; }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CUSTOMREQUEST => $methode,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json']]);
        if ($body !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'json' => is_string($roh) ? (json_decode($roh, true) ?: null) : null];
    }

    /* ------------------------------ Vorlagen ----------------------------- */

    public static function vorlageName(int $schritt, string $sprache): string { return 'vecom_folge' . $schritt . '_' . $sprache; }

    /** Fehlende Vorlagen anlegen; eine geänderte Vorlage (neuer Text hier) geht zurück auf „neu“. */
    public static function vorlagenAnlegen(): void
    {
        foreach (self::TEXTE as $schritt => $je) {
            foreach ($je as $sp => $text) {
                $alt = Db::one('SELECT * FROM akq_wa_vorlagen WHERE schritt = ? AND sprache = ?', [$schritt, $sp]);
                if (!$alt) {
                    Db::insert('akq_wa_vorlagen', ['schritt' => $schritt, 'sprache' => $sp, 'name' => self::vorlageName($schritt, $sp), 'text' => $text]);
                } elseif ($alt['text'] !== $text) {
                    Db::update('akq_wa_vorlagen', (int) $alt['id'], ['text' => $text, 'meta_status' => 'neu', 'meta_id' => null, 'meta_grund' => null, 'geaendert_am' => date('Y-m-d H:i:s')]);
                }
            }
        }
    }

    /** @return array<int, array<string, array>> */
    public static function vorlagen(): array
    {
        self::vorlagenAnlegen();
        $aus = [];
        foreach (Db::all('SELECT * FROM akq_wa_vorlagen ORDER BY schritt, FIELD(sprache, "it", "de", "en")') as $z) { $aus[(int) $z['schritt']][(string) $z['sprache']] = $z; }
        return $aus;
    }

    /**
     * Neue oder abgelehnte Vorlagen bei Meta einreichen. Vorher läuft derselbe
     * Textprüfer wie für jede Mail (Angstmache, Preise, erfundene Zahlen).
     * @return array{eingereicht:int, fehler:list<string>}
     */
    public static function anmelden(): array
    {
        if (!self::bereit()) { throw new RuntimeException('WhatsApp ist noch nicht eingerichtet (Nummern-ID, Konto-ID, Schlüssel).'); }
        $n = 0; $fehler = [];
        foreach (self::vorlagen() as $schritt => $je) {
            foreach ($je as $sp => $v) {
                if (!in_array($v['meta_status'], ['neu', 'fehler', 'REJECTED'], true)) { continue; }
                $probe = strtr($v['text'], ['{{1}}' => 'Trattoria Esempio', '{{2}}' => 'https://vecom-design.it/zugang.php?t=esempio']) . "\n" . self::FUSS[$sp];
                $maengel = array_values(array_filter(AkquiseText::pruefen('WhatsApp', $probe, $sp, [], 'whatsapp'), static fn($m) => !str_contains($m, 'Hinweis, wie man')));
                if ($maengel) { $fehler[] = $v['name'] . ': ' . $maengel[0]; continue; }
                $r = self::anfrage('POST', self::API . '/' . AkquiseGate::einstellung('wa_konto_id') . '/message_templates', [
                    'name' => $v['name'], 'language' => $sp, 'category' => 'MARKETING',
                    'components' => [
                        ['type' => 'BODY', 'text' => $v['text'], 'example' => ['body_text' => [['Trattoria Esempio', 'https://vecom-design.it/zugang.php?t=esempio']]]],
                        ['type' => 'FOOTER', 'text' => self::FUSS[$sp]],
                    ],
                ]);
                if ($r['status'] >= 200 && $r['status'] < 300 && !empty($r['json']['id'])) {
                    Db::update('akq_wa_vorlagen', (int) $v['id'], ['meta_status' => (string) ($r['json']['status'] ?? 'PENDING'), 'meta_id' => (string) $r['json']['id'], 'meta_grund' => null]);
                    $n++;
                } else {
                    $grund = mb_substr((string) ($r['json']['error']['error_user_msg'] ?? $r['json']['error']['message'] ?? ('HTTP ' . $r['status'])), 0, 255);
                    Db::update('akq_wa_vorlagen', (int) $v['id'], ['meta_status' => 'fehler', 'meta_grund' => $grund]);
                    $fehler[] = $v['name'] . ': ' . $grund;
                }
            }
        }
        Akquise::protokoll(null, 'whatsapp', 'WhatsApp-Vorlagen bei Meta eingereicht: ' . $n . ($fehler ? ' · ' . count($fehler) . ' mit Fehler' : ''));
        return ['eingereicht' => $n, 'fehler' => $fehler];
    }

    /** Genehmigungsstand bei Meta abrufen. @return int geänderte Vorlagen */
    public static function standAbrufen(): int
    {
        if (!self::bereit()) { return 0; }
        $r = self::anfrage('GET', self::API . '/' . AkquiseGate::einstellung('wa_konto_id') . '/message_templates?fields=name,status,language,rejected_reason&limit=100');
        $n = 0;
        foreach ((array) ($r['json']['data'] ?? []) as $t) {
            $v = Db::one('SELECT * FROM akq_wa_vorlagen WHERE name = ? AND sprache = ?', [(string) ($t['name'] ?? ''), substr((string) ($t['language'] ?? ''), 0, 2)]);
            if (!$v || $v['meta_status'] === (string) $t['status']) { continue; }
            Db::update('akq_wa_vorlagen', (int) $v['id'], ['meta_status' => mb_substr((string) $t['status'], 0, 20),
                'meta_grund' => ($t['rejected_reason'] ?? '') !== '' && ($t['rejected_reason'] ?? '') !== 'NONE' ? mb_substr((string) $t['rejected_reason'], 0, 255) : null]);
            $n++;
        }
        return $n;
    }

    /* ------------------------------ Senden -------------------------------- */

    /**
     * Folge-Schritt per WhatsApp. Prüft selbst noch einmal Einwilligung und
     * Gate -- wer diese Methode ruft, kann nichts daran vorbei schicken.
     * @return array{ok:bool, simuliert?:bool, id?:int, grund?:string}
     */
    public static function folgeSenden(array $f, int $schritt, string $sprache, string $link): array
    {
        if (!AkquiseGate::einwilligungDeckt($f, 'whatsapp')) { return ['ok' => false, 'grund' => 'Keine WhatsApp-Einwilligung.']; }
        $gate = AkquiseGate::pruefen($f, 'whatsapp');
        if ($gate['status'] !== AkquiseGate::ERLAUBT) { return ['ok' => false, 'grund' => 'Gate: ' . ($gate['gruende'][0] ?? $gate['status'])]; }
        $v = Db::one("SELECT * FROM akq_wa_vorlagen WHERE schritt = ? AND sprache = ? AND meta_status = 'APPROVED'", [$schritt, $sprache]);
        if (!$v) { return ['ok' => false, 'grund' => 'WhatsApp-Vorlage ' . $schritt . ' (' . strtoupper($sprache) . ') ist bei Meta nicht genehmigt.']; }
        $an = preg_replace('~\D~', '', (string) $f['whatsapp']) ?? '';
        $actor = class_exists('Auth', false) && Auth::angemeldet() ? Auth::name() : 'System';
        if (AkquiseGate::testbetrieb()) {
            $id = (int) Db::insert('akq_versand', ['firma_id' => (int) $f['id'], 'kanal' => 'whatsapp', 'an' => '+' . $an, 'status' => 'simuliert',
                'compliance' => $gate['status'], 'grund' => 'Testbetrieb — WhatsApp ' . $v['name'] . ' nur simuliert', 'abmelde_token' => bin2hex(random_bytes(20)), 'actor' => $actor]);
            Akquise::protokoll((int) $f['id'], 'testbetrieb', 'Testbetrieb: WhatsApp an +' . $an . ' nur simuliert (' . $v['name'] . ')', ['versand' => $id]);
            return ['ok' => true, 'simuliert' => true, 'id' => $id];
        }
        $r = self::anfrage('POST', self::API . '/' . AkquiseGate::einstellung('wa_nummer_id') . '/messages', [
            'messaging_product' => 'whatsapp', 'to' => $an, 'type' => 'template',
            'template' => ['name' => $v['name'], 'language' => ['code' => $sprache],
                'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr((string) $f['name'], 0, 60)], ['type' => 'text', 'text' => $link]]]]],
        ]);
        $ok = $r['status'] >= 200 && $r['status'] < 300 && !empty($r['json']['messages'][0]['id']);
        $id = (int) Db::insert('akq_versand', ['firma_id' => (int) $f['id'], 'kanal' => 'whatsapp', 'an' => '+' . $an, 'status' => $ok ? 'gesendet' : 'fehler',
            'compliance' => $gate['status'], 'grund' => $ok ? 'WhatsApp ' . $v['name'] . ' · ' . mb_substr((string) $r['json']['messages'][0]['id'], 0, 120)
                : mb_substr('WhatsApp abgelehnt: ' . (string) ($r['json']['error']['message'] ?? ('HTTP ' . $r['status'])), 0, 255),
            'abmelde_token' => bin2hex(random_bytes(20)), 'actor' => $actor]);
        return $ok ? ['ok' => true, 'id' => $id] : ['ok' => false, 'id' => $id, 'grund' => 'Meta hat die Nachricht nicht angenommen.'];
    }

    /* ------------------------------ Webhook ------------------------------- */

    public static function signaturGut(string $roh, string $kopf): bool
    {
        $geheim = self::geheim('wa_app_geheim');
        if ($geheim === '' || !str_starts_with($kopf, 'sha256=')) { return false; }
        return hash_equals('sha256=' . hash_hmac('sha256', $roh, $geheim), $kopf);
    }

    /** Eingehende Nachrichten und Zustellfehler. @return int verarbeitete Nachrichten */
    public static function verarbeiten(array $nutzlast): int
    {
        $n = 0;
        foreach ((array) ($nutzlast['entry'] ?? []) as $eintrag) {
            foreach ((array) ($eintrag['changes'] ?? []) as $ae) {
                $wert = (array) ($ae['value'] ?? []);
                foreach ((array) ($wert['messages'] ?? []) as $m) { if (self::nachricht((array) $m)) { $n++; } }
                foreach ((array) ($wert['statuses'] ?? []) as $s) {
                    if (($s['status'] ?? '') === 'failed') {
                        $f = self::firmaZurNummer((string) ($s['recipient_id'] ?? ''));
                        if ($f) { Akquise::protokoll((int) $f['id'], 'versand_fehler', 'WhatsApp nicht zugestellt: ' . mb_substr((string) ($s['errors'][0]['title'] ?? 'unbekannt'), 0, 150)); }
                    }
                }
            }
        }
        return $n;
    }

    private static function firmaZurNummer(string $ziffern): ?array
    {
        $ziffern = preg_replace('~\D~', '', $ziffern) ?? '';
        if (strlen($ziffern) < 8) { return null; }
        foreach (Db::all("SELECT * FROM akq_firmen WHERE whatsapp IS NOT NULL AND whatsapp <> ''") as $f) {
            if ((preg_replace('~\D~', '', (string) $f['whatsapp']) ?? '') === $ziffern) { return $f; }
        }
        return null;
    }

    private static function nachricht(array $m): bool
    {
        $f = self::firmaZurNummer((string) ($m['from'] ?? ''));
        if (!$f) { return false; }
        $text = trim((string) ($m['text']['body'] ?? $m['button']['text'] ?? $m['interactive']['button_reply']['title'] ?? ''));
        $klein = mb_strtolower($text);
        foreach (self::STOP as $w) {
            if ($klein === $w || str_starts_with($klein, $w . ' ') || str_starts_with($klein, $w . '.') || str_starts_with($klein, $w . '!')) {
                AkquiseGate::sperren((int) $f['id'], 'Per WhatsApp abgemeldet („' . mb_substr($text, 0, 40) . '“)', 'abmeldung');
                Akquise::protokoll((int) $f['id'], 'abmeldung', 'Per WhatsApp „' . mb_substr($text, 0, 40) . '“ geantwortet — gesperrt, keine weiteren Nachrichten.');
                try { Events::melden('akquise_abmeldung', 'Akquise: ' . $f['name'] . ' hat sich per WhatsApp abgemeldet', 'info', null, 'akquise/' . $f['id']); } catch (Throwable $e) { }
                return true;
            }
        }
        $klasse = AkquiseText::klassifizieren('', $text);
        Db::insert('akq_antworten', ['firma_id' => (int) $f['id'], 'eingang_am' => date('Y-m-d H:i:s'), 'von' => '+' . preg_replace('~\D~', '', (string) $m['from']),
            'betreff' => 'WhatsApp', 'text' => mb_substr($text !== '' ? $text : '[' . (string) ($m['type'] ?? 'Nachricht') . ']', 0, 5000), 'klasse' => $klasse]);
        Db::update('akq_firmen', (int) $f['id'], ['antwort_status' => $klasse, 'kontakt_status' => in_array((string) $f['kontakt_status'], ['kunde'], true) ? $f['kontakt_status'] : 'geantwortet']);
        Akquise::protokoll((int) $f['id'], 'antwort', 'Antwort per WhatsApp: „' . mb_substr($text, 0, 120) . '“');
        try { Events::melden('akquise_antwort', 'WhatsApp-Antwort von ' . $f['name'], 'gut', mb_substr($text, 0, 200), 'akquise/' . $f['id']); } catch (Throwable $e) { }
        return true;
    }
}
