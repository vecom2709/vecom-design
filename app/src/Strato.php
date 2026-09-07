<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';

/**
 * DIE GESPRÄCHE HOLEN, STATT SIE ANZUSCHAUEN
 * ===========================================================================
 *
 * Manuela läuft bei STRATO. Was sie GETAN hat, steht in unseren activities --
 * jeder Werkzeugaufruf mit Parametern und Ergebnis, unsere Spur, für immer.
 * Was am Telefon GESPROCHEN wurde, liegt bei STRATO: Betreff,
 * Zusammenfassung, Dauer, Anrufer.
 *
 * Und daneben, in der Oberfläche gar nicht sichtbar, liegt eine maschinelle
 * Auswertung je Anruf: wie er ausging, wie beteiligt der Anrufer war, welche
 * Probleme auffielen, und -- das Wertvollste -- ob der Assistent gegen seine
 * eigenen Anweisungen verstoßen hat. Der fehlgeschlagene Anruf vom 7.9. um
 * 00:11 steht dort mit vier Schlagworten: falsche Werkzeugparameter,
 * Anweisung missachtet, Anrufer frustriert, Erkennungsfehler. Das ist kein
 * Verdacht mehr, das ist ein Befund -- und in einer Liste von 43 Anrufen
 * zählt ihn niemand von Hand zusammen.
 *
 * WARUM EIN AUFFRISCHUNGS-TOKEN UND KEIN PASSWORT
 *
 * Für die Abfrage braucht es eine Anmeldung. Ein Passwort im Server zu
 * hinterlegen kam nicht in Frage -- weder er noch ich geben Passwörter
 * irgendwo ein. Supabase kennt dafür den Auffrischungs-Token: Er wird beim
 * Anmelden im Browser vergeben, taugt nur für genau dieses eine Konto bei
 * genau diesem einen Dienst, wird bei jeder Benutzung ausgetauscht, und ein
 * Abmelden bei STRATO macht ihn wertlos. Er kommt aus seinem Browser über
 * ein Feld in seiner eigenen Verwaltung hierher -- nie über einen Chat, nie
 * über eine E-Mail.
 *
 * WAS PASSIERT, WENN ER ABLÄUFT
 *
 * Dann steht es auf der Telefonseite, mit dem Satz, was zu tun ist. Es
 * scheitert nichts still: Ein Abgleich, der aufhört zu laufen, ohne dass es
 * jemand merkt, ist schlimmer als keiner.
 */
final class Strato
{
    /** Öffentliche Angaben -- sie stehen so auch im Browser-Bundle von STRATO. */
    public const PROJEKT = 'https://oeblavonrjzfihahjvmm.supabase.co';

    /** Wie weit zurück beim Abgleich gesehen wird. */
    public const TAGE = 60;

    /** So lange gilt ein Zugangs-Token, bevor wir einen neuen holen. */
    public const TOKEN_SEKUNDEN = 2400;

    /** So viele Sekunden auf den zu warten, der gerade erneuert. */
    public const SPERRE_WARTEN = 8;

    public const ZEITGRENZE = 20;

    /* ==================================================================== */
    /*  Zugang                                                              */
    /* ==================================================================== */

    public static function eingerichtet(): bool
    {
        return self::wert('strato_refresh') !== '' && self::wert('strato_anon') !== '';
    }

    /**
     * Den Zugang hinterlegen. Beides kommt aus dem Browser, beides über ein
     * Feld in der Verwaltung.
     *
     * Der Token wird sofort ausprobiert. Ein hinterlegter Zugang, von dem
     * erst der nächste Cronlauf merkt, dass er nicht geht, ist kein Zugang,
     * sondern eine Vermutung.
     *
     * @return array{ok:bool,text:string}
     */
    public static function zugangSetzen(string $anon, string $refresh): array
    {
        $anon    = trim($anon);
        $refresh = trim($refresh);
        if ($anon === '' || $refresh === '') {
            return ['ok' => false, 'text' => 'Es fehlt eine der beiden Angaben.'];
        }
        if (!str_starts_with($anon, 'eyJ')) {
            return ['ok' => false, 'text' => 'Der öffentliche Schlüssel sieht nicht aus wie einer — er beginnt mit „eyJ“.'];
        }

        self::merken('strato_anon', $anon);
        self::merken('strato_refresh', $refresh);
        self::merken('strato_zugang', '');            // zwischengespeicherten Token verwerfen
        self::merken('strato_fehler', '');

        $t = self::zugangsToken();
        if ($t === null) {
            return ['ok' => false, 'text' => 'Der Token wurde nicht angenommen: ' . self::wert('strato_fehler')];
        }
        return ['ok' => true, 'text' => 'Der Zugang steht.'];
    }

    public static function zugangLoeschen(): void
    {
        foreach (['strato_anon', 'strato_refresh', 'strato_zugang', 'strato_fehler'] as $k) {
            self::merken($k, '');
        }
    }

