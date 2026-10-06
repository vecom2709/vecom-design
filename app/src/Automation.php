<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   Automation.php — das Automation Center (Phase 8, 06.10.2026).

   Uwe: Probelauf „zeigt Liste“, Not-Aus „alles, was rausgeht“, Fehler
   „Meldung + Handy“, Rechte „Schalten Admin, Not-Aus alle“.

   EIN REGISTER FÜR JEDE REGEL
   Der Cron fragt vor jeder Aufgabe hier nach. Eine Aufgabe ohne Eintrag
   läuft nicht — die Kette prüft, dass jeder Schlüssel im Cron hier steht.
   Sonst gäbe es wieder einen Weg nach draußen, den niemand abschalten kann.

   STUFEN (Spezifikation)
     A  läuft allein. raus = false: nichts verlässt das Haus (Prüfen, Lesen,
        Aufräumen, Meldungen an Uwe selbst). raus = true: erreicht Kunden,
        Partner oder die Öffentlichkeit, oder bewegt Geld.
     B  bereitet vor, ein Mensch gibt frei (Entwürfe). Verlässt nie das Haus.
     C  nur von Hand — steht hier zur Übersicht, läuft nie im Cron.

   NOT-AUS
   Hält jede Regel mit raus = true an, sofort und für alle — und seit
   06.10.2026 auch die Wege außerhalb des Cron (ausgangGesperrt, siehe
   unten). Prüfungen, Zahlungsabgleich und Sicherung laufen weiter: Ein Not-Aus, der auch die
   Sicherung stoppt, wäre ein zweiter Schaden. Ziehen darf ihn jede Rolle
   mit Zugang zur Verwaltung außer „Nur lesen“; lösen nur der Admin.

   AB WERK wie vorher: Jede Regel ist an. Die eigenen Schalter der Bereiche
   (Notbremse der Akquise, Autopilot, automatische Partner-Auszahlung …)
   bleiben, dieses Register liegt darüber.
   ========================================================================== */
final class Automation
{
    public const BEREICHE = ['kunden' => 'Kunden & Projekte', 'geld' => 'Geld', 'hosting' => 'Hosting & Seiten', 'partner' => 'Partner',
                             'shop' => 'Marketing Center (Druck)', 'akquise' => 'Akquise', 'marketing' => 'Marketing & Kanäle', 'system' => 'System'];
    /** So oft hintereinander gescheitert → Meldung in der Verwaltung und Zuruf aufs Handy (einmal, bis es wieder klappt). */
    public const FEHLER_MELDEN_AB = 3;

