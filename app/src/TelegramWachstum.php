<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   TelegramWachstum.php — Telegram Growth Engine, Schritt T1: Messung
   (01.10.2026, Uwe: „Ja mach“).

   WOZU

   Ohne Messung ist jede Zahl im späteren Dashboard geraten. Dieser Schritt
   sorgt dafür, dass Telegram dieselben Fragen beantworten kann wie eine
   Kampagne auf der Website: Woher kam jemand, und wurde daraus ein Lead,
   ein Kunde, Umsatz?

   DREI WEGE HINEIN, EINE WAHRHEIT

   1. Bot-Start über einen Kampagnenlink: t.me/BOT?start=m_CODE (oder
      m_CODE_WERBEMITTEL). Das ist dieselbe Kampagne wie /k/CODE — der
      Bot legt einen Besuch in der Spur an (Spur::telegramBesuch), und
      Preisrechner, Anfrage, Angebot, Auftrag und Zahlung hängen sich daran
      wie auf der Website. Keine zweite Rechnung, keine zweite Tabelle.
   2. Kanal-Beitritt über einen eigenen Einladungslink je Kampagne. Telegram
      meldet (Update „chat_member“), über welchen Link jemand kam. Wir
      zählen den Beitritt — WER beigetreten ist, speichern wir nicht.
   3. Alles andere (Partner p_, Empfehlung e_, Wörter wie „kanal“, „web“)
      steht schon als Quelle am Chat; hier wird es nur täglich gezählt.

   WAS TELEGRAM NICHT HERGIBT — UND WIR NICHT ERFINDEN

   Aufrufe und Weiterleitungen einzelner Kanalbeiträge sieht kein Bot. Die
   stehen in Telegrams eigener Statistik. Hier steht nur, was gemessen ist.

   DATENSCHUTZ

   tg_tage enthält nur Zahlen je Tag und Quelle — keine Chat-Kennung, keinen
   Namen. Die Zählung darf nie etwas anderes aufhalten: Jede Methode hier
   schluckt ihre Fehler und meldet sie ins Fehlerprotokoll.
   ========================================================================== */
final class TelegramWachstum
{
    /** Was gezählt wird. „stand“-Arten sind Momentaufnahmen (überschreiben), die anderen Zähler (addieren). */
    public const ARTEN = [
        'bot_neu'     => 'Neue Bot-Nutzer',
        'bot_aktiv'   => 'Aktive Bot-Nutzer',
        'bot_wieder'  => 'Wiederkehrende Bot-Nutzer',
        'bot_start'   => 'Starts über einen Link',
        'kanal_bei'   => 'Kanal-Beitritte',
        'kanal_aus'   => 'Kanal-Austritte',
        'kanal_stand' => 'Kanal-Mitglieder',
        // T2 (01.10.2026): Stufen des Funnels — je Chat einmal (stufe()), in der Mini-App je Rechner einmal.
        'wegweiser'      => 'Wegweiser genutzt',
        'check'          => 'Website-Check geöffnet',
        'check_fertig'   => 'Website-Check mit Ergebnis',
        'interesse'      => 'Interesse an einem Thema',
        'rechner'        => 'Preisrechner gestartet',
        'rechner_fertig' => 'Preisrechner abgeschlossen',
        'beratung'       => 'Beratung gestartet',
        'lead'           => 'Anfrage abgeschickt',
        'app_start'      => 'Mini-App geöffnet',
        // Kommentare unter dem Kanal (01.10.2026, TelegramGruppe): nur Zahlen, kein Inhalt, kein Absender.
        'kommentar'      => 'Kommentare unter dem Kanal',
        'kommentar_weg'  => 'Kommentare gelöscht (Werbung)',
    ];

    /** Die Stufen, die ein Chat einmal erreichen kann (telegram_chats.stufen). */
    public const STUFEN = ['wegweiser', 'check', 'check_fertig', 'interesse', 'rechner', 'rechner_fertig', 'beratung', 'lead'];

    /** Start-Parameter einer Kampagne: m_CODE oder m_CODE_WERBEMITTEL (Codes wie bei /k/). */
    public const START_MUSTER = '/^m_([a-z0-9][a-z0-9-]{2,23})(?:_([a-z0-9][a-z0-9-]{0,11}))?$/';

    /* ================================================================== */
    /*  Zählen                                                            */
    /* ================================================================== */