    /**
     * Ein gültiger Zugangs-Token, notfalls frisch geholt.
     *
     * Supabase tauscht den Auffrischungs-Token bei jeder Benutzung aus. Der
     * neue wird SOFORT gespeichert -- wer ihn erst nach der Abfrage sichert,
     * verliert bei einem Fehler dazwischen den Zugang und weiß nicht, warum.
     */
    public static function zugangsToken(): ?string
    {
        $da = self::zwischengespeicherter();
        if ($da !== null) { return $da; }

        $anon = self::wert('strato_anon');
        if ($anon === '' || self::wert('strato_refresh') === '') {
            self::merken('strato_fehler', 'Kein Zugang hinterlegt.');
            return null;
        }

        /* NUR EINER DARF ERNEUERN
           ------------------------------------------------------------------
           Supabase tauscht den Auffrischungs-Token bei jeder Benutzung aus
           und widerruft die ganze Sitzung, wenn ein bereits benutzter noch
           einmal kommt: „Invalid Refresh Token: Already Used". Genau das ist
           am 7. September passiert -- der stündliche Abgleich und ein Klick
           in der Verwaltung fielen zusammen, und danach war der Zugang tot.
           Der Fehler ist selten, aber wenn er kommt, kostet er den Zugang
           und niemand weiss, warum.

           Also eine Sperre. Wer sie nicht bekommt, hat es nicht eilig: Der
           andere schreibt gerade einen frischen Token, und danach steht er
           da. Deshalb wird nach dem Warten NOCH EINMAL nachgesehen, bevor
           irgendetwas abgerufen wird. */
        $sperre = self::still(static fn() => (int) Db::wert(
            "SELECT GET_LOCK('vd_strato_token', ?)", [self::SPERRE_WARTEN], 0), 0);

        try {
            /* Zweiter Blick: In der Wartezeit kann ein anderer fertig
               geworden sein. Ohne diese Zeile wäre die Sperre wirkungslos --
               sie würde nur die gleichzeitigen Abrufe nacheinander machen,
               statt einen davon einzusparen. */
            $da = self::zwischengespeicherter();
            if ($da !== null) { return $da; }

            $refresh = self::wert('strato_refresh');
            if ($refresh === '') {
                self::merken('strato_fehler', 'Kein Zugang hinterlegt.');
                return null;
            }

            $a = self::abruf('POST', self::PROJEKT . '/auth/v1/token?grant_type=refresh_token',
                             $anon, null, ['refresh_token' => $refresh]);

            if (!$a['ok'] || !is_array($a['daten']) || ($a['daten']['access_token'] ?? '') === '') {
                /* Letzter Blick, bevor gemeldet wird: Bekam die Sperre jemand
                   anders und war gerade fertig, ist alles in Ordnung -- und
                   eine Fehlermeldung wäre schlicht falsch. */
                $da = self::zwischengespeicherter();
                if ($da !== null) { return $da; }

                $grund = is_array($a['daten'])
                    ? (string) ($a['daten']['error_description'] ?? $a['daten']['msg']
                             ?? $a['daten']['error'] ?? '')
                    : '';
                self::merken('strato_fehler', $grund !== ''
                    ? $grund
                    : 'Der Zugang wurde abgelehnt (' . $a['status'] . '). Vermutlich abgemeldet oder abgelaufen.');
                return null;
            }

            /* Zuerst den neuen Auffrischungs-Token sichern, dann alles andere. */
            if (($a['daten']['refresh_token'] ?? '') !== '') {
                self::merken('strato_refresh', (string) $a['daten']['refresh_token']);
            }
            $token = (string) $a['daten']['access_token'];
            $gilt  = min((int) ($a['daten']['expires_in'] ?? 3600), self::TOKEN_SEKUNDEN);
            self::merken('strato_zugang',
                (string) json_encode(['token' => $token, 'bis' => time() + $gilt - 60]));
            self::merken('strato_fehler', '');
            return $token;
        } finally {
            if ($sperre === 1) {
                self::still(static fn() => Db::wert("SELECT RELEASE_LOCK('vd_strato_token')", [], 0));
            }
        }
    }

    /** Der noch gültige Token aus dem Zwischenspeicher -- oder null. */
    private static function zwischengespeicherter(): ?string
    {
        $roh = self::wert('strato_zugang');
        if ($roh === '') { return null; }
        $d = json_decode($roh, true);
        if (!is_array($d) || (string) ($d['token'] ?? '') === '') { return null; }
        return (int) ($d['bis'] ?? 0) > time() ? (string) $d['token'] : null;
    }

    /* ==================================================================== */
    /*  Abgleich                                                            */
    /* ==================================================================== */

    /**
     * Die Gespräche der letzten Wochen holen und ablegen.
     *
     * Es wird immer das ganze Fenster geholt, nicht nur das Neue: Die
     * Auswertung eines Anrufs entsteht erst ein paar Minuten nach dem
     * Auflegen, und ein Anruf, der beim ersten Abgleich noch keine hatte,
     * bekäme sie sonst nie.
     *
     * @return array<string,mixed>
     */
    public static function abgleichen(int $tage = self::TAGE): array
    {
        if (!self::eingerichtet()) { return ['ok' => false, 'grund' => 'kein_zugang']; }

        $token = self::zugangsToken();
        if ($token === null) {
            self::melden();
            return ['ok' => false, 'grund' => 'zugang', 'text' => self::wert('strato_fehler')];
        }

        $ab = gmdate('Y-m-d\TH:i:s\Z', time() - $tage * 86400);
        $u  = self::PROJEKT . '/rest/v1/conversations'
            . '?select=' . rawurlencode('id,call_sid,created_at,call_seconds_billed,fwd_seconds_billed,'
                                      . 'agent_number,customer_number,summaries(content,metadata)')
            . '&created_at=gte.' . rawurlencode($ab)
            . '&order=created_at.desc&limit=500';

        $a = self::abruf('GET', $u, self::wert('strato_anon'), $token);
        if (!$a['ok'] || !is_array($a['daten'])) {
            self::merken('strato_fehler', 'Die Gespräche kamen nicht (' . $a['status'] . ').');
            self::melden();
            return ['ok' => false, 'grund' => 'abruf', 'status' => $a['status']];
        }

        /* WAS GELÖSCHT WURDE, KOMMT NICHT WIEDER
           ------------------------------------------------------------------
           Bei STRATO bleibt der Anruf liegen -- daran kommen wir nicht heran.
           Käme er beim nächsten Abgleich zurück, wäre „gelöscht" eine Lüge,
           die sich selbst widerlegt, während man zusieht. Die Sperrliste
           merkt sich dafür nur die Kennung: kein Name, keine Nummer, kein
           Betreff. */
        $weg = array_flip(array_column(
            (array) self::still(static fn() => Db::all('SELECT id FROM telefon_gespraech_weg'), []), 'id'));

        $neu = $geaendert = $uebersprungen = 0;
        foreach ($a['daten'] as $g) {
            if (!is_array($g)) { continue; }
            if (isset($weg[(string) ($g['id'] ?? '')])) { $uebersprungen++; continue; }
            $r = self::ablegen($g);
            if ($r === 'neu') { $neu++; } elseif ($r === 'geaendert') { $geaendert++; }
        }

        self::merken('strato_zuletzt', date('Y-m-d H:i:s'));
        self::merken('strato_fehler', '');
        return ['ok' => true, 'gesehen' => count($a['daten']), 'neu' => $neu,
                'geaendert' => $geaendert, 'uebersprungen' => $uebersprungen];
    }

