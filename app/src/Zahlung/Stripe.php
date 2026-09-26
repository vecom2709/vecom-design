<?php
declare(strict_types=1);

/**
 * Stripe — ohne fremde Bibliothek, nur ueber die HTTPS-Schnittstelle.
 *
 * Warum ohne Composer: Der Rest der Anwendung kommt ohne aus, und der
 * FTP-Deploy laedt einfach Dateien hoch. Eine Abhaengigkeit mit eigenem
 * Aktualisierungszyklus waere hier ein Fremdkoerper. Gebraucht werden drei
 * Dinge: eine Bezahlseite anlegen, eine Unterschrift pruefen — und seit dem
 * 13.09.2026 selbst nachfragen, ob eine Bezahlseite bezahlt wurde.
 *
 * Das Nachfragen kam dazu, weil ein Webhook ein Anruf ist, den der ANDERE
 * macht. Faellt er aus, ist er falsch unterschrieben oder gar nicht
 * eingetragen, sieht es hier aus wie "nicht bezahlt" — und niemand merkt
 * etwas. Genau das ist am 13.09.2026 einem Kunden passiert. Siehe
 * sitzungLesen() und Cron::zahlungenAbgleichen().
 *
 * Kartendaten beruehren diesen Server nie — bezahlt wird auf einer Seite,
 * die Stripe selbst ausliefert.
 *
 * Zugangsdaten stehen ausschliesslich in app/config.local.php:
 *
 *   'stripe' => [
 *       'modus'          => 'test',            // oder 'live'
 *       'geheim'         => 'sk_test_…',       // Geheimer Schluessel
 *       'webhook_geheim' => 'whsec_…',         // Signaturgeheimnis des Webhooks
 *   ],
 */
final class StripeAnbieter implements Anbieter
{
    private array $cfg;

