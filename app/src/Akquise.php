<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Events.php';

/**
 * Akquise — Firmen, Dubletten, Audits, Protokoll.
 *
 * WAS DIESE KLASSE IST
 *
 * Die Stammdatenhaltung des Lead-Systems. Sie nimmt entgegen, was der Worker
 * auf Uwes Rechner findet (Firmen aus OpenStreetMap, Audit-Ergebnisse), und
 * sorgt dafuer, dass es jede Firma genau einmal gibt. Bewertet wird in
 * AkquiseScore, gesperrt und freigegeben in AkquiseGate, geschrieben in
 * AkquiseText. Hier steht nichts, was das Haus verlaesst.
 *
 * WARUM DIE DUBLETTENPRUEFUNG DREI WEGE GEHT
 *
 * Dieselbe Pizzeria steht in OpenStreetMap oft zweimal: einmal als Punkt,
 * einmal als Gebaeude, manchmal mit "www." und manchmal ohne, einmal als
 * "Pizzeria Da Totò" und einmal als "Da Toto". Die Domain ist der staerkste
 * Anker, aber nicht jeder Betrieb hat eine. Deshalb: Domain → Quelle →
 * Name+PLZ → Adresse. Was ueber einen dieser Wege schon da ist, wird
 * ergaenzt, nicht neu angelegt -- und eine Sperre bleibt dabei immer stehen.
 */
final class Akquise
{
    /** Kategorien der Befunde, in der Reihenfolge der Detailansicht. */
    public const KATEGORIEN = [
        'technik'     => 'Technik',
        'performance' => 'Performance',
        'mobile'      => 'Mobile',
        'ux'          => 'UX',
        'design'      => 'Design',
        'seo'         => 'SEO',
        'conversion'  => 'Conversion',
        'vertrauen'   => 'Vertrauen',
        'experience'  => 'Experience-Potenzial',
    ];

    public const KONTAKT_STATUS = [
        'neu' => 'Neu', 'qualifiziert' => 'Qualifiziert', 'vorlage' => 'Vorlage bereit',
        'freigegeben' => 'Freigegeben', 'kontaktiert' => 'Kontaktiert', 'geantwortet' => 'Geantwortet',
        'kunde' => 'Kunde geworden', 'abgelehnt' => 'Kein Interesse', 'gesperrt' => 'Gesperrt',
    ];

    public const AUDIT_STATUS = [
        'offen' => 'Wartet auf Audit', 'laeuft' => 'Audit läuft', 'fertig' => 'Geprüft',
        'fehler' => 'Audit gescheitert', 'keine_website' => 'Keine eigene Website', 'uebersprungen' => 'Übersprungen',
    ];

    /* ==================================================================
       EINFACHE SPRACHE (26.09.2026, Uwe: „einfacher, verstaendlicher")

       In der Datenbank bleiben die feinen Zustaende stehen -- sie tragen
       die Logik (Gate, Zweitansprache, Prüfspur). Auf dem Bildschirm
       stehen fuenf Stufen und eine Ampel. Umbenannt wird nur die Anzeige,
       nie die gespeicherten Werte: Sonst waeren alte Protokolle und die
       Kette nicht mehr lesbar.
       ================================================================== */

    /** Fuenf Stufen fuer den Bildschirm => die gespeicherten Kontaktzustaende dahinter. */
    public const STUFEN5 = [
        'neu'         => ['Neu', ['neu']],
        'bereit'      => ['Bereit', ['qualifiziert', 'vorlage', 'freigegeben']],
        'kontaktiert' => ['Kontaktiert', ['kontaktiert']],
        'antwort'     => ['Antwort', ['geantwortet']],
        'erledigt'    => ['Erledigt', ['kunde', 'abgelehnt', 'gesperrt']],
    ];

    public static function stufe5(string $kontaktStatus): string
    {
        foreach (self::STUFEN5 as $k => [, $werte]) {
            if (in_array($kontaktStatus, $werte, true)) { return $k; }
        }
        return 'neu';
    }

    /* ==================================================================
       PIPELINE (27.09.2026, Uwe: „Pipeline-Anzeige an der Firma“)

       Die fünf Stufen oben bleiben für Listen und Filter. An der Firma
       steht der ganze Weg bis zum Auftrag. Die Stufen werden aus dem
       gerechnet, was schon da ist -- nur Angebot, Verhandlung, Gewonnen und
       Verloren setzt Uwe selbst (Spalte pipeline). Eine zweite, von Hand
       gepflegte Wahrheit über „kontaktiert“ oder „Termin“ gibt es nicht.
       ================================================================== */
    public const PIPELINE = [
        'neu' => 'Neu', 'analysiert' => 'Analysiert', 'qualifiziert' => 'Qualifiziert', 'kontaktweg' => 'Kontaktweg',
        'kontaktiert' => 'Kontaktiert', 'interesse' => 'Interesse', 'termin' => 'Termin', 'angebot' => 'Angebot',
        'verhandlung' => 'Verhandlung', 'gewonnen' => 'Gewonnen',
    ];
    /** Was Uwe selbst setzen darf. */
    public const PIPELINE_HAND = ['angebot' => 'Angebot geschickt', 'verhandlung' => 'In Verhandlung', 'gewonnen' => 'Gewonnen', 'verloren' => 'Verloren'];

    /**
     * @return array{jetzt:string, verloren:bool, erreicht:array<string,string>}  erreicht: Stufe => woran man es sieht
     */
    public static function pipeline(array $f): array
    {
        $id = (int) $f['id'];
        $wert = static function (string $sql, array $p = []) { try { return Db::wert($sql, $p, 0); } catch (Throwable $e) { return 0; } };
        $e = ['neu' => 'gefunden am ' . date('d.m.Y', strtotime((string) ($f['recherchiert_am'] ?? $f['created_at'] ?? 'now')))];
        if (in_array((string) $f['audit_status'], ['fertig', 'keine_website'], true)) {
            $e['analysiert'] = $f['audit_status'] === 'fertig' ? 'Website geprüft' : 'keine eigene Website';
        }
        $check = (int) $wert('SELECT COUNT(*) FROM akq_checks WHERE firma_id = ?', [$id]);
        if (((int) ($f['score'] ?? 0)) >= 51 || in_array((string) $f['kontakt_status'], ['qualifiziert', 'vorlage', 'freigegeben'], true) || $check > 0) {
            $e['qualifiziert'] = $check > 0 ? 'hat selbst den Website-Check gemacht' : 'Chance ' . (int) ($f['score'] ?? 0);
        }
        require_once __DIR__ . '/AkquiseGate.php';
        $amp = AkquiseGate::ampel($f);
        /* Ein Weg (Brief, Anruf) gibt es für fast jeden Betrieb -- als Stufe zählt er erst, wenn der Betrieb auch in Frage kommt. */
        if (trim((string) ($f['einwilligung'] ?? '')) !== '' || (isset($e['qualifiziert']) && in_array($amp['farbe'], ['gruen', 'gelb'], true))) {
            $e['kontaktweg'] = trim((string) ($f['einwilligung'] ?? '')) !== '' ? 'Einwilligung liegt vor' : $amp['wort'];
        }
        $gesendet = (int) $wert("SELECT COUNT(*) FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand')", [$id]);
        if ($gesendet > 0 || in_array((string) $f['kontakt_status'], ['kontaktiert', 'geantwortet', 'kunde'], true)) {
            $e['kontaktiert'] = $gesendet . ' ' . ($gesendet === 1 ? 'Kontakt' : 'Kontakte');
        }
        $positiv = (int) $wert("SELECT COUNT(*) FROM akq_antworten WHERE firma_id = ? AND klasse IN ('INTERESTED','MORE_INFO','CALL_REQUEST','PRICE_REQUEST')", [$id]);
        $wunsch = (int) $wert('SELECT COUNT(*) FROM akq_checks WHERE firma_id = ? AND ausfuehrlich = 1', [$id]);
        $termine = (int) $wert("SELECT COUNT(*) FROM akq_termine WHERE firma_id = ? AND status IN ('gebucht','erledigt')", [$id]);
        if ($positiv > 0 || $wunsch > 0 || $termine > 0) {
            $e['interesse'] = $positiv > 0 ? 'positive Antwort' : ($wunsch > 0 ? 'will die ausführliche Analyse' : 'hat einen Termin gebucht');
        }
        if ($termine > 0) { $e['termin'] = $termine === 1 ? 'Termin gebucht' : $termine . ' Termine'; }
        $hand = (string) ($f['pipeline'] ?? '');
        $am = !empty($f['pipeline_am']) ? ' am ' . date('d.m.Y', strtotime((string) $f['pipeline_am'])) : '';
        foreach (['angebot', 'verhandlung', 'gewonnen'] as $k) {
            if (array_search($hand, array_keys(self::PIPELINE), true) >= array_search($k, array_keys(self::PIPELINE), true) && isset(self::PIPELINE[$hand])) {
                $e[$k] = $k === $hand ? 'von dir gesetzt' . $am : 'erledigt';
            }
        }
        if ((string) $f['kontakt_status'] === 'kunde' || (int) ($f['bestandskunde'] ?? 0) === 1) { $e['gewonnen'] = $e['gewonnen'] ?? 'Kunde geworden'; }
        $jetzt = 'neu';
        foreach (array_keys(self::PIPELINE) as $k) { if (isset($e[$k])) { $jetzt = $k; } }
        $verloren = $hand === 'verloren' || ((string) $f['kontakt_status'] === 'abgelehnt' && $jetzt !== 'gewonnen');
        return ['jetzt' => $jetzt, 'verloren' => $verloren, 'erreicht' => $e];
    }

    /** Angebot / Verhandlung / Gewonnen / Verloren von Hand setzen ('' = zurück auf das Gerechnete). */
    /** Ergebnis der Kundenanlage beim letzten „Gewonnen“ — für die Meldung nach dem Klick (Modul G). */
    public static ?array $letzterKunde = null;

