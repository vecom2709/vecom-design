<?php
declare(strict_types=1);

require_once __DIR__ . '/Partner.php';

/**
 * WIE DAS GELD ZUM PARTNER KOMMT
 * ===========================================================================
 *
 * Uwe, 26.09.2026: „Gibt es mehrere Möglichkeiten der Auszahlung, Stripe
 * und noch vieles mehr?“ — Ja: Stripe, SEPA-Überweisung, PayPal, Wise und
 * Verrechnung. Der Partner wählt auf seiner Seite; Uwe schaltet ein, welche
 * es gibt. Ein Weg zählt nur, wenn er technisch da ist (Schlüssel in
 * config.local.php) — ein eingeschalteter Weg ohne Zugang wäre ein
 * Versprechen, das beim ersten Partner platzt.
 *
 * WAS VON ALLEIN GEHT UND WAS NICHT
 *  stripe  automatisch (Partner.php, auszahlenStripe)
 *  paypal  automatisch über die Auszahlungs-Schnittstelle von PayPal
 *  wise    wird automatisch angelegt; ob Wise es ohne Bestätigung in der App
 *          ausführt, entscheidet Wise (starke Kundenauthentifizierung) —
 *          dann steht die Auszahlung „offen“, bis Uwe sie bestätigt
 *  sepa    nie automatisch: Die Verwaltung baut eine SEPA-Datei, Uwe lädt
 *          sie im Online-Banking hoch und bestätigt, wenn die Bank sie
 *          ausgeführt hat
 *  gutschrift  Verrechnung mit einer offenen Rate des Partners als Kunde —
 *          auf Uwes Klick, weil es die Buchhaltung berührt
 *
 * Für alle gilt dasselbe wie für Stripe: Wartezeit, Mindestbetrag, Tageslimit
 * (für die automatischen), Beleg, und bei späterer Erstattung Rückforderung.
 */
final class PartnerWege
{
    public const WEGE = [
        'stripe'     => 'Stripe',
        'sepa'       => 'SEPA-Überweisung',
        'paypal'     => 'PayPal',
        'wise'       => 'Wise',
        'gutschrift' => 'Verrechnung',
    ];

    /** Wege, die ohne Klick rausgehen dürfen (Tageslimit gilt). */
    public const AUTOMATISCH = ['stripe', 'paypal', 'wise'];

    /** Nur für die Prüfkette: ersetzt PayPal- und Wise-Aufrufe. @var (Closure(string,string,string,?array):array)|null */
    public static ?Closure $httpProbe = null;

    /* ==================================================================== */
    /*  Welche Wege gibt es                                                 */
    /* ==================================================================== */

    /** Eingeschaltet UND technisch möglich. @return list<string> */
    public static function eingeschaltet(): array
    {
        $an = array_map('trim', explode(',', Partner::einstellung('partner_wege') ?: 'stripe,sepa,paypal,wise,gutschrift'));
        return array_values(array_filter(array_keys(self::WEGE), static fn($w) => in_array($w, $an, true) && self::technisch($w)));
    }

    public static function technisch(string $weg): bool
    {
        return match ($weg) {
            // Ohne Connect kein Partnerkonto: dann wird Stripe gar nicht erst angeboten.
            'stripe' => Partner::einstellung('partner_stripe_connect') !== 'fehlt'
                        && (Partner::$stripeProbe !== null || self::stripeBereit()),
            'paypal' => self::$httpProbe !== null || (self::cfg('paypal', 'client_id') !== '' && self::cfg('paypal', 'secret') !== ''),
            'wise'   => self::$httpProbe !== null || (self::cfg('wise', 'token') !== '' && self::cfg('wise', 'profil') !== ''),
            'sepa', 'gutschrift' => true,
            default  => false,
        };
    }

    /** Was dieser Partner wählen kann. Verrechnung nur, wer selbst Kunde ist. */
    public static function fuerPartner(array $p): array
    {
        return array_values(array_filter(self::eingeschaltet(),
            static fn($w) => $w !== 'gutschrift' || (int) ($p['customer_id'] ?? 0) > 0));
    }

