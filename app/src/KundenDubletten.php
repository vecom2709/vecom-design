<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   KundenDubletten.php — doppelte Kunden finden und zusammenführen (Phase 9b, 06.10.2026).

   Uwe: „Vorschlag + Zusammenführen per Klick“. Die Spezifikation verbietet
   automatisches Zusammenführen — hier schlägt die Maschine nur vor:
     · gleiche Telefonnummer (die letzten neun Ziffern)
     · gleiche P. IVA oder gleicher Codice fiscale
     · gleiche E-Mail
     · gleiche Firma (ohne Rechtsform und Satzzeichen)
     · gleiche Firmen-Domain der E-Mail (nicht bei Gmail & Co.)
   „Sind verschieden“ blendet ein Paar für immer aus (kunden_verschieden).
   „Ist dieselbe“ führt nach Rückfrage zusammen — nur dann, nie von selbst.

   ZUSAMMENFÜHREN
   Ein Kunde bleibt (Ziel), der andere geht in ihm auf: Jede Zeile, die auf ihn
   zeigt, zeigt danach auf das Ziel — die Liste der Spalten kommt aus der
   Datenbank selbst (information_schema), damit keine neue Tabelle vergessen
   wird. Leere Felder des Ziels füllt der andere auf. Alles in einer
   Transaktion: Stößt eine Zeile auf einen eindeutigen Schlüssel (der Partner
   ist bei beiden zugeordnet …), wird NICHTS geändert und gesagt, wo es hakt.
   RECHNUNGEN WANDERN NIE: Eine gestellte Rechnung gehört zu dem Kunden, an den
   sie ging. Hat der andere Rechnungen, muss er das Ziel sein; haben beide
   welche, bleibt es Handarbeit. Der aufgelöste Kunde steht vollständig in der
   Prüfspur.
   ========================================================================== */
final class KundenDubletten
{
    public const FREEMAIL = ['gmail.com', 'googlemail.com', 'libero.it', 'hotmail.com', 'hotmail.it', 'outlook.com', 'outlook.it', 'live.com', 'live.it', 'yahoo.com',
                             'yahoo.it', 'icloud.com', 'me.com', 'virgilio.it', 'alice.it', 'tiscali.it', 'tim.it', 'fastwebnet.it', 'aruba.it', 'pec.it', 'legalmail.it',
                             'gmx.de', 'gmx.net', 'web.de', 't-online.de', 'aol.com', 'proton.me', 'protonmail.com', 'email.it', 'inwind.it', 'example.com'];
    private const RECHTSFORM = '~\b(s\.?r\.?l\.?s?|s\.?n\.?c\.?|s\.?a\.?s\.?|s\.?p\.?a\.?|gmbh|ug|ag|kg|ohg|e\.?k\.?|ltd|di|ditta|soc\.?|societa|società|coop\.?)\b~u';
    /** Felder, die das Ziel vom anderen übernimmt, wenn es sie nicht hat. */
    private const AUFFUELLEN = ['email', 'phone', 'company', 'industry', 'street', 'zip', 'city', 'country', 'tax_code', 'vat_id', 'sdi', 'stripe_kunde'];

    public static function telefon(string $t): string
    {
        $z = preg_replace('~\D~', '', $t) ?? '';
        return strlen($z) >= 8 ? substr($z, -9) : '';
    }

    public static function firma(string $f): string
    {
        $f = mb_strtolower(trim($f));
        $f = (string) preg_replace(self::RECHTSFORM, ' ', $f);
        $f = (string) preg_replace('~[^\p{L}\p{N}]+~u', '', $f);
        return mb_strlen($f) >= 3 ? $f : '';
    }

    public static function domain(string $email): string
    {
        $d = mb_strtolower((string) substr((string) strrchr(trim($email), '@'), 1));
        return $d !== '' && !in_array($d, self::FREEMAIL, true) && !str_ends_with($d, '.invalid') ? $d : '';
    }