    public static function zaehlen(string $art, string $quelle = '', int $n = 1): void
    {
        if (!isset(self::ARTEN[$art]) || $n === 0) { return; }
        try {
            Db::run('INSERT INTO tg_tage (tag, art, quelle, zahl) VALUES (CURDATE(), ?, ?, ?) ON DUPLICATE KEY UPDATE zahl = zahl + VALUES(zahl)',
                [$art, self::quelle($quelle), $n]);
        } catch (Throwable $e) { error_log('TelegramWachstum::zaehlen(' . $art . '): ' . $e->getMessage()); }
    }

    /** Momentaufnahme (z. B. Mitgliederstand): der Wert des Tages wird ersetzt, nicht addiert. */
    public static function stand(string $art, int $wert, string $quelle = ''): void
    {
        if (!isset(self::ARTEN[$art])) { return; }
        try {
            Db::run('INSERT INTO tg_tage (tag, art, quelle, zahl) VALUES (CURDATE(), ?, ?, ?) ON DUPLICATE KEY UPDATE zahl = VALUES(zahl)',
                [$art, self::quelle($quelle), $wert]);
        } catch (Throwable $e) { error_log('TelegramWachstum::stand(' . $art . '): ' . $e->getMessage()); }
    }

    /** Quellen-Kennung säubern: nur, was ein Start-Parameter oder ein Link-Name sein kann. */
    public static function quelle(string $roh): string
    {
        return mb_substr((string) preg_replace('/[^A-Za-z0-9_:-]/', '', $roh), 0, 40);
    }

    /**
     * Zählt, was eine Nachricht an den Bot über seine Nutzer verrät — aufgerufen
     * mit dem Chat, wie er VOR dieser Nachricht stand. Aktiv = erste Nachricht
     * an diesem Tag; wiederkehrend = aktiv und schon vor heute da gewesen.
     */
    public static function botKontakt(array $vorher, bool $neu): void
    {
        $heute = date('Y-m-d');
        if ($neu) { self::zaehlen('bot_aktiv'); return; }   // „neu“ zählt der Bot nach der Nachricht, mit Quelle
        if (substr((string) ($vorher['letzte_am'] ?? ''), 0, 10) >= $heute) { return; }
        self::zaehlen('bot_aktiv');
        if (substr((string) ($vorher['created_at'] ?? ''), 0, 10) < $heute) { self::zaehlen('bot_wieder'); }
    }

    /**
     * Ein Chat erreicht eine Stufe des Funnels — gezählt wird nur das erste
     * Mal, mit der Quelle des Chats. Die Prüfung sitzt im UPDATE selbst
     * (FIND_IN_SET), damit zwei schnelle Klicks nicht zweimal zählen.
     */
    public static function stufe(array $c, string $stufe): void
    {
        if (!in_array($stufe, self::STUFEN, true) || empty($c['id'])) { return; }
        try {
            $n = Db::run("UPDATE telegram_chats SET stufen = TRIM(BOTH ',' FROM CONCAT(stufen, ',', ?)) WHERE id = ? AND FIND_IN_SET(?, stufen) = 0",
                [$stufe, (int) $c['id'], $stufe])->rowCount();
            if ($n === 1) {
                self::zaehlen($stufe, (string) Db::wert('SELECT quelle_code FROM telegram_chats WHERE id = ?', [(int) $c['id']], ''));
            }
        } catch (Throwable $e) { error_log('TelegramWachstum::stufe(' . $stufe . '): ' . $e->getMessage()); }
    }

    /**
     * Dieser Kunde kam über Telegram (Anfrage aus dem Bot oder der Mini-App).
     * Die erste Herkunft bleibt — wer später noch einmal über Telegram
     * anfragt, war schon vorher Kunde über einen anderen Weg oder dieselbe Quelle.
     */
    public static function herkunftMerken(int $kundeId, string $quelle, string $weg, ?int $anfrageId = null): void
    {
        if ($kundeId <= 0) { return; }
        try {
            Db::run('INSERT IGNORE INTO tg_herkunft (customer_id, quelle, weg, anfrage_id) VALUES (?, ?, ?, ?)',
                [$kundeId, self::quelle($quelle), $weg === 'app' ? 'app' : 'bot', $anfrageId]);
        } catch (Throwable $e) { error_log('TelegramWachstum::herkunftMerken: ' . $e->getMessage()); }
    }

