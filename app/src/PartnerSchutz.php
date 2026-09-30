<?php
declare(strict_types=1);

require_once __DIR__ . '/Partner.php';

/**
 * Schutz der Vecom-Unterlagen im Partnerbereich (30.09.2026).
 *
 * Uwe: „recherchiere, dass Partner die Betriebe und alles nicht für eigene
 * Zwecke verwenden dürfen, auch unser Logo nicht ändern … alle Partner-
 * Dashboards sollen direkt gesperrt werden und erst mit Zustimmung aktiviert
 * werden … in der Verwaltung hinterlegt und ein Schriftstück für den Anwalt.“
 * Dazu: „statt Unterschrift ein Haken zum Anklicken“.
 *
 * DIE SPERRE: Der Partnerbereich öffnet sich erst, wenn der Partner die
 * aktuelle Fassung mit ZWEI Haken angenommen hat (ganzer Text + ausdrücklich
 * die belastenden Klauseln, Art. 1341/1342 c.c.) UND Uwe ihn einmal
 * freigeschaltet hat. Spätere Fassungen: erneut zustimmen, Uwes Freigabe
 * bleibt. Gesperrt (Verstoß) = zu, bis Uwe wieder freischaltet.
 *
 * BEKANNTE GRENZE (Cass. ord. 20945/2026): Für die belastenden Klauseln
 * reicht nach der Cassazione ein Haken allein nicht, es bräuchte eine
 * einfache elektronische Unterschrift (z. B. Code per E-Mail). Uwe hat sich
 * bewusst für den Haken entschieden. Vertraulichkeit, Eigentum, Logo und
 * Datenschutz tragen auch so; Vertragsstrafe, Kundenschutz und Gerichtsstand
 * sind damit angreifbar. Steht so auch im Merkblatt für den Anwalt.
 *
 * BEWEISE: Zugriffsprotokoll, erfasste Verstöße, Kontrolleinträge („Fallen“)
 * mit eigener E-Mail-Adresse und eine Kennung in jedem Werbemittel-PDF.
 * aktePdf() fasst alles für den Anwalt zusammen.
 */
final class PartnerSchutz
{
    public const PENALE_STANDARD_CENTS = 250000;
    public const KUNDENSCHUTZ_STANDARD_MONATE = 24;
    public const RESERVIERUNGEN_JE_TAG = 15;
    public const FALLEN_JE_PARTNER = 40;

    /** Der zweite Haken: die belastenden Klauseln ausdrücklich (Art. 1341/1342 c.c.). */
    public const KLAUSELN = [
        'it' => 'Ai sensi degli artt. 1341 e 1342 c.c. approvo specificamente i punti 9 (riservatezza, anche dopo la fine), 10 (tutela della clientela per {schutz} mesi), 13 (penale di {penale} per violazione), 14 (risoluzione immediata e decadenza delle provvigioni legate alla violazione) e 15 (modifiche e foro di Agrigento).',
        'de' => 'Gemäß Art. 1341 und 1342 c.c. stimme ich ausdrücklich den Punkten 9 (Vertraulichkeit, auch nach dem Ende), 10 (Kundenschutz für {schutz} Monate), 13 (Vertragsstrafe von {penale} je Verstoß), 14 (sofortige Auflösung und Wegfall der Provisionen aus dem Verstoß) und 15 (Änderungen und Gerichtsstand Agrigento) zu.',
        'en' => 'Pursuant to Arts. 1341 and 1342 of the Italian Civil Code I specifically approve sections 9 (confidentiality, also after termination), 10 (customer protection for {schutz} months), 13 (penalty of {penale} per breach), 14 (immediate termination and loss of commission connected with the breach) and 15 (changes and venue of Agrigento).',
    ];

    public const VERSTOSS_ARTEN = [
        'daten' => 'Betriebsdaten weitergegeben oder selbst genutzt',
        'kundenschutz' => 'Betrieb/Kunde selbst betreut oder abgeworben',
        'logo' => 'Logo/Marke verändert oder missbraucht',
        'datenschutz' => 'Personenbezogene Daten zweckentfremdet',
        'werbung' => 'Unerlaubte Werbung (Spam, ohne Einwilligung)',
        'sonstiges' => 'Sonstiges',
    ];

    /* ================================================================== */
    /*  Zustand                                                           */
    /* ================================================================== */

    /** @return string frei | zustimmen | wartet | gesperrt */
    public static function stand(array $p): string
    {
        if (!empty($p['gesperrt_am'])) { return 'gesperrt'; }
        if ((string) ($p['vereinbarung_version'] ?? '') !== Partner::VEREINBARUNG_VERSION || empty($p['vereinbarung_klauseln_am'])) { return 'zustimmen'; }
        if (empty($p['freigeschaltet_am'])) { return 'wartet'; }
        return 'frei';
    }

    public static function freigeschaltet(?array $p): bool { return $p !== null && self::stand($p) === 'frei'; }

    /** SQL-Bedingung „Partnerbereich offen“ für Läufe, die Partnern etwas schicken. */
    public static function sqlFrei(string $a = 'p'): string
    {
        $v = str_replace("'", '', Partner::VEREINBARUNG_VERSION);
        return "($a.freigeschaltet_am IS NOT NULL AND $a.gesperrt_am IS NULL AND $a.vereinbarung_klauseln_am IS NOT NULL AND $a.vereinbarung_version = '$v')";
    }

    /** Die Zahlen, die in der Vereinbarung stehen. */
    public static function werte(string $sprache = 'de'): array
    {
        require_once __DIR__ . '/Fmt.php';
        $penale = Partner::einstellung('partner_penale_cents');
        $schutz = Partner::einstellung('partner_kundenschutz_monate');
        return [
            '{penale}' => Fmt::geld($penale !== '' ? (int) $penale : self::PENALE_STANDARD_CENTS),
            '{schutz}' => (string) ($schutz !== '' ? max(1, min(60, (int) $schutz)) : self::KUNDENSCHUTZ_STANDARD_MONATE),
        ];
    }