    /** Einen Satz ablegen. @return 'neu'|'geaendert'|'gleich' */
    private static function ablegen(array $g): string
    {
        $id = (string) ($g['id'] ?? '');
        if ($id === '') { return 'gleich'; }

        /* DIE SPERRE GEHÖRT AN DIE SCHREIBENDE STELLE
           Der Abgleich filtert schon vorab -- eine Liste ist billiger als
           eine Abfrage je Satz. Aber wer sich darauf verlässt, hat die
           Sperre an der Stelle, an der GEHOLT wird, statt an der, an der
           GESCHRIEBEN wird. Ein zweiter Aufrufer käme daran vorbei, und
           „gelöscht" wäre wieder eine Lüge. Also hier noch einmal. */
        if ((int) self::still(static fn() => Db::wert(
                'SELECT COUNT(*) FROM telefon_gespraech_weg WHERE id = ?', [$id], 0), 0) > 0) {
            return 'gleich';
        }

        /* Die Zusammenfassung kommt als Liste, weil ein Gespräch mehrere
           haben KÖNNTE. Bisher hat es genau eine oder keine. */
        $s = $g['summaries'] ?? [];
        if (isset($s['content']) || isset($s['metadata'])) { $s = [$s]; }
        $erste = is_array($s) && $s ? (array) $s[0] : [];

        $inhalt = $erste['content'] ?? [];
        if (is_string($inhalt)) { $inhalt = json_decode($inhalt, true) ?: []; }
        $meta = $erste['metadata'] ?? [];
        if (is_string($meta)) { $meta = json_decode($meta, true) ?: []; }
        $an = is_array($meta) ? (array) ($meta['analysis'] ?? []) : [];
        $fn = (array) ($an['function_calling'] ?? []);

        $nummer = (string) ($g['customer_number'] ?? '');

        $daten = [
            'call_sid'        => self::kurz((string) ($g['call_sid'] ?? ''), 80),
            'begonnen'        => self::zeit((string) ($g['created_at'] ?? '')),
            'sekunden'        => max(0, (int) ($g['call_seconds_billed'] ?? 0)),
            'weiter_sek'      => max(0, (int) ($g['fwd_seconds_billed'] ?? 0)),
            'agent_nummer'    => self::kurz((string) ($g['agent_number'] ?? ''), 40),
            'kunde_nummer'    => self::kurz($nummer, 40),
            'name'            => self::kurz((string) ($inhalt['name'] ?? ''), 160),
            'betreff'         => self::kurz((string) ($inhalt['subject'] ?? ''), 255),
            'zusammenfassung' => (string) ($inhalt['summary'] ?? ''),
            'ausgang'         => self::kurz((string) ($an['call_outcome'] ?? ''), 48),
            'engagement'      => self::kurz((string) ($an['engagement_level'] ?? ''), 48),
            'tags'            => self::kurz(implode(',', array_map('strval', (array) ($an['issue_tags'] ?? []))), 500),
            'notizen'         => trim((string) ($an['analysis_notes'] ?? '')
                                    . ((string) ($an['call_outcome_notes'] ?? '') !== ''
                                       ? "\n" . (string) $an['call_outcome_notes'] : '')),
            'verstoss'        => !empty($fn['prompt_violation']) ? 1 : 0,
            'verstoss_text'   => (string) ($fn['prompt_violation_notes'] ?? ''),
            'erfunden'        => (!empty($fn['other_hallucination']) || !empty($fn['booking_hallucination'])
                                  || !empty($fn['forwarding_hallucination'])) ? 1 : 0,
            'nachrichten'     => (int) ($meta['total_messages'] ?? 0),
            'werkzeuge'       => (int) ($meta['function_calls'] ?? 0),
            'kunde_id'        => self::kundeZu($nummer),
            'roh'             => (string) json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'geholt_am'       => date('Y-m-d H:i:s'),
        ];

        $vorher = Db::one('SELECT ausgang, betreff, tags, anonym FROM telefon_gespraeche WHERE id = ?', [$id]);

        /* ANONYMISIERT WIRD NICHT ÜBERSCHRIEBEN
           Der Abgleich schreibt jede Zeile neu, und STRATO weiß nichts von
           einer Anonymisierung. Ohne diese drei Zeilen stünde der Name eine
           Stunde später wieder da -- die Löschung wäre rückgängig gemacht,
           ohne dass jemand etwas getan hätte. */
        if (is_array($vorher) && (int) ($vorher['anonym'] ?? 0) === 1) { return 'gleich'; }

        $felder = array_keys($daten);
        $sql = 'INSERT INTO telefon_gespraeche (id, ' . implode(', ', $felder) . ') VALUES (?'
             . str_repeat(', ?', count($felder)) . ') ON DUPLICATE KEY UPDATE '
             . implode(', ', array_map(static fn($f) => "$f = VALUES($f)", $felder));
        Db::run($sql, array_merge([$id], array_values($daten)));

        if ($vorher === null) { return 'neu'; }
        return ($vorher['ausgang'] !== $daten['ausgang'] || $vorher['betreff'] !== $daten['betreff']
                || $vorher['tags'] !== $daten['tags']) ? 'geaendert' : 'gleich';
    }