    /* ================================================================== */
    /*  Kampagne im Bot                                                   */
    /* ================================================================== */

    /**
     * Kampagne und Werbemittel aus einem Start-Parameter — nur aktive (wie
     * /k/: eine pausierte Kampagne zählt nichts mehr).
     *
     * @return array{0:?array,1:?array,2:string}  Kampagne, Werbemittel, Quellen-Kennung
     */
    public static function kampagneAusStart(string $arg): array
    {
        if (!preg_match(self::START_MUSTER, $arg, $m)) { return [null, null, '']; }
        try {
            require_once __DIR__ . '/MkKampagne.php';
            [$k, $cr] = MkKampagne::ausCode($m[1], (string) ($m[2] ?? ''));
        } catch (Throwable $e) { return [null, null, '']; }
        if ($k === null) { return [null, null, '']; }
        return [$k, $cr, 'm_' . $k['code'] . ($cr !== null ? '_' . $cr['code'] : '')];
    }

    /** Der Bot-Link einer Kampagne (für die Kampagnenseite). Leer, solange der Bot keinen Namen hat. */
    public static function botLink(array $k, ?array $cr = null): string
    {
        $param = 'm_' . $k['code'] . ($cr !== null ? '_' . $cr['code'] : '');
        /* Ist der Bot nur für den Admin (ab Werk seit 01.10.2026), öffnet der Link
           das Vecom-Fenster mit derselben Kampagne (telegram-app.php merkt sie). */
        if (!Telegram::botOffen()) {
            require_once __DIR__ . '/TelegramApp.php';
            $app = TelegramApp::link($param);
            if ($app !== '') { return $app; }
        }
        return Telegram::link($param);
    }

    /* ================================================================== */
    /*  Kanal: Einladungslinks und Beitritte                              */
    /* ================================================================== */

    /**
     * Einen eigenen Einladungslink des Kanals für eine Kampagne anlegen.
     * Braucht im Kanal das Recht „Nutzer einladen“ — fehlt es, sagt Telegram
     * das, und der Satz geht so an Uwe.
     *
     * @return array{ok:bool, text:string, link?:string}
     */
    public static function einladungAnlegen(int $kampagneId, string $wer = ''): array
    {
        require_once __DIR__ . '/MkKampagne.php';
        $k = MkKampagne::laden($kampagneId);
        if ($k === null) { return ['ok' => false, 'text' => 'Diese Kampagne gibt es nicht.']; }
        $kanal = Telegram::kanal();
        if ($kanal['id'] === '') { return ['ok' => false, 'text' => 'Erst den Kanal hinterlegen (Einstellungen → Telegram).']; }
        $alt = Db::one('SELECT link FROM tg_einladungen WHERE kampagne_id = ? AND aktiv = 1 ORDER BY id DESC LIMIT 1', [$kampagneId]);
        if ($alt) { return ['ok' => true, 'text' => 'Für diese Kampagne gibt es schon einen Kanal-Link.', 'link' => (string) $alt['link']]; }
        // Ohne „chat_member“ im Webhook käme kein Beitritt an — das zuerst sicherstellen.
        Telegram::webhookNachziehen();
        $name = mb_substr('m_' . $k['code'], 0, 32);
        $r = Telegram::rufen('createChatInviteLink', ['chat_id' => $kanal['id'], 'name' => $name]);
        $link = (string) ($r['result']['invite_link'] ?? '');
        if (!$r['ok'] || !preg_match('~^https://t\.me/\+[A-Za-z0-9_-]{8,64}$~', $link)) {
            $grund = $r['beschreibung'] !== '' ? $r['beschreibung'] : 'keine Antwort';
            $rechte = stripos($grund, 'not enough rights') !== false || stripos($grund, 'CHAT_ADMIN_REQUIRED') !== false;
            return ['ok' => false, 'text' => $rechte
                ? 'Der Bot darf im Kanal noch keine Einladungslinks anlegen. In Telegram: Kanal → Administratoren → Bot → „Nutzer einladen“ einschalten.'
                : 'Telegram hat den Link nicht angelegt (' . $grund . ').'];
        }
        Db::insert('tg_einladungen', ['kampagne_id' => $kampagneId, 'name' => $name, 'link' => $link, 'erstellt_von' => mb_substr($wer, 0, 80)]);
        return ['ok' => true, 'text' => 'Kanal-Link angelegt — Beitritte darüber zählen für diese Kampagne.', 'link' => $link];
    }

