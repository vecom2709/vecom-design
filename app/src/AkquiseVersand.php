<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';

/**
 * Vorlage → Freigabe → Versand → Antwort.
 *
 * DIE DREI SCHLOESSER VOR JEDER E-MAIL
 *
 *   1. Der Text ist freigegeben -- von einem Menschen, nach der Textpruefung.
 *   2. Das Gate sagt CONTACT_ALLOWED fuer genau diese Firma und E-Mail.
 *   3. Versand eingeschaltet, Notbremse nicht gezogen, Grenzen eingehalten.
 *
 * Research und Versand sind dadurch getrennt: Der Worker kann Firmen finden,
 * pruefen und Texte vorschlagen, aber er hat keine Tuer zum Verschicken.
 * Senden gibt es nur hier, nur angemeldet, nur mit Klick.
 *
 * REVIEW_REQUIRED heisst: Uwe entscheidet und macht es selbst (Brief,
 * Anruf) -- das System vermerkt nur, MIT seiner Begruendung. Bei
 * DO_NOT_EMAIL und UNKNOWN gibt es keinen Weg vorbei, auch nicht von Hand.
 */
final class AkquiseVersand
{
    /* ================================================================== */
    /*  Vorlagen                                                          */
    /* ================================================================== */

    /**
     * Speichert eine Vorlage. Die Pruefung laeuft immer, beanstandete Texte
     * werden gespeichert (damit man sie bearbeiten kann), aber nie freigegeben.
     */
    public static function vorlageSpeichern(int $firmaId, ?int $auditId, string $sprache, string $kanal,
                                            string $betreff, string $text, string $von = 'hand', ?int $ersetzt = null, ?string $variante = null): int
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        if ((int) $f['gesperrt'] === 1) { throw new RuntimeException('Die Firma ist gesperrt — für sie wird kein Text mehr geschrieben.'); }
        if (!isset(AkquiseText::SPRACHEN[$sprache])) { $sprache = AkquiseText::spracheFuer($f); }
        if (!isset(AkquiseGate::KANAELE[$kanal])) { $kanal = 'email'; }
        $betreff = trim(mb_substr($betreff, 0, 255));
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') { throw new RuntimeException('Der Text ist leer.'); }
        $auditId ??= (int) (Akquise::letzterAudit($firmaId)['id'] ?? 0) ?: null;
        $befunde = $auditId ? Akquise::befunde($auditId) : [];
        $hinweise = AkquiseText::pruefen($betreff, $text, $sprache, $befunde, $kanal, $f);
        $fp = AkquiseText::fingerabdruck($betreff, $text);

        $andere = Db::one('SELECT id, firma_id FROM akq_vorlagen WHERE fingerabdruck = ?', [$fp]);
        if ($andere && (int) $andere['firma_id'] !== $firmaId) {
            throw new RuntimeException('Genau dieser Text existiert schon für eine andere Firma. Jede Ansprache muss individuell sein.');
        }
        if ($andere && (int) $andere['id'] !== (int) $ersetzt) {
            return (int) $andere['id'];   // derselbe Text fuer dieselbe Firma: nichts Neues
        }

