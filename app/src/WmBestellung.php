<?php
declare(strict_types=1);

/* ==========================================================================
   WmBestellung.php — Marketing Center, Phase 3: Partner bestellen
   Werbemittel (03.10.2026, Uwe: „B, aber Partner kann trotzdem bestellen“).

   DREI REGELN AUS DER VORGABE, UND WO SIE STEHEN

   „Eine Bestellung darf niemals nur aufgrund einer Frontend-Rückmeldung als
   bezahlt gelten.“ — bezahlt wird nur in bezahltVonStripe() (Webhook oder
   Abgleich, Betrag und Währung müssen passen) oder in vonHandBezahlt()
   (Uwe in der Verwaltung). Der Rückweg von Stripe in den Browser setzt
   nichts.

   „Preise bei Nachbestellung erneut prüfen.“ — anlegen() rechnet den Preis
   jedes Mal neu aus Einkauf und Marge (Werbemittel::preis), nie aus einem
   Wert, den der Browser schickt.

   „Keine Inhalte ohne Partnerfreigabe automatisch drucken.“ — ohne
   freigegebenen Entwurf gibt es keine Bestellung, und die Position merkt
   sich, WELCHER Entwurf gedruckt wird.

   DOPPELT BESTELLEN

   Ein Doppelklick oder ein Zurück-und-nochmal ergibt keine zweite
   Bestellung: Gibt es für dieselbe Variante, denselben Entwurf und dieselbe
   Adresse in den letzten 30 Minuten schon eine offene, kommt diese zurück.
   ========================================================================== */
final class WmBestellung
{
    /** Geliefert wird nach Italien und Deutschland (Uwe, 04.10.2026) — dorthin, wo der Partner wohnt. */
    public const LAENDER = ['IT', 'DE'];
    public const OFFEN = ['angefragt', 'offen'];
    /** Höchstens so viele unbezahlte Bestellungen je Partner zugleich. */
    public const OFFEN_MAX = 5;
    /** Prüfnaht: ersetzt Mail::senden (Kette). Null = echte Mail. */
    public static $senden = null;

    // ---- Zahlweg ----------------------------------------------------------------

    /**
     * „stripe“ nur, wenn Uwe es in der Verwaltung eingeschaltet hat UND der
     * Schlüssel da ist. Sonst „anfrage“: Die Bestellung wird gespeichert, und
     * Uwe klärt den Zahlungsweg. Voreinstellung ist „anfrage“ — Geld fließt
     * erst, wenn er es ausdrücklich will (Partita IVA, 03.10.2026).
     */
    public static function zahlweg(?object $stripe = null): string
    {
        try { $an = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'wm_zahlweg'", [], 'anfrage'); } catch (Throwable $e) { $an = 'anfrage'; }
        if ($an !== 'stripe') { return 'anfrage'; }
        if ($stripe === null) {
            require_once __DIR__ . '/Zahlung/Anbieter.php';
            require_once __DIR__ . '/Zahlung/Stripe.php';
            $stripe = new StripeAnbieter();
        }
        return $stripe->bereit() ? 'stripe' : 'anfrage';
    }

    /**
     * Automatikbetrieb (Uwe, 04.10.2026: „Nichts von Hand — nach Zahlung des
     * Partners soll automatisch der Anbieter die Bestellung abwickeln“).
     * An: Nach der Zahlung geht die Bestellung als echter Auftrag an die
     * Druckerei der Bestellung, sofern die eine Anbindung hat; die
     * Sendungsnummer kommt von dort, und der Partner bekommt die Mail.
     * Voreinstellung: an.
     */
    public static function automatik(): bool
    {
        try { return (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'wm_automatik'", [], '1') !== '0'; } catch (Throwable $e) { return true; }
    }

    public static function automatikSetzen(bool $an): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('wm_automatik', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$an ? '1' : '0']);
    }

    /**
     * Nach bestätigter Zahlung: Auftrag automatisch an die Druckerei, wenn
     * sie angebunden ist. Ein Fehler wird festgehalten und gemeldet, NICHT
     * wiederholt. Ohne Anbindung: Meldung an Uwe, mehr nicht.
     */
    public static function nachZahlung(int $id): void
    {
        if (!self::automatik()) { return; }
        /* Not-Aus (AI Office Stufe 0, 06.10.2026): Ein Druckauftrag kostet Geld und ist nicht
           zurückzuholen. Während des Not-Aus wie bei ausgeschalteter Automatik: Uwe löst ihn
           selbst aus. Die Zahlung ist gebucht, nur der Auftrag wartet. */
        if (class_exists('Automation') && Automation::ausgangGesperrt()) {
            $n = (string) Db::wert('SELECT nummer FROM wm_bestellungen WHERE id = ?', [$id], '');
            Events::melden('wm_notaus', 'Werbemittel ' . $n . ': Druckauftrag wartet (Not-Aus)', 'warnung',
                'Bezahlt, aber wegen des Not-Aus nicht automatisch an die Druckerei gegeben. Nach dem Lösen von Hand bestellen.', '/werbemittel/bestellungen');
            return;
        }
        try {
            $anbieter = (string) Db::wert('SELECT anbieter FROM wm_positionen WHERE bestellung_id = ? ORDER BY id LIMIT 1', [$id], '');
            require_once __DIR__ . '/Druckerei.php';
            $r = Druckerei::senden($anbieter, $id);
            if ($r['ok']) {
                Events::protokoll('wm_auftrag_automatisch', 'Werbemittel-Bestellung #' . $id . ' automatisch an ' . $anbieter, null, null, null, ['wm_bestellung' => $id, 'ref' => $r['id'] ?? '']);
            }
            if (!$r['ok'] && $r['grund'] !== 'keine Anbindung' && Db::wert('SELECT anbieter_status FROM wm_bestellungen WHERE id = ?', [$id], null) === null) {
                // Vorprüfung gescheitert (z. B. Artikelnummer fehlt) — nichts gesendet, Uwe muss es wissen.
                Events::melden('wm_druckerei_fehler', 'Werbemittel #' . $id . ': nicht an ' . $anbieter . ' gesendet', 'schlecht', mb_substr($r['grund'], 0, 480), '/werbemittel/bestellungen');
            }
            if ($r['grund'] !== 'keine Anbindung') { return; }   // gesendet — oder Fehler, der gemeldet ist
            $n = (string) Db::wert('SELECT nummer FROM wm_bestellungen WHERE id = ?', [$id], '');
            Events::melden('wm_ohne_anbindung', 'Werbemittel ' . $n . ': Druckerei ohne Anbindung', 'warnung',
                '„' . ($anbieter !== '' ? $anbieter : 'unbekannt') . '“ hat keine automatische Anbindung — diese Bestellung muss dort von Hand bestellt werden.', '/werbemittel/bestellungen');
        } catch (Throwable $e) { error_log('WmBestellung::nachZahlung ' . $id . ': ' . $e->getMessage()); }
    }

    public static function zahlwegSetzen(string $weg): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('wm_zahlweg', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [$weg === 'stripe' ? 'stripe' : 'anfrage']);
    }

