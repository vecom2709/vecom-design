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
            'anzeige' => AkquiseGate::einstellung('wa_anzeige', ''),
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
        foreach (['wa_nummer_id' => 'nummer_id', 'wa_konto_id' => 'konto_id', 'wa_anzeige' => 'anzeige'] as $k => $feld) {
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
        /* Not-Aus (AI Office Stufe 0, 06.10.2026): Assistent und Folge-Vorlagen schweigen,
           solange er steht — eine WhatsApp-Antwort Stunden später ist keine Antwort mehr,
           deshalb wird hier nichts gesammelt, nur nicht gesendet. */
        if ($methode === 'POST' && str_ends_with($url, '/messages') && class_exists('Automation') && Automation::ausgangGesperrt()) {
            return ['status' => 0, 'json' => ['error' => ['message' => 'Not-Aus — nichts verschickt.']]];
        }
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

    /* ------------------------------ Antworten im Gespräch ------------------- */

    /** Freier Text -- nur als Antwort, solange der Betrieb in den letzten 24 Stunden selbst geschrieben hat (Metas Regel). */
    public static function textSenden(string $an, string $text): bool
    {
        $r = self::anfrage('POST', self::API . '/' . AkquiseGate::einstellung('wa_nummer_id') . '/messages',
            ['messaging_product' => 'whatsapp', 'to' => $an, 'type' => 'text', 'text' => ['body' => mb_substr($text, 0, 4000), 'preview_url' => true]]);
        return $r['status'] >= 200 && $r['status'] < 300;
    }

    /** Zwei oder drei Antwortknöpfe. @param array<string,string> $knoepfe id => Beschriftung (höchstens 20 Zeichen) */
    public static function knoepfeSenden(string $an, string $text, array $knoepfe): bool
    {
        $b = [];
        foreach (array_slice($knoepfe, 0, 3, true) as $id => $titel) { $b[] = ['type' => 'reply', 'reply' => ['id' => (string) $id, 'title' => mb_substr($titel, 0, 20)]]; }
        $r = self::anfrage('POST', self::API . '/' . AkquiseGate::einstellung('wa_nummer_id') . '/messages',
            ['messaging_product' => 'whatsapp', 'to' => $an, 'type' => 'interactive', 'interactive' => ['type' => 'button', 'body' => ['text' => mb_substr($text, 0, 1024)], 'action' => ['buttons' => $b]]]);
        return $r['status'] >= 200 && $r['status'] < 300;
    }

    /** Sätze des Assistenten. */
    public const ASSISTENT = [
        'it' => ['hallo' => 'Buongiorno da Vecom Design! Mi scriva l’indirizzo del suo sito (per es. trattoria-rossi.it): le mando subito una prima analisi gratuita.',
                 'adresse' => 'Non riesco a leggere l’indirizzo. Me lo riscriva così: trattoria-rossi.it', 'warten' => 'Un attimo, riprovo tra pochi secondi: mi riscriva l’indirizzo.',
                 'ergebnis' => 'Ecco la prima analisi di {host}:', 'stand' => ['gut' => 'va bene', 'hinweis' => 'da migliorare', 'schlecht' => 'problema'],
                 'frage' => 'Vuole l’analisi completa con i consigli e il suo spazio personale su Vecom Design? Con «Sì» accetta che Vecom Design (Uwe Vetter) le scriva su WhatsApp a questo numero sul suo sito e su offerte adatte. Può revocare in qualsiasi momento scrivendo STOP.',
                 'ja' => 'Sì, volentieri', 'nein' => 'No, grazie', 'email' => 'Grazie! Per aprire il suo spazio personale mi scriva la sua e-mail. Se preferisce di no, scriva «salta».',
                 'emailFalsch' => 'Questa e-mail non mi sembra giusta. Me la riscriva, oppure «salta».', 'fertig' => 'Ecco il suo spazio personale su Vecom Design, già pronto: {link}' . "\n\n" . 'L’analisi completa arriva a breve. Per domande risponda qui.',
                 'fertigOhne' => 'Perfetto! L’analisi completa arriva a breve qui su WhatsApp. Per domande risponda qui.', 'nichtJa' => 'Va bene, nessun problema. Se cambia idea, ci scriva quando vuole.', 'stop' => 'Va bene, non le scriveremo più.'],
        'de' => ['hallo' => 'Guten Tag von Vecom Design! Schreiben Sie mir die Adresse Ihrer Website (z. B. trattoria-rossi.it), dann schicke ich Ihnen sofort eine erste kostenlose Analyse.',
                 'adresse' => 'Die Adresse kann ich nicht lesen. Bitte so schreiben: trattoria-rossi.it', 'warten' => 'Einen Moment, bitte in ein paar Sekunden die Adresse noch einmal schicken.',
                 'ergebnis' => 'Hier die erste Analyse von {host}:', 'stand' => ['gut' => 'gut', 'hinweis' => 'verbesserbar', 'schlecht' => 'Problem'],
                 'frage' => 'Möchten Sie die ausführliche Analyse mit Tipps und Ihren persönlichen Bereich bei Vecom Design? Mit „Ja“ erlauben Sie, dass Vecom Design (Uwe Vetter) Ihnen per WhatsApp an diese Nummer Nachrichten zu Ihrer Website und zu passenden Angeboten schickt. Sie können das jederzeit mit STOP widerrufen.',
                 'ja' => 'Ja, gern', 'nein' => 'Nein, danke', 'email' => 'Danke! Für Ihren persönlichen Bereich schreiben Sie mir bitte Ihre E-Mail-Adresse. Wenn Sie das nicht möchten, schreiben Sie „weiter“.',
                 'emailFalsch' => 'Die E-Mail-Adresse sieht nicht richtig aus. Bitte noch einmal, oder „weiter“.', 'fertig' => 'Hier ist Ihr persönlicher Bereich bei Vecom Design, schon vorbereitet: {link}' . "\n\n" . 'Die ausführliche Analyse kommt in Kürze. Bei Fragen einfach hier antworten.',
                 'fertigOhne' => 'Prima! Die ausführliche Analyse kommt in Kürze hier per WhatsApp. Bei Fragen einfach hier antworten.', 'nichtJa' => 'In Ordnung. Wenn Sie es sich anders überlegen, schreiben Sie uns jederzeit.', 'stop' => 'In Ordnung, wir schreiben Ihnen nicht mehr.'],
        'en' => ['hallo' => 'Hello from Vecom Design! Send me your website address (e.g. trattoria-rossi.it) and I will send you a first free analysis right away.',
                 'adresse' => 'I cannot read the address. Please write it like this: trattoria-rossi.it', 'warten' => 'One moment, please send the address again in a few seconds.',
                 'ergebnis' => 'Here is the first analysis of {host}:', 'stand' => ['gut' => 'good', 'hinweis' => 'could be better', 'schlecht' => 'problem'],
                 'frage' => 'Would you like the full analysis with tips and your personal area at Vecom Design? With “Yes” you agree that Vecom Design (Uwe Vetter) may message you on WhatsApp at this number about your website and suitable offers. You can withdraw at any time by writing STOP.',
                 'ja' => 'Yes, please', 'nein' => 'No, thanks', 'email' => 'Thank you! For your personal area, please send me your email address. If you’d rather not, write “skip”.',
                 'emailFalsch' => 'That email doesn’t look right. Please send it again, or “skip”.', 'fertig' => 'Here is your personal area at Vecom Design, already set up: {link}' . "\n\n" . 'The full analysis follows shortly. Reply here with any questions.',
                 'fertigOhne' => 'Great! The full analysis follows shortly here on WhatsApp. Reply here with any questions.', 'nichtJa' => 'All right. If you change your mind, write to us any time.', 'stop' => 'All right, we will not write to you again.'],
    ];
    public const WORTLAUT_CHAT = 'v3wachat';

    private static function sprache(string $text, string $vorher): string
    {
        $t = mb_strtolower($text);
        if (preg_match('~\b(analyse|kostenlos|guten tag|hallo|ich|website)\b~u', $t) && !preg_match('~\b(analisi|sito|buongiorno)\b~u', $t)) { return 'de'; }
        if (preg_match('~\b(analysis|free|hello|would like|my website)\b~u', $t) && !preg_match('~\b(analisi|sito)\b~u', $t)) { return 'en'; }
        return $vorher !== '' ? $vorher : 'it';
    }

    /**
     * Der WhatsApp-Assistent (28.09.2026, Uwe: Ja zu Z1). Nur Antworten auf
     * jemanden, der gerade selbst geschrieben hat. Die Einwilligung entsteht
     * erst mit dem Knopf „Sì“ unter dem vollständigen Wortlaut -- die Nachricht
     * mit Zeitstempel und Nachrichten-ID ist der Beleg.
     */
    private static function assistent(array $m, string $ziffern, ?array $g): bool
    {
        $text = trim((string) ($m['text']['body'] ?? $m['button']['text'] ?? $m['interactive']['button_reply']['title'] ?? ''));
        $knopf = (string) ($m['interactive']['button_reply']['id'] ?? '');
        if (!$g) {
            Db::run('INSERT IGNORE INTO akq_wa_gespraeche (nummer, sprache, stand) VALUES (?, ?, ?)', [$ziffern, self::sprache($text, ''), 'neu']);
            $g = Db::one('SELECT * FROM akq_wa_gespraeche WHERE nummer = ?', [$ziffern]);
        }
        $sp = (string) $g['sprache'];
        $A = self::ASSISTENT[$sp] ?? self::ASSISTENT['it'];
        $setzen = static fn(array $w) => Db::update('akq_wa_gespraeche', (int) $g['id'], $w + ['letzte_am' => date('Y-m-d H:i:s')]);
        $klein = mb_strtolower($text);
        foreach (self::STOP as $w) {
            if ($klein === $w || str_starts_with($klein, $w . ' ')) {
                $setzen(['stand' => 'beendet']);
                if (!empty($g['firma_id'])) { AkquiseGate::sperren((int) $g['firma_id'], 'Im WhatsApp-Assistenten „' . mb_substr($text, 0, 30) . '“ geschrieben', 'abmeldung'); }
                self::textSenden($ziffern, $A['stop']);
                return true;
            }
        }
        /* Eine Adresse im Text? Dann gleich prüfen -- auch schon in der ersten Nachricht. */
        $adresse = preg_match('~((?:https?://)?(?:www\.)?[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9\-]+)*\.[a-z]{2,}(?:/\S*)?)~iu', $text, $am) ? $am[1] : '';
        if (in_array($g['stand'], ['neu', 'url'], true)) {
            if ($adresse === '') {
                $setzen(['stand' => 'url']);
                self::textSenden($ziffern, $g['stand'] === 'neu' ? $A['hallo'] : $A['adresse']);
                return true;
            }
            require_once __DIR__ . '/PartnerSeite.php';
            $kc = PartnerSeite::kurzcheck($adresse, 'wa:' . $ziffern);
            if (!$kc['ok']) { $setzen(['stand' => 'url']); self::textSenden($ziffern, $kc['grund'] === 'adresse' ? $A['adresse'] : $A['warten']); return true; }
            $P = Texte::PARTNER_CHECK['punkte'];
            $zeilen = [];
            foreach ($kc['punkte'] as $p) {
                if (!isset($P[$p['was']])) { continue; }
                $zeilen[] = ['gut' => '🟢', 'hinweis' => '🟡', 'schlecht' => '🔴'][$p['stand']] . ' ' . Texte::h($P[$p['was']]['titel'], $sp) . ': ' . ($A['stand'][$p['stand']] ?? '');
            }
            $setzen(['stand' => 'ja', 'url' => mb_substr((string) $kc['url'], 0, 255), 'ampel' => json_encode($kc['punkte'])]);
            /* Note und ausführlicher Bericht zum Anschauen (28.09.2026, A1–A10). */
            $zusatz = '';
            try {
                require_once __DIR__ . '/WebBericht.php';
                $tok = WebBericht::speichern($kc, null, 'whatsapp');
                $zusatz = "\n\n" . ['it' => 'Voto: ', 'de' => 'Note: ', 'en' => 'Score: '][$sp] . WebBericht::note($kc['punkte']) . '/100 · ' . WebBericht::stufe(WebBericht::note($kc['punkte']), $sp)
                    . "\n" . ['it' => 'Rapporto completo, con spiegazioni: ', 'de' => 'Ausführlicher Bericht mit Erklärungen: ', 'en' => 'Full report with explanations: '][$sp] . WebBericht::adresse($tok, $sp);
            } catch (Throwable $e) { }
            self::textSenden($ziffern, strtr($A['ergebnis'], ['{host}' => $kc['host']]) . "\n\n" . implode("\n", $zeilen) . $zusatz);
            self::knoepfeSenden($ziffern, $A['frage'], ['ja' => $A['ja'], 'nein' => $A['nein']]);
            return true;
        }
        if ($g['stand'] === 'ja') {
            $ja = $knopf === 'ja' || in_array($klein, ['sì', 'si', 'sì volentieri', 'ja', 'yes', 'ok'], true);
            if (!$ja) {
                $setzen(['stand' => 'beendet']);
                self::textSenden($ziffern, $A['nichtJa']);
                return true;
            }
            require_once __DIR__ . '/AkquiseKurz.php';
            $b = AkquiseKurz::betrieb((string) $g['url']);
            if ($b === null) { $setzen(['stand' => 'url']); self::textSenden($ziffern, $A['adresse']); return true; }
            $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$b['id']]);
            if (!$f || (int) $f['gesperrt'] === 1 || AkquiseGate::trifftSperrliste(['telefon' => '+' . $ziffern] + $f) !== null) {
                $setzen(['stand' => 'beendet']);
                self::textSenden($ziffern, $A['nichtJa']);
                return true;
            }
            $jetzt = date('Y-m-d H:i:s');
            $wortlaut = $A['frage'];
            $ewId = (int) Db::insert('akq_einwilligungen', ['firma_id' => $b['id'], 'link_token' => bin2hex(random_bytes(20)), 'quelle' => 'whatsapp', 'sprache' => $sp,
                'status' => 'bestaetigt', 'whatsapp' => '+' . $ziffern, 'wortlaut' => $wortlaut, 'wortlaut_version' => self::WORTLAUT_CHAT,
                'angefragt_am' => $jetzt, 'bestaetigt_am' => $jetzt]);
            $beleg = mb_substr('WhatsApp-Chat ' . date('d.m.Y H:i') . ': Knopf „' . ($text ?: 'Sì') . '“ unter dem Wortlaut, von +' . $ziffern . ' (Nachricht ' . mb_substr((string) ($m['id'] ?? ''), 0, 40) . ', Nachweis #' . $ewId . ')', 0, 255);
            $kanaele = array_filter(array_map('trim', explode(',', (string) ($f['einwilligung_kanaele'] ?? ''))));
            if (trim((string) $f['einwilligung']) === '') { $kanaele = []; }
            $kanaele[] = 'whatsapp';
            $neu = ['einwilligung' => $beleg, 'einwilligung_kanaele' => implode(',', array_values(array_unique($kanaele))), 'whatsapp' => '+' . $ziffern];
            Db::update('akq_firmen', $b['id'], $neu);
            Events::pruefspur('akquise_rechtsgrundlage', 'akq_firmen', $b['id'], ['einwilligung' => $f['einwilligung']], $neu);
            Akquise::protokoll($b['id'], 'einwilligung', 'Einwilligung im WhatsApp-Assistenten: WhatsApp an +' . $ziffern);
            AkquiseGate::statusSpeichern($b['id']);
            $setzen(['stand' => 'email', 'firma_id' => $b['id']]);
            try { Db::run("UPDATE web_berichte SET firma_id = ? WHERE host = ? AND firma_id IS NULL AND quelle = 'whatsapp' ORDER BY id DESC LIMIT 1", [$b['id'], $b['host']]); } catch (Throwable $e) { }
            try { Events::melden('akquise_einwilligung', 'WhatsApp-Assistent: ' . $f['name'] . ' hat eingewilligt', 'gut', '+' . $ziffern, 'akquise/' . $b['id']); } catch (Throwable $e) { }
            self::textSenden($ziffern, $A['email']);
            return true;
        }
        if ($g['stand'] === 'email') {
            $fid = (int) $g['firma_id'];
            $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$fid]);
            if (!$f) { $setzen(['stand' => 'beendet']); return true; }
            $ueberspringen = in_array($klein, ['salta', 'weiter', 'skip', 'no'], true);
            $email = Akquise::normEmail($text);
            if (!$ueberspringen && $email === null) { self::textSenden($ziffern, $A['emailFalsch']); return true; }
            require_once __DIR__ . '/AkquiseFolge.php';
            if ($email !== null) {
                /* Die E-Mail dient dem persönlichen Bereich, den er gerade verlangt hat. Werbung per E-Mail
                   deckt sie nicht (Kanäle bleiben „whatsapp“) -- dafür bräuchte es die Bestätigungsmail. */
                Db::update('akq_firmen', $fid, ['email' => $email]);
                $f['email'] = $email;
                require_once __DIR__ . '/Zugang.php';
                $link = Zugang::vorbereiten($email, $sp, $fid, (string) $f['name']);
                self::textSenden($ziffern, strtr($A['fertig'], ['{link}' => (string) $link]));
            } else {
                self::textSenden($ziffern, $A['fertigOhne']);
            }
            $setzen(['stand' => 'fertig']);
            try { AkquiseFolge::starten($fid); } catch (Throwable $e) { }
            return true;
        }
        return false;
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
        /* Der Assistent (Z1) zuerst: ein laufendes Gespräch, oder jemand ohne
           Betrieb bei uns schreibt -- dann fragt der Assistent nach der Website. */
        $ziffern = preg_replace('~\D~', '', (string) ($m['from'] ?? '')) ?? '';
        $gespraech = Db::one('SELECT * FROM akq_wa_gespraeche WHERE nummer = ?', [$ziffern]);
        $f = self::firmaZurNummer($ziffern);
        if (AkquiseGate::schalterSelbst('wa_assistent') && (($gespraech && !in_array($gespraech['stand'], ['fertig', 'beendet'], true)) || (!$f && !$gespraech))) {
            return self::assistent($m, $ziffern, $gespraech);
        }
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