    /** Regel => [Titel, Bereich, Stufe, raus, was sie tut]. */
    public const REGELN = [
        // Kunden & Projekte
        'erinnerungen'   => ['Fragebogen-Erinnerung', 'kunden', 'A', true, 'Erinnert Kunden an einen offenen Fragebogen — höchstens zweimal.'],
        'zugaenge'       => ['Zugangs-Erinnerung', 'kunden', 'A', true, 'Erinnert an nie geöffnete Zugänge (höchstens dreimal) und räumt abgelaufene weg.'],
        'vorwissen'      => ['Vorwissen nachholen', 'kunden', 'A', false, 'Liest alte Website und P. IVA, wo der Fragebogen es nicht geschafft hat.'],
        'angebote'       => ['Abgelaufene Angebote schließen', 'kunden', 'A', false, 'Setzt Angebote nach ihrer Frist auf abgelaufen.'],
        'abnahme'        => ['Fertige Seiten prüfen', 'kunden', 'A', false, 'Prüft fertige Seiten einmal am Tag und meldet nur, was schlechter wurde.'],
        'rueckrufe'      => ['Liegende Rückrufe melden', 'kunden', 'A', false, 'Eine Meldung an dich, wenn Rückrufe zu lange liegen.'],
        'telefon_rueckblick' => ['Wochenblick Telefonassistentin', 'kunden', 'A', false, 'Einmal die Woche: Vorschläge an dich, nichts wird geändert.'],
        'gespraeche'     => ['Gespräche von STRATO holen', 'kunden', 'A', false, 'Holt die Anrufe stündlich herüber (nur lesen).'],
        'netlify'        => ['Netlify-Vorschau erinnern', 'kunden', 'A', false, 'Erinnert dich nach vier Wochen an die Vorschau (löscht nie).'],
        // Geld
        'zahlabgleich'   => ['Zahlungsabgleich', 'geld', 'A', false, 'Fragt offene Zahlungen beim Anbieter nach und bucht Eingänge. Beleg, Auftragsbestätigung und Fragebogen, die daraus folgen, hält der Not-Aus zurück (06.10.2026).'],
        'zahllinks'      => ['Zahlungslinks ablaufen lassen', 'geld', 'A', false, 'Abgelaufene Links zurück auf „ausstehend“.'],
        'abbuchungen'    => ['Abbuchung fälliger Raten', 'geld', 'A', true, 'Bucht angekündigte Raten am Fälligkeitstag über Stripe ab.'],
        'mahnungen'      => ['Mahnung Stufe 1', 'geld', 'A', true, 'Erste Zahlungserinnerung drei Tage nach Fälligkeit, mit frischem Link. Stufe 2 und 3 bleiben bei dir.'],
        'betreuung'      => ['Betreuungsmonate anlegen und anfordern', 'geld', 'A', true, 'Legt fällige Monate als Raten an und fordert sie gleich per Mail an bzw. kündigt die Abbuchung an. Bis 06.10.2026 stand hier „fordert nichts an“ — das stimmte nicht.'],
        'abos'           => ['Gekündigte Verträge beenden', 'geld', 'A', false, 'Setzt Verträge nach ihrem Enddatum auf beendet.'],
        'steuerakte'     => ['Paket fürs Finanzamt', 'geld', 'A', false, 'Baut das Jahrespaket jeden Morgen neu.'],
        // Hosting & Seiten
        'websites'       => ['Erreichbarkeit der Seiten', 'hosting', 'A', false, 'Prüft, ob die Kundenseiten antworten.'],
        'ssl'            => ['Zertifikate', 'hosting', 'A', false, 'Warnt vor ablaufenden Zertifikaten.'],
        'hosting'        => ['Hosting-Schritte fortsetzen', 'hosting', 'A', true, 'Wiederholt liegengebliebene Schritte im KAS; am Ende geht der Zugang an den Kunden.'],
        'speicher'       => ['Speicher der Kunden', 'hosting', 'A', false, 'Warnt ab 90 % belegtem Speicher.'],
        'reseller'       => ['Reseller-Vertrag lesen', 'hosting', 'A', false, 'Liest den Vertrag (nur lesen).'],
        'bericht'        => ['Monatsbericht an Hosting-Kunden', 'hosting', 'A', true, 'Ab dem Ersten einmal im Monat an jeden Hosting-Kunden.'],
        'registriert'    => ['Neue Domains nachsehen', 'hosting', 'A', false, 'Merkt, wenn eine bestellte Domain da ist.'],
        'https'          => ['HTTPS der Domains', 'hosting', 'A', false, 'Prüft HTTPS, bis es steht, dann täglich.'],
        'altseiten'      => ['Alte Seiten sichern', 'hosting', 'A', false, 'Sichert alte Seiten portionsweise.'],
        'mailumzug'      => ['E-Mail-Umzüge', 'hosting', 'A', false, 'Kopiert Postfächer portionsweise (lesend von der alten Seite).'],
        'seitenumzug'    => ['Seiten-Umzüge', 'hosting', 'A', false, 'Zieht Seiten portionsweise um.'],
        'domains'        => ['Domain-Umzüge nachsehen', 'hosting', 'A', false, 'Zeigen die Nameserver schon auf All-Inkl?'],
        'hosting_abgelaufen' => ['Abgelaufene Zugangsdaten löschen', 'hosting', 'A', false, 'Löscht verschlüsselte Zugangsdaten nach der Frist.'],
        // Partner
        'partner'        => ['Provisionen reifen lassen', 'partner', 'A', false, 'Gibt Provisionen nach 14 Tagen frei, prüft Stripe-Konten und Auffälliges.'],
        'partner_auszahlung' => ['Partner-Auszahlung', 'partner', 'A', true, 'Zahlt bereite Provisionen automatisch aus — bewusste Ausnahme, mit Tageslimit.'],
        'partner_hinweise' => ['Hinweise an Partner', 'partner', 'A', true, 'Nachhaken, Kurstag, Meilensteine, ruhende Partner, Jahresübersicht.'],
        'partner_berichte' => ['Monatsbericht an Partner', 'partner', 'A', true, 'Am Monatsersten der Bericht an jeden Partner.'],
        'partner_weg'    => ['Auszahlungsweg fehlt', 'partner', 'A', true, 'Erinnert höchstens alle 14 Tage, wenn Geld bereitliegt und der Weg fehlt.'],
        'partner_impuls' => ['Wochenimpuls', 'partner', 'A', true, 'Einmal die Woche ein Impuls an die Partner.'],
        'partner_neufassung' => ['Neue Partnervereinbarung', 'partner', 'A', true, 'Einmal Bescheid, dass der Bereich bis zur Zustimmung gesperrt ist.'],
        'partner_weckruf' => ['Weckruf', 'partner', 'A', true, 'Nach 30 stillen Tagen, höchstens monatlich.'],
        'partner_autopilot' => ['Partner-Autopilot', 'partner', 'A', true, 'Fünf Betriebe am Tag für Partner, die ihn eingeschaltet haben.'],
        'partner_automatik' => ['Partner-Automatik', 'partner', 'A', true, 'Was jeder Partner für sich eingeschaltet hat.'],
        'partner_rueckrufe' => ['Anrufliste der Partner', 'partner', 'A', true, 'Morgens die fälligen Rückrufe an den Partner.'],
        'partner_vecom_adressen' => ['@vecom-Adressen lesen', 'partner', 'A', false, 'Liest die Adressen im KAS und ordnet eindeutige zu (legt nichts an).'],
        // Marketing Center (Druck)
        'wm_gelato'      => ['Gelato-Aufträge', 'shop', 'A', true, 'Liest den Stand; bei Versand eine Mail an den Partner.'],
        'wm_helloprint'  => ['HelloPrint-Aufträge', 'shop', 'A', true, 'Liest den Stand; bei Versand eine Mail an den Partner.'],
        'wm_printful'    => ['Printful-Aufträge', 'shop', 'A', true, 'Liest den Stand; bei Versand eine Mail an den Partner.'],
        'wm_preise_alt'  => ['Alte Druckpreise melden', 'shop', 'A', false, 'Ab Tag 25 eine Meldung an dich, welche Preise zu prüfen sind.'],
        'wm_printful_fotos' => ['Produktfotos holen', 'shop', 'A', false, 'Holt fertig gerechnete Fotos bei Printful.'],
        'wm_printful_flaechen' => ['Druckflächen messen', 'shop', 'A', false, 'Liest die Druckflächen (nur lesen).'],
        'wm_gelato_katalog' => ['Gelato-Katalog', 'shop', 'A', false, 'Sucht Artikel für neue Produkte (nur lesen).'],
        'wm_printful_vorlagenfotos' => ['Fotos je Gestaltung', 'shop', 'A', false, 'Musterfoto je Gestaltung, nach und nach.'],
        'wm_printful_preise' => ['Printful-Preise', 'shop', 'A', false, 'Preise je Auflage und Land, alle 7 Tage (nur lesen).'],
        'wm_gelato_preise' => ['Gelato-Preise', 'shop', 'A', false, 'Preise je Auflage und Land, alle 7 Tage (nur lesen).'],
        'wm_abgleich'    => ['Werbemittel-Zahlungen', 'shop', 'A', false, 'Fragt offene Bestellungen bei Stripe nach (nur lesen).'],
        'wm_zustellen'   => ['Als zugestellt setzen', 'shop', 'A', false, 'Versendet ohne Bestätigung → nach 14 Tagen zugestellt.'],
        // Akquise
        'akquise_signale' => ['Signale und Wiedervorlagen', 'akquise', 'A', false, 'Sieht drei Websites je Lauf nach, fällige Wiedervorlagen als Meldung.'],
        'akquise_postfach' => ['Akquise-Postfach lesen', 'akquise', 'A', false, 'Ordnet Antworten zu — beantwortet nichts.'],
        'akquise_folgen' => ['Akquise-Folgemails', 'akquise', 'A', true, 'Fällige Folgeschritte nach Einwilligung, soweit Schalter, Gate und Grenzen es zulassen.'],
        'akquise_berichte' => ['Berichte und Branchenzahlen', 'akquise', 'A', false, 'Rechnet Berichte und Branchen-Statistik nach.'],
        'akquise_tipp'   => ['Website-Tipp der Woche', 'akquise', 'A', true, 'Dienstags, nur an bestätigte Abos.'],
        'akquise_termine' => ['Termin-Erinnerung', 'akquise', 'A', true, 'Am Vortag eine Erinnerung an den Gebuchten, genau einmal.'],
        'akquise_prio'   => ['Akquise-Priorität rechnen', 'akquise', 'A', false, 'Rechnet die Priorität je Betrieb aus den eigenen Daten nach (300 je Lauf).'],
        'partner_warnungen' => ['Partner-Warnungen 48 h / 72 h', 'partner', 'A', true, 'Heißes Signal beim reservierten Betrieb ohne Reaktion: nach 48 h Hinweis an den Partner, nach 72 h Meldung an Uwe — je Signal einmal.'],
        'akquise_checks' => ['Website-Checks anonymisieren', 'akquise', 'A', false, 'Leert persönliche Felder nach der Frist.'],
        'akquise_woche'  => ['Akquise-Wochenbericht', 'akquise', 'A', false, 'Montags ein Zuruf an dich.'],
        // Marketing & Kanäle
        'marketing_posten' => ['Freigegebene Beiträge posten', 'marketing', 'A', true, 'Postet freigegebene Inhalte zur geplanten Zeit und spiegelt sie in den Telegram-Kanal.'],
        'marketing_schluessel' => ['Schlüssel der Plattformen erneuern', 'marketing', 'A', false, 'Erneuert Zugänge rechtzeitig (TikTok gilt 24 Stunden).'],
        'telegram_werbung' => ['Kanal-Werbung vorbereiten', 'marketing', 'B', false, 'Legt Entwürfe an, die den Telegram-Kanal bekannt machen — Freigabe bei dir.'],
        'marketing_autopilot' => ['Wochen-Autopilot', 'marketing', 'B', false, 'Je eingeschaltetem Land eine Kampagne zur Freigabe (ab Werk aus).'],
        'tiktok_takt'    => ['TikTok-Takt', 'marketing', 'B', false, 'Hält drei Tage Vorrat an Entwürfen (ab Werk aus).'],
        'telegram_plan'  => ['Telegram-Redaktionsplan', 'marketing', 'B', false, 'Einmal die Woche drei Beiträge zur Freigabe.'],
        'marketing_deutsch' => ['Deutsche Fassungen nachholen', 'marketing', 'B', false, 'Auftrag an den PC für fehlende Übersetzungen.'],
        'telegram'       => ['Telegram aufräumen', 'marketing', 'A', false, 'Stille Chats und alte Daten weg, Kanal-Links nachlegen.'],
        'telegram_woche' => ['Telegram-Wochenbericht', 'marketing', 'A', false, 'Montags an dein Telegram.'],
        'verzeichnisse'  => ['Verzeichnisse erinnern', 'marketing', 'A', false, 'Ein Zuruf je Woche für liegende Einträge.'],
        'spur'           => ['Tracking zusammenfassen', 'marketing', 'A', false, 'Einzeldaten nach der Frist zu Tageszahlen.'],
        // System
        'spuerhund'      => ['Umsatz-Spürhund', 'geld', 'A', false, 'Sucht einmal am Tag Chancen: Seite ohne Betreuung oder Hosting, Angebote ohne Antwort, wartende Interessenten. Schreibt niemandem (AI Office Stufe 3).'],
        'morgenbriefing' => ['Morgenbriefing', 'system', 'A', false, 'Um 07:30 an dein Telegram: was wartet, Geld, Technik, Akquise und Termine (AI Office Stufe 2).'],
        'claude_zugang'  => ['Claude-Zugang aufräumen', 'system', 'A', false, 'Löscht abgelaufene Anfragen und Schlüssel, ungenutzte Programmanmeldungen und die Spur nach 180 Tagen.'],
        'meldungen'      => ['Erledigte Meldungen', 'system', 'A', false, 'Meldungen, deren Anlass vorbei ist, gelten als gelesen.'],
        'meldungen_alt'  => ['Alte Meldungen löschen', 'system', 'A', false, 'Gelesene Meldungen nach einem Monat.'],
        'aufgeraeumt'    => ['Alte Prüfungen löschen', 'system', 'A', false, 'Räumt alte Prüfergebnisse weg.'],
        'cockpit'        => ['Cockpit-Schutz', 'system', 'A', false, 'Merkt, ob /cockpit/ geschützt ist.'],
        'zurufe'         => ['Zurufe an dich', 'system', 'A', false, 'Liegengebliebene Zurufe aufs Handy, zweiter Versuch.'],
        'versand'        => ['E-Mail-Versand prüfen', 'system', 'A', false, 'Fragt täglich, ob Brevo noch antwortet.'],
        'zustellbarkeit' => ['SPF, DKIM, DMARC', 'system', 'A', false, 'Prüft täglich die Einträge der Absenderdomain.'],
        'sicherung'      => ['Sicherung der Datenbank', 'system', 'A', false, 'Täglicher Auszug.'],
        'sicherung_aussen' => ['Sicherung außer Haus überwachen', 'system', 'A', false, 'Meldet, wenn Uwes Rechner die Sicherung über 2 Tage nicht abgeholt oder über 9 Tage nicht probeweise eingespielt hat (06.10.2026).'],
    ];

