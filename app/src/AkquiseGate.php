<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Akquise.php';

/**
 * Das Compliance-Gate — und die Sperrliste, die davor steht.
 *
 * DIE REIHENFOLGE IST DER KERN
 *
 *  1. Sperrliste / Widerspruch        → DO_NOT_EMAIL, immer, fuer jeden Kanal
 *  2. Schon einmal kontaktiert        → DO_NOT_EMAIL (keine Zweitansprache)
 *  3. Land unbekannt                  → UNKNOWN
 *  4. Regel aus akq_regeln            → was dort steht
 *  5. keine passende Regel            → UNKNOWN
 *
 * Nichts davon steht als Recht im Code. Der Code kennt nur die Reihenfolge
 * und die Bedeutung der vier Ergebnisse; WAS in Deutschland oder Italien
 * gilt, steht in akq_regeln -- mit Quelle und Pruefdatum, in der
 * Oberflaeche aenderbar. Recht aendert sich, und ein Deploy ist nicht der
 * Ort, an dem man das nachzieht.
 *
 * UND BEI ZWEIFEL: NICHT SENDEN
 *
 * Automatisch verschickt wird nur bei CONTACT_ALLOWED, freigegebener
 * Vorlage, eingeschaltetem Versand, nicht gezogener Notbremse und
 * eingehaltenen Grenzen. Fehlt eines davon, geht nichts raus -- und der
 * verhinderte Versuch wird mit Grund festgehalten, damit man sieht, dass
 * das Gate gearbeitet hat.
 */
final class AkquiseGate
{
    public const ERLAUBT  = 'CONTACT_ALLOWED';
    public const PRUEFEN  = 'REVIEW_REQUIRED';
    public const NICHT    = 'DO_NOT_EMAIL';
    public const UNKLAR   = 'UNKNOWN';

    public const STATUS = [
        self::ERLAUBT => 'Ja, erlaubt',
        self::PRUEFEN => 'Ja, nach kurzer Prüfung',
        self::NICHT   => 'Nein',
        self::UNKLAR  => 'Unklar — lieber nicht',
    ];

    public const KANAELE = [
        'email' => 'E-Mail', 'brief' => 'Brief', 'telefon' => 'Telefon',
        'kontaktformular' => 'Kontaktformular', 'whatsapp' => 'WhatsApp / SMS',
    ];

    /** Kanaele, ueber die das System selbst etwas verschicken koennte. */
    public const AUTOMATISIERBAR = ['email'];

    /* ================================================================== */
    /*  Sperrliste                                                        */
    /* ================================================================== */

    /**
     * Trifft diese Firma die Sperrliste? Liefert den Grund oder null.
     *
     * Geprueft wird ueber alle Merkmale, nicht nur die ID: Wer widerspricht
     * und spaeter unter anderem OSM-Eintrag wieder auftaucht, ist dieselbe
     * Firma. Die E-Mail-Domain zaehlt mit -- info@ und inhaber@ derselben
     * Domain sind derselbe Betrieb.
     */
    public static function trifftSperrliste(array $f): ?string
    {
        $pruefen = [];
        if (!empty($f['domain']))    { $pruefen[] = ['domain', (string) $f['domain']]; }
        if (!empty($f['email'])) {
            $e = mb_strtolower((string) $f['email']);
            $pruefen[] = ['email', $e];
            $d = substr(strrchr($e, '@') ?: '', 1);
            if ($d !== '' && !self::istFreemail($d)) { $pruefen[] = ['domain', $d]; }
        }
        if (!empty($f['telefon']))   { $pruefen[] = ['telefon', (string) $f['telefon']]; }
        if (!empty($f['name_norm'])) {
            $pruefen[] = ['firma', self::firmaSchluessel($f)];
        }
        foreach ($pruefen as [$art, $wert]) {
            $g = Db::wert('SELECT grund FROM akq_sperrliste WHERE art = ? AND wert = ?', [$art, $wert], null);
            if ($g !== null) { return $art . ': ' . $g; }
        }
        return null;
    }

    public static function firmaSchluessel(array $f): string
    {
        return mb_substr((string) ($f['name_norm'] ?? '') . '|' . (string) ($f['plz'] ?? $f['stadt'] ?? ''), 0, 190);
    }

