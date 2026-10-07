<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Ablauf.php';
require_once __DIR__ . '/Automation.php';

/**
 * AI Freigaben (AI Office Stufe 1, 06.10.2026).
 *
 * Uwe: Umfang „Was Claude vorschlägt“, Ausführen „Dieselbe Tat wie Ihr Knopf“,
 * Zurückstellen „morgen, 3 Tage, 1 Woche“, Telegram „nur RAUS, SCHWER nur in der
 * Verwaltung“.
 *
 * WAS EINE FREIGABE IST
 *
 * Ein Vorschlag mit allem, was man zum Entscheiden braucht: was passiert, wem,
 * wie es jetzt steht, wie es danach steht, Risiko (aus Ablauf::TRAGWEITE —
 * dieselbe Liste wie die Rückfragen), Kosten, Rückweg, Empfehlung. Genehmigt
 * wird immer dieselbe Tat, die Uwes Knopf in der Verwaltung auslöst: jede Art
 * unten ruft genau die Methode, die auch app/index.php ruft. Es gibt keinen
 * zweiten Weg für dasselbe.
 *
 * WAS SIE NICHT IST
 *
 * Kein Weg an Regeln vorbei. Die Methoden prüfen selbst, was sie immer prüfen
 * (Bausperre, Fragebogen vor Angebot, Betreff Pflicht). Eine Freigabe, die
 * daran scheitert, steht danach als „fehlgeschlagen“ da, mit dem Satz warum.
 */
final class Freigabe
{
    public const OFFEN = 'offen';
    public const ZURUECK = 'zurueckgestellt';
    public const LAEUFT = 'laeuft';

    /** Zurückstellen: Uwe wählt (Tage). */
    public const ZURUECK_TAGE = [1 => 'morgen', 3 => '3 Tage', 7 => '1 Woche'];

    /**
     * Arten: welche bestehende Tat, welche Daten Pflicht sind, was Uwe vor dem Genehmigen ändern darf.
     * @var array<string, array{tat:string, pflicht:list<string>, aenderbar:list<string>, wort:string}>
     */
    public const ARTEN = [
        'vorschau_frei'     => ['tat' => 'vorschau_frei',     'pflicht' => ['projekt'],        'aenderbar' => [],                  'wort' => 'Vorschau freischalten'],
        'angebot_senden'    => ['tat' => 'angebot_senden',    'pflicht' => ['angebot'],        'aenderbar' => [],                  'wort' => 'Angebot senden'],
        'kunde_nachricht'   => ['tat' => 'kunde_nachricht',   'pflicht' => ['kunde', 'text'],  'aenderbar' => ['betreff', 'text'], 'wort' => 'Nachricht an den Kunden'],
        'nachricht_senden'  => ['tat' => 'nachricht_senden',  'pflicht' => ['projekt', 'text'],'aenderbar' => ['betreff', 'text'], 'wort' => 'Nachricht zum Projekt'],
        'partner_nachricht' => ['tat' => 'partner_nachricht', 'pflicht' => ['partner', 'text'],'aenderbar' => ['text'],            'wort' => 'Nachricht an den Partner'],
        // AI Office Stufe 4 (07.10.2026, Uwe: alle vier angekreuzt) — jede ruft dieselbe Methode wie ihr Knopf.
        'bewertung_bitten'      => ['tat' => 'bewertung_bitten',      'pflicht' => ['kunde'],            'aenderbar' => [], 'wort' => 'Bitte um Google-Bewertung'],
        'fragebogen_einladen'   => ['tat' => 'fragebogen_einladen',   'pflicht' => ['projekt'],          'aenderbar' => [], 'wort' => 'Einladung zum Fragebogen'],
        'restzahlung_anfordern' => ['tat' => 'restzahlung_anfordern', 'pflicht' => ['projekt'],          'aenderbar' => [], 'wort' => 'Restzahlung anfordern'],
        'abo_anfordern'         => ['tat' => 'abo_anfordern',         'pflicht' => ['zahlung'],          'aenderbar' => [], 'wort' => 'Betreuungsrate anfordern'],
        'mahnung_schicken'      => ['tat' => 'mahnung_schicken',      'pflicht' => ['zahlung', 'stufe'], 'aenderbar' => [], 'wort' => 'Mahnung schicken'],
        // AI Office Stufe 5 (07.10.2026, Uwe: „Link über AI Freigaben“): der Download-Link fürs Exit-Paket.
        'exit_link_senden'      => ['tat' => 'exit_link_senden',      'pflicht' => ['paket'],            'aenderbar' => [], 'wort' => 'Exit-Paket-Link schicken'],
    ];