    public static function pipelineSetzen(int $id, string $wert): void
    {
        self::$letzterKunde = null;
        if ($wert !== '' && !isset(self::PIPELINE_HAND[$wert])) { throw new InvalidArgumentException('Unbekannte Stufe.'); }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        $neu = ['pipeline' => $wert !== '' ? $wert : null, 'pipeline_am' => $wert !== '' ? date('Y-m-d H:i:s') : null];
        if ($wert === 'gewonnen' && !in_array((string) $f['kontakt_status'], ['kunde'], true)) { $neu['kontakt_status'] = 'kunde'; }
        Db::update('akq_firmen', $id, $neu);
        Events::pruefspur('akquise_pipeline', 'akq_firmen', $id, ['pipeline' => $f['pipeline'] ?? null, 'kontakt_status' => $f['kontakt_status']], $neu);
        self::protokoll($id, 'pipeline', $wert !== '' ? 'Stand gesetzt: ' . self::PIPELINE_HAND[$wert] : 'Stand zurückgesetzt (wieder aus den Daten gerechnet)');
        /* Modul G (06.10.2026, Uwe: „Automatisch bei Gewonnen“): Jetzt entsteht der Kunde — oder wird verknüpft,
           wenn es ihn unter der Adresse schon gibt. Ohne Adresse später, sobald sie eingetragen ist. */
        if ($wert === 'gewonnen') {
            require_once __DIR__ . '/AkquiseKunde.php';
            self::$letzterKunde = AkquiseKunde::nachGewonnen($id);
        }
        /* Gewonnen oder verloren: keine Folge-Mails mehr. */
        if (in_array($wert, ['gewonnen', 'verloren'], true)) {
            try {
                require_once __DIR__ . '/AkquiseFolge.php';
                $fo = Db::wert("SELECT id FROM akq_folgen WHERE firma_id = ? AND status <> 'beendet'", [$id], null);
                if ($fo !== null) { AkquiseFolge::beenden((int) $fo, $wert === 'gewonnen' ? 'Gewonnen — der Kundenweg übernimmt' : 'Als verloren markiert'); }
            } catch (Throwable $e) { }
        }
    }

    /** "Chance" statt "Score": Zahl plus ein Wort, das man ohne Legende versteht. */
    public static function chanceWort(?int $score): string
    {
        if ($score === null) { return '—'; }
        return match (true) {
            $score >= 86 => 'Top',
            $score >= 71 => 'sehr gut',
            $score >= 51 => 'gut',
            $score >= 31 => 'mittel',
            default      => 'gering',
        };
    }

    public static function belegWort(string $status): string
    {
        return $status === 'VERIFIED' ? 'geprüft' : ($status === 'UNVERIFIED' ? 'unsicher' : 'verworfen');
    }

    /** Spricht die Firma Deutsch? Dann gibt es den Anrufzettel (Uwes Regel: DE auch anrufen). */
    public static function deutschsprachig(array $f): bool
    {
        return strtoupper((string) ($f['land'] ?? '')) === 'DE' || (string) ($f['sprache'] ?? '') === 'de';
    }

    /**
     * Der eine naechste Schritt je Firma -- das, was der goldene Knopf tut.
     *
     * @param array<string,mixed> $f Firma, optional mit vorlage_status/vorlage_kanal
     * @return array{wort:string,ziel:string,art:string}|null  art: link|post|still
     */
    public static function naechsterSchritt(array $f): ?array
    {
        $id = (int) ($f['id'] ?? 0);
        $seite = 'akquise/' . $id;
        if ((int) ($f['gesperrt'] ?? 0) === 1) { return null; }
        $ks = (string) ($f['kontakt_status'] ?? 'neu');
        $as = (string) ($f['audit_status'] ?? 'offen');
        if (in_array($ks, ['kunde', 'abgelehnt'], true)) { return null; }
        if ($ks === 'geantwortet') { return ['wort' => 'Antwort ansehen', 'ziel' => $seite . '?ansicht=verlauf', 'art' => 'link']; }
        if ($ks === 'kontaktiert') { return ['wort' => 'Antwort eintragen', 'ziel' => $seite . '#antwort', 'art' => 'link']; }
        if (in_array($as, ['offen', 'laeuft'], true)) { return ['wort' => 'Wird geprüft', 'ziel' => $seite, 'art' => 'still']; }
        if ($as === 'keine_website') {
            return self::deutschsprachig($f)
                ? ['wort' => 'Anrufzettel', 'ziel' => $seite . '/anruf', 'art' => 'link']
                : ['wort' => 'Ansehen', 'ziel' => $seite, 'art' => 'link'];
        }
        $vs = (string) ($f['vorlage_status'] ?? '');
        $vk = (string) ($f['vorlage_kanal'] ?? 'brief');
        require_once __DIR__ . '/AkquiseGate.php';
        $briefAn = AkquiseGate::briefAn();
        if ($vs === 'freigegeben') {
            if ($vk === 'email') { return ['wort' => 'E-Mail senden', 'ziel' => $seite . '#kontakt', 'art' => 'link']; }
            if ($briefAn) { return ['wort' => 'Brief drucken', 'ziel' => $seite . '/brief', 'art' => 'link']; }
        }
        if ($vs === 'entwurf' && ($vk === 'email' || $briefAn)) { return ['wort' => 'Text prüfen', 'ziel' => $seite . '#kontakt', 'art' => 'link']; }
        if ($as === 'fertig' && (int) ($f['score'] ?? 0) >= 31) {
            /* Ohne Briefe (27.09.2026): E-Mail nur mit Einwilligung, sonst der Anruf -- oder nichts. */
            if (trim((string) ($f['einwilligung'] ?? '')) !== '' && !empty($f['email'])) { return ['wort' => 'E-Mail schreiben', 'ziel' => 'akq_vorlage_regel', 'art' => 'post']; }
            if ($briefAn) { return ['wort' => 'Brief schreiben', 'ziel' => 'akq_vorlage_regel', 'art' => 'post']; }
            if (self::deutschsprachig($f) && !empty($f['telefon'])) { return ['wort' => 'Anrufzettel', 'ziel' => $seite . '/anruf', 'art' => 'link']; }
        }
        return ['wort' => 'Ansehen', 'ziel' => $seite, 'art' => 'link'];
    }

    /** Nach so vielen Tagen darf eine Website neu geprueft werden. */
    public const NEUPRUEFUNG_TAGE = 90;

    /**
     * Plattformen, die keine eigene Website sind. Steht dort die "Website"
     * eines Betriebs, hat er keine -- und genau das ist der Befund, nicht
     * die Facebook-Seite.
     */
    private const PLATTFORMEN = [
        'facebook.com', 'm.facebook.com', 'instagram.com', 'tripadvisor.com', 'tripadvisor.it', 'tripadvisor.de',
        'booking.com', 'airbnb.com', 'airbnb.it', 'airbnb.de', 'google.com', 'goo.gl', 'maps.app.goo.gl',
        'linktr.ee', 'wa.me', 'whatsapp.com', 'youtube.com', 'tiktok.com', 'twitter.com', 'x.com',
        'paginegialle.it', 'paginebianche.it', 'gelbeseiten.de', 'yelp.com', 'thefork.it', 'thefork.com',
        'linkedin.com', 'business.site',
    ];

    /** Rechtsformen fallen fuer den Namensvergleich weg. */
    private const RECHTSFORMEN = [
        'gmbh & co. kg', 'gmbh & co kg', 'gmbh', 'ug haftungsbeschraenkt', 'ug', 'ohg', 'kg', 'gbr', 'e.k.', 'ek',
        'e.v.', 'ag', 'mbh', 's.r.l.s.', 's.r.l.', 'srls', 'srl', 's.n.c.', 'snc', 's.a.s.', 'sas', 's.p.a.', 'spa',
        'di', 'ditta', 'societa cooperativa', 'soc. coop.', 'coop',
    ];

    /** @var array<string,array>|null */
    private static ?array $branchen = null;

    /* ================================================================== */
    /*  Branchen                                                          */
    /* ================================================================== */

    /** @return array<string,array<string,mixed>> */
    public static function branchen(): array
    {
        if (self::$branchen === null) {
            $roh = json_decode((string) file_get_contents(__DIR__ . '/akquise_branchen.json'), true) ?: [];
            unset($roh['_hinweis']);
            self::$branchen = $roh;
        }
        return self::$branchen;
    }

    public static function branchenName(?string $schluessel, string $sprache = 'de'): string
    {
        if ($schluessel === null || $schluessel === '') { return '—'; }
        $b = self::branchen()[$schluessel] ?? null;
        return $b ? (string) ($b[$sprache] ?? $b['de']) : $schluessel;
    }

    /* ================================================================== */
    /*  Normalisieren                                                     */
    /* ================================================================== */

    public static function ohneAkzente(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $t === false ? $s : $t;
    }