    /** Nur von Hand (Stufe C) — zur Übersicht. Läuft nie im Cron. */
    public const VON_HAND = [
        'Mahnung Stufe 2 und 3' => 'geld', 'Rechnung stellen' => 'geld', 'Provision von Hand auszahlen (SEPA, Verrechnung)' => 'geld',
        'Partner sperren' => 'partner', 'Kundendaten löschen' => 'kunden', 'Dubletten zusammenführen' => 'kunden',
        'Akquise-Stapel senden' => 'akquise', 'Kunden-E-Mail ohne Anlass' => 'kunden',
    ];

    /** Prüfnaht: fn(string $regel, string $text) — ersetzt Events::melden + Zuruf in der Kette. */
    public static $melden = null;

    private static ?array $zustand = null;

    /* ---------------------------------------------------------------- Lesen */

    /* WEGE OHNE MENSCHEN (AI Office Stufe 0, 06.10.2026)
       Der Not-Aus hielt bis heute nur den Cron an. Die Systemanalyse fand
       Wege, die von selbst nach draußen gehen, ohne je durch den Cron zu
       laufen: Stripe-Webhook (Beleg, Auftragsbestätigung, Druckauftrag,
       KAS-Anlage), WhatsApp- und Meta-Webhook (Assistent, Kommentar-
       Direktnachrichten), Telegram-Bot, KI-Telefon. Jeder dieser Eingänge
       meldet sich jetzt als „automatisch“; die Ausgänge (Mail, Telegram,
       WhatsApp, Meta, Druckerei, KAS) fragen ausgangGesperrt(). Ein Klick in
       der Verwaltung ist nie automatisch — Uwe kann während des Not-Aus
       weiter selbst handeln. Nachrichten an Uwe selbst gehen immer. */
    private static bool $automatisch = false;

