<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Akquise.php';

/**
 * Kommunikationsstatus für E-Mail (06.10.2026, Uwe: „Überarbeite die starre
 * E-Mail-Sperre … Keine pauschale Sperre mehr. Stattdessen erhält jeder
 * Betrieb einen Kommunikationsstatus.“)
 *
 * FÜNF DINGE, GETRENNT
 *
 *   1. gefunden    – es gibt eine Adresse (email_found)
 *   2. anzeigen    – IMMER, die Adresse wird nie wegen fehlender Einwilligung versteckt
 *   3. Entwurf     – immer erlaubt, außer bei „Nicht kontaktieren“
 *   4. versenden   – manueller Einzelversand, nur bei dokumentiertem Versandgrund
 *   5. Werbung     – nur, wenn der Grund das trägt (Einwilligung, Bestandskunde mit allen vier Punkten)
 *
 * VIER STATUS
 *
 *   keine_freigabe      🔴 Keine Versandfreigabe dokumentiert
 *   pruefen             🟡 Manuelle Prüfung erforderlich
 *   freigegeben         🟢 Versand freigegeben (manuell, einzeln)
 *   nicht_kontaktieren  ⛔ Widerspruch, Abmeldung, Beschwerde, Kontaktverbot, Sperre
 *
 * Automatischer Versand und Serien laufen weiter ausschließlich über das Gate
 * (AkquiseGate) — ein hier dokumentierter Grund schaltet keine Automatik frei.
 *
 * Das System dokumentiert den eingetragenen Kontaktstatus und den angegebenen
 * Versandgrund. Es trifft keine Aussage darüber, ob ein Versand rechtlich
 * zulässig ist; das bleibt die Entscheidung dessen, der den Grund einträgt.
 */
final class AkquiseMail
{
    public const KEINE = 'keine_freigabe';
    public const PRUEFEN = 'pruefen';
    public const FREI = 'freigegeben';
    public const NICHT = 'nicht_kontaktieren';

    /** Status → [Zeichen, Farbe, Wort] */
    public const STATUS = [
        self::KEINE   => ['🔴', 'rot',  'Keine Versandfreigabe dokumentiert'],
        self::PRUEFEN => ['🟡', 'gelb', 'Manuelle Prüfung erforderlich'],
        self::FREI    => ['🟢', 'gruen', 'Versand freigegeben'],
        self::NICHT   => ['⛔', 'rot',  'Nicht kontaktieren'],
    ];

    /**
     * Versandgründe → [Bezeichnung, Freigabe, Werbung].
     * Freigabe 'haken' = nur, wenn die vier Voraussetzungen bestätigt sind;
     * 'admin' = nur, wenn ein Admin sie ausdrücklich setzt.
     */
    public const GRUENDE = [
        'einwilligung'   => ['Ausdrückliche Einwilligung vorhanden', true, true],
        'bestandskunde'  => ['Bestehender Kunde – § 7 Abs. 3 UWG / Art. 130 Abs. 4 Codice Privacy prüfen', 'haken', true],
        'selbst_kontakt' => ['Kunde hat selbst Kontakt aufgenommen', true, false],
        'anfrage'        => ['Konkrete Anfrage des Kunden beantworten', true, false],
        'geschaeft'      => ['Laufende Geschäftsbeziehung', true, false],
        'sonstiges'      => ['Sonstiger dokumentierter Kommunikationsgrund', 'admin', false],
    ];

    /** Die vier Voraussetzungen für Bestandskunden (Wortlaut wie in akq_regeln). */
    public const BESTAND_HAKEN = [
        'verkauf'    => 'Adresse stammt aus einem Verkauf an diesen Kunden',
        'aehnlich'   => 'Werbung nur für eigene, ähnliche Leistungen',
        'kein_nein'  => 'Der Kunde hat nicht widersprochen',
        'hinweis'    => 'Hinweis auf das Widerspruchsrecht bei Erhebung und in jeder Nachricht',
    ];

    /** Gründe für „Nicht kontaktieren“ → [Bezeichnung, Quelle für die Sperrliste]. */
    public const DNC_GRUENDE = [
        'widerspruch'    => ['Widerspruch', 'antwort'],
        'abmeldung'      => ['Abmeldung', 'abmeldung'],
        'beschwerde'     => ['Beschwerde', 'antwort'],
        'kontaktverbot'  => ['Ausdrückliches Kontaktverbot', 'hand'],
    ];