    /** Der aktive Kanal-Link einer Kampagne, mit seinen Zählern — oder null. */
    public static function einladung(int $kampagneId): ?array
    {
        try {
            return Db::one('SELECT * FROM tg_einladungen WHERE kampagne_id = ? AND aktiv = 1 ORDER BY id DESC LIMIT 1', [$kampagneId]) ?: null;
        } catch (Throwable $e) { return null; }
    }

    /**
     * Update „chat_member“: Jemand ist dem Kanal beigetreten oder hat ihn
     * verlassen. Nur unser Kanal zählt; gespeichert wird die Zahl, nicht die Person.
     *
     * @return string  Vermerk für das Webhook-Protokoll
     */
    public static function mitglied(array $cm): string
    {
        try {
            $kanal = Telegram::kanal();
            if ($kanal['id'] === '' || (string) ($cm['chat']['id'] ?? '') !== $kanal['id']) { return 'fremder_chat'; }
            if (!empty($cm['new_chat_member']['user']['is_bot'])) { return 'bot'; }
            $drin = static fn(array $m): bool => in_array((string) ($m['status'] ?? ''), ['creator', 'administrator', 'member'], true)
                || ((string) ($m['status'] ?? '') === 'restricted' && !empty($m['is_member']));
            $war = $drin((array) ($cm['old_chat_member'] ?? []));
            $ist = $drin((array) ($cm['new_chat_member'] ?? []));
            if ($war === $ist) { return 'unveraendert'; }
            $link = (string) ($cm['invite_link']['invite_link'] ?? '');
            $e = $link !== '' ? Db::one('SELECT id, name FROM tg_einladungen WHERE link = ?', [$link]) : null;
            /* Wer über den öffentlichen @Namen oder die Suche kam, trägt keinen Link: „ohne“. */
            $quelle = $e ? (string) $e['name'] : ($link !== '' ? 'einladung' : '');
            if ($ist) {
                self::zaehlen('kanal_bei', $quelle);
                if ($e) { Db::run('UPDATE tg_einladungen SET beitritte = beitritte + 1 WHERE id = ?', [(int) $e['id']]); }
                return 'beitritt';
            }
            /* Beim Austritt schickt Telegram keinen Link mit — der Austritt zählt ohne Quelle. */
            self::zaehlen('kanal_aus', $quelle);
            return 'austritt';
        } catch (Throwable $e) {
            error_log('TelegramWachstum::mitglied: ' . $e->getMessage());
            return 'fehler';
        }
    }

    /** Einmal am Tag: Mitgliederzahl des Kanals festhalten (getChatMemberCount). */
    public static function kanalStand(): ?int
    {
        $kanal = Telegram::kanal();
        if ($kanal['id'] === '' || !Telegram::bereit()) { return null; }
        $r = Telegram::rufen('getChatMemberCount', ['chat_id' => $kanal['id']]);
        if (!$r['ok'] || !is_int($r['result'] ?? null)) { return null; }
        self::stand('kanal_stand', (int) $r['result']);
        return (int) $r['result'];
    }

    /* ================================================================== */
    /*  Lesen                                                             */
    /* ================================================================== */

    /**
     * Summen je Art im Zeitraum, für eine Quelle oder alle. „stand“ ist der
     * letzte bekannte Wert, keine Summe.
     *
     * @return array<string,int|null>  null = noch nie gemessen
     */
    public static function summen(string $von, string $bis, ?string $quelle = null): array
    {
        $aus = array_fill_keys(array_keys(self::ARTEN), null);
        try {
            $w = $quelle !== null ? ' AND quelle = ?' : '';
            $a = $quelle !== null ? [$von, $bis, $quelle] : [$von, $bis];
            foreach (Db::all("SELECT art, SUM(zahl) AS n FROM tg_tage WHERE tag BETWEEN ? AND ?$w AND art <> 'kanal_stand' GROUP BY art", $a) as $r) {
                $aus[(string) $r['art']] = (int) $r['n'];
            }
            $st = Db::one("SELECT zahl FROM tg_tage WHERE art = 'kanal_stand' AND tag <= ? ORDER BY tag DESC LIMIT 1", [$bis]);
            if ($st) { $aus['kanal_stand'] = (int) $st['zahl']; }
        } catch (Throwable $e) { /* Migration noch offen: alles „nicht gemessen“ */ }
        return $aus;
    }