    /** Am Anfang jedes Eingangs ohne Menschen aufrufen (cron.php, Webhooks, Telefon). */
    public static function automatischAb(string $herkunft = 'automatisch'): void
    {
        self::$automatisch = true;
        self::$herkunft = mb_substr($herkunft, 0, 40);
    }

    private static string $herkunft = '';

    public static function automatisch(): bool { return self::$automatisch; }

    public static function herkunft(): string { return self::$herkunft; }

    /** Prüfnaht für die Kette: zurück in „ein Mensch klickt“. */
    public static function automatischZuruecksetzen(): void { self::$automatisch = false; self::$herkunft = ''; }

    /** Darf ein automatischer Weg jetzt etwas nach draußen schicken? true = nein, Not-Aus. */
    public static function ausgangGesperrt(): bool
    {
        if (!self::$automatisch) { return false; }
        try { return self::notAus(); } catch (Throwable $e) { return false; }
    }

    public static function notAus(): bool
    {
        return (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'auto_notaus'", [], '0') === '1';
    }

    /** @return array{an:bool, am:string, von:string} */
    public static function notAusStand(): array
    {
        $w = static fn(string $k) => (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], '');
        return ['an' => self::notAus(), 'am' => $w('auto_notaus_am'), 'von' => $w('auto_notaus_von')];
    }