    /** Die Rufnummer einem Kunden zuordnen -- dieselben letzten neun Ziffern wie beim Nachschlagen. */
    private static function kundeZu(string $nummer): ?int
    {
        $z = preg_replace('/[^0-9]/', '', $nummer) ?? '';
        if (strlen($z) < 6) { return null; }
        $id = Db::wert("SELECT id FROM customers
                         WHERE phone IS NOT NULL AND phone <> ''
                           AND RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 9) = ?
                         LIMIT 1", [substr($z, -9)], 0);
        return (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Ein stiller Abgleich ist ein kaputter Abgleich.
     *
     * Gemeldet wird höchstens einmal am Tag -- ein abgelaufener Zugang ist
     * ein Zustand, keine Nachricht, und eine Meldung je Cronlauf wäre in
     * einer Woche eine Wand aus derselben Zeile.
     */
    private static function melden(): void
    {
        $heute = date('Y-m-d');
        if (self::wert('strato_gemeldet') === $heute) { return; }
        self::merken('strato_gemeldet', $heute);
        Events::melden('strato_zugang', 'Die Gespräche von STRATO kommen nicht mehr an', 'schlecht',
            self::wert('strato_fehler') . ' Der Zugang wird unter Einstellungen → Telefonassistentin '
            . 'neu hinterlegt; abgemeldet zu haben genügt, damit er abläuft.', '/einstellungen?b=telefon');
    }

    /* ==================================================================== */
    /*  Die Werkzeuge hinüberschicken                                       */
    /* ==================================================================== */

    /**
     * WARUM DIE VERWALTUNG DAS SELBST TUN MUSS
     * =====================================================================
     *
     * Jede Änderung an einer Werkzeugbeschreibung musste bisher von Hand
     * nach drüben: vierzehn Blöcke kopieren, vierzehnmal einfügen. Wer das
     * dreimal gemacht hat, macht es beim vierten Mal nicht mehr -- und dann
     * steht bei STRATO eine Fassung, die niemand mehr kennt, während hier
     * eine andere gepflegt wird. Im September ist die Konfiguration drüben
     * dreimal auf einen alten Stand zurückgefallen, ohne dass es jemand
     * bemerkt hätte, bis ein Anruf danebenging.
     *
     * WAS DABEI ANGEFASST WIRD UND WAS AUSDRÜCKLICH NICHT
     *
     * Geschrieben wird ausschliesslich `config.tools`. Stimme, Tempo,
     * Begrüssung, Aussprache, Sprachen, der Verhaltenstext -- alles, was er
     * drüben in der Oberfläche eingestellt hat -- bleibt Zeichen für
     * Zeichen, wie es ist. Es wird vorher frisch gelesen und mit genau
     * diesem Satz zurückgeschrieben; wer eine ganze Konfiguration aus dem
     * Gedächtnis schreibt, überschreibt Einstellungen, von denen er nichts
     * weiss.
     *
     * @return array{ok:bool,text:string,werkzeuge?:int}
     */
    public static function werkzeugeUebertragen(): array
    {
        require_once __DIR__ . '/Telefonwerkzeuge.php';

        if (!self::eingerichtet()) {
            return ['ok' => false, 'text' => 'Kein Zugang zu STRATO hinterlegt.'];
        }
        $token = self::zugangsToken();
        if ($token === null) {
            return ['ok' => false, 'text' => 'Der Zugang wird nicht angenommen: ' . self::fehler()];
        }

        $werkzeuge = Telefonwerkzeuge::objekte();
        if (count($werkzeuge) !== count(Telefonwerkzeuge::REIHE)) {
            return ['ok' => false, 'text' => 'Es fehlen Werkzeuge — nichts geschickt.'];
        }

        $id = self::wert('strato_agent');
        $u  = self::PROJEKT . '/rest/v1/agent_configs'
            . ($id !== '' ? '?id=eq.' . rawurlencode($id) : '?select=id,config&limit=2');

        /* Erst lesen: Ohne die aktuelle Konfiguration wüssten wir nicht, was
           daneben steht -- und würden es beim Schreiben verlieren.

           ALS OBJEKTE, NICHT ALS ARRAY. Seine ganze Konfiguration geht hier
           durch und wird unverändert zurückgeschrieben. Mit assoc=true würde
           jedes leere Objekt darin -- irgendwo in personality_config, im
           Widget, in den Kontakten -- zu [] und käme so bei STRATO an.
           Genau das ist am 7. September mit „lage" passiert und hat den
           Assistenten stillgelegt. */
        $a = self::abruf('GET', $id !== '' ? $u . '&select=id,config' : $u,
                         self::wert('strato_anon'), $token, null, true);
        if (!$a['ok'] || !is_array($a['daten']) || !$a['daten']) {
            return ['ok' => false, 'text' => 'Die Konfiguration war nicht zu lesen ('
                                           . $a['status'] . ').'];
        }
        if ($id === '' && count($a['daten']) > 1) {
            return ['ok' => false, 'text' => 'Es gibt mehrere Assistenten. Sag mir, welcher gemeint ist.'];
        }

        $satz = $a['daten'][0];
        $cfg  = $satz->config ?? null;
        if (!$cfg instanceof stdClass) {
            return ['ok' => false, 'text' => 'Die Konfiguration kam leer zurück.'];
        }

        $agent = (string) ($satz->id ?? $id);
        if ($agent === '') { return ['ok' => false, 'text' => 'Kein Assistent gefunden.']; }
        self::merken('strato_agent', $agent);

        $cfg->tools = $werkzeuge;   // NUR das. Alles andere bleibt, wie es kam.

        $p = self::abruf('PATCH', self::PROJEKT . '/rest/v1/agent_configs?id=eq.' . rawurlencode($agent),
                         self::wert('strato_anon'), $token, (object) ['config' => $cfg]);
        if (!$p['ok']) {
            return ['ok' => false, 'text' => 'Das Schreiben wurde abgelehnt (' . $p['status'] . ').'];
        }

        /* Nachlesen statt glauben. Ein PATCH, der 204 sagt und nichts tut,
           ist genau die Art Fehler, die man erst am Telefon merkt. */
        $n = self::abruf('GET', self::PROJEKT . '/rest/v1/agent_configs?id=eq.'
                                . rawurlencode($agent) . '&select=config',
                         self::wert('strato_anon'), $token, null, true);
        $tools = $n['daten'][0]->config->tools ?? null;
        $drueben = is_array($tools) ? count($tools) : 0;
        if ($drueben !== count($werkzeuge)) {
            return ['ok' => false, 'text' => 'Drüben stehen jetzt ' . $drueben . ' statt '
                                           . count($werkzeuge) . ' Werkzeuge. Bitte nachsehen.'];
        }

        /* UND DAS EIGENTLICHE: Steht „properties" noch als Objekt da? Die
           Zahl allein sagt nichts -- vierzehn Werkzeuge, von denen eines ein
           kaputtes Schema hat, legen alle vierzehn still. Diese Prüfung
           kostet nichts und hätte den Ausfall verhindert. */
        $krumm = [];
        foreach ($tools as $x) {
            if (is_array($x->parameters->properties ?? null)) { $krumm[] = (string) ($x->name ?? '?'); }
        }
        if ($krumm) {
            return ['ok' => false, 'text' => 'Bei ' . implode(', ', $krumm) . ' ist „properties" '
                                           . 'als Liste statt als Objekt angekommen. Das legt den '
                                           . 'ganzen Assistenten still — bitte sag mir Bescheid.'];
        }

        self::merken('strato_werkzeuge_am', date('Y-m-d H:i:s'));
        require_once __DIR__ . '/Events.php';
        self::still(static fn() => Events::protokoll('telefon_werkzeuge',
            $drueben . ' Werkzeugbeschreibungen zu STRATO übertragen'));

        return ['ok' => true, 'werkzeuge' => $drueben,
                'text' => $drueben . ' Werkzeuge übertragen. Stimme, Tempo, Begrüßung und '
                        . 'der Verhaltenstext drüben sind unverändert geblieben.'];
    }

    public static function werkzeugeAm(): string { return self::wert('strato_werkzeuge_am'); }

    /* ==================================================================== */
    /*  Auswertung                                                          */
    /* ==================================================================== */

    /** Was die Schlagworte auf Deutsch heißen. Was hier fehlt, wird roh gezeigt. */
    /**
     * Was die Schlagworte auf Deutsch heißen.
     *
     * Die Liste ist gewachsen, nicht erfunden: Der erste Abgleich brachte
     * 43 Gespräche, und was darin wirklich vorkam, steht jetzt hier. Was
     * fehlt, wird roh gezeigt statt verschluckt -- so sieht man beim
     * nächsten neuen Schlagwort, dass es eines gibt.
     */
    public const TAG = [
        'incorrect_function_parameters'  => 'falsche Angaben an ein Werkzeug',
        'failed_to_follow_tool_guidance' => 'Anweisung des Werkzeugs missachtet',
        'failed_to_call_function'        => 'Werkzeug gar nicht erst benutzt',
        'tool_error'                     => 'Werkzeug antwortete nicht',
        'caller_frustration'             => 'Anrufer war genervt',
        'caller_confusion'               => 'Anrufer war verwirrt',
        'transcription_error'            => 'falsch verstanden',
        'unclear_audio'                  => 'schlechte Verbindung',
        'long_silence'                   => 'lange Stille',
        'repetition'                     => 'hat sich wiederholt',
        'agent_repeated_itself'          => 'hat sich wiederholt',
        'interrupted'                    => 'ins Wort gefallen',
        'interruption_handling_friction' => 'kam nach einer Unterbrechung nicht klar',
        'call_ended_before_resolution'   => 'Gespräch endete ohne Ergebnis',
        'wrong_language'                 => 'falsche Sprache',
        'hallucination'                  => 'etwas erfunden',
        'other_hallucination'            => 'etwas erfunden',
        'hallucinated_action'            => 'hat etwas behauptet, das sie nicht getan hat',
        'booking_hallucination'          => 'einen Termin erfunden',
        'forwarding_hallucination'       => 'eine Weiterleitung erfunden',
        'failed_to_confirm_email'        => 'E-Mail-Adresse nicht bestätigen lassen',
        'missing_information'            => 'Angabe fehlte',
        'incomplete_data_collection'     => 'nicht zu Ende gefragt',
    ];

    /**
     * Wie ein Anruf ausging -- und ob das gut oder schlecht ist.
     *
     * DIE FARBE IST EIN URTEIL UND WIRD SPARSAM VERGEBEN. „Weitergeleitet"
     * und „aufgelegt" bleiben farblos: Beides kann richtig gewesen sein,
     * und eine rote Zeile, die nichts bedeutet, macht die roten Zeilen
     * wertlos, die etwas bedeuten.
     */
    public const AUSGANG = [
        'GOAL_ACHIEVED'          => ['Ziel erreicht', 'gut'],
        'RESOLVED'               => ['erledigt', 'gut'],
        'COMPLETED'              => ['abgeschlossen', 'gut'],
        'INFORMATION_GIVEN'      => ['Auskunft gegeben', 'gut'],
        'CALLBACK_REQUESTED'     => ['Rückruf gewünscht', 'gut'],
        'APPOINTMENT_BOOKED'     => ['Termin vereinbart', 'gut'],
        'MESSAGE_TAKEN'          => ['Anliegen aufgenommen', 'gut'],
        'TRANSFERRED'            => ['weitergeleitet', ''],
        'CALLER_HUNG_UP'         => ['aufgelegt', ''],
        'NO_CONVERSATION'        => ['kein Gespräch', ''],
        'CALLER_QUIT_PREMATURELY'=> ['Anrufer stieg vorzeitig aus', 'schlecht'],
        'AGENT_ERROR'            => ['Fehler des Assistenten', 'schlecht'],
        'UNRESOLVED'             => ['ohne Ergebnis', 'schlecht'],
        'ABANDONED'              => ['abgebrochen', 'schlecht'],
        'GOAL_NOT_ACHIEVED'      => ['Ziel verfehlt', 'schlecht'],
    ];

    public const ENGAGEMENT = [
        'ENGAGED_WITH_INTENT' => 'wollte etwas',
        'ENGAGED'             => 'beteiligt',
        'PASSIVE'             => 'zurückhaltend',
        'DISENGAGED'          => 'abweisend',
        'NONE'                => 'gar nicht',
    ];

    public static function tagWort(string $t): string
    {
        return self::TAG[$t] ?? str_replace('_', ' ', $t);
    }

    /** @return array{wort:string,ton:string} */
    public static function ausgangWort(string $a): array
    {
        $p = self::AUSGANG[$a] ?? [$a !== '' ? str_replace('_', ' ', mb_strtolower($a)) : 'unbekannt', ''];
        return ['wort' => $p[0], 'ton' => $p[1]];
    }

    /**
     * Die Gespräche, wie sie auf der Seite stehen.
     *
     * @return list<array<string,mixed>>
     */
    public static function gespraeche(int $tage = 30, string $filter = '', int $grenze = 100): array
    {
        $wo = ['g.begonnen >= NOW() - INTERVAL ' . max(1, $tage) . ' DAY'];
        if ($filter === 'probleme')   { $wo[] = "(g.verstoss = 1 OR g.erfunden = 1 OR g.ausgang = 'AGENT_ERROR' OR g.tags <> '')"; }
        if ($filter === 'verstoss')   { $wo[] = '(g.verstoss = 1 OR g.erfunden = 1)'; }
        if ($filter === 'gespraech')  { $wo[] = 'g.sekunden >= 30'; }
        if ($filter === 'kunden')     { $wo[] = 'g.kunde_id IS NOT NULL'; }

        return Db::all(
            'SELECT g.*, c.name AS kunde_name, c.company AS kunde_firma
               FROM telefon_gespraeche g
               LEFT JOIN customers c ON c.id = g.kunde_id
              WHERE ' . implode(' AND ', $wo) . '
              ORDER BY g.begonnen DESC
              LIMIT ' . max(1, min(500, $grenze)));
    }

    /**
     * Die Zahlen über allem.
     *
     * WAS HIER NICHT STEHT: eine Erfolgsquote. „Erledigt" heißt bei STRATO,
     * dass der Assistent zufrieden war, nicht dass ein Auftrag entstand.
     * Was am Ende zählt, steht im Trichter -- der rechnet mit Bedarfen und
     * Bestellungen, nicht mit Selbstauskünften.
     *
     * @return array<string,mixed>
     */
    public static function zahlen(int $tage = 30): array
    {
        $g = Db::all('SELECT ausgang, engagement, tags, verstoss, erfunden, sekunden, kunde_id, kunde_nummer
                        FROM telefon_gespraeche
                       WHERE begonnen >= NOW() - INTERVAL ' . max(1, $tage) . ' DAY');

        $z = ['anrufe' => count($g), 'sekunden' => 0, 'echte' => 0, 'verstoesse' => 0,
              'erfunden' => 0, 'bekannt' => 0, 'widget' => 0,
              'ausgang' => [], 'engagement' => [], 'tags' => []];

        foreach ($g as $x) {
            $z['sekunden'] += (int) $x['sekunden'];
            /* Unter einer halben Minute wurde nicht gesprochen, sondern
               aufgelegt. Solche Anrufe verzerren jeden Durchschnitt. */
            if ((int) $x['sekunden'] >= 30) { $z['echte']++; }
            if ((int) $x['verstoss']) { $z['verstoesse']++; }
            if ((int) $x['erfunden']) { $z['erfunden']++; }
            if ($x['kunde_id'] !== null) { $z['bekannt']++; }
            if ((string) $x['kunde_nummer'] === 'widget-call') { $z['widget']++; }

            $a = (string) $x['ausgang'];
            if ($a !== '') { $z['ausgang'][$a] = ($z['ausgang'][$a] ?? 0) + 1; }
            $e = (string) $x['engagement'];
            if ($e !== '') { $z['engagement'][$e] = ($z['engagement'][$e] ?? 0) + 1; }
            foreach (array_filter(explode(',', (string) $x['tags'])) as $t) {
                $z['tags'][$t] = ($z['tags'][$t] ?? 0) + 1;
            }
        }
        arsort($z['ausgang']); arsort($z['engagement']); arsort($z['tags']);
        $z['minuten'] = (int) round($z['sekunden'] / 60);
        $sch = $z['echte'] > 0 ? (int) round($z['sekunden'] / $z['echte']) : 0;
        /* Als Minuten und Sekunden, nicht als „265 s". Am Telefon denkt
           niemand in Sekunden -- „4:25" liest man, ohne zu rechnen. */
        $z['schnitt'] = intdiv($sch, 60) . ':' . str_pad((string) ($sch % 60), 2, '0', STR_PAD_LEFT);
        return $z;
    }

    /**
     * Unsere eigene Spur zu einem Gespräch.
     *
     * Zusammengeführt wird über Zeit und Rufnummer -- die Telefonplattform
     * gibt uns keine gemeinsame Nummer. Bei einem Anruf über die Website
     * („widget-call") gibt es gar keine Nummer, dann bleibt nur die Zeit.
     *
     * @return list<array<string,mixed>>
     */
    public static function spur(array $gespraech): array
    {
        $von = (string) $gespraech['begonnen'];
        $dauer = max(60, (int) $gespraech['sekunden'] + 120);
        return Db::all(
            "SELECT id, type, title, meta, created_at FROM activities
              WHERE type LIKE 'telefon\\_%'
                AND created_at >= ? AND created_at <= DATE_ADD(?, INTERVAL ? SECOND)
              ORDER BY id ASC LIMIT 60",
            [$von, $von, $dauer]);
    }

    /* ==================================================================== */
    /*  Löschen                                                             */
    /* ==================================================================== */

    /**
     * WAS AN DIESEM GESPRÄCH NOCH OFFEN IST
     * ---------------------------------------------------------------------
     * Der Grund, warum Löschen hier eine Rückfrage bekommt und nicht bloß
     * einen Knopf: In einem Gespräch entsteht Arbeit. Jemand hat um einen
     * Rückruf gebeten und wartet darauf. Manuela konnte etwas nicht
     * beantworten, und die Frage steht noch offen. Ein Link wurde zugesagt
     * und nie verschickt.
     *
     * Wird so ein Gespräch weggeräumt, verschwindet nicht nur eine Zeile,
     * sondern die Verabredung dahinter -- und niemand merkt es, weil genau
     * die Zeile fehlt, die daran erinnert hätte. Deshalb sagt diese Methode
     * in Worten, was noch aussteht, und deshalb geht das Löschen erst weiter,
     * wenn jemand das ausdrücklich bestätigt.
     *
     * @return list<string>
     */
    public static function offenesZu(array $g): array
    {
        require_once __DIR__ . '/Telefon.php';

        $von   = (string) ($g['begonnen'] ?? '');
        if ($von === '') { return []; }
        $dauer = max(60, (int) ($g['sekunden'] ?? 0) + 120);
        $bis   = date('Y-m-d H:i:s', strtotime($von) + $dauer);

        $offen = [];

        /* Ein Rückruf, den niemand abgehakt hat. Erkannt genau wie in
           Telefon::rueckrufe() -- eine zweite Wahrheit über „erledigt" wäre
           die schlimmste Art von Fehler hier. */
        $rueck = (array) self::still(static fn() => Db::all(
            "SELECT a.id, a.meta FROM activities a
              WHERE a.type IN ('telefon_melde', 'telefon_hilfe') AND a.demo = 0
                AND a.created_at >= ? AND a.created_at <= ?
                AND NOT EXISTS (
                    SELECT 1 FROM activities e
                     WHERE e.type = 'telefon_rueckruf_erledigt'
                       AND e.meta LIKE CONCAT('%\"quelle\":\"', a.id, '\"%'))",
            [$von, $bis]), []);
        foreach ($rueck as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            $art = is_array($m) ? (string) ($m['art'] ?? '') : '';
            if (in_array($art, ['rueckruf', 'beschwerde', 'nachricht'], true)) {
                $wer = trim((string) ($m['name'] ?? $m['nummer'] ?? ''));
                $offen[] = 'Ein Rückruf ist noch nicht abgehakt'
                         . ($wer !== '' ? ' (' . $wer . ')' : '') . '.';
            }
        }

        /* Eine Frage, die Manuela nicht beantworten konnte und die noch
           niemand weggeräumt hat. Verglichen wird der Wortlaut: Die Spur
           trägt keinen Schlüssel, und „irgendwo gibt es offene Fragen" wäre
           bei jedem Gespräch wahr und damit keine Warnung. */
        $luecken = (array) self::still(static fn() => Db::all(
            "SELECT meta FROM activities
              WHERE type = 'telefon_wissensluecke' AND demo = 0
                AND created_at >= ? AND created_at <= ?", [$von, $bis]), []);
        $n = 0;
        foreach ($luecken as $z) {
            $m = json_decode((string) ($z['meta'] ?? ''), true);
            if (is_array($m) && Telefon::lueckeOffen((string) ($m['frage'] ?? ''))) { $n++; }
        }
        if ($n > 0) {
            $offen[] = $n === 1
                ? 'Eine Frage aus diesem Gespräch steht noch auf der Liste „Was Manuela nicht wusste".'
                : $n . ' Fragen aus diesem Gespräch stehen noch auf der Liste „Was Manuela nicht wusste".';
        }

        /* „Angefangen und nichts daraus geworden" -- dieselbe Liste, die auf
           der Telefonseite steht. Wer sie wegräumt, räumt die Erinnerung weg,
           dass da noch etwas hinterherzuschicken wäre. */
        foreach ((array) self::still(static fn() => Telefon::offeneGespraeche(30), []) as $o) {
            $w = (string) ($o['wann'] ?? '');
            if ($w !== '' && $w >= $von && $w <= $bis) {
                $offen[] = 'Hier fing etwas an, aus dem nichts geworden ist — '
                         . 'das Gespräch steht noch auf der Liste „Angefangen und nichts daraus geworden".';
                break;
            }
        }

        return array_values(array_unique($offen));
    }

    /**
     * Gespräche löschen -- samt der eigenen Spur, die dazugehört.
     *
     * Ein Gespräch ohne seine Spur wäre ein halbes Löschen: Der Anruf
     * verschwände aus der Liste, und die Werkzeugaufrufe mit Rufnummer und
     * Adresse blieben in den Aktivitäten stehen.
     *
     * Ohne $auchOffene wird übersprungen, woran noch etwas hängt, und
     * zurückgemeldet, was und warum. Der Aufrufer entscheidet dann -- nicht
     * diese Methode.
     *
     * @param list<string> $ids
     * @return array{weg:int,spur:int,offen:list<array{id:string,betreff:string,gruende:list<string>}>}
     */
    public static function loeschen(array $ids, bool $auchOffene = false): array
    {
        $weg = $spur = 0;
        $offen = [];

        foreach (array_unique(array_map('strval', $ids)) as $id) {
            if (!preg_match('~^[0-9a-fA-F-]{8,40}$~', $id)) { continue; }
            $g = self::still(static fn() => Db::one('SELECT * FROM telefon_gespraeche WHERE id = ?', [$id]), null);
            if (!is_array($g)) { continue; }

            if (!$auchOffene) {
                $gruende = self::offenesZu($g);
                if ($gruende) {
                    $offen[] = ['id' => $id, 'betreff' => (string) ($g['betreff'] ?: 'ohne Betreff'),
                                'wann' => (string) $g['begonnen'], 'gruende' => $gruende];
                    continue;
                }
            }

            $spur += self::spurLoeschen($g);
            self::still(static fn() => Db::run('DELETE FROM telefon_gespraeche WHERE id = ?', [$id]));
            self::sperren($id, 'von Hand');
            $weg++;
        }

        if ($weg > 0) {
            require_once __DIR__ . '/Events.php';
            /* Festgehalten wird, DASS gelöscht wurde -- ohne das, was
               gelöscht wurde. Sonst stünde der Inhalt gleich wieder da, nur
               in einer anderen Tabelle. */
            self::still(static fn() => Events::protokoll('telefon_geloescht',
                $weg . ' Gespräch' . ($weg === 1 ? '' : 'e') . ' gelöscht'
                . ($spur > 0 ? ' (mit ' . $spur . ' Einträgen aus dem Verlauf)' : ''),
                null, null, null, ['anzahl' => $weg, 'spur' => $spur]));
        }

        return ['weg' => $weg, 'spur' => $spur, 'offen' => $offen];
    }

    /**
     * Alle Gespräche, die älter sind als so viele Tage.
     *
     * Der Weg für „einmal aufräumen". Offene Sachen bleiben auch hier stehen,
     * solange sie nicht ausdrücklich mitgenommen werden.
     */
    public static function loeschenAelterAls(int $tage, bool $auchOffene = false): array
    {
        $tage = max(0, min(3650, $tage));
        $ids = array_column((array) self::still(static fn() => Db::all(
            'SELECT id FROM telefon_gespraeche WHERE begonnen < NOW() - INTERVAL ' . $tage . ' DAY'), []), 'id');
        return self::loeschen($ids, $auchOffene);
    }

    /**
     * Alles, was zu einem Kunden gehört -- weil er gelöscht wird.
     *
     * Hier wird nicht gefragt. Wer eine Akte löscht, hat die Frage schon
     * beantwortet, und ein Anruf, der ohne seine Akte stehen bliebe, wäre
     * genau das, was Löschen verhindern soll: der Name verschwindet aus der
     * Kundenliste und bleibt im Telefonprotokoll stehen.
     */
    public static function zuKundeLoeschen(int $kundeId): int
    {
        if ($kundeId <= 0) { return 0; }
        $reihen = (array) self::still(static fn() => Db::all(
            'SELECT * FROM telefon_gespraeche WHERE kunde_id = ?', [$kundeId]), []);
        $n = 0;
        foreach ($reihen as $g) {
            self::spurLoeschen($g);
            self::still(static fn() => Db::run('DELETE FROM telefon_gespraeche WHERE id = ?', [$g['id']]));
            self::sperren((string) $g['id'], 'Kunde gelöscht');
            $n++;
        }
        return $n;
    }

    /** Die Aktivitäten aus dem Zeitfenster dieses Gesprächs. */
    private static function spurLoeschen(array $g): int
    {
        $von = (string) ($g['begonnen'] ?? '');
        if ($von === '') { return 0; }
        $dauer = max(60, (int) ($g['sekunden'] ?? 0) + 120);
        return (int) self::still(static fn() => Db::run(
            "DELETE FROM activities
              WHERE type LIKE 'telefon\\_%'
                AND created_at >= ?
                AND created_at <= DATE_ADD(?, INTERVAL ? SECOND)",
            [$von, $von, $dauer])->rowCount(), 0);
    }

    private static function sperren(string $id, string $grund): void
    {
        self::still(static fn() => Db::run(
            'INSERT INTO telefon_gespraech_weg (id, grund) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE grund = VALUES(grund)', [$id, $grund]));
    }

    /** Wie viele Gespräche gesperrt sind -- steht auf der Seite, damit es niemanden überrascht. */
    public static function gesperrt(): int
    {
        return (int) self::still(static fn() => Db::wert(
            'SELECT COUNT(*) FROM telefon_gespraech_weg', [], 0), 0);
    }

    /** Die Sperre aufheben: Beim nächsten Abgleich kommen sie wieder. */
    public static function sperreLoesen(): int
    {
        return (int) self::still(static fn() => Db::run('DELETE FROM telefon_gespraech_weg')->rowCount(), 0);
    }

    /* ==================================================================== */
    /*  Kleinkram                                                           */
    /* ==================================================================== */

    public static function zuletzt(): string { return self::wert('strato_zuletzt'); }
    public static function fehler(): string  { return self::wert('strato_fehler'); }

    /** @return mixed */
    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    public static function wert(string $k): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''); }
        catch (Throwable $e) { return ''; }
    }

    private static function merken(string $k, string $v): void
    {
        try {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
        } catch (Throwable $e) { /* der Abgleich ist nicht tragend */ }
    }

    private static function kurz(string $s, int $n): string
    {
        return mb_substr(trim($s), 0, $n);
    }

    private static function zeit(string $iso): string
    {
        $t = strtotime($iso);
        return date('Y-m-d H:i:s', $t !== false ? $t : time());
    }

    /**
     * @param bool $alsObjekte Fremdes JSON, das UNVERÄNDERT zurückgeschrieben
     *   wird, muss als Objekt dekodiert werden. Mit assoc=true wird aus
     *   jedem leeren JSON-Objekt {} ein leeres PHP-Array [], und beim
     *   Zurückschreiben steht dort []. Bei Stratos Schemaprüfung ist das
     *   der Unterschied zwischen „läuft" und „Assistent nicht erreichbar".
     * @return array{ok:bool,status:int,daten:mixed}
     */
    private static function abruf(string $art, string $url, string $anon, ?string $token,
                                  mixed $koerper = null, bool $alsObjekte = false): array
    {
        $kopf = ['apikey: ' . $anon, 'Accept: application/json'];
        if ($token !== null) { $kopf[] = 'Authorization: Bearer ' . $token; }
        if ($koerper !== null) { $kopf[] = 'Content-Type: application/json'; }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $art,
            CURLOPT_TIMEOUT        => self::ZEITGRENZE,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER     => $kopf,
            CURLOPT_USERAGENT      => 'vecom-design.it Verwaltung',
        ]);
        if ($koerper !== null) {
            /* Ohne JSON_UNESCAPED_UNICODE käme jeder Umlaut als \u00fc an --
               lesbar für Maschinen, unlesbar für den, der drüben nachsieht. */
            curl_setopt($ch, CURLOPT_POSTFIELDS,
                (string) json_encode($koerper, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $antwort = curl_exec($ch);
        $status  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler  = curl_error($ch);
        curl_close($ch);

        if ($antwort === false) {
            return ['ok' => false, 'status' => 0, 'daten' => ['error' => $fehler]];
        }
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status,
                'daten' => json_decode((string) $antwort, !$alsObjekte)];
    }
}