    /** Der Weg, der für ihn gilt: seine Wahl, sonst Stripe, sonst der erste. */
    public static function weg(array $p): ?string
    {
        $moeglich = self::fuerPartner($p);
        $wahl = (string) ($p['auszahlungsweg'] ?? '');
        if ($wahl !== '' && in_array($wahl, $moeglich, true)) { return $wahl; }
        if (in_array('stripe', $moeglich, true)) { return 'stripe'; }
        return $moeglich[0] ?? null;
    }

    /** Hat der Partner alles angegeben, was dieser Weg braucht? */
    public static function bereit(array $p, ?string $weg = null): bool
    {
        $weg ??= self::weg($p);
        return match ($weg) {
            'stripe'     => !empty($p['stripe_bereit']),
            'sepa', 'wise' => (string) ($p['iban_blob'] ?? '') !== '' && trim((string) ($p['kontoinhaber'] ?? '')) !== '',
            'paypal'     => filter_var((string) ($p['paypal_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false,
            'gutschrift' => (int) ($p['customer_id'] ?? 0) > 0,
            default      => false,
        };
    }

    /* ==================================================================== */
    /*  Der Partner wählt                                                   */
    /* ==================================================================== */

    /** @return ?string Fehlerschlüssel (Texte::PARTNER) oder null */
    public static function setzen(int $partnerId, array $d): ?string
    {
        $p = Partner::laden($partnerId);
        if (!$p) { return 'panne'; }
        $weg = (string) ($d['weg'] ?? '');
        if (!in_array($weg, self::fuerPartner($p), true)) { return 'panne'; }
        $neu = ['auszahlungsweg' => $weg];

        if ($weg === 'sepa' || $weg === 'wise') {
            $inhaber = mb_substr(trim((string) ($d['kontoinhaber'] ?? '')), 0, 160);
            $iban = self::ibanNorm((string) ($d['iban'] ?? ''));
            if ($iban === '' && (string) ($p['iban_blob'] ?? '') !== '') {
                // leer = unverändert (die gespeicherte wird nie ausgeliefert)
            } elseif (!self::ibanGueltig($iban)) {
                return 'iban_falsch';
            } else {
                require_once __DIR__ . '/Hosting.php';
                $blob = Hosting::versiegeln(['iban' => $iban]);
                if ($blob === null) { return 'panne'; }
                $neu['iban_blob'] = $blob;
                $neu['iban_ende'] = substr($iban, -4);
                $neu['wise_empfaenger'] = null;           // neue IBAN = neuer Empfänger bei Wise
            }
            if ($inhaber === '') { return 'inhaber_fehlt'; }
            $neu['kontoinhaber'] = $inhaber;
        }
        if ($weg === 'paypal') {
            $mail = mb_strtolower(trim((string) ($d['paypal_email'] ?? '')));
            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) { return 'email_falsch'; }
            $neu['paypal_email'] = $mail;
        }
        Db::update('partner', $partnerId, $neu);
        $spur = $neu;
        unset($spur['iban_blob']);                        // die IBAN nie in die Prüfspur
        Events::pruefspur('partner_auszahlungsweg', 'partner', $partnerId, ['auszahlungsweg' => $p['auszahlungsweg']], $spur);
        return null;
    }

    public static function ibanNorm(string $iban): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $iban));
    }

    /** Prüfsumme nach ISO 13616 (mod 97). */
    public static function ibanGueltig(string $iban): bool
    {
        $iban = self::ibanNorm($iban);
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) { return false; }
        $umgestellt = substr($iban, 4) . substr($iban, 0, 4);
        $zahl = '';
        foreach (str_split($umgestellt) as $z) { $zahl .= ctype_alpha($z) ? (string) (ord($z) - 55) : $z; }
        $rest = 0;
        foreach (str_split($zahl, 7) as $teil) { $rest = (int) (($rest . $teil) % 97); }
        return $rest === 1;
    }

    private static function iban(array $p): string
    {
        require_once __DIR__ . '/Hosting.php';
        return (string) (Hosting::entsiegeln((string) ($p['iban_blob'] ?? ''))['iban'] ?? '');
    }

    /* ==================================================================== */
    /*  Auszahlen — ein Eingang für alle Wege                               */
    /* ==================================================================== */

    /** @return array{ok:bool,text:string,betrag?:int,offen?:bool} */
    public static function auszahlen(int $partnerId, bool $automatisch = false): array
    {
        $p = Partner::laden($partnerId);
        if (!$p) { return ['ok' => false, 'text' => 'Partner nicht gefunden.']; }
        $weg = self::weg($p);
        if ($weg === null) { return ['ok' => false, 'text' => 'Kein Auszahlungsweg eingeschaltet.']; }
        if ($automatisch && !in_array($weg, self::AUTOMATISCH, true)) {
            return ['ok' => false, 'text' => self::WEGE[$weg] . ' geht nie von allein.'];
        }
        if ($weg === 'stripe') { return Partner::auszahlenStripe($partnerId, $automatisch); }
        if ($weg === 'sepa' || $weg === 'gutschrift') {
            return ['ok' => false, 'text' => $weg === 'sepa' ? 'SEPA: über die Datei unter „Partner“ auszahlen.' : 'Verrechnung: eine offene Rate wählen.'];
        }

        $v = self::vorpruefen($p, $weg);
        if (!$v['ok']) { return $v; }
        return $weg === 'paypal' ? self::paypal($p, $v['liste'], $v['summe'], $automatisch)
                                 : self::wise($p, $v['liste'], $v['summe'], $automatisch);
    }

    /**
     * Was für alle Wege gilt: Vereinbarung, Angaben, Mindestbetrag — und
     * nur Provisionen, deren Kundenzahlung JETZT noch bezahlt ist.
     *
     * @return array{ok:bool,text?:string,liste?:list<array>,summe?:int}
     */
    private static function vorpruefen(array $p, string $weg): array
    {
        require_once __DIR__ . '/Fmt.php';
        if (empty($p['vereinbarung_am'])) { return ['ok' => false, 'text' => 'Die Partnervereinbarung ist noch nicht bestätigt.']; }
        if (!self::bereit($p, $weg)) { return ['ok' => false, 'text' => 'Für ' . self::WEGE[$weg] . ' fehlen die Angaben des Partners.']; }
        $liste = Db::all("SELECT pp.*, z.status AS zstatus FROM partner_provisionen pp JOIN payments z ON z.id = pp.payment_id
                           WHERE pp.partner_id = ? AND pp.status = 'bereit' ORDER BY pp.id", [(int) $p['id']]);
        foreach ($liste as $i => $x) {
            if ($x['zstatus'] !== 'bezahlt') {
                Db::run("UPDATE partner_provisionen SET status = 'storniert', grund = 'Zahlung nicht mehr bezahlt' WHERE id = ?", [(int) $x['id']]);
                unset($liste[$i]);
            }
        }
        $liste = array_values($liste);
        $summe = array_sum(array_map(static fn($x) => (int) $x['provision_cents'] - (int) $x['einbehalt_cents'], $liste));
        if ($summe <= 0) { return ['ok' => false, 'text' => 'Nichts auszuzahlen.']; }
        if ($summe < Partner::zahl('partner_mindest_cents')) {
            return ['ok' => false, 'text' => 'Noch unter dem Mindestbetrag (' . Fmt::geld(Partner::zahl('partner_mindest_cents')) . ').'];
        }
        return ['ok' => true, 'liste' => $liste, 'summe' => $summe];
    }

    /* ---------------- PayPal ---------------- */

    /**
     * Eine Sammelauszahlung mit einem Posten. Die Kennung des Stapels setzt
     * sich aus den Provisionsnummern zusammen: Schickt ein Wiederholungslauf
     * dieselben Provisionen noch einmal, lehnt PayPal den doppelten Stapel ab.
     */
    private static function paypal(array $p, array $liste, int $summe, bool $auto): array
    {
        require_once __DIR__ . '/Fmt.php';
        $ids = array_map(static fn($x) => (int) $x['id'], $liste);
        $stapel = 'vecom-' . $p['id'] . '-' . substr(hash('sha256', implode(',', $ids)), 0, 16);
        $r = self::paypalAufruf('POST', '/v1/payments/payouts', [
            'sender_batch_header' => ['sender_batch_id' => $stapel, 'email_subject' => 'Vecom Design — Provision'],
            'items' => [[
                'recipient_type' => 'EMAIL', 'receiver' => (string) $p['paypal_email'],
                'amount' => ['value' => number_format($summe / 100, 2, '.', ''), 'currency' => 'EUR'],
                'note' => 'Provision Vecom Design', 'sender_item_id' => $stapel,
            ]],
        ]);
        $id = (string) ($r['batch_header']['payout_batch_id'] ?? '');
        if ($id === '') {
            $f = (string) ($r['message'] ?? $r['error_description'] ?? 'PayPal lehnte ab');
            Events::melden('partner_auszahlung_fehler', 'PayPal-Auszahlung gescheitert: ' . $p['name'], 'warnung', $f, '/partner/' . (int) $p['id']);
            return ['ok' => false, 'text' => 'PayPal: ' . $f];
        }
        self::alsAusgezahlt($ids);
        $aid = Partner::auszahlungBuchen((int) $p['id'], $summe, 'paypal', 'PayPal ' . $id, $auto, $ids, 'erledigt', $id);
        Partner::schreiben((int) $p['id'], 'partner_auszahlung', ['betrag' => Fmt::geld($summe)]);
        Events::protokoll('partner_auszahlung', 'Provision ausgezahlt (PayPal' . ($auto ? ', automatisch' : '') . '): '
            . Fmt::geld($summe) . ' an ' . $p['name'], null, null, null, ['partner_id' => (int) $p['id'], 'auszahlung_id' => $aid]);
        return ['ok' => true, 'text' => Fmt::geld($summe) . ' über PayPal ausgezahlt.', 'betrag' => $summe];
    }

    private static function paypalAufruf(string $m, string $weg, ?array $rumpf): array
    {
        $basis = self::cfg('paypal', 'modus') === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        if (self::$httpProbe !== null) { return (self::$httpProbe)('paypal', $m, $weg, $rumpf); }
        $t = self::http('POST', $basis . '/v1/oauth2/token', ['Accept: application/json'], 'grant_type=client_credentials',
                        self::cfg('paypal', 'client_id') . ':' . self::cfg('paypal', 'secret'));
        $token = (string) ($t['access_token'] ?? '');
        if ($token === '') { return ['message' => 'PayPal-Zugang wird nicht angenommen.']; }
        return self::http($m, $basis . $weg, ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
                          $rumpf !== null ? (string) json_encode($rumpf) : null);
    }

    /* ---------------- Wise ---------------- */

    /**
     * Angebot → Empfänger (einmal je IBAN) → Überweisung → aus dem
     * Wise-Guthaben bezahlen. Verlangt Wise dafür eine Bestätigung in der
     * App, bleibt die Überweisung angelegt und die Auszahlung „offen“.
     */
    private static function wise(array $p, array $liste, int $summe, bool $auto): array
    {
        require_once __DIR__ . '/Fmt.php';
        $profil = self::cfg('wise', 'profil') ?: 'probe';
        $ids = array_map(static fn($x) => (int) $x['id'], $liste);

        $empf = (string) ($p['wise_empfaenger'] ?? '');
        if ($empf === '') {
            $a = self::wiseAufruf('POST', '/v1/accounts', ['profile' => $profil, 'accountHolderName' => (string) $p['kontoinhaber'],
                'currency' => 'EUR', 'type' => 'iban', 'details' => ['legalType' => 'PRIVATE', 'IBAN' => self::iban($p)]]);
            $empf = (string) ($a['id'] ?? '');
            if ($empf === '') { return self::wiseFehler($p, $a); }
            Db::run('UPDATE partner SET wise_empfaenger = ? WHERE id = ?', [$empf, (int) $p['id']]);
        }
        $q = self::wiseAufruf('POST', '/v3/profiles/' . $profil . '/quotes', ['sourceCurrency' => 'EUR', 'targetCurrency' => 'EUR',
            'targetAmount' => round($summe / 100, 2), 'targetAccount' => (int) $empf]);
        if (!isset($q['id'])) { return self::wiseFehler($p, $q); }
        $kennung = self::uuid('vecom-wise-' . implode(',', $ids));
        $t = self::wiseAufruf('POST', '/v1/transfers', ['targetAccount' => (int) $empf, 'quoteUuid' => (string) $q['id'],
            'customerTransactionId' => $kennung, 'details' => ['reference' => 'Provision Vecom']]);
        if (!isset($t['id'])) { return self::wiseFehler($p, $t); }

        $f = self::wiseAufruf('POST', '/v3/profiles/' . $profil . '/transfers/' . $t['id'] . '/payments', ['type' => 'BALANCE']);
        $bezahlt = ($f['status'] ?? '') === 'COMPLETED';
        if ($bezahlt) {
            self::alsAusgezahlt($ids);
            $aid = Partner::auszahlungBuchen((int) $p['id'], $summe, 'wise', 'Wise ' . $t['id'], $auto, $ids, 'erledigt', (string) $t['id']);
            Partner::schreiben((int) $p['id'], 'partner_auszahlung', ['betrag' => Fmt::geld($summe)]);
            return ['ok' => true, 'text' => Fmt::geld($summe) . ' über Wise ausgezahlt.', 'betrag' => $summe];
        }
        self::alsUnterwegs($ids);
        $aid = Partner::auszahlungBuchen((int) $p['id'], $summe, 'wise', 'Wise ' . $t['id'], $auto, $ids, 'offen', (string) $t['id']);
        Events::melden('partner_wise', 'Wise: Überweisung an ' . $p['name'] . ' bestätigen', 'hinweis',
            Fmt::geld($summe) . ' sind bei Wise angelegt. Wise verlangt die Bestätigung in der App; danach unter Partner „ausgeführt“ klicken.',
            '/partner/' . (int) $p['id']);
        return ['ok' => true, 'offen' => true, 'text' => 'Bei Wise angelegt — bitte in der Wise-App bestätigen.', 'betrag' => $summe];
    }

    private static function wiseFehler(array $p, array $r): array
    {
        $f = (string) ($r['errors'][0]['message'] ?? $r['message'] ?? $r['error'] ?? 'Wise lehnte ab');
        Events::melden('partner_auszahlung_fehler', 'Wise-Auszahlung gescheitert: ' . $p['name'], 'warnung', $f, '/partner/' . (int) $p['id']);
        return ['ok' => false, 'text' => 'Wise: ' . $f];
    }

    private static function wiseAufruf(string $m, string $weg, ?array $rumpf): array
    {
        if (self::$httpProbe !== null) { return (self::$httpProbe)('wise', $m, $weg, $rumpf); }
        $basis = self::cfg('wise', 'modus') === 'sandbox' ? 'https://api.sandbox.transferwise.tech' : 'https://api.wise.com';
        return self::http($m, $basis . $weg, ['Authorization: Bearer ' . self::cfg('wise', 'token'), 'Content-Type: application/json'],
                          $rumpf !== null ? (string) json_encode($rumpf) : null);
    }

    /* ---------------- SEPA ---------------- */

    /**
     * Alle fälligen SEPA-Auszahlungen als eine Datei (pain.001.001.03).
     * Mit dem Erzeugen werden sie „offen“ und ihre Provisionen „unterwegs“ —
     * ein zweites Herunterladen nimmt sie nicht noch einmal auf.
     *
     * @return array{ok:bool,text:string,xml?:string,anzahl?:int,summe?:int}
     */
    public static function sepaDatei(): array
    {
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Fmt.php';
        $eigeneIban = self::ibanNorm(Firma::get('iban'));
        if (!self::ibanGueltig($eigeneIban)) {
            return ['ok' => false, 'text' => 'Deine eigene IBAN fehlt oder stimmt nicht (Einstellungen → Firma).'];
        }
        $posten = [];
        foreach (Db::all("SELECT * FROM partner WHERE status IN ('aktiv','pausiert')") as $p) {
            if (self::weg($p) !== 'sepa') { continue; }
            $v = self::vorpruefen($p, 'sepa');
            if (!$v['ok']) { continue; }
            $posten[] = [$p, $v['liste'], $v['summe']];
        }
        if (!$posten) { return ['ok' => false, 'text' => 'Keine SEPA-Auszahlung fällig.']; }

        $x = static fn(string $s): string => htmlspecialchars(self::sepaZeichen($s), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $nachricht = 'VECOM-' . date('YmdHis');
        $gesamt = array_sum(array_column($posten, 2));
        $bloecke = '';
        foreach ($posten as [$p, $liste, $summe]) {
            $ids = array_map(static fn($z) => (int) $z['id'], $liste);
            self::alsUnterwegs($ids);
            $aid = Partner::auszahlungBuchen((int) $p['id'], $summe, 'sepa', 'SEPA-Datei ' . $nachricht, false, $ids, 'offen', $nachricht);
            $nr = (string) Db::wert('SELECT nummer FROM partner_auszahlungen WHERE id = ?', [$aid], '');
            $bloecke .= '<CdtTrfTxInf><PmtId><EndToEndId>' . $x($nr) . '</EndToEndId></PmtId>'
                . '<Amt><InstdAmt Ccy="EUR">' . number_format($summe / 100, 2, '.', '') . '</InstdAmt></Amt>'
                . '<Cdtr><Nm>' . $x(mb_substr((string) $p['kontoinhaber'], 0, 70)) . '</Nm></Cdtr>'
                . '<CdtrAcct><Id><IBAN>' . $x(self::iban($p)) . '</IBAN></Id></CdtrAcct>'
                . '<RmtInf><Ustrd>' . $x('Provision Vecom Design ' . $nr) . '</Ustrd></RmtInf></CdtTrfTxInf>';
        }
        $name = $x(mb_substr(Firma::get('name') ?: 'Vecom Design', 0, 70));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03"><CstmrCdtTrfInitn>'
            . '<GrpHdr><MsgId>' . $nachricht . '</MsgId><CreDtTm>' . date('Y-m-d\TH:i:s') . '</CreDtTm>'
            . '<NbOfTxs>' . count($posten) . '</NbOfTxs><CtrlSum>' . number_format($gesamt / 100, 2, '.', '') . '</CtrlSum>'
            . '<InitgPty><Nm>' . $name . '</Nm></InitgPty></GrpHdr>'
            . '<PmtInf><PmtInfId>' . $nachricht . '-1</PmtInfId><PmtMtd>TRF</PmtMtd>'
            . '<NbOfTxs>' . count($posten) . '</NbOfTxs><CtrlSum>' . number_format($gesamt / 100, 2, '.', '') . '</CtrlSum>'
            . '<PmtTpInf><SvcLvl><Cd>SEPA</Cd></SvcLvl></PmtTpInf><ReqdExctnDt>' . date('Y-m-d') . '</ReqdExctnDt>'
            . '<Dbtr><Nm>' . $name . '</Nm></Dbtr><DbtrAcct><Id><IBAN>' . $eigeneIban . '</IBAN></Id></DbtrAcct>'
            . '<DbtrAgt><FinInstnId><Othr><Id>NOTPROVIDED</Id></Othr></FinInstnId></DbtrAgt><ChrgBr>SLEV</ChrgBr>'
            . $bloecke . '</PmtInf></CstmrCdtTrfInitn></Document>';
        Events::protokoll('partner_sepa', 'SEPA-Datei erzeugt: ' . count($posten) . ' Überweisung(en), ' . Fmt::geld($gesamt));
        return ['ok' => true, 'text' => '', 'xml' => $xml, 'anzahl' => count($posten), 'summe' => $gesamt];
    }

    /** Nur Zeichen, die jede Bank im SEPA-Zeichensatz annimmt. */
    private static function sepaZeichen(string $s): string
    {
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
                        'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', '’' => "'", '—' => '-', '–' => '-']);
        return (string) preg_replace("~[^A-Za-z0-9/\\-?:().,'+ ]~", '', $s);
    }

    /** Was fällig ist, aber nie von allein geht (SEPA, Verrechnung). @return list<array{partner:array,weg:string,summe:int}> */
    public static function handarbeit(): array
    {
        $aus = [];
        foreach (Db::all("SELECT * FROM partner WHERE status IN ('aktiv','pausiert') AND vereinbarung_am IS NOT NULL") as $p) {
            $weg = self::weg($p);
            if (!in_array($weg, ['sepa', 'gutschrift'], true) || !self::bereit($p, $weg)) { continue; }
            $s = Partner::auszahlbar((int) $p['id']);
            if ($s >= Partner::zahl('partner_mindest_cents')) { $aus[] = ['partner' => $p, 'weg' => $weg, 'summe' => $s]; }
        }
        return $aus;
    }

    /* ---------------- Verrechnung ---------------- */

    /**
     * Mit einer offenen Rate des Partners (als Kunde) verrechnen. Die Rate
     * wird geteilt: Der verrechnete Teil gilt als bezahlt (Anbieter
     * „verrechnung“, Referenz = Belegnummer), der Rest bleibt offen. So
     * bleibt der Umsatz in voller Höhe in den Büchern, und die Provision
     * steht als Ausgabe daneben — verrechnen ist Bezahlen, nicht Rabatt.
     */
    public static function verrechnen(int $partnerId, int $zahlungId): array
    {
        require_once __DIR__ . '/Fmt.php';
        $p = Partner::laden($partnerId);
        if (!$p || (int) ($p['customer_id'] ?? 0) <= 0) { return ['ok' => false, 'text' => 'Der Partner ist kein Kunde.']; }
        $z = Db::one('SELECT z.* FROM payments z LEFT JOIN orders o ON o.id = z.order_id LEFT JOIN abos a ON a.id = z.abo_id
                       WHERE z.id = ? AND COALESCE(o.customer_id, a.customer_id) = ?', [$zahlungId, (int) $p['customer_id']]);
        if (!$z || in_array((string) $z['status'], ['bezahlt', 'rueckerstattet', 'teilweise_erstattet', 'storniert', 'abgebrochen'], true)) {
            return ['ok' => false, 'text' => 'Diese Rate ist nicht offen oder gehört nicht diesem Partner.'];
        }
        if (empty($p['vereinbarung_am'])) { return ['ok' => false, 'text' => 'Die Partnervereinbarung ist noch nicht bestätigt.']; }

        /* Ganze Provisionen, bis die Rate voll ist -- eine Provision wird
           nie geteilt, damit jede genau einen Beleg hat. */
        $liste = Db::all("SELECT pp.* FROM partner_provisionen pp JOIN payments x ON x.id = pp.payment_id
                           WHERE pp.partner_id = ? AND pp.status = 'bereit' AND x.status = 'bezahlt' ORDER BY pp.id", [$partnerId]);
        $betrag = 0; $ids = [];
        foreach ($liste as $x) {
            $n = (int) $x['provision_cents'] - (int) $x['einbehalt_cents'];
            if ($betrag + $n > (int) $z['amount_cents']) { continue; }
            $betrag += $n; $ids[] = (int) $x['id'];
        }
        if ($betrag <= 0) { return ['ok' => false, 'text' => 'Keine Provision passt in diese Rate.']; }

        $aid = Partner::auszahlungBuchen($partnerId, $betrag, 'gutschrift', 'Verrechnet mit Rate #' . $zahlungId, false, $ids, 'erledigt', (string) $zahlungId);
        $nr = (string) Db::wert('SELECT nummer FROM partner_auszahlungen WHERE id = ?', [$aid], '');
        self::alsAusgezahlt($ids);

        if ($betrag >= (int) $z['amount_cents']) {
            $teil = (int) $z['id'];
        } else {
            Db::run('UPDATE payments SET amount_cents = amount_cents - ?, link_url = NULL, link_bis = NULL, provider_sitzung = NULL WHERE id = ?',
                    [$betrag, (int) $z['id']]);
            $teil = Db::insert('payments', ['order_id' => $z['order_id'], 'abo_id' => $z['abo_id'], 'abrechnungsmonat' => $z['abrechnungsmonat'],
                'art' => $z['art'], 'bezeichnung' => mb_substr((string) $z['bezeichnung'] . ' (verrechnet ' . $nr . ')', 0, 190),
                'amount_cents' => $betrag, 'currency' => $z['currency'], 'status' => 'ausstehend', 'faellig_am' => $z['faellig_am']]);
        }
        Events::zahlungBestaetigen($teil, $nr, 'verrechnung');
        Partner::schreiben($partnerId, 'partner_auszahlung', ['betrag' => Fmt::geld($betrag)]);
        Events::pruefspur('partner_verrechnet', 'payment', (int) $z['id'], ['amount_cents' => (int) $z['amount_cents']],
                          ['verrechnet_cents' => $betrag, 'auszahlung' => $nr]);
        return ['ok' => true, 'text' => Fmt::geld($betrag) . ' mit der Rate verrechnet' . ($teil !== (int) $z['id'] ? ' — der Rest bleibt offen.' : '.')];
    }

    /* ---------------- offene Auszahlungen ---------------- */

    /** Die Bank bzw. Wise hat ausgeführt. */
    public static function bestaetigen(int $auszahlungId): bool
    {
        require_once __DIR__ . '/Fmt.php';
        $a = Db::one("SELECT * FROM partner_auszahlungen WHERE id = ? AND status = 'offen'", [$auszahlungId]);
        if (!$a) { return false; }
        Db::run("UPDATE partner_auszahlungen SET status = 'erledigt' WHERE id = ?", [$auszahlungId]);
        Db::run("UPDATE partner_provisionen SET status = 'ausgezahlt', ausgezahlt_am = NOW() WHERE auszahlung_id = ? AND status = 'unterwegs'", [$auszahlungId]);
        Partner::schreiben((int) $a['partner_id'], 'partner_auszahlung', ['betrag' => Fmt::geld((int) $a['betrag_cents'])]);
        Events::pruefspur('partner_auszahlung_bestaetigt', 'partner_auszahlung', $auszahlungId, ['status' => 'offen'], ['status' => 'erledigt']);
        return true;
    }

    /** Doch nicht raus (Datei nicht hochgeladen, Wise abgebrochen): wieder „bereit“. */
    public static function abbrechen(int $auszahlungId): bool
    {
        $a = Db::one("SELECT * FROM partner_auszahlungen WHERE id = ? AND status = 'offen'", [$auszahlungId]);
        if (!$a) { return false; }
        Db::run("UPDATE partner_auszahlungen SET status = 'abgebrochen' WHERE id = ?", [$auszahlungId]);
        Db::run("UPDATE partner_provisionen SET status = 'bereit', auszahlung_id = NULL WHERE auszahlung_id = ? AND status = 'unterwegs'", [$auszahlungId]);
        Events::pruefspur('partner_auszahlung_abgebrochen', 'partner_auszahlung', $auszahlungId, ['status' => 'offen'], ['status' => 'abgebrochen']);
        return true;
    }

    /* ==================================================================== */

    private static function alsAusgezahlt(array $ids): void
    {
        if ($ids) { Db::run("UPDATE partner_provisionen SET status = 'ausgezahlt', ausgezahlt_am = NOW() WHERE id IN (" . implode(',', array_map('intval', $ids)) . ')'); }
    }

    private static function alsUnterwegs(array $ids): void
    {
        if ($ids) { Db::run("UPDATE partner_provisionen SET status = 'unterwegs' WHERE id IN (" . implode(',', array_map('intval', $ids)) . ')'); }
    }

    /** Eine feste UUID aus einem Text — derselbe Text, dieselbe UUID (Wise-Idempotenz). */
    private static function uuid(string $text): string
    {
        $h = md5($text);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-4' . substr($h, 13, 3) . '-a' . substr($h, 17, 3) . '-' . substr($h, 20, 12);
    }

    private static function stripeBereit(): bool
    {
        try {
            require_once __DIR__ . '/Zahlung/Anbieter.php';
            require_once __DIR__ . '/Zahlung/Stripe.php';
            return (new StripeAnbieter())->bereit();
        } catch (Throwable $e) { return false; }
    }

    private static function cfg(string $dienst, string $k): string
    {
        return trim((string) (((array) Config::get($dienst, []))[$k] ?? ''));
    }

    private static function http(string $m, string $url, array $kopf, ?string $rumpf, string $basicAuth = ''): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $m, CURLOPT_HTTPHEADER => $kopf, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25]);
        if ($rumpf !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $rumpf); }
        if ($basicAuth !== '') { curl_setopt($ch, CURLOPT_USERPWD, $basicAuth); }
        $roh = curl_exec($ch);
        $netz = curl_error($ch);
        curl_close($ch);
        if ($roh === false) { return ['message' => 'nicht erreichbar: ' . $netz]; }
        $d = json_decode((string) $roh, true);
        return is_array($d) ? $d : [];
    }
}
