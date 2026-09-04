<?php
declare(strict_types=1);

/* ==========================================================================
   Empfehlung.php — Wer jemanden bringt, zahlt weniger fuer die Betreuung.

   ZWEI WEGE, UND BEIDE SIND NOETIG

   Der eine ist der Link mit Code: sauber zuzuordnen, aber nur brauchbar fuer
   Leute, die Links weitergeben. Der andere ist die Frage "Wer hat uns
   empfohlen?" im Konfigurator. Die Kunden hier sind Baecker, Handwerker und
   Wirte in und um Agrigent — die empfehlen am Tresen. Ohne die zweite Frage
   bliebe der groessere Teil der Empfehlungen unsichtbar.

   Ein genannter Name ist keine Zuordnung, sondern ein Hinweis. Er wird
   festgehalten und wartet darauf, dass ein Mensch ihn einem Kunden zuordnet.
   Automatisch zu raten, wer gemeint ist, waere hier der teuerste Fehler:
   Der Rabatt landet beim Falschen, und der Richtige merkt es.

   VERDIENT WIRD BEI DER ANZAHLUNG

   Sobald der Geworbene bezahlt hat, ist die Empfehlung etwas wert. Wird
   zurueckerstattet, verfaellt sie wieder.

   VERLAENGERN STATT STAPELN

   Jede verdiente Empfehlung bringt zwoelf Monate zu fuenfzehn Prozent — die
   zweite haengt sich an die erste an, statt den Satz zu verdoppeln. Sonst
   zahlt jemand mit sieben Empfehlungen irgendwann nichts mehr.

   DER RABATT HAENGT AM KUNDEN, NICHT AM VERTRAG

   Wer noch keine Betreuung hat, verliert seinen Anspruch sonst — dabei ist
   er gerade der, den man halten will. Er liegt am Kunden und greift, sobald
   ein Vertrag beginnt.
   ========================================================================== */
final class Empfehlung
{
    /** Ohne 0/O und 1/I/L: Diese Codes werden am Telefon vorgelesen. */
    private const ZEICHEN = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /* ----------------------------------------------------------------------
       Code
       ---------------------------------------------------------------------- */