    public static function klauselText(string $sprache): string
    {
        return strtr(self::KLAUSELN[$sprache] ?? self::KLAUSELN['it'], self::werte($sprache));
    }

    /** Der volle Wortlaut, dem zugestimmt wird: Vereinbarung + zweiter Haken. */
    public static function wortlaut(string $sprache, array $p): string
    {
        return Partner::vereinbarungText($sprache, $p) . "\n\n[X] " . self::klauselText($sprache);
    }

    /* ================================================================== */
    /*  Zustimmen, freischalten, sperren                                  */
    /* ================================================================== */

    /** @return string ok | haken */
    public static function zustimmen(array $p, string $sprache, bool $ganz, bool $klauseln, string $ip = '', bool $melden = true): string
    {
        if (!$ganz || !$klauseln) { return 'haken'; }
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        $text = self::wortlaut($sprache, $p);
        $id = (int) $p['id'];
        $warFrei = !empty($p['freigeschaltet_am']);
        Partner::vereinbarungMerken($id, $text);
        Db::run('UPDATE partner SET vereinbarung_klauseln_am = NOW(), vereinbarung_hash = ?, vereinbarung_ip_hash = ?, vereinbarung_sprache = ? WHERE id = ?',
            [hash('sha256', $text), $ip !== '' ? hash('sha256', $ip . '|' . Config::get('app_geheim', 'vecom')) : null, $sprache, $id]);
        Events::pruefspur('partner_zustimmung', 'partner', $id, [], ['fassung' => Partner::VEREINBARUNG_VERSION, 'hash' => hash('sha256', $text)]);
        if ($melden && !$warFrei && empty($p['gesperrt_am'])) {
            try {
                Events::melden('partner_freigabe', 'Partner wartet auf Freischaltung: ' . $p['name'], 'hinweis',
                    'Hat der Partnervereinbarung (Fassung ' . Partner::VEREINBARUNG_VERSION . ') mit beiden Haken zugestimmt.', 'partner/' . $id);
            } catch (Throwable $e) { }
            try {
                require_once __DIR__ . '/Zuruf.php';
                Zuruf::vormerken('partner_freigabe', 'Partner: Ein Partner hat der Vereinbarung zugestimmt und wartet auf die Freischaltung in der Verwaltung.', 5);
            } catch (Throwable $e) { }
        }
        return 'ok';
    }

    /** @return string ok | fehlt (noch keine Zustimmung zur aktuellen Fassung) | kein_partner */
    public static function freischalten(int $id, string $wer, ?callable $senden = null): string
    {
        $p = Partner::laden($id);
        if (!$p) { return 'kein_partner'; }
        if ((string) $p['vereinbarung_version'] !== Partner::VEREINBARUNG_VERSION || empty($p['vereinbarung_klauseln_am'])) { return 'fehlt'; }
        $warGesperrt = !empty($p['gesperrt_am']);
        Db::run('UPDATE partner SET freigeschaltet_am = NOW(), freigeschaltet_von = ?, gesperrt_am = NULL, gesperrt_grund = NULL WHERE id = ?', [mb_substr($wer, 0, 80), $id]);
        Events::pruefspur('partner_freigeschaltet', 'partner', $id, ['gesperrt' => $warGesperrt ? 1 : 0], ['freigeschaltet' => 1]);
        Partner::schreiben($id, 'partner_freigeschaltet', [], $senden);
        return 'ok';
    }

    public static function sperren(int $id, string $grund, string $wer): bool
    {
        $p = Partner::laden($id);
        if (!$p) { return false; }
        $grund = mb_substr(trim($grund) !== '' ? trim($grund) : 'Gesperrt von ' . $wer, 0, 255);
        Db::run('UPDATE partner SET gesperrt_am = NOW(), gesperrt_grund = ? WHERE id = ?', [$grund, $id]);
        Events::pruefspur('partner_gesperrt', 'partner', $id, [], ['grund' => $grund, 'von' => $wer]);
        return true;
    }

