<?php
declare(strict_types=1);

/**
 * Die eigenen Firmendaten — das, was auf einem Beleg oben steht.
 *
 * Sie liegen in der Tabelle settings und lassen sich in der Verwaltung
 * pflegen. Ohne SSH ist das der einzige Weg, sie zu aendern, ohne eine
 * Datei auf dem Server zu bearbeiten.
 *
 * Der wichtigste Punkt hier ist die Partita IVA. Ist keine hinterlegt,
 * heisst das Dokument "Zahlungsbeleg" und nicht "Rechnung" — wer keine
 * Umsatzsteuernummer hat, stellt keine Rechnung im steuerlichen Sinn aus.
 * Welcher Satz genau daruntergehoert, sagt der Commercialista; er steht
 * als freier Text in den Einstellungen und nicht im Code.
 */
final class Firma
{
    private const FELDER = [
        'firma_name'      => 'Vecom Design',
        'firma_inhaber'   => 'Uwe Vetter',
        'firma_strasse'   => '',
        'firma_plz'       => '',
        'firma_ort'       => 'Aragona (AG)',
        'firma_land'      => 'Italien',
        'firma_email'     => 'kontakt@vecom-design.it',
        'firma_telefon'   => '',
        'firma_web'       => 'vecom-design.it',
        'firma_piva'      => '',
        'firma_steuernr'  => '',
        'firma_iban'      => '',
        'firma_bank'      => '',
        'firma_mwst'      => '0',
        'firma_hinweis'   => '',
    ];

    /** @var array<string,string>|null */
    private static ?array $werte = null;

    public static function alle(): array
    {
        if (self::$werte === null) {
            $gespeichert = [];
            try {
                foreach (Db::all("SELECT skey, svalue FROM settings WHERE skey LIKE 'firma\\_%'") as $z) {
                    $gespeichert[(string) $z['skey']] = (string) ($z['svalue'] ?? '');
                }
            } catch (Throwable $e) { /* dann die Vorgaben */ }
            self::$werte = [];
            foreach (self::FELDER as $schluessel => $vorgabe) {
                $wert = $gespeichert[$schluessel] ?? '';
                self::$werte[$schluessel] = $wert !== '' ? $wert : $vorgabe;
            }
        }
        return self::$werte;
    }

    public static function get(string $feld, string $ersatz = ''): string
    {
        $k = str_starts_with($feld, 'firma_') ? $feld : 'firma_' . $feld;
        $w = self::alle()[$k] ?? '';
        return $w !== '' ? $w : $ersatz;
    }

    public static function speichern(array $eingabe): void
    {
        foreach (array_keys(self::FELDER) as $schluessel) {
            if (!array_key_exists($schluessel, $eingabe)) { continue; }
            $wert = trim((string) $eingabe[$schluessel]);
            Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$schluessel, mb_substr($wert, 0, 2000)]);
        }
        self::$werte = null;
    }

    /** Der Steuersatz als Zahl, zum Beispiel 22.0 fuer 22 %. */
    public static function mwst(): float
    {
        return (float) str_replace(',', '.', self::get('mwst', '0'));
    }

    /** Ohne Umsatzsteuernummer ist es ein Beleg, keine Rechnung. */
    public static function istRechnungsberechtigt(): bool
    {
        return self::get('piva') !== '';
    }

    /** Die Anschrift als Zeilen, leere ausgelassen. */
    public static function anschrift(): array
    {
        $zeilen = [self::get('name'), self::get('inhaber')];
        $strasse = self::get('strasse');
        if ($strasse !== '') { $zeilen[] = $strasse; }
        $ort = trim(self::get('plz') . ' ' . self::get('ort'));
        if ($ort !== '') { $zeilen[] = $ort; }
        $land = self::get('land');
        if ($land !== '') { $zeilen[] = $land; }
        return array_values(array_filter($zeilen, static fn($z) => trim($z) !== ''));
    }

    /** Was unten auf dem Beleg steht: Kontakt, Steuernummern, Bank. */
    public static function fusszeilen(): array
    {
        $aus = [];
        $kontakt = array_filter([self::get('email'), self::get('telefon'), self::get('web')]);
        if ($kontakt) { $aus[] = implode('  ·  ', $kontakt); }

        $steuer = [];
        if (self::get('piva') !== '')     { $steuer[] = 'P. IVA ' . self::get('piva'); }
        if (self::get('steuernr') !== '') { $steuer[] = 'C.F. ' . self::get('steuernr'); }
        if ($steuer) { $aus[] = implode('  ·  ', $steuer); }

        $bank = array_filter([self::get('bank'), self::get('iban') !== '' ? 'IBAN ' . self::get('iban') : '']);
        if ($bank) { $aus[] = implode('  ·  ', $bank); }

        return $aus;
    }
}
