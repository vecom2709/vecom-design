<?php
declare(strict_types=1);

/* ==========================================================================
   Druckerei.php — gemeinsame Teile der angebundenen Druckereien
   (Marketing Center, 04.10.2026).

   REGISTER statt verstreuter if-Abfragen: Welche Druckerei per Schnittstelle
   beliefert werden kann, steht hier an EINER Stelle (ANGEBUNDEN). Jede Klasse
   dort hat NAME, bereit(?string $land), nachsehen() und eine Methode zum
   Senden. Eine neue Druckerei (Print.com, Pixartprinting …) wird hier
   eingetragen, sobald ihre Doku vorliegt — vorher nicht.

   SPERRE / FEHLER / ERLEDIGT: dieselbe Regel für alle — senden genau einmal,
   ein Fehler bleibt stehen und wird nicht wiederholt („Wenn API-Bestellung
   fehlschlägt: NICHT automatisch mehrfach bestellen.“).
   ========================================================================== */
final class Druckerei
{
    /** Name (wie in den Angeboten) => Klasse. */
    public const ANGEBUNDEN = ['Gelato' => 'Gelato', 'HelloPrint' => 'HelloPrint', 'Printful' => 'Printful'];

    private static function laden(string $klasse): void
    {
        require_once __DIR__ . '/' . $klasse . '.php';
        // Jede Klasse im Register erfüllt den Vertrag (DruckereiSchnittstelle.php) — sonst lieber laut.
        if (!is_subclass_of($klasse, DruckereiAnbieter::class)) { throw new LogicException($klasse . ' ist keine DruckereiAnbieter.'); }
    }

    /**
     * Klassen mit Preis-Schnittstelle (DruckereiPreise), die gerade bereit sind — statt einer
     * festen Liste an jeder Stelle, die Preise holt (04.10.2026, Marketingcenter Schritt 1c).
     * @return list<class-string<DruckereiPreise>>
     */
    public static function mitPreisen(): array
    {
        $aus = [];
        foreach (self::ANGEBUNDEN as $klasse) {
            self::laden($klasse);
            if (is_subclass_of($klasse, DruckereiPreise::class) && $klasse::bereit()) { $aus[] = $klasse; }
        }
        return $aus;
    }

    /** Namen der Druckereien, die für $land gerade per Schnittstelle beliefert werden können. */
    public static function bereitFuer(string $land): array
    {
        $aus = [];
        foreach (self::ANGEBUNDEN as $name => $klasse) {
            self::laden($klasse);
            if ($klasse::bereit($land)) { $aus[] = $name; }
        }
        return $aus;
    }

    /** Auftrag an die Druckerei $name (echter Auftrag). @return array{ok:bool, grund:string, id?:string} */
    public static function senden(string $name, int $bestellungId): array
    {
        foreach (self::ANGEBUNDEN as $n => $klasse) {
            if (strcasecmp($n, $name) !== 0) { continue; }
            self::laden($klasse);
            if (!$klasse::bereit()) { break; }
            /* Vor dem Auftrag den Preis neu holen (Uwe, 04.10.2026: „nicht dass man
               draufzahlt“): Ist es bei der Druckerei inzwischen teurer als beim
               Bestellen eingefroren, geht NICHTS raus — Uwe entscheidet. */
            // Schon gesendet oder nicht bezahlt: gar nicht erst nachfragen (die Klasse lehnt es ohnehin ab).
            if (!Db::wert("SELECT id FROM wm_bestellungen WHERE id = ? AND status = 'bezahlt' AND anbieter_status IS NULL", [$bestellungId])) {
                return ['ok' => false, 'grund' => 'Diese Bestellung wurde schon gesendet (oder ist nicht bezahlt).'];
            }
            if (method_exists($klasse, 'preisJetzt')) {
                require_once __DIR__ . '/Fmt.php';
                $ek = (int) Db::wert('SELECT COALESCE(SUM(einkauf_cent * menge), 0) FROM wm_positionen WHERE bestellung_id = ?', [$bestellungId], 0);
                $jetzt = $klasse::preisJetzt($bestellungId);
                if ($jetzt === null) { return ['ok' => false, 'grund' => 'Aktueller Preis bei ' . $n . ' nicht abrufbar — nichts gesendet. Bitte prüfen und von Hand beauftragen.']; }
                if ($jetzt > $ek) {
                    return ['ok' => false, 'grund' => $n . ' ist teurer geworden: jetzt ' . Fmt::geld($jetzt, 'EUR') . ', beim Bestellen ' . Fmt::geld($ek, 'EUR') . ' — nichts gesendet, damit kein Verlust entsteht.'];
                }
            }
            return $klasse::auftragSenden($bestellungId);
        }
        return ['ok' => false, 'grund' => 'keine Anbindung'];
    }

