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
        'firma_regime'    => 'normal',
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

    /**
     * Der Steuersatz als Zahl, zum Beispiel 22.0 fuer 22 %.
     *
     * Zwei Riegel davor, und beide sind wichtig genug, um hier zu stehen und
     * nicht im Formular:
     *
     * 1. OHNE Partita IVA ist der Satz immer 0. Wer keine Nummer hat, darf
     *    keine Steuer ausweisen — steht trotzdem ein Satz in den
     *    Einstellungen, waere das Ergebnis ein Dokument, das Steuer
     *    aufschluesselt und im selben Atemzug erklaert, es sei keine
     *    Rechnung. Genau dieser Widerspruch stand hier am 01.09.2026.
     * 2. Im regime forfettario wird ebenfalls keine Steuer ausgewiesen —
     *    dafuer gehoert der gesetzliche Hinweis auf die Rechnung, siehe
     *    pflichthinweis().
     */
    public static function mwst(): float
    {
        if (!self::istRechnungsberechtigt()) { return 0.0; }
        if (self::regime() === 'forfettario') { return 0.0; }
        return (float) str_replace(',', '.', self::get('mwst', '0'));
    }

    /** Der eingetragene Satz, ungeachtet der Riegel — fuer die Anzeige. */
    public static function mwstEingetragen(): float
    {
        return (float) str_replace(',', '.', self::get('mwst', '0'));
    }

    /** 'normal' oder 'forfettario'. Ohne Partita IVA ohne Wirkung. */
    public static function regime(): string
    {
        return self::get('regime', 'normal') === 'forfettario' ? 'forfettario' : 'normal';
    }

    /**
     * Der Satz, der von Gesetzes wegen auf dem Dokument stehen muss.
     *
     * Ohne Partita IVA ist es kein Rechnungshinweis, sondern die schlichte
     * Feststellung, dass es keine Rechnung ist. Im forfettario ist es der
     * vorgeschriebene Wortlaut aus der Legge 190/2014. Welcher Fall gilt,
     * sagt der Commercialista — hier wird nur ausgegeben, was zur
     * Einstellung passt.
     */
    public static function pflichthinweis(): string
    {
        if (!self::istRechnungsberechtigt()) {
            return 'Dies ist ein Zahlungsbeleg, keine Rechnung im steuerlichen Sinn.';
        }
        if (self::regime() === 'forfettario') {
            return 'Operazione senza applicazione dell\'IVA ai sensi dell\'articolo 1, '
                . 'commi da 54 a 89, della Legge n. 190/2014 e successive modificazioni.';
        }
        return '';
    }

    /**
     * Marca da bollo: im forfettario ab 77,47 EUR faellig, 2 EUR.
     * Der Betrag kommt in Cent herein.
     */
    public static function bolloNoetig(int $betragCent): bool
    {
        return self::istRechnungsberechtigt()
            && self::regime() === 'forfettario'
            && $betragCent > 7747;
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
