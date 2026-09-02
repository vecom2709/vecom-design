<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';

/**
 * Eingangsbelege: was das Geschaeft kostet.
 *
 * WARUM DAS MITGESCHRIEBEN WIRD, OBWOHL ES NICHTS SPART
 *
 * Im Regime forfettario werden Kosten nicht einzeln abgezogen — der Gewinn
 * ergibt sich aus einem festen Prozentsatz der Einnahmen. Trotzdem gilt:
 *
 *   1. Eingangsrechnungen muessen nummeriert und aufbewahrt werden.
 *      Comma 59 der L. 190/2014 nimmt den Forfettario von den IVA-Pflichten
 *      aus, "ad eccezione degli obblighi di numerazione e di conservazione
 *      delle fatture di acquisto".
 *   2. Leistungen von auslaendischen Anbietern loesen Reverse Charge aus.
 *      Da faellt italienische IVA an, die wirklich zu zahlen ist. Genau die
 *      Liste will der Commercialista sehen — und genau die hat sonst niemand.
 *   3. Ohne Ausgabenseite laesst sich kein Konto abgleichen. Bei einer
 *      Pruefung ist die erste Frage, was die ungeklaerten Abgaenge waren.
 *
 * Die Nummer ist eine eigene Reihe (EA-Jahr-lfd) und hat mit den eigenen
 * Belegen (BE-/RE-) nichts zu tun. Sie ist die Nummerierung nach comma 59.
 */
final class Ausgabe
{
    public const MAX_BYTES = 15 * 1024 * 1024;

    /** Womit man als Webdesigner tatsaechlich zu tun hat — mehr braucht es nicht. */
    public const KATEGORIEN = [
        'hosting'    => 'Hosting und Domains',
        'software'   => 'Software und Abos',
        'gebuehren'  => 'Zahlungsgebühren',
        'werbung'    => 'Werbung',
        'geraete'    => 'Geräte und Zubehör',
        'buero'      => 'Büro und Material',
        'beratung'   => 'Beratung, Commercialista, Gebühren',
        'beitraege'  => 'Beiträge und Versicherungen',
        'reise'      => 'Fahrt und Reise',
        'sonstiges'  => 'Sonstiges',
    ];

    private const ERLAUBT = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    /** Der Ordner fuer die Belegdateien — entsteht beim ersten Mal, samt Sperre. */
    public static function ordner(): string
    {
        $pfad = dirname(__DIR__) . '/eingang';
        if (!is_dir($pfad)) {
            if (!@mkdir($pfad, 0755, true) && !is_dir($pfad)) {
                throw new RuntimeException('Der Ordner für Eingangsbelege lässt sich nicht anlegen.');
            }
        }
        $sperre = $pfad . '/.htaccess';
        if (!is_file($sperre)) {
            @file_put_contents($sperre, "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n");
        }
        return $pfad;
    }

    /* ================================================================== */

    /** @return list<array<string,mixed>> */
    public static function alle(?int $jahr = null): array
    {
        if ($jahr !== null) {
            return Db::all('SELECT * FROM ausgaben WHERE YEAR(datum) = ? ORDER BY datum DESC, id DESC', [$jahr]);
        }
        return Db::all('SELECT * FROM ausgaben ORDER BY datum DESC, id DESC LIMIT 500');
    }

    public static function eine(int $id): ?array
    {
        return Db::one('SELECT * FROM ausgaben WHERE id = ?', [$id]);
    }

    /** @return list<int> Jahre mit Ausgaben, neuestes zuerst. */
    public static function jahre(): array
    {
        $r = Db::all('SELECT DISTINCT YEAR(datum) AS jahr FROM ausgaben ORDER BY jahr DESC');
        return array_map(static fn($z) => (int) $z['jahr'], $r);
    }

