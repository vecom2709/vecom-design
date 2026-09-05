<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Die eine Adresse des Kunden.
 *
 * WAS SIE ERSETZT
 *
 * Bisher bekam der Kunde zwei Links: einen mit der Anfrage und einen mit der
 * Auftragsbestaetigung. Der erste leitete auf den zweiten weiter — nur hing
 * der zweite am Fragebogen. Gab es keinen, gab es auch keine Seite. Und nach
 * dem Onlinegang gab es gar nichts mehr: Wer ein halbes Jahr spaeter eine
 * Aenderung wollte, musste eine alte E-Mail suchen.
 *
 * Der Schluessel gehoert deshalb an den KUNDEN. Eine Adresse, vom ersten
 * Kontakt bis Jahre danach, und sie bleibt dieselbe, wenn er spaeter eine
 * zweite Seite bestellt.
 *
 * WARUM ER NICHT ABLAEUFT
 *
 * Ein Zugang, der ablaeuft, ist genau dann kaputt, wenn man ihn braucht: beim
 * Kunden, der sich nach acht Monaten meldet. Die Seite zeigt nichts, was
 * Schaden anrichtet — kein Geld, keine fremden Daten, keine Verwaltung. Wer
 * zahlt, zahlt bei Stripe; wer etwas aendern will, schreibt eine Nachricht,
 * die bei Uwe landet und von ihm beantwortet wird.
 *
 * Bleibt das Weitergeben. Dagegen hilft kein Ablaufdatum, sondern ein Knopf:
 * In der Kundenakte laesst sich der Schluessel zurueckziehen. Dann gilt der
 * alte Link nicht mehr, und der Kunde bekommt den neuen per E-Mail.
 */
final class Kundenzugang
{
    /**
     * Der Schluessel dieses Kunden. Entsteht beim ersten Aufruf.
     *
     * Wichtig: Das erste Anlegen nimmt NICHTS zurueck. Frueher lief es ueber
     * neu(), und weil neu() die alten Anfrage- und Fragebogenlinks entwertet,
     * hat das blosse Anzeigen der Kundenseite dem Kunden den Fragebogenlink
     * unter den Fuessen weggezogen.
     */
    public static function token(int $kundeId): string
    {
        $da = (string) self::still(fn() => Db::wert('SELECT token FROM customers WHERE id = ?', [$kundeId], ''), '');
        if ($da !== '') { return $da; }
        return self::vergeben($kundeId, false);
    }

    /**
     * Einen neuen Schluessel erzeugen — der alte gilt ab sofort nicht mehr,
     * und die alten Anfrage- und Fragebogenlinks auch nicht. Gedacht fuer den
     * Fall, dass ein Kunde den Link weitergegeben hat.
     */
    public static function neu(int $kundeId): string
    {
        return self::vergeben($kundeId, true);
    }

    private static function vergeben(int $kundeId, bool $altesZuruecknehmen): string
    {
        for ($versuch = 0; $versuch < 5; $versuch++) {
            // 24 Byte Zufall, hexadezimal 48 Zeichen. Nicht zu erraten, und
            // weil die Spalte eindeutig ist, faellt eine Doppelung auf.
            $neu = bin2hex(random_bytes(24));
            try {
                Db::run('UPDATE customers SET token = ?, token_seit = NOW() WHERE id = ?', [$neu, $kundeId]);

                if ($altesZuruecknehmen) {
                    // Sonst waere das Zurueckziehen eine Beruhigung ohne
                    // Wirkung: Wer den alten Anfrage- oder Fragebogenlink hat,
                    // wuerde von dort weiterhin auf die neue Seite geleitet.
                    // Der Fragebogen bekommt beim naechsten Aufruf aus der
                    // Verwaltung von allein einen frischen Schluessel.
                    self::still(fn() => Db::run(
                        'UPDATE anfragen SET token = NULL, token_bis = NULL WHERE customer_id = ?', [$kundeId]), null);
                    self::still(fn() => Db::run(
                        'UPDATE questionnaires SET token = NULL WHERE customer_id = ?', [$kundeId]), null);
                }

                return $neu;
            } catch (Throwable $e) { /* sehr unwahrscheinlich: schon vergeben */ }
        }
        throw new RuntimeException('Zugangslink konnte nicht erzeugt werden.');
    }