    // ---- Adressen -------------------------------------------------------------

    public static function adressen(int $partnerId): array
    {
        return Db::all('SELECT * FROM wm_adressen WHERE partner_id = ? ORDER BY updated_at DESC, id DESC', [$partnerId]);
    }

    /** Legt eine Adresse an oder ändert eine eigene. Gibt die id zurück. */
    public static function adresseSpeichern(int $partnerId, array $e, int $id = 0): int
    {
        $t = static fn(string $k, int $max): string => mb_substr(trim((string) preg_replace('~\s+~u', ' ', (string) ($e[$k] ?? ''))), 0, $max);
        $d = [
            'name' => $t('name', 120), 'firma' => $t('firma', 160), 'strasse' => $t('strasse', 160),
            'plz' => strtoupper($t('plz', 12)), 'ort' => $t('ort', 120),
            'land' => strtoupper($t('land', 2)), 'telefon' => $t('telefon', 40),
        ];
        foreach (['name', 'strasse', 'plz', 'ort'] as $k) {
            if ($d[$k] === '') { throw new InvalidArgumentException('adresse_' . $k); }
        }
        if (!in_array($d['land'], self::LAENDER, true)) { throw new InvalidArgumentException('adresse_land'); }
        if ($d['telefon'] !== '' && !preg_match('~^\+?[0-9 ()/.-]{6,40}$~', $d['telefon'])) { throw new InvalidArgumentException('adresse_telefon'); }
        if ($id > 0) {
            if (!Db::wert('SELECT COUNT(*) FROM wm_adressen WHERE id = ? AND partner_id = ?', [$id, $partnerId])) {
                throw new InvalidArgumentException('adresse_fremd');
            }
            Db::update('wm_adressen', $id, $d);
            return $id;
        }
        if ((int) Db::wert('SELECT COUNT(*) FROM wm_adressen WHERE partner_id = ?', [$partnerId]) >= 10) {
            throw new InvalidArgumentException('adresse_zuviel');
        }
        $d['partner_id'] = $partnerId;
        return Db::insert('wm_adressen', $d);
    }

    // ---- Bestellen --------------------------------------------------------------