        $daten = [
            'firma_id' => $firmaId, 'audit_id' => $auditId, 'sprache' => $sprache, 'kanal' => $kanal,
            'betreff' => $betreff, 'text' => $text, 'erzeugt_von' => in_array($von, ['regel', 'claude', 'hand'], true) ? $von : 'hand',
            'fingerabdruck' => $fp, 'status' => 'entwurf',
            'pruefhinweise' => $hinweise ? json_encode($hinweise, JSON_UNESCAPED_UNICODE) : null,
            'freigegeben_von' => null, 'freigegeben_am' => null,
        ];
        // Die Variante gilt für den erzeugten Text; eine Bearbeitung von Hand behält sie.
        if (in_array($variante, ['A', 'B'], true)) { $daten['variante'] = $variante; }
        if ($ersetzt !== null) {
            $alt = Db::one('SELECT * FROM akq_vorlagen WHERE id = ? AND firma_id = ?', [$ersetzt, $firmaId]);
            if (!$alt) { throw new RuntimeException('Vorlage nicht gefunden.'); }
            if ($alt['status'] === 'gesendet') { throw new RuntimeException('Eine verschickte Vorlage bleibt, wie sie war.'); }
            Db::update('akq_vorlagen', $ersetzt, $daten);
            $id = $ersetzt;
            Akquise::protokoll($firmaId, 'text', 'Kontaktvorlage bearbeitet (' . strtoupper($sprache) . ')' . ($hinweise ? ' — ' . count($hinweise) . ' Beanstandung(en)' : ''));
        } else {
            $id = Db::insert('akq_vorlagen', $daten);
            Akquise::protokoll($firmaId, 'text', 'Kontaktvorlage erzeugt (' . $von . ', ' . strtoupper($sprache) . ')'
                . ($hinweise ? ' — ' . count($hinweise) . ' Beanstandung(en)' : ''));
        }
        if (in_array((string) $f['kontakt_status'], ['neu', 'qualifiziert'], true)) {
            Db::update('akq_firmen', $firmaId, ['kontakt_status' => 'vorlage']);
        }
        return $id;
    }

    /**
     * Erzeugt eine Regel-Vorlage aus dem letzten Audit.
     *
     * Ohne Kanalangabe: E-Mail nur, wenn die Ampel sie erlaubt -- sonst
     * Brief. Ein E-Mail-Entwurf fuer eine Firma, die man nicht anschreiben
     * darf, waere eine Einladung zum Fehler (26.09.2026).
     */
    /** Mit E-Mail-Adresse öffnet der Text im eigenen Mailprogramm (06.10.2026) — außer bei „Nicht kontaktieren“ oder Partner. */
    private static function mailOffen(array $f): bool
    {
        require_once __DIR__ . '/AkquiseMail.php';
        return AkquiseMail::kann($f)['senden'];
    }

    public static function regelVorlage(int $firmaId, ?string $sprache = null, ?string $kanal = null): int
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        $kanal = in_array($kanal, ['email', 'brief'], true) ? $kanal
            : (AkquiseGate::ampel($f)['farbe'] === 'gruen' || self::mailOffen($f) ? 'email' : 'brief');
        if ($kanal === 'brief' && !AkquiseGate::briefAn()) {
            throw new RuntimeException('Briefe sind ausgeschaltet. Eine E-Mail geht nur mit Einwilligung — die entsteht über den Website-Check oder einen Einwilligungs-Link nach einem Gespräch.');
        }
        $audit = Akquise::letzterAudit($firmaId);
        if (!$audit || $audit['status'] !== 'fertig') { throw new RuntimeException('Es gibt noch kein fertiges Audit — ohne Befunde kein Text.'); }
        $sprache = $sprache && isset(AkquiseText::SPRACHEN[$sprache]) ? $sprache : AkquiseText::spracheFuer($f);
        $e = AkquiseText::erzeugen($f, $audit, Akquise::befunde((int) $audit['id']), $sprache, $kanal);
        return self::vorlageSpeichern($firmaId, (int) $audit['id'], $sprache, $kanal, $e['betreff'], $e['text'], 'regel', null, $e['variante'] ?? 'A');
    }

    public static function freigeben(int $vorlageId): void
    {
        $v = Db::one('SELECT * FROM akq_vorlagen WHERE id = ?', [$vorlageId]);
        if (!$v) { throw new RuntimeException('Vorlage nicht gefunden.'); }
        if ($v['status'] !== 'entwurf') { throw new RuntimeException('Nur Entwürfe lassen sich freigeben.'); }
        if ($v['kanal'] === 'brief' && !AkquiseGate::briefAn()) { throw new RuntimeException('Briefe sind ausgeschaltet — dieser Brieftext bleibt gespeichert, lässt sich aber nicht freigeben.'); }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $v['firma_id']]) ?? [];
        $befunde = $v['audit_id'] ? Akquise::befunde((int) $v['audit_id']) : [];
        $hinweise = AkquiseText::pruefen((string) $v['betreff'], (string) $v['text'], (string) $v['sprache'], $befunde, (string) $v['kanal'], $f);
        if ($hinweise) {
            Db::update('akq_vorlagen', $vorlageId, ['pruefhinweise' => json_encode($hinweise, JSON_UNESCAPED_UNICODE)]);
            throw new RuntimeException('Der Text hat noch Beanstandungen: ' . implode(' · ', $hinweise));
        }
        if ((int) ($f['gesperrt'] ?? 0) === 1) { throw new RuntimeException('Die Firma ist gesperrt.'); }
        Db::update('akq_vorlagen', $vorlageId, [
            'status' => 'freigegeben', 'freigegeben_von' => Auth::name() ?: 'System',
            'freigegeben_am' => date('Y-m-d H:i:s'), 'pruefhinweise' => null,
        ]);
        if (in_array((string) $f['kontakt_status'], ['neu', 'qualifiziert', 'vorlage'], true)) {
            Db::update('akq_firmen', (int) $v['firma_id'], ['kontakt_status' => 'freigegeben']);
        }
        Akquise::protokoll((int) $v['firma_id'], 'freigabe', 'Kontaktvorlage freigegeben', ['vorlage' => $vorlageId]);
        Events::pruefspur('akquise_freigabe', 'akq_vorlagen', $vorlageId);
    }

    public static function verwerfen(int $vorlageId): void
    {
        $v = Db::one('SELECT * FROM akq_vorlagen WHERE id = ?', [$vorlageId]);
        if (!$v || $v['status'] === 'gesendet') { throw new RuntimeException('Diese Vorlage lässt sich nicht verwerfen.'); }
        Db::update('akq_vorlagen', $vorlageId, ['status' => 'verworfen']);
        Akquise::protokoll((int) $v['firma_id'], 'text', 'Kontaktvorlage verworfen', ['vorlage' => $vorlageId]);
    }

    /* ================================================================== */
    /*  Versand                                                           */
    /* ================================================================== */

    /** Haelt einen verhinderten Versuch fest -- er ist der Beweis, dass das Gate arbeitet. */
    private static function blockiert(array $f, ?int $vorlageId, string $kanal, string $compliance, string $grund): never
    {
        Db::insert('akq_versand', [
            'firma_id' => (int) $f['id'], 'vorlage_id' => $vorlageId, 'kanal' => $kanal, 'an' => $f['email'] ?? null,
            'status' => 'blockiert', 'compliance' => $compliance, 'grund' => mb_substr($grund, 0, 255),
            'actor' => Auth::angemeldet() ? Auth::name() : 'System',
        ]);
        Akquise::protokoll((int) $f['id'], 'versand_blockiert', 'Versand verhindert: ' . $grund);
        throw new RuntimeException('Nicht verschickt: ' . $grund);
    }

    /**
     * Verschickt eine freigegebene E-Mail-Vorlage.
     *
     * @param string $pruefvermerk Bei REVIEW_REQUIRED Pflicht: Wer hat was geprueft.
     */
    public static function senden(int $vorlageId, string $pruefvermerk = '', array $anhaenge = []): int
    {
        require_once __DIR__ . '/Mail.php';
        $v = Db::one('SELECT * FROM akq_vorlagen WHERE id = ?', [$vorlageId]);
        if (!$v) { throw new RuntimeException('Vorlage nicht gefunden.'); }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $v['firma_id']]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        if ($v['kanal'] !== 'email') { throw new RuntimeException('Nur E-Mails verschickt das System selbst. Brief und Anruf bitte von Hand vermerken.'); }
        if ($v['status'] !== 'freigegeben') { self::blockiert($f, $vorlageId, 'email', (string) $f['compliance_status'], 'Die Vorlage ist nicht freigegeben.'); }

        $gate = AkquiseGate::pruefen($f, 'email');
        if (in_array($gate['status'], [AkquiseGate::NICHT, AkquiseGate::UNKLAR], true)) {
            self::blockiert($f, $vorlageId, 'email', $gate['status'], 'Anschreiben: ' . AkquiseGate::STATUS[$gate['status']] . ' — ' . ($gate['gruende'][0] ?? ''));
        }
        if ($gate['status'] === AkquiseGate::PRUEFEN && mb_strlen(trim($pruefvermerk)) < 15) {
            self::blockiert($f, $vorlageId, 'email', $gate['status'], 'Prüfung nötig — ohne dokumentierten Prüfvermerk geht nichts raus.');
        }
        if (empty($f['email'])) { self::blockiert($f, $vorlageId, 'email', $gate['status'], 'Keine E-Mail-Adresse.'); }
        $sperre = AkquiseGate::versandSperre($f);
        if ($sperre !== null) { self::blockiert($f, $vorlageId, 'email', $gate['status'], $sperre); }

        $r = self::rausschicken($f, (string) $v['betreff'], (string) $v['text'], (string) $v['sprache'], $vorlageId, $gate['status'],
            $gate['status'] === AkquiseGate::PRUEFEN ? 'Prüfvermerk: ' . trim($pruefvermerk) : null, $anhaenge ? ['anhaenge' => $anhaenge] : []);
        $versandId = $r['id'];
        if ($r['simuliert']) { return $versandId; }
        Db::update('akq_vorlagen', $vorlageId, ['status' => 'gesendet']);
        Db::update('akq_firmen', (int) $f['id'], ['versand_status' => 'gesendet', 'kontakt_status' => 'kontaktiert']);
        Akquise::protokoll((int) $f['id'], 'versand', 'Kontakt per E-Mail versendet an ' . $f['email'], ['versand' => $versandId]);
        Events::pruefspur('akquise_versand', 'akq_versand', $versandId, [], ['an' => $f['email'], 'compliance' => $gate['status']]);
        require_once __DIR__ . '/AkquiseSignal.php';
        AkquiseSignal::vormerken((int) $f['id'], 'email');
        return $versandId;
    }

    /**
     * Der eine Weg, auf dem eine Akquise-Mail das Haus verlässt -- für die
     * freigegebene Einzelmail und für die Folge-Mails (27.09.2026). Gate und
     * Grenzen prüft der Aufrufer vorher; hier kommen Abmeldelink, List-
     * Unsubscribe und der Testbetrieb dazu. Im Testbetrieb entsteht ein
     * Eintrag „simuliert“ -- nichts geht an Brevo, am Kontaktstand ändert
     * sich nichts. Scheitert Brevo, wirft die Methode.
     *
     * @return array{id:int, simuliert:bool}
     */
    /** @var null|callable(string $an, string $betreff, string $text): bool  Austauschbar für die Kette (dort gibt es kein Brevo). */
    public static $postbote = null;

    public static function rausschicken(array $f, string $betreff, string $text, string $sprache, ?int $vorlageId, string $gate, ?string $grund = null, array $optionen = []): array
    {
        require_once __DIR__ . '/Mail.php';
        $token = bin2hex(random_bytes(20));
        $actor = class_exists('Auth', false) && Auth::angemeldet() ? Auth::name() : 'System';   // im Cron gibt es keine Anmeldung
        if (AkquiseGate::testbetrieb()) {
            $id = (int) Db::insert('akq_versand', [
                'firma_id' => (int) $f['id'], 'vorlage_id' => $vorlageId, 'kanal' => 'email', 'an' => $f['email'],
                'status' => 'simuliert', 'compliance' => $gate, 'grund' => mb_substr('Testbetrieb — nicht verschickt' . ($grund ? ' · ' . $grund : ''), 0, 255),
                'abmelde_token' => $token, 'actor' => $actor,
            ]);
            Akquise::protokoll((int) $f['id'], 'testbetrieb', 'Testbetrieb: E-Mail an ' . $f['email'] . ' nur simuliert — „' . mb_substr($betreff, 0, 80) . '“', ['versand' => $id]);
            return ['id' => $id, 'simuliert' => true];
        }
        $abmelden = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/widerspruch.php?t=' . $token;
        $zusatz = [
            'de' => "\n\nKeine weiteren Nachrichten: $abmelden",
            'it' => "\n\nNon ricevere altri messaggi: $abmelden",
            'en' => "\n\nNo further messages: $abmelden",
        ][$sprache] ?? "\n\n$abmelden";
        $versandId = (int) Db::insert('akq_versand', [
            'firma_id' => (int) $f['id'], 'vorlage_id' => $vorlageId, 'kanal' => 'email', 'an' => $f['email'],
            'status' => 'fehler', 'compliance' => $gate, 'grund' => $grund !== null ? mb_substr($grund, 0, 255) : null,
            'abmelde_token' => $token, 'actor' => $actor,
        ]);
        /* $optionen (06.10.2026): absender/antwortAn für den Direktversand aus der Verwaltung -- Mail::senden lässt nur die eigene Domain zu. */
        /* anhaenge (07.10.2026): der Branchen-Flyer zur Werbe-Mail nach Uwes Ja (AkquiseWerbung). */
        $optionen = array_intersect_key($optionen, ['absender' => 1, 'antwortAn' => 1, 'anhaenge' => 1]);
        $ok = self::$postbote ? (bool) (self::$postbote)((string) $f['email'], $betreff, $text . $zusatz, $optionen) : Mail::senden('akquise', (string) $f['email'], $betreff, $text . $zusatz, [
            'kopfzeilen' => ['List-Unsubscribe' => '<' . $abmelden . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
            'sprache' => $sprache,
            'nurText' => true,
        ] + $optionen);
        /* Mail-Spur (07.10.2026): die Zeile in `mails` an den Versand hängen — die Firmenakte zeigt die Mail
           dann mit Stand und Text. Nur, wenn der echte Postbote lief (der Prüf-Postbote schreibt keine Zeile). */
        if (!self::$postbote && Mail::$letzteId !== null) {
            try { Db::update('akq_versand', $versandId, ['mail_id' => Mail::$letzteId]); } catch (Throwable $e) { }
        }
        if (!$ok) {
            Db::update('akq_versand', $versandId, ['grund' => 'Brevo hat die Nachricht nicht angenommen — siehe E-Mail-Protokoll.']);
            Db::update('akq_firmen', (int) $f['id'], ['versand_status' => 'fehler']);
            Akquise::protokoll((int) $f['id'], 'versand_fehler', 'Versand gescheitert');
            throw new RuntimeException('Der Versand ist gescheitert. Einzelheiten im E-Mail-Protokoll.');
        }
        Db::update('akq_versand', $versandId, ['status' => 'gesendet']);
        return ['id' => $versandId, 'simuliert' => false];
    }

    /**
     * Uwe hat selbst Kontakt aufgenommen (Brief, Anruf, persoenlich).
     *
     * Auch das ist eine Kontaktaufnahme und sperrt die Zweitansprache. Bei
     * REVIEW_REQUIRED braucht es seine Begruendung (z. B. warum ein Anruf
     * mutmasslich erwuenscht war) -- sie steht danach im Versandprotokoll.
     */
    public static function vonHand(int $firmaId, string $kanal, string $begruendung, ?int $vorlageId = null): int
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        if (!isset(AkquiseGate::KANAELE[$kanal])) { throw new RuntimeException('Unbekannter Kanal.'); }
        $gate = AkquiseGate::pruefen($f, $kanal);
        if (in_array($gate['status'], [AkquiseGate::NICHT, AkquiseGate::UNKLAR], true)) {
            self::blockiert($f, $vorlageId, $kanal, $gate['status'], 'Anschreiben: ' . AkquiseGate::STATUS[$gate['status']] . ' — ' . ($gate['gruende'][0] ?? ''));
        }
        if ($gate['status'] === AkquiseGate::PRUEFEN && mb_strlen(trim($begruendung)) < 15) {
            throw new RuntimeException('Bitte kurz begründen, warum die Kontaktaufnahme hier zulässig ist (mind. 15 Zeichen).');
        }
        $id = Db::insert('akq_versand', [
            'firma_id' => $firmaId, 'vorlage_id' => $vorlageId, 'kanal' => $kanal,
            'an' => match ($kanal) { 'telefon' => $f['telefon'], 'whatsapp' => $f['whatsapp'] ?? $f['telefon'], 'email' => $f['email'], default => $f['adresse'] ?? null },
            'status' => 'von_hand', 'compliance' => $gate['status'], 'grund' => mb_substr(trim($begruendung), 0, 255),
            'actor' => Auth::angemeldet() ? Auth::name() : 'System',
        ]);
        if ($vorlageId) { Db::run("UPDATE akq_vorlagen SET status = 'gesendet' WHERE id = ? AND firma_id = ?", [$vorlageId, $firmaId]); }
        Db::update('akq_firmen', $firmaId, ['versand_status' => 'gesendet', 'kontakt_status' => 'kontaktiert']);
        Akquise::protokoll($firmaId, 'versand', 'Von Hand kontaktiert (' . AkquiseGate::KANAELE[$kanal] . ')', ['versand' => $id]);
        Events::pruefspur('akquise_von_hand', 'akq_versand', $id, [], ['kanal' => $kanal, 'begruendung' => $begruendung]);
        require_once __DIR__ . '/AkquiseSignal.php';
        AkquiseSignal::vormerken($firmaId, $kanal);
        return $id;
    }

    /**
     * WhatsApp-Link mit vorgefülltem Text (27.09.2026). Nur wenn das Gate für
     * WhatsApp „erlaubt“ sagt -- also nach bestätigter Einwilligung mit genau
     * dieser Nummer. Das System schickt selbst nichts per WhatsApp: Der Link
     * öffnet WhatsApp, Uwe liest und drückt selbst auf Senden.
     */
    public static function whatsappLink(array $f, string $sprache, string $analyse = ''): ?string
    {
        if (AkquiseGate::pruefen($f, 'whatsapp')['status'] !== AkquiseGate::ERLAUBT) { return null; }
        $ziffern = preg_replace('~\D~', '', (string) ($f['whatsapp'] ?? ''));
        if (strlen((string) $ziffern) < 8) { return null; }
        $abs = AkquiseText::absender();
        $w = ['{inhaber}' => $abs['inhaber'], '{absender}' => $abs['firma'], '{firma}' => (string) $f['name'], '{link}' => $analyse];
        $t = static fn(string $k) => strtr(Texte::h(Texte::AKQ_WHATSAPP[$k], $sprache), $w);
        $text = $t('hallo') . "\n\n" . ($analyse !== '' ? $t('analyse') : $t('ohne')) . "\n\n" . $t('stopp');
        return 'https://wa.me/' . $ziffern . '?text=' . rawurlencode($text);
    }

    /**
     * Der Brief ist raus -- Uwe hat ihn gedruckt und eingeworfen.
     *
     * Brief steht auf "Pruefung noetig": Werbewiderspruch und
     * Informationspflicht muessen erfuellt sein. Die Bestaetigung dieses
     * Klicks IST die dokumentierte Pruefung; sie steht mit Datum und Name
     * im Versandprotokoll und in der Pruefspur.
     */
    public static function briefVerschickt(int $firmaId, int $vorlageId, bool $bestaetigt): int
    {
        if (!$bestaetigt) {
            throw new RuntimeException('Bitte bestätigen: kein Werbewiderspruch bekannt, Hinweise im Brief enthalten.');
        }
        $v = Db::one("SELECT * FROM akq_vorlagen WHERE id = ? AND firma_id = ? AND kanal = 'brief'", [$vorlageId, $firmaId]);
        if (!$v || $v['status'] !== 'freigegeben') { throw new RuntimeException('Erst den Brief freigeben, dann drucken und verschicken.'); }
        return self::vonHand($firmaId, 'brief', 'Brief per Post am ' . date('d.m.Y') . ' — kein Werbewiderspruch bekannt, '
            . 'Widerspruchs- und Datenschutzhinweis im Brief (bestätigt von ' . (Auth::name() ?: 'System') . ')', $vorlageId);
    }

    /**
     * Ergebnis eines Anrufs, mit einem Klick.
     *
     * "per_mail": Der Inhaber bittet am Telefon um Zusendung per E-Mail --
     * das ist eine Einwilligung, und sie wird genau so festgehalten (wer,
     * wann, wie). Danach steht die Ampel fuer E-Mail auf gruen.
     */
    public static function anrufErgebnis(int $firmaId, string $ergebnis, string $begruendung, string $email = ''): string
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        if ($ergebnis === 'nicht_erreicht') {
            Akquise::protokoll($firmaId, 'anruf', 'Angerufen, nicht erreicht' . (trim($begruendung) !== '' ? ' — ' . mb_substr(trim($begruendung), 0, 200) : ''));
            return 'Vermerkt: nicht erreicht. Du kannst es später noch einmal versuchen.';
        }
        if (!in_array($ergebnis, ['interesse', 'kein_interesse', 'per_mail'], true)) { throw new RuntimeException('Unbekanntes Ergebnis.'); }
        self::vonHand($firmaId, 'telefon', $begruendung);
        if ($ergebnis === 'kein_interesse') {
            self::antwortEintragen($firmaId, 'Telefon', 'Anruf', 'Am Telefon: kein Interesse.', 'NOT_INTERESTED');
            return 'Vermerkt und gesperrt — wir melden uns dort nicht mehr.';
        }
        if ($ergebnis === 'per_mail') {
            $mail = Akquise::normEmail($email);
            if ($mail === null) { throw new RuntimeException('Bitte die E-Mail-Adresse eintragen, die genannt wurde.'); }
            $alt = ['einwilligung' => $f['einwilligung'], 'email' => $f['email']];
            $neu = ['email' => $mail, 'einwilligung' => mb_substr('Anruf am ' . date('d.m.Y H:i') . ' (' . (Auth::name() ?: 'Uwe')
                . '): bittet um Zusendung der Beobachtungen per E-Mail an ' . $mail, 0, 255)];
            Db::update('akq_firmen', $firmaId, $neu);
            Events::pruefspur('akquise_rechtsgrundlage', 'akq_firmen', $firmaId, $alt, $neu);
            AkquiseGate::statusSpeichern($firmaId);
            self::antwortEintragen($firmaId, 'Telefon', 'Anruf', 'Am Telefon: bittet um Zusendung per E-Mail.', 'MORE_INFO');
            return 'Einwilligung festgehalten. Die E-Mail ist jetzt erlaubt — Text prüfen und senden.';
        }
        self::antwortEintragen($firmaId, 'Telefon', 'Anruf', 'Am Telefon: Interesse.', 'INTERESTED');
        return 'Vermerkt: Interesse. Jetzt Termin oder Angebot.';
    }

    /* ================================================================== */
    /*  Widerspruch und Antworten                                         */
    /* ================================================================== */

    /** Der Abmeldelink. Liefert die Sprache fuer die Bestaetigungsseite oder null. */
    public static function widerspruch(string $token): ?string
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $token)) { return null; }
        $v = Db::one('SELECT v.*, t.sprache FROM akq_versand v LEFT JOIN akq_vorlagen t ON t.id = v.vorlage_id WHERE v.abmelde_token = ?', [$token]);
        if (!$v) { return null; }
        $f = Db::one('SELECT gesperrt FROM akq_firmen WHERE id = ?', [(int) $v['firma_id']]);
        if ($f && (int) $f['gesperrt'] === 0) {
            AkquiseGate::sperren((int) $v['firma_id'], 'Widerspruch über den Abmeldelink', 'abmeldung');
        }
        return (string) ($v['sprache'] ?? 'it');
    }

    public static function antwortEintragen(int $firmaId, string $von, string $betreff, string $text, string $klasse = ''): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        $quelle = 'hand';
        if (!isset(AkquiseText::ANTWORT_KLASSEN[$klasse])) {
            $klasse = AkquiseText::klassifizieren($betreff, $text);
            $quelle = 'regel';
        }
        $versand = Db::one("SELECT id FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand') ORDER BY id DESC LIMIT 1", [$firmaId]);
        $id = Db::insert('akq_antworten', [
            'firma_id' => $firmaId, 'versand_id' => $versand['id'] ?? null, 'eingang_am' => date('Y-m-d H:i:s'),
            'von' => mb_substr(trim($von), 0, 190) ?: null, 'betreff' => mb_substr(trim($betreff), 0, 255) ?: null,
            'text' => $text !== '' ? $text : null, 'klasse' => $klasse, 'klasse_quelle' => $quelle,
        ]);
        Db::update('akq_firmen', $firmaId, ['antwort_status' => $klasse, 'kontakt_status' => 'geantwortet']);
        Akquise::protokoll($firmaId, 'antwort', 'Antwort erhalten: ' . AkquiseText::ANTWORT_KLASSEN[$klasse] . ' (' . $quelle . ')', ['antwort' => $id]);

        if ($klasse === 'DO_NOT_CONTACT') {
            AkquiseGate::sperren($firmaId, 'Hat der Kontaktaufnahme widersprochen', 'antwort');
        } elseif ($klasse === 'NOT_INTERESTED') {
            // "Kein Interesse" heisst auch: keine weitere Ansprache.
            AkquiseGate::sperren($firmaId, 'Kein Interesse — keine weitere Ansprache', 'antwort');
            Db::update('akq_firmen', $firmaId, ['kontakt_status' => 'abgelehnt']);
        } elseif ($klasse === 'INVALID_ADDRESS' && !empty($versand['id'])) {
            Db::update('akq_versand', (int) $versand['id'], ['status' => 'bounce']);
            Db::update('akq_firmen', $firmaId, ['versand_status' => 'bounce']);
        } elseif (in_array($klasse, ['INTERESTED', 'CALL_REQUEST', 'PRICE_REQUEST', 'MORE_INFO'], true)) {
            // Keine automatische Antwort -- ein Mensch meldet sich.
            Events::melden('akquise_antwort', 'Akquise: ' . AkquiseText::ANTWORT_KLASSEN[$klasse] . ' — ' . $f['name'],
                'info', null, 'akquise/' . $firmaId);
        }
        /* Ein fertiger Antwortentwurf in AI Freigaben (07.10.2026, Vorschlag 7) — raus nur mit Uwes Ja. */
        if ($quelle !== 'hand') {
            try { require_once __DIR__ . '/KiAntwort.php'; KiAntwort::nachEingang((int) $id, 'email'); } catch (Throwable $e) { }
        }
        return ['id' => $id, 'klasse' => $klasse];
    }
}