    /**
     * Vorschläge: Paare mit Gründen, stärkste zuerst.
     * @return list<array{a:array, b:array, gruende:list<string>}>
     */
    public static function vorschlaege(int $max = 50): array
    {
        $kunden = Db::all('SELECT id, kundennr, name, email, phone, company, city, vat_id, tax_code, created_at FROM customers
                            WHERE anonym_am IS NULL AND COALESCE(demo, 0) = 0 ORDER BY id');
        $nein = [];
        foreach (Db::all('SELECT a_id, b_id FROM kunden_verschieden') as $v) { $nein[(int) $v['a_id'] . '-' . (int) $v['b_id']] = true; }
        $gruppen = [];
        foreach ($kunden as $k) {
            $schl = ['Telefon' => self::telefon((string) $k['phone']), 'P. IVA' => strtoupper(preg_replace('~\s+~', '', (string) $k['vat_id']) ?? ''),
                     'Codice fiscale' => strtoupper(preg_replace('~\s+~', '', (string) $k['tax_code']) ?? ''), 'E-Mail' => mb_strtolower(trim((string) $k['email'])),
                     'Firma' => self::firma((string) $k['company']), 'Domain' => self::domain((string) $k['email'])];
            foreach ($schl as $art => $w) { if ($w !== '') { $gruppen[$art][$w][] = (int) $k['id']; } }
        }
        $gewicht = ['P. IVA' => 5, 'Codice fiscale' => 5, 'E-Mail' => 5, 'Telefon' => 4, 'Firma' => 3, 'Domain' => 1];
        $paare = [];
        foreach ($gruppen as $art => $werte) {
            foreach ($werte as $ids) {
                if (count($ids) < 2 || count($ids) > 6) { continue; }   // sechs Kunden mit derselben Domain sind eine Firma, kein Versehen
                for ($i = 0; $i < count($ids); $i++) { for ($j = $i + 1; $j < count($ids); $j++) {
                    $s = $ids[$i] . '-' . $ids[$j];
                    if (isset($nein[$s])) { continue; }
                    $paare[$s]['gruende'][$art] = true;
                    $paare[$s]['punkte'] = ($paare[$s]['punkte'] ?? 0) + $gewicht[$art];
                } }
            }
        }
        uasort($paare, static fn($x, $y) => $y['punkte'] <=> $x['punkte']);
        $nachId = array_column($kunden, null, 'id');
        $aus = [];
        foreach (array_slice($paare, 0, $max, true) as $s => $p) {
            [$a, $b] = array_map('intval', explode('-', $s));
            $aus[] = ['a' => $nachId[$a], 'b' => $nachId[$b], 'gruende' => array_keys($p['gruende'])];
        }
        return $aus;
    }

    public static function anzahl(): int
    {
        return count(self::vorschlaege(50));
    }

    /** „Sind verschieden“ — für immer. */
    public static function verschieden(int $a, int $b, ?int $userId = null): void
    {
        [$a, $b] = [min($a, $b), max($a, $b)];
        Db::run('INSERT IGNORE INTO kunden_verschieden (a_id, b_id, user_id) VALUES (?, ?, ?)', [$a, $b, $userId]);
        try { require_once __DIR__ . '/Events.php'; Events::pruefspur('kunde_verschieden', 'customers', $a, [], ['und' => $b]); } catch (Throwable $e) { }
    }

    /** Alle Spalten, die auf einen Kunden zeigen — aus der Datenbank, nicht aus einer Liste, die veraltet. */
    public static function verweise(): array
    {
        $aus = [];
        foreach (Db::all("SELECT table_name AS t, column_name AS c FROM information_schema.columns
                           WHERE table_schema = DATABASE() AND column_name IN ('customer_id', 'kunde_id', 'geworbener_id', 'empfehler_id') AND table_name <> 'customers'
                           ORDER BY table_name, column_name") as $r) {
            $aus[] = [(string) $r['t'], (string) $r['c']];
        }
        return $aus;
    }

    /**
     * $wegId geht in $zielId auf. Nur nach Klick und Rückfrage (index.php, Ablauf::TRAGWEITE).
     * @return array{ok:bool, text:string, verschoben?:array<string,int>}
     */
    public static function zusammenfuehren(int $zielId, int $wegId, ?int $userId = null): array
    {
        if ($zielId === $wegId || $zielId <= 0 || $wegId <= 0) { return ['ok' => false, 'text' => 'Zwei verschiedene Kunden wählen.']; }
        $ziel = Db::one('SELECT * FROM customers WHERE id = ?', [$zielId]);
        $weg = Db::one('SELECT * FROM customers WHERE id = ?', [$wegId]);
        if (!$ziel || !$weg) { return ['ok' => false, 'text' => 'Kunde nicht gefunden.']; }
        if ($ziel['anonym_am'] !== null || $weg['anonym_am'] !== null) { return ['ok' => false, 'text' => 'Ein anonymisierter Kunde wird nicht zusammengeführt.']; }
        $rZiel = (int) Db::wert('SELECT COUNT(*) FROM invoices WHERE customer_id = ?', [$zielId], 0);
        $rWeg = (int) Db::wert('SELECT COUNT(*) FROM invoices WHERE customer_id = ?', [$wegId], 0);
        if ($rWeg > 0 && $rZiel > 0) { return ['ok' => false, 'text' => 'Beide haben Rechnungen — die bleiben, wem sie gestellt wurden. Das bleibt Handarbeit.']; }
        if ($rWeg > 0) { return ['ok' => false, 'text' => '„' . $weg['name'] . '“ hat Rechnungen und muss deshalb bleiben — führe andersherum zusammen.', 'tauschen' => true]; }
        /* Ein Kunde gehört höchstens einem Partner (Partner::zuordnen hängt nie um). Sind beide zugeordnet, entscheidet ein Mensch, wem. */
        if ((int) Db::wert('SELECT COUNT(DISTINCT customer_id) FROM partner_zuordnungen WHERE customer_id IN (?, ?)', [$zielId, $wegId], 0) === 2) {
            return ['ok' => false, 'text' => 'Beide sind einem Partner zugeordnet — wem der Kunde gehört, bitte zuerst von Hand klären. Nichts geändert.'];
        }
        $verweise = self::verweise();
        $verschoben = [];
        try {
            Db::transaktion(static function () use ($verweise, $zielId, $wegId, $ziel, $weg, &$verschoben): void {
                foreach ($verweise as [$t, $c]) {
                    $n = Db::run("UPDATE `$t` SET `$c` = ? WHERE `$c` = ?", [$zielId, $wegId])->rowCount();
                    if ($n > 0) { $verschoben[$t . '.' . $c] = $n; }
                }
                $auf = [];
                foreach (self::AUFFUELLEN as $f) {
                    if (trim((string) ($ziel[$f] ?? '')) === '' && trim((string) ($weg[$f] ?? '')) !== '') { $auf[$f] = $weg[$f]; }
                }
                $notiz = trim((string) ($ziel['notes'] ?? ''));
                $notiz .= ($notiz !== '' ? "\n\n" : '') . 'Zusammengeführt am ' . date('d.m.Y') . ' mit ' . $weg['name'] . ' (' . ($weg['kundennr'] ?? ('#' . $weg['id'])) . ')'
                        . (trim((string) ($weg['notes'] ?? '')) !== '' ? ":\n" . trim((string) $weg['notes']) : '.');
                $auf['notes'] = mb_substr($notiz, 0, 60000);
                /* Erst den anderen löschen, dann auffüllen: Die E-Mail ist eindeutig — solange er noch steht, gehörte sie zweimal. */
                Db::run('DELETE FROM customers WHERE id = ?', [$wegId]);
                Db::update('customers', $zielId, $auf);
            });
        } catch (Throwable $e) {
            if (Db::doppelt($e)) {
                preg_match("~for key '([^']+)'~", $e->getMessage(), $m);
                return ['ok' => false, 'text' => 'Nichts geändert: Beide hängen an etwas, das es nur einmal geben darf (' . ($m[1] ?? 'eindeutiger Schlüssel') . '). Das bitte zuerst von Hand klären.'];
            }
            throw $e;
        }
        $vorher = $weg; unset($vorher['token']);
        try { require_once __DIR__ . '/Events.php'; Events::pruefspur('kunde_zusammengefuehrt', 'customers', $zielId, $vorher, ['ziel' => $zielId, 'verschoben' => $verschoben]); } catch (Throwable $e) { }
        return ['ok' => true, 'text' => '„' . $weg['name'] . '“ ist jetzt Teil von „' . $ziel['name'] . '“ (' . array_sum($verschoben) . ' Verweise umgehängt).', 'verschoben' => $verschoben];
    }
}
