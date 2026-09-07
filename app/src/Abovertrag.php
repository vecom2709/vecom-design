<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Pdf.php';
require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Widerruf.php';
require_once __DIR__ . '/Kunde.php';

/**
 * Das Vertragsblatt fuer MONATSVERTRAEGE — Betreuung und Hosting.
 *
 * WARUM ES DAS BRAUCHT
 *
 * Die Bestellung einer Website hat ihr Vertragsblatt (Vertragsblatt.php).
 * Die Monatsvertraege hatten keins: Wer Betreuung oder Domain & Hosting
 * abschloss, sah die Bedingungen nur auf der Kundenseite — Mindestlaufzeit,
 * Kuendigung, Widerruf standen nirgends zum Aufheben. Beim Solo-Hosting
 * kommt der Vertrag sogar komplett online zustande (der Ja-Knopf auf der
 * Kundenseite IST der Abschluss) — ein Fernabsatzvertrag, und der verlangt
 * eine Bestaetigung auf dauerhaftem Datentraeger mit allen Pflichtangaben:
 * wer die Leistung erbringt, was sie kostet, wie lange sie laeuft, wie man
 * kuendigt, und das Widerrufsrecht.
 *
 * Also: dasselbe Blatt-Prinzip wie bei der Bestellung. Es haengt an der
 * Bestaetigungs-Mail, die mit dem Vertragsschluss rausgeht, und liegt
 * danach jederzeit abrufbar auf der Kundenseite. Eine Seite, mehr nicht.
 */
final class Abovertrag
{
    /** @var array<string,array<string,string>> */
    private const WORTE = [
        'it' => [
            'titel'    => 'Contratto mensile',
            'an'       => 'CLIENTE',
            'eck'      => 'CONTRATTO',
            'datum'    => 'Data',
            'kundennr' => 'N. cliente',
            'was'      => 'OGGETTO DEL CONTRATTO',
            'drin'     => 'PRESTAZIONI COMPRESE',
            'preis'    => 'Canone mensile',
            'beginn'   => 'Inizio',
            'mindest'  => 'Durata minima fino al',
            'kuend'    => 'Poi disdetta libera a fine mese — dalla tua pagina personale o per e-mail, senza motivazione.',
            'zahlart'  => 'Pagamento',
            'zust'     => 'CONCLUSO',
            'zustText' => 'Confermato il {datum} con un clic sulla pagina personale del cliente («ordine con obbligo di pagare»).',
            'recht'    => 'DIRITTO DI RECESSO',
            'agb'      => 'Condizioni e informativa privacy',
        ],
        'de' => [
            'titel'    => 'Monatsvertrag',
            'an'       => 'KUNDE',
            'eck'      => 'VERTRAG',
            'datum'    => 'Datum',
            'kundennr' => 'Kundennummer',
            'was'      => 'GEGENSTAND DES VERTRAGS',
            'drin'     => 'ENTHALTENE LEISTUNGEN',
            'preis'    => 'Monatsbetrag',
            'beginn'   => 'Beginn',
            'mindest'  => 'Mindestlaufzeit bis',
            'kuend'    => 'Danach jederzeit zum Monatsende kündbar — auf deiner Kundenseite oder formlos per E-Mail, ohne Begründung.',
            'zahlart'  => 'Zahlweise',
            'zust'     => 'ZUSTANDE GEKOMMEN',
            'zustText' => 'Bestätigt am {datum} per Klick auf der Kundenseite („zahlungspflichtig bestellen").',
            'recht'    => 'WIDERRUFSRECHT',
            'agb'      => 'AGB und Datenschutzerklärung',
        ],
        'en' => [
            'titel'    => 'Monthly contract',
            'an'       => 'CUSTOMER',
            'eck'      => 'CONTRACT',
            'datum'    => 'Date',
            'kundennr' => 'Customer no.',
            'was'      => 'SUBJECT OF THE CONTRACT',
            'drin'     => 'SERVICES INCLUDED',
            'preis'    => 'Monthly fee',
            'beginn'   => 'Start',
            'mindest'  => 'Minimum term until',
            'kuend'    => 'After that, cancel any time at month’s end — from your personal page or informally by email, no reasons needed.',
            'zahlart'  => 'Payment',
            'zust'     => 'CONCLUDED',
            'zustText' => 'Confirmed on {datum} with a click on the customer page (“order with obligation to pay”).',
            'recht'    => 'RIGHT OF WITHDRAWAL',
            'agb'      => 'Terms and privacy notice',
        ],
    ];