    /** Der Kunde hinter einem Schluessel — oder null. */
    public static function ausToken(string $token): ?array
    {
        if (!preg_match('~^[a-f0-9]{48}$~', $token)) { return null; }
        return self::still(fn() => Db::one('SELECT * FROM customers WHERE token = ?', [$token]), null);
    }

    /** Die vollstaendige Adresse, die in jede E-Mail gehoert. */
    public static function link(string $token): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/kunde.php?t=' . rawurlencode($token);
    }

    /** Bequem: Adresse zu einer Kundennummer, Schluessel notfalls erzeugend. */
    public static function linkFuer(int $kundeId): string
    {
        return self::link(self::token($kundeId));
    }

    /**
     * Die alten Schluessel fuehren weiter zum Ziel.
     *
     * Links, die schon in Postfaechern liegen, duerfen nicht sterben — weder
     * der an der Anfrage noch der am Fragebogen. Beide Seiten rufen das hier
     * und leiten auf die eine Adresse um.
     *
     * @param string $art 'anfrage' oder 'fragebogen'
     */
    public static function kundeZuAltemToken(string $art, string $token): ?int
    {
        if ($token === '') { return null; }
        $sql = $art === 'anfrage'
            ? 'SELECT customer_id FROM anfragen WHERE token = ?'
            : 'SELECT customer_id FROM questionnaires WHERE token = ?';
        $id = self::still(fn() => Db::wert($sql, [$token], null), null);
        return $id !== null ? (int) $id : null;
    }

    /* ================================================================== */
    /*  Was die Kundenseite zeigt                                         */
    /* ================================================================== */

    /** Von Uwes Stufen auf die des Kunden. Er sieht kein "Angebot", er sieht eine Anzahlung. */
    private const STUFEN = [
        'gespraech'  => 'anfrage',
        'angebot'    => 'angebot',
        'onboarding' => 'angaben',
        'arbeit'     => 'arbeit',
        'vorschau'   => 'entwurf',
        'freigabe'   => 'freigabe',
        'online'     => 'online',
        'fertig'     => 'fertig',
    ];

    /** Die Reihenfolge, wie sie auf der Fortschrittsleiste steht. */
    public const REIHE = ['anfrage', 'angebot', 'angaben', 'arbeit', 'entwurf', 'freigabe', 'online'];

    /**
     * Alles, was die Kundenseite braucht — aus derselben Quelle, aus der
     * auch Uwes Verwaltung liest. Zwei Sichten auf dieselben Tatsachen, nie
     * zwei Wahrheiten.
     *
     * @return array<string,mixed>
     */
    public static function seite(array $kunde): array
    {
        require_once __DIR__ . '/Vorgang.php';
        require_once __DIR__ . '/Texte.php';
        $kid = (int) $kunde['id'];

        // Der juengste Vorgang dieses Kunden: erst eine Bestellung, sonst
        // eine Anfrage, aus der noch keine geworden ist.
        $bid = self::still(fn() => Db::wert(
            'SELECT id FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kid], null), null);
        $aid = $bid === null ? self::still(fn() => Db::wert(
            'SELECT id FROM anfragen WHERE customer_id = ? AND order_id IS NULL ORDER BY id DESC LIMIT 1',
            [$kid], null), null) : null;

        $v = null;
        if ($bid !== null)      { $v = self::still(fn() => Vorgang::laden('b' . (int) $bid), null); }
        elseif ($aid !== null)  { $v = self::still(fn() => Vorgang::laden('a' . (int) $aid), null); }

        $stufe = $v ? (self::STUFEN[$v['stufe']] ?? 'anfrage') : 'anfrage';
        $nr    = array_search($stufe, self::REIHE, true);

        // Die Adresse der Seite: solange sie nicht online ist, der Entwurf.
        //
        // Der Entwurf erscheint erst, wenn er ausdruecklich freigeschaltet ist,
        // nicht schon beim Speichern der Adresse. Sonst waere jeder
        // Zwischenstand sofort offen — und Uwe koennte die Adresse nicht
        // eintragen, bevor er sie zeigen will.
        $vorschau     = '';
        $vorschauFrei = null;
        $abnahmeFrei  = null;
        $live         = '';
        if ($v && $v['projekt_id']) {
            $p = (array) self::still(fn() => Db::one(
                'SELECT * FROM projects WHERE id = ?', [(int) $v['projekt_id']]), []);

            // Zwischen Deploy und naechstem Cronlauf kann die Spalte noch
            // fehlen. Dann gilt die alte Regel weiter — sonst verschwaende
            // eine schon freigegebene Vorschau fuer zehn Minuten, und der
            // Kunde stuende vor einem Knopf, der eben noch da war.
            $spalteDa = array_key_exists('vorschau_frei_am', $p);
            $vorschauFrei = $spalteDa ? ($p['vorschau_frei_am'] ?? null) : ($p['preview_url'] ?? null);
            if ($vorschauFrei !== null && $vorschauFrei !== '') {
                $vorschau = trim((string) ($p['preview_url'] ?? ''));
            }

            /* Der zweite Schalter. Ansehen darf er nach dem ersten, abnehmen
               erst nach diesem -- und abnehmen heisst: Restzahlung faellig,
               Seite geht online, "damit ist es besprochen". Fehlt die Spalte
               noch (zwischen Deploy und Cronlauf), gilt "nicht frei": Lieber
               fehlt der Knopf zehn Minuten, als dass er zu frueh dasteht. */
            $abnahmeFrei = array_key_exists('abnahme_frei_am', $p)
                ? ($p['abnahme_frei_am'] ?? null) : null;
        }
        if ($v && !empty($v['website']['url']) && in_array((string) $v['website']['status'], ['online', 'wird_geprueft'], true)) {
            $live = (string) $v['website']['url'];
        }

        /* Wer dran ist — aus SEINER Sicht, nicht aus Uwes.
           Uwes Arbeitsliste kennt Zwischenschritte, die den Kunden nichts
           angehen: Solange der Fragebogen noch nicht verschickt ist, wartet
           dort ich auf mich selbst. Auf seiner Seite stuende dann "Wir sind
           dran" ueber einem Knopf, den nur er druecken kann — ein Widerspruch,
           den er nicht aufloesen kann. Also entscheidet hier die Stufe: Gibt
           es auf ihr etwas fuer ihn zu tun, ist er dran. */
        $wer = (string) (Texte::KUNDE_STUFEN[$stufe]['wer'] ?? 'wir');
        if ($wer === 'kunde') {
            // ... aber nur, wenn es den Knopf wirklich gibt.
            if ($stufe === 'angaben' && !self::hatFragebogen($v)) { $wer = 'wir'; }
            if (($stufe === 'angebot' || $stufe === 'freigabe') && !self::hatZahllink($v)) { $wer = 'wir'; }
        }
        if ($v && $v['dran'] === Vorgang::NIEMAND) { $wer = 'niemand'; }

        return [
            'kunde'    => $kunde,
            'vorgang'  => $v,
            'stufe'    => $stufe,
            'stufe_nr' => $nr === false ? 0 : (int) $nr,
            'dran'     => $wer,
            'vorschau'      => $vorschau,
            'vorschau_frei' => $vorschauFrei,
            'abnahme_frei'  => $abnahmeFrei,
            'live'          => $live,
            // Der Fallback steht zweimal da, weil der zweite Zugriff sonst
            // eine Meldung ausloest, wenn die Spalte gar nicht mitgelesen wurde.
            'sprache'  => in_array((string) ($kunde['sprache'] ?? 'it'), ['it','de','en'], true)
                          ? (string) ($kunde['sprache'] ?? 'it') : 'it',
        ];
    }

    /** Steht ein Fragebogen bereit, den er ausfuellen kann? */
    private static function hatFragebogen(?array $v): bool
    {
        return $v !== null && !empty($v['fragebogen'])
            && (string) ($v['fragebogen']['status'] ?? '') === 'offen';
    }

    /** Liegt eine offene Zahlung mit Link vor? */
    private static function hatZahllink(?array $v): bool
    {
        foreach ((array) ($v['zahlungen'] ?? []) as $z) {
            if (($z['status'] ?? '') !== 'bezahlt' && !empty($z['link_url'])) { return true; }
        }
        return false;
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