    /** Cron: bei allen angebundenen Druckereien nachsehen. */
    public static function nachsehenAlle(): int
    {
        $n = 0;
        foreach (self::ANGEBUNDEN as $klasse) {
            self::laden($klasse);
            try { $n += $klasse::nachsehen(); } catch (Throwable $e) { error_log('Druckerei::nachsehenAlle ' . $klasse . ': ' . $e->getMessage()); }
        }
        return $n;
    }

    /** Artikelnummer einer Variante bei einer Druckerei (Schlüssel klein, z. B. 'helloprint'); leer = Zuordnung löschen. */
    public static function artikelSetzen(int $varianteId, string $anbieter, string $artikel, int $menge): void
    {
        $anbieter = strtolower(trim($anbieter));
        if (!in_array($anbieter, array_map('strtolower', array_keys(self::ANGEBUNDEN)), true)) { throw new InvalidArgumentException('Druckerei unbekannt.'); }
        $artikel = trim($artikel);
        if ($artikel === '') {
            Db::run('DELETE FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = ?', [$varianteId, $anbieter]);
            return;
        }
        if (!preg_match('#^[A-Za-z0-9_~.:-]{3,200}$#', $artikel)) { throw new InvalidArgumentException('Artikelnummer ungültig.'); }
        if ($menge < 1 || $menge > 100000) { throw new InvalidArgumentException('Menge ungültig.'); }
        Db::run('INSERT INTO wm_anbieter_produkte (variante_id, anbieter, artikel, menge) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE artikel = VALUES(artikel), menge = VALUES(menge)', [$varianteId, $anbieter, $artikel, $menge]);
    }

    public static function artikel(int $varianteId, string $anbieter): ?array
    {
        return Db::one('SELECT artikel, menge FROM wm_anbieter_produkte WHERE variante_id = ? AND anbieter = ?', [$varianteId, strtolower($anbieter)]) ?: null;
    }