    /** In welcher Sprache das Blatt geschrieben wird. */
    public static function sprache(?array $k): string
    {
        $s = strtolower((string) ($k['sprache'] ?? 'it'));
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }

    public static function dateiname(array $abo, string $sprache): string
    {
        $vorn = match ($sprache) {
            'de' => 'Monatsvertrag',
            'en' => 'Monthly-contract',
            default => 'Contratto-mensile',
        };
        return $vorn . '-A' . (int) $abo['id'] . '.pdf';
    }

    /** Paketname und Leistungen in der Sprache des Kunden — aus packages.texte. */
    private static function paketworte(array $abo, string $sprache): array
    {
        $name = (string) $abo['paket_name'];
        $punkte = [];
        try {
            $p = Db::one('SELECT name, features, texte FROM packages WHERE id = ?',
                [(int) $abo['package_id']]);
            if ($p) {
                $t = $p['texte'] ? (json_decode((string) $p['texte'], true) ?: []) : [];
                $s = $t[$sprache] ?? [];
                if (trim((string) ($s['name'] ?? '')) !== '') { $name = (string) $s['name']; }
                $roh = (isset($s['features']) && is_array($s['features']) && $s['features'])
                    ? $s['features']
                    : (json_decode((string) ($p['features'] ?? '[]'), true) ?: []);
                foreach ($roh as $zeile) {
                    $zeile = trim((string) $zeile);
                    // Zwischenueberschriften ("Incluso:") sind Karten-Schmuck,
                    // auf dem Blatt zaehlt nur, was wirklich Leistung ist.
                    if ($zeile === '' || str_ends_with($zeile, ':')) { continue; }
                    $punkte[] = $zeile;
                    if (count($punkte) >= 8) { break; }
                }
            }
        } catch (Throwable $e) { /* dann eben nur der Name */ }
        return [$name, $punkte];
    }

