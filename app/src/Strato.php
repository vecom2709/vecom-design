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
        $roh = self::wert('strato_zugang');
        if ($roh !== '') {
            $d = json_decode($roh, true);
            if (is_array($d) && (int) ($d['bis'] ?? 0) > time() && ($d['token'] ?? '') !== '') {
                return (string) $d['token'];
            }
        }

        $anon    = self::wert('strato_anon');
        $refresh = self::wert('strato_refresh');
        if ($anon === '' || $refresh === '') {
            self::merken('strato_fehler', 'Kein Zugang hinterlegt.');
            return null;
        }

        $a = self::abruf('POST', self::PROJEKT . '/auth/v1/token?grant_type=refresh_token',
                         $anon, null, ['refresh_token' => $refresh]);

        if (!$a['ok'] || !is_array($a['daten']) || ($a['daten']['access_token'] ?? '') === '') {
            $grund = is_array($a['daten'])
                ? (string) ($a['daten']['error_description'] ?? $a['daten']['msg'] ?? $a['daten']['error'] ?? '')
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
        self::merken('strato_zugang', (string) json_encode(['token' => $token, 'bis' => time() + $gilt - 60]));
        self::merken('strato_fehler', '');
        return $token;
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

        $neu = $geaendert = 0;
        foreach ($a['daten'] as $g) {
            if (!is_array($g)) { continue; }
            $r = self::ablegen($g);
            if ($r === 'neu') { $neu++; } elseif ($r === 'geaendert') { $geaendert++; }
        }

        self::merken('strato_zuletzt', date('Y-m-d H:i:s'));
        self::merken('strato_fehler', '');
        return ['ok' => true, 'gesehen' => count($a['daten']), 'neu' => $neu, 'geaendert' => $geaendert];
    }

    /** Einen Satz ablegen. @return 'neu'|'geaendert'|'gleich' */
    private static function ablegen(array $g): string
    {
        $id = (string) ($g['id'] ?? '');
        if ($id === '') { return 'gleich'; }

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

        $vorher = Db::one('SELECT ausgang, betreff, tags FROM telefon_gespraeche WHERE id = ?', [$id]);

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
    /*  Auswertung                                                          */
    /* ==================================================================== */

    /** Was die Schlagworte auf Deutsch heißen. Was hier fehlt, wird roh gezeigt. */
    public const TAG = [
        'incorrect_function_parameters' => 'falsche Angaben an ein Werkzeug',
        'failed_to_follow_tool_guidance' => 'Anweisung des Werkzeugs missachtet',
        'caller_frustration'            => 'Anrufer war genervt',
        'transcription_error'           => 'falsch verstanden',
        'unclear_audio'                 => 'schlechte Verbindung',
        'long_silence'                  => 'lange Stille',
        'repetition'                    => 'hat sich wiederholt',
        'interrupted'                   => 'ins Wort gefallen',
        'wrong_language'                => 'falsche Sprache',
        'hallucination'                 => 'etwas erfunden',
        'missing_information'           => 'Angabe fehlte',
        'tool_error'                    => 'Werkzeug antwortete nicht',
    ];

    public const AUSGANG = [
        'AGENT_ERROR'        => ['Fehler des Assistenten', 'schlecht'],
        'RESOLVED'           => ['erledigt', 'gut'],
        'COMPLETED'          => ['abgeschlossen', 'gut'],
        'INFORMATION_GIVEN'  => ['Auskunft gegeben', 'gut'],
        'CALLBACK_REQUESTED' => ['Rückruf gewünscht', 'gut'],
        'APPOINTMENT_BOOKED' => ['Termin vereinbart', 'gut'],
        'TRANSFERRED'        => ['weitergeleitet', ''],
        'CALLER_HUNG_UP'     => ['aufgelegt', ''],
        'NO_CONVERSATION'    => ['kein Gespräch', ''],
        'UNRESOLVED'         => ['ohne Ergebnis', 'schlecht'],
        'ABANDONED'          => ['abgebrochen', 'schlecht'],
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
    /*  Kleinkram                                                           */
    /* ==================================================================== */

    public static function zuletzt(): string { return self::wert('strato_zuletzt'); }
    public static function fehler(): string  { return self::wert('strato_fehler'); }

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

    /** @return array{ok:bool,status:int,daten:mixed} */
    private static function abruf(string $art, string $url, string $anon, ?string $token, ?array $koerper = null): array
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($koerper));
        }
        $antwort = curl_exec($ch);
        $status  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler  = curl_error($ch);
        curl_close($ch);

        if ($antwort === false) {
            return ['ok' => false, 'status' => 0, 'daten' => ['error' => $fehler]];
        }
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status,
                'daten' => json_decode((string) $antwort, true)];
    }
}
