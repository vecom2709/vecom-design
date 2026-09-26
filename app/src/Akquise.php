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
        if ($vs === 'freigegeben') {
            return $vk === 'email'
                ? ['wort' => 'E-Mail senden', 'ziel' => $seite . '#kontakt', 'art' => 'link']
                : ['wort' => 'Brief drucken', 'ziel' => $seite . '/brief', 'art' => 'link'];
        }
        if ($vs === 'entwurf') { return ['wort' => 'Text prüfen', 'ziel' => $seite . '#kontakt', 'art' => 'link']; }
        if ($as === 'fertig' && (int) ($f['score'] ?? 0) >= 31) { return ['wort' => 'Brief schreiben', 'ziel' => 'akq_vorlage_regel', 'art' => 'post']; }
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
            $land = strtoupper((string) $land);
            if ($land === 'IT') { $z = '+39' . $z; }
            elseif ($land === 'DE' && str_starts_with($z, '0')) { $z = '+49' . substr($z, 1); }
        }
        return $z;
    }

    public static function normEmail(?string $e): ?string
    {
        $e = mb_strtolower(trim((string) $e));
        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
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
                if ($neu) { Db::update('akq_firmen', $id, $neu); }
                self::protokoll($id, 'dublette', 'Erneut gefunden (' . $grund . ') — nur leere Felder ergänzt',
                    ['ergaenzt' => array_keys($neu)], $laufId);
                return ['id' => $id, 'neu' => false, 'grund' => $grund, 'gesperrt' => (int) ($alt['gesperrt'] ?? 0) === 1];
            }

            $d['kennung'] = self::neueKennung();
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
    public static function naechsteAudits(int $anzahl = 10): array
    {
        $anzahl = max(1, min(50, $anzahl));
        /* "laeuft" seit ueber zwei Stunden heisst: Der Worker ist dabei
           abgestuerzt. Dann darf ein neuer Lauf die Firma wieder nehmen. */
        Db::run("UPDATE akq_firmen SET audit_status = 'offen'
                  WHERE audit_status = 'laeuft' AND updated_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        $zeilen = Db::all(
            "SELECT id, kennung, name, url, domain, land, branche, stadt, sprache, tourismus
               FROM akq_firmen
              WHERE gesperrt = 0 AND url IS NOT NULL AND domain IS NOT NULL
                AND (audit_status = 'offen'
                     OR (audit_status = 'fertig' AND geprueft_am < DATE_SUB(NOW(), INTERVAL " . self::NEUPRUEFUNG_TAGE . " DAY))
                     -- Ein Fehler ist oft voruebergehend (Seite kurz weg, Zeitueberschreitung):
                     -- naechster Versuch nach drei Stunden, also im naechsten Nachtlauf.
                     -- Hoechstens drei Fehlversuche in zwei Wochen, dann erst wieder nach der Frist oben.
                     OR (audit_status = 'fehler' AND updated_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)
                         AND (SELECT COUNT(*) FROM akq_audits a WHERE a.firma_id = akq_firmen.id AND a.status = 'fehler'
                                AND a.created_at > DATE_SUB(NOW(), INTERVAL 14 DAY)) < 3))
              ORDER BY (audit_status = 'offen') DESC, recherchiert_am ASC, id ASC
              LIMIT $anzahl");
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
        foreach (['sprache' => 2, 'email' => 190, 'telefon' => 40, 'ansprechpartner' => 120] as $feld => $max) {
            // Was der Worker auf der Website gefunden hat, fuellt nur Luecken.
            $wert = $feld === 'email' ? self::normEmail($e[$feld] ?? null)
                  : ($feld === 'telefon' ? self::normTelefon($e[$feld] ?? null, (string) $firma['land']) : self::kurz($e[$feld] ?? null, $max));
            if ($wert !== null && ($firma[$feld] ?? null) === null) { $upd[$feld] = $wert; }
        }
        Db::update('akq_firmen', $firmaId, $upd);

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
        return ['zeilen' => $zeilen, 'gesamt' => $gesamt, 'seite' => $seite, 'seiten' => $seiten];
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
        $zeilen[] = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/app/akquise';
        require_once __DIR__ . '/Zuruf.php';
        Zuruf::vormerken('akquise_woche', implode("\n", $zeilen), 60 * 24 * 6);
        return ['gemeldet' => true] + $z;
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