    /** Arten, die nur die Verwaltung selbst vorschlägt — nie Claude über den Connector. */
    public const NUR_VERWALTUNG = ['exit_link_senden'];

    /** Prüfnaht für die Kette: ersetzt die Telegram-Nachricht an Uwe. */
    public static $telegram = null;

    /* ------------------------------------------------------------ Vorschlagen */

    /**
     * Einen Vorschlag anlegen. Derselbe offene Vorschlag (Art + Daten) entsteht nur einmal.
     * @param array $meta titel, grund, ist, soll, kosten, auswirkung, rollback, empfehlung, von, system
     * @return int ID der Freigabe
     */
    public static function vorschlagen(string $art, array $daten, array $meta = []): int
    {
        $a = self::ARTEN[$art] ?? null;
        if ($a === null) { throw new InvalidArgumentException('Unbekannte Freigabe-Art: ' . $art); }
        foreach ($a['pflicht'] as $f) {
            if (!isset($daten[$f]) || trim((string) $daten[$f]) === '') { throw new InvalidArgumentException('Es fehlt: ' . $f); }
        }
        if (isset($daten['text'])) { $daten['text'] = mb_substr(trim((string) $daten['text']), 0, 8000); }
        if (isset($daten['betreff'])) { $daten['betreff'] = mb_substr(trim((string) $daten['betreff']), 0, 200); }
        $daten = self::norm($daten);
        $json = json_encode($daten, JSON_UNESCAPED_UNICODE);
        $finger = hash('sha256', $art . "\0" . $json);
        $schon = Db::wert("SELECT id FROM ai_freigaben WHERE fingerabdruck = ? AND status IN ('offen', 'zurueckgestellt', 'laeuft') LIMIT 1", [$finger], null);
        if ($schon !== null) { return (int) $schon; }

        [$kunde, $projekt, $partner] = self::bezug($art, $daten);
        $s = static fn(string $k, int $max = 2000): ?string => isset($meta[$k]) && trim((string) $meta[$k]) !== '' ? mb_substr(trim((string) $meta[$k]), 0, $max) : null;
        $id = (int) Db::insert('ai_freigaben', [
            'art' => $art, 'daten' => $json, 'fingerabdruck' => $finger,
            'titel' => mb_substr((string) ($s('titel', 255) ?? $a['wort']), 0, 255),
            'kunde_id' => $kunde, 'projekt_id' => $projekt, 'partner_id' => $partner,
            'system_name' => $s('system', 40) ?? 'Verwaltung',
            'grund' => $s('grund'), 'ist' => $s('ist'), 'soll' => $s('soll'), 'kosten' => $s('kosten', 255),
            'auswirkung' => $s('auswirkung'), 'rollback' => $s('rollback'), 'empfehlung' => $s('empfehlung'),
            'vorgeschlagen_von' => $s('von', 80) ?? 'Claude',
        ]);
        self::spur('freigabe_vorgeschlagen', $id, [], ['art' => $art, 'von' => $meta['von'] ?? 'Claude']);
        try {
            require_once __DIR__ . '/Events.php';
            Events::melden('ai_freigabe', 'Freigabe wartet: ' . mb_substr((string) ($s('titel', 200) ?? $a['wort']), 0, 200), 'hinweis',
                $s('grund', 400), '/ai-freigaben/' . $id);
        } catch (Throwable $e) { }
        self::telegramMelden($id);
        return $id;
    }

    /** Nummern als Zahl, Schlüssel sortiert — damit „5“ und 5 derselbe Vorschlag sind. */
    private static function norm(array $d): array
    {
        foreach (['projekt', 'kunde', 'partner', 'angebot', 'zahlung', 'stufe', 'paket'] as $k) { if (isset($d[$k])) { $d[$k] = (int) $d[$k]; } }
        ksort($d);
        return $d;
    }