    /* Die Nummer der zuletzt erzeugten Bezahlseite (cs_…). Sie ist der
       einzige Faden, an dem der Abgleich spaeter zieht: Ohne sie wissen wir
       nicht, WELCHE Seite zu welcher Rate gehoerte, und koennen nicht
       nachfragen. Die Aufrufer schreiben sie neben den Link in die
       Datenbank (payments.provider_sitzung). */
    private string $letzteSitzung = '';

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? (array) Config::get('stripe', []);
    }

    /** Die Nummer der Bezahlseite aus dem letzten bezahlseite()-Aufruf. */
    public function letzteSitzung(): string { return $this->letzteSitzung; }

    public function schluessel(): string { return 'stripe'; }

    public function modus(): string
    {
        return ($this->cfg['modus'] ?? 'test') === 'live' ? 'live' : 'test';
    }

    /** Nur die Art des Schlüssels (sk_live, rk_test …), nie der Schlüssel selbst. */
    public function schluesselArt(): string
    {
        return preg_match('/^(sk|rk)_(live|test)_/', trim((string) ($this->cfg['geheim'] ?? '')), $t) ? $t[1] . '_' . $t[2] : '';
    }

    public function bereit(): bool
    {
        return trim((string) ($this->cfg['geheim'] ?? '')) !== '';
    }

    public function webhookBereit(): bool
    {
        return trim((string) ($this->cfg['webhook_geheim'] ?? '')) !== '';
    }

    /** Nur zur Anzeige: die letzten vier Zeichen, damit man den Schluessel wiedererkennt. */
    public function schluesselHinweis(): string
    {
        $k = (string) ($this->cfg['geheim'] ?? '');
        if ($k === '') { return '—'; }
        return substr($k, 0, 8) . '…' . substr($k, -4);
    }

    /* ------------------------------------------------------------------ */

    public function bezahlseite(array $zahlung, array $bestellung, array $kunde, ?string $erfolgUrl = null): string
    {
        if (!$this->bereit()) {
            throw new RuntimeException('Für Stripe fehlt der geheime Schlüssel in app/config.local.php.');
        }

        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $marke = (string) Config::get('firma', 'Vecom Design');
        $titel = trim(($zahlung['bezeichnung'] ?: 'Zahlung') . ' · ' . $bestellung['package_name']);

        /* Wohin es nach der Zahlung geht. Standard ist die Danke-Seite; der
           Direktkauf von Domain & Hosting uebergibt hier die persoenliche
           Kundenseite, damit der Kunde nach dem Bezahlen direkt auf seinem
           Dashboard landet — und nicht davor. */
        $erfolg = ($erfolgUrl !== null && trim($erfolgUrl) !== '')
            ? trim($erfolgUrl) : ($basis . '/danke.html?zahlung=ok');

        $felder = [
            'mode'                          => 'payment',
            'client_reference_id'           => (string) $zahlung['id'],
            'customer_email'                => (string) $kunde['email'],
            'success_url'                   => $erfolg,
            'cancel_url'                    => $basis . '/#plans',
            'locale'                        => 'auto',
            'line_items[0][quantity]'       => '1',
            'line_items[0][price_data][currency]'            => strtolower((string) $zahlung['currency']),
            'line_items[0][price_data][unit_amount]'         => (string) (int) $zahlung['amount_cents'],
            'line_items[0][price_data][product_data][name]'  => $titel,
            'line_items[0][price_data][product_data][description]' => $marke . ' · ' . $bestellung['order_no'],
            'metadata[zahlung_id]'          => (string) $zahlung['id'],
            'metadata[bestellung]'          => (string) $bestellung['order_no'],
            'metadata[bestellung_id]'       => (string) $bestellung['id'],
            'payment_intent_data[description]' => $marke . ' · ' . $bestellung['order_no'] . ' · ' . $titel,

            /* MANAGED PAYMENTS AUS -- SONST ENTSTEHT KEINE BEZAHLSEITE
               ------------------------------------------------------------
               Stripe hat "Managed Payments" bei neuen Konten standardmaessig
               an. Damit tritt Stripe selbst als Haendler auf und rechnet die
               Umsatzsteuer ab -- und verlangt dafuer zu jedem Posten einen
               Steuerkode (tax_code). Ohne den lehnt es die Sitzung rundheraus
               ab: "the product tax code is missing", und der Kunde bekommt
               ueberhaupt keinen Zahlungslink. Genau das ist beim ersten
               Durchlauf passiert.

               Einen Steuerkode zu raten waere hier der falsche Ausweg: Solange
               keine Partita IVA da ist, wird auch keine Umsatzsteuer
               ausgewiesen, und was spaeter der richtige Kode ist, entscheidet
               der Commercialista und nicht diese Zeile. Bis dahin also aus --
               dann verhaelt sich Stripe wie eine gewoehnliche Bezahlseite und
               ueberlaesst die Steuer dem Rechnungssteller.

               Kommt die Partita IVA, gehoert diese Stelle noch einmal
               angesehen: Dann ist Managed Payments samt tax_code womoeglich
               die bequemere Loesung. */
            'managed_payments[enabled]'     => 'false',
        ];

        /* DER SCHLUESSEL GEGEN DOPPELTE BEZAHLSEITEN -- UND WARUM ER DIE
           FELDER MITZAEHLEN MUSS
           ----------------------------------------------------------------
           Stripe merkt sich zu jedem Idempotency-Key die Parameter, mit
           denen er zuerst benutzt wurde, und lehnt ihn danach fuer jede
           abweichende Anfrage ab. Der Schluessel war bisher fest
           "zahlung-<id>". Sobald sich an der Anfrage irgendetwas aenderte --
           ein anderer Betrag, eine andere Bezeichnung, eine neue
           Einstellung --, war der Knopf "Neuen Link erzeugen" fuer diese
           Zahlung dauerhaft blockiert: "Keys for idempotent requests can
           only be used with the same parameters." Genau das ist beim
           Durchspielen passiert.

           Mit den Feldern im Schluessel bleibt der Schutz, der gemeint war
           -- zweimal derselbe Klick erzeugt weiterhin nur eine Bezahlseite
           --, waehrend eine wirklich andere Anfrage auch einen anderen
           Schluessel bekommt. */
        $einmalig = 'zahlung-' . $zahlung['id'] . '-' . substr(md5(serialize($felder)), 0, 16);

        $antwort = $this->anfrage('POST', '/v1/checkout/sessions', $felder, $einmalig);

        if (empty($antwort['url'])) {
            $grund = $antwort['error']['message'] ?? 'unbekannter Fehler';
            throw new RuntimeException('Stripe hat keine Bezahlseite geliefert: ' . $grund);
        }
        $this->letzteSitzung = (string) ($antwort['id'] ?? '');
        return (string) $antwort['url'];
    }

    /**
     * Fragt eine Bezahlseite bei Stripe ab — der Rueckweg zum Webhook.
     *
     * Zurueck kommt, was fuer die Buchung zaehlt:
     *   bezahlt   — Stripe sagt payment_status = paid
     *   referenz  — die Nummer des Zahlungsvorgangs (pi_…), fuer den Beleg
     *   offen     — die Sitzung laeuft noch, es ist nur noch nichts passiert
     *   abgelaufen— die Sitzung ist verfallen, hier kommt nichts mehr
     *
     * Faellt Stripe aus oder antwortet mit einem Fehler, wird geworfen. Der
     * Aufrufer soll das sehen und es beim naechsten Lauf noch einmal
     * versuchen — eine stille Null waere hier dasselbe Uebel wie der
     * ausgefallene Webhook.
     */
    public function sitzungLesen(string $sitzungId): array
    {
        if (trim($sitzungId) === '') {
            throw new RuntimeException('Ohne Nummer der Bezahlseite kann nicht nachgefragt werden.');
        }
        if (!$this->bereit()) {
            throw new RuntimeException('Für Stripe fehlt der geheime Schlüssel in app/config.local.php.');
        }

        /* Eine Abbuchung (Phase 2) hat keine Bezahlseite, sondern einen
           Zahlungsvorgang (pi_...). Der Abgleich fragt beides hier -- eine
           Lastschrift braucht Tage, und der Webhook dafuer ist nicht
           eingetragen. */
        if (str_starts_with($sitzungId, 'pi_')) { return $this->vorgangLesen($sitzungId); }

        $a = $this->anfrage('GET', '/v1/checkout/sessions/' . rawurlencode($sitzungId), []);

        if (isset($a['error'])) {
            throw new RuntimeException('Stripe: ' . (string) ($a['error']['message'] ?? 'unbekannter Fehler'));
        }

        /* payment_intent kommt je nach Kontostand als Zeichenkette oder als
           ausgeklapptes Objekt zurueck. Beides muss hier durchgehen, sonst
           steht im Beleg spaeter "Array". */
        $pi = $a['payment_intent'] ?? null;
        $referenz = is_array($pi) ? (string) ($pi['id'] ?? '') : (string) ($pi ?? '');
        if ($referenz === '') { $referenz = (string) ($a['id'] ?? ''); }

        return [
            'bezahlt'    => ($a['payment_status'] ?? '') === 'paid',
            'referenz'   => $referenz,
            'status'     => (string) ($a['status'] ?? ''),          // open | complete | expired
            'abgelaufen' => ($a['status'] ?? '') === 'expired',
            'betrag'     => (int) ($a['amount_total'] ?? 0),
            'waehrung'   => strtoupper((string) ($a['currency'] ?? '')),
        ];
    }

    /* ================================================================== */
    /*  Abbuchen (Phase 2, 25.09.2026)                                    */
    /*                                                                    */
    /*  Kein Stripe-Abonnement: Die Fristen rechnet Abo.php. Hier wird nur */
    /*  ein Zahlungsmittel hinterlegt und je Rate einmal abgebucht.        */
    /* ================================================================== */

    /** Die Stripe-Kundennummer -- einmal angelegt, danach wiederverwendet. */
    public function kunde(array $kunde): string
    {
        $da = trim((string) ($kunde['stripe_kunde'] ?? ''));
        if ($da !== '') { return $da; }
        $a = $this->anfrage('POST', '/v1/customers', [
            'email' => (string) $kunde['email'],
            'name'  => (string) (($kunde['company'] ?? '') ?: $kunde['name']),
            'metadata[kunde_id]' => (string) $kunde['id'],
            'preferred_locales[0]' => (string) ($kunde['sprache'] ?? 'it'),
        ], 'kunde-' . (int) $kunde['id']);
        if (empty($a['id'])) {
            throw new RuntimeException('Stripe hat keinen Kunden angelegt: ' . (string) ($a['error']['message'] ?? '?'));
        }
        return (string) $a['id'];
    }

    /**
     * Die Stripe-Seite, auf der der Kunde Karte oder Lastschrift hinterlegt
     * (Checkout im Modus "setup": nichts wird bezahlt).
     *
     * NUR KARTE UND SEPA (25.09.2026, Uwe)
     * Im Konto sind ein Dutzend Zahlarten an (Revolut Pay, Amazon Pay, Link,
     * ...). Fuer eine Einmalzahlung ist das gut; fuers monatliche Abbuchen
     * ohne den Kunden sind nur Karte und SEPA-Lastschrift gemacht. Bei den
     * anderen haengt es am Anbieter, ob eine Abbuchung durchgeht -- und
     * scheitert sie, kaeme jeden Monat doch wieder der Zahlungslink. Dazu
     * erkennt der Kunde "Visa •••• 4242", aber nicht "revolut_pay".
     */
    public function einrichtungsseite(string $stripeKunde, int $aboId, string $zurueck, string $abbruch, string $sprache): string
    {
        $a = $this->anfrage('POST', '/v1/checkout/sessions', [
            'mode'        => 'setup',
            'currency'    => 'eur',
            'payment_method_types[0]' => 'card',
            'payment_method_types[1]' => 'sepa_debit',
            'customer'    => $stripeKunde,
            'client_reference_id' => 'abo-' . $aboId,
            'metadata[abo_id]'    => (string) $aboId,
            'setup_intent_data[metadata][abo_id]' => (string) $aboId,
            'success_url' => $zurueck . (str_contains($zurueck, '?') ? '&' : '?') . 'einrichtung={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $abbruch,
            'locale'      => in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'auto',
        ]);
        if (empty($a['url'])) {
            throw new RuntimeException('Stripe hat keine Seite geliefert: ' . (string) ($a['error']['message'] ?? '?'));
        }
        return (string) $a['url'];
    }

    /**
     * Was auf der Einrichtungsseite hinterlegt wurde.
     *
     * @return array{fertig:bool, abo_id:int, kunde:string, zahlmittel:string, art:string, text:string}
     */
    public function einrichtungLesen(string $sitzungId): array
    {
        $a = $this->anfrage('GET', '/v1/checkout/sessions/' . rawurlencode($sitzungId),
            ['expand' => ['setup_intent.payment_method']]);
        if (isset($a['error'])) { throw new RuntimeException('Stripe: ' . (string) ($a['error']['message'] ?? '?')); }
        $si = is_array($a['setup_intent'] ?? null) ? $a['setup_intent'] : [];
        $pm = is_array($si['payment_method'] ?? null) ? $si['payment_method'] : [];
        return [
            'fertig'     => ($a['mode'] ?? '') === 'setup' && ($a['status'] ?? '') === 'complete'
                            && ($si['status'] ?? '') === 'succeeded' && !empty($pm['id']),
            'abo_id'     => (int) ($a['metadata']['abo_id'] ?? 0),
            'kunde'      => (string) ($a['customer'] ?? ''),
            'zahlmittel' => (string) ($pm['id'] ?? ''),
            'art'        => (string) ($pm['type'] ?? ''),
            'text'       => self::zahlmittelText($pm),
        ];
    }

    /** "Visa •••• 4242" / "SEPA •••• 3000" -- mehr zeigen wir nie. */
    public static function zahlmittelText(array $pm): string
    {
        if (($pm['type'] ?? '') === 'card') {
            return ucfirst((string) ($pm['card']['brand'] ?? 'Karte')) . ' •••• ' . (string) ($pm['card']['last4'] ?? '');
        }
        if (($pm['type'] ?? '') === 'sepa_debit') {
            return 'SEPA •••• ' . (string) ($pm['sepa_debit']['last4'] ?? '');
        }
        return (string) ($pm['type'] ?? '');
    }

    /**
     * Eine Rate abbuchen -- ohne dass der Kunde dabei ist ("off_session").
     *
     * @return array{status:string, vorgang:string, grund:string}
     *         status: bezahlt | laeuft (Lastschrift, dauert Tage) | abgelehnt
     */
    public function abbuchen(array $zahlung, string $stripeKunde, string $zahlmittel): array
    {
        $a = $this->anfrage('POST', '/v1/payment_intents', [
            'amount'         => (string) (int) $zahlung['amount_cents'],
            'currency'       => strtolower((string) $zahlung['currency']),
            'customer'       => $stripeKunde,
            'payment_method' => $zahlmittel,
            'off_session'    => 'true',
            'confirm'        => 'true',
            'description'    => (string) Config::get('firma', 'Vecom Design') . ' · ' . (string) $zahlung['bezeichnung'],
            'metadata[zahlung_id]' => (string) $zahlung['id'],
            'metadata[art]'        => 'abbuchung',
        /* Je Rate und Betrag genau ein Vorgang: Laeuft der Cron doppelt oder
           bricht die Verbindung ab, bucht Stripe trotzdem nur einmal ab. */
        ], 'abbuchung-' . (int) $zahlung['id'] . '-' . (int) $zahlung['amount_cents'] . '-' . $zahlmittel);

        /* Abgelehnt kommt als Fehler -- mit dem Vorgang darin. */
        $pi = isset($a['error']) ? (array) ($a['error']['payment_intent'] ?? []) : $a;
        $status = (string) ($pi['status'] ?? '');
        $grund = (string) ($a['error']['message'] ?? ($pi['last_payment_error']['message'] ?? ''));
        return [
            'status'  => $status === 'succeeded' ? 'bezahlt' : ($status === 'processing' ? 'laeuft' : 'abgelehnt'),
            'vorgang' => (string) ($pi['id'] ?? ''),
            'grund'   => $grund !== '' ? $grund : $status,
            'betrag'  => (int) ($pi['amount_received'] ?? ($pi['amount'] ?? 0)),
            'waehrung'=> strtoupper((string) ($pi['currency'] ?? '')),
        ];
    }

    /** Fuer den Abgleich: ein Zahlungsvorgang in derselben Form wie sitzungLesen(). */
    private function vorgangLesen(string $id): array
    {
        $a = $this->anfrage('GET', '/v1/payment_intents/' . rawurlencode($id), []);
        if (isset($a['error'])) { throw new RuntimeException('Stripe: ' . (string) ($a['error']['message'] ?? '?')); }
        $st = (string) ($a['status'] ?? '');
        return [
            'bezahlt'    => $st === 'succeeded',
            'referenz'   => (string) ($a['id'] ?? $id),
            'status'     => $st,
            'abgelaufen' => in_array($st, ['canceled', 'requires_payment_method'], true),
            'betrag'     => (int) ($a['amount_received'] ?? 0),
            'waehrung'   => strtoupper((string) ($a['currency'] ?? '')),
        ];
    }

    /** Ein Zahlungsmittel wieder loesen, wenn der Kunde die Abbuchung beendet. */
    public function zahlmittelLoesen(string $zahlmittel): void
    {
        if ($zahlmittel !== '') { $this->anfrage('POST', '/v1/payment_methods/' . rawurlencode($zahlmittel) . '/detach', []); }
    }

    /**
     * Prueft die Unterschrift, mit der Stripe jeden Webhook versieht.
     * Ohne diese Pruefung koennte jeder eine bezahlte Zahlung vortaeuschen.
     */
    public function ereignisPruefen(string $rohtext, array $kopfzeilen): ?array
    {
        $geheim = (string) ($this->cfg['webhook_geheim'] ?? '');
        if ($geheim === '') { return null; }

        $kopf = '';
        foreach ($kopfzeilen as $name => $wert) {
            if (strtolower((string) $name) === 'stripe-signature') { $kopf = (string) $wert; break; }
        }
        if ($kopf === '') { return null; }

        $zeit = null; $unterschriften = [];
        foreach (explode(',', $kopf) as $teil) {
            $paar = explode('=', trim($teil), 2);
            if (count($paar) !== 2) { continue; }
            if ($paar[0] === 't')  { $zeit = (int) $paar[1]; }
            if ($paar[0] === 'v1') { $unterschriften[] = $paar[1]; }
        }
        if ($zeit === null || !$unterschriften) { return null; }

        // Alte Unterschriften nicht mehr annehmen — sonst liesse sich ein
        // mitgeschnittener Aufruf spaeter wiederholen.
        if (abs(time() - $zeit) > 300) { return null; }

        $erwartet = hash_hmac('sha256', $zeit . '.' . $rohtext, $geheim);
        $passt = false;
        foreach ($unterschriften as $u) {
            if (hash_equals($erwartet, $u)) { $passt = true; break; }
        }
        if (!$passt) { return null; }

        $daten = json_decode($rohtext, true);
        if (!is_array($daten) || empty($daten['id']) || empty($daten['type'])) { return null; }

        return ['id' => (string) $daten['id'], 'typ' => (string) $daten['type'], 'daten' => $daten];
    }

    /* ------------------------------------------------------------------ */

    /** @param array<string,string> $felder */
    /**
     * Stripe Connect fürs Partnerprogramm (Konto, Einrichtungslink,
     * Überweisung, Rückholung). Derselbe Weg wie alles andere hier, nur
     * nach außen geöffnet -- ein zweiter HTTP-Client wäre eine zweite
     * Stelle, an der der Schlüssel liegt.
     */
    public function aufrufen(string $methode, string $weg, array $felder = [], string $einmalig = ''): array
    {
        return $this->anfrage($methode, $weg, $felder, $einmalig);
    }

    private function anfrage(string $methode, string $weg, array $felder, string $einmalig = ''): array
    {
        $basis = rtrim((string) ($this->cfg['api'] ?? 'https://api.stripe.com'), '/');
        $kopf = [
            'Authorization: Bearer ' . $this->cfg['geheim'],
            'Content-Type: application/x-www-form-urlencoded',
        ];
        // Verhindert doppelte Bezahlseiten, falls die Verbindung abbricht und
        // der Aufruf wiederholt wird.
        if ($einmalig !== '') { $kopf[] = 'Idempotency-Key: ' . $einmalig; }

        /* GET traegt seine Felder in der Adresse, nicht im Rumpf. Ein GET mit
           Rumpf beantwortet Stripe je nach Tageslaune mit einem Fehler —
           deshalb die Weiche statt eines gemeinsamen Aufrufs. */
        $ziel = $basis . $weg;
        $rumpf = null;
        if (strtoupper($methode) === 'GET') {
            if ($felder !== []) { $ziel .= '?' . http_build_query($felder); }
        } else {
            $rumpf = http_build_query($felder);
        }

        $ch = curl_init($ziel);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_HTTPHEADER     => $kopf,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($rumpf !== null) { curl_setopt($ch, CURLOPT_POSTFIELDS, $rumpf); }
        $roh  = curl_exec($ch);
        $netz = curl_error($ch);
        curl_close($ch);

        if ($roh === false) {
            throw new RuntimeException('Stripe war nicht erreichbar: ' . $netz);
        }
        $daten = json_decode((string) $roh, true);
        return is_array($daten) ? $daten : [];
    }
}