    /**
     * Einmal je Partner, der die aktuelle Fassung noch nicht angenommen hat:
     * Hinweis, dass der Bereich bis zur Zustimmung gesperrt ist. Eine
     * Vertragsmitteilung, keine Werbung (Uwe: Ja, 30.09.2026).
     */
    public static function hinweiseVersenden(?callable $senden = null, int $hoechstens = 40): int
    {
        $n = 0;
        foreach (Db::all("SELECT id FROM partner WHERE status IN ('aktiv','pausiert') AND email <> ''
                             AND (vereinbarung_version IS NULL OR vereinbarung_version <> ? OR vereinbarung_klauseln_am IS NULL)
                             AND (neufassung_hinweis_am IS NULL OR neufassung_hinweis_am < DATE_SUB(NOW(), INTERVAL 60 DAY))
                           ORDER BY id LIMIT " . max(1, $hoechstens), [Partner::VEREINBARUNG_VERSION]) as $z) {
            Db::run('UPDATE partner SET neufassung_hinweis_am = NOW() WHERE id = ?', [(int) $z['id']]);
            if (Partner::schreiben((int) $z['id'], 'partner_neufassung', [], $senden)) { $n++; }
        }
        return $n;
    }

    /* ================================================================== */
    /*  Zugriffsprotokoll                                                 */
    /* ================================================================== */

    public static function protokoll(int $partnerId, string $art, ?int $firmaId = null, string $info = ''): void
    {
        try {
            if ($art === 'seite') {   // Seitenaufrufe höchstens alle 10 Minuten festhalten
                $zuletzt = Db::wert("SELECT MAX(created_at) FROM partner_zugriffe WHERE partner_id = ? AND art = 'seite'", [$partnerId], null);
                if ($zuletzt !== null && strtotime((string) $zuletzt) > time() - 600) { return; }
            }
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            Db::run('INSERT INTO partner_zugriffe (partner_id, art, firma_id, info, ip_hash) VALUES (?, ?, ?, ?, ?)', [
                $partnerId, mb_substr($art, 0, 30), $firmaId, mb_substr($info, 0, 255),
                $ip !== '' ? substr(hash('sha256', $ip . '|' . Config::get('app_geheim', 'vecom')), 0, 16) : '',
            ]);
        } catch (Throwable $e) { /* Das Protokoll darf nie den Partnerbereich aufhalten */ }
    }

    /** @return list<array<string,mixed>> */
    public static function zugriffe(int $partnerId, int $anzahl = 60): array
    {
        return Db::all('SELECT z.*, f.name AS firma, f.stadt FROM partner_zugriffe z LEFT JOIN akq_firmen f ON f.id = z.firma_id
                         WHERE z.partner_id = ? ORDER BY z.id DESC LIMIT ' . max(1, min(2000, $anzahl)), [$partnerId]);
    }

    /** @return array<string,int> Zugriffe je Art in den letzten 30 Tagen. */
    public static function zugriffZahlen(int $partnerId): array
    {
        $aus = [];
        foreach (Db::all('SELECT art, COUNT(*) AS n FROM partner_zugriffe WHERE partner_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY art', [$partnerId]) as $z) {
            $aus[(string) $z['art']] = (int) $z['n'];
        }
        return $aus;
    }

    /* ================================================================== */
    /*  Verstöße                                                          */
    /* ================================================================== */

    /** @return ?string Fehler */
    public static function verstossErfassen(int $partnerId, array $d, string $wer): ?string
    {
        $art = (string) ($d['art'] ?? '');
        if (!isset(self::VERSTOSS_ARTEN[$art])) { return 'Bitte die Art des Verstoßes wählen.'; }
        $text = trim((string) ($d['beschreibung'] ?? ''));
        if (mb_strlen($text) < 10) { return 'Bitte kurz beschreiben, was passiert ist (mindestens ein Satz).'; }
        $datum = (string) ($d['festgestellt_am'] ?? '');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $datum) || strtotime($datum) === false || strtotime($datum) > time() + 86400) { $datum = date('Y-m-d'); }
        $id = Db::insert('partner_verstoesse', [
            'partner_id' => $partnerId, 'art' => $art, 'festgestellt_am' => $datum,
            'beschreibung' => mb_substr($text, 0, 8000), 'beleg' => mb_substr(trim((string) ($d['beleg'] ?? '')), 0, 8000) ?: null,
            'erfasst_von' => mb_substr($wer, 0, 80),
        ]);
        Events::pruefspur('partner_verstoss', 'partner', $partnerId, [], ['verstoss_id' => $id, 'art' => $art]);
        return null;
    }

    /** @return list<array<string,mixed>> */
    public static function verstoesse(int $partnerId): array
    {
        return Db::all('SELECT * FROM partner_verstoesse WHERE partner_id = ? ORDER BY festgestellt_am DESC, id DESC', [$partnerId]);
    }

    /* ================================================================== */
    /*  Kontrolleinträge (Fallen)                                         */
    /* ================================================================== */

    private const FALLE_VORSILBE = [
        'it' => ['restaurant' => 'Trattoria', 'hotel' => 'Hotel', 'ferienwohnung' => 'B&B', 'agriturismo' => 'Agriturismo', 'bar_cafe' => 'Bar',
                 'baeckerei' => 'Panificio', 'handwerk' => 'Falegnameria', 'bau' => 'Edilizia', 'immobilien' => 'Immobiliare', 'autohaus' => 'Auto',
                 'werkstatt' => 'Officina', 'friseur' => 'Parrucchiere', 'beauty' => 'Centro Estetico', 'fitness' => 'Palestra', 'tourismus' => 'Viaggi',
                 'einzelhandel' => 'Bottega', 'produzent' => 'Cantina', 'industrie' => 'Lavorazioni', 'kanzlei' => 'Studio', 'beratung' => 'Consulenze',
                 'medizin' => 'Studio Medico', 'dienstleister' => 'Servizi'],
        'de' => ['restaurant' => 'Gasthaus', 'hotel' => 'Hotel', 'ferienwohnung' => 'Ferienwohnung', 'agriturismo' => 'Hofladen', 'bar_cafe' => 'Café',
                 'baeckerei' => 'Bäckerei', 'handwerk' => 'Schreinerei', 'bau' => 'Bau', 'immobilien' => 'Immobilien', 'autohaus' => 'Autohaus',
                 'werkstatt' => 'Kfz-Werkstatt', 'friseur' => 'Friseur', 'beauty' => 'Kosmetik', 'fitness' => 'Fitness', 'tourismus' => 'Reisen',
                 'einzelhandel' => 'Laden', 'produzent' => 'Weingut', 'industrie' => 'Fertigung', 'kanzlei' => 'Kanzlei', 'beratung' => 'Beratung',
                 'medizin' => 'Praxis', 'dienstleister' => 'Service'],
    ];
    private const FALLE_NAMEN = [
        'it' => ['Lombardo', 'Greco', 'Russo', 'Messina', 'Caruso', 'Amato', 'Marino', 'Rizzo', 'Ferrara', 'Costa', 'Gallo', 'Leone', 'Bruno',
                 'Mancuso', 'Vitale', 'Sorrentino', 'Fontana', 'Parisi', 'Morreale', 'Cusumano', 'Randazzo', 'Sciortino', 'Lo Presti', 'Giordano'],
        'de' => ['Becker', 'Hoffmann', 'Schäfer', 'Koch', 'Richter', 'Klein', 'Wolf', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger',
                 'Hartmann', 'Lange', 'Werner', 'Krause', 'Lehmann', 'Köhler', 'Maier', 'Fuchs', 'Vogel', 'Busch', 'Kühn', 'Sommer'],
    ];

