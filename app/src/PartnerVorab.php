<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Partner.php';

/* ==========================================================================
   PartnerVorab.php — der vereinbarte Festpreis vom Partner (02.10.2026).

   Uwe: „Partner können vorab Preise, die mit dem Kunden geklärt waren,
   eingeben und als Link schicken, wo sein persönliches Dashboard ist, ohne
   Fragebogen. Der Kunde wird anhand des Partners hinterlegt und der Preis
   wird in der Verwaltung beim Kunden angezeigt. Einzige, was der Kunde
   eingeben muss, ist Name, E-Mail, Impressum usw. Danach folgt es ganz
   normal der Kette.“ Dazu (Rückfrage): Uwe gibt das Angebot frei; der
   Partner trägt Preis + kurzen Leistungstext ein.

   DER WEG
     1. Partner: Preis, Leistungen, Sprache, eigene Notiz → Link (vorab.php?v=…).
        Den Link verschickt der PARTNER selbst. Von hier geht nichts raus.
     2. Kunde: öffnet den Link, sieht Preis und Leistungen, trägt Name,
        E-Mail, Telefon und die Impressum-Angaben ein, stimmt Datenschutz
        und AGB zu.
     3. Daraus entstehen — über denselben Eingang wie jede Anfrage
        (Anfrage::annehmen) — Kunde und Anfrage, die Partner-Zuordnung
        (Provision wie gewohnt) und ein Festpreis-Angebot als ENTWURF mit
        dem Leistungstext als Einleitung. Uwe bekommt eine Meldung, klickt
        die Bausteine hinein und sendet. Ab da: die bekannte Kette.
     4. Der Kunde landet sofort in seinem Dashboard — ohne die acht Fragen
        (Zugang::vorhabenOffen kennt das Festpreis-Angebot).

   WAS DER PARTNER SIEHT
     Nur seine eigene Notiz, Preis und Stand („offen“, „eingetragen“,
     „Angebot beim Kunden“, „angenommen“). Keine Kundendaten — wie überall
     im Partnerbereich.
   ========================================================================== */
final class PartnerVorab
{
    public const GUELTIG_TAGE = 60;
    public const MAX_JE_TAG = 20;
    public const MIN_CENTS = 5000;          // 50 € — darunter ist es ein Tippfehler
    public const MAX_CENTS = 10000000;      // 100.000 €