    /**
     * Das Blatt. Leerer String, wenn der Vertrag nicht auffindbar ist —
     * ein fehlendes PDF darf nie eine Mail oder eine Seite aufhalten.
     */
    public static function pdf(int $aboId): string
    {
        $abo = Db::one('SELECT * FROM abos WHERE id = ?', [$aboId]);
        if (!$abo) { return ''; }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $abo['customer_id']]);

        $sprache  = self::sprache($k);
        $w        = self::WORTE[$sprache];
        $waehrung = (string) ($abo['currency'] ?? 'EUR');
        [$paketName, $punkte] = self::paketworte($abo, $sprache);

        $blau  = [0.024, 0.282, 0.910];
        $cyan  = [0.122, 0.910, 1.0];
        $tinte = [0.051, 0.106, 0.165];
        $grau  = [0.42, 0.46, 0.53];
        $leise = [0.60, 0.64, 0.70];
        $linie = [0.87, 0.89, 0.92];

        $p = new Pdf();
        $rand   = 56.0;
        $rechts = Pdf::A4_BREIT - $rand;
        $breit  = $rechts - $rand;

        /* ---------- Briefkopf, wie auf Beleg und Auftragsbestaetigung ---------- */
        require_once __DIR__ . '/Rechnung.php';
        $logo = Rechnung::logo();
        if ($logo === null || !$p->bild($logo, $rand, 44, 98, 67)) {
            $bv = $p->text($rand, 62, 'VECOM', 17, true, 'links', $blau);
            $p->text($rand + $bv + 5, 62, 'DESIGN', 17, true, 'links', $tinte);
        }
        $y = 46;
        foreach (Firma::anschrift() as $i => $zeile) {
            $p->text($rechts, $y, $zeile, 8.5, $i === 0, 'rechts', $i === 0 ? $tinte : $grau);
            $y += 11.5;
        }
        $p->flaeche($rand, 124, $breit * 0.38, 1.6, $blau);
        $p->flaeche($rand + $breit * 0.38, 124, $breit * 0.12, 1.6, $cyan);

        /* ---------- Titel und Eckdaten ---------- */
        $p->text($rand, 164, $w['titel'], 20, true, 'links', $tinte);
        $p->text($rechts, 152, $w['eck'], 7.5, true, 'rechts', $leise);
        $p->text($rechts, 166, 'A-' . (int) $abo['id'], 11, true, 'rechts', $tinte);
        $p->text($rechts, 180, $w['datum'] . ' ' . Fmt::datum((string) $abo['created_at']), 9, false, 'rechts', $grau);
        $knr = Kunde::nummer((int) $abo['customer_id']);
        if ($knr !== '') {
            $p->text($rechts, 193, $w['kundennr'] . ' ' . $knr, 9, false, 'rechts', $grau);
        }

        /* ---------- Kunde ---------- */
        $empf = array_values(array_filter([
            (string) ($k['company'] ?? ''),
            (string) ($k['name'] ?? ''),
            (string) ($k['street'] ?? ''),
            trim((string) ($k['zip'] ?? '') . ' ' . (string) ($k['city'] ?? '')),
            (string) ($k['country'] ?? ''),
        ], static fn($z) => trim($z) !== ''));

        $y = 214;
        $p->text($rand, $y, $w['an'], 7.5, true, 'links', $leise);
        $y += 16;
        foreach ($empf as $i => $zeile) {
            $p->text($rand, $y, $zeile, $i === 0 ? 11 : 10, $i === 0, 'links', $i === 0 ? $tinte : $grau);
            $y += 13.5;
        }

        /* ---------- Was vereinbart ist ---------- */
        $y += 14;
        $p->text($rand, $y, $w['was'], 7.5, true, 'links', $leise);
        $y += 17;
        $p->text($rand, $y, $paketName, 12, true, 'links', $tinte);
        $p->text($rechts, $y, Fmt::geld((int) $abo['betrag_cents'], $waehrung)
            . ' ' . ['it' => 'al mese', 'de' => 'im Monat', 'en' => 'per month'][$sprache],
            11, true, 'rechts', $tinte);

        if ($punkte) {
            $y += 8;
            foreach ($punkte as $zeile) {
                $y += 13.5;
                foreach ($p->umbrechen('·  ' . $zeile, $breit, 9) as $j => $u) {
                    if ($j > 0) { $y += 11.5; }
                    $p->text($rand + 8, $y, $u, 9, false, 'links', $grau);
                }
            }
        }

        /* ---------- Laufzeit und Kuendigung ---------- */
        $y += 16;
        $p->linie($rand, $y, $rechts, $y, 0.6, $linie);
        $y += 17;
        $zeilen = [
            [$w['beginn'],  Fmt::datum((string) $abo['beginn'])],
            [$w['mindest'], Fmt::datum((string) $abo['mindestlaufzeit_bis'])],
        ];
        require_once __DIR__ . '/Abo.php';
        $zeilen[] = [$w['zahlart'], Abo::ZAHLARTEN[(string) $abo['zahlart']] ?? (string) $abo['zahlart']];
        foreach ($zeilen as [$links, $wert]) {
            $p->text($rand, $y, $links, 9.5, false, 'links', $grau);
            $p->text($rand + $breit * 0.42, $y, $wert, 9.5, true, 'links', $tinte);
            $y += 14.5;
        }
        $y += 2;
        foreach ($p->umbrechen($w['kuend'], $breit, 8.5) as $zeile) {
            $p->text($rand, $y, $zeile, 8.5, false, 'links', $grau);
            $y += 11.5;
        }

        /* ---------- Wie der Vertrag zustande kam (Solo-Hosting: der Klick) ---------- */
        try {
            $auftrag = Db::one(
                "SELECT zugestimmt_am FROM hosting_auftraege
                  WHERE customer_id = ? AND project_id IS NULL AND zugestimmt_am IS NOT NULL
                  ORDER BY id DESC LIMIT 1", [(int) $abo['customer_id']]);
            if ((string) ($abo['paket_slug'] ?? '') === 'hosting' && $auftrag) {
                $y += 10;
                $p->text($rand, $y, $w['zust'], 7.5, true, 'links', $leise);
                $y += 14;
                $satz = str_replace('{datum}', Fmt::datum((string) $auftrag['zugestimmt_am']), $w['zustText']);
                foreach ($p->umbrechen($satz, $breit, 8.5) as $zeile) {
                    $p->text($rand, $y, $zeile, 8.5, false, 'links', $grau);
                    $y += 11.5;
                }
            }
        } catch (Throwable $e) { /* der Abschnitt ist Zugabe */ }

        /* ---------- Widerrufsrecht ---------- */
        $y += 12;
        $p->text($rand, $y, $w['recht'], 7.5, true, 'links', $leise);
        $y += 15;
        foreach ($p->umbrechen(Widerruf::t('widText', $sprache), $breit, 8.5) as $zeile) {
            $p->text($rand, $y, $zeile, 8.5, false, 'links', $grau);
            $y += 11.5;
        }

        /* ---------- AGB und Datenschutz ---------- */
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $y += 8;
        $p->text($rand, $y, $w['agb'] . ':  ' . $basis . '/legal.html', 8.5, false, 'links', $grau);

        /* ---------- Fuss ---------- */
        $fuss = Pdf::A4_HOCH - 82;
        $p->flaeche($rand, $fuss, $breit * 0.10, 1.2, $blau);
        $p->linie($rand + $breit * 0.10, $fuss + 0.6, $rechts, $fuss + 0.6, 0.5, $linie);
        $fy = $fuss + 18;
        foreach (Firma::fusszeilen() as $zeile) {
            $p->text($rand, $fy, $zeile, 8, false, 'links', $leise);
            $fy += 11;
        }

        return $p->fertig();
    }

    /**
     * Die Bestaetigungs-Mail zum Vertragsschluss — mit dem Blatt im Anhang.
     *
     * Geht genau EINMAL je Vertrag raus (Wiederholungsschutz ueber die
     * Mail-Tabelle: ein Anlass je Kunde reicht nicht, denn Betreuung und
     * Hosting sind zwei Vertraege — deshalb steht die Vertragsnummer im
     * Betreff und der Schutz laeuft ueber das Protokoll).
     */
    public static function bestaetigen(int $aboId): bool
    {
        $abo = Db::one('SELECT * FROM abos WHERE id = ?', [$aboId]);
        if (!$abo) { return false; }

        // Schon bestaetigt? Das Protokoll weiss es.
        $schon = (int) Db::wert(
            "SELECT COUNT(*) FROM activities
              WHERE type = 'abo_vertragsblatt' AND JSON_EXTRACT(meta, '$.abo_id') = ?",
            [$aboId], 0);
        if ($schon > 0) { return false; }

        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $abo['customer_id']]);
        if (!$k || trim((string) $k['email']) === '') { return false; }

        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';
        require_once __DIR__ . '/Kundenzugang.php';

        $sprache = self::sprache($k);
        [$paketName] = self::paketworte($abo, $sprache);

        $seite = '';
        try { $seite = Kundenzugang::linkFuer((int) $abo['customer_id']); } catch (Throwable $e) { }
        if ($seite === '') { $seite = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/'); }

        [$betreff, $text] = Texte::mail('vertrag_monat', $sprache, [
            'name'    => (string) $k['name'],
            'paket'   => $paketName,
            'betrag'  => Fmt::geld((int) $abo['betrag_cents'], (string) $abo['currency']),
            'beginn'  => Fmt::datum((string) $abo['beginn']),
            'mindest' => Fmt::datum((string) $abo['mindestlaufzeit_bis']),
            'link'    => $seite,
        ]);

        $anhaenge = [];
        try {
            $daten = self::pdf($aboId);
            if ($daten !== '') {
                $anhaenge[] = ['name' => self::dateiname($abo, $sprache), 'daten' => $daten];
            }
        } catch (Throwable $e) { /* die Mail traegt auch ohne Anhang — der Link steht drin */ }

        $ok = Mail::senden('vertrag_monat', (string) $k['email'], $betreff, $text, [
            'customer_id' => (int) $abo['customer_id'],
            'antwortAn'   => Mail::eigeneAdresse(),
            'anhaenge'    => $anhaenge,
        ]);
        if ($ok) {
            require_once __DIR__ . '/Events.php';
            Events::protokoll('abo_vertragsblatt', 'Vertragsblatt verschickt: ' . $paketName
                . ' (A-' . $aboId . ')', (int) $abo['customer_id'], null,
                $abo['project_id'] !== null ? (int) $abo['project_id'] : null,
                ['abo_id' => $aboId]);
        }
        return $ok;
    }
}