    private static function istFreemail(string $d): bool
    {
        return in_array($d, ['gmail.com', 'libero.it', 'hotmail.com', 'hotmail.it', 'yahoo.com', 'yahoo.it',
            'outlook.com', 'outlook.it', 'virgilio.it', 'alice.it', 'tiscali.it', 'gmx.de', 'gmx.net', 'web.de',
            't-online.de', 'icloud.com', 'live.it', 'live.com', 'email.it', 'tin.it', 'pec.it', 'legalmail.it'], true);
    }

    /**
     * Setzt eine Firma dauerhaft auf DO_NOT_CONTACT -- mit allen Merkmalen.
     *
     * Dauerhaft heisst: Es gibt in der Oberflaeche keinen Knopf, der das
     * zuruecknimmt. Wer einen Widerspruch versehentlich eintraegt, loescht
     * die Zeile bewusst in der Sperrliste -- mit Pruefspur.
     */
    public static function sperren(int $firmaId, string $grund, string $quelle = 'hand'): void
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        $actor = Auth::angemeldet() ? Auth::name() : 'System';
        $grund = mb_substr(trim($grund) ?: 'Keine Kontaktaufnahme gewünscht', 0, 255);
        $eintraege = [['firma', self::firmaSchluessel($f)]];
        if (!empty($f['domain']))  { $eintraege[] = ['domain', (string) $f['domain']]; }
        if (!empty($f['email']))   { $eintraege[] = ['email', mb_strtolower((string) $f['email'])]; }
        if (!empty($f['telefon'])) { $eintraege[] = ['telefon', (string) $f['telefon']]; }
        foreach ($eintraege as [$art, $wert]) {
            Db::run('INSERT IGNORE INTO akq_sperrliste (art, wert, grund, quelle, firma_id, actor) VALUES (?,?,?,?,?,?)',
                [$art, $wert, $grund, $quelle, $firmaId, $actor]);
        }
        Db::update('akq_firmen', $firmaId, [
            'gesperrt' => 1, 'kontakt_status' => 'gesperrt', 'compliance_status' => self::NICHT,
        ]);
        // Offene Entwuerfe duerfen nach einem Widerspruch nicht mehr freigegeben werden.
        Db::run("UPDATE akq_vorlagen SET status = 'verworfen' WHERE firma_id = ? AND status IN ('entwurf','freigegeben')", [$firmaId]);
        Akquise::protokoll($firmaId, 'gesperrt', 'Auf DO_NOT_CONTACT gesetzt: ' . $grund, ['quelle' => $quelle]);
        Events::pruefspur('akquise_sperren', 'akq_firmen', $firmaId, [], ['grund' => $grund, 'quelle' => $quelle]);
    }

    /** Eine Adresse/Domain direkt sperren, auch ohne Firma (z. B. Anruf "nie wieder"). */
    public static function eintragen(string $art, string $wert, string $grund): void
    {
        if (!in_array($art, ['domain', 'email', 'telefon', 'firma'], true)) {
            throw new InvalidArgumentException('Unbekannte Art.');
        }
        $wert = match ($art) {
            'domain'  => Akquise::normDomain($wert) ?? '',
            'email'   => Akquise::normEmail($wert) ?? '',
            'telefon' => Akquise::normTelefon($wert) ?? '',
            default   => mb_strtolower(trim($wert)),
        };
        if ($wert === '') { throw new InvalidArgumentException('Der Wert ist leer oder ungültig.'); }
        Db::run('INSERT IGNORE INTO akq_sperrliste (art, wert, grund, quelle, actor) VALUES (?,?,?,?,?)',
            [$art, $wert, mb_substr($grund ?: 'Von Hand gesperrt', 0, 255), 'hand', Auth::angemeldet() ? Auth::name() : 'System']);
        // Bestehende Firmen mit diesem Merkmal gleich mitsperren.
        $spalte = ['domain' => 'domain', 'email' => 'email', 'telefon' => 'telefon'][$art] ?? null;
        if ($spalte !== null) {
            foreach (Db::all("SELECT id FROM akq_firmen WHERE $spalte = ? AND gesperrt = 0", [$wert]) as $z) {
                self::sperren((int) $z['id'], $grund ?: 'Von Hand gesperrt');
            }
        }
        Events::pruefspur('akquise_sperrliste', 'akq_sperrliste', null, [], ['art' => $art, 'wert' => $wert]);
    }

    /* ================================================================== */
    /*  Das Gate                                                          */
    /* ================================================================== */

    /**
     * @return array{status:string,gruende:list<string>,regel:?array,bedingung:string}
     */
    public static function pruefen(array $f, string $kanal = 'email'): array
    {
        $gruende = [];
        if (!isset(self::KANAELE[$kanal])) {
            return ['status' => self::UNKLAR, 'gruende' => ['Unbekannter Kanal.'], 'regel' => null, 'bedingung' => ''];
        }

        // 1. Sperrliste
        $sperre = (int) ($f['gesperrt'] ?? 0) === 1 ? 'Firma ist gesperrt' : self::trifftSperrliste($f);
        if ($sperre !== null) {
            return ['status' => self::NICHT, 'gruende' => ['Sperrliste: ' . $sperre], 'regel' => null, 'bedingung' => ''];
        }
        if (in_array((string) ($f['kontakt_status'] ?? ''), ['abgelehnt', 'gesperrt'], true)) {
            return ['status' => self::NICHT, 'gruende' => ['Die Firma hat abgelehnt.'], 'regel' => null, 'bedingung' => ''];
        }

        // 2. Keine Zweitansprache -- ausser die Firma hat selbst darum gebeten
        //    (Einwilligung festgehalten oder positive Antwort). Wer am Telefon
        //    sagt "schicken Sie mir das per Mail", soll die Mail bekommen.
        $gebeten = trim((string) ($f['einwilligung'] ?? '')) !== ''
            || in_array((string) ($f['antwort_status'] ?? ''), ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST'], true);
        if (!empty($f['id']) && !$gebeten) {
            $schon = Db::one("SELECT kanal, created_at FROM akq_versand
                               WHERE firma_id = ? AND status IN ('gesendet','von_hand') ORDER BY id DESC LIMIT 1", [(int) $f['id']]);
            if ($schon) {
                return ['status' => self::NICHT, 'gruende' => [
                    'Bereits kontaktiert am ' . date('d.m.Y', strtotime((string) $schon['created_at']))
                    . ' (' . (self::KANAELE[$schon['kanal']] ?? $schon['kanal']) . '). Keine zweite Ansprache.'],
                    'regel' => null, 'bedingung' => ''];
            }
        }

        // 3. Land
        $land = strtoupper((string) ($f['land'] ?? ''));
        if (!in_array($land, ['DE', 'IT'], true)) {
            return ['status' => self::UNKLAR, 'gruende' => ['Für dieses Land ist keine Regel hinterlegt.'], 'regel' => null, 'bedingung' => ''];
        }

        // 4. Regel
        $bedingung = trim((string) ($f['einwilligung'] ?? '')) !== '' ? 'einwilligung'
                   : ((int) ($f['bestandskunde'] ?? 0) === 1 ? 'bestandskunde' : 'ohne');
        $regel = null;
        foreach ([[$land, $kanal], [$land, '*'], ['*', $kanal], ['*', '*']] as [$l, $k]) {
            $regel = Db::one('SELECT * FROM akq_regeln WHERE land = ? AND kanal = ? AND bedingung = ? AND aktiv = 1', [$l, $k, $bedingung]);
            if ($regel) { break; }
        }
        if (!$regel) {
            return ['status' => self::UNKLAR, 'gruende' => ['Keine aktive Regel für ' . $land . ' / ' . self::KANAELE[$kanal]
                . ' / ' . $bedingung . '. Im Zweifel: nicht senden.'], 'regel' => null, 'bedingung' => $bedingung];
        }
        $status = (string) $regel['ergebnis'];
        if (!isset(self::STATUS[$status])) { $status = self::UNKLAR; }
        $gruende[] = (string) $regel['begruendung'];

        // Zusaetze, die nur verschaerfen, nie lockern.
        if ($kanal === 'email' && empty($f['email'])) {
            $gruende[] = 'Keine E-Mail-Adresse bekannt.';
        }
        $b = Akquise::branchen()[(string) ($f['branche'] ?? '')] ?? [];
        if (!empty($b['berufsrecht']) && $status === self::ERLAUBT) {
            $status = self::PRUEFEN;
            $gruende[] = 'Berufsrechtlich geregelte Branche — Text vor dem Versand einzeln prüfen.';
        }
        if (empty($regel['geprueft_am']) || strtotime((string) $regel['geprueft_am']) < strtotime('-12 months')) {
            if ($status === self::ERLAUBT) { $status = self::PRUEFEN; }
            $gruende[] = 'Die Regel wurde seit über 12 Monaten nicht geprüft.';
        }
        return ['status' => $status, 'gruende' => $gruende, 'regel' => $regel, 'bedingung' => $bedingung];
    }

    /**
     * Die Ampel: Darf ich diese Firma ansprechen -- und wie?
     *
     * Fuer Listen gebaut: liest nur die Firma und die (zwischengespeicherten)
     * Regeln, keine Sperrliste und keinen Versand. Das ist erlaubt, weil
     * beides beim Eintragen schon auf die Firma durchgeschrieben wird
     * (gesperrt, kontakt_status). Vor dem eigentlichen Versand prueft
     * pruefen() trotzdem alles einzeln -- die Ampel ist Anzeige, kein Schloss.
     *
     * @return array{farbe:string,wort:string}  farbe: gruen|gelb|rot|grau
     */
    public static function ampel(array $f): array
    {
        if ((int) ($f['gesperrt'] ?? 0) === 1 || in_array((string) ($f['kontakt_status'] ?? ''), ['abgelehnt', 'gesperrt'], true)) {
            return ['farbe' => 'rot', 'wort' => 'Nicht ansprechen'];
        }
        $land = strtoupper((string) ($f['land'] ?? ''));
        $bed = trim((string) ($f['einwilligung'] ?? '')) !== '' ? 'einwilligung' : ((int) ($f['bestandskunde'] ?? 0) === 1 ? 'bestandskunde' : 'ohne');
        $r = static fn(string $kanal) => self::regelErgebnis($land, $kanal, $bed);
        if ($r('email') === self::ERLAUBT && !empty($f['email'])) { return ['farbe' => 'gruen', 'wort' => 'E-Mail erlaubt']; }
        if (in_array((string) ($f['kontakt_status'] ?? ''), ['kontaktiert', 'geantwortet', 'kunde'], true)) {
            return ['farbe' => 'grau', 'wort' => 'Schon kontaktiert'];
        }
        $brief = self::regelErgebnis($land, 'brief', 'ohne');   // Post braucht keine Einwilligung -- die Regel "ohne" gilt fuer alle
        $tel = self::regelErgebnis($land, 'telefon', 'ohne');
        if (in_array($brief, [self::ERLAUBT, self::PRUEFEN], true)) {
            return ['farbe' => 'gelb', 'wort' => in_array($tel, [self::ERLAUBT, self::PRUEFEN], true) && Akquise::deutschsprachig($f)
                ? 'Brief oder Anruf' : 'Per Brief'];
        }
        return ['farbe' => 'grau', 'wort' => 'Unklar'];
    }

    /** @var array<string,string> */
    private static array $regelCache = [];

    private static function regelErgebnis(string $land, string $kanal, string $bedingung): string
    {
        $k = "$land|$kanal|$bedingung";
        if (!isset(self::$regelCache[$k])) {
            $e = null;
            foreach ([[$land, $kanal], [$land, '*'], ['*', $kanal], ['*', '*']] as [$l, $kk]) {
                $e = Db::wert('SELECT ergebnis FROM akq_regeln WHERE land = ? AND kanal = ? AND bedingung = ? AND aktiv = 1', [$l, $kk, $bedingung], null);
                if ($e !== null) { break; }
            }
            self::$regelCache[$k] = (string) ($e ?? self::UNKLAR);
        }
        return self::$regelCache[$k];
    }

    /** Schreibt den Status fuer den Hauptkanal E-Mail an die Firma. */
    public static function statusSpeichern(int $firmaId): string
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { return self::UNKLAR; }
        $p = self::pruefen($f, 'email');
        if ($p['status'] !== (string) $f['compliance_status']) {
            Db::update('akq_firmen', $firmaId, ['compliance_status' => $p['status']]);
            Akquise::protokoll($firmaId, 'compliance', 'Compliance geprüft: ' . self::STATUS[$p['status']],
                ['gruende' => $p['gruende']]);
        }
        return $p['status'];
    }

    /* ================================================================== */
    /*  Grenzen                                                           */
    /* ================================================================== */

    public static function einstellung(string $k, string $vorgabe = ''): string
    {
        return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], $vorgabe);
    }

    public static function setzen(string $k, string $v): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
    }

    public static function grenzen(): array
    {
        return [
            'versand_an'   => self::einstellung('akq_versand_an', '0') === '1',
            'stop'         => self::einstellung('akq_stop', '0') === '1',
            'tag'          => max(0, (int) self::einstellung('akq_limit_tag', '10')),
            'stunde'       => max(0, (int) self::einstellung('akq_limit_stunde', '3')),
            'domain_tage'  => max(1, (int) self::einstellung('akq_limit_domain_tage', '180')),
            'pause'        => max(0, (int) self::einstellung('akq_pause_sekunden', '120')),
            'fehler'       => max(1, (int) self::einstellung('akq_fehler_grenze', '3')),
            'bounce'       => max(1, (int) self::einstellung('akq_bounce_grenze', '2')),
        ];
    }

    /**
     * Darf JETZT eine E-Mail an diese Firma raus? Liefert null oder den Grund dagegen.
     *
     * Die Grenzen zaehlen, was wirklich rausging -- aus akq_versand, nicht
     * aus einem Zaehler, der sich verzaehlen koennte.
     */
    public static function versandSperre(array $f): ?string
    {
        $g = self::grenzen();
        if ($g['stop'])        { return 'Die Notbremse ist gezogen — alle Aussendungen stehen.'; }
        if (!$g['versand_an']) { return 'Der E-Mail-Versand ist ausgeschaltet (Einstellungen der Akquise).'; }
        $tag = (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE status = 'gesendet' AND kanal = 'email' AND created_at >= CURDATE()");
        if ($tag >= $g['tag']) { return "Tageslimit erreicht ($tag von {$g['tag']})."; }
        $std = (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE status = 'gesendet' AND kanal = 'email' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        if ($std >= $g['stunde']) { return "Stundenlimit erreicht ($std von {$g['stunde']})."; }
        $letzte = Db::wert("SELECT MAX(created_at) FROM akq_versand WHERE status = 'gesendet' AND kanal = 'email'", [], null);
        if ($letzte !== null && time() - strtotime((string) $letzte) < $g['pause']) {
            return 'Pause zwischen zwei Aussendungen noch nicht vorbei (' . $g['pause'] . ' s).';
        }
        $fehler = (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE status = 'fehler' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        if ($fehler >= $g['fehler']) { return "Zu viele Fehlschläge in 24 Stunden ($fehler) — erst nachsehen."; }
        $bounce = (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE status = 'bounce' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        if ($bounce >= $g['bounce']) { return "Zu viele unzustellbare Adressen in 7 Tagen ($bounce) — Adressqualität prüfen."; }
        if (!empty($f['domain'])) {
            $d = (int) Db::wert("SELECT COUNT(*) FROM akq_versand v JOIN akq_firmen f ON f.id = v.firma_id
                                   WHERE f.domain = ? AND v.status IN ('gesendet','von_hand')
                                     AND v.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$f['domain'], $g['domain_tage']]);
            if ($d > 0) { return 'An diese Domain ging in den letzten ' . $g['domain_tage'] . ' Tagen schon etwas.'; }
        }
        return null;
    }

    /** Notbremse. Wirkt sofort auf jeden weiteren Versuch. */
    public static function notbremse(bool $ziehen): void
    {
        self::setzen('akq_stop', $ziehen ? '1' : '0');
        Akquise::protokoll(null, 'notbremse', $ziehen ? 'Notbremse gezogen — aller Versand gestoppt' : 'Notbremse gelöst');
        Events::pruefspur($ziehen ? 'akquise_stop' : 'akquise_weiter', 'settings', null);
    }
}