    /** "Pizzeria Da Totò S.r.l." → "pizzeria da toto" */
    public static function normName(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);
        $s = self::ohneAkzente($s);
        $s = strtolower($s);
        foreach (self::RECHTSFORMEN as $rf) {
            // Nur am Wortende oder als ganzes Wort -- "Agenzia Spa" ist eine
            // Firma, "Spa Resort" ist ein Wellnesshotel.
            $s = preg_replace('~(^|\s)' . preg_quote($rf, '~') . '\s*$~', ' ', $s) ?? $s;
        }
        $s = preg_replace('~[^a-z0-9]+~', ' ', $s) ?? $s;
        return trim(preg_replace('~\s+~', ' ', $s) ?? $s);
    }

    /** Liefert die Domain ohne www., klein -- oder null, wenn es keine eigene ist. */
    public static function normDomain(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') { return null; }
        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) { $url = 'http://' . $url; }
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || !str_contains($host, '.')) { return null; }
        $host = strtolower(rtrim($host, '.'));
        if (function_exists('idn_to_ascii')) {
            $a = @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($a) && $a !== '') { $host = $a; }
        }
        $host = preg_replace('~^www\d?\.~', '', $host) ?? $host;
        return $host;
    }

    public static function istPlattform(?string $domain): bool
    {
        if ($domain === null) { return false; }
        foreach (self::PLATTFORMEN as $p) {
            if ($domain === $p || str_ends_with($domain, '.' . $p)) { return true; }
        }
        return false;
    }

    public static function normAdresse(?string $strasse, ?string $plz = null): ?string
    {
        $s = trim((string) $strasse);
        if ($s === '') { return null; }
        $s = mb_strtolower($s);
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);
        $s = strtolower(self::ohneAkzente($s));
        $s = preg_replace(['~strasse\b~', '~str\.?\b~', '~\bvia\b~', '~\bviale\b~', '~\bpiazza\b~', '~\bcorso\b~'],
                          ['str', 'str', 'v', 'vle', 'p', 'c'], $s) ?? $s;
        $s = preg_replace('~[^a-z0-9]+~', '', $s) ?? $s;
        return $s === '' ? null : mb_substr($s . '|' . trim((string) $plz), 0, 255);
    }

    /**
     * Telefon in internationaler Form. Mit Land wird aus der Ortsnummer eine
     * +39/+49-Nummer -- sonst waeren "0922 000111" aus OSM und
     * "+39 0922 000111" von der Website zwei verschiedene Nummern, und die
     * Sperrliste liesse die zweite durch. In Italien bleibt die 0 der
     * Vorwahl stehen, in Deutschland faellt sie weg.
     */
    public static function normTelefon(?string $t, ?string $land = null): ?string
    {
        $z = preg_replace('~[^0-9+]~', '', (string) $t) ?? '';
        if ($z === '' || strlen(ltrim($z, '+')) < 6) { return null; }
        if (str_starts_with($z, '00')) { $z = '+' . substr($z, 2); }
        if (!str_starts_with($z, '+')) {
            /* Ländervorwahl ohne Plus (29.09.2026): Overture liefert oft „4940619121“ oder
               „390922…“. Ohne „+“ wählt das Telefon des Partners eine falsche Nummer.
               In Italien hat eine Nummer ohne Vorwahl höchstens 10 Ziffern und beginnt nie
               mit 39 + weiterer Ziffer so lang -- deshalb erst ab 11 Ziffern als Vorwahl deuten. */
            $land = strtoupper((string) $land);
            if ($land === 'IT') { $z = (str_starts_with($z, '39') && strlen($z) >= 11 ? '+' : '+39') . $z; }
            elseif ($land === 'DE' && str_starts_with($z, '0')) { $z = '+49' . substr($z, 1); }
            elseif ($land === 'DE' && preg_match('~^49[1-9]\d{6,11}$~', $z)) { $z = '+' . $z; }
        }
        /* Doppelte 49 vor einer Handynummer (29.09.2026, live gesehen: „+49491723890040“). Nach
           +49 folgt bei Handys 15x/16x/17x mit 10–11 Ziffern; „49“ davor ist die Vorwahl ein zweites Mal. */
        if (preg_match('~^\+49(49)(1[5-7]\d{8,9})$~', $z, $m)) { $z = '+49' . $m[2]; }
        return $z;
    }

    public static function normEmail(?string $e): ?string
    {
        $e = mb_strtolower(trim((string) $e));
        if ($e === '') { return null; }
        if (filter_var($e, FILTER_VALIDATE_EMAIL)) { return $e; }
        /* Tolerant (06.10.2026, Uwe: „alle Betriebe, wo E-Mail vorhanden ist, sollen im Mailprogramm öffnen — es gibt noch
           einige, wo es nicht geht“): „mailto:“, Leerzeichen, „[at]“/„(at)“, mehrere Adressen („a@x.it; b@x.it“ → die erste),
           Satzzeichen am Rand und Umlaut-Domains (bäckerei.de → xn--…) werden aufgelöst. */
        $e = (string) preg_replace(['~^mailto:~u', '~\s*[\[(]\s*(at|chiocciola)\s*[\])]\s*~u', '~\s*[\[(]\s*(dot|punto|punkt)\s*[\])]\s*~u'], ['', '@', '.'], $e);
        if (!preg_match('~[^\s<>()\[\],;:"\'/]+@[^\s<>()\[\],;:"\'/]+\.[^\s<>()\[\],;:"\'/]+~u', $e, $m)) { return null; }
        $e = rtrim($m[0], '.-_');
        if (filter_var($e, FILTER_VALIDATE_EMAIL)) { return $e; }
        [$lokal, $domain] = explode('@', $e, 2) + ['', ''];
        if (function_exists('idn_to_ascii') && $domain !== '' && preg_match('~[^\x00-\x7f]~', $domain)) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && filter_var($lokal . '@' . $ascii, FILTER_VALIDATE_EMAIL)) { return $lokal . '@' . $ascii; }
        }
        return null;
    }

    /* ==================================================================
       ERREICHBAR ODER AUSSORTIERT (06.10.2026, Uwe: „finde von allen die
       E-Mail-Adressen und zeige sie mit an, auch zukünftige — und die
       Betriebe, die keine E-Mail haben und kein WhatsApp, lösche raus“)

       WhatsApp heißt: eine eingetragene WhatsApp-Nummer, ein wa.me-Link auf
       der Website oder eine Handynummer (IT +39 3…, DE +49 15/16/17) --
       ob eine Nummer wirklich WhatsApp hat, sieht man von außen nicht,
       eine Handynummer ist die belastbare Näherung. Festnetz allein zählt nicht.

       Gelöscht wird nur, was niemanden sonst betrifft: nicht gesperrt (die
       Sperre muss stehen bleiben), Stufe neu oder qualifiziert, keine
       Einwilligung, Website schon geprüft, und kein Eintrag in einer der
       Bezugstabellen (Partner, Briefe, Versand, Termine, Antworten …).
       Eigene Daten (Audits, Befunde, Protokoll, Analysen, Signale, Fotos)
       gehen mit.
       ================================================================== */

    /** Handynummer in der normalisierten Form (+39 3…, +49 15/16/17 …). */
    public static function istHandy(?string $tel): bool
    {
        return (bool) preg_match('~^\+(393\d{8,9}|491[5-7]\d{8,9})$~', (string) $tel);
    }

    /** Die Nummer für WhatsApp: eingetragen, sonst die Handynummer, sonst null. */
    public static function whatsappNummer(array $f): ?string
    {
        $wa = trim((string) ($f['whatsapp'] ?? ''));
        if ($wa !== '') { return $wa; }
        $tel = (string) ($f['telefon'] ?? '');
        return self::istHandy($tel) ? $tel : null;
    }

    /** Hat der Betrieb eine E-Mail oder WhatsApp? */
    public static function erreichbar(array $f): bool
    {
        return trim((string) ($f['email'] ?? '')) !== '' || self::whatsappNummer($f) !== null;
    }

    /** Dasselbe für Rohdaten aus der Suche (noch nicht normalisiert). */
    public static function erreichbarRoh(array $roh): bool
    {
        $land = strtoupper((string) ($roh['land'] ?? ''));
        return self::normEmail($roh['email'] ?? null) !== null
            || trim((string) ($roh['whatsapp'] ?? '')) !== ''
            || self::istHandy(self::normTelefon($roh['telefon'] ?? null, $land));
    }

    /** Wo ein Betrieb sonst noch vorkommt -- dann bleibt er. Tabelle => Spalte. */
    public const BEZUG = [
        'akq_antworten' => 'firma_id', 'akq_briefe' => 'firma_id', 'akq_checks' => 'firma_id', 'akq_einwilligungen' => 'firma_id',
        'akq_folgen' => 'firma_id', 'akq_sperrliste' => 'firma_id', 'akq_termine' => 'firma_id', 'akq_versand' => 'firma_id',
        'akq_vorlagen' => 'firma_id', 'akq_wa_gespraeche' => 'firma_id', 'mk_demos' => 'akq_firma_id',
        'partner_briefwunsch' => 'firma_id', 'partner_leads' => 'firma_id', 'partner_reservierungen' => 'firma_id',
        'partner_tagesliste' => 'firma_id', 'partner_zugriffe' => 'firma_id', 'web_berichte' => 'firma_id',
        // Akquise-CRM (Migration 193): was ein Mensch dazu notiert hat, wird nie still aussortiert.
        'akq_notizen' => 'firma_id', 'akq_kontakte' => 'firma_id', 'akq_kanaele' => 'firma_id',
    ];

    /** Was mit dem Betrieb geht. */
    public const EIGENE = ['akq_befunde', 'akq_audits', 'akq_protokoll', 'akq_analysen', 'akq_signale'];

    /** Bedingung „darf aussortiert werden“ über f = akq_firmen. */
    public static function aussortierbarSql(): string
    {
        $w = ["f.gesperrt = 0", "f.kontakt_status IN ('neu','qualifiziert')", "COALESCE(f.einwilligung, '') = ''",
              "COALESCE(f.email, '') = ''", "COALESCE(f.whatsapp, '') = ''",
              "NOT (COALESCE(f.telefon, '') REGEXP '^[+](393[0-9]{8,9}|491[5-7][0-9]{8,9})$')",
              "f.audit_status IN ('fertig','fehler','keine_website','uebersprungen')"];
        if (self::spalteDa('akq_firmen', 'crm_stufe')) {   // von Hand gesetzt: Stufe, nächster Schritt, Sperrart, Mobil
            $w[] = "f.crm_stufe IS NULL AND f.naechster_schritt IS NULL AND f.sperr_art IS NULL AND COALESCE(f.mobil, '') = ''";
        }
        foreach (self::BEZUG as $t => $sp) {
            if (self::tabelleDa($t)) { $w[] = "NOT EXISTS (SELECT 1 FROM $t b WHERE b.$sp = f.id)"; }
        }
        return implode(' AND ', $w);
    }

    /** @var array<string,bool> */
    private static array $tabellen = [];

    private static function spalteDa(string $t, string $sp): bool
    {
        $k = $t . '.' . $sp;
        if (!isset(self::$tabellen[$k])) {
            try { self::$tabellen[$k] = Db::all("SHOW COLUMNS FROM $t LIKE " . Db::pdo()->quote($sp)) !== []; } catch (Throwable $e) { self::$tabellen[$k] = false; }
        }
        return self::$tabellen[$k];
    }

    private static function tabelleDa(string $t): bool
    {
        if (!isset(self::$tabellen[$t])) {
            try { Db::wert("SELECT 1 FROM $t LIMIT 1"); self::$tabellen[$t] = true; } catch (Throwable $e) { self::$tabellen[$t] = false; }
        }
        return self::$tabellen[$t];
    }

    public static function aussortierbarZahl(): int
    {
        return (int) Db::wert('SELECT COUNT(*) FROM akq_firmen f WHERE ' . self::aussortierbarSql());
    }

    /**
     * Löscht Betriebe ohne E-Mail und ohne WhatsApp (alle oder nur $nurId)
     * und merkt sich ihre Schlüssel. @return int gelöschte Betriebe
     */
    public static function aussortieren(?int $nurId = null, int $max = 5000): int
    {
        $sql = 'SELECT f.* FROM akq_firmen f WHERE ' . self::aussortierbarSql() . ($nurId !== null ? ' AND f.id = ?' : '') . ' ORDER BY f.id LIMIT ' . max(1, $max);
        $weg = 0;
        foreach (Db::all($sql, $nurId !== null ? [$nurId] : []) as $f) {
            $id = (int) $f['id'];
            $bilder = [];
            foreach (Db::all('SELECT screenshot_mobil, screenshot_desktop FROM akq_audits WHERE firma_id = ?', [$id]) as $au) {
                foreach ($au as $b) { if ((string) $b !== '') { $bilder[] = (string) $b; } }
            }
            Db::transaktion(static function () use ($id, $f): void {
                foreach (self::EIGENE as $t) { if (self::tabelleDa($t)) { Db::run("DELETE FROM $t WHERE firma_id = ?", [$id]); } }
                Db::run('DELETE FROM akq_firmen WHERE id = ?', [$id]);
                foreach (self::aussortierSchluessel($f) as $k) {
                    Db::run('INSERT IGNORE INTO akq_aussortiert (schluessel, name) VALUES (?, ?)', [$k, mb_substr((string) $f['name'], 0, 190)]);
                }
            }, 3);
            foreach (array_unique($bilder) as $bild) {
                if (preg_match('~^[A-Za-z0-9._-]+$~', $bild)) {
                    try { require_once __DIR__ . '/Ablage.php'; @unlink(Ablage::ordner() . '/akquise/' . $bild); } catch (Throwable $e) { }
                }
            }
            $weg++;
        }
        if ($weg > 0) {
            self::protokoll(null, 'aussortiert', $weg . ' Betrieb' . ($weg === 1 ? '' : 'e') . ' ohne E-Mail und ohne WhatsApp gelöscht');
        }
        return $weg;
    }

    /** @return list<string> */
    public static function aussortierSchluessel(array $f): array
    {
        $k = [];
        if (trim((string) ($f['quelle'] ?? '')) !== '') { $k[] = 'q:' . mb_substr((string) $f['quelle'], 0, 180); }
        $dom = (string) ($f['domain'] ?? '') !== '' ? (string) $f['domain'] : (string) (self::normDomain($f['url'] ?? null) ?? '');
        if ($dom !== '' && !self::istPlattform($dom)) { $k[] = 'd:' . mb_substr($dom, 0, 180); }
        $nn = (string) ($f['name_norm'] ?? '') !== '' ? (string) $f['name_norm'] : self::normName((string) ($f['name'] ?? ''));
        if ($nn !== '' && trim((string) ($f['plz'] ?? '')) !== '') { $k[] = 'n:' . mb_substr($nn . '|' . trim((string) $f['plz']), 0, 180); }
        return $k;
    }

    /** Schon einmal aussortiert? */
    public static function warAussortiert(array $roh): bool
    {
        return self::aussortiertGrund($roh) !== null;
    }

    /** Warum draußen (Kunden finden, 07.10.2026)? null = nie aussortiert. Der stärkste Grund gewinnt. */
    public static function aussortiertGrund(array $roh): ?string
    {
        $k = self::aussortierSchluessel($roh);
        if (!$k || !self::tabelleDa('akq_aussortiert')) { return null; }
        $in = implode(',', array_fill(0, count($k), '?'));
        if (!self::spalteDa('akq_aussortiert', 'grund')) {
            return (int) Db::wert("SELECT COUNT(*) FROM akq_aussortiert WHERE schluessel IN ($in)", $k) > 0 ? 'ohne_kontakt' : null;
        }
        $g = array_column(Db::all("SELECT grund FROM akq_aussortiert WHERE schluessel IN ($in)", $k), 'grund');
        if (!$g) { return null; }
        $anders = array_values(array_diff($g, ['ohne_kontakt']));
        return $anders ? (string) $anders[0] : 'ohne_kontakt';
    }

    /** Wieder aufnehmen: Schlüssel vergessen (jetzt mit Kontaktweg gefunden) — nur „ohne Kontaktweg“; Kunde, kein Interesse,
        abgemeldet usw. bleiben draußen. */
    public static function aussortiertVergessen(array $roh): void
    {
        $k = self::aussortierSchluessel($roh);
        if ($k && self::tabelleDa('akq_aussortiert')) {
            Db::run('DELETE FROM akq_aussortiert WHERE schluessel IN (' . implode(',', array_fill(0, count($k), '?')) . ')'
                . (self::spalteDa('akq_aussortiert', 'grund') ? " AND grund = 'ohne_kontakt'" : ''), $k);
        }
    }

    private static function still(callable $fn): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return null; }
    }

    /** Herkunft einer gefundenen Adresse (06.10.2026). */
    public static function mailQuelle(?string $quelle): string
    {
        require_once __DIR__ . '/AkquiseMail.php';
        return mb_substr(AkquiseMail::quelleAus($quelle), 0, 255);
    }

    /** Schreiben mit email_source -- vor Migration 192 fehlt die Spalte: dann ohne. */
    public static function mitMailQuelle(callable $mit, array $daten, callable $ohne): void
    {
        try { $mit(); }
        catch (PDOException $e) {
            if (!array_key_exists('email_source', $daten) || !str_contains($e->getMessage(), 'email_source')) { throw $e; }
            unset($daten['email_source']);
            $ohne($daten);
        }
    }

    /** L-XXXXXXXX aus einem Alphabet ohne 0/O und 1/I -- am Telefon buchstabierbar. */
    public static function neueKennung(): string
    {
        $abc = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $k = 'L-';
        for ($i = 0; $i < 8; $i++) { $k .= $abc[random_int(0, 31)]; }
        return $k;
    }

    /* ================================================================== */
    /*  Protokoll                                                         */
    /* ================================================================== */

    public static function protokoll(?int $firmaId, string $schritt, string $text, array $meta = [], ?int $laufId = null): void
    {
        try {
            Db::insert('akq_protokoll', [
                'firma_id' => $firmaId, 'lauf_id' => $laufId, 'schritt' => mb_substr($schritt, 0, 40),
                'text' => mb_substr($text, 0, 500),
                'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'actor' => Auth::angemeldet() ? Auth::name() : 'System',
            ]);
        } catch (Throwable $e) {
            /* Ein Protokolleintrag darf nie den Vorgang selbst umwerfen. */
        }
    }

    /* ================================================================== */
    /*  Dubletten                                                         */
    /* ================================================================== */

    /**
     * Gibt es die Firma schon? Liefert [id, grund] oder null.
     *
     * @param array<string,mixed> $d bereits normalisierte Felder
     */
    public static function dubletteFinden(array $d): ?array
    {
        if (!empty($d['domain'])) {
            $id = Db::wert('SELECT id FROM akq_firmen WHERE domain = ?', [$d['domain']], null);
            if ($id !== null) { return [(int) $id, 'domain']; }
        }
        if (!empty($d['quelle'])) {
            $id = Db::wert('SELECT id FROM akq_firmen WHERE quelle = ?', [$d['quelle']], null);
            if ($id !== null) { return [(int) $id, 'quelle']; }
        }
        /* Kunden finden (07.10.2026): dieselbe Partita IVA oder dieselbe Firmen-Mail ist derselbe Betrieb. */
        if (!empty($d['piva'])) {
            $id = self::still(static fn() => Db::wert('SELECT id FROM akq_firmen WHERE piva = ? LIMIT 1', [$d['piva']], null));
            if ($id !== null) { return [(int) $id, 'piva']; }
        }
        if (!empty($d['email'])) {
            $id = Db::wert('SELECT id FROM akq_firmen WHERE email = ? LIMIT 1', [$d['email']], null);
            if ($id !== null) { return [(int) $id, 'email']; }
        }
        if (!empty($d['name_norm']) && !empty($d['plz'])) {
            $id = Db::wert('SELECT id FROM akq_firmen WHERE name_norm = ? AND plz = ? LIMIT 1',
                           [$d['name_norm'], $d['plz']], null);
            if ($id !== null) { return [(int) $id, 'name_plz']; }
        }
        if (!empty($d['name_norm']) && !empty($d['stadt']) && empty($d['plz'])) {
            $id = Db::wert('SELECT id FROM akq_firmen WHERE name_norm = ? AND stadt = ? LIMIT 1',
                           [$d['name_norm'], $d['stadt']], null);
            if ($id !== null) { return [(int) $id, 'name_ort']; }
        }
        if (!empty($d['adresse_norm'])) {
            /* Gleiche Adresse allein reicht nicht -- in einem Haus sitzen oft
               Bar und Friseur. Erst mit aehnlichem Namen ist es dieselbe Firma. */
            foreach (Db::all('SELECT id, name_norm FROM akq_firmen WHERE adresse_norm = ? LIMIT 20', [$d['adresse_norm']]) as $z) {
                similar_text((string) $z['name_norm'], (string) ($d['name_norm'] ?? ''), $prozent);
                if ($prozent >= 70) { return [(int) $z['id'], 'adresse']; }
            }
        }
        return null;
    }

    /**
     * Nimmt eine gefundene Firma an. Neu anlegen oder ergaenzen.
     *
     * Ergaenzt werden NUR leere Felder. Was Uwe von Hand korrigiert hat,
     * ueberschreibt keine spaetere Recherche -- sonst stuende nach jedem
     * Lauf wieder die falsche Telefonnummer aus OpenStreetMap drin.
     *
     * @param array<string,mixed> $roh
     * @return array{id:int,neu:bool,grund:?string,gesperrt:bool}
     */
    public static function firmaMelden(array $roh, ?int $laufId = null): array
    {
        [$d, $plattform, $domain] = self::normalisieren($roh);
        $r = self::firmaUebernehmen($d, $laufId, $plattform, $domain);
        /* Kunden finden (07.10.2026): schon Kunde? mögliche Dublette? — markieren, nie still verwerfen. */
        try { require_once __DIR__ . '/KundenFinden.php'; KundenFinden::abgleichen((int) $r['id']); } catch (Throwable $e) { }
        return $r;
    }

    /**
     * Rohdaten → Felder von akq_firmen. @return array{0:array<string,mixed>,1:bool,2:?string} [Felder, Plattform?, Domain]
     */
    public static function normalisieren(array $roh): array
    {
        $name = trim((string) ($roh['name'] ?? ''));
        $land = strtoupper(trim((string) ($roh['land'] ?? '')));
        if ($name === '' || !in_array($land, ['DE', 'IT'], true)) {
            throw new InvalidArgumentException('Firma ohne Namen oder mit unbekanntem Land.');
        }
        $url = trim((string) ($roh['url'] ?? ''));
        $domain = self::normDomain($url);
        $plattform = self::istPlattform($domain);
        $branche = (string) ($roh['branche'] ?? '');
        if ($branche !== '' && !isset(self::branchen()[$branche])) { $branche = ''; }

        $d = [
            'name'            => mb_substr($name, 0, 190),
            'name_norm'       => mb_substr(self::normName($name), 0, 190),
            'domain'          => $plattform ? null : $domain,
            'url'             => $url !== '' ? mb_substr($url, 0, 500) : null,
            'land'            => $land,
            'region'          => self::kurz($roh['region'] ?? null, 120),
            'kreis'           => self::kurz($roh['kreis'] ?? null, 120),
            'stadt'           => self::kurz($roh['stadt'] ?? null, 120),
            'plz'             => self::kurz($roh['plz'] ?? null, 10),
            'adresse'         => self::kurz($roh['adresse'] ?? null, 255),
            'adresse_norm'    => self::normAdresse($roh['adresse'] ?? null, $roh['plz'] ?? null),
            'lat'             => isset($roh['lat']) && is_numeric($roh['lat']) ? round((float) $roh['lat'], 6) : null,
            'lon'             => isset($roh['lon']) && is_numeric($roh['lon']) ? round((float) $roh['lon'], 6) : null,
            'branche'         => $branche !== '' ? $branche : null,
            'unternehmensart' => self::kurz($roh['unternehmensart'] ?? null, 80),
            'telefon'         => self::kurz(self::normTelefon($roh['telefon'] ?? null, $land), 40),
            'email'           => self::normEmail($roh['email'] ?? null),
            'ansprechpartner' => self::kurz($roh['ansprechpartner'] ?? null, 120),
            'tourismus'       => $branche !== '' && !empty(self::branchen()[$branche]['tourismus']) ? 1 : 0,
            'quelle'          => self::kurz($roh['quelle'] ?? null, 80),
            'quelle_lizenz'   => self::kurz($roh['quelle_lizenz'] ?? null, 60),
        ];
        /* Kunden finden (07.10.2026): Partita IVA und „neu auf OpenStreetMap“ (Version 1 des Eintrags). */
        if (self::spalteDa('akq_firmen', 'piva')) {
            require_once __DIR__ . '/KundenFinden.php';
            $d['piva'] = KundenFinden::normPiva($roh['piva'] ?? null);
            $osm = (string) ($roh['osm_zeit'] ?? '');
            $d['osm_neu_am'] = (int) ($roh['osm_version'] ?? 0) === 1 && preg_match('~^\d{4}-\d{2}-\d{2}~', $osm) ? substr($osm, 0, 10) : null;
        }
        return [$d, $plattform, $domain];
    }

    /** @return array{id:int,neu:bool,grund:?string,gesperrt:bool} */
    private static function firmaUebernehmen(array $d, ?int $laufId, bool $plattform, ?string $domain): array
    {
        return Db::nochmal(static function () use ($d, $laufId, $plattform, $domain) {
            $treffer = self::dubletteFinden($d);
            if ($treffer !== null) {
                [$id, $grund] = $treffer;
                $alt = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]) ?? [];
                $neu = [];
                foreach ($d as $feld => $wert) {
                    if ($wert === null || $wert === '' || in_array($feld, ['name', 'name_norm'], true)) { continue; }
                    if (($alt[$feld] ?? null) === null || ($alt[$feld] ?? '') === '') {
                        // Eine Domain, die schon einer anderen Firma gehoert,
                        // wird nicht nachgetragen -- der eindeutige Schluessel
                        // wuerde es ohnehin ablehnen.
                        if ($feld === 'domain' && Db::wert('SELECT id FROM akq_firmen WHERE domain = ? AND id <> ?', [$wert, $id], null) !== null) { continue; }
                        if ($feld === 'quelle' && Db::wert('SELECT id FROM akq_firmen WHERE quelle = ? AND id <> ?', [$wert, $id], null) !== null) { continue; }
                        $neu[$feld] = $wert;
                    }
                }
                if (isset($neu['email'])) { $neu['email_source'] = self::mailQuelle($d['quelle']); }
                if ($neu) { self::mitMailQuelle(static fn() => Db::update('akq_firmen', $id, $neu), $neu, static fn($n) => Db::update('akq_firmen', $id, $n)); }
                self::protokoll($id, 'dublette', 'Erneut gefunden (' . $grund . ') — nur leere Felder ergänzt',
                    ['ergaenzt' => array_keys($neu)], $laufId);
                return ['id' => $id, 'neu' => false, 'grund' => $grund, 'gesperrt' => (int) ($alt['gesperrt'] ?? 0) === 1];
            }

            $d['kennung'] = self::neueKennung();
            if ($d['email'] !== null) { $d['email_source'] = self::mailQuelle($d['quelle']); }
            if ($d['url'] === null) {
                $d['audit_status'] = 'keine_website';
            } elseif ($plattform) {
                $d['audit_status'] = 'keine_website';
            }
            $id = Db::insert('akq_firmen', $d);

            require_once __DIR__ . '/AkquiseGate.php';
            $gesperrt = AkquiseGate::trifftSperrliste(Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]) ?? []);
            if ($gesperrt !== null) {
                Db::update('akq_firmen', $id, ['gesperrt' => 1, 'kontakt_status' => 'gesperrt', 'compliance_status' => 'DO_NOT_EMAIL']);
            } else {
                // Sofort einstufen -- auch Firmen ohne Website, die nie ein Audit bekommen.
                AkquiseGate::statusSpeichern($id);
            }
            self::protokoll($id, 'gefunden', 'Firma gefunden' . ($d['quelle'] ? ' (' . $d['quelle'] . ')' : ''), [], $laufId);
            if ($d['url'] !== null) {
                self::protokoll($id, 'website', $plattform
                    ? 'Nur Plattform-Auftritt erkannt: ' . $domain
                    : 'Website erkannt: ' . $domain, [], $laufId);
            }
            if ($gesperrt !== null) {
                self::protokoll($id, 'gesperrt', 'Steht auf der Sperrliste (' . $gesperrt . ') — wird nie angesprochen', [], $laufId);
            }
            return ['id' => $id, 'neu' => true, 'grund' => null, 'gesperrt' => $gesperrt !== null];
        }, 'uq_akq_');
    }

    private static function kurz(mixed $w, int $max): ?string
    {
        $s = trim((string) ($w ?? ''));
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    /* ================================================================== */
    /*  Audits                                                            */
    /* ================================================================== */

    /**
     * Welche Websites als naechstes geprueft werden.
     *
     * Zuerst nie gepruefte, dann solche, deren Pruefung laenger als
     * NEUPRUEFUNG_TAGE zurueckliegt. Gesperrte nie -- wer nicht angesprochen
     * werden will, dessen Seite braucht auch kein Gutachten.
     */
    /** Ab so vielen geprüften Websites entsteht eine Branchen-Seite (= BranchenStatistik::MIN). */
    public const GRUPPE_MIN = 15;

    public static function naechsteAudits(int $anzahl = 10): array
    {
        $anzahl = max(1, min(50, $anzahl));
        /* "laeuft" seit ueber zwei Stunden heisst: Der Worker ist dabei
           abgestuerzt. Dann darf ein neuer Lauf die Firma wieder nehmen. */
        Db::run("UPDATE akq_firmen SET audit_status = 'offen'
                  WHERE audit_status = 'laeuft' AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        /* Gezielt nach Branche + Ort (29.09.2026, Uwe: Ja): zuerst die größten
           Gruppen, die noch keine 15 geprüften Websites haben -- dann entsteht
           die Branchen-Seite (W1) nach wenigen Nächten statt verstreut über
           Monate. Eine GROUP-BY-Abfrage je Abruf, danach wie bisher. */
        // Wer über den Website-Check die ausführliche Analyse will, geht allem vor
        $zeilen = Db::all("SELECT f.id, f.kennung, f.name, f.url, f.domain, f.land, f.branche, f.stadt, f.sprache, f.tourismus
                             FROM akq_firmen f JOIN akq_checks c ON c.firma_id = f.id AND c.ausfuehrlich = 1 AND c.status = 'neu'
                            WHERE f.gesperrt = 0 AND f.url IS NOT NULL AND f.domain IS NOT NULL AND f.audit_status = 'offen'
                         GROUP BY f.id ORDER BY MIN(c.id) LIMIT " . $anzahl);
        try {
            $ziele = Db::all("SELECT land, branche, stadt, SUM(audit_status = 'fertig') AS fertig, SUM(audit_status = 'laeuft') AS laeuft
                                FROM akq_firmen
                               WHERE gesperrt = 0 AND url IS NOT NULL AND domain IS NOT NULL AND branche IS NOT NULL AND stadt IS NOT NULL AND stadt <> ''
                            GROUP BY land, branche, stadt
                              HAVING COUNT(*) >= ? AND fertig < ? AND SUM(audit_status = 'offen') > 0
                            ORDER BY COUNT(*) DESC LIMIT 12", [self::GRUPPE_MIN, self::GRUPPE_MIN]);
            foreach ($ziele as $zg) {
                if (count($zeilen) >= $anzahl) { break; }
                $weg = array_map(static fn($z) => (int) $z['id'], $zeilen);
                $fehlt = min($anzahl - count($zeilen), self::GRUPPE_MIN + 3 - (int) $zg['fertig'] - (int) $zg['laeuft']);
                if ($fehlt < 1) { continue; }   // drei mehr, falls Websites tot sind
                $zeilen = array_merge($zeilen, Db::all(
                    "SELECT id, kennung, name, url, domain, land, branche, stadt, sprache, tourismus
                       FROM akq_firmen
                      WHERE gesperrt = 0 AND url IS NOT NULL AND domain IS NOT NULL AND audit_status = 'offen'
                        AND land = ? AND branche = ? AND stadt = ?" . ($weg ? ' AND id NOT IN (' . implode(',', $weg) . ')' : '') . "
                   ORDER BY recherchiert_am ASC, id ASC LIMIT " . max(1, (int) $fehlt), [$zg['land'], $zg['branche'], $zg['stadt']]));
            }
        } catch (Throwable $e) { $zeilen = []; }
        $schon = array_map(static fn($z) => (int) $z['id'], $zeilen);
        $rest = $anzahl - count($zeilen);
        $zeilen = array_merge($zeilen, $rest <= 0 ? [] : Db::all(
            "SELECT id, kennung, name, url, domain, land, branche, stadt, sprache, tourismus
               FROM akq_firmen
              WHERE gesperrt = 0 AND url IS NOT NULL AND domain IS NOT NULL" . ($schon ? ' AND id NOT IN (' . implode(',', $schon) . ')' : '') . "
                AND (audit_status = 'offen'
                     OR (audit_status = 'fertig' AND geprueft_am < DATE_SUB(NOW(), INTERVAL " . self::NEUPRUEFUNG_TAGE . " DAY))
                     -- Ein Fehler ist oft voruebergehend (Seite kurz weg, Zeitueberschreitung):
                     -- naechster Versuch nach drei Stunden, also im naechsten Nachtlauf.
                     -- Hoechstens drei Fehlversuche in zwei Wochen, dann erst wieder nach der Frist oben.
                     OR (audit_status = 'fehler' AND updated_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)
                         AND (SELECT COUNT(*) FROM akq_audits a WHERE a.firma_id = akq_firmen.id AND a.status = 'fehler'
                                AND a.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)) < 3))
              ORDER BY (audit_status = 'offen') DESC,
                       -- Wer über den Website-Check die ausführliche Analyse will, wartet nicht hinter der Recherche.
                       EXISTS (SELECT 1 FROM akq_checks c WHERE c.firma_id = akq_firmen.id AND c.ausfuehrlich = 1 AND c.status = 'neu') DESC,
                       recherchiert_am ASC, id ASC
              LIMIT $rest"));
        foreach ($zeilen as $z) {
            Db::update('akq_firmen', (int) $z['id'], ['audit_status' => 'laeuft']);
        }
        return $zeilen;
    }

    /**
     * Nimmt ein Audit-Ergebnis an.
     *
     * Der Score wird HIER gerechnet, nicht im Worker. Der Worker liefert
     * Befunde; welche Gewichtung gilt, entscheidet die Verwaltung. So gibt es
     * genau eine Stelle, an der man die Gewichte aendert, und alte Audits
     * lassen sich mit neuen Gewichten nachrechnen.
     *
     * @param array<string,mixed> $e
     */
    public static function auditMelden(int $firmaId, array $e): array
    {
        require_once __DIR__ . '/AkquiseScore.php';
        require_once __DIR__ . '/AkquiseGate.php';

        $firma = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$firma) { throw new RuntimeException('Firma ' . $firmaId . ' gibt es nicht.'); }

        $status = (string) ($e['status'] ?? 'fertig');
        if (!in_array($status, ['fertig', 'fehler', 'keine_website', 'uebersprungen'], true)) { $status = 'fertig'; }
        $befunde = [];
        foreach ((array) ($e['befunde'] ?? []) as $b) {
            if (!is_array($b)) { continue; }
            $kat = (string) ($b['kategorie'] ?? '');
            if (!isset(self::KATEGORIEN[$kat])) { continue; }
            $titel = trim((string) ($b['titel'] ?? ''));
            $code = preg_replace('~[^a-z0-9_]~', '', strtolower((string) ($b['code'] ?? ''))) ?? '';
            if ($titel === '' || $code === '') { continue; }
            $st = strtoupper((string) ($b['status'] ?? 'VERIFIED'));
            $befunde[] = [
                'kategorie'    => $kat,
                'code'         => mb_substr($code, 0, 60),
                'schwere'      => max(1, min(5, (int) ($b['schwere'] ?? 2))),
                'titel'        => mb_substr($titel, 0, 255),
                'beschreibung' => self::kurz($b['beschreibung'] ?? null, 4000),
                'wirkung'      => self::kurz($b['wirkung'] ?? null, 2000),
                'url'          => self::kurz($b['url'] ?? null, 500),
                'messwert'     => isset($b['messwert']) ? json_encode($b['messwert'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'beleg'        => self::kurz($b['beleg'] ?? null, 4000),
                'screenshot'   => self::kurz($b['screenshot'] ?? null, 120),
                'status'       => in_array($st, ['VERIFIED', 'UNVERIFIED'], true) ? $st : 'UNVERIFIED',
            ];
        }

        $rechnung = AkquiseScore::berechnen($befunde, (string) ($firma['branche'] ?? ''));
        $jetzt = date('Y-m-d H:i:s');

        $auditId = (int) Db::transaktion(static function () use ($firmaId, $e, $befunde, $rechnung, $status, $jetzt) {
            $auditId = Db::insert('akq_audits', [
                'firma_id'         => $firmaId,
                'gestartet_am'     => self::zeit($e['gestartet_am'] ?? null) ?? $jetzt,
                'beendet_am'       => self::zeit($e['beendet_am'] ?? null) ?? $jetzt,
                'status'           => $status,
                'worker_version'   => self::kurz($e['worker_version'] ?? null, 20),
                'geprueft_url'     => self::kurz($e['geprueft_url'] ?? null, 500),
                'seiten'           => max(0, min(65535, (int) ($e['seiten'] ?? 0))),
                'messwerte'        => isset($e['messwerte']) ? json_encode($e['messwerte'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'teilwerte'        => json_encode($rechnung['teile'], JSON_UNESCAPED_UNICODE),
                'score'            => $status === 'fertig' ? $rechnung['score'] : null,
                'ki'               => isset($e['ki']) ? json_encode($e['ki'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ki_modell'        => self::kurz($e['ki_modell'] ?? null, 60),
                'loesung'          => self::kurz($e['loesung'] ?? null, 4000),
                'experience'       => self::kurz($e['experience'] ?? null, 4000),
                'screenshot_mobil'   => self::kurz($e['screenshot_mobil'] ?? null, 120),
                'screenshot_desktop' => self::kurz($e['screenshot_desktop'] ?? null, 120),
            ]);
            foreach ($befunde as $b) {
                Db::insert('akq_befunde', $b + ['audit_id' => $auditId, 'firma_id' => $firmaId, 'erkannt_am' => $jetzt]);
            }
            return $auditId;
        }, 3);
        /* Markierungen auf dem Handyfoto (28.09.2026, A2): nur geprüfte Zahlen, höchstens sechs. Vor Migration 102 fehlt die Spalte. */
        if (!empty($e['marken']) && is_array($e['marken'])) {
            $marken = [];
            foreach (array_slice($e['marken'], 0, 6) as $m) {
                if (!is_array($m) || !preg_match('~^[a-z]{3,12}$~', (string) ($m['art'] ?? ''))) { continue; }
                $marken[] = ['art' => (string) $m['art'], 'x' => round((float) ($m['x'] ?? 0), 1), 'y' => round((float) ($m['y'] ?? 0), 1), 'b' => round((float) ($m['b'] ?? 0), 1), 'h' => round((float) ($m['h'] ?? 0), 1)];
            }
            if ($marken) { try { Db::update('akq_audits', $auditId, ['marken' => json_encode($marken)]); } catch (Throwable $x) { } }
        }

        /* Öffnungszeiten laut Website (29.09.2026, D3) -- nur geprüfte Werte; vor Migration 107 fehlt die Spalte. */
        $oz = self::oeffnungPruefen($e['oeffnungszeiten'] ?? null);
        if ($oz !== null) { try { Db::update('akq_firmen', $firmaId, ['oeffnungszeiten' => json_encode($oz)]); } catch (Throwable $x) { } }

        $top = array_map(static fn($b) => $b['titel'], array_slice(AkquiseScore::topBefunde($befunde), 0, 3));
        $upd = [
            'geprueft_am'  => $jetzt,
            'audit_status' => $status,
            'top_probleme' => json_encode($top, JSON_UNESCAPED_UNICODE),
        ];
        if ($status === 'fertig') {
            $upd['score'] = $rechnung['score'];
            $upd['score_stufe'] = $rechnung['stufe'];
            if ((string) $firma['kontakt_status'] === 'neu' && $rechnung['score'] >= 51) {
                $upd['kontakt_status'] = 'qualifiziert';
            }
        }
        foreach (['sprache' => 2, 'email' => 190, 'telefon' => 40, 'whatsapp' => 40, 'ansprechpartner' => 120] as $feld => $max) {
            // Was der Worker auf der Website gefunden hat, fuellt nur Luecken.
            // WhatsApp (06.10.2026): die Nummer aus einem wa.me-Link der Website.
            $wert = $feld === 'email' ? self::normEmail($e[$feld] ?? null)
                  : (in_array($feld, ['telefon', 'whatsapp'], true) ? self::normTelefon($e[$feld] ?? null, (string) $firma['land']) : self::kurz($e[$feld] ?? null, $max));
            if ($wert !== null && ($firma[$feld] ?? null) === null) { $upd[$feld] = $wert; }
        }
        if (isset($upd['email'])) { $upd['email_source'] = 'Website: ' . mb_substr((string) ($e['geprueft_url'] ?? $firma['url'] ?? ''), 0, 200); }
        /* Kunden finden (07.10.2026): Partita IVA und betreuende Agentur aus der Fußzeile. */
        if (self::spalteDa('akq_firmen', 'piva')) {
            require_once __DIR__ . '/KundenFinden.php';
            $pv = KundenFinden::normPiva($e['piva'] ?? null);
            if ($pv !== null && empty($firma['piva'])) { $upd['piva'] = $pv; }
            $ag = self::kurz($e['agentur'] ?? null, 120);
            if ($ag !== null && !preg_match('~vecom~i', $ag)) { $upd['agentur'] = $ag; }
        }
        self::mitMailQuelle(static fn() => Db::update('akq_firmen', $firmaId, $upd), $upd, static fn($n) => Db::update('akq_firmen', $firmaId, $n));
        if (isset($upd['piva']) || isset($upd['email']) || isset($upd['telefon'])) {
            try { require_once __DIR__ . '/KundenFinden.php'; KundenFinden::abgleichen($firmaId); } catch (Throwable $x) { }
        }

        self::protokoll($firmaId, 'audit', 'Audit abgeschlossen (' . self::AUDIT_STATUS[$status] . ')',
            ['audit_id' => $auditId, 'seiten' => (int) ($e['seiten'] ?? 0)]);
        if ($befunde) {
            $verifiziert = count(array_filter($befunde, static fn($b) => $b['status'] === 'VERIFIED'));
            self::protokoll($firmaId, 'befunde', count($befunde) . ' Befunde erkannt, davon ' . $verifiziert . ' belegt',
                ['audit_id' => $auditId]);
        }
        if ($status === 'fertig') {
            self::protokoll($firmaId, 'score', 'Score berechnet: ' . $rechnung['score'] . ' (' . AkquiseScore::STUFEN[$rechnung['stufe']] . ')',
                ['teile' => $rechnung['teile']]);
        }
        AkquiseGate::statusSpeichern($firmaId);
        return ['audit_id' => $auditId, 'score' => $rechnung['score'], 'stufe' => $rechnung['stufe'], 'befunde' => count($befunde)];
    }

    /**
     * Öffnungszeiten vom Worker: {quelle, zeiten:[{t,v,b}]} → {q, z, am} oder null.
     * Alles, was nicht genau passt, fällt weg -- ein falsches „jetzt geöffnet“
     * ist schlimmer als keins.
     */
    public static function oeffnungPruefen(mixed $o): ?array
    {
        if (!is_array($o) || !is_array($o['zeiten'] ?? null)) { return null; }
        $z = [];
        foreach (array_slice($o['zeiten'], 0, 14) as $x) {
            if (!is_array($x) || !is_array($x['t'] ?? null)) { continue; }
            $tage = array_values(array_unique(array_filter(array_map('intval', $x['t']), static fn($d) => $d >= 1 && $d <= 7)));
            sort($tage);
            $v = (string) ($x['v'] ?? ''); $b = (string) ($x['b'] ?? '');
            if (!$tage || !preg_match('~^([01]\d|2[0-3]):[0-5]\d$~', $v) || !preg_match('~^([01]\d|2[0-3]):[0-5]\d$~', $b)) { continue; }
            $z[] = ['t' => $tage, 'v' => $v, 'b' => $b];
        }
        if (!$z) { return null; }
        return ['q' => ($o['quelle'] ?? '') === 'daten' ? 'daten' : 'text', 'z' => $z, 'am' => date('Y-m-d')];
    }

    private static function zeit(mixed $w): ?string
    {
        $s = trim((string) ($w ?? ''));
        if ($s === '') { return null; }
        $t = strtotime($s);
        return $t === false ? null : date('Y-m-d H:i:s', $t);
    }

    /**
     * Rechnet einen Audit neu -- nach einem verworfenen Befund oder mit
     * geaenderten Gewichten. Nur der letzte Audit einer Firma schreibt auf
     * die Firma durch; aeltere bleiben Beleg ihres Zeitpunkts.
     */
    public static function neuBewerten(int $auditId): array
    {
        require_once __DIR__ . '/AkquiseScore.php';
        $a = Db::one('SELECT * FROM akq_audits WHERE id = ?', [$auditId]);
        if (!$a) { throw new RuntimeException('Audit nicht gefunden.'); }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $a['firma_id']]) ?? [];
        $befunde = self::befunde($auditId);
        $r = AkquiseScore::berechnen($befunde, (string) ($f['branche'] ?? ''));
        Db::update('akq_audits', $auditId, ['score' => $a['status'] === 'fertig' ? $r['score'] : null,
            'teilwerte' => json_encode($r['teile'], JSON_UNESCAPED_UNICODE)]);
        $letzter = self::letzterAudit((int) $a['firma_id']);
        if ($letzter && (int) $letzter['id'] === $auditId && $a['status'] === 'fertig') {
            $top = array_map(static fn($b) => $b['titel'], array_slice(AkquiseScore::topBefunde($befunde), 0, 3));
            $upd = ['score' => $r['score'], 'score_stufe' => $r['stufe'], 'top_probleme' => json_encode($top, JSON_UNESCAPED_UNICODE)];
            if (($f['kontakt_status'] ?? '') === 'neu' && $r['score'] >= 51) { $upd['kontakt_status'] = 'qualifiziert'; }
            Db::update('akq_firmen', (int) $a['firma_id'], $upd);
            self::protokoll((int) $a['firma_id'], 'score', 'Score neu berechnet: ' . $r['score']);
        }
        return $r;
    }

    public static function letzterAudit(int $firmaId): ?array
    {
        return Db::one("SELECT * FROM akq_audits WHERE firma_id = ? ORDER BY id DESC LIMIT 1", [$firmaId]);
    }

    /** @return list<array<string,mixed>> */
    public static function befunde(int $auditId): array
    {
        return Db::all("SELECT * FROM akq_befunde WHERE audit_id = ? AND status <> 'VERWORFEN'
                         ORDER BY FIELD(status,'VERIFIED','UNVERIFIED'), schwere DESC, id", [$auditId]);
    }

    /* ================================================================== */
    /*  Liste mit Filtern                                                 */
    /* ================================================================== */

    public const SORTIERUNG = [
        'score'   => 'f.score DESC, f.id DESC',
        'neu'     => 'f.id DESC',
        'geprueft'=> 'f.geprueft_am DESC',
        'name'    => 'f.name ASC',
        'prio'    => 'f.prio_score IS NULL, f.prio_score DESC, f.id DESC',   // Akquise-CRM (06.10.2026)
    ];

    /**
     * @param array<string,mixed> $f Filter aus der Adresszeile
     * @return array{zeilen:list<array>,gesamt:int,seite:int,seiten:int}
     */
    public static function liste(array $f, int $seite = 1, int $proSeite = 50): array
    {
        $wo = ['1=1'];
        $args = [];
        $gleich = ['land' => 'f.land', 'region' => 'f.region', 'kreis' => 'f.kreis', 'stadt' => 'f.stadt',
                   'branche' => 'f.branche', 'compliance' => 'f.compliance_status',
                   'audit' => 'f.audit_status', 'stufe' => 'f.score_stufe'];
        // Kontakt: fuenf Stufen auf dem Bildschirm, mehrere Zustaende dahinter.
        $k5 = (string) ($f['kontakt'] ?? '');
        if (isset(self::STUFEN5[$k5])) {
            $werte = self::STUFEN5[$k5][1];
            $wo[] = 'f.kontakt_status IN (' . implode(',', array_fill(0, count($werte), '?')) . ')';
            array_push($args, ...$werte);
        } elseif ($k5 !== '' && isset(self::KONTAKT_STATUS[$k5])) {
            $wo[] = 'f.kontakt_status = ?'; $args[] = $k5;
        }
        if (!empty($f['stark'])) { $wo[] = 'f.score >= 71'; }
        /* Akquise-CRM (06.10.2026): Priorität und vorhandene Kontaktwege — „alle Dachdecker in Mainz über 80 mit E-Mail“. */
        if (isset($f['prio_min']) && $f['prio_min'] !== '' && is_numeric($f['prio_min'])) { $wo[] = 'f.prio_score >= ?'; $args[] = (int) $f['prio_min']; }
        if (!empty($f['prio']) && in_array($f['prio'], ['jetzt', 'gut', 'spaeter', 'niedrig', 'nie'], true)) { $wo[] = 'f.prio_stufe = ?'; $args[] = (string) $f['prio']; }
        if (!empty($f['mit_email'])) { $wo[] = "(f.email IS NOT NULL AND f.email <> '')"; }
        if (!empty($f['mit_whatsapp'])) { $wo[] = "((f.whatsapp IS NOT NULL AND f.whatsapp <> '') OR (f.mobil IS NOT NULL AND f.mobil <> ''))"; }
        /* Schnellfilter (29.09.2026, K1): wer hat zugestimmt, wer hat keine Website */
        if (!empty($f['darf'])) { $wo[] = "(f.einwilligung IS NOT NULL AND f.einwilligung <> '')"; }
        if (!empty($f['ohne_web'])) { $wo[] = "(f.url IS NULL OR f.url = '')"; }
        /* Beim Partner (03.10.2026, Uwe): wer telefoniert diesen Betrieb gerade ab */
        if (!empty($f['partner'])) {
            if (ctype_digit((string) $f['partner'])) {
                $wo[] = 'EXISTS (SELECT 1 FROM partner_reservierungen r WHERE r.firma_id = f.id AND r.bis >= CURDATE() AND r.partner_id = ?)';
                $args[] = (int) $f['partner'];
            } else {
                $wo[] = 'EXISTS (SELECT 1 FROM partner_reservierungen r WHERE r.firma_id = f.id AND r.bis >= CURDATE())';
            }
        }
        foreach ($gleich as $k => $spalte) {
            $w = trim((string) ($f[$k] ?? ''));
            if ($w !== '') { $wo[] = "$spalte = ?"; $args[] = $w; }
        }
        if (isset($f['score_min']) && $f['score_min'] !== '' && is_numeric($f['score_min'])) {
            $wo[] = 'f.score >= ?'; $args[] = (int) $f['score_min'];
        }
        if (!empty($f['von']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $f['von'])) {
            $wo[] = 'f.recherchiert_am >= ?'; $args[] = $f['von'] . ' 00:00:00';
        }
        if (!empty($f['bis']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $f['bis'])) {
            $wo[] = 'f.recherchiert_am <= ?'; $args[] = $f['bis'] . ' 23:59:59';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $wo[] = '(f.name LIKE ? OR f.domain LIKE ? OR f.kennung = ? OR f.stadt LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($args, $like, $like, strtoupper($q), $like);
        }
        /* Kunden finden (07.10.2026, Uwe): standardmäßig nur die Arbeitsliste — wer schon Kunde ist, kein Interesse hat
           oder gesperrt ist, steht unter seinem eigenen Reiter. „alle“ zeigt alles. */
        $ansicht = (string) ($f['ansicht'] ?? '');
        if ($ansicht === '' && empty($f['kontakt']) && empty($f['gesperrte']) && empty($f['q'])) { $ansicht = 'arbeit'; }
        if ($ansicht !== 'alle' && self::spalteDa('akq_firmen', 'markierung')) {
            require_once __DIR__ . '/KundenFinden.php';
            $w = KundenFinden::ansichtSql($ansicht);
            if ($w !== '') { $wo[] = $w; }
            if ($ansicht === 'aus') { $f['gesperrte'] = 1; }
        }
        if (empty($f['gesperrte'])) { $wo[] = 'f.gesperrt = 0'; }
        $sql = implode(' AND ', $wo);
        $ordnung = self::SORTIERUNG[(string) ($f['sort'] ?? 'score')] ?? self::SORTIERUNG['score'];

        $gesamt = (int) Db::wert("SELECT COUNT(*) FROM akq_firmen f WHERE $sql", $args);
        $seiten = max(1, (int) ceil($gesamt / $proSeite));
        $seite = max(1, min($seite, $seiten));
        $ab = ($seite - 1) * $proSeite;
        $zeilen = Db::all("SELECT f.*,
                  (SELECT v.status FROM akq_vorlagen v WHERE v.firma_id = f.id AND v.status <> 'verworfen' ORDER BY v.id DESC LIMIT 1) AS vorlage_status,
                  (SELECT v.kanal  FROM akq_vorlagen v WHERE v.firma_id = f.id AND v.status <> 'verworfen' ORDER BY v.id DESC LIMIT 1) AS vorlage_kanal
             FROM akq_firmen f WHERE $sql ORDER BY $ordnung LIMIT $proSeite OFFSET $ab", $args);
        // Partner-Kennzeichnung je Zeile: wer hat den Betrieb gerade (Anrufliste oder eigene Reservierung)
        if ($zeilen) {
            $ids = array_map(static fn($z) => (int) $z['id'], $zeilen);
            $res = [];
            try {
                foreach (Db::all('SELECT r.firma_id, r.partner_id, r.herkunft, r.anruf_status, r.versuche, r.bis, p.name AS partner_name
                                    FROM partner_reservierungen r JOIN partner p ON p.id = r.partner_id
                                   WHERE r.bis >= CURDATE() AND r.firma_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $r) {
                    $res[(int) $r['firma_id']] = $r;
                }
            } catch (Throwable $e) { $res = []; }   // Tabelle noch nicht da
            foreach ($zeilen as &$z) { $z['beim_partner'] = $res[(int) $z['id']] ?? null; }
            unset($z);
        }
        return ['zeilen' => $zeilen, 'gesamt' => $gesamt, 'seite' => $seite, 'seiten' => $seiten];
    }

    /**
     * Kennzeichnung „beim Partner“ fuer Listen (03.10.2026, Uwe): kurzer Stand
     * und eine Art fuer die Farbe -- an = laeuft, fertig = zugestimmt, aus = erledigt.
     * @return array{0:string,1:string}
     */
    public static function partnerKennung(array $r): array
    {
        if ((string) ($r['herkunft'] ?? '') !== 'vecom') { return ['kümmert sich', 'an']; }
        $v = (int) ($r['versuche'] ?? 0);
        return match ((string) ($r['anruf_status'] ?? 'offen')) {
            'nicht_erreicht' => ['ruft an · ' . $v . '× nicht erreicht', 'an'],
            'zugestimmt' => ['· hat zugestimmt', 'fertig'],
            'kein_interesse' => ['· kein Interesse', 'aus'],
            'nicht_erreichbar' => ['· nicht erreichbar', 'aus'],
            default => ['ruft an', 'an'],
        };
    }

    /** Werte fuer die Auswahllisten der Filter -- nur was es wirklich gibt. */
    public static function filterWerte(): array
    {
        return [
            'region' => array_column(Db::all('SELECT DISTINCT region FROM akq_firmen WHERE region IS NOT NULL ORDER BY region'), 'region'),
            'kreis'  => array_column(Db::all('SELECT DISTINCT kreis FROM akq_firmen WHERE kreis IS NOT NULL ORDER BY kreis'), 'kreis'),
            'stadt'  => array_column(Db::all('SELECT DISTINCT stadt FROM akq_firmen WHERE stadt IS NOT NULL ORDER BY stadt LIMIT 500'), 'stadt'),
            'branche'=> array_column(Db::all('SELECT DISTINCT branche FROM akq_firmen WHERE branche IS NOT NULL ORDER BY branche'), 'branche'),
        ];
    }

    /**
     * Der Wochenbericht aufs Handy -- montags frueh, einmal je Kalenderwoche.
     *
     * Er ersetzt die WhatsApp-Nachricht des alten Lead-Scouts. Nur Zahlen
     * und der Weg in die Verwaltung, nie ein Firmenname: Der Zuruf laeuft
     * ueber einen fremden Dienst (siehe Zuruf.php). Gemeldet wird nur, wenn
     * sich etwas getan hat -- ein Bericht „nichts passiert" wird nach dem
     * dritten Mal nicht mehr gelesen, auch dann nicht, wenn er etwas sagt.
     *
     * @return array<string,mixed>
     */
    public static function wochenbericht(?int $jetzt = null): array
    {
        $jetzt ??= time();
        if ((int) date('N', $jetzt) !== 1 || (int) date('G', $jetzt) < 7) { return ['uebersprungen' => 'nicht Montag früh']; }
        $woche = date('oW', $jetzt);
        if ((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_wochenbericht'", [], '') === $woche) {
            return ['uebersprungen' => 'schon gemeldet'];
        }
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_wochenbericht', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$woche]);

        $ab = date('Y-m-d H:i:s', $jetzt - 7 * 86400);
        $z = array_map(static fn($v) => (int) $v, Db::one("SELECT
                (SELECT COUNT(*) FROM akq_firmen WHERE recherchiert_am >= ?) AS gefunden,
                (SELECT COUNT(*) FROM akq_audits WHERE beendet_am >= ? AND status = 'fertig') AS geprueft,
                (SELECT COUNT(*) FROM akq_firmen WHERE geprueft_am >= ? AND score >= 71 AND gesperrt = 0) AS stark,
                (SELECT COUNT(*) FROM akq_firmen WHERE gesperrt = 0 AND kontakt_status IN ('qualifiziert','vorlage','freigegeben')) AS bereit,
                (SELECT COUNT(*) FROM akq_versand WHERE created_at >= ? AND status IN ('gesendet','von_hand')) AS kontaktiert,
                (SELECT COUNT(*) FROM akq_antworten WHERE eingang_am >= ?) AS antworten,
                (SELECT COUNT(*) FROM akq_antworten WHERE eingang_am >= ? AND klasse IN ('INTERESTED','MORE_INFO','CALL_REQUEST','PRICE_REQUEST')) AS positiv,
                (SELECT COUNT(*) FROM akq_analysen WHERE zuletzt_am >= ?) AS analyse_offen",
            [$ab, $ab, $ab, $ab, $ab, $ab, $ab]) ?? []);
        if (($z['gefunden'] + $z['geprueft'] + $z['kontaktiert'] + $z['antworten'] + $z['analyse_offen']) === 0) {
            return ['gemeldet' => false] + $z;
        }
        $zeilen = ['Neue Kunden finden — die Woche:'];
        if ($z['gefunden'])      { $zeilen[] = '• ' . $z['gefunden'] . ' Betriebe gefunden, ' . $z['geprueft'] . ' Websites geprüft'; }
        elseif ($z['geprueft'])  { $zeilen[] = '• ' . $z['geprueft'] . ' Websites geprüft'; }
        if ($z['stark'])         { $zeilen[] = '• ' . $z['stark'] . ' mit starker Chance'; }
        if ($z['bereit'])        { $zeilen[] = '• ' . $z['bereit'] . ' bereit zum Ansprechen'; }
        if ($z['kontaktiert'])   { $zeilen[] = '• ' . $z['kontaktiert'] . ' angeschrieben oder angerufen'; }
        if ($z['antworten'])     { $zeilen[] = '• ' . $z['antworten'] . ' Antworten' . ($z['positiv'] ? ', davon ' . $z['positiv'] . ' mit Interesse' : ''); }
        if ($z['analyse_offen']) { $zeilen[] = '• ' . $z['analyse_offen'] . '× wurde eine Analyse-Seite geöffnet'; }
        /* Erweitert (27.09.2026, Uwe: Ja): Wochenziel, Trichter der letzten 30
           Tage und die drei Betriebe, bei denen sich gerade etwas tut. Jeder
           Teil still -- ein fehlender Baustein kostet eine Zeile, nicht den Bericht. */
        foreach (self::wochenberichtZusatz($jetzt) as $zeile) { $zeilen[] = $zeile; }
        $zeilen[] = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/app/akquise';
        require_once __DIR__ . '/Zuruf.php';
        Zuruf::vormerken('akquise_woche', implode("\n", $zeilen), 60 * 24 * 6);
        return ['gemeldet' => true] + $z;
    }

    /** @return list<string> Zusatzzeilen für den Wochenbericht */
    public static function wochenberichtZusatz(int $jetzt): array
    {
        $aus = [];
        try {
            require_once __DIR__ . '/AkquiseAuswertung.php';
            $w = AkquiseAuswertung::woche($jetzt - 7 * 86400);
            $aus[] = '• Wochenziel letzte Woche: ' . $w['erreicht'] . ' von ' . $w['ziel'] . ($w['erreicht'] >= $w['ziel'] ? ' ✓' : '');
            $t = AkquiseAuswertung::summe(AkquiseAuswertung::trichter('branche', 30));
            if ($t['angesprochen'] > 0) {
                $p = static fn(int $a, int $b): string => $b > 0 ? ' (' . round($a / $b * 100) . ' %)' : '';
                $aus[] = '• 30 Tage: ' . $t['angesprochen'] . ' angesprochen → ' . $t['geoeffnet'] . ' Analyse geöffnet' . $p($t['geoeffnet'], $t['angesprochen'])
                    . ' → ' . $t['antwort'] . ' Antwort → ' . $t['interesse'] . ' Interesse → ' . $t['kunde'] . ' Kunde';
            }
        } catch (Throwable $e) { }
        try {
            $ab = date('Y-m-d H:i:s', $jetzt - 7 * 86400);
            $heiss = Db::all("SELECT f.name, MAX(a.aufrufe) AS n FROM akq_analysen a JOIN akq_firmen f ON f.id = a.firma_id
                               WHERE a.zuletzt_am >= ? AND f.gesperrt = 0 AND f.kontakt_status NOT IN ('kunde','abgelehnt','geantwortet')
                            GROUP BY f.id, f.name ORDER BY n DESC LIMIT 3", [$ab]);
            if ($heiss) { $aus[] = '• Jetzt anrufen: ' . implode(', ', array_map(static fn($z) => $z['name'] . ' (Analyse ' . (int) $z['n'] . '× geöffnet)', $heiss)); }
            $sig = Db::all('SELECT f.name, s.text FROM akq_signale s JOIN akq_firmen f ON f.id = s.firma_id WHERE s.erledigt = 0 ORDER BY s.id DESC LIMIT 3');
            if ($sig) { $aus[] = '• Signale: ' . implode(' · ', array_map(static fn($z) => $z['name'] . ': ' . $z['text'], $sig)); }
            $auto = (int) Db::wert('SELECT COUNT(*) FROM akq_antworten WHERE nachricht_id IS NOT NULL AND eingang_am >= ?', [$ab], 0);
            if ($auto) { $aus[] = '• ' . $auto . ' Antworten automatisch aus dem Postfach eingeordnet'; }
        } catch (Throwable $e) { }
        return $aus;
    }

    /**
     * Uebernahme aus dem alten Lead-Scout (26.09.2026): Notiz, Sprache und
     * -- wichtiger -- ob der Betrieb schon angeschrieben wurde.
     *
     * Der Worker darf sonst nichts am Kontaktstand aendern. Hier darf er es,
     * weil die Aenderung nur in eine Richtung geht: Sie SPERRT eine zweite
     * Ansprache, sie erlaubt nie eine. Ein Betrieb, den Uwe am 23.09. per
     * WhatsApp angeschrieben hat, darf nicht als „Neu“ wieder auftauchen.
     */
    public static function altbestand(int $id, array $roh): void
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { return; }
        $neu = [];
        $notiz = trim((string) ($roh['notiz'] ?? ''));
        if ($notiz !== '' && trim((string) $f['notiz']) === '') { $neu['notiz'] = mb_substr($notiz, 0, 2000); }
        $sp = (string) ($roh['sprache'] ?? '');
        if (in_array($sp, ['de', 'it', 'en'], true) && empty($f['sprache'])) { $neu['sprache'] = $sp; }
        if ($neu) { Db::update('akq_firmen', $id, $neu); }

        $k = $roh['schon_kontaktiert'] ?? null;
        if (!is_array($k)) { return; }
        $am = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($k['am'] ?? '')) ? (string) $k['am'] : date('Y-m-d');
        $kanal = in_array($k['kanal'] ?? '', ['email', 'whatsapp', 'kontaktformular', 'brief', 'telefon'], true) ? (string) $k['kanal'] : 'whatsapp';
        if (Db::wert("SELECT id FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand')", [$id], null) !== null) { return; }
        $vid = Db::insert('akq_versand', [
            'firma_id' => $id, 'kanal' => $kanal, 'an' => $kanal === 'email' ? $f['email'] : $f['telefon'],
            'status' => 'von_hand', 'compliance' => (string) $f['compliance_status'],
            'grund' => mb_substr('Vor der Verwaltung angeschrieben (alter Lead-Scout, ' . (string) ($k['wie'] ?? 'von Hand') . ') am ' . date('d.m.Y', strtotime($am)), 0, 255),
            'actor' => 'Lead-Scout', 'created_at' => $am . ' 12:00:00',
        ]);
        Db::update('akq_firmen', $id, ['kontakt_status' => 'kontaktiert', 'versand_status' => 'gesendet']);
        self::protokoll($id, 'versand', 'Aus dem alten Lead-Scout übernommen: am ' . date('d.m.Y', strtotime($am)) . ' kontaktiert', ['versand' => $vid]);
    }

    /** Kennzahlen fuer den Kopf der Seite -- jedes Mal gerechnet, nie gespeichert. */
    public static function kennzahlen(): array
    {
        $z = Db::one("SELECT COUNT(*) AS gesamt,
                             SUM(audit_status = 'fertig') AS geprueft,
                             SUM(audit_status = 'offen') AS wartend,
                             SUM(audit_status = 'keine_website') AS ohne_website,
                             SUM(score >= 71) AS stark,
                             SUM(kontakt_status = 'vorlage') AS vorlagen,
                             SUM(kontakt_status IN ('kontaktiert','geantwortet')) AS kontaktiert,
                             SUM(gesperrt = 1) AS gesperrt,
                             SUM(gesperrt = 0 AND kontakt_status IN ('qualifiziert','vorlage','freigegeben')) AS bereit,
                             SUM(gesperrt = 0 AND kontakt_status = 'kontaktiert') AS warten,
                             SUM(kontakt_status = 'geantwortet') AS antworten
                        FROM akq_firmen") ?? [];
        return array_map(static fn($v) => (int) $v, $z);
    }
}