    /* ================================================================== */
    /*  Lesen                                                             */
    /* ================================================================== */

    /** Nicht kontaktieren? (eigenes Feld, Sperre oder Ablehnung) */
    public static function nichtKontaktieren(array $f): bool
    {
        return (int) ($f['email_do_not_contact'] ?? 0) === 1 || (int) ($f['gesperrt'] ?? 0) === 1
            || in_array((string) ($f['kontakt_status'] ?? ''), ['abgelehnt', 'gesperrt'], true);
    }

    /** Status aus den Feldern -- dieselbe Regel wie beim Speichern. */
    public static function status(array $f): string
    {
        if (self::nichtKontaktieren($f)) { return self::NICHT; }
        if ((int) ($f['email_send_allowed'] ?? 0) === 1 || self::einwilligungAlt($f)) { return self::FREI; }
        if ((int) ($f['email_review_requested'] ?? 0) === 1 || trim((string) ($f['email_legal_basis'] ?? '')) !== '') { return self::PRUEFEN; }
        return self::KEINE;
    }

    /** Eine über den alten Weg erfasste Einwilligung (Anruf, Besuch, Double-Opt-in) zählt weiter. */
    private static function einwilligungAlt(array $f): bool
    {
        require_once __DIR__ . '/AkquiseGate.php';
        return AkquiseGate::einwilligungDeckt($f, 'email');
    }

    /**
     * Was mit der Adresse geht -- fünf getrennte Antworten.
     * @return array{gefunden:bool,anzeigen:bool,entwurf:bool,senden:bool,werbung:bool,status:string,grund:?string}
     */
    public static function kann(array $f): array
    {
        $status = self::status($f);
        $gefunden = Akquise::normEmail((string) ($f['email'] ?? '')) !== null;
        $senden = $gefunden && $status === self::FREI && !self::partnerHat($f);
        $grund = null;
        if (!$gefunden) { $grund = 'Keine E-Mail-Adresse bekannt.'; }
        elseif ($status === self::NICHT) { $grund = 'Nicht kontaktieren: Werbeversand bleibt blockiert.'; }
        elseif ($status !== self::FREI) { $grund = 'Kein Versand ohne dokumentierten Versandgrund.'; }
        elseif (!$senden) { $grund = 'Ein Partner kümmert sich gerade um diesen Betrieb.'; }
        return [
            'gefunden' => $gefunden,
            'anzeigen' => $gefunden,                       // nie wegen fehlender Einwilligung versteckt
            'entwurf'  => $gefunden && $status !== self::NICHT,
            'senden'   => $senden,
            'werbung'  => $senden && ((int) ($f['email_marketing_consent'] ?? 0) === 1 || self::einwilligungAlt($f)),
            'status'   => $status,
            'grund'    => $grund,
        ];
    }

    private static function partnerHat(array $f): bool
    {
        if (empty($f['id'])) { return false; }
        try {
            require_once __DIR__ . '/PartnerRecherche.php';
            $res = PartnerRecherche::reserviertVon((int) $f['id']);
            if ($res === null) { return false; }
            require_once __DIR__ . '/AkquiseGate.php';
            return !(AkquiseGate::einwilligungDeckt($f, 'email') && AkquiseGate::anrufZugestimmt((int) $f['id']));
        } catch (Throwable $e) { return false; }
    }

    /** @return array{0:string,1:string,2:string} */
    public static function anzeige(array $f): array
    {
        return self::STATUS[self::status($f)];
    }

    /** Verlauf der Dokumentation. @return list<array> */
    public static function verlauf(int $firmaId): array
    {
        try { return Db::all('SELECT * FROM akq_mail_grundlagen WHERE firma_id = ? ORDER BY id DESC LIMIT 50', [$firmaId]); }
        catch (Throwable $e) { return []; }
    }

    /** Die Herkunft der Adresse in Worten -- aus der Suchquelle. */
    public static function quelleAus(?string $quelle): string
    {
        $q = (string) $quelle;
        return match (true) {
            str_starts_with($q, 'osm:')        => 'OpenStreetMap-Eintrag (' . $q . ')',
            str_starts_with($q, 'overture:')   => 'Overture-Maps-Eintrag',
            str_starts_with($q, 'lead-scout:') => 'Lead-Scout (alte Liste)',
            $q === ''                          => 'Suche (Quelle nicht angegeben)',
            default                            => 'Suche: ' . mb_substr($q, 0, 120),
        };
    }

