<?php
declare(strict_types=1);

/**
 * Stripe — ohne fremde Bibliothek, nur ueber die HTTPS-Schnittstelle.
 *
 * Warum ohne Composer: Der Rest der Anwendung kommt ohne aus, und der
 * FTP-Deploy laedt einfach Dateien hoch. Eine Abhaengigkeit mit eigenem
 * Aktualisierungszyklus waere hier ein Fremdkoerper. Gebraucht werden genau
 * zwei Dinge: eine Bezahlseite anlegen und eine Unterschrift pruefen.
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

    public function __construct(?array $cfg = null)
    {
        $this->cfg = $cfg ?? (array) Config::get('stripe', []);
    }

    public function schluessel(): string { return 'stripe'; }

    public function modus(): string
    {
        return ($this->cfg['modus'] ?? 'test') === 'live' ? 'live' : 'test';
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

    public function bezahlseite(array $zahlung, array $bestellung, array $kunde): string
    {
        if (!$this->bereit()) {
            throw new RuntimeException('Für Stripe fehlt der geheime Schlüssel in app/config.local.php.');
        }

        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $marke = (string) Config::get('firma', 'Vecom Design');
        $titel = trim(($zahlung['bezeichnung'] ?: 'Zahlung') . ' · ' . $bestellung['package_name']);

        $felder = [
            'mode'                          => 'payment',
            'client_reference_id'           => (string) $zahlung['id'],
            'customer_email'                => (string) $kunde['email'],
            'success_url'                   => $basis . '/danke.html?zahlung=ok',
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
        return (string) $antwort['url'];
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

        $ch = curl_init($basis . $weg);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $methode,
            CURLOPT_POSTFIELDS     => http_build_query($felder),
            CURLOPT_HTTPHEADER     => $kopf,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
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