    /** Für die Kampagnenseite: Bot-Starts und Kanal-Beitritte dieser Kampagne im Zeitraum (alle Werbemittel). */
    public static function kampagne(array $k, string $von, string $bis): array
    {
        $aus = ['bot_start' => 0, 'bot_neu' => 0, 'kanal_bei' => 0];
        try {
            foreach (Db::all("SELECT art, SUM(zahl) AS n FROM tg_tage WHERE tag BETWEEN ? AND ? AND (quelle = ? OR quelle LIKE ?)
                               AND art IN ('bot_start','bot_neu','kanal_bei') GROUP BY art",
                [$von, $bis, 'm_' . $k['code'], 'm\\_' . $k['code'] . '\\_%']) as $r) {
                $aus[(string) $r['art']] = (int) $r['n'];
            }
        } catch (Throwable $e) { }
        return $aus;
    }

    /* ================================================================== */
    /*  Kanal-Links mit Zählung (01.10.2026, Uwe: „Alles“ — Vorschlag 1/7) */
    /* ================================================================== */

    /**
     * Wo der Kanal verlinkt ist. Jeder Ort bekommt eine gewöhnliche Kampagne
     * „kanal-ORT“ mit eigenem Einladungslink — so zählt jeder Beitritt für
     * seinen Ort (Update chat_member, wie bei jeder Kampagne). Auf den Seiten
     * steht immer /kanal.php?w=ORT: Der Link dort veraltet nie, auch wenn der
     * Einladungslink einmal neu angelegt werden muss.
     */
    public const KANAL_ORTE = [
        'fuss'   => 'Website: Telegram-Symbol unten auf der Startseite',
        'check'  => 'Website-Check: unter dem Ergebnis',
        'mail'   => 'E-Mails an Kunden: Fußzeile',
        'kunde'  => 'Angebots- und Projektseiten: Fußzeile',
        'qr'     => 'QR-Aufsteller zum Ausdrucken',
        'profil' => 'Profile (Facebook, YouTube, TikTok …): Link in der Beschreibung',
        // 02.10.2026 (Uwe: „schnell 100 Mitglieder“): jeder Ort zählt für sich.
        'partner'       => 'Partner-Dashboard: Kasten „Vecom auf Telegram“ (beitreten und weitergeben)',
        'kundenbereich' => 'Kundenbereich: Link „Kanal öffnen“',
        'instagram'     => 'Instagram: Link im Profil',
        'facebook'      => 'Facebook-Seite: Link im Profil',
        'youtube'       => 'YouTube-Kanal: Link im Profil',
        // 03.10.2026 (Uwe: „Kanal-Link überall“): TikTok zählt für sich, wie die anderen Profile.
        'tiktok'        => 'TikTok: Link im Profil',
        'social'        => 'Beiträge auf Facebook/Instagram, die den Kanal bekannt machen',
    ];