    /**
     * Die naechste freie Nummer im Jahr. Lueckenlos, weil comma 59 eine
     * fortlaufende Nummerierung verlangt — deshalb wird nach der hoechsten
     * vergebenen gezaehlt und nicht nach der Anzahl der Zeilen.
     */
    public static function naechsteNummer(?string $datum = null): string
    {
        $jahr = date('Y', strtotime($datum ?: 'today'));
        $letzte = (string) Db::wert(
            "SELECT beleg_nr FROM ausgaben WHERE beleg_nr LIKE ? ORDER BY beleg_nr DESC LIMIT 1",
            ['EA-' . $jahr . '-%'], '');
        $n = $letzte !== '' && preg_match('~(\d+)$~', $letzte, $t) ? ((int) $t[1]) + 1 : 1;
        return 'EA-' . $jahr . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Anlegen oder aendern. Die Datei ist freiwillig — ohne sie ist der
     * Eintrag trotzdem etwas wert, mit ihr aber erst vollstaendig.
     *
     * @param array<string,mixed> $f Werte aus dem Formular
     * @param array|null $datei Ein Eintrag aus $_FILES oder null
     */
    public static function speichern(array $f, ?array $datei = null, int $id = 0): int
    {
        $datum = trim((string) ($f['datum'] ?? ''));
        if ($datum === '' || strtotime($datum) === false) {
            throw new RuntimeException('Das Datum fehlt oder ist unlesbar.');
        }
        $lieferant = trim((string) ($f['lieferant'] ?? ''));
        if ($lieferant === '') { throw new RuntimeException('Wer hat das gestellt? Ohne Lieferant kein Beleg.'); }

        $netto  = self::cents((string) ($f['netto'] ?? '0'));
        $steuer = self::cents((string) ($f['steuer'] ?? '0'));
        $brutto = self::cents((string) ($f['brutto'] ?? '0'));
        // Wer nur den Endbetrag hat, soll nicht rechnen muessen.
        if ($brutto === 0 && ($netto > 0 || $steuer > 0)) { $brutto = $netto + $steuer; }
        if ($netto === 0 && $brutto > 0)                  { $netto  = $brutto - $steuer; }
        if ($brutto <= 0) { throw new RuntimeException('Ohne Betrag ist der Beleg leer.'); }

        $land = strtoupper(substr(trim((string) ($f['land'] ?? 'IT')), 0, 2)) ?: 'IT';
        $kategorie = (string) ($f['kategorie'] ?? 'sonstiges');
        if (!isset(self::KATEGORIEN[$kategorie])) { $kategorie = 'sonstiges'; }

        $werte = [
            'datum'          => date('Y-m-d', strtotime($datum)),
            'bezahlt_am'     => trim((string) ($f['bezahlt_am'] ?? '')) !== ''
                                ? date('Y-m-d', strtotime((string) $f['bezahlt_am'])) : null,
            'lieferant'      => mb_substr($lieferant, 0, 190),
            'land'           => $land,
            'ust_id'         => mb_substr(trim((string) ($f['ust_id'] ?? '')), 0, 40) ?: null,
            'kategorie'      => $kategorie,
            'titel'          => mb_substr(trim((string) ($f['titel'] ?? '')), 0, 190) ?: null,
            'netto_cents'    => max(0, $netto),
            'steuer_cents'   => max(0, $steuer),
            'brutto_cents'   => $brutto,
            'waehrung'       => strtoupper(substr((string) ($f['waehrung'] ?? 'EUR'), 0, 3)) ?: 'EUR',
            'reverse_charge' => !empty($f['reverse_charge']) ? 1 : 0,
            'zahlweg'        => mb_substr(trim((string) ($f['zahlweg'] ?? '')), 0, 40) ?: null,
            'notiz'          => trim((string) ($f['notiz'] ?? '')) ?: null,
        ];

        if ($id > 0) {
            $vorher = self::eine($id);
            if (!$vorher) { throw new RuntimeException('Diesen Beleg gibt es nicht.'); }
            Db::update('ausgaben', $id, $werte);
        } else {
            $werte['beleg_nr'] = self::naechsteNummer($werte['datum']);
            $id = Db::insert('ausgaben', $werte);
        }

        if ($datei !== null && (int) ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            self::dateiAnnehmen($id, $datei);
        }
        return $id;
    }

    /** Nimmt die Belegdatei an und haengt sie an den Eintrag. */
    public static function dateiAnnehmen(int $id, array $datei): void
    {
        if ((int) ($datei['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Die Datei ist nicht vollständig angekommen.');
        }
        $tmp = (string) ($datei['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Die Datei ist nicht richtig angekommen.');
        }
        $groesse = (int) filesize($tmp);
        if ($groesse <= 0)               { throw new RuntimeException('Die Datei ist leer.'); }
        if ($groesse > self::MAX_BYTES)  { throw new RuntimeException('Die Datei ist größer als ' . Fmt::bytes(self::MAX_BYTES) . '.'); }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $typ = (string) $finfo->file($tmp);
        if (!isset(self::ERLAUBT[$typ])) {
            throw new RuntimeException('Als Beleg gehen PDF, JPG, PNG und WebP — nicht ' . $typ . '.');
        }

        $alt = self::eine($id);
        $name = bin2hex(random_bytes(16)) . '.bin';
        if (!move_uploaded_file($tmp, self::ordner() . '/' . $name)) {
            throw new RuntimeException('Die Datei ließ sich nicht ablegen.');
        }
        @chmod(self::ordner() . '/' . $name, 0644);

        Db::update('ausgaben', $id, [
            'stored_name' => $name,
            'orig_name'   => mb_substr(basename(str_replace('\\', '/', (string) ($datei['name'] ?? 'beleg'))), 0, 190),
            'mime'        => $typ,
            'size_bytes'  => $groesse,
        ]);
        // Erst wenn die neue sicher liegt, verschwindet die alte.
        if ($alt && ($alt['stored_name'] ?? '') !== '') {
            @unlink(self::ordner() . '/' . basename((string) $alt['stored_name']));
        }
    }

    /** Der Pfad zur hinterlegten Datei, oder null. */
    public static function dateipfad(array $a): ?string
    {
        $n = (string) ($a['stored_name'] ?? '');
        if ($n === '') { return null; }
        $pfad = self::ordner() . '/' . basename($n);
        return is_file($pfad) ? $pfad : null;
    }

    public static function loeschen(int $id): void
    {
        $a = self::eine($id);
        if (!$a) { return; }
        $pfad = self::dateipfad($a);
        Db::run('DELETE FROM ausgaben WHERE id = ?', [$id]);
        if ($pfad !== null) { @unlink($pfad); }
    }

    /**
     * "12,50" und "12.50" und "1.234,56" sollen alle dasselbe bedeuten.
     * Wer im Formular tippt, denkt nicht an Trennzeichen.
     */
    public static function cents(string $eingabe): int
    {
        $s = trim(str_replace([' ', "\u{00a0}", '€'], '', $eingabe));
        if ($s === '') { return 0; }
        $komma = strrpos($s, ',');
        $punkt = strrpos($s, '.');
        $trenner = max($komma === false ? -1 : $komma, $punkt === false ? -1 : $punkt);
        if ($trenner >= 0 && strlen($s) - $trenner - 1 <= 2) {
            $ganz = preg_replace('~[^0-9-]~', '', substr($s, 0, $trenner)) ?? '0';
            $rest = preg_replace('~[^0-9]~', '', substr($s, $trenner + 1)) ?? '';
            $rest = str_pad(substr($rest, 0, 2), 2, '0');
            return (int) ($ganz === '' || $ganz === '-' ? '0' : $ganz) * 100
                 + (int) $rest * (str_starts_with(trim($s), '-') ? -1 : 1);
        }
        return (int) round((float) (preg_replace('~[^0-9-]~', '', $s) ?? '0') * 100);
    }

    /** @return array{anzahl:int,brutto:int,rc_netto:int,rc_iva:int} */
    public static function summe(int $jahr): array
    {
        $r = Db::one(
            "SELECT COUNT(*) AS anzahl,
                    COALESCE(SUM(brutto_cents), 0) AS brutto,
                    COALESCE(SUM(CASE WHEN reverse_charge = 1 THEN netto_cents ELSE 0 END), 0) AS rc_netto
               FROM ausgaben WHERE YEAR(datum) = ?", [$jahr]);
        $rcNetto = (int) ($r['rc_netto'] ?? 0);
        return [
            'anzahl'   => (int) ($r['anzahl'] ?? 0),
            'brutto'   => (int) ($r['brutto'] ?? 0),
            'rc_netto' => $rcNetto,
            // 22 % auf die auslaendischen Netto-Betraege — das ist der Betrag,
            // der im Reverse Charge tatsaechlich zu zahlen waere. Eine
            // Groessenordnung, keine Steuerberechnung.
            'rc_iva'   => (int) round($rcNetto * 0.22),
        ];
    }
}