    /** Zustand aller Regeln aus der Tabelle, einmal je Anfrage gelesen. */
    private static function zustand(): array
    {
        if (self::$zustand === null) {
            self::$zustand = [];
            try { foreach (Db::all('SELECT * FROM automationen') as $z) { self::$zustand[(string) $z['regel']] = $z; } }
            catch (Throwable $e) { /* vor der Migration: alles an, wie vorher */ }
        }
        return self::$zustand;
    }

    public static function vergessen(): void { self::$zustand = null; }

    /** Darf die Regel jetzt laufen? Unbekannt → nein. */
    public static function darf(string $regel): bool
    {
        return self::grund($regel) === null;
    }

    /** Warum nicht? null = sie darf. */
    public static function grund(string $regel): ?string
    {
        $r = self::REGELN[$regel] ?? null;
        if ($r === null) { return 'Nicht im Automation Center eingetragen.'; }
        if (!empty(self::zustand()[$regel]['aus'])) { return 'Ausgeschaltet.'; }
        if ($r[3] && self::notAus()) { return 'Not-Aus — nichts geht raus.'; }
        return null;
    }

    /** Für die Seite: jede Regel mit Zustand, nach Bereich geordnet. */
    public static function liste(): array
    {
        $z = self::zustand();
        $aus = [];
        foreach (self::REGELN as $k => [$titel, $bereich, $stufe, $raus, $was]) {
            $s = $z[$k] ?? [];
            $aus[$bereich][] = ['regel' => $k, 'titel' => $titel, 'stufe' => $stufe, 'raus' => $raus, 'was' => $was,
                'aus' => !empty($s['aus']), 'grund' => self::grund($k), 'zuletzt_am' => $s['zuletzt_am'] ?? null, 'dauer_ms' => isset($s['dauer_ms']) ? (int) $s['dauer_ms'] : null,
                'ergebnis' => $s['ergebnis'] ?? null, 'fehler' => $s['fehler'] ?? null, 'fehler_folge' => (int) ($s['fehler_folge'] ?? 0),
                'probe' => method_exists(self::class, 'probe_' . $k)];
        }
        return $aus;
    }

    /* ------------------------------------------------------------ Schreiben */

    /** Ein- oder ausschalten (nur Admin — prüft index.php). */
    public static function schalten(string $regel, bool $an, ?int $userId = null): bool
    {
        if (!isset(self::REGELN[$regel])) { return false; }
        Db::run('INSERT INTO automationen (regel, aus, aus_von, aus_am) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE aus = VALUES(aus), aus_von = VALUES(aus_von), aus_am = VALUES(aus_am)',
                [$regel, $an ? 0 : 1, $an ? null : $userId, $an ? null : date('Y-m-d H:i:s')]);
        self::vergessen();
        self::spur($an ? 'automation_an' : 'automation_aus', ['regel' => $regel]);
        return true;
    }