    /** Der Empfehlungscode eines Kunden. Entsteht beim ersten Abruf. */
    public static function codeFuer(int $kundeId): string
    {
        $k = Db::one('SELECT id, name, empfehl_code FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { return ''; }
        $vorhanden = trim((string) ($k['empfehl_code'] ?? ''));
        if ($vorhanden !== '') { return $vorhanden; }

        for ($versuch = 0; $versuch < 12; $versuch++) {
            $code = self::bauen((string) $k['name']);
            $schon = Db::wert('SELECT COUNT(*) FROM customers WHERE empfehl_code = ?', [$code], 0);
            if ((int) $schon === 0) {
                Db::update('customers', $kundeId, ['empfehl_code' => $code]);
                return $code;
            }
        }
        return '';
    }

    /**
     * Vier Buchstaben aus dem Namen, drei zufaellige hinterher.
     *
     * Der Namensteil ist nicht Zierde: Uwe sieht am Code, wem er gehoert,
     * ohne nachzuschlagen — und der Kunde erkennt seinen eigenen wieder.
     */
    private static function bauen(string $name): string
    {
        $rein = strtoupper(preg_replace('/[^A-Za-z]/', '', self::entumlauten($name)) ?? '');
        $teil = substr($rein, 0, 4);
        if (strlen($teil) < 4) { $teil = str_pad($teil, 4, 'X'); }

        $zufall = '';
        $max = strlen(self::ZEICHEN) - 1;
        for ($i = 0; $i < 3; $i++) { $zufall .= self::ZEICHEN[random_int(0, $max)]; }
        return $teil . $zufall;
    }

    private static function entumlauten(string $s): string
    {
        return strtr($s, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue',
                          'à'=>'a','è'=>'e','é'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);
    }

    /** Welchem Kunden gehoert dieser Code? */
    public static function kundeZuCode(string $code): ?int
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z0-9]{5,16}$/', $code)) { return null; }
        $id = Db::wert('SELECT id FROM customers WHERE empfehl_code = ?', [$code], 0);
        return (int) $id ?: null;
    }

    /* ----------------------------------------------------------------------
       Festhalten
       ---------------------------------------------------------------------- */

    /**
     * Haelt fest, dass hinter diesem Bedarf eine Empfehlung steckt.
     *
     * Wird nach dem Absenden gerufen und darf nichts kaputtmachen: Der Bedarf
     * steht bereits, die Anfrage auch. Scheitert das hier, fehlt eine
     * Gutschrift — das laesst sich nachtragen, ein verlorener Auftrag nicht.
     */
    public static function vormerken(int $bedarfId, ?int $anfrageId, ?int $geworbenerId,
                                     string $code, string $genanntAls): ?int
    {
        $code       = strtoupper(trim($code));
        $genanntAls = trim($genanntAls);
        if ($code === '' && $genanntAls === '') { return null; }

        $empfehlerId = $code !== '' ? self::kundeZuCode($code) : null;

        // Sich selbst zu empfehlen ist keine Empfehlung.
        if ($empfehlerId !== null && $geworbenerId !== null && $empfehlerId === $geworbenerId) {
            return null;
        }

        return Db::insert('empfehlungen', [
            'empfehler_id'  => $empfehlerId,
            'geworbener_id' => $geworbenerId,
            'bedarf_id'     => $bedarfId,
            'anfrage_id'    => $anfrageId,
            'code'          => mb_substr($code, 0, 16),
            'quelle'        => $code !== '' ? 'link' : 'genannt',
            'genannt_als'   => mb_substr($genanntAls, 0, 160),
            'status'        => 'offen',
        ]);
    }

    /** Haengt eine offene Empfehlung an die Bestellung, die daraus wurde. */
    public static function anBestellung(int $geworbenerId, int $orderId): void
    {
        try {
            Db::run("UPDATE empfehlungen SET order_id = ?
                      WHERE geworbener_id = ? AND order_id IS NULL AND status = 'offen'",
                    [$orderId, $geworbenerId]);
        } catch (Throwable $e) { /* nachtragbar */ }
    }

    /* ----------------------------------------------------------------------
       Verdienen und verfallen
       ---------------------------------------------------------------------- */

    /**
     * Eine Zahlung ist eingegangen — macht das eine Empfehlung wertvoll?
     *
     * Nur Empfehlungen mit einem zugeordneten Empfehler koennen verdienen.
     * Eine bloss genannte, noch niemandem zugeordnete bleibt offen stehen,
     * bis ein Mensch sie zuordnet. Danach greift dieselbe Stelle erneut.
     */
    public static function beiZahlung(int $orderId): void
    {
        try {
            $offene = Db::all(
                "SELECT * FROM empfehlungen
                  WHERE order_id = ? AND status = 'offen' AND empfehler_id IS NOT NULL",
                [$orderId]
            );
            foreach ($offene as $e) {
                Db::update('empfehlungen', (int) $e['id'], [
                    'status'      => 'verdient',
                    'verdient_am' => date('Y-m-d H:i:s'),
                ]);
                self::neuBerechnen((int) $e['empfehler_id']);

                $wer = (string) Db::wert('SELECT name FROM customers WHERE id = ?', [(int) $e['empfehler_id']], '');
                Events::melden('empfehlung_verdient', 'Empfehlungsrabatt verdient', 'gut',
                    $wer . ' bekommt ' . self::prozent() . ' Prozent auf die Betreuung',
                    '/kunden/' . (int) $e['empfehler_id']);
            }
        } catch (Throwable $e) { /* Gutschrift laesst sich nachtragen */ }
    }

    /** Rueckerstattung: die Empfehlung verfaellt, der Rabatt wird neu gerechnet. */
    public static function beiRueckerstattung(int $orderId, string $grund = 'Zahlung erstattet'): void
    {
        try {
            $betroffen = Db::all("SELECT * FROM empfehlungen WHERE order_id = ? AND status = 'verdient'", [$orderId]);
            foreach ($betroffen as $e) {
                Db::update('empfehlungen', (int) $e['id'], [
                    'status'       => 'verfallen',
                    'verfallen_am' => date('Y-m-d H:i:s'),
                    'grund'        => mb_substr($grund, 0, 200),
                ]);
                if ($e['empfehler_id'] !== null) { self::neuBerechnen((int) $e['empfehler_id']); }
            }
        } catch (Throwable $e) { /* dann eben von Hand */ }
    }

    /**
     * Rechnet den Rabatt eines Kunden aus allen verdienten Empfehlungen neu.
     *
     * Neu gerechnet statt fortgeschrieben: Faellt eine Empfehlung nachtraeglich
     * weg, muss der Rabatt zurueckgehen. Wer nur addiert, kann das nicht.
     *
     * Die Empfehlungen werden der Reihe nach angehaengt: Die erste laeuft ab
     * ihrem Tag zwoelf Monate, die zweite haengt sich an das Ende der ersten
     * — es sei denn, die ist laengst abgelaufen, dann faengt sie neu an.
     */
    public static function neuBerechnen(int $kundeId): void
    {
        $verdiente = Db::all(
            "SELECT verdient_am FROM empfehlungen
              WHERE empfehler_id = ? AND status = 'verdient' AND verdient_am IS NOT NULL
              ORDER BY verdient_am",
            [$kundeId]
        );

        if (!$verdiente) {
            Db::update('customers', $kundeId, ['rabatt_prozent' => 0, 'rabatt_bis' => null]);
            return;
        }

        $monate = self::monate();
        $bis = null;
        foreach ($verdiente as $v) {
            $ab  = (string) $v['verdient_am'];
            $von = ($bis !== null && $bis > $ab) ? $bis : $ab;
            $bis = date('Y-m-d H:i:s', (int) strtotime("+$monate months", (int) strtotime($von)));
        }

        Db::update('customers', $kundeId, [
            'rabatt_prozent' => self::prozent(),
            'rabatt_bis'     => date('Y-m-d', (int) strtotime((string) $bis)),
        ]);
    }

    /* ----------------------------------------------------------------------
       Abfragen
       ---------------------------------------------------------------------- */

    /**
     * Der heute gueltige Rabatt eines Kunden.
     *
     * @return array{prozent:int,bis:?string,aktiv:bool}
     */
    public static function rabattFuer(int $kundeId): array
    {
        try {
            $k = Db::one('SELECT rabatt_prozent, rabatt_bis FROM customers WHERE id = ?', [$kundeId]);
        } catch (Throwable $e) { $k = null; }
        if (!$k) { return ['prozent' => 0, 'bis' => null, 'aktiv' => false]; }

        $prozent = (int) $k['rabatt_prozent'];
        $bis     = $k['rabatt_bis'] !== null ? (string) $k['rabatt_bis'] : null;
        $aktiv   = $prozent > 0 && $bis !== null && $bis >= date('Y-m-d');
        return ['prozent' => $prozent, 'bis' => $bis, 'aktiv' => $aktiv];
    }

    /** Was ein Monatsbetrag mit dem Rabatt dieses Kunden kostet. */
    public static function aufBetrag(int $kundeId, int $cents): int
    {
        $r = self::rabattFuer($kundeId);
        if (!$r['aktiv'] || $cents <= 0) { return $cents; }
        return (int) round($cents * (100 - $r['prozent']) / 100);
    }

    /** Empfehlungen eines Kunden, neueste zuerst. */
    public static function fuerKunde(int $kundeId): array
    {
        try {
            return Db::all(
                "SELECT e.*, g.name AS geworbener
                   FROM empfehlungen e
                   LEFT JOIN customers g ON g.id = e.geworbener_id
                  WHERE e.empfehler_id = ? ORDER BY e.created_at DESC",
                [$kundeId]
            );
        } catch (Throwable $e) { return []; }
    }

    /** Alles, was noch niemandem zugeordnet ist — die Arbeitsliste. */
    public static function offeneNennungen(): array
    {
        try {
            return Db::all(
                "SELECT e.*, g.name AS geworbener, g.company AS geworbener_firma
                   FROM empfehlungen e
                   LEFT JOIN customers g ON g.id = e.geworbener_id
                  WHERE e.empfehler_id IS NULL AND e.status = 'offen'
                  ORDER BY e.created_at DESC"
            );
        } catch (Throwable $e) { return []; }
    }

    /**
     * Ordnet eine genannte Empfehlung einem Kunden zu.
     *
     * Hat die zugehoerige Bestellung schon bezahlt, wird sofort nachgeholt,
     * was beim Bezahlen nicht ging: Damals gab es noch keinen Empfehler.
     */
    public static function zuordnen(int $empfehlungId, int $empfehlerId): bool
    {
        $e = Db::one('SELECT * FROM empfehlungen WHERE id = ?', [$empfehlungId]);
        if (!$e || $e['status'] !== 'offen') { return false; }
        if ($e['geworbener_id'] !== null && (int) $e['geworbener_id'] === $empfehlerId) { return false; }

        Db::update('empfehlungen', $empfehlungId, ['empfehler_id' => $empfehlerId]);

        if ($e['order_id'] !== null) {
            $bezahlt = (int) Db::wert(
                "SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE order_id = ? AND status = 'bezahlt'",
                [(int) $e['order_id']], 0
            );
            if ($bezahlt > 0) { self::beiZahlung((int) $e['order_id']); }
        }
        return true;
    }

    /* ----------------------------------------------------------------------
       Klein
       ---------------------------------------------------------------------- */

    /* ----------------------------------------------------------------------
       Aufraeumen
       ---------------------------------------------------------------------- */

    /**
     * Empfehlungen, auf die nichts mehr zeigt.
     *
     * Seit dem Loeschen eines Kunden gehen seine Empfehlungen mit. Was davor
     * entstanden ist -- oder was ein Probelauf hinterlassen hat --, liegt
     * weiter da: eine Zeile ohne Empfehler, ohne Geworbenen und ohne Bedarf,
     * die in der Verwaltung als offene Nennung leuchtet und sich nicht
     * zuordnen laesst, weil es niemanden mehr gibt, dem sie gehoeren koennte.
     *
     * Verwaist heisst hier streng: KEINER der fuenf Wege fuehrt noch
     * irgendwohin. Wartet die Nennung nur auf eine Zuordnung, der Geworbene
     * ist aber noch da, bleibt sie -- das ist Arbeit, kein Muell.
     *
     * @param bool $wirklich false zaehlt nur, true raeumt weg
     */
    public static function aufraeumen(bool $wirklich = false): int
    {
        $wo = "(e.geworbener_id IS NULL OR kg.id IS NULL)
           AND (e.empfehler_id  IS NULL OR ke.id IS NULL)
           AND (e.bedarf_id     IS NULL OR b.id  IS NULL)
           AND (e.anfrage_id    IS NULL OR a.id  IS NULL)
           AND (e.order_id      IS NULL OR o.id  IS NULL)";
        $von = "FROM empfehlungen e
                LEFT JOIN customers kg ON kg.id = e.geworbener_id
                LEFT JOIN customers ke ON ke.id = e.empfehler_id
                LEFT JOIN bedarf    b  ON b.id  = e.bedarf_id
                LEFT JOIN anfragen  a  ON a.id  = e.anfrage_id
                LEFT JOIN orders    o  ON o.id  = e.order_id
               WHERE $wo";
        try {
            if (!$wirklich) { return (int) Db::wert("SELECT COUNT(*) $von", [], 0); }
            return Db::run("DELETE e $von")->rowCount();
        } catch (Throwable $e) { return 0; }
    }

    public static function prozent(): int
    {
        return max(0, min(100, (int) self::einstellung('empfehlung_prozent', '15')));
    }

    public static function monate(): int
    {
        return max(1, (int) self::einstellung('empfehlung_monate', '12'));
    }

    private static function einstellung(string $schluessel, string $ersatz): string
    {
        try {
            return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], $ersatz);
        } catch (Throwable $e) { return $ersatz; }
    }
}