    /**
     * Legt eine Bestellung an (oder gibt die eben angelegte zurück).
     * @return array{id:int, nummer:string, summe_cent:int, neu:bool}
     */
    public static function anlegen(array $p, int $varianteId, int $adresseId, string $sprache): array
    {
        require_once __DIR__ . '/Werbemittel.php';
        $pid = (int) $p['id'];
        $v = Db::one('SELECT v.id, v.auflage, v.einkauf_cent, v.aktiv, v.name_it AS v_it, v.name_de AS v_de, v.name_en AS v_en, w.id AS pr_id, w.nummer AS pr_nummer, w.name_it, w.name_de, w.name_en, w.marge_prozent, w.mindestmarge_cent,
                             w.aktiv AS pr_aktiv, k.aktiv AS kat_aktiv
                        FROM wm_varianten v JOIN wm_produkte w ON w.id = v.produkt_id JOIN wm_kategorien k ON k.id = w.kategorie_id
                       WHERE v.id = ?', [$varianteId]);
        if (!$v || !(int) $v['aktiv'] || !(int) $v['pr_aktiv'] || !(int) $v['kat_aktiv']) {
            throw new InvalidArgumentException('nicht_verfuegbar');
        }
        $entwurf = Db::one("SELECT id FROM wm_entwuerfe WHERE partner_id = ? AND produkt_id = ? AND status = 'freigegeben' ORDER BY id DESC LIMIT 1",
            [$pid, (int) $v['pr_id']]);
        if (!$entwurf) { throw new InvalidArgumentException('freigabe_fehlt'); }
        $a = Db::one('SELECT * FROM wm_adressen WHERE id = ? AND partner_id = ?', [$adresseId, $pid]);
        if (!$a) { throw new InvalidArgumentException('adresse_fehlt'); }

        // Preis neu rechnen — nie aus dem Browser — und zwar für das Land der
        // Lieferadresse: günstigste Druckerei für genau dieses Land.
        $ek = Werbemittel::einkauf((int) $v['id'], (string) $a['land']);
        if (!$ek) { throw new InvalidArgumentException('nicht_lieferbar'); }
        $r = Werbemittel::regel(['marge_prozent' => $v['marge_prozent'], 'mindestmarge_cent' => $v['mindestmarge_cent']]);
        $preis = Werbemittel::preis($ek['cent'], $r['marge_prozent'], $r['mindestmarge_cent']);
        // Gewinnsperre (04.10.2026): bleibt nach Einkauf und Zahlungskosten zu wenig, wird nicht bestellt.
        if (Werbemittel::gewinn($preis, $ek['cent']) < Werbemittel::MIN_GEWINN_CENT) { throw new InvalidArgumentException('nicht_lieferbar'); }
        $adresse = json_encode(array_intersect_key($a, array_flip(['name', 'firma', 'strasse', 'plz', 'ort', 'land', 'telefon'])), JSON_UNESCAPED_UNICODE);
        $sprache = in_array($sprache, Werbemittel::SPRACHEN, true) ? $sprache : 'it';
        $feld = static fn(string $f) => trim((string) ($v[$f . '_' . $sprache] ?? '')) !== '' ? (string) $v[$f . '_' . $sprache] : (string) $v[$f . '_it'];

        $r = Db::transaktion(static function () use ($pid, $v, $entwurf, $adresse, $preis, $sprache, $feld, $ek): array {
            // Doppelklick, Zurück-und-nochmal: dieselbe offene Bestellung zurückgeben.
            $gleich = Db::one("SELECT b.id, b.nummer, b.summe_cent FROM wm_bestellungen b JOIN wm_positionen x ON x.bestellung_id = b.id
                                WHERE b.partner_id = ? AND b.status IN ('angefragt', 'offen') AND b.adresse = ? AND b.summe_cent = ?
                                  AND x.variante_id = ? AND x.entwurf_id = ? AND b.created_at > NOW() - INTERVAL 30 MINUTE
                                ORDER BY b.id DESC LIMIT 1 FOR UPDATE", [$pid, $adresse, $preis, (int) $v['id'], (int) $entwurf['id']]);
            if ($gleich) { return ['id' => (int) $gleich['id'], 'nummer' => (string) $gleich['nummer'], 'summe_cent' => (int) $gleich['summe_cent'], 'neu' => false]; }
            if ((int) Db::wert("SELECT COUNT(*) FROM wm_bestellungen WHERE partner_id = ? AND status IN ('angefragt', 'offen') FOR UPDATE", [$pid]) >= self::OFFEN_MAX) {
                throw new InvalidArgumentException('zuviel_offen');
            }
            $jahr = date('Y');
            $n = (int) Db::wert("SELECT COUNT(*) FROM wm_bestellungen WHERE nummer LIKE ? FOR UPDATE", ['VEC-MKT-' . $jahr . '-%']) + 1;
            $nummer = sprintf('VEC-MKT-%s-%06d', $jahr, $n);
            $id = Db::insert('wm_bestellungen', [
                'nummer' => $nummer, 'partner_id' => $pid, 'status' => 'angefragt', 'summe_cent' => $preis,
                'steuer_cent' => 0, 'waehrung' => 'EUR', 'adresse' => $adresse, 'sprache' => $sprache,
            ]);
            Db::insert('wm_positionen', [
                'bestellung_id' => $id, 'produkt_id' => (int) $v['pr_id'], 'variante_id' => (int) $v['id'], 'entwurf_id' => (int) $entwurf['id'],
                'produkt_nummer' => (string) $v['pr_nummer'], 'name' => $feld('name'), 'variante' => trim((string) $v['v_' . $sprache]) !== '' ? (string) $v['v_' . $sprache] : (string) $v['v_it'],
                'auflage' => (int) $v['auflage'], 'menge' => 1, 'preis_cent' => $preis, 'einkauf_cent' => $ek['cent'], 'anbieter' => $ek['anbieter'],
            ]);
            return ['id' => $id, 'nummer' => $nummer, 'summe_cent' => $preis, 'neu' => true];
        }, 5);
        // Bestätigung an den Partner: Antwort auf seine eigene Bestellung, erst nach dem Speichern.
        if ($r['neu']) { self::mailen($r['id'], 'wm_eingang'); }
        return $r;
    }

    /**
     * Bezahlseite bei Stripe. Setzt den Status auf „offen“ und merkt sich die
     * Sitzung — bezahlt wird erst, wenn Stripe es meldet.
     * $stripe: StripeAnbieter oder ein Ersatz mit aufrufen() (Kette).
     */
    public static function bezahlseite(int $id, int $partnerId, object $stripe, string $erfolg, string $abbruch): string
    {
        $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ? AND partner_id = ?', [$id, $partnerId]);
        if (!$b || !in_array($b['status'], self::OFFEN, true)) { throw new InvalidArgumentException('nicht_offen'); }
        $pos = Db::one('SELECT * FROM wm_positionen WHERE bestellung_id = ? ORDER BY id LIMIT 1', [$id]);
        $marke = (string) Config::get('firma', 'Vecom Design');
        $felder = [
            'mode' => 'payment',
            'client_reference_id' => 'wm-' . $id,
            'success_url' => $erfolg,
            'cancel_url' => $abbruch,
            'locale' => 'auto',
            'line_items[0][quantity]' => '1',
            'line_items[0][price_data][currency]' => strtolower((string) $b['waehrung']),
            'line_items[0][price_data][unit_amount]' => (string) (int) $b['summe_cent'],
            'line_items[0][price_data][product_data][name]' => trim(($pos['name'] ?? 'Werbemittel') . ' · ' . ($pos['variante'] ?? '')),
            'line_items[0][price_data][product_data][description]' => $marke . ' · ' . $b['nummer'],
            'metadata[wm_bestellung]' => (string) $id,
            'metadata[nummer]' => (string) $b['nummer'],
            'payment_intent_data[description]' => $marke . ' · ' . $b['nummer'],
            // Wie in Zahlung/Stripe.php: ohne Partita IVA keine Steuerabwicklung durch Stripe.
            'managed_payments[enabled]' => 'false',
        ];
        $p = Db::one('SELECT email FROM partner WHERE id = ?', [$partnerId]);
        if ($p && filter_var((string) $p['email'], FILTER_VALIDATE_EMAIL)) { $felder['customer_email'] = (string) $p['email']; }
        $antwort = $stripe->aufrufen('POST', '/v1/checkout/sessions', $felder, 'wm-' . $id . '-' . substr(md5(serialize($felder)), 0, 16));
        if (empty($antwort['url']) || empty($antwort['id'])) {
            throw new RuntimeException('Stripe hat keine Bezahlseite geliefert: ' . (string) ($antwort['error']['message'] ?? 'unbekannt'));
        }
        Db::run("UPDATE wm_bestellungen SET status = 'offen', stripe_sitzung = ? WHERE id = ? AND status IN ('angefragt', 'offen')",
            [(string) $antwort['id'], $id]);
        return (string) $antwort['url'];
    }

    // ---- Bezahlt: nur Stripe (Webhook/Abgleich) oder Uwe ------------------------

    /** @return string gebucht|schon|abweichung|unbekannt */
    public static function bezahltVonStripe(int $id, string $referenz, int $betrag, string $waehrung): string
    {
        $r = (string) Db::transaktion(static function () use ($id, $referenz, $betrag, $waehrung): string {
            $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ? FOR UPDATE', [$id]);
            if (!$b) { return 'unbekannt'; }
            if ($b['bezahlt_am'] !== null) { return 'schon'; }
            if ($betrag !== (int) $b['summe_cent'] || strtoupper(trim($waehrung)) !== strtoupper((string) $b['waehrung']) || !in_array($b['status'], self::OFFEN, true)) {
                Events::melden('wm_zahlung_abweichung', 'Werbemittel: Zahlung passt nicht zur Bestellung ' . $b['nummer'], 'schlecht',
                    'Stripe meldet ' . Fmt::geld($betrag, strtoupper($waehrung) ?: 'EUR') . ', gefordert ' . Fmt::geld((int) $b['summe_cent'], (string) $b['waehrung'])
                    . ', Status „' . $b['status'] . '“. Nicht automatisch gebucht — bei Stripe ansehen. Vorgang ' . ($referenz ?: '?') . '.', '/werbemittel/bestellungen');
                return 'abweichung';
            }
            Db::run("UPDATE wm_bestellungen SET status = 'bezahlt', bezahlt_am = NOW(), bezahlt_wie = 'stripe', stripe_referenz = ? WHERE id = ?", [$referenz, $id]);
            Events::melden('wm_bezahlt', 'Werbemittel bezahlt: ' . $b['nummer'], 'hinweis',
                Fmt::geld((int) $b['summe_cent'], (string) $b['waehrung']) . ' — jetzt beim Drucker beauftragen.', '/werbemittel/bestellungen');
            return 'gebucht';
        }, 3);
        if ($r === 'gebucht') { self::mailen($id, 'wm_bezahlt'); self::nachZahlung($id); }
        return $r;
    }

    /** Überweisung o. Ä., von Uwe in der Verwaltung bestätigt. */
    public static function vonHandBezahlt(int $id, string $wie = 'ueberweisung'): bool
    {
        $wie = in_array($wie, ['ueberweisung', 'bar', 'stripe'], true) ? $wie : 'ueberweisung';
        $ok = Db::run("UPDATE wm_bestellungen SET status = 'bezahlt', bezahlt_am = NOW(), bezahlt_wie = ? WHERE id = ? AND status IN ('angefragt', 'offen') AND bezahlt_am IS NULL",
            [$wie, $id])->rowCount() === 1;
        if ($ok) { self::mailen($id, 'wm_bezahlt'); self::nachZahlung($id); }
        return $ok;
    }

    // ---- Weiter im Ablauf (Verwaltung) -----------------------------------------

    public static function beimDrucker(int $id, string $anbieter, string $ref): bool
    {
        $anbieter = mb_substr(trim($anbieter), 0, 60);
        if ($anbieter === '') { throw new InvalidArgumentException('Anbieter fehlt.'); }
        return Db::run("UPDATE wm_bestellungen SET status = 'beim_drucker', beim_drucker_am = NOW(), anbieter = ?, anbieter_ref = ? WHERE id = ? AND status = 'bezahlt'",
            [$anbieter, mb_substr(trim($ref), 0, 120), $id])->rowCount() === 1;
    }

    public static function versendet(int $id, string $tracking, string $url = ''): bool
    {
        $tracking = mb_substr(trim($tracking), 0, 120);
        $url = trim($url);
        if ($tracking === '') { throw new InvalidArgumentException('Sendungsnummer fehlt.'); }
        if ($url !== '' && !preg_match('~^https://[^\s<>"]{4,390}$~', $url)) { throw new InvalidArgumentException('Link zur Sendungsverfolgung muss mit https:// beginnen.'); }
        $ok = Db::run("UPDATE wm_bestellungen SET status = 'versendet', versendet_am = NOW(), tracking = ?, tracking_url = ? WHERE id = ? AND status = 'beim_drucker'",
            [$tracking, $url !== '' ? $url : null, $id])->rowCount() === 1;
        if ($ok) { self::mailen($id, 'wm_versendet'); }
        return $ok;
    }

    // ---- Zugestellt und Reklamation (Phase 6a, 05.10.2026) --------------------
    /* Uwe: „Partner bestätigt, sonst nach 14 Tagen“. Ab der Zustellung kann der Partner
       14 Tage lang reklamieren; Uwe entscheidet: Neudruck, Gutschrift oder abgelehnt.
       Geld bewegt sich hier nie — eine Gutschrift macht Uwe bei Stripe selbst, einen
       Neudruck bei der Druckerei; hier steht nur, was entschieden ist. */

    public const ZUSTELL_TAGE = 14;
    public const REKLAMATION_TAGE = 14;
    public const ENTSCHEIDE = ['neudruck', 'gutschrift', 'abgelehnt'];

    /** Versendet → zugestellt. $partnerId null = Verwaltung oder Automatik. */
    public static function zugestellt(int $id, ?int $partnerId, string $wie = 'partner'): bool
    {
        $wie = in_array($wie, ['partner', 'automatisch', 'verwaltung'], true) ? $wie : 'partner';
        $sql = "UPDATE wm_bestellungen SET status = 'zugestellt', zugestellt_am = NOW(), zugestellt_wie = ? WHERE id = ? AND status = 'versendet'";
        $arg = [$wie, $id];
        if ($partnerId !== null) { $sql .= ' AND partner_id = ?'; $arg[] = $partnerId; }
        $ok = Db::run($sql, $arg)->rowCount() === 1;
        if ($ok) { Events::protokoll('wm_zugestellt', 'Werbemittel-Bestellung #' . $id . ' zugestellt (' . $wie . ')', null, null, null, ['wm_bestellung' => $id]); }
        return $ok;
    }

    /** Cron: was seit ZUSTELL_TAGE versendet ist und keiner bestätigt hat, gilt als zugestellt. Schickt nichts. */
    public static function automatischZustellen(): int
    {
        $n = 0;
        foreach (Db::all("SELECT id FROM wm_bestellungen WHERE status = 'versendet' AND versendet_am < NOW() - INTERVAL " . self::ZUSTELL_TAGE . ' DAY LIMIT 200') as $b) {
            if (self::zugestellt((int) $b['id'], null, 'automatisch')) { $n++; }
        }
        return $n;
    }

    /** Darf der Partner diese Bestellung (noch) reklamieren? Versendet, oder zugestellt und höchstens REKLAMATION_TAGE her. */
    public static function reklamierbar(array $b): bool
    {
        if ($b['status'] === 'versendet') { return true; }
        return $b['status'] === 'zugestellt' && empty($b['reklamation_am']) && !empty($b['zugestellt_am'])
            && strtotime((string) $b['zugestellt_am']) >= time() - self::REKLAMATION_TAGE * 86400;
    }

    /**
     * Der Partner meldet ein Problem — mit Grund (Pflicht) und Foto (freiwillig, als WebP neu gerechnet).
     * Eine versendete Bestellung gilt damit auch als zugestellt. Nur eigene, nur einmal.
     * @return string ok | grund | foto | nicht
     */
    public static function reklamieren(int $id, int $partnerId, string $grund, ?string $fotoPfad = null, int $fotoGroesse = 0): string
    {
        $grund = trim((string) preg_replace('/\s+/u', ' ', $grund));
        if (mb_strlen($grund) < 10) { return 'grund'; }
        $grund = mb_substr($grund, 0, 600);
        $foto = null;
        if ($fotoPfad !== null && $fotoGroesse > 0) {
            require_once __DIR__ . '/PartnerMediathek.php';
            $foto = PartnerMediathek::bildRechnen($fotoPfad, $fotoGroesse);
            if (str_starts_with($foto, 'fehler:')) { return 'foto'; }
        }
        $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ? AND partner_id = ?', [$id, $partnerId]);
        if (!$b || !self::reklamierbar($b)) { return 'nicht'; }
        $ok = Db::run("UPDATE wm_bestellungen SET status = 'reklamation', reklamation_am = NOW(), reklamation_grund = ?, reklamation_foto = ?,
                              zugestellt_am = COALESCE(zugestellt_am, NOW()), zugestellt_wie = COALESCE(zugestellt_wie, 'partner')
                        WHERE id = ? AND partner_id = ? AND status IN ('versendet','zugestellt') AND reklamation_am IS NULL",
                      [$grund, $foto, $id, $partnerId])->rowCount() === 1;
        if (!$ok) { return 'nicht'; }
        Events::melden('wm_reklamation', 'Reklamation: Werbemittel ' . $b['nummer'], 'warnung',
            mb_substr($grund, 0, 300) . ($foto !== null ? ' (mit Foto)' : ''), '/werbemittel/bestellungen#b' . $id);
        Events::protokoll('wm_reklamation', 'Werbemittel-Bestellung #' . $id . ' reklamiert', null, null, null, ['wm_bestellung' => $id]);
        return 'ok';
    }

    /** Uwe entscheidet über eine Reklamation. Danach steht die Bestellung wieder auf „zugestellt“, mit Entscheid; der Partner bekommt eine Mail. */
    public static function reklamationEntscheiden(int $id, string $entscheid, string $antwort = ''): bool
    {
        if (!in_array($entscheid, self::ENTSCHEIDE, true)) { throw new InvalidArgumentException('Entscheid unbekannt.'); }
        $antwort = mb_substr(trim($antwort), 0, 600);
        if ($entscheid === 'abgelehnt' && mb_strlen($antwort) < 10) { throw new InvalidArgumentException('Bei „abgelehnt“ bitte einen Satz für den Partner.'); }
        $ok = Db::run("UPDATE wm_bestellungen SET status = 'zugestellt', reklamation_entscheid = ?, reklamation_antwort = ?, reklamation_entschieden_am = NOW()
                        WHERE id = ? AND status = 'reklamation'", [$entscheid, $antwort !== '' ? $antwort : null, $id])->rowCount() === 1;
        if ($ok) {
            Events::pruefspur('wm_reklamation_entschieden', 'wm_bestellung', $id, ['status' => 'reklamation'], ['entscheid' => $entscheid]);
            self::mailen($id, 'wm_reklamation_' . $entscheid, ['antwort' => $antwort]);
        }
        return $ok;
    }

    /** Foto einer Reklamation — für die Verwaltung und den Partner, dem die Bestellung gehört. */
    public static function reklamationFoto(int $id, ?int $partnerId = null): ?string
    {
        $sql = 'SELECT reklamation_foto FROM wm_bestellungen WHERE id = ? AND reklamation_foto IS NOT NULL';
        $arg = [$id];
        if ($partnerId !== null) { $sql .= ' AND partner_id = ?'; $arg[] = $partnerId; }
        $f = Db::wert($sql, $arg, null);
        return is_string($f) && $f !== '' ? $f : null;
    }

    /**
     * Der Partner bricht eine UNBEZAHLTE Bestellung ab (04.10.2026). Gibt es
     * eine Stripe-Bezahlseite, wird sie zuerst bei Stripe beendet
     * (POST /v1/checkout/sessions/{id}/expire, Doku gelesen 04.10.2026) —
     * sonst könnte der Partner im alten Tab noch bezahlen. Lässt Stripe sie
     * nicht beenden, wird nachgesehen: bezahlt → NICHT abbrechen; schon
     * abgelaufen → abbrechen; sonst lieber nichts tun.
     * @return string 'ok' | 'nicht'
     */
    public static function partnerAbbrechen(int $id, int $partnerId, ?object $stripe): string
    {
        $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ? AND partner_id = ?', [$id, $partnerId]);
        if (!$b || !in_array($b['status'], self::OFFEN, true) || $b['bezahlt_am'] !== null) { return 'nicht'; }
        $sitzung = (string) ($b['stripe_sitzung'] ?? '');
        if ($sitzung !== '') {
            if ($stripe === null) { return 'nicht'; }
            try {
                $a = $stripe->aufrufen('POST', '/v1/checkout/sessions/' . rawurlencode($sitzung) . '/expire', [], 'wm-abbruch-' . $id);
                if (isset($a['error'])) {
                    $st = $stripe->sitzungLesen($sitzung);
                    if ($st['bezahlt'] || !$st['abgelaufen']) { return 'nicht'; }
                }
            } catch (Throwable $e) { error_log('WmBestellung::partnerAbbrechen ' . $id . ': ' . $e->getMessage()); return 'nicht'; }
        }
        $ok = Db::run("UPDATE wm_bestellungen SET status = 'storniert', storniert_am = NOW() WHERE id = ? AND partner_id = ? AND status IN ('angefragt', 'offen') AND bezahlt_am IS NULL",
            [$id, $partnerId])->rowCount() === 1;
        if ($ok) { Events::protokoll('wm_partner_storno', 'Werbemittel ' . $b['nummer'] . ' vom Partner abgebrochen (unbezahlt)', null, null, null, ['wm_bestellung' => $id]); }
        return $ok ? 'ok' : 'nicht';
    }

    /** Nur bis „bezahlt“. Bereits bezahlt: Erstattung macht Uwe bei Stripe selbst — hier wird nichts zurückgebucht. */
    public static function stornieren(int $id): bool
    {
        return Db::run("UPDATE wm_bestellungen SET status = 'storniert', storniert_am = NOW() WHERE id = ? AND status IN ('angefragt', 'offen', 'bezahlt')",
            [$id])->rowCount() === 1;
    }

    /**
     * Mail an den Partner zu seiner Bestellung, in seiner Sprache. Ein
     * Fehler beim Versand hält den Ablauf nicht auf — die Bestellung ist
     * gespeichert, und der Partner sieht den Stand in seinem Bereich.
     */
    private static function mailen(int $id, string $anlass, array $extra = []): void
    {
        try {
            require_once __DIR__ . '/Partner.php';
            require_once __DIR__ . '/Werbemittel.php';
            $b = Db::one('SELECT * FROM wm_bestellungen WHERE id = ?', [$id]);
            if (!$b) { return; }
            $pos = Db::one('SELECT name, variante FROM wm_positionen WHERE bestellung_id = ? ORDER BY id LIMIT 1', [$id]);
            $sp = Db::wert('SELECT sprache FROM partner WHERE id = ?', [(int) $b['partner_id']], 'it');
            $sp = in_array($sp, ['it', 'de', 'en'], true) ? (string) $sp : 'it';
            Partner::schreiben((int) $b['partner_id'], $anlass, [
                'nummer' => (string) $b['nummer'],
                'betrag' => Werbemittel::euro((int) $b['summe_cent']),
                'produkt' => trim(($pos['name'] ?? '') . ' · ' . ($pos['variante'] ?? ''), ' ·'),
                'tracking' => (string) ($b['tracking'] ?? ''),
                'tracking_url' => (string) ($b['tracking_url'] ?? ''),
                'zahlung' => Texte::h(Texte::PARTNER_WERBEMITTEL[self::zahlweg() === 'stripe' ? 'mail_stripe' : 'mail_anfrage'], $sp),
            ] + $extra, self::$senden);
        } catch (Throwable $e) { error_log('WmBestellung::mailen ' . $anlass . ' ' . $id . ': ' . $e->getMessage()); }
    }

    // ---- Lesen -------------------------------------------------------------------

    /** Für die Verwaltung: alles, mit Positionen (inkl. Einkauf). */
    public static function verwaltung(int $anzahl = 200): array
    {
        // Ohne das Foto selbst (MEDIUMBLOB) — die Verwaltung lädt es über reklamationFoto().
        $b = Db::all('SELECT b.*, b.reklamation_foto IS NOT NULL AS reklamation_mit_foto, p.name AS partner, p.code FROM wm_bestellungen b JOIN partner p ON p.id = b.partner_id
                       ORDER BY b.id DESC LIMIT ' . max(1, min(1000, $anzahl)));
        return self::mitPositionen($b, true);
    }

    /** Für den Partner: nur seine, ohne Einkauf, Anbieter-Auftragsnummer, Notiz oder Stripe-Kennungen. */
    public static function fuerPartner(int $partnerId): array
    {
        $b = Db::all('SELECT id, nummer, status, summe_cent, steuer_cent, waehrung, adresse, created_at, bezahlt_am, beim_drucker_am, versendet_am,
                             storniert_am, tracking, tracking_url, zugestellt_am, zugestellt_wie, reklamation_am, reklamation_grund,
                             reklamation_foto IS NOT NULL AS reklamation_mit_foto, reklamation_entscheid, reklamation_antwort, reklamation_entschieden_am
                        FROM wm_bestellungen WHERE partner_id = ? ORDER BY id DESC LIMIT 50', [$partnerId]);
        return self::mitPositionen($b, false);
    }

    private static function mitPositionen(array $kopf, bool $admin): array
    {
        if (!$kopf) { return []; }
        $ids = array_map(static fn($r) => (int) $r['id'], $kopf);
        $felder = $admin ? '*' : 'id, bestellung_id, produkt_nummer, name, variante, auflage, menge, preis_cent, entwurf_id';
        $pos = Db::all("SELECT $felder FROM wm_positionen WHERE bestellung_id IN (" . implode(',', $ids) . ') ORDER BY id');
        $je = [];
        foreach ($pos as $x) { $je[(int) $x['bestellung_id']][] = $x; }
        foreach ($kopf as &$k) {
            unset($k['reklamation_foto']);
            $k['positionen'] = $je[(int) $k['id']] ?? [];
            $k['adresse'] = (array) json_decode((string) $k['adresse'], true);
        }
        unset($k);
        return $kopf;
    }

    /**
     * Rückfall für einen ausgefallenen Webhook: offene Bestellungen mit
     * Bezahlseite bei Stripe nachfragen (nur lesen). $stripe mit sitzungLesen().
     */
    public static function abgleichen(object $stripe): int
    {
        $n = 0;
        foreach (Db::all("SELECT id, stripe_sitzung FROM wm_bestellungen WHERE status = 'offen' AND stripe_sitzung IS NOT NULL
                           AND created_at > NOW() - INTERVAL 3 DAY AND updated_at < NOW() - INTERVAL 5 MINUTE LIMIT 20") as $b) {
            try {
                $s = $stripe->sitzungLesen((string) $b['stripe_sitzung']);
                if (!empty($s['bezahlt']) && self::bezahltVonStripe((int) $b['id'], (string) $s['referenz'], (int) $s['betrag'], (string) $s['waehrung']) === 'gebucht') { $n++; }
            } catch (Throwable $e) { error_log('WmBestellung::abgleichen ' . $b['id'] . ': ' . $e->getMessage()); }
        }
        return $n;
    }
}