    /** Die Domain der Kontrolladressen. Leer = keine Kontrolleinträge (braucht eine Sammeladresse, die ins Akquise-Postfach läuft). */
    public static function fallenDomain(): string
    {
        $d = mb_strtolower(trim(Partner::einstellung('partner_fallen_domain')));
        return preg_match('~^(?=.{4,190}$)([a-z0-9-]+\.)+[a-z]{2,}$~', $d) ? $d : '';
    }

    private static function ortSchluessel(string $ort): string
    {
        require_once __DIR__ . '/Akquise.php';
        return mb_substr(trim(preg_replace('~[^a-z0-9]+~', ' ', mb_strtolower(Akquise::ohneAkzente($ort))) ?? ''), 0, 80);
    }

    /** Der Kontrolleintrag dieses Partners für Ort und Branche — legt ihn beim ersten Mal an. */
    public static function falleFuer(int $partnerId, string $ort, string $branche, string $land = 'IT'): ?array
    {
        $domain = self::fallenDomain();
        $schluessel = self::ortSchluessel($ort);
        if ($domain === '' || mb_strlen($schluessel) < 2) { return null; }
        require_once __DIR__ . '/Akquise.php';
        $sp = strtoupper($land) === 'DE' ? 'de' : 'it';
        $h = hexdec(substr(hash('sha256', $partnerId . '|' . $schluessel . '|' . $branche . '|' . Config::get('app_geheim', 'vecom')), 0, 8));
        if ($branche === '' || !isset(self::FALLE_VORSILBE[$sp][$branche])) {
            $keys = array_keys(self::FALLE_VORSILBE[$sp]);
            $bKey = $keys[$h % count($keys)];
        } else { $bKey = $branche; }
        $f = Db::one('SELECT * FROM partner_fallen WHERE partner_id = ? AND ort_schluessel = ? AND branche = ?', [$partnerId, $schluessel, $bKey]);
        if ($f) { return $f; }
        if ((int) Db::wert('SELECT COUNT(*) FROM partner_fallen WHERE partner_id = ?', [$partnerId], 0) >= self::FALLEN_JE_PARTNER) { return null; }
        $nachname = self::FALLE_NAMEN[$sp][intdiv($h, 7) % count(self::FALLE_NAMEN[$sp])];
        $name = self::FALLE_VORSILBE[$sp][$bKey] . ' ' . $nachname;
        $ortSchoen = mb_convert_case(mb_substr(trim($ort), 0, 80), MB_CASE_TITLE, 'UTF-8');
        for ($i = 0; $i < 5; $i++) {
            $lokal = preg_replace('~[^a-z0-9]+~', '', mb_strtolower(Akquise::ohneAkzente($name))) . random_int(10, 99);
            try {
                $neu = Db::insert('partner_fallen', ['partner_id' => $partnerId, 'name' => $name, 'branche' => $bKey, 'ort' => $ortSchoen,
                    'ort_schluessel' => $schluessel, 'land' => $sp === 'de' ? 'DE' : 'IT', 'email' => $lokal . '@' . $domain]);
                return Db::one('SELECT * FROM partner_fallen WHERE id = ?', [$neu]);
            } catch (Throwable $e) {
                $f = Db::one('SELECT * FROM partner_fallen WHERE partner_id = ? AND ort_schluessel = ? AND branche = ?', [$partnerId, $schluessel, $bKey]);
                if ($f) { return $f; }
            }
        }
        return null;
    }

    /** Als Treffer im Firmen-Finder, im selben Format wie PartnerRecherche::suchen(). */
    public static function falleAlsTreffer(array $f, int $partnerId, string $sprache): array
    {
        require_once __DIR__ . '/Akquise.php';
        $meine = $f['reserviert_bis'] !== null && strtotime((string) $f['reserviert_bis']) >= strtotime('today');
        return ['id' => -(int) $f['id'], 'name' => (string) $f['name'], 'ort' => (string) $f['ort'], 'adresse' => '',
                'branche' => Akquise::branchenName((string) $f['branche'], $sprache), 'chance' => 'hoch', 'domain' => '',
                'stand' => $meine ? 'meine' : 'frei', 'bis' => $meine ? $f['reserviert_bis'] : null];
    }

    /** @return string ok | fi_weg */
    public static function falleReservieren(int $partnerId, int $falleId, int $tage): string
    {
        if (!Db::one('SELECT id FROM partner_fallen WHERE id = ? AND partner_id = ?', [$falleId, $partnerId])) { return 'fi_weg'; }
        Db::run('UPDATE partner_fallen SET reserviert_bis = DATE_ADD(CURDATE(), INTERVAL ' . max(1, $tage) . ' DAY) WHERE id = ? AND partner_id = ?', [$falleId, $partnerId]);
        return 'ok';
    }

    public static function falleFreigeben(int $partnerId, int $falleId): void
    {
        Db::run('UPDATE partner_fallen SET reserviert_bis = NULL WHERE id = ? AND partner_id = ?', [$falleId, $partnerId]);
    }