    /** Vor dem Senden: in EINER Anweisung „wird gesendet“ — nur wenn noch nichts gesendet wurde. */
    public static function sperren(int $id, string $name): bool
    {
        return Db::run("UPDATE wm_bestellungen SET anbieter = ?, anbieter_status = 'wird_gesendet', anbieter_fehler = NULL, anbieter_am = NOW()
                         WHERE id = ? AND status = 'bezahlt' AND anbieter_status IS NULL", [$name, $id])->rowCount() === 1;
    }

    public static function fehler(int $id, string $text): void
    {
        Db::run("UPDATE wm_bestellungen SET anbieter_status = 'fehler', anbieter_fehler = ? WHERE id = ?", [mb_substr($text, 0, 500), $id]);
        Events::melden('wm_druckerei_fehler', 'Druckerei: Auftrag nicht angelegt', 'schlecht', mb_substr($text, 0, 480), '/werbemittel/bestellungen');
    }

    /** Auftrag angelegt: Bezug merken, Bestellung „beim Drucker“. */
    public static function erledigt(int $id, string $ref): void
    {
        Db::run("UPDATE wm_bestellungen SET anbieter_ref = ?, anbieter_status = 'auftrag', status = 'beim_drucker', beim_drucker_am = NOW() WHERE id = ? AND status = 'bezahlt'",
            [mb_substr($ref, 0, 120), $id]);
    }

    /**
     * PROBE-ENTWURF (04.10.2026, Uwe: Test-Entwurf ansehen und wieder löschen).
     * Prüft mit einer MUSTERKARTE — kein Partner, kein Partnerinhalt —, ob die
     * Druckerei unsere Datei annimmt. Immer nur Entwurf (Gelato orderType
     * „draft“, Printful ohne confirm): gedruckt und berechnet wird nichts,
     * solange Uwe ihn nicht im Dashboard bestätigt. Keine Bestellung in der
     * Datenbank, nur ein Eintrag im Protokoll.
     */
    public const MUSTER = ['id' => 0, 'name' => 'Mario Rossi', 'code' => 'PROBE', 'email' => 'kontakt@vecom-design.it', 'sprache' => 'it'];

    /** Adresse auf dem Entwurf: deutlich als Probe beschriftet (wird nie versendet). */
    public const MUSTER_ADRESSE = ['name' => 'PROBE Nicht-drucken', 'firma' => 'Vecom Design', 'strasse' => 'Via Atenea 1', 'plz' => '92100', 'ort' => 'Agrigento', 'land' => 'IT'];

    /** Die Musterkarte in der Fassung $fassung (probe_druck = PDF für Gelato, probe_pf_* = JPEG für Printful). */
    public static function musterDatei(string $fassung): string
    {
        // Dieselben Bausteine wie im Partnerbereich (Link, QR, Name) — druckdatei.php lädt sie sonst nicht.
        foreach (['Fmt', 'Partner', 'PartnerWerbung', 'PartnerKarten'] as $k) { require_once __DIR__ . '/' . $k . '.php'; }
        return match ($fassung) {
            'probe_druck' => PartnerKarten::druckPdf(self::MUSTER, 'a', 'it', 'email', 4.0, 300),
            'probe_pf_vorn', 'probe_pf_hinten' => (static function () use ($fassung): string {
                require_once __DIR__ . '/Printful.php';
                return PartnerKarten::eingepasst(self::MUSTER, 'a', $fassung === 'probe_pf_vorn' ? 'vorn' : 'hinten', 'it', 'email', Printful::VORLAGE[0], Printful::VORLAGE[1]);
            })(),
            default => '',
        };
    }

    /** Probe-Entwurf an die Druckerei $name. @return array{ok:bool, grund:string, id?:string} */
    public static function probeSenden(string $name): array
    {
        foreach (self::ANGEBUNDEN as $n => $klasse) {
            if (strcasecmp($n, $name) !== 0) { continue; }
            self::laden($klasse);
            if (!$klasse::bereit() || !method_exists($klasse, 'probeSenden')) { break; }
            $r = $klasse::probeSenden();
            if ($r['ok']) { Events::protokoll('wm_probe', 'Probe-Entwurf an ' . $n . ' (Musterkarte, nichts gedruckt)', null, null, null, ['anbieter' => $n, 'ref' => $r['id'] ?? '']); }
            return $r;
        }
        return ['ok' => false, 'grund' => $name . ' ist nicht angebunden.'];
    }

    /**
     * Schlüssel für die Druckdatei-Links. Eigentlich `app_geheim` — fehlt der
     * (so am 04.10.2026 auf dem Server: der Probe-Entwurf scheiterte daran),
     * wird er aus `hosting_geheim` abgeleitet (HMAC mit eigenem Zweck, also
     * ein anderer Schlüssel als der für die Hosting-Zugänge). Nie erratbar:
     * ohne beides gibt es keinen Link.
     */
    public static function linkGeheim(?array $cfg = null): string
    {
        $g = (string) ($cfg !== null ? ($cfg['app_geheim'] ?? '') : Config::get('app_geheim', ''));
        if (strlen($g) >= 16) { return $g; }
        $h = (string) ($cfg !== null ? ($cfg['hosting_geheim'] ?? '') : Config::get('hosting_geheim', ''));
        return strlen($h) >= 32 ? hash_hmac('sha256', 'vecom|druckdatei-links', $h) : '';
    }

    /** Fassungen einer Druckdatei, die ein Link ausliefern darf. */
    public const FASSUNGEN = ['druck', 'frei', 'pf_vorn', 'pf_hinten', 'probe_druck', 'probe_pf_vorn', 'probe_pf_hinten'];

    /**
     * Unterschriebener, befristeter Link zur Druckdatei. $fassung: 'druck'
     * (4 mm Beschnitt, Gelato), 'frei' (genau die freigegebene Datei, 3 mm)
     * oder 'pf_vorn'/'pf_hinten' (Printful: eingepasst 90 × 50 mm, JPEG je
     * Seite — vom Partner vor der Freigabe gesehen). Ohne app_geheim kein Link.
     */
    public static function dateiLink(int $entwurfId, string $fassung = 'frei', int $tage = 14): string
    {
        $fassung = in_array($fassung, self::FASSUNGEN, true) ? $fassung : 'frei';
        $geheim = self::linkGeheim();
        if (strlen($geheim) < 16) { throw new RuntimeException('app_geheim (oder hosting_geheim) fehlt in config.local.php — ohne ihn kein Link für die Druckdatei.'); }
        $bis = time() + $tage * 86400;
        $sig = hash_hmac('sha256', 'wm-druck|' . $entwurfId . '|' . $bis . '|' . $fassung, $geheim);
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/druckdatei.php?' . http_build_query(['e' => $entwurfId, 'x' => $bis, 'f' => $fassung, 's' => $sig]);
    }

    /** Prüft einen Link aus dateiLink(). @return array{0:int,1:string} [Entwurfs-id oder 0, Fassung] */
    public static function linkPruefen(string $e, string $x, string $f, string $s): array
    {
        $geheim = self::linkGeheim();
        if (!in_array($f, self::FASSUNGEN, true) || strlen($geheim) < 16 || !ctype_digit($e) || !ctype_digit($x) || (int) $x < time()) { return [0, '']; }
        return hash_equals(hash_hmac('sha256', 'wm-druck|' . $e . '|' . $x . '|' . $f, $geheim), $s) ? [(int) $e, $f] : [0, ''];
    }
}