    /** @return array{0:?int,1:?int,2:?int} Kunde, Projekt, Partner */
    private static function bezug(string $art, array $d): array
    {
        $kunde = isset($d['kunde']) ? (int) $d['kunde'] : null;
        $projekt = isset($d['projekt']) ? (int) $d['projekt'] : null;
        $partner = isset($d['partner']) ? (int) $d['partner'] : null;
        try {
            if ($projekt !== null && $kunde === null) { $kunde = (int) Db::wert('SELECT customer_id FROM projects WHERE id = ?', [$projekt], 0) ?: null; }
            if (isset($d['paket'])) { $kunde = (int) Db::wert('SELECT customer_id FROM exit_pakete WHERE id = ?', [(int) $d['paket']], 0) ?: $kunde; }
            if (isset($d['angebot'])) { $kunde = (int) Db::wert('SELECT customer_id FROM angebote WHERE id = ?', [(int) $d['angebot']], 0) ?: $kunde; }
            if (isset($d['zahlung']) && $kunde === null) {
                $kunde = (int) Db::wert('SELECT COALESCE(o.customer_id, a.customer_id) FROM payments p LEFT JOIN orders o ON o.id = p.order_id
                                          LEFT JOIN abos a ON a.id = p.abo_id WHERE p.id = ?', [(int) $d['zahlung']], 0) ?: null;
            }
        } catch (Throwable $e) { }
        return [$kunde, $projekt, $partner];
    }

    /* ----------------------------------------------------------------- Lesen */

    /** Offen = offen oder zurückgestellt und fällig. @return list<array> */
    public static function offen(int $grenze = 100): array
    {
        try {
            return Db::all("SELECT * FROM ai_freigaben
                             WHERE status = 'offen' OR (status = 'zurueckgestellt' AND zurueck_bis <= NOW())
                             ORDER BY id LIMIT " . max(1, min(500, $grenze)));
        } catch (Throwable $e) { return []; }
    }

    /** @return list<array> zurückgestellt, noch nicht fällig */
    public static function ruhend(): array
    {
        try { return Db::all("SELECT * FROM ai_freigaben WHERE status = 'zurueckgestellt' AND zurueck_bis > NOW() ORDER BY zurueck_bis LIMIT 100"); }
        catch (Throwable $e) { return []; }
    }

    /** @return list<array> die letzten Entscheidungen */
    public static function entschieden(int $anzahl = 20): array
    {
        try { return Db::all("SELECT * FROM ai_freigaben WHERE status NOT IN ('offen', 'zurueckgestellt', 'laeuft') ORDER BY entschieden_am DESC, id DESC LIMIT " . max(1, min(100, $anzahl))); }
        catch (Throwable $e) { return []; }
    }

    public static function anzahlOffen(): int
    {
        try { return (int) Db::wert("SELECT COUNT(*) FROM ai_freigaben WHERE status = 'offen' OR (status = 'zurueckgestellt' AND zurueck_bis <= NOW())", [], 0); }
        catch (Throwable $e) { return 0; }
    }

    public static function laden(int $id): ?array
    {
        try { return Db::one('SELECT * FROM ai_freigaben WHERE id = ?', [$id]) ?: null; } catch (Throwable $e) { return null; }
    }

    public static function daten(array $f): array { return json_decode((string) $f['daten'], true) ?: []; }

    /** Die Rückfrage der zugrunde liegenden Tat — Risiko-Stufe, Satz, Ja-Wort. */
    public static function rueckfrage(array $f): array
    {
        $tat = self::ARTEN[$f['art']]['tat'] ?? '';
        return Ablauf::rueckfrage($tat) ?? ['gewicht' => Ablauf::RAUS, 'frage' => 'Das geht an den Empfänger.', 'ja' => 'Ja, ausführen'];
    }

    /* ------------------------------------------------------------- Entscheiden */

    /**
     * Genehmigen: dieselbe Methode wie Uwes Knopf.
     * @param array $aenderung nur Felder aus 'aenderbar'
     * @param string $kanal verwaltung | telegram
     * @return array{ok:bool, text:string}
     */
    public static function genehmigen(int $id, string $wer, array $aenderung = [], string $kanal = 'verwaltung', ?int $userId = null): array
    {
        $f = self::laden($id);
        if (!$f || !self::entscheidbar($f)) { return ['ok' => false, 'text' => 'Diese Freigabe ist schon entschieden.']; }
        $art = self::ARTEN[$f['art']] ?? null;
        if ($art === null) { return ['ok' => false, 'text' => 'Diese Art kennt die Verwaltung nicht mehr.']; }
        if ($kanal === 'telegram' && self::rueckfrage($f)['gewicht'] !== Ablauf::RAUS) {
            return ['ok' => false, 'text' => 'Das wiegt schwer — genehmigen nur in der Verwaltung.'];
        }
        $d = self::daten($f);
        $geaendert = false;
        foreach ($art['aenderbar'] as $feld) {
            if (array_key_exists($feld, $aenderung)) {
                $neu = trim((string) $aenderung[$feld]);
                if ($neu !== trim((string) ($d[$feld] ?? ''))) { $d[$feld] = $neu; $geaendert = true; }
            }
        }
        // Atomar: nur wer offen → läuft kippt, führt aus. Ein zweiter Klick tut nichts Doppeltes.
        $n = Db::run("UPDATE ai_freigaben SET status = 'laeuft', entschieden_von = ?, entschieden_am = NOW(), kanal = ?, geaendert = ?, daten = ?
                       WHERE id = ? AND (status = 'offen' OR status = 'zurueckgestellt')",
            [mb_substr($wer, 0, 80), $kanal, $geaendert ? 1 : 0, json_encode($d, JSON_UNESCAPED_UNICODE), $id])->rowCount();
        if ($n !== 1) { return ['ok' => false, 'text' => 'Diese Freigabe ist schon entschieden.']; }

        /* Uwes Entscheidung ist ein Klick eines Menschen — auch wenn sie über den Telegram-Webhook
           kommt, der sonst als „automatisch“ gilt (Not-Aus, Automation::ausgangGesperrt). */
        $warAutomatisch = Automation::automatisch();
        $herkunft = Automation::herkunft();
        Automation::automatischZuruecksetzen();
        /* Mail-Spur (07.10.2026): Jede Mail dieser Tat trägt die Freigabe — so steht sie hier unter
           „Zuletzt entschieden“, in der Akte und in Telegram mit Text und Stand. */
        require_once __DIR__ . '/Mail.php';
        $vorherAusloeser = Mail::$ausloeser;
        Mail::$ausloeser = ['art' => 'freigabe', 'ref' => (string) $f['art'], 'id' => $id, 'wer' => $wer];
        try {
            $text = self::ausfuehren($f['art'], $d, $userId);
            $ok = true;
        } catch (Throwable $e) {
            $text = $e->getMessage() !== '' ? $e->getMessage() : 'Die Tat ließ sich nicht ausführen.';
            $ok = false;
        } finally {
            Mail::$ausloeser = $vorherAusloeser;
            if ($warAutomatisch) { Automation::automatischAb($herkunft); }
        }
        Db::run('UPDATE ai_freigaben SET status = ?, ergebnis = ? WHERE id = ?', [$ok ? 'ausgefuehrt' : 'fehlgeschlagen', mb_substr($text, 0, 1000), $id]);
        self::spur($ok ? 'freigabe_genehmigt' : 'freigabe_fehlgeschlagen', $id, ['art' => $f['art']], ['von' => $wer, 'kanal' => $kanal, 'geaendert' => $geaendert, 'ergebnis' => mb_substr($text, 0, 300)]);
        $post = '';
        try { require_once __DIR__ . '/MailSpur.php'; $post = MailSpur::kurz(MailSpur::zuFreigabe($id)); } catch (Throwable $e) { }
        return ['ok' => $ok, 'text' => $text, 'post' => $post];
    }

    /** Die Tat selbst — genau die Methode, die auch app/index.php für Uwes Knopf ruft. */
    private static function ausfuehren(string $art, array $d, ?int $userId): string
    {
        switch ($art) {
            case 'vorschau_frei':
                require_once __DIR__ . '/Nachricht.php';
                $p = Db::one('SELECT * FROM projects WHERE id = ?', [(int) $d['projekt']]);
                if (!$p) { throw new RuntimeException('Das Projekt gibt es nicht mehr.'); }
                return Nachricht::vorschauFreischalten((int) $d['projekt'])['text'];
            case 'angebot_senden':
                require_once __DIR__ . '/Angebot.php';
                if (!Angebot::senden((int) $d['angebot'])) { throw new RuntimeException('Das Angebot ging nicht raus — es ist kein Entwurf mehr oder hat keinen Betrag.'); }
                return 'Das Angebot ist raus.';
            case 'kunde_nachricht':
                require_once __DIR__ . '/Nachricht.php';
                Nachricht::anKunde((int) $d['kunde'], (string) $d['text'], (string) ($d['betreff'] ?? ''));
                return 'Nachricht ist raus — der Kunde bekommt sie per E-Mail.';
            case 'nachricht_senden':
                require_once __DIR__ . '/Nachricht.php';
                Nachricht::schreiben((int) $d['projekt'], (string) $d['text'], 'admin', (string) ($d['betreff'] ?? ''));
                return 'Nachricht ist raus — der Kunde bekommt sie auch per E-Mail.';
            case 'partner_nachricht':
                require_once __DIR__ . '/PartnerPost.php';
                try { PartnerPost::schreiben((int) $d['partner'], (string) $d['text'], 'vecom', $userId); }
                catch (InvalidArgumentException $e) { throw new RuntimeException('Die Nachricht ist leer.'); }
                return 'Antwort verschickt.';
            case 'bewertung_bitten':
                require_once __DIR__ . '/Nachricht.php';
                $r = Nachricht::bewertungBitten((int) $d['kunde']);
                if (!$r['ok']) { throw new RuntimeException($r['text']); }
                return $r['text'];
            case 'fragebogen_einladen':
                require_once __DIR__ . '/Onboarding.php';
                if (!Onboarding::einladen((int) $d['projekt'], true)) { throw new RuntimeException('Die Einladung ging nicht raus. Ist der Fragebogen schon abgeschlossen?'); }
                return 'Fragebogen verschickt.';
            case 'restzahlung_anfordern':
                require_once __DIR__ . '/Nachricht.php';
                if (!Nachricht::restzahlungAnfordern((int) $d['projekt'])) { throw new RuntimeException('Nichts zu tun: Entweder ist nichts mehr offen, oder die Anforderung ging schon raus.'); }
                return 'Die Restzahlung ist angefordert — der Kunde hat die E-Mail mit dem Zahlungslink.';
            case 'abo_anfordern':
                require_once __DIR__ . '/Abo.php';
                $a = Abo::anfordern((int) $d['zahlung']);
                if ($a !== 'raus') { throw new RuntimeException($a === 'versand_fehler' ? 'Der Versand hat nicht geklappt — siehe Nachrichten.' : 'Nichts zu tun: bezahlt oder schon angefordert.'); }
                return 'Die Aufforderung ist raus — ab jetzt läuft die Frist von sieben Tagen.';
            case 'mahnung_schicken':
                require_once __DIR__ . '/Mahnung.php';
                $stufe = max(2, min(3, (int) $d['stufe']));
                $m = Mahnung::schicken((int) $d['zahlung'], $stufe);
                if ($m !== 'raus') { throw new RuntimeException($m === 'versand_fehler' ? 'Sie wäre dran gewesen, aber der Versand hat nicht geklappt.' : 'Nichts zu tun: bezahlt, oder diese Stufe ging schon raus.'); }
                return Mahnung::name($stufe) . ' ist raus — der Kunde hat sie samt frischem Zahlungslink.';
            case 'exit_link_senden':
                require_once __DIR__ . '/ExitPaket.php';
                $r = ExitPaket::linkSenden((int) $d['paket']);
                if (!$r['ok']) { throw new RuntimeException($r['text']); }
                return $r['text'];
        }
        throw new RuntimeException('Unbekannte Art.');
    }

    /**
     * Uwe hat dieselbe Tat schon selbst per Knopf ausgelöst: offene Vorschläge dazu sind erledigt,
     * damit nichts doppelt angeboten wird. Wirft nie.
     */
    public static function vonHandErledigt(string $art, array $daten): void
    {
        try {
            $finger = hash('sha256', $art . "\0" . json_encode(self::norm($daten), JSON_UNESCAPED_UNICODE));
            Db::run("UPDATE ai_freigaben SET status = 'von_hand', entschieden_am = NOW(), ergebnis = 'Schon per Knopf in der Verwaltung erledigt.'
                      WHERE fingerabdruck = ? AND status IN ('offen', 'zurueckgestellt')", [$finger]);
        } catch (Throwable $e) { }
    }

    /** @return array{ok:bool, text:string} */
    public static function ablehnen(int $id, string $wer, string $grund = '', string $kanal = 'verwaltung'): array
    {
        $n = Db::run("UPDATE ai_freigaben SET status = 'abgelehnt', entschieden_von = ?, entschieden_am = NOW(), kanal = ?, ergebnis = ?
                       WHERE id = ? AND (status = 'offen' OR status = 'zurueckgestellt')",
            [mb_substr($wer, 0, 80), $kanal, $grund !== '' ? mb_substr($grund, 0, 1000) : null, $id])->rowCount();
        if ($n !== 1) { return ['ok' => false, 'text' => 'Diese Freigabe ist schon entschieden.']; }
        self::spur('freigabe_abgelehnt', $id, [], ['von' => $wer, 'kanal' => $kanal, 'grund' => $grund]);
        return ['ok' => true, 'text' => 'Abgelehnt — es passiert nichts.'];
    }

    /** @return array{ok:bool, text:string} */
    public static function zurueckstellen(int $id, int $tage, string $wer, string $kanal = 'verwaltung'): array
    {
        if (!isset(self::ZURUECK_TAGE[$tage])) { return ['ok' => false, 'text' => 'Zurückstellen geht um 1, 3 oder 7 Tage.']; }
        $bis = date('Y-m-d 07:00:00', strtotime('+' . $tage . ' day'));
        $n = Db::run("UPDATE ai_freigaben SET status = 'zurueckgestellt', zurueck_bis = ? WHERE id = ? AND (status = 'offen' OR status = 'zurueckgestellt')",
            [$bis, $id])->rowCount();
        if ($n !== 1) { return ['ok' => false, 'text' => 'Diese Freigabe ist schon entschieden.']; }
        self::spur('freigabe_zurueckgestellt', $id, [], ['von' => $wer, 'kanal' => $kanal, 'bis' => $bis]);
        return ['ok' => true, 'text' => 'Zurückgestellt bis ' . date('d.m.', strtotime($bis)) . ' — dann steht sie wieder oben.'];
    }

    private static function entscheidbar(array $f): bool
    {
        return $f['status'] === self::OFFEN || $f['status'] === self::ZURUECK;
    }

    /* --------------------------------------------------------------- Telegram */

    /** Neue Freigabe in Uwes Admin-Chat: RAUS mit Knöpfen, SCHWER nur mit Link in die Verwaltung. */
    public static function telegramMelden(int $id): void
    {
        try {
            $f = self::laden($id);
            if (!$f) { return; }
            $r = self::rueckfrage($f);
            $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . rtrim(Config::basis(), '/');
            $text = '🛡 <b>Freigabe wartet</b>' . "\n" . self::h((string) $f['titel'])
                  . ($f['grund'] ? "\n\n" . self::h(mb_substr((string) $f['grund'], 0, 600)) : '')
                  . "\n\n<i>" . self::h($r['frage']) . '</i>';
            $knoepfe = $r['gewicht'] === Ablauf::RAUS
                ? [[['text' => '👀 Ansehen und entscheiden', 'callback_data' => 'v:ag:' . $id]], [['text' => '🛠 In der Verwaltung', 'url' => $basis . '/ai-freigaben/' . $id]]]
                : [[['text' => '🛠 Nur in der Verwaltung (wiegt schwer)', 'url' => $basis . '/ai-freigaben/' . $id]]];
            if (self::$telegram) { (self::$telegram)($text, $knoepfe); return; }
            require_once __DIR__ . '/Telegram.php';
            require_once __DIR__ . '/TelegramAdmin.php';
            require_once __DIR__ . '/TelegramMarketing.php';
            foreach (TelegramMarketing::adminChats() as $chat) {
                Telegram::rufen('sendMessage', ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML',
                    'link_preview_options' => ['is_disabled' => true], 'reply_markup' => ['inline_keyboard' => $knoepfe]]);
            }
        } catch (Throwable $e) { /* die Freigabe steht trotzdem in der Verwaltung */ }
    }

    public static function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    private static function spur(string $aktion, int $id, array $vorher, array $nachher): void
    {
        try {
            require_once __DIR__ . '/Events.php';
            Events::pruefspur($aktion, 'ai_freigaben', $id, $vorher, $nachher);
        } catch (Throwable $e) { }
    }
}