    /** Reservierte Kontrolleinträge im Format von PartnerRecherche::meine(). */
    public static function fallenMeine(int $partnerId, string $sprache): array
    {
        require_once __DIR__ . '/Akquise.php';
        $aus = [];
        foreach (Db::all('SELECT * FROM partner_fallen WHERE partner_id = ? AND reserviert_bis >= CURDATE() ORDER BY reserviert_bis', [$partnerId]) as $f) {
            $aus[] = ['id' => -(int) $f['id'], 'name' => (string) $f['name'], 'ort' => (string) $f['ort'], 'adresse' => '',
                      'branche' => Akquise::branchenName((string) $f['branche'], $sprache), 'chance' => 'hoch', 'bis' => (string) $f['reserviert_bis'],
                      'domain' => '', 'telefon' => '', 'email' => (string) $f['email'], 'url' => '',
                      'land' => (string) ($f['land'] ?: 'IT'),
                      'stadt' => (string) $f['ort'], 'plz' => '', 'branche_key' => (string) $f['branche']];
        }
        return $aus;
    }

    /**
     * Aus dem Akquise-Postfach: Ging eine Mail an eine Kontrolladresse?
     * Dann ist die Liste dieses Partners bei jemandem gelandet (oder er
     * schreibt selbst — das zeigt der Absender). Genau eine Meldung je Mail.
     */
    public static function falleTreffer(array $m, string $nachrichtId): bool
    {
        $domain = self::fallenDomain();
        $an = mb_strtolower((string) ($m['an'] ?? ''));
        if ($domain === '' || !str_contains($an, '@' . $domain)) { return false; }
        preg_match_all('~[a-z0-9._%+-]+@' . preg_quote($domain, '~') . '~', $an, $t);
        foreach (array_unique($t[0]) as $adr) {
            $f = Db::one('SELECT f.*, p.name AS partner, p.email AS partner_email FROM partner_fallen f JOIN partner p ON p.id = f.partner_id WHERE f.email = ?', [$adr]);
            if (!$f) { continue; }
            if ((int) Db::wert('SELECT COUNT(*) FROM partner_fallen_treffer WHERE nachricht_id = ?', [$nachrichtId], 0) > 0) { return true; }
            Db::insert('partner_fallen_treffer', [
                'falle_id' => (int) $f['id'], 'partner_id' => (int) $f['partner_id'], 'von' => mb_substr((string) $m['von'], 0, 190),
                'betreff' => mb_substr((string) $m['betreff'], 0, 255), 'auszug' => mb_substr((string) ($m['text'] ?? ''), 0, 4000),
                'nachricht_id' => mb_substr($nachrichtId, 0, 190), 'eingang_am' => $m['datum'] ?? null,
            ]);
            Db::run('UPDATE partner_fallen SET treffer = treffer + 1, letzter_treffer = NOW() WHERE id = ?', [(int) $f['id']]);
            $selbst = mb_strtolower((string) ($m['von_adresse'] ?? '')) === mb_strtolower((string) $f['partner_email']);
            try {
                Events::melden('partner_falle', ($selbst ? 'Kontrolleintrag vom Partner selbst angeschrieben: ' : 'Kontrolleintrag angeschrieben — Liste weitergegeben? ') . $f['partner'],
                    $selbst ? 'hinweis' : 'schlecht', 'An ' . $adr . ' (' . $f['name'] . ', ' . $f['ort'] . ') von ' . mb_substr((string) $m['von'], 0, 120)
                    . ' — Betreff: ' . mb_substr((string) $m['betreff'], 0, 150), 'partner/' . (int) $f['partner_id'] . '#schutz');
            } catch (Throwable $e) { }
            if (!$selbst) {
                try {
                    require_once __DIR__ . '/Zuruf.php';
                    Zuruf::vormerken('partner_falle', 'Partner: Ein Kontrolleintrag wurde von fremder Seite angeschrieben. Einzelheiten in der Verwaltung beim Partner.', 30);
                } catch (Throwable $e) { }
            }
            return true;
        }
        return false;
    }

