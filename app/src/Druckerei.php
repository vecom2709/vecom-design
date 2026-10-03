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
    public const ANGEBUNDEN = ['Gelato' => 'Gelato', 'HelloPrint' => 'HelloPrint'];

    private static function laden(string $klasse): void
    {
        require_once __DIR__ . '/' . $klasse . '.php';
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
            return $klasse === 'Gelato' ? Gelato::entwurfSenden($bestellungId, true) : $klasse::auftragSenden($bestellungId);
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
     * Unterschriebener, befristeter Link zur Druckdatei. $fassung: 'druck'
     * (4 mm Beschnitt, Gelato) oder 'frei' (genau die freigegebene Datei,
     * 3 mm). Ohne app_geheim kein Link.
     */
    public static function dateiLink(int $entwurfId, string $fassung = 'frei', int $tage = 14): string
    {
        $fassung = $fassung === 'druck' ? 'druck' : 'frei';
        $geheim = (string) Config::get('app_geheim', '');
        if (strlen($geheim) < 16) { throw new RuntimeException('app_geheim fehlt in config.local.php — ohne ihn kein Link für die Druckdatei.'); }
        $bis = time() + $tage * 86400;
        $sig = hash_hmac('sha256', 'wm-druck|' . $entwurfId . '|' . $bis . '|' . $fassung, $geheim);
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/druckdatei.php?' . http_build_query(['e' => $entwurfId, 'x' => $bis, 'f' => $fassung, 's' => $sig]);
    }

    /** Prüft einen Link aus dateiLink(). @return array{0:int,1:string} [Entwurfs-id oder 0, Fassung] */
    public static function linkPruefen(string $e, string $x, string $f, string $s): array
    {
        $geheim = (string) Config::get('app_geheim', '');
        if (!in_array($f, ['druck', 'frei'], true) || strlen($geheim) < 16 || !ctype_digit($e) || !ctype_digit($x) || (int) $x < time()) { return [0, '']; }
        return hash_equals(hash_hmac('sha256', 'wm-druck|' . $e . '|' . $x . '|' . $f, $geheim), $s) ? [(int) $e, $f] : [0, ''];
    }
}