    /** @return array{ok:bool, grund?:string, id?:int, link?:string} */
    public static function anlegen(int $partnerId, array $d): array
    {
        $p = Partner::laden($partnerId);
        if (!$p || $p['status'] !== 'aktiv') { return ['ok' => false, 'grund' => 'panne']; }
        $cents = Partner::centsAusEingabe((string) ($d['preis'] ?? ''));
        if ($cents === null || $cents < self::MIN_CENTS || $cents > self::MAX_CENTS) { return ['ok' => false, 'grund' => 'pv_e_preis']; }
        $leistungen = trim(preg_replace('/[ \t]+/', ' ', (string) ($d['leistungen'] ?? '')) ?? '');
        if (mb_strlen($leistungen) < 3) { return ['ok' => false, 'grund' => 'pv_e_leistungen']; }
        $sprache = in_array((string) ($d['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $d['sprache'] : 'it';
        $heute = (int) Db::wert('SELECT COUNT(*) FROM partner_vorab WHERE partner_id = ? AND created_at >= CURDATE()', [$partnerId], 0);
        if ($heute >= self::MAX_JE_TAG) { return ['ok' => false, 'grund' => 'pv_e_genug']; }

        $token = bin2hex(random_bytes(20));
        $id = Db::insert('partner_vorab', [
            'partner_id'  => $partnerId,
            'token'       => $token,
            'bezeichnung' => mb_substr(trim((string) ($d['bezeichnung'] ?? '')), 0, 120),
            'preis_cents' => $cents,
            'leistungen'  => mb_substr($leistungen, 0, 600),
            'sprache'     => $sprache,
        ]);
        Events::protokoll('partner_vorab', 'Partner ' . $p['name'] . ' legt einen Vorab-Link an (' . self::geld($cents) . ')', null, null, null,
                          ['partner_id' => $partnerId, 'vorab_id' => $id]);
        return ['ok' => true, 'id' => $id, 'link' => self::link($token, $sprache)];
    }

    public static function link(string $token, string $sprache = ''): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/vorab.php?v=' . $token
             . (in_array($sprache, ['de', 'en'], true) ? '&lang=' . $sprache : '');
    }

    /** Die Links dieses Partners mit Stand — ohne Kundendaten. @return list<array<string,mixed>> */
    public static function liste(int $partnerId, int $max = 30): array
    {
        $zeilen = Db::all(
            "SELECT v.id, v.token, v.bezeichnung, v.preis_cents, v.leistungen, v.sprache, v.status, v.created_at, v.eingeloest_at,
                    a.status AS angebot_status
               FROM partner_vorab v LEFT JOIN angebote a ON a.id = v.angebot_id
              WHERE v.partner_id = ? AND v.status <> 'zurueckgezogen'
              ORDER BY v.id DESC LIMIT " . max(1, min(100, $max)), [$partnerId]);
        foreach ($zeilen as &$z) {
            $z['stand'] = self::stand($z);
            $z['link'] = self::link((string) $z['token'], (string) $z['sprache']);
        }
        unset($z);
        return $zeilen;
    }

    /** offen | abgelaufen | eingetragen | beim_kunden | angenommen | abgelehnt */
    public static function stand(array $z): string
    {
        if ((string) $z['status'] === 'offen') {
            return strtotime((string) $z['created_at']) < time() - self::GUELTIG_TAGE * 86400 ? 'abgelaufen' : 'offen';
        }
        return match ((string) ($z['angebot_status'] ?? '')) {
            'gesendet'   => 'beim_kunden',
            'angenommen' => 'angenommen',
            'abgelehnt', 'abgelaufen', 'zurueckgezogen' => 'abgelehnt',
            default      => 'eingetragen',
        };
    }

    public static function zurueckziehen(int $partnerId, int $id): bool
    {
        return Db::run("UPDATE partner_vorab SET status = 'zurueckgezogen' WHERE id = ? AND partner_id = ? AND status = 'offen'",
                       [$id, $partnerId])->rowCount() === 1;
    }

    /** Der offene, gültige Link mit seinem (aktiven) Partner — sonst null. */
    public static function ausToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $token)) { return null; }
        $z = Db::one("SELECT v.*, p.name AS partner_name, p.firma AS partner_firma, p.status AS partner_status
                        FROM partner_vorab v JOIN partner p ON p.id = v.partner_id WHERE v.token = ?", [$token]);
        if (!$z) { return null; }
        $z['gueltig'] = (string) $z['status'] === 'offen' && (string) $z['partner_status'] === 'aktiv'
                     && strtotime((string) $z['created_at']) >= time() - self::GUELTIG_TAGE * 86400;
        return $z;
    }

    /** Was der Kunde eintragen muss — mit den Pflichtfeldern. @return array<string,bool> */
    public const FELDER = [
        'name' => true, 'firma' => true, 'email' => true, 'telefon' => true,
        'strasse' => true, 'plz' => true, 'ort' => true, 'land' => true,
        'tax_code' => false, 'vat_id' => false, 'sdi' => false,
    ];

    /**
     * Der Kunde trägt sich ein.
     * @return array{ok:bool, grund?:string, fehlt?:list<string>, link?:string, kunde_id?:int, angebot_id?:int}
     */
    public static function einloesen(string $token, array $d): array
    {
        $v = self::ausToken($token);
        if ($v === null) { return ['ok' => false, 'grund' => 'unbekannt']; }
        if (!$v['gueltig']) { return ['ok' => false, 'grund' => (string) $v['status'] === 'offen' ? 'abgelaufen' : 'schon']; }

        $w = [];
        $fehlt = [];
        foreach (self::FELDER as $f => $pflicht) {
            $w[$f] = trim(preg_replace('/\s+/', ' ', (string) ($d[$f] ?? '')) ?? '');
            if ($pflicht && $w[$f] === '') { $fehlt[] = $f; }
        }
        $w['email'] = mb_strtolower($w['email']);
        if ($w['email'] !== '' && !filter_var($w['email'], FILTER_VALIDATE_EMAIL)) { $fehlt[] = 'email'; }
        if (empty($d['zustimmung'])) { $fehlt[] = 'zustimmung'; }
        if ($fehlt) { return ['ok' => false, 'grund' => 'fehlt', 'fehlt' => array_values(array_unique($fehlt))]; }

        /* Erst den Link für sich beanspruchen — ein Doppelklick oder zwei
           Geräte dürfen keine zwei Kunden und keine zwei Angebote erzeugen. */
        if (Db::run("UPDATE partner_vorab SET status = 'einloesen' WHERE id = ? AND status = 'offen'", [(int) $v['id']])->rowCount() !== 1) {
            return ['ok' => false, 'grund' => 'schon'];
        }
        try {
            $sprache = (string) $v['sprache'];
            $preis = self::geld((int) $v['preis_cents']);
            $empfohlen = trim((string) $v['partner_firma']) !== ''
                ? trim((string) $v['partner_name']) . ' (' . trim((string) $v['partner_firma']) . ')' : trim((string) $v['partner_name']);

            require_once __DIR__ . '/Anfrage.php';
            $anfrage = Anfrage::annehmen([
                'name' => $w['name'], 'email' => $w['email'], 'telefon' => $w['telefon'], 'firma' => $w['firma'],
                'sprache' => $sprache, 'sprache_gefragt' => true,
                'nachricht' => "Festpreis mit Partner vereinbart: $preis\nLeistungen: " . $v['leistungen'],
                'empfohlen_von' => $empfohlen,
            ]);
            if ($anfrage === null) { throw new RuntimeException('Anfrage ließ sich nicht anlegen.'); }
            $kid = (int) Db::wert('SELECT customer_id FROM anfragen WHERE id = ?', [$anfrage], 0);
            if ($kid <= 0) { throw new RuntimeException('Kein Kunde zur Anfrage.'); }

            /* Impressum-Angaben: leere Felder der Akte ergänzen, nie überschreiben
               (wie Anfrage::annehmen) — eine gepflegte Akte bleibt, wie sie ist. */
            foreach (['street' => 'strasse', 'zip' => 'plz', 'city' => 'ort', 'country' => 'land',
                      'tax_code' => 'tax_code', 'vat_id' => 'vat_id', 'sdi' => 'sdi'] as $spalte => $feld) {
                if ($w[$feld] === '') { continue; }
                $leer = $spalte === 'country' ? "($spalte IS NULL OR $spalte = '' OR $spalte = 'Italien')" : "($spalte IS NULL OR $spalte = '')";
                Db::run("UPDATE customers SET $spalte = ? WHERE id = ? AND $leer",
                    [mb_substr($w[$feld], 0, in_array($spalte, ['zip', 'tax_code', 'vat_id'], true) ? 32 : 160), $kid]);
            }

            $zu = Partner::zuordnen($kid, (int) $v['partner_id'], 'vorab');

            require_once __DIR__ . '/Angebot.php';
            $aid = Angebot::festpreisNeu($kid, (int) $v['preis_cents'], $sprache);
            if (!is_int($aid)) { throw new RuntimeException('Festpreis-Angebot: ' . $aid); }
            Db::update('angebote', $aid, ['einleitung' => mb_substr((string) $v['leistungen'], 0, 600)]);

            Db::run("UPDATE partner_vorab SET status = 'eingeloest', customer_id = ?, angebot_id = ?, eingeloest_at = NOW() WHERE id = ?",
                    [$kid, $aid, (int) $v['id']]);

            Events::protokoll('partner_vorab_kunde', 'Vorab-Link von Partner ' . $v['partner_name'] . ' eingelöst — Festpreis ' . $preis, $kid, null, null,
                              ['partner_id' => (int) $v['partner_id'], 'vorab_id' => (int) $v['id'], 'angebot_id' => $aid, 'zuordnung' => $zu]);
            /* Kundendaten nur im Text, nicht im Titel — der Titel kann aufs Handy gehen. */
            Events::melden('partner_vorab', 'Festpreis vom Partner eingetragen — ' . $preis, 'gut',
                ($w['firma'] !== '' ? $w['firma'] : $w['name']) . ': ' . $v['partner_name'] . ' hat ' . $preis . ' vereinbart („' . mb_substr((string) $v['leistungen'], 0, 160) . '“). '
                . 'Bausteine hineinklicken und das Angebot senden.'
                . ($zu === 'zugeordnet' ? '' : ' Achtung: NICHT dem Partner zugeordnet (' . $zu . ').'),
                '/angebote/' . $aid);

            require_once __DIR__ . '/Kundenzugang.php';
            return ['ok' => true, 'link' => Kundenzugang::linkFuer($kid, $sprache), 'kunde_id' => $kid, 'angebot_id' => $aid];
        } catch (Throwable $e) {
            /* Den Link wieder freigeben, damit der Kunde es nochmal versuchen kann. */
            try { Db::run("UPDATE partner_vorab SET status = 'offen' WHERE id = ? AND status = 'einloesen'", [(int) $v['id']]); } catch (Throwable $e2) { }
            throw $e;
        }
    }

    /** Für die Kundenakte und das Dashboard: der eingelöste Vorab-Link dieses Kunden. */
    public static function zuKunde(int $kundeId): ?array
    {
        try {
            return Db::one("SELECT v.*, p.name AS partner_name, p.id AS pid FROM partner_vorab v JOIN partner p ON p.id = v.partner_id
                             WHERE v.customer_id = ? AND v.status = 'eingeloest' ORDER BY v.id DESC LIMIT 1", [$kundeId]);
        } catch (Throwable $e) {
            return null;     // Tabelle noch nicht da (zwischen Deploy und Migration)
        }
    }

    public static function zuAngebot(int $angebotId): ?array
    {
        try {
            return Db::one("SELECT v.*, p.name AS partner_name, p.id AS pid FROM partner_vorab v JOIN partner p ON p.id = v.partner_id
                             WHERE v.angebot_id = ? LIMIT 1", [$angebotId]);
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function geld(int $cents): string
    {
        require_once __DIR__ . '/Fmt.php';
        return Fmt::geld($cents);
    }
}