    /** @return list<array<string,mixed>> */
    public static function fallenTreffer(int $partnerId): array
    {
        return Db::all('SELECT t.*, f.name, f.ort, f.email FROM partner_fallen_treffer t JOIN partner_fallen f ON f.id = t.falle_id
                         WHERE t.partner_id = ? ORDER BY t.id DESC', [$partnerId]);
    }

    /* ================================================================== */
    /*  Kennung in Werbemitteln                                           */
    /* ================================================================== */

    /** Kurze, nicht erratbare Kennung je Partner — steht unsichtbar in jedem PDF aus seinem Bereich. */
    public static function kennung(array $p): string
    {
        return 'VDP' . (int) $p['id'] . '-' . strtoupper(substr(hash('sha256', (int) $p['id'] . '|' . Config::get('app_geheim', 'vecom') . '|kennung'), 0, 6));
    }

    /** Welcher Partner steckt hinter einer Kennung aus einem gefundenen PDF? */
    public static function ausKennung(string $k): ?array
    {
        if (!preg_match('~VDP(\d+)-([0-9A-F]{6})~i', $k, $m)) { return null; }
        $p = Partner::laden((int) $m[1]);
        return $p && hash_equals(self::kennung($p), 'VDP' . (int) $m[1] . '-' . strtoupper($m[2])) ? $p : null;
    }

    /* ================================================================== */
    /*  Akte für den Anwalt                                               */
    /* ================================================================== */

    public const AKTE = [
        'it' => [
            'titel' => 'Fascicolo per l’avvocato', 'sub' => 'Partner e possibile violazione dell’accordo partner',
            'parti' => 'PARTI', 'titolare' => 'Titolare', 'partner' => 'Partner', 'accordo' => 'ACCORDO ACCETTATO',
            'versione' => 'Versione', 'accettato' => 'Accettato il', 'clausole' => 'Clausole approvate (2ª spunta)',
            'modo' => 'Modalità', 'modo_t' => 'Due caselle di spunta nell’area partner (nessuna firma elettronica, scelta del titolare).',
            'impronta' => 'Impronta SHA-256 del testo', 'attivato' => 'Area attivata il', 'bloccato' => 'Area bloccata il',
            'violazioni' => 'VIOLAZIONI ACCERTATE', 'nessuna' => 'Nessuna registrata.', 'trappole' => 'VOCI DI CONTROLLO CONTATTATE',
            'accessi' => 'REGISTRO ACCESSI (ultimi 200)', 'provv' => 'PROVVIGIONI', 'testo' => 'TESTO INTEGRALE ACCETTATO',
            'note' => 'NOTA GIURIDICA (sintesi, da verificare)', 'bozza' => 'BOZZA DI DIFFIDA (da verificare e firmare dall’avvocato)',
            'generato' => 'Generato il', 'pagina' => 'Pagina',
        ],
        'de' => [
            'titel' => 'Akte für den Anwalt', 'sub' => 'Partner und möglicher Verstoß gegen die Partnervereinbarung',
            'parti' => 'PARTEIEN', 'titolare' => 'Inhaber', 'partner' => 'Partner', 'accordo' => 'ANGENOMMENE VEREINBARUNG',
            'versione' => 'Fassung', 'accettato' => 'Angenommen am', 'clausole' => 'Klauseln bestätigt (2. Haken)',
            'modo' => 'Art der Zustimmung', 'modo_t' => 'Zwei Haken im Partnerbereich (keine elektronische Unterschrift, Entscheidung des Inhabers).',
            'impronta' => 'SHA-256-Prüfsumme des Wortlauts', 'attivato' => 'Bereich freigeschaltet am', 'bloccato' => 'Bereich gesperrt am',
            'violazioni' => 'FESTGESTELLTE VERSTÖSSE', 'nessuna' => 'Keine erfasst.', 'trappole' => 'ANGESCHRIEBENE KONTROLLEINTRÄGE',
            'accessi' => 'ZUGRIFFSPROTOKOLL (letzte 200)', 'provv' => 'PROVISIONEN', 'testo' => 'VOLLER ANGENOMMENER WORTLAUT',
            'note' => 'RECHTLICHES MERKBLATT (Kurzfassung, vom Anwalt zu prüfen)', 'bozza' => 'ENTWURF EINER ABMAHNUNG (vom Anwalt zu prüfen und zu unterschreiben)',
            'generato' => 'Erstellt am', 'pagina' => 'Seite',
        ],
    ];

    public const MERKBLATT = [
        'it' => [
            'Segreti commerciali (artt. 98–99 D.Lgs. 30/2005): i dati, le analisi e le liste sono accessibili solo con chiave personale, dopo accettazione dell’obbligo di riservatezza; ogni accesso è registrato e i dati contengono voci di controllo (misure di segretezza adeguate).',
            'Banca dati (artt. 102-bis ss. L. 633/1941): la raccolta, selezione e valutazione delle aziende è frutto di investimento del titolare. I dati grezzi (nome, indirizzo) provengono in parte da fonti aperte (OpenStreetMap/ODbL, Overture Maps) e non sono di esclusiva del titolare.',
            'Diritto d’autore e marchio: testi, grafiche, immagini 3D, volantini e logo sono opere del titolare; il logo è usato come marchio di fatto (art. 2571 c.c.). Registrazione UIBM consigliata.',
            'Concorrenza sleale (art. 2598 c.c.) e patto di non concorrenza (art. 2596 c.c.: forma scritta, limite di attività, massimo 5 anni).',
            'Protezione dei dati: il partner agisce come persona autorizzata/responsabile (artt. 28–29 GDPR); un uso per fini propri lo rende titolare autonomo (art. 28, par. 10).',
            'Penale (artt. 1382–1384 c.c., riducibile dal giudice) e clausola risolutiva espressa (art. 1456 c.c.).',
            'Attenzione: secondo Cass. ord. 20945/2026 la sola spunta non basta per l’approvazione specifica delle clausole vessatorie (artt. 1341–1342 c.c.); penale, tutela della clientela e foro potrebbero essere contestati. Riservatezza, proprietà, marchio e dati restano validi.',
            'Rischio di riqualificazione come agente di commercio (artt. 1742 ss. c.c., Enasarco) se il partner opera in modo stabile e organizzato; l’accordo esclude esclusiva, zona e obblighi di attività.',
        ],
        'de' => [
            'Geschäftsgeheimnis (Art. 98–99 D.Lgs. 30/2005, GeschGehG): Daten, Analysen und Listen sind nur mit persönlichem Schlüssel nach Annahme der Vertraulichkeitspflicht zugänglich; jeder Zugriff wird protokolliert, die Daten enthalten Kontrolleinträge (angemessene Geheimhaltungsmaßnahmen).',
            'Datenbankrecht (Art. 102-bis ff. L. 633/1941): Zusammenstellung, Auswahl und Bewertung der Betriebe sind eine Investition des Inhabers. Die Rohdaten (Name, Adresse) stammen teils aus offenen Quellen (OpenStreetMap/ODbL, Overture Maps) und gehören ihm nicht exklusiv.',
            'Urheberrecht und Marke: Texte, Grafiken, 3D-Bilder, Flyer und Logo sind Werke des Inhabers; das Logo wird als nicht eingetragene Marke benutzt (Art. 2571 c.c.). Eintragung beim UIBM empfohlen.',
            'Unlauterer Wettbewerb (Art. 2598 c.c.) und Wettbewerbsverbot (Art. 2596 c.c.: schriftlich, auf eine Tätigkeit begrenzt, höchstens 5 Jahre).',
            'Datenschutz: Der Partner handelt als befugte Person bzw. Auftragsverarbeiter (Art. 28–29 DSGVO); Nutzung für eigene Zwecke macht ihn zum eigenen Verantwortlichen (Art. 28 Abs. 10).',
            'Vertragsstrafe (Art. 1382–1384 c.c., vom Richter herabsetzbar) und ausdrückliche Auflösungsklausel (Art. 1456 c.c.).',
            'Achtung: Nach Cass. ord. 20945/2026 reicht ein Haken allein nicht für die ausdrückliche Zustimmung zu belastenden Klauseln (Art. 1341–1342 c.c.); Vertragsstrafe, Kundenschutz und Gerichtsstand könnten angegriffen werden. Vertraulichkeit, Eigentum, Marke und Datenschutz tragen weiter.',
            'Risiko der Einstufung als Handelsvertreter (Art. 1742 ff. c.c., Enasarco; in DE § 84 ff. HGB) bei dauerhafter, organisierter Tätigkeit; die Vereinbarung schließt Exklusivität, Gebiet und Tätigkeitspflicht aus.',
        ],
    ];

    /** Entwurf einer Abmahnung/diffida — ausdrücklich nur ein Entwurf für den Anwalt. */
    public static function diffida(array $p, string $sprache, array $verstoesse): string
    {
        require_once __DIR__ . '/Firma.php';
        $w = self::werte($sprache);
        $absender = implode(', ', Firma::anschrift());
        $liste = $verstoesse ? implode("\n", array_map(static fn($v) => '- ' . date('d.m.Y', strtotime((string) $v['festgestellt_am'])) . ': ' . mb_substr((string) $v['beschreibung'], 0, 400), $verstoesse)) : '- …';
        if ($sprache === 'de') {
            return "Abmahnung und Aufforderung zur Unterlassung\n\nAn: {$p['name']}" . ((string) $p['firma'] !== '' ? " ({$p['firma']})" : '') . ", {$p['email']}\nVon: $absender\n\n"
                . "Sie haben am " . date('d.m.Y', strtotime((string) $p['vereinbarung_am'])) . " der Partnervereinbarung von Vecom Design (Fassung " . $p['vereinbarung_version'] . ") zugestimmt. Wir haben folgende Verstöße festgestellt:\n$liste\n\n"
                . "Wir fordern Sie auf, binnen 7 Tagen nach Zugang (1) jede Nutzung der Vecom-Unterlagen, Betriebsdaten, des Logos und der Werbemittel einzustellen, (2) alle Kopien zu löschen und dies schriftlich zu bestätigen, (3) mitzuteilen, an wen Sie Daten weitergegeben haben, und (4) die vereinbarte Vertragsstrafe von {$w['{penale}']} je Verstoß zu zahlen. Weitergehende Ansprüche, insbesondere auf Schadensersatz, behalten wir uns vor.\n\n"
                . "Die Partnervereinbarung ist mit sofortiger Wirkung aufgelöst (Nr. 14, Art. 1456 c.c.).\n\nOrt, Datum, Unterschrift";
        }
        return "Diffida e messa in mora\n\nA: {$p['name']}" . ((string) $p['firma'] !== '' ? " ({$p['firma']})" : '') . ", {$p['email']}\nDa: $absender\n\n"
            . "In data " . date('d/m/Y', strtotime((string) $p['vereinbarung_am'])) . " Lei ha accettato l’accordo partner di Vecom Design (versione " . $p['vereinbarung_version'] . "). Abbiamo accertato le seguenti violazioni:\n$liste\n\n"
            . "La diffidiamo, entro 7 giorni dal ricevimento, a (1) cessare ogni uso dei Materiali Vecom, dei dati delle aziende, del logo e dei materiali pubblicitari, (2) cancellarne ogni copia e confermarlo per iscritto, (3) indicarci a chi ha ceduto i dati e (4) pagare la penale concordata di {$w['{penale}']} per violazione. Restano salvi ulteriori diritti, in particolare al risarcimento del danno.\n\n"
            . "L’accordo partner è risolto con effetto immediato (punto 14, art. 1456 c.c.).\n\nLuogo, data, firma";
    }

    /** Die Akte als PDF, italienisch (für den Avvocato) oder deutsch. */
    public static function aktePdf(int $partnerId, string $sprache = 'it'): ?string
    {
        require_once __DIR__ . '/Pdf.php';
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Fmt.php';
        $p = Partner::laden($partnerId);
        if (!$p) { return null; }
        $sp = $sprache === 'de' ? 'de' : 'it';
        $W = self::AKTE[$sp];
        $pdf = new Pdf();
        $pdf->info(['Title' => $W['titel'] . ' — ' . $p['name'], 'Author' => 'Vecom Design', 'Subject' => self::kennung($p)]);
        $rand = 56.0; $breite = Pdf::A4_BREIT - 2 * $rand; $unten = Pdf::A4_HOCH - 60;
        $tinte = [0.05, 0.08, 0.12]; $grau = [0.42, 0.46, 0.53]; $gold = [0.62, 0.48, 0.18];
        $y = 0.0;
        $fuss = static function () use (&$pdf, $rand, $W, $grau): void {
            $pdf->text($rand, Pdf::A4_HOCH - 30, 'Vecom Design · ' . $W['titel'] . ' · ' . $W['pagina'] . ' ' . $pdf->seitenzahl(), 7.5, false, 'links', $grau);
        };
        $platz = static function (float $noetig) use (&$pdf, &$y, $unten, $fuss): void {
            if ($y + $noetig > $unten) { $fuss(); $pdf->neueSeite(); $y = 60.0; }
        };
        $absatz = static function (string $text, float $gr = 9.5, bool $fett = false, array $farbe = [0.05, 0.08, 0.12]) use (&$pdf, &$y, $rand, $breite, $platz): void {
            foreach ($pdf->umbrechen($text, $breite, $gr, $fett) as $z) {
                $platz($gr * 1.5);
                $pdf->text($rand, $y, $z, $gr, $fett, 'links', $farbe);
                $y += $gr * 1.45;
            }
        };
        $kopf = static function (string $t) use (&$pdf, &$y, $rand, $breite, $platz, $gold): void {
            $y += 10; $platz(40);
            $pdf->text($rand, $y, $t, 9, true, 'links', $gold);
            $pdf->linie($rand, $y + 5, $rand + $breite, $y + 5, 0.6, [0.85, 0.8, 0.7]);
            $y += 20;
        };
        $zeile = static function (string $k, string $v) use (&$pdf, &$y, $rand, $breite, $platz, $grau, $tinte): void {
            $zeilen = $pdf->umbrechen($v !== '' ? $v : '—', $breite - 170, 9.5);
            $platz(14 * count($zeilen));
            $pdf->text($rand, $y, $k, 9, false, 'links', $grau);
            foreach ($zeilen as $i => $z) { $pdf->text($rand + 170, $y + $i * 13.5, $z, 9.5, false, 'links', $tinte); }
            $y += 13.5 * count($zeilen) + 2;
        };
        $dt = static fn($d): string => $d ? date($sp === 'de' ? 'd.m.Y H:i' : 'd/m/Y H:i', strtotime((string) $d)) : '—';

        $y = 70;
        $pdf->text($rand, $y, $W['titel'], 20, true, 'links', $tinte); $y += 20;
        $pdf->text($rand, $y, $W['sub'] . ' — ' . $p['name'], 10, false, 'links', $grau); $y += 14;
        $pdf->text($rand, $y, $W['generato'] . ' ' . $dt(date('Y-m-d H:i:s')), 8.5, false, 'links', $grau); $y += 6;

        $kopf($W['parti']);
        $zeile($W['titolare'], implode(', ', Firma::anschrift()));
        $zeile($W['partner'], trim($p['name'] . ((string) $p['firma'] !== '' ? ' — ' . $p['firma'] : '') . ((string) $p['steuer_nr'] !== '' ? ' — ' . $p['steuer_nr'] : '')));
        $zeile('E-Mail', (string) $p['email']);
        $zeile('Code / ID', $p['code'] . ' / ' . $p['id'] . ' / ' . self::kennung($p));
        $zeile('Status', (string) $p['status']);

        $kopf($W['accordo']);
        $zeile($W['versione'], (string) ($p['vereinbarung_version'] ?? ''));
        $zeile($W['accettato'], $dt($p['vereinbarung_am'] ?? null));
        $zeile($W['clausole'], $dt($p['vereinbarung_klauseln_am'] ?? null));
        $zeile($W['modo'], $W['modo_t']);
        $zeile($W['impronta'], (string) ($p['vereinbarung_hash'] ?? ''));
        $zeile('IP (hash)', (string) ($p['vereinbarung_ip_hash'] ?? ''));
        $zeile($W['attivato'], $dt($p['freigeschaltet_am'] ?? null) . ((string) ($p['freigeschaltet_von'] ?? '') !== '' ? ' (' . $p['freigeschaltet_von'] . ')' : ''));
        if (!empty($p['gesperrt_am'])) { $zeile($W['bloccato'], $dt($p['gesperrt_am']) . ' — ' . $p['gesperrt_grund']); }

        $kopf($W['violazioni']);
        $vs = self::verstoesse($partnerId);
        if (!$vs) { $absatz($W['nessuna']); }
        foreach ($vs as $v) {
            $absatz(date('d.m.Y', strtotime((string) $v['festgestellt_am'])) . ' · ' . (self::VERSTOSS_ARTEN[$v['art']] ?? $v['art']) . ' · ' . $v['erfasst_von'], 9, true);
            $absatz((string) $v['beschreibung']);
            if ((string) $v['beleg'] !== '') { $absatz('Beleg: ' . $v['beleg'], 8.5, false, $grau); }
            $y += 4;
        }

        $kopf($W['trappole']);
        $tr = self::fallenTreffer($partnerId);
        if (!$tr) { $absatz($W['nessuna']); }
        foreach ($tr as $t) {
            $absatz($dt($t['eingang_am'] ?: $t['created_at']) . ' · ' . $t['email'] . ' (' . $t['name'] . ', ' . $t['ort'] . ')', 9, true);
            $absatz('Da/Von: ' . $t['von'] . ' — ' . $t['betreff']);
            $y += 3;
        }

        $kopf($W['accessi']);
        foreach (self::zugriffe($partnerId, 200) as $z) {
            $absatz($dt($z['created_at']) . '  ' . $z['art'] . ((int) $z['firma_id'] !== 0 ? '  #' . $z['firma_id'] . ($z['firma'] ? ' ' . $z['firma'] . ' (' . $z['stadt'] . ')' : '') : '')
                . ((string) $z['info'] !== '' ? '  ' . $z['info'] : '') . ((string) $z['ip_hash'] !== '' ? '  ip:' . $z['ip_hash'] : ''), 8);
        }

        $kopf($W['provv']);
        $s = Partner::summen($partnerId);
        $absatz(implode(' · ', array_map(static fn($k) => $k . ' ' . Fmt::geld((int) ($s[$k] ?? 0)), ['wartet', 'freigabe', 'bereit', 'ausgezahlt'])));

        $kopf($W['testo']);
        $absatz((string) ($p['vereinbarung_text'] ?? '—'), 8.5);

        $kopf($W['note']);
        foreach (self::MERKBLATT[$sp] as $m) { $absatz('• ' . $m, 9); $y += 2; }

        $kopf($W['bozza']);
        $absatz(self::diffida($p, $sp, $vs), 9);

        $fuss();
        return $pdf->fertig();
    }
}