    public static function notAusZiehen(string $wer): void
    {
        foreach (['auto_notaus' => '1', 'auto_notaus_am' => date('Y-m-d H:i:s'), 'auto_notaus_von' => mb_substr($wer, 0, 80)] as $k => $v) {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        }
        self::spur('automation_notaus', ['von' => $wer]);
        try {
            require_once __DIR__ . '/Events.php';
            Events::melden('automation_notaus', 'Not-Aus gezogen — keine Automation schickt etwas raus', 'schlecht',
                'Gezogen von ' . $wer . '. Prüfungen, Zahlungsabgleich und Sicherung laufen weiter. Lösen kann nur der Admin.', '/automationen');
        } catch (Throwable $e) { }
    }

    public static function notAusLoesen(string $wer): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('auto_notaus', '0') ON DUPLICATE KEY UPDATE svalue = '0'");
        self::spur('automation_weiter', ['von' => $wer]);
    }

    /**
     * Nach dem Cron-Lauf: je Regel Dauer, Ergebnis und Fehler festhalten — in EINER Anweisung.
     * Nach FEHLER_MELDEN_AB Fehlschlägen hintereinander einmal melden; ein Erfolg setzt zurück.
     * @param array<string,array{ms:int, ergebnis:string, fehler:?string}> $laeufe
     */
    public static function festhalten(array $laeufe): void
    {
        if (!$laeufe) { return; }
        $vorher = self::zustand();
        $werte = []; $par = [];
        foreach ($laeufe as $regel => $l) {
            if (!isset(self::REGELN[$regel])) { continue; }
            $folge = $l['fehler'] !== null ? (int) ($vorher[$regel]['fehler_folge'] ?? 0) + 1 : 0;
            $werte[] = '(?, NOW(), ?, ?, ?, ?, ' . ($l['fehler'] !== null ? 'NOW()' : 'NULL') . ', 1)';
            array_push($par, $regel, $l['ms'], mb_substr($l['ergebnis'], 0, 250), $l['fehler'] !== null ? mb_substr($l['fehler'], 0, 250) : null, $folge);
            if ($folge === self::FEHLER_MELDEN_AB) { self::fehlerMelden($regel, (string) $l['fehler']); }
        }
        if (!$werte) { return; }
        /* fehler bleibt nach einem Erfolg stehen (mit Datum) — man soll sehen, was zuletzt schiefging. */
        Db::run('INSERT INTO automationen (regel, zuletzt_am, dauer_ms, ergebnis, fehler, fehler_folge, fehler_am, laeufe) VALUES ' . implode(', ', $werte) . '
                 ON DUPLICATE KEY UPDATE zuletzt_am = VALUES(zuletzt_am), dauer_ms = VALUES(dauer_ms), ergebnis = VALUES(ergebnis),
                   fehler = COALESCE(VALUES(fehler), fehler), fehler_folge = VALUES(fehler_folge), fehler_am = COALESCE(VALUES(fehler_am), fehler_am),
                   laeufe = laeufe + 1', $par);
        self::vergessen();
    }

    /** Aus dem Rückgabewert einer Aufgabe: Fehlertext oder null. */
    public static function fehlerAus(mixed $ergebnis): ?string
    {
        if (is_array($ergebnis) && isset($ergebnis['fehler']) && $ergebnis['fehler'] !== '' && $ergebnis['fehler'] !== 0 && $ergebnis['fehler'] !== false) {
            return is_string($ergebnis['fehler']) ? $ergebnis['fehler'] : 'Fehler gemeldet (' . json_encode($ergebnis['fehler']) . ')';
        }
        return null;
    }

    /** Kurzfassung des Ergebnisses für die Liste („3 erinnert · 0 Fehler“). */
    public static function kurz(mixed $ergebnis): string
    {
        if (is_bool($ergebnis)) { return $ergebnis ? 'ja' : 'nein'; }
        if (is_scalar($ergebnis) || $ergebnis === null) { return (string) $ergebnis; }
        $teile = [];
        foreach ((array) $ergebnis as $k => $v) {
            if ($k === 'fehler') { continue; }
            $teile[] = $k . ' ' . (is_scalar($v) ? (is_bool($v) ? ($v ? 'ja' : 'nein') : (string) $v) : (is_array($v) ? '…' : ''));
            if (count($teile) >= 6) { break; }
        }
        return mb_substr(implode(' · ', $teile), 0, 250);
    }

    private static function fehlerMelden(string $regel, string $fehler): void
    {
        $titel = self::REGELN[$regel][0];
        $text = 'Die Automation „' . $titel . '“ ist ' . self::FEHLER_MELDEN_AB . '-mal hintereinander gescheitert: ' . mb_substr($fehler, 0, 200);
        if (self::$melden !== null) { (self::$melden)($regel, $text); return; }
        try {
            require_once __DIR__ . '/Events.php';
            Events::melden('automation_fehler', 'Automation scheitert: ' . $titel, 'schlecht', $text . ' Sie läuft beim nächsten Mal wieder.', '/automationen#' . $regel);
        } catch (Throwable $e) { }
        try {
            require_once __DIR__ . '/Zuruf.php';
            Zuruf::vormerken('automation_fehler_' . $regel, 'Vecom: Automation „' . $titel . '“ scheitert seit ' . self::FEHLER_MELDEN_AB . ' Läufen.', 720);
        } catch (Throwable $e) { }
    }

    private static function spur(string $tat, array $nachher): void
    {
        try { require_once __DIR__ . '/Events.php'; Events::pruefspur($tat, 'automationen', null, [], $nachher); } catch (Throwable $e) { }
    }

    /* ----------------------------------------------------------- Probelauf
       Zeigt, wen die Regel JETZT träfe. Nur SELECT — kein Senden, kein Ändern,
       kein Aufruf nach draußen. Die Abfragen spiegeln die Auswahl der Regel;
       die Kette vergleicht beide an Beispieldaten, damit sie nicht auseinanderlaufen.
       @return array{zeilen:list<array{wer:string, was:string}>, hinweis?:string} */

    public static function probe(string $regel): ?array
    {
        $m = 'probe_' . $regel;
        if (!isset(self::REGELN[$regel]) || !method_exists(self::class, $m)) { return null; }
        $r = self::$m();
        $g = self::grund($regel);
        if ($g !== null) { $r['hinweis'] = trim(($r['hinweis'] ?? '') . ' Im Moment läuft sie nicht: ' . $g); }
        return $r;
    }

    private static function probe_erinnerungen(): array
    {
        require_once __DIR__ . '/Onboarding.php';
        $erste = date('Y-m-d H:i:s', strtotime('-' . Onboarding::ERINNERUNG_NACH_TAGEN . ' days'));
        $zweite = date('Y-m-d H:i:s', strtotime('-' . Onboarding::ZWEITE_NACH_TAGEN . ' days'));
        $z = Db::all("SELECT c.name, q.erinnert_am FROM questionnaires q JOIN customers c ON c.id = q.customer_id
                       WHERE q.status = 'offen' AND (q.eingeladen_am IS NOT NULL OR (q.data IS NOT NULL AND q.data <> '')) AND c.anonym_am IS NULL
                         AND ((q.erinnert_am IS NULL AND q.updated_at <= ?)
                           OR (q.erinnert_am IS NOT NULL AND q.erinnert2_am IS NULL AND q.erinnert_am <= ? AND q.updated_at <= DATE_ADD(q.erinnert_am, INTERVAL 5 SECOND)))
                       LIMIT 25", [$erste, $zweite]);
        return ['zeilen' => array_map(static fn($r) => ['wer' => (string) $r['name'], 'was' => ($r['erinnert_am'] === null ? '1.' : '2.') . ' Erinnerung an den Fragebogen'], $z)];
    }

    private static function probe_mahnungen(): array
    {
        require_once __DIR__ . '/Mahnung.php';
        require_once __DIR__ . '/Fmt.php';
        return ['zeilen' => array_map(static fn($z) => ['wer' => (string) $z['kunde'],
            'was' => 'Zahlungserinnerung ' . $z['order_no'] . ' · ' . Fmt::geld((int) $z['amount_cents']) . ' · fällig seit ' . Fmt::datum((string) $z['faellig_am'])], Mahnung::faellige(1))];
    }

    private static function probe_abbuchungen(): array
    {
        require_once __DIR__ . '/Fmt.php';
        $z = Db::all("SELECT c.name, z.amount_cents, z.faellig_am FROM payments z JOIN abos a ON a.id = z.abo_id JOIN customers c ON c.id = a.customer_id
                       WHERE z.method = 'abbuchung' AND z.status = 'ausstehend' AND z.faellig_am IS NOT NULL AND z.faellig_am <= CURDATE()
                         AND a.zahlmittel_id IS NOT NULL AND c.stripe_kunde IS NOT NULL ORDER BY z.id LIMIT 20");
        return ['zeilen' => array_map(static fn($r) => ['wer' => (string) $r['name'], 'was' => 'Abbuchung ' . Fmt::geld((int) $r['amount_cents']) . ' (fällig ' . Fmt::datum((string) $r['faellig_am']) . ')'], $z)];
    }

    private static function probe_akquise_termine(): array
    {
        $j = time();
        $z = Db::all("SELECT name, beginn FROM akq_termine WHERE status = 'gebucht' AND erinnert_am IS NULL AND beginn BETWEEN ? AND ? AND email IS NOT NULL AND email <> ''",
                     [date('Y-m-d H:i:s', $j + 12 * 3600), date('Y-m-d H:i:s', $j + 30 * 3600)]);
        return ['zeilen' => array_map(static fn($r) => ['wer' => (string) $r['name'], 'was' => 'Erinnerung an den Termin am ' . date('d.m. H:i', strtotime((string) $r['beginn']))], $z)];
    }

    private static function probe_akquise_folgen(): array
    {
        foreach (['Akquise', 'AkquiseScore', 'AkquiseGate', 'AkquiseFolge'] as $k) { require_once __DIR__ . "/$k.php"; }
        $hinweis = !AkquiseGate::schalter('folge') ? 'Der Schalter der Folge-Mails in der Akquise ist aus.' : (AkquiseGate::grenzen()['stop'] ? 'Die Notbremse der Akquise ist gezogen.' : '');
        $z = Db::all("SELECT f.name, fo.schritt FROM akq_folgen fo JOIN akq_firmen f ON f.id = fo.firma_id
                       WHERE fo.status = 'laeuft' AND fo.naechst_am <= ? ORDER BY fo.naechst_am LIMIT " . (AkquiseFolge::JE_LAUF), [date('Y-m-d H:i:s')]);
        return ['zeilen' => array_map(static fn($r) => ['wer' => (string) $r['name'], 'was' => 'Folge-Mail Schritt ' . ((int) $r['schritt'] + 1) . ' (wenn kein Hindernis)'], $z)] + ($hinweis !== '' ? ['hinweis' => $hinweis] : []);
    }

    private static function probe_marketing_posten(): array
    {
        require_once __DIR__ . '/MkVeroeffentlichen.php';
        $z = Db::all("SELECT id, plattform, titel, geplant_am FROM mk_inhalte WHERE status = 'freigegeben' AND geplant_am IS NOT NULL AND geplant_am <= NOW() ORDER BY geplant_am LIMIT " . MkVeroeffentlichen::JE_LAUF);
        return ['zeilen' => array_map(static fn($r) => ['wer' => (string) $r['plattform'], 'was' => '„' . mb_substr((string) ($r['titel'] ?? ''), 0, 60) . '“ (geplant ' . date('d.m. H:i', strtotime((string) $r['geplant_am'])) . ')'], $z)];
    }

    private static function probe_partner_auszahlung(): array
    {
        require_once __DIR__ . '/Partner.php';
        require_once __DIR__ . '/PartnerWege.php';
        require_once __DIR__ . '/Fmt.php';
        if (Partner::einstellung('partner_auto_auszahlen') !== '1') { return ['zeilen' => [], 'hinweis' => 'Die automatische Auszahlung ist in den Partner-Einstellungen aus.']; }
        $limit = Partner::zahl('partner_auto_tageslimit_cents');
        $heute = (int) Db::wert("SELECT COALESCE(SUM(betrag_cents),0) FROM partner_auszahlungen WHERE automatisch = 1 AND status <> 'abgebrochen' AND created_at >= CURDATE()", [], 0);
        $zeilen = [];
        foreach (Db::all("SELECT * FROM partner WHERE status = 'aktiv' AND vereinbarung_am IS NOT NULL") as $p) {
            $weg = PartnerWege::weg($p);
            if (!in_array($weg, PartnerWege::AUTOMATISCH, true) || !PartnerWege::bereit($p, $weg)) { continue; }
            $offen = Partner::auszahlbar((int) $p['id']);
            if ($offen < Partner::zahl('partner_mindest_cents')) { continue; }
            $passt = $heute + $offen <= $limit;
            if ($passt) { $heute += $offen; }
            $zeilen[] = ['wer' => (string) $p['name'] . ' (' . $p['code'] . ')', 'was' => Fmt::geld($offen) . ' über ' . PartnerWege::WEGE[$weg] . ($passt ? '' : ' — wartet, Tageslimit erreicht')];
        }
        return ['zeilen' => $zeilen, 'hinweis' => 'Tageslimit ' . Fmt::geld($limit) . '.'];
    }

    private static function probe_hosting(): array
    {
        require_once __DIR__ . '/Hosting.php';
        $z = Db::all("SELECT DISTINCT h.id, c.name FROM hosting_auftraege h JOIN hosting_schritte s ON s.auftrag_id = h.id LEFT JOIN customers c ON c.id = h.customer_id
                       WHERE h.status = 'in_arbeit' AND s.status IN ('fehler', 'offen', 'laeuft') AND s.versuche < ?
                         AND s.updated_at < NOW() - INTERVAL " . (int) Hosting::PAUSE_MINUTEN . ' MINUTE LIMIT 5', [Hosting::VERSUCHE]);
        return ['zeilen' => array_map(static fn($r) => ['wer' => (string) ($r['name'] ?? ('Auftrag ' . $r['id'])), 'was' => 'Hosting-Auftrag ' . $r['id'] . ' wird fortgesetzt'], $z)];
    }
}