    /** Die öffentliche Adresse eines Orts — sie leitet auf dessen Einladungslink. */
    public static function kanalOrtLink(string $ort): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/kanal.php?w=' . (isset(self::KANAL_ORTE[$ort]) ? $ort : 'fuss');
    }

    /**
     * Wohin /kanal.php?w=ORT führt — ohne einen einzigen Aufruf bei Telegram
     * (die Seite ist öffentlich; angelegt wird nur aus der Verwaltung und im
     * Cronlauf). Fehlt der eigene Link, der öffentliche Kanal; fehlt auch der,
     * die Startseite. Nie eine fremde Adresse.
     */
    public static function kanalZiel(string $ort): string
    {
        if (isset(self::KANAL_ORTE[$ort])) {
            try {
                $e = Db::one("SELECT e.link FROM tg_einladungen e JOIN mk_kampagnen k ON k.id = e.kampagne_id
                               WHERE k.code = ? AND k.status = 'aktiv' AND e.aktiv = 1 ORDER BY e.id DESC LIMIT 1", ['kanal-' . $ort]);
                if ($e && preg_match('~^https://t\.me/\+[A-Za-z0-9_-]{8,64}$~', (string) $e['link'])) { return (string) $e['link']; }
            } catch (Throwable $x) { /* dann der öffentliche Kanal */ }
        }
        $pub = (string) (Telegram::kanal()['link'] ?? '');
        return preg_match('~^https://t\.me/[A-Za-z0-9_]{4,64}/?$~', $pub) ? $pub : '/';
    }

    /**
     * Für jeden Ort die Kampagne und den Einladungslink anlegen, wo sie fehlen
     * (Verwaltung beim Öffnen des Telegram-Reiters, täglicher Cronlauf).
     * @return array<string, array{link:string, beitritte:int, ok:bool, text:string}>
     */
    public static function kanalLinksSicherstellen(bool $anlegen = true): array
    {
        require_once __DIR__ . '/MkKampagne.php';
        $aus = [];
        $kanalDa = Telegram::kanal()['id'] !== '' && Telegram::bereit();
        foreach (self::KANAL_ORTE as $ort => $wort) {
            $zeile = ['link' => self::kanalOrtLink($ort), 'beitritte' => 0, 'ok' => false, 'text' => ''];
            try {
                $k = Db::one('SELECT * FROM mk_kampagnen WHERE code = ?', ['kanal-' . $ort]);
                if ($k === null && $anlegen && $kanalDa) {
                    $kid = MkKampagne::anlegen(['name' => 'Kanal-Link: ' . $wort, 'plattform' => 'telegram', 'code' => 'kanal-' . $ort, 'ziel' => '/',
                        'notiz' => 'Telegram-Kanal, verlinkt hier: ' . $wort . '. Beitritte über diesen Link zählen für diesen Ort.']);
                    $k = is_int($kid) ? MkKampagne::laden($kid) : Db::one('SELECT * FROM mk_kampagnen WHERE code = ?', ['kanal-' . $ort]);
                }
                if ($k !== null) {
                    $e = self::einladung((int) $k['id']);
                    if ($e === null && $anlegen && $kanalDa) {
                        $r = self::einladungAnlegen((int) $k['id'], 'Kanal-Links');
                        $zeile['text'] = $r['ok'] ? '' : $r['text'];
                        $e = self::einladung((int) $k['id']);
                    }
                    if ($e !== null) { $zeile['ok'] = true; $zeile['beitritte'] = (int) $e['beitritte']; }
                }
                if (!$zeile['ok'] && $zeile['text'] === '') { $zeile['text'] = $kanalDa ? 'noch kein eigener Einladungslink — führt auf den öffentlichen Kanal' : 'Kanal oder Bot noch nicht verbunden'; }
            } catch (Throwable $x) { $zeile['text'] = 'nicht angelegt: ' . mb_substr($x->getMessage(), 0, 80); }
            $aus[$ort] = $zeile;
        }
        return $aus;
    }

    /* ================================================================== */
    /*  Wochenbericht (01.10.2026, Uwe: „Alles“ — Vorschlag 8)             */
    /* ================================================================== */

    /**
     * Was die letzte Woche in Telegram gebracht hat — ein paar Zeilen, nur
     * Zahlen (keine Namen, keine Chat-Kennungen). Montags per Cron an Uwes
     * Telegram (TelegramAdmin::zuruf), sonst an den gewohnten Zuruf.
     */
    public static function wochenbericht(?int $jetzt = null): string
    {
        $jetzt ??= time();
        $bis = date('Y-m-d', $jetzt - 86400);
        $von = date('Y-m-d', $jetzt - 7 * 86400);
        $s = self::summen($von, $bis);
        $n = static fn(string $a): int => (int) ($s[$a] ?? 0);
        $z = [];
        $z[] = 'Telegram, Woche ' . date('d.m.', strtotime($von)) . '–' . date('d.m.', strtotime($bis));
        $stand = $s['kanal_stand'] ?? null;
        $z[] = 'Kanal: ' . ($stand !== null ? $stand . ' Mitglieder' : 'Mitglieder noch nicht gemessen') . ' · +' . $n('kanal_bei') . ' / −' . $n('kanal_aus');
        /* Woher: die stärksten Quellen nach Beitritten und neuen Nutzern (Kampagnen samt Werbemitteln zusammen). */
        $quellen = [];
        try {
            foreach (Db::all("SELECT quelle, SUM(zahl) AS n FROM tg_tage WHERE tag BETWEEN ? AND ? AND art IN ('kanal_bei','bot_neu','app_start') AND quelle <> '' GROUP BY quelle", [$von, $bis]) as $r) {
                $q = preg_match('/^m_([a-z0-9][a-z0-9-]{2,23})_[a-z0-9-]+$/', (string) $r['quelle'], $m) ? 'm_' . $m[1] : (string) $r['quelle'];
                $quellen[$q] = ($quellen[$q] ?? 0) + (int) $r['n'];
            }
        } catch (Throwable $e) { }
        arsort($quellen);
        if ($quellen) {
            $namen = [];
            $kamp = [];
            $codes = array_values(array_map(static fn($q) => substr($q, 2), array_filter(array_keys($quellen), static fn($q) => str_starts_with($q, 'm_'))));
            if ($codes) {
                try {
                    foreach (Db::all('SELECT code, name FROM mk_kampagnen WHERE code IN (' . implode(',', array_fill(0, count($codes), '?')) . ')', $codes) as $r) { $kamp[(string) $r['code']] = (string) $r['name']; }
                } catch (Throwable $e) { }
            }
            foreach (array_slice($quellen, 0, 3, true) as $q => $wie) {
                $namen[] = (str_starts_with($q, 'm_') ? ($kamp[substr($q, 2)] ?? 'Kampagne ' . substr($q, 2)) : ($q === 'kanal' ? 'Kanal-Knöpfe' : ($q === 'telegram' ? 'Fenster ohne Angabe' : $q))) . ' ' . $wie;
            }
            $z[] = 'Woher: ' . implode(' · ', $namen);
        }
        $z[] = 'Vecom-Fenster geöffnet: ' . $n('app_start') . ' · Preisrechner: ' . $n('rechner') . ' (' . $n('rechner_fertig') . ' fertig) · Anfragen: ' . $n('lead');
        if ($n('kommentar') > 0 || $n('kommentar_weg') > 0) { $z[] = 'Kommentare: ' . $n('kommentar') . ($n('kommentar_weg') > 0 ? ' · davon als Werbung gelöscht: ' . $n('kommentar_weg') : ''); }
        try {
            require_once __DIR__ . '/Verzeichnisse.php';
            $f = array_sum(Verzeichnisse::faellig());
            if ($f > 0) { $z[] = 'Verzeichnisse: ' . $f . ' Einträge warten seit über einer Woche'; }
        } catch (Throwable $e) { }
        return implode("\n", $z);
    }

    /** Montags einmal: den Wochenbericht an Uwes Telegram, ersatzweise an den Zuruf. */
    public static function wochenberichtSenden(?int $jetzt = null): bool
    {
        $text = self::wochenbericht($jetzt);
        try {
            require_once __DIR__ . '/TelegramAdmin.php';
            if (TelegramAdmin::zuruf($text)) { return true; }
        } catch (Throwable $e) { }
        try { require_once __DIR__ . '/Zuruf.php'; Zuruf::vormerken('telegram_woche', $text, 6 * 24 * 60); return true; } catch (Throwable $e) { return false; }
    }

    /** Tageszahlen sind klein und ohne Personenbezug — nach zwei Jahren trotzdem weg. */
    public static function aufraeumen(): int
    {
        try { return Db::run('DELETE FROM tg_tage WHERE tag < (CURDATE() - INTERVAL 730 DAY)')->rowCount(); } catch (Throwable $e) { return 0; }
    }

    /* ================================================================== */
    /*  Werbung für den Kanal (02.10.2026, Uwe: „schnell 100 Mitglieder“)  */
    /* ================================================================== */

    /** Die Entwürfe: je Sprache ein Facebook-Beitrag und ein Instagram-Karussell. */
    public const WERBUNG = [
        'it' => [
            'fb' => "📲 Vecom Design adesso è anche su Telegram.\n\nNovità, esempi di siti per attività in Sicilia e consigli pratici — brevi e senza spam. Un tocco e siete nel canale:",
            'hook' => 'Siamo su Telegram 📲',
            'folien' => [['titel' => 'Cosa trova', 'text' => 'Novità, esempi di siti e consigli pratici per la sua attività.'],
                         ['titel' => 'Come entrare', 'text' => 'Cerchi @vecomdesign su Telegram e tocchi «Unisciti».']],
            'ig' => "Il canale Telegram di Vecom Design: novità, esempi e consigli, senza spam. Ci trova come @vecomdesign 📲",
            'tags' => ['#telegram', '#sitoweb', '#sicilia', '#piccoleimprese'],
        ],
        'de' => [
            'fb' => "📲 Vecom Design gibt es jetzt auch auf Telegram.\n\nNeuigkeiten, Website-Beispiele für Betriebe und praktische Tipps — kurz und ohne Spam. Ein Tipp, und Sie sind im Kanal:",
            'hook' => 'Wir sind auf Telegram 📲',
            'folien' => [['titel' => 'Was Sie finden', 'text' => 'Neuigkeiten, Website-Beispiele und praktische Tipps für Ihren Betrieb.'],
                         ['titel' => 'So treten Sie bei', 'text' => 'Suchen Sie @vecomdesign in Telegram und tippen Sie auf „Beitreten“.']],
            'ig' => "Der Telegram-Kanal von Vecom Design: Neuigkeiten, Beispiele und Tipps, ohne Spam. Zu finden als @vecomdesign 📲",
            'tags' => ['#telegram', '#website', '#kleinunternehmen', '#webdesign'],
        ],
    ];

    /**
     * Einmal: vier Entwürfe in den Freigabe-Stapel (Facebook-Beitrag und Instagram-
     * Karussell, Italienisch und Deutsch). Raus geht nichts ohne Uwes Freigabe.
     * Der Facebook-Link läuft über die Kampagne „kanal-werbung“ (/k/… zählt den
     * Klick) auf /kanal.php, und von dort mit dem Einladungslink „social“ in den Kanal.
     * @return int angelegte Entwürfe
     */
    public static function werbungAnlegen(): int
    {
        try {
            if (Telegram::kanal()['id'] === '' || !Telegram::bereit()) { return 0; }
            foreach (Db::all("SELECT felder FROM mk_inhalte WHERE plattform IN ('facebook', 'instagram') AND created_at >= NOW() - INTERVAL 365 DAY") as $z) {
                if (!empty((json_decode((string) $z['felder'], true) ?: [])['kanal_werbung'])) { return 0; }
            }
            require_once __DIR__ . '/MkKampagne.php';
            $k = Db::one('SELECT id FROM mk_kampagnen WHERE code = ?', ['kanal-werbung']);
            $kid = $k ? (int) $k['id'] : MkKampagne::anlegen(['name' => 'Telegram-Kanal bekannt machen', 'plattform' => 'facebook', 'code' => 'kanal-werbung', 'ziel' => '/kanal.php',
                'notiz' => 'Beiträge auf Facebook/Instagram, die zum Telegram-Kanal führen. Klicks zählen hier, Beitritte beim Kanal-Link „social“.']);
            if (!is_int($kid)) { return 0; }
            $n = 0;
            foreach (self::WERBUNG as $sp => $w) {
                $land = $sp === 'de' ? 'DE' : 'IT';
                $basis = ['branche' => '', 'land' => $land, 'sprache' => $sp, 'art' => 'organisch', 'kampagne_id' => $kid, 'status' => 'entwurf'];
                $deFb = self::WERBUNG['de']['fb'];
                Db::insert('mk_inhalte', $basis + ['format' => 'beitrag', 'plattform' => 'facebook', 'titel' => 'Telegram-Kanal bekannt machen (' . strtoupper($sp) . ')',
                    'felder' => json_encode(['text' => $w['fb'], 'hashtags' => $w['tags'], 'kanal_werbung' => 1], JSON_UNESCAPED_UNICODE),
                    'uebersetzung' => $sp === 'de' ? null : $deFb]);
                Db::insert('mk_inhalte', $basis + ['format' => 'karussell', 'plattform' => 'instagram', 'titel' => 'Telegram-Kanal bekannt machen · Karussell (' . strtoupper($sp) . ')',
                    'felder' => json_encode(['hook' => $w['hook'], 'folien' => $w['folien'], 'text' => $w['ig'], 'hashtags' => $w['tags'], 'kanal_werbung' => 1], JSON_UNESCAPED_UNICODE),
                    'uebersetzung' => $sp === 'de' ? null : self::WERBUNG['de']['ig']]);
                $n += 2;
            }
            return $n;
        } catch (Throwable $e) { return 0; }
    }
}