    /* ================================================================== */
    /*  Schreiben                                                         */
    /* ================================================================== */

    /** Status neu berechnen und speichern. */
    public static function statusSchreiben(int $firmaId): string
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f || !array_key_exists('email_contact_status', $f)) { return self::KEINE; }
        $s = self::status($f);
        if ($s !== (string) $f['email_contact_status']) {
            Db::update('akq_firmen', $firmaId, ['email_contact_status' => $s]);
        }
        return $s;
    }

    private static function bearbeiter(): string
    {
        return Auth::angemeldet() ? (Auth::name() ?: 'Verwaltung') : 'System';
    }

    private static function felder(array $f): array
    {
        $k = ['email_verified', 'email_contact_status', 'email_send_allowed', 'email_marketing_consent', 'email_review_requested',
              'email_legal_basis', 'email_legal_basis_date', 'email_legal_basis_source', 'email_legal_basis_note', 'email_legal_basis_by',
              'email_do_not_contact', 'email_dnc_reason', 'email_dnc_note', 'email_dnc_at', 'gesperrt', 'kontakt_status'];
        return array_intersect_key($f, array_flip($k));
    }

    private static function verlaufEintragen(int $firmaId, array $z): void
    {
        Db::insert('akq_mail_grundlagen', $z + ['firma_id' => $firmaId, 'bearbeiter' => self::bearbeiter(), 'user_id' => Auth::id() ?: null]);
    }

    /**
     * „Versandgrund dokumentieren“. Pflicht: Grund, Datum, Quelle/Nachweis,
     * Notiz; Bearbeiter ist der angemeldete Zugang. Ob danach freigegeben
     * ist, hängt vom Grund ab (GRUENDE).
     * @return array{status:string,freigabe:bool,werbung:bool}
     */
    public static function grundDokumentieren(int $firmaId, array $e): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Betrieb nicht gefunden.'); }
        if (self::nichtKontaktieren($f)) {
            throw new RuntimeException('Für diesen Betrieb ist „Nicht kontaktieren“ gesetzt. Das hebt nur ein Admin mit Begründung auf.');
        }
        $grund = (string) ($e['grund'] ?? '');
        if (!isset(self::GRUENDE[$grund])) { throw new RuntimeException('Bitte einen Grund wählen.'); }
        $datum = (string) ($e['datum'] ?? '');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $datum) || strtotime($datum) === false || $datum > date('Y-m-d')) {
            throw new RuntimeException('Bitte das Datum angeben (nicht in der Zukunft).');
        }
        $quelle = mb_substr(trim(strip_tags((string) ($e['quelle'] ?? ''))), 0, 255);
        if (mb_strlen($quelle) < 3) { throw new RuntimeException('Bitte Quelle oder Nachweis angeben (z. B. „E-Mail vom 02.10. an info@…“, „Auftrag Nr. 12“).'); }
        $notiz = mb_substr(trim(strip_tags((string) ($e['notiz'] ?? ''))), 0, 2000);
        if (mb_strlen($notiz) < 3) { throw new RuntimeException('Bitte eine kurze Notiz eintragen.'); }

        [, $freigabeRegel, $werbungRegel] = self::GRUENDE[$grund];
        $haken = array_keys(array_filter((array) ($e['haken'] ?? [])));
        $alleHaken = !array_diff(array_keys(self::BESTAND_HAKEN), $haken);
        $freigabe = match (true) {
            $freigabeRegel === true    => true,
            $freigabeRegel === 'haken' => $alleHaken,
            $freigabeRegel === 'admin' => Auth::rolle() === 'admin' && !empty($e['freigeben']),
            default                    => false,
        };
        $werbung = $freigabe && $werbungRegel === true;
        if ($grund === 'bestandskunde') {
            $notiz .= "\nVoraussetzungen bestätigt: " . ($haken ? implode(', ', array_map(static fn($h) => self::BESTAND_HAKEN[$h] ?? $h, $haken)) : 'keine');
        }

        $neu = [
            'email_legal_basis' => $grund, 'email_legal_basis_date' => $datum, 'email_legal_basis_source' => $quelle,
            'email_legal_basis_note' => $notiz, 'email_legal_basis_by' => self::bearbeiter(),
            'email_send_allowed' => $freigabe ? 1 : 0, 'email_marketing_consent' => $werbung ? 1 : 0,
            'email_review_requested' => $freigabe ? 0 : 1,
        ];
        Db::transaktion(static function () use ($firmaId, $neu, $grund, $datum, $quelle, $notiz, $freigabe, $werbung): void {
            Db::update('akq_firmen', $firmaId, $neu);
            self::verlaufEintragen($firmaId, ['art' => 'grund', 'grund' => $grund, 'datum' => $datum, 'quelle' => $quelle, 'notiz' => $notiz,
                'freigabe' => $freigabe ? 1 : 0, 'werbung' => $werbung ? 1 : 0]);
        }, 3);
        Events::pruefspur('akquise_versandgrund', 'akq_firmen', $firmaId, self::felder($f), $neu);
        Akquise::protokoll($firmaId, 'versandgrund', 'Versandgrund dokumentiert: ' . self::GRUENDE[$grund][0]
            . ($freigabe ? ' — Versand freigegeben' . ($werbung ? ' (auch Werbung)' : ' (keine Werbung)') : ' — Prüfung erforderlich'));
        $status = self::statusSchreiben($firmaId);
        return ['status' => $status, 'freigabe' => $freigabe, 'werbung' => $werbung];
    }

    /** Prüfung anfordern (🟡) -- ohne Freigabe. */
    public static function pruefungAnfordern(int $firmaId, string $notiz): void
    {
        $f = self::firma($firmaId);
        if (self::nichtKontaktieren($f)) { throw new RuntimeException('„Nicht kontaktieren“ ist gesetzt.'); }
        $notiz = mb_substr(trim(strip_tags($notiz)), 0, 2000);
        Db::update('akq_firmen', $firmaId, ['email_review_requested' => 1]);
        self::verlaufEintragen($firmaId, ['art' => 'pruefung', 'notiz' => $notiz ?: null]);
        Events::pruefspur('akquise_mail_pruefung', 'akq_firmen', $firmaId, self::felder($f), ['email_review_requested' => 1]);
        Akquise::protokoll($firmaId, 'versandgrund', 'Prüfung des Versandgrunds angefordert');
        self::statusSchreiben($firmaId);
    }

    /** Freigabe zurücknehmen (Admin): zurück auf 🔴, der Verlauf bleibt. */
    public static function freigabeZuruecknehmen(int $firmaId, string $notiz): void
    {
        self::nurAdmin();
        $f = self::firma($firmaId);
        $neu = ['email_send_allowed' => 0, 'email_marketing_consent' => 0, 'email_review_requested' => 0, 'email_legal_basis' => null,
                'email_legal_basis_date' => null, 'email_legal_basis_source' => null, 'email_legal_basis_note' => null, 'email_legal_basis_by' => null];
        Db::update('akq_firmen', $firmaId, $neu);
        self::verlaufEintragen($firmaId, ['art' => 'zurueck', 'notiz' => mb_substr(trim(strip_tags($notiz)), 0, 2000) ?: null]);
        Events::pruefspur('akquise_mail_zurueck', 'akq_firmen', $firmaId, self::felder($f), $neu);
        Akquise::protokoll($firmaId, 'versandgrund', 'Versandfreigabe zurückgenommen');
        self::statusSchreiben($firmaId);
    }

    /** Adresse als bestätigt / nicht bestätigt markieren (Admin). */
    public static function adresseGeprueft(int $firmaId, bool $ja): void
    {
        self::nurAdmin();
        $f = self::firma($firmaId);
        Db::update('akq_firmen', $firmaId, ['email_verified' => $ja ? 1 : 0]);
        self::verlaufEintragen($firmaId, ['art' => 'geprueft', 'notiz' => $ja ? 'Adresse als bestätigt markiert' : 'Bestätigung der Adresse entfernt']);
        Events::pruefspur('akquise_mail_geprueft', 'akq_firmen', $firmaId, ['email_verified' => (int) ($f['email_verified'] ?? 0)], ['email_verified' => $ja ? 1 : 0]);
    }

    /**
     * „Nicht kontaktieren“ setzen -- darf jeder in der Verwaltung, weil es
     * nur schützt. Der Betrieb kommt dabei auf die Sperrliste (alle Wege).
     */
    public static function nichtKontaktierenSetzen(int $firmaId, string $grund, string $notiz): void
    {
        if (!isset(self::DNC_GRUENDE[$grund])) { throw new RuntimeException('Bitte den Grund wählen: Widerspruch, Abmeldung, Beschwerde oder Kontaktverbot.'); }
        $f = self::firma($firmaId);
        $notiz = mb_substr(trim(strip_tags($notiz)), 0, 255);
        $neu = ['email_do_not_contact' => 1, 'email_dnc_reason' => $grund, 'email_dnc_note' => $notiz ?: null, 'email_dnc_at' => date('Y-m-d H:i:s'),
                'email_send_allowed' => 0, 'email_marketing_consent' => 0];
        Db::update('akq_firmen', $firmaId, $neu);
        self::verlaufEintragen($firmaId, ['art' => 'dnc', 'grund' => $grund, 'notiz' => $notiz ?: null, 'datum' => date('Y-m-d')]);
        Events::pruefspur('akquise_nicht_kontaktieren', 'akq_firmen', $firmaId, self::felder($f), $neu);
        if ((int) $f['gesperrt'] !== 1) {
            require_once __DIR__ . '/AkquiseGate.php';
            AkquiseGate::sperren($firmaId, self::DNC_GRUENDE[$grund][0] . ($notiz !== '' ? ': ' . $notiz : ''), self::DNC_GRUENDE[$grund][1]);
        }
        self::statusSchreiben($firmaId);
    }

    /**
     * „Nicht kontaktieren“ aufheben -- nur Admin, nur mit Begründung, voll
     * protokolliert (alter Stand, Sperrlisten-Zeilen, Begründung).
     */
    public static function nichtKontaktierenAufheben(int $firmaId, string $begruendung): void
    {
        self::nurAdmin();
        $f = self::firma($firmaId);
        $begruendung = mb_substr(trim(strip_tags($begruendung)), 0, 2000);
        if (mb_strlen($begruendung) < 10) { throw new RuntimeException('Bitte begründen, warum „Nicht kontaktieren“ aufgehoben wird (mindestens ein Satz).'); }
        $sperren = Db::all('SELECT art, wert, grund, quelle, actor, created_at FROM akq_sperrliste WHERE firma_id = ?', [$firmaId]);
        $neu = ['email_do_not_contact' => 0, 'email_dnc_reason' => null, 'email_dnc_note' => null, 'email_dnc_at' => null, 'gesperrt' => 0]
             + (in_array((string) $f['kontakt_status'], ['gesperrt', 'abgelehnt'], true) ? ['kontakt_status' => 'neu'] : []);
        Db::transaktion(static function () use ($firmaId, $neu, $begruendung): void {
            Db::run('DELETE FROM akq_sperrliste WHERE firma_id = ?', [$firmaId]);
            Db::update('akq_firmen', $firmaId, $neu);
            self::verlaufEintragen($firmaId, ['art' => 'dnc_aufgehoben', 'notiz' => $begruendung, 'datum' => date('Y-m-d')]);
        }, 3);
        Events::pruefspur('akquise_nicht_kontaktieren_aufgehoben', 'akq_firmen', $firmaId,
            self::felder($f) + ['sperrliste' => $sperren], $neu + ['begruendung' => $begruendung]);
        Akquise::protokoll($firmaId, 'versandgrund', '„Nicht kontaktieren“ von ' . self::bearbeiter() . ' aufgehoben: ' . mb_substr($begruendung, 0, 200));
        require_once __DIR__ . '/AkquiseGate.php';
        AkquiseGate::statusSpeichern($firmaId);
        self::statusSchreiben($firmaId);
    }

    /* ================================================================== */
    /*  Senden über das eigene Mailprogramm (06.10.2026, Uwe: „Das System   */
    /*  soll die E-Mails NICHT selbst über einen eigenen Server oder eine   */
    /*  API versenden. Es soll stattdessen einen mailto:-Link erzeugen …    */
    /*  Das System speichert nur den Zeitstempel der Generierung.“)         */
    /*                                                                      */
    /*  Ersetzt den Direktversand über Brevo vom selben Tag. Gespeichert    */
    /*  wird eine Zeile in akq_versand: Zeitpunkt, wer, an welche Adresse   */
    /*  und ein Abmeldeschlüssel (damit der Abmeldelink im Text wirkt) —    */
    /*  kein Betreff, kein Text. Ob die Mail wirklich rausging, weiß das    */
    /*  System nicht; es weiß nur, dass der Link erzeugt wurde.             */
    /* ================================================================== */

    /** Für Anzeige und Werkstatt-Vorschau: der Absender ist das eigene Programm. @return array{email:?string,name:string,antwort:?string,quelle:string} */
    public static function absender(): array
    {
        return ['email' => null, 'name' => 'dein eigenes Mailprogramm', 'antwort' => null,
                'quelle' => 'die Mail geht aus deinem eigenen Programm (Outlook, Apple Mail …) mit deiner eigenen Adresse raus'];
    }

    /** Ab dieser Länge kürzen manche Programme den Link -- dann liegt der Text zusätzlich in der Zwischenablage. */
    public const MAILTO_LANG = 1900;

    /**
     * mailto:-Link erzeugen. Nur bei 🟢; dieselbe Werkstatt-Prüfung wie bisher.
     * @return array{id:int,link:string,lang:bool,text:string}
     */
    public static function mailtoErzeugen(int $firmaId, string $betreff, string $text, bool $hinweiseGelesen = false): array
    {
        require_once __DIR__ . '/AkquiseText.php';
        $f = self::firma($firmaId);
        $k = self::kann($f);
        if (!$k['senden']) { throw new RuntimeException('Senden geht hier nicht: ' . ($k['grund'] ?? 'kein Versandgrund dokumentiert') . '.'); }
        $betreff = trim(mb_substr(strip_tags($betreff), 0, 200));
        $text = trim(str_replace("\r\n", "\n", strip_tags($text)));
        if ($betreff === '') { throw new RuntimeException('Bitte einen Betreff eintragen.'); }
        if (mb_strlen($text) < 20) { throw new RuntimeException('Der Text ist zu kurz.'); }
        if (mb_strlen($text) > 20000) { throw new RuntimeException('Der Text ist zu lang.'); }
        /* Nachrichten-Werkstatt (Modul D): Stopp nie, Hinweise nur bestätigt — auf dem Server. */
        require_once __DIR__ . '/AkquiseWerkstatt.php';
        AkquiseWerkstatt::freigeben(AkquiseWerkstatt::pruefliste($f, 'email', $betreff, $text, true), $hinweiseGelesen);
        $token = bin2hex(random_bytes(20));
        $sp = AkquiseText::spracheFuer($f);
        $abmelden = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/widerspruch.php?t=' . $token;
        $voll = $text . "\n\n" . (['de' => 'Keine weiteren Nachrichten: ', 'it' => 'Non ricevere altri messaggi: ', 'en' => 'No further messages: '][$sp] ?? '') . $abmelden;
        $id = (int) Db::insert('akq_versand', [
            'firma_id' => $firmaId, 'kanal' => 'email', 'an' => $f['email'], 'status' => 'von_hand',
            'compliance' => (string) $f['compliance_status'], 'abmelde_token' => $token, 'actor' => self::bearbeiter(),
            'grund' => mb_substr('mailto-Link erzeugt · ' . (self::GRUENDE[(string) ($f['email_legal_basis'] ?? '')][0] ?? 'Einwilligung'), 0, 255),
        ]);
        Db::update('akq_firmen', $firmaId, ['versand_status' => 'gesendet']
            + (in_array((string) $f['kontakt_status'], ['neu', 'qualifiziert', 'vorlage', 'freigegeben'], true) ? ['kontakt_status' => 'kontaktiert'] : []));
        Akquise::protokoll($firmaId, 'versand', 'E-Mail im eigenen Mailprogramm geöffnet (mailto-Link erzeugt von ' . self::bearbeiter() . ')', ['versand' => $id]);
        Events::pruefspur('akquise_mailto', 'akq_versand', $id, [], ['an' => $f['email'], 'erzeugt' => date('Y-m-d H:i:s')]);
        $link = 'mailto:' . rawurlencode((string) $f['email']) . '?subject=' . rawurlencode($betreff) . '&body=' . rawurlencode($voll);
        return ['id' => $id, 'link' => $link, 'lang' => strlen($link) > self::MAILTO_LANG, 'text' => $voll];
    }

    private static function firma(int $firmaId): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Betrieb nicht gefunden.'); }
        return $f;
    }

    private static function nurAdmin(): void
    {
        if (Auth::rolle() !== 'admin') { throw new RuntimeException('Das darf nur ein Admin.'); }
    }
}
