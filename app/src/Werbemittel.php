<?php
declare(strict_types=1);

/* ==========================================================================
   Werbemittel.php — Marketing Center, Phase 1: Katalog und Preis.

   EIN PREIS, AN EINER STELLE GERECHNET

   Gespeichert wird nur der Einkauf (wm_varianten.einkauf_cent). Der
   Verkaufspreis entsteht hier aus Einkauf und Marge, jedes Mal. Wer die
   Standardmarge ändert, ändert damit sofort jeden Preis, der keine eigene
   Regel hat — ohne dass irgendwo ein alter Preis stehen bleibt.

   Die Regel: Einkauf plus Marge in Prozent, aber nie weniger als Einkauf
   plus Mindestmarge. Aufgerundet auf volle 10 Cent, damit keine Preise wie
   12,37 € im Katalog stehen. Gerundet wird nur nach oben — die Marge darf
   durch die Rundung nie unter die Regel fallen.

   WAS PARTNER SEHEN DÜRFEN

   katalog() ist das Einzige, was der Partnerbereich bekommt. Darin stehen
   Name, Format und Endpreis — kein Einkauf, keine Marge, kein Anbieter.
   Die Felder werden einzeln übernommen, nicht die Zeile durchgereicht: Eine
   Spalte, die später in wm_varianten dazukommt, darf nicht still im
   Partnerbereich auftauchen. verwaltung() ist nur für den Admin.

   Bestellbar ist eine Variante erst mit Einkaufspreis; ein Produkt ohne
   bestellbare Variante erscheint gar nicht. Sonst stünde „0,00 €“ im Katalog.
   ========================================================================== */
final class Werbemittel
{
    public const SPRACHEN = ['it', 'de', 'en'];
    /** Vorlagen, die heute eine Druckdatei erzeugen können. */
    public const VORLAGEN = ['visitenkarte' => 'Visitenkarte (PartnerKarten)', 'flyer_a6' => 'Flyer A6 (WmDruck)', 'flyer_a5' => 'Flyer A5 (WmDruck)', 'flyer_branche' => 'Branchen-Flyer A5 DE/IT/EN (WmDruck)', 'aufkleber_50' => 'Aufkleber rund Ø 5 cm (WmDruck)', 'rollup_85' => 'Roll-up 85 × 200 cm (WmDruck)', 'kalender_a3' => 'Wandkalender A3 2027 (WmKalender, Gelato)', 'tasse_11' => 'Tasse 11 oz (WmDruck, Printful)', 'notizbuch' => 'Notizbuch A5 (WmDruck, Printful)', 'flasche' => 'Edelstahlflasche 500 ml (WmDruck, Printful)', 'untersetzer' => 'Kork-Untersetzer 95 mm (WmDruck, Printful)', 'beutel' => 'Stoffbeutel schwarz (WmDruck, Printful)'];

    /** Hat die Vorlage eine Gestaltung mit Stil/Sprache/Kontakt, Vorschau und Freigabe? */
    public static function gestaltbar(string $vorlage): bool
    {
        return isset(self::VORLAGEN[$vorlage]) && $vorlage !== '';
    }

    /**
     * Kachelbild eines Stils oder Branchenmotivs für die Auswahl (04.10.2026, Uwe: „direkt
     * auswählen, ohne viel zu suchen“): nur die Vorderseite, ohne Partnerdaten, 260 px breit.
     * Einmal gerechnet, dann aus app/zwischenspeicher (die Vorlagen sind bis 18 MB groß).
     */
    public static function miniBild(string $vorlage, string $stil, string $sprache): string
    {
        require_once __DIR__ . '/PartnerKarten.php';
        require_once __DIR__ . '/WmDruck.php';
        if (!self::gestaltbar($vorlage) || !self::stilDa($vorlage, $stil)) { return ''; }
        $sprache = in_array($sprache, self::SPRACHEN, true) ? $sprache : 'it';
        if ($vorlage === 'kalender_a3') { require_once __DIR__ . '/WmKalender.php'; }
        $quelle = match ($vorlage) {
            'visitenkarte' => PartnerKarten::vornDatei($stil),
            'kalender_a3' => WmKalender::datei($sprache, 0, true),
            default => WmDruck::vornDatei($vorlage, $stil, $sprache),
        };
        if ($quelle === '' || !is_file($quelle)) { return ''; }
        $ordner = dirname(__DIR__) . '/zwischenspeicher/mini';
        // v3: mit der ersten freigegebenen Überschrift (die Vorlagen haben seit Schritt 4 keine mehr im Bild).
        $ziel = $ordner . '/' . substr(hash('sha256', $quelle . '|' . filemtime($quelle) . '|v3|' . json_encode(Texte::WM_TITEL[WmDruck::titel('')] ?? [])), 0, 24) . '.jpg';
        if (is_file($ziel)) { return (string) file_get_contents($ziel); }
        $im = @imagecreatefromjpeg($quelle);
        if (!$im) { return ''; }
        if ($vorlage !== 'visitenkarte') { WmDruck::titelAuf($im, $vorlage, $stil, $sprache, ''); }
        // Beschnitt ab: Anteil aus dem Layout der Datei, die tatsächlich geladen wurde.
        $l = match ($vorlage) {
            'visitenkarte' => ['b' => 85, 'beschnitt' => 3],
            'kalender_a3' => WmKalender::layout(),
            default => WmDruck::layout(WmDruck::branche($vorlage, $stil) ? 'flyer_a5' : $vorlage),
        };
        $rand = (int) round(imagesx($im) * $l['beschnitt'] / ($l['b'] + 2 * $l['beschnitt']));
        $zu = imagecrop($im, ['x' => $rand, 'y' => $rand, 'width' => imagesx($im) - 2 * $rand, 'height' => imagesy($im) - 2 * $rand]) ?: $im;
        $klein = imagescale($zu, 420, -1, IMG_BICUBIC) ?: $zu;     // Kachel ~140–200 px breit: doppelt für scharfe Bildschirme
        ob_start(); imagejpeg($klein, null, 84); $jpg = (string) ob_get_clean();
        if (!is_dir($ordner)) { @mkdir($ordner, 0775, true); }
        @file_put_contents($ziel, $jpg, LOCK_EX);
        return $jpg;
    }

    /** Vorschau der Wahl (JPEG) — für jede Vorlage dieselbe Frage. */
    public static function vorschauBild(array $p, string $vorlage, string $stil, string $sprache, string $kontakt, string $titel = ''): string
    {
        $p['_wm_titel'] = $titel;
        require_once __DIR__ . '/PartnerKarten.php';
        /* Doppelte Auflösung (04.10.2026, Uwe: „alles qualitativ hochwertig, auch von der Auflösung“):
           die Vorschau steht bis ~540 px breit da — auf hochauflösenden Handys braucht sie das Doppelte. */
        if ($vorlage === 'visitenkarte') { return PartnerKarten::vorschau($p, $stil, $sprache, $kontakt, 1440); }
        if ($vorlage === 'kalender_a3') { require_once __DIR__ . '/WmKalender.php'; return WmKalender::vorschau($p, $stil, $sprache, $kontakt, 760); }
        require_once __DIR__ . '/WmDruck.php';
        return WmDruck::vorschau($p, $vorlage, $stil, $sprache, $kontakt, 760);
    }

    /** Gibt es den Stil für diese Vorlage? */
    public static function stilDa(string $vorlage, string $stil): bool
    {
        require_once __DIR__ . '/PartnerKarten.php';
        if ($vorlage === 'visitenkarte') { return PartnerKarten::gibt($stil); }
        if ($vorlage === 'kalender_a3') { require_once __DIR__ . '/WmKalender.php'; return WmKalender::gibt($stil); }
        require_once __DIR__ . '/WmDruck.php';
        return WmDruck::gibt($vorlage, $stil);
    }

    // ---- Margenregel --------------------------------------------------------

    /** @return array{marge_prozent:int, mindestmarge_cent:int} */
    public static function standard(): array
    {
        return [
            'marge_prozent'     => max(0, min(500, (int) self::einstellung('wm_marge_prozent', '35'))),
            'mindestmarge_cent' => max(0, (int) self::einstellung('wm_mindestmarge_cent', '500')),
        ];
    }

    public static function standardSetzen(int $prozent, int $mindestCent): void
    {
        if ($prozent < 0 || $prozent > 500) { throw new InvalidArgumentException('Marge muss zwischen 0 und 500 % liegen.'); }
        if ($mindestCent < 0 || $mindestCent > 1000000) { throw new InvalidArgumentException('Mindestmarge ungültig.'); }
        foreach (['wm_marge_prozent' => $prozent, 'wm_mindestmarge_cent' => $mindestCent] as $k => $v) {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, (string) $v]);
        }
    }

    /** Regel eines Produkts: eigene Werte, sonst Standard. */
    public static function regel(array $produkt): array
    {
        $s = self::standard();
        return [
            'marge_prozent'     => $produkt['marge_prozent'] !== null ? (int) $produkt['marge_prozent'] : $s['marge_prozent'],
            'mindestmarge_cent' => $produkt['mindestmarge_cent'] !== null ? (int) $produkt['mindestmarge_cent'] : $s['mindestmarge_cent'],
        ];
    }

    /**
     * ZAHLUNGSKOSTEN (04.10.2026, Uwe: „nicht dass man draufzahlt“). Stripe
     * behält von jeder Zahlung einen Anteil plus festen Betrag ein. Damit die
     * Marge danach noch ganz bleibt, wird die Gebühr VOR der Marge in den
     * Preis gerechnet — sicherheitshalber mit 3 % + 0,25 € (EWR-Standardkarten
     * kosten 1,5 % + 0,25 €, Premium- und Nicht-EWR-Karten mehr).
     * Gespeichert als Zehntelprozent und Cent in settings.
     * @return array{zehntel:int, fix_cent:int}
     */
    public static function zahlkosten(): array
    {
        return [
            'zehntel'  => max(0, min(200, (int) self::einstellung('wm_zahlkosten_zehntel', '30'))),
            'fix_cent' => max(0, min(1000, (int) self::einstellung('wm_zahlkosten_fix_cent', '25'))),
        ];
    }

    public static function zahlkostenSetzen(int $zehntel, int $fixCent): void
    {
        if ($zehntel < 0 || $zehntel > 200 || $fixCent < 0 || $fixCent > 1000) { throw new InvalidArgumentException('Zahlungskosten ungültig.'); }
        foreach (['wm_zahlkosten_zehntel' => $zehntel, 'wm_zahlkosten_fix_cent' => $fixCent] as $k => $v) {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, (string) $v]);
        }
    }

    /** Was von einem Partnerpreis nach Zahlungskosten bleibt (Gebühr aufgerundet). */
    public static function nachZahlkosten(int $preisCent, ?array $zk = null): int
    {
        $zk ??= self::zahlkosten();
        return $preisCent - intdiv($preisCent * $zk['zehntel'] + 999, 1000) - $zk['fix_cent'];
    }

    /** Gewinn je Bestellung: Preis − Zahlungskosten − Einkauf (inkl. Versand und MwSt). */
    public static function gewinn(int $preisCent, int $einkaufCent, ?array $zk = null): int
    {
        return $preisCent > 0 ? self::nachZahlkosten($preisCent, $zk) - $einkaufCent : 0;
    }

    /** Darunter ist eine Auflage nicht bestellbar (Uwe: „ich sage ja“ zur Gewinnsperre, 04.10.2026). */
    public const MIN_GEWINN_CENT = 100;

    /**
     * Verkaufspreis in Cent. 0 heißt: nicht bestellbar (kein Einkauf).
     * Einkauf + Marge (nie unter Einkauf + Mindestmarge), dann so viel
     * darauf, dass nach den Zahlungskosten genau das übrig bleibt, auf
     * 10 Cent aufgerundet.
     */
    public static function preis(int $einkaufCent, int $margeProzent, int $mindestCent, ?array $zk = null): int
    {
        if ($einkaufCent <= 0) { return 0; }
        $zk ??= self::zahlkosten();
        $nachProzent = intdiv($einkaufCent * (100 + $margeProzent) + 99, 100);   // aufrunden
        $roh = max($nachProzent, $einkaufCent + $mindestCent);
        $p = intdiv(($roh + $zk['fix_cent']) * 1000 + (1000 - $zk['zehntel']) - 1, 1000 - $zk['zehntel']);
        $p = intdiv($p + 9, 10) * 10;
        while (self::nachZahlkosten($p, $zk) < $roh) { $p += 10; }   // Rundung der Gebühr nachziehen
        return $p;
    }

    // ---- Partnerbereich ------------------------------------------------------

    /** So lange gilt ein geprüfter Druckereipreis; danach ist die Auflage gesperrt, bis er neu geprüft ist. */
    public const FRISCH_TAGE = 30;

    /** Länder, in die geliefert wird (Uwe, 04.10.2026: Italien und Deutschland). */
    public const LIEFERLAENDER = ['IT' => 'Italia', 'DE' => 'Deutschland'];
    /** Mehrwertsteuer je Lieferland in Prozent — für Netto-Preise von Druckereien (Gelato-Quote). */
    public const MWST = ['IT' => 22, 'DE' => 19];

    /**
     * Was Vecom für diese Auflage bei Lieferung nach $land zahlt, und bei wem.
     * Gibt es Angebote der Druckereien, ist es das günstigste für dieses Land —
     * gibt es für das Land keins, ist dorthin nicht lieferbar (null). Ohne
     * jedes Angebot gilt der von Hand eingetragene Einkauf für alle Länder.
     * @return ?array{cent:int, anbieter:?string}
     */
    public static function einkauf(int $varianteId, string $land): ?array
    {
        $land = strtoupper($land);
        if (!isset(self::LIEFERLAENDER[$land])) { return null; }
        /* Automatikbetrieb (04.10.2026): Wenn eine Druckerei mit Anbindung ein
           Angebot fürs Land hat, gewinnt die günstigste MIT Anbindung — sonst
           müsste jemand von Hand bestellen. Ohne angebundene Druckerei bleibt
           es bei der günstigsten überhaupt (und Uwe bekommt nach der Zahlung
           eine Meldung). */
        $g = null;
        $auto = self::automatischeAnbieter($land);
        if ($auto) {
            $ph = implode(',', array_fill(0, count($auto), '?'));
            $g = Db::one("SELECT anbieter, preis_cent FROM wm_anbieter_preise WHERE variante_id = ? AND land = ? AND anbieter IN ($ph)
                           AND geprueft_am >= CURDATE() - INTERVAL " . self::FRISCH_TAGE . " DAY ORDER BY preis_cent, id LIMIT 1",
                array_merge([$varianteId, $land], $auto)) ?: null;
        }
        /* Nur geprüfte Preise der letzten 30 Tage (Uwe, 04.10.2026: „ja“ zur
           Sperre): ein alter Preis kann inzwischen zu niedrig sein — dann
           lieber nicht bestellbar als draufzahlen. */
        $g ??= Db::one('SELECT anbieter, preis_cent FROM wm_anbieter_preise WHERE variante_id = ? AND land = ?
                         AND geprueft_am >= CURDATE() - INTERVAL ' . self::FRISCH_TAGE . ' DAY ORDER BY preis_cent, id LIMIT 1', [$varianteId, $land]);
        if ($g) { return ['cent' => (int) $g['preis_cent'], 'anbieter' => (string) $g['anbieter']]; }
        if ((int) Db::wert('SELECT COUNT(*) FROM wm_anbieter_preise WHERE variante_id = ?', [$varianteId]) > 0) { return null; }
        $h = (int) Db::wert('SELECT einkauf_cent FROM wm_varianten WHERE id = ?', [$varianteId], 0);
        return $h > 0 ? ['cent' => $h, 'anbieter' => null] : null;
    }

    /** Druckereien, die im Automatikbetrieb Aufträge für $land per Schnittstelle bekommen können (Register: Druckerei::ANGEBUNDEN). */
    public static function automatischeAnbieter(string $land = 'IT'): array
    {
        require_once __DIR__ . '/WmBestellung.php';
        require_once __DIR__ . '/Druckerei.php';
        return WmBestellung::automatik() ? Druckerei::bereitFuer($land) : [];
    }

    /** Endpreis für den Partner bei Lieferung nach $land; 0 = dorthin nicht bestellbar. */
    public static function preisFuer(int $varianteId, string $land, array $regel): int
    {
        $e = self::einkauf($varianteId, $land);
        if (!$e) { return 0; }
        $p = self::preis($e['cent'], $regel['marge_prozent'], $regel['mindestmarge_cent']);
        return self::gewinn($p, $e['cent']) >= self::MIN_GEWINN_CENT ? $p : 0;   // Gewinnsperre
    }

    /**
     * In welches Land ein Partner vermutlich liefern lässt: seine zuletzt
     * benutzte Adresse, sonst sein Land aus dem Profil, sonst Italien. Nur
     * für die Anzeige — bestellt wird zum Preis des Landes der Adresse.
     */
    public static function anzeigeLand(array $p): string
    {
        try {
            $l = (string) Db::wert('SELECT land FROM wm_adressen WHERE partner_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1', [(int) ($p['id'] ?? 0)], '');
        } catch (Throwable $e) { $l = ''; }
        if (!isset(self::LIEFERLAENDER[$l])) { $l = strtoupper(trim((string) ($p['land'] ?? ''))); }
        return isset(self::LIEFERLAENDER[$l]) ? $l : 'IT';
    }

    /**
     * Der Katalog, wie ein Partner ihn sieht — Preise für $land, dazu je
     * Variante die Preise aller Lieferländer ('preise').
     * @return list<array{slug:string,name:string,produkte:list<array>}>
     */
    public static function katalog(string $sprache, bool $auchAus = false, string $land = 'IT'): array
    {
        /* $auchAus nur für „Als Partner ansehen“: Uwe sieht ein Produkt, bevor
           er es einschaltet. Dann trägt jedes Produkt 'sichtbar'. */
        $sprache = in_array($sprache, self::SPRACHEN, true) ? $sprache : 'it';
        $land = isset(self::LIEFERLAENDER[strtoupper($land)]) ? strtoupper($land) : 'IT';
        $kats = Db::all('SELECT * FROM wm_kategorien WHERE aktiv = 1 ORDER BY sortierung, id');
        $prods = Db::all('SELECT * FROM wm_produkte' . ($auchAus ? '' : ' WHERE aktiv = 1') . ' ORDER BY sortierung, id');
        $vars = Db::all('SELECT * FROM wm_varianten WHERE aktiv = 1 ORDER BY sortierung, auflage, id');
        $jeProdukt = [];
        foreach ($vars as $v) { $jeProdukt[(int) $v['produkt_id']][] = $v; }

        $aus = [];
        foreach ($kats as $k) {
            $liste = [];
            foreach ($prods as $p) {
                if ((int) $p['kategorie_id'] !== (int) $k['id'] || empty($jeProdukt[(int) $p['id']])) { continue; }
                $r = self::regel($p);
                $varianten = [];
                foreach ($jeProdukt[(int) $p['id']] as $v) {
                    $preise = [];
                    foreach (array_keys(self::LIEFERLAENDER) as $l) {
                        $x = self::preisFuer((int) $v['id'], $l, $r);
                        if ($x > 0) { $preise[$l] = $x; }
                    }
                    if (!$preise) { continue; }          // nirgendwohin bestellbar → nicht zeigen
                    $varianten[] = [
                        'id'         => (int) $v['id'],
                        'name'       => self::feld($v, 'name', $sprache),
                        'auflage'    => (int) $v['auflage'],
                        'preis_cent' => $preise[$land] ?? 0,
                        'preise'     => $preise,
                    ];
                }
                if (!$varianten) { continue; }
                $imLand = array_filter(array_column($varianten, 'preis_cent'));
                /* Material und Lieferung (04.10.2026, Uwe: „exakt das, was der Partner kauft“): aus dem
                   günstigsten geprüften Angebot fürs Land — dieselbe Druckerei, die beim Bestellen gewählt wird.
                   Nur Beschreibung, nie Druckereiname oder Einkaufspreis. */
                $ang = self::angebote((int) $varianten[0]['id'], $land)[0] ?? null;
                $liste[] = [
                    'id'        => (int) $p['id'],
                    'nummer'    => (string) $p['nummer'],
                    'name'      => self::feld($p, 'name', $sprache),
                    'text'      => self::feld($p, 'text', $sprache),
                    'format'    => self::format($p),
                    'vorlage'   => (string) $p['vorlage'],
                    'bereich'   => $p['bereich'] ?? null,   // Bereich im Marketing Center (Marketingcenter::bereich)
                    'land'      => $land,
                    'ab_cent'   => $imLand ? min($imLand) : min(array_map(static fn($v) => min($v['preise']), $varianten)),
                    'varianten' => $varianten,
                    'material'  => trim((string) ($ang['papier'] ?? '')),
                    'lieferung' => self::ohneBetrag((string) ($ang['lieferung'] ?? '')),
                ] + ($auchAus ? ['sichtbar' => (int) $p['aktiv'] === 1] : []);
            }
            if ($liste) {
                $aus[] = ['slug' => (string) $k['slug'], 'name' => self::feld($k, 'name', $sprache), 'produkte' => $liste];
            }
        }
        return $aus;
    }

    // ---- Verwaltung (nur Admin) ----------------------------------------------

    /** Alles, mit Einkauf, Druckerei, Preis und Marge je Variante und Lieferland. */
    public static function verwaltung(): array
    {
        $kats = Db::all('SELECT * FROM wm_kategorien ORDER BY sortierung, id');
        $prods = Db::all('SELECT * FROM wm_produkte ORDER BY sortierung, id');
        $vars = Db::all('SELECT * FROM wm_varianten ORDER BY sortierung, auflage, id');
        $jeProdukt = [];
        foreach ($vars as $v) { $jeProdukt[(int) $v['produkt_id']][] = $v; }
        foreach ($prods as &$p) {
            $r = self::regel($p);
            $p['regel'] = $r;
            $p['format'] = self::format($p);
            $p['varianten'] = [];
            foreach ($jeProdukt[(int) $p['id']] ?? [] as $v) {
                $v['laender'] = [];
                foreach (array_keys(self::LIEFERLAENDER) as $l) {
                    $e = self::einkauf((int) $v['id'], $l);
                    $preis = $e ? self::preis($e['cent'], $r['marge_prozent'], $r['mindestmarge_cent']) : 0;
                    $gewinn = $e ? self::gewinn($preis, (int) $e['cent']) : 0;
                    $v['laender'][$l] = ['einkauf_cent' => $e['cent'] ?? 0, 'anbieter' => $e['anbieter'] ?? null, 'preis_cent' => $preis,
                        'marge_cent' => $preis > 0 ? $preis - (int) $e['cent'] : 0, 'gewinn_cent' => $gewinn,
                        'gesperrt' => $e && $gewinn < self::MIN_GEWINN_CENT,
                        'mindest_greift' => $e && (int) $e['cent'] * $r['marge_prozent'] < $r['mindestmarge_cent'] * 100,
                        'veraltet' => !$e && (int) Db::wert('SELECT COUNT(*) FROM wm_anbieter_preise WHERE variante_id = ? AND land = ?', [(int) $v['id'], $l]) > 0];
                }
                // Italien als Hauptspalte (wie bisher), Deutschland daneben.
                $v['preis_cent'] = $v['laender']['IT']['preis_cent'];
                $v['marge_cent'] = $v['laender']['IT']['marge_cent'];
                $v['hat_angebote'] = (int) Db::wert('SELECT COUNT(*) FROM wm_anbieter_preise WHERE variante_id = ?', [(int) $v['id']]) > 0;
                $p['varianten'][] = $v;
            }
            $p['bestellbar'] = (bool) array_filter($p['varianten'], fn($v) => (int) $v['aktiv'] === 1 && array_filter(array_column($v['laender'], 'preis_cent')));
        }
        unset($p);
        return ['kategorien' => $kats, 'produkte' => $prods, 'standard' => self::standard(), 'zahlkosten' => self::zahlkosten()];
    }

    /** Legt ein Produkt an oder ändert es. Gibt die id zurück. */
    public static function produktSpeichern(array $e, int $id = 0): int
    {
        require_once __DIR__ . '/Marketingcenter.php';
        $d = [
            'kategorie_id'  => (int) ($e['kategorie_id'] ?? 0),
            'breite_zmm'    => self::mmZuZmm($e['breite_mm'] ?? '0'),
            'hoehe_zmm'     => self::mmZuZmm($e['hoehe_mm'] ?? '0'),
            'beschnitt_zmm' => self::mmZuZmm($e['beschnitt_mm'] ?? '3'),
            'vorlage'       => array_key_exists((string) ($e['vorlage'] ?? ''), self::VORLAGEN) ? (string) $e['vorlage'] : '',
            // Bereich im Marketing Center; leer = wie die Kategorie (04.10.2026)
            'bereich'       => in_array((string) ($e['bereich'] ?? ''), Marketingcenter::PRODUKT_BEREICHE, true) ? (string) $e['bereich'] : null,
            'marge_prozent' => self::leerOderZahl($e['marge_prozent'] ?? '', 0, 500),
            'mindestmarge_cent' => self::leerOderEuro($e['mindestmarge_eur'] ?? ''),
            'aktiv'         => !empty($e['aktiv']) ? 1 : 0,
            'sortierung'    => (int) ($e['sortierung'] ?? 0),
        ];
        foreach (self::SPRACHEN as $s) {
            $d['name_' . $s] = mb_substr(trim((string) ($e['name_' . $s] ?? '')), 0, 160);
            $d['text_' . $s] = mb_substr(trim((string) ($e['text_' . $s] ?? '')), 0, 400);
        }
        if ($d['name_it'] === '') { throw new InvalidArgumentException('Der italienische Name fehlt.'); }
        if (!Db::wert('SELECT COUNT(*) FROM wm_kategorien WHERE id = ?', [$d['kategorie_id']])) {
            throw new InvalidArgumentException('Kategorie unbekannt.');
        }
        if ($id > 0) {
            Db::update('wm_produkte', $id, $d);
            return $id;
        }
        $neu = Db::insert('wm_produkte', $d);
        Db::run("UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE id = ?", [$neu]);
        return $neu;
    }

    public static function varianteSpeichern(int $produktId, array $e, int $id = 0): int
    {
        if (!Db::wert('SELECT COUNT(*) FROM wm_produkte WHERE id = ?', [$produktId])) {
            throw new InvalidArgumentException('Produkt unbekannt.');
        }
        $d = [
            'auflage'      => max(1, (int) ($e['auflage'] ?? 1)),
            'einkauf_cent' => (int) (self::leerOderEuro($e['einkauf_eur'] ?? '') ?? 0),
            'aktiv'        => !empty($e['aktiv']) ? 1 : 0,
            'sortierung'   => (int) ($e['sortierung'] ?? 0),
        ];
        foreach (self::SPRACHEN as $s) {
            $d['name_' . $s] = mb_substr(trim((string) ($e['name_' . $s] ?? '')), 0, 120);
        }
        if ($d['name_it'] === '') { throw new InvalidArgumentException('Der italienische Name der Variante fehlt.'); }
        if ($id > 0) {
            // Die Variante muss zu diesem Produkt gehören — sonst ließe sich über
            // eine fremde id eine Variante eines anderen Produkts umschreiben.
            if (!Db::wert('SELECT COUNT(*) FROM wm_varianten WHERE id = ? AND produkt_id = ?', [$id, $produktId])) {
                throw new InvalidArgumentException('Variante gehört nicht zu diesem Produkt.');
            }
            // Gibt es geprüfte Angebote, ist der Einkauf das günstigste davon — nicht frei einzutragen.
            if ((int) Db::wert('SELECT COUNT(*) FROM wm_anbieter_preise WHERE variante_id = ?', [$id]) > 0) { unset($d['einkauf_cent']); }
            Db::update('wm_varianten', $id, $d);
            return $id;
        }
        $d['produkt_id'] = $produktId;
        return Db::insert('wm_varianten', $d);
    }

    // ---- Preisvergleich der Druckereien (04.10.2026) --------------------------
    /*  Uwe: „Versuche immer das günstigste zu suchen … selbe Qualität wie bei
        günstigeren, nehme günstigeren.“ Je Auflage die geprüften Angebote;
        der Einkauf ist je Lieferland das günstigste davon (einkauf()).
        wm_varianten.einkauf_cent gilt nur noch, solange es gar kein Angebot
        gibt; anbieter_guenstig wird nicht mehr benutzt (04.10.2026, eine
        Wahrheit statt eines Zwischenspeichers). Ob die Qualität gleich ist,
        entscheidet ein Mensch beim Eintragen (Papier steht daneben). */

    /** Angebote einer Variante (optional nur eines Landes), je Land günstigstes zuerst. */
    public static function angebote(int $varianteId, ?string $land = null): array
    {
        return $land === null
            ? Db::all('SELECT * FROM wm_anbieter_preise WHERE variante_id = ? ORDER BY land = \'IT\' DESC, land, preis_cent, id', [$varianteId])
            : Db::all('SELECT * FROM wm_anbieter_preise WHERE variante_id = ? AND land = ? ORDER BY preis_cent, id', [$varianteId, strtoupper($land)]);
    }

    /** Produktfoto der Druckerei zu einem Entwurf — nur für den eigenen Partner, nur wenn fertig. */
    public static function produktfoto(int $entwurfId, int $partnerId, int $nr = 0): ?string
    {
        // nr 0 = Hauptfoto, 1–3 = weitere Ansichten (wm_produktfotos) — immer nur vom eigenen Entwurf.
        $b = $nr === 0
            ? Db::wert("SELECT mockup FROM wm_entwuerfe WHERE id = ? AND partner_id = ? AND mockup_status = 'fertig'", [$entwurfId, $partnerId], null)
            : Db::wert("SELECT f.bild FROM wm_produktfotos f JOIN wm_entwuerfe e ON e.id = f.entwurf_id
                         WHERE f.entwurf_id = ? AND e.partner_id = ? AND f.nr = ? AND e.mockup_status = 'fertig'", [$entwurfId, $partnerId, $nr], null);
        return is_string($b) && $b !== '' ? $b : null;
    }

    /** Produktfoto der Druckerei für eine Gestaltung (Musterdaten) — oder null. */
    public static function vorlagenfoto(string $vorlage, string $stil, string $sprache): ?string
    {
        $b = Db::wert("SELECT bild FROM wm_vorlagenfotos WHERE vorlage = ? AND stil = ? AND sprache = ? AND status = 'fertig'", [$vorlage, $stil, $sprache], null);
        return is_string($b) && $b !== '' ? $b : null;
    }

    /** Für welche „Stil|Sprache“ einer Vorlage es schon ein Produktfoto gibt. @return list<string> */
    public static function vorlagenfotosDa(string $vorlage): array
    {
        return array_map(static fn($z) => $z['stil'] . '|' . $z['sprache'],
            Db::all("SELECT stil, sprache FROM wm_vorlagenfotos WHERE vorlage = ? AND status = 'fertig' ORDER BY stil, sprache", [$vorlage]));
    }

    /** Welche Fotos es zu einem eigenen Entwurf gibt: [0, 1, 2 …] (0 = Hauptfoto). */
    public static function produktfotos(int $entwurfId, int $partnerId): array
    {
        if (!Db::wert("SELECT COUNT(*) FROM wm_entwuerfe WHERE id = ? AND partner_id = ? AND mockup_status = 'fertig'", [$entwurfId, $partnerId])) { return []; }
        return array_merge([0], array_map('intval', array_column(Db::all('SELECT nr FROM wm_produktfotos WHERE entwurf_id = ? ORDER BY nr', [$entwurfId]), 'nr')));
    }

    /** Wer stellt dieses Produkt im Land her? Die Druckerei des günstigsten geprüften Angebots (wie beim Bestellen). */
    public static function hersteller(array $produkt, string $land): string
    {
        $v = (int) ($produkt['varianten'][0]['id'] ?? 0);
        return $v > 0 ? (string) (self::angebote($v, $land)[0]['anbieter'] ?? '') : '';
    }

    /** Lieferhinweis ohne Beträge: „… (ca. 11 Tage), Standard +6 €“ → „… (ca. 11 Tage)“ — Partner sehen keine Druckereipreise. */
    public static function ohneBetrag(string $t): string
    {
        $teile = array_filter(array_map('trim', preg_split('~,\s+~u', $t) ?: []), static fn($x) => $x !== '' && !preg_match('~€|\bEUR\b|\d+[.,]\d{2}~u', $x));
        return implode(', ', $teile);
    }

    /** Legt ein Angebot an oder ändert es (gleiche Druckerei + gleiches Land = selbe Zeile). */
    public static function angebotSpeichern(int $varianteId, array $e): void
    {
        $anbieter = mb_substr(trim((string) ($e['anbieter'] ?? '')), 0, 40);
        $land = strtoupper(trim((string) ($e['land'] ?? 'IT')));
        $preis = self::leerOderEuro($e['preis_eur'] ?? '');
        $netto = self::leerOderEuro($e['netto_eur'] ?? '');
        $link = trim((string) ($e['link'] ?? ''));
        if ($anbieter === '' || $preis === null || $preis <= 0) { throw new InvalidArgumentException('Druckerei und Preis (inkl. Versand, so wie Vecom zahlt) sind Pflicht.'); }
        if (!isset(self::LIEFERLAENDER[$land])) { throw new InvalidArgumentException('Lieferland muss Italien oder Deutschland sein.'); }
        if ($link !== '' && !preg_match('~^https://[^\s<>"]{4,390}$~', $link)) { throw new InvalidArgumentException('Link muss mit https:// beginnen.'); }
        $datum = trim((string) ($e['geprueft_am'] ?? '')) ?: date('Y-m-d');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $datum)) { throw new InvalidArgumentException('Datum ungültig.'); }
        if (!Db::wert('SELECT COUNT(*) FROM wm_varianten WHERE id = ?', [$varianteId])) { throw new InvalidArgumentException('Variante unbekannt.'); }
        Db::run('INSERT INTO wm_anbieter_preise (variante_id, anbieter, land, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE preis_cent = VALUES(preis_cent), netto_cent = VALUES(netto_cent), papier = VALUES(papier),
                                         lieferung = VALUES(lieferung), link = VALUES(link), geprueft_am = VALUES(geprueft_am)',
            [$varianteId, $anbieter, $land, $preis, $netto, mb_substr(trim((string) ($e['papier'] ?? '')), 0, 120),
             mb_substr(trim((string) ($e['lieferung'] ?? '')), 0, 160), $link, $datum]);
    }

    public static function angebotLoeschen(int $varianteId, string $anbieter, string $land = 'IT'): void
    {
        Db::run('DELETE FROM wm_anbieter_preise WHERE variante_id = ? AND anbieter = ? AND land = ?', [$varianteId, $anbieter, strtoupper($land)]);
    }

    /** Angebote, die älter als $tage sind, gelten als „neu prüfen“. */
    public static function veraltet(array $angebot, int $tage = 30): bool
    {
        return strtotime((string) $angebot['geprueft_am']) < strtotime('-' . $tage . ' days');
    }

    // ---- Phase 2: Druckdatei und Freigabe (03.10.2026) ----------------------
    /*  Vorgabe: „Keine Inhalte ohne Partnerfreigabe automatisch drucken.“
        Gespeichert wird die fertige Datei, nicht nur die Wahl — gedruckt wird
        später genau das, was der Partner gesehen und freigegeben hat. */

    /** Höchstens so viele Entwürfe je Partner und Tag: jeder kostet Rechenzeit. */
    public const ENTWUERFE_JE_TAG = 30;

    /** Wahl prüfen und normalisieren. Wirft bei allem, was es nicht gibt. */
    public static function wahl(array $e, string $vorlage = ''): array
    {
        require_once __DIR__ . '/PartnerKarten.php';
        if ($vorlage === 'flyer_branche') { // „Stil“ ist hier die Branche (04.10.2026)
            require_once __DIR__ . '/WmDruck.php';
            $w = ['stil' => (string) ($e['stil'] ?? ''), 'sprache' => (string) ($e['sprache'] ?? ''), 'kontakt' => (string) ($e['kontakt'] ?? '')];
            if (!WmDruck::gibt('flyer_branche', $w['stil'])) { throw new InvalidArgumentException('Branche unbekannt.'); }
            if (!in_array($w['sprache'], self::SPRACHEN, true)) { throw new InvalidArgumentException('Sprache unbekannt.'); }
            if (!in_array($w['kontakt'], PartnerKarten::KONTAKTE, true)) { throw new InvalidArgumentException('Kontakt unbekannt.'); }
            return $w;
        }
        $w = [
            'stil'    => (string) ($e['stil'] ?? ''),
            'sprache' => (string) ($e['sprache'] ?? ''),
            'kontakt' => (string) ($e['kontakt'] ?? ''),
        ];
        // Je Vorlage ihre eigenen Stile — bei Flyern seit 04.10.2026 auch ein Branchenmotiv (WmDruck::branche).
        if (!($vorlage !== '' ? self::stilDa($vorlage, $w['stil']) : PartnerKarten::gibt($w['stil']))) { throw new InvalidArgumentException('Stil unbekannt.'); }
        if (!in_array($w['sprache'], self::SPRACHEN, true)) { throw new InvalidArgumentException('Sprache unbekannt.'); }
        if (!in_array($w['kontakt'], PartnerKarten::KONTAKTE, true)) { throw new InvalidArgumentException('Kontakt unbekannt.'); }
        // Überschrift (Schritt 4): nur aus der freigegebenen Liste; ohne Angabe die erste. Freier Text kommt nie auf den Druck.
        if ($vorlage !== '' && $vorlage !== 'visitenkarte') {
            require_once __DIR__ . '/WmDruck.php';
            if (WmDruck::hatTitel($vorlage, $w['stil'])) {
                $t = (string) ($e['titel'] ?? '');
                if ($t !== '' && !isset(Texte::WM_TITEL[$t])) { throw new InvalidArgumentException('Überschrift nicht freigegeben.'); }
                $w['titel'] = WmDruck::titel($t);
            }
        }
        return $w;
    }

    /**
     * Erzeugt die Druckdatei für die Wahl des Partners und legt sie als Entwurf
     * ab. Ein noch nicht freigegebener Entwurf desselben Produkts wird ersetzt.
     *
     * Seit 04.10.2026 (Marketingcenter, Marketing-ID): Die Zeile entsteht ZUERST
     * (Status „entsteht“), weil ihre Nummer in den QR-Code gehört (/p/CODE/wm-241).
     * „entsteht“ kann niemand freigeben (freigeben() verlangt „entwurf“); scheitert
     * die Datei, verschwindet die Zeile wieder, und der alte Entwurf bleibt stehen.
     */
    /** Prüfnaht für die Kette: läuft, nachdem die Zeile „entsteht“ angelegt ist, und kann scheitern. */
    public static ?\Closure $vorDatei = null;

    public static function entwurfAnlegen(array $p, int $produktId, array $eingabe): int
    {
        $pr = Db::one('SELECT * FROM wm_produkte WHERE id = ? AND aktiv = 1', [$produktId]);
        if (!$pr || $pr['vorlage'] === '') { throw new InvalidArgumentException('Produkt nicht verfügbar.'); }
        $w = self::wahl($eingabe, (string) $pr['vorlage']);
        // Liegengebliebenes (Abbruch mitten im Erzeugen) zählt nicht und stört nicht.
        Db::run("DELETE FROM wm_entwuerfe WHERE partner_id = ? AND status = 'entsteht' AND created_at < NOW() - INTERVAL 1 HOUR", [(int) $p['id']]);
        $heute = (int) Db::wert('SELECT COUNT(*) FROM wm_entwuerfe WHERE partner_id = ? AND created_at >= CURDATE()', [(int) $p['id']]);
        if ($heute >= self::ENTWUERFE_JE_TAG) { throw new RuntimeException('zuviel'); }
        // Kampagne (05.10.2026, Etappe 0c): nur eine eigene; Version: V1, V2, … je Partner und Produkt.
        $kampagneId = (int) ($eingabe['kampagne'] ?? 0);
        if ($kampagneId > 0) {
            require_once __DIR__ . '/PartnerKampagne.php';
            if (!PartnerKampagne::laden((int) $p['id'], $kampagneId)) { $kampagneId = 0; }
        }
        $version = 1 + (int) Db::wert('SELECT COALESCE(MAX(version), 0) FROM wm_entwuerfe WHERE partner_id = ? AND produkt_id = ?', [(int) $p['id'], $produktId], 0);
        $id = Db::insert('wm_entwuerfe', [
            'partner_id' => (int) $p['id'], 'produkt_id' => $produktId, 'wahl' => json_encode($w, JSON_UNESCAPED_UNICODE),
            'datei' => '', 'datei_hash' => str_repeat('0', 64), 'datei_bytes' => 0, 'status' => 'entsteht',
            'kampagne_id' => $kampagneId > 0 ? $kampagneId : null, 'version' => $version,
        ]);
        try {
            if (self::$vorDatei) { (self::$vorDatei)(); }
            $p = self::mitKanal($p, $id);
            $p['_wm_titel'] = (string) ($w['titel'] ?? '');
            $pdf = match ((string) $pr['vorlage']) {
                'visitenkarte' => PartnerKarten::pdf($p, $w['stil'], $w['sprache'], $w['kontakt'], 'einzeln'),
                'flyer_a6', 'flyer_a5', 'flyer_branche', 'aufkleber_50', 'rollup_85', 'tasse_11', 'notizbuch', 'flasche', 'untersetzer', 'beutel' => (static function () use ($p, $pr, $w): string { require_once __DIR__ . '/WmDruck.php'; return WmDruck::pdf($p, (string) $pr['vorlage'], $w['stil'], $w['sprache'], $w['kontakt']); })(),
                'kalender_a3' => (static function () use ($p, $w): string { require_once __DIR__ . '/WmKalender.php'; return WmKalender::pdf($p, $w['stil'], $w['sprache'], $w['kontakt']); })(),
                default => '',
            };
            if ($pdf === '') { throw new RuntimeException('Druckdatei ließ sich nicht erzeugen.'); }
            // Dieselbe Karte für den Druckanbieter (Gelato: 4 mm Beschnitt, 300 dpi) — im selben Moment
            // aus denselben Daten, damit nichts anderes gedruckt wird als freigegeben (Phase 4).
            $druck = match ((string) $pr['vorlage']) {
                'visitenkarte' => PartnerKarten::druckPdf($p, $w['stil'], $w['sprache'], $w['kontakt'], 4.0, 300),
                // Flyer gehen an Flyeralarm (von Hand): dort 1 mm Beschnitt je Seite statt unserer 3 mm.
                'flyer_a6', 'flyer_a5', 'flyer_branche' => WmDruck::pdf($p, (string) $pr['vorlage'], $w['stil'], $w['sprache'], $w['kontakt'], self::FLYERALARM_BESCHNITT),
                // Kalender: schon im Gelato-Maß (4 mm Beschnitt, 300 dpi) — Druckfassung = Ansicht, Byte für Byte.
                // Gespeichert wird sie nur EINMAL (11 MB): zweimal in einer Zeile überschreitet max_allowed_packet
                // (16 MB, gemessen 04.10.2026: „MySQL server has gone away“). Erkennbar an datei_druck NULL bei
                // datei_druck_hash = datei_hash; druckdatei.php liefert dann die Ansicht aus.
                'kalender_a3' => $pdf,
                default => '',
            };
            // Und die eingepasste Fassung für Printful (90 × 50 mm, Uwes Entscheidung 04.10.2026): der
            // Partner sieht sie vor der Freigabe als zweite Vorschau — ohne sie geht nichts an Printful.
            $pf = ['', ''];
            if (in_array((string) $pr['vorlage'], ['tasse_11', 'notizbuch', 'flasche', 'untersetzer', 'beutel'], true)) {
                // Printful druckt Tasse und Geschenke aus genau diesen Bildern (Printfuls Pixelmaß, Code als Raster) — der
                // Partner sieht sie als Vorschau. Zwei Druckstellen (Notizbuch): Vorder- und Rückseite.
                require_once __DIR__ . '/Printful.php';
                $zwei = isset(Printful::ARTEN[(string) $pr['vorlage']]['dateien']['back']);
                $pf = [WmDruck::bild($p, (string) $pr['vorlage'], $w['stil'], $w['sprache'], $w['kontakt']),
                       $zwei ? WmDruck::bild($p, (string) $pr['vorlage'], $w['stil'], $w['sprache'], $w['kontakt'], 'hinten') : ''];
                if ($pf[0] === '' || ($zwei && $pf[1] === '')) { throw new RuntimeException('Printful-Bild ließ sich nicht erzeugen.'); }
            }
            if ((string) $pr['vorlage'] === 'visitenkarte') {
                require_once __DIR__ . '/Printful.php';
                [$pw, $ph] = Printful::VORLAGE;
                $pf = [PartnerKarten::eingepasst($p, $w['stil'], 'vorn', $w['sprache'], $w['kontakt'], $pw, $ph, Printful::RAND),
                       PartnerKarten::eingepasst($p, $w['stil'], 'hinten', $w['sprache'], $w['kontakt'], $pw, $ph, Printful::RAND)];
            }
        } catch (Throwable $e) {
            Db::run("DELETE FROM wm_entwuerfe WHERE id = ? AND status = 'entsteht'", [$id]);
            throw $e;
        }
        $druckGleich = $druck !== '' && $druck === $pdf;
        // Eine Druckstelle (Tasse, Flasche, Untersetzer, Beutel): nur vorn.
        $tasse = in_array((string) $pr['vorlage'], ['tasse_11', 'flasche', 'untersetzer', 'beutel'], true);
        Db::transaktion(static function () use ($p, $produktId, $id, $pdf, $druck, $pf, $druckGleich, $tasse): void {
            Db::run("DELETE FROM wm_entwuerfe WHERE partner_id = ? AND produkt_id = ? AND status = 'entwurf' AND id <> ?", [(int) $p['id'], $produktId, $id]);
            Db::run("UPDATE wm_entwuerfe SET datei = ?, datei_hash = ?, datei_bytes = ?, datei_druck = ?, datei_druck_hash = ?,
                            datei_pf_vorn = ?, datei_pf_hinten = ?, status = 'entwurf' WHERE id = ? AND status = 'entsteht'", [
                $pdf, hash('sha256', $pdf), strlen($pdf),
                $druck !== '' && !$druckGleich ? $druck : null, $druck !== '' ? hash('sha256', $druck) : null,
                // Karte: beide Seiten oder keine; Tasse: nur vorn (eine Druckstelle).
                $pf[0] !== '' && ($pf[1] !== '' || $tasse) ? $pf[0] : null, $pf[0] !== '' && $pf[1] !== '' ? $pf[1] : null, $id]);
        }, 3);
        // QR-Prüfung vor der Produktion (Schritt 8): die fertigen Dateien zurücklesen. Fällt sie durch,
        // bleibt der Entwurf sichtbar, ist aber nicht freigebbar (freigeben) und geht nie in den Druck.
        require_once __DIR__ . '/QrPruefung.php';
        QrPruefung::fuerEntwurf($id);
        return $id;
    }

    // ---- Marketing-ID je Werbemittel (04.10.2026, Marketingcenter Schritt 1b) -------------
    /*  VM-2026-000241: Jahr und Nummer des Entwurfs. Nicht gespeichert, sondern gebildet —
        so kann sie nie von der Zeile abweichen, zu der sie gehört. */

    /** Kanal hinter dem QR-Code dieses Werbemittels (Partner::BEITRAG_KANAELE „wm“). */
    public static function kanal(int $entwurfId): string
    {
        return 'wm-' . $entwurfId;
    }

    /** $p mit dem Kanal des Werbemittels — WmDruck::qrLink und PartnerKarten::link lesen ihn. */
    public static function mitKanal(array $p, int $entwurfId): array
    {
        $p['_wm_kanal'] = self::kanal($entwurfId);
        return $p;
    }

    /** @param array{id:int|string, created_at:string} $e */
    public static function marketingId(array $e): string
    {
        return sprintf('VM-%s-%06d', substr((string) $e['created_at'], 0, 4), (int) $e['id']);
    }

    /** VM-2026-000241 → 241 (oder null). Das Jahr wird mitgeprüft, sonst passt jede Zahl. */
    public static function ausMarketingId(string $mid): ?int
    {
        if (!preg_match('~^VM-(\d{4})-(\d{6,8})$~', strtoupper(trim($mid)), $m)) { return null; }
        $jahr = Db::wert('SELECT YEAR(created_at) FROM wm_entwuerfe WHERE id = ?', [(int) $m[2]], null);
        return $jahr !== null && (int) $jahr === (int) $m[1] ? (int) $m[2] : null;
    }

    /**
     * Was ein Werbemittel gebracht hat — nur für den eigenen Partner.
     * scans: jeder echte Aufruf über den QR-Code/Link (dauerhaft gezählt).
     * besucher: verschiedene Besucher (aus der Spur, nur so weit die Rohdaten reichen).
     * anfragen: Kunden, die über dieses Werbemittel zugeordnet wurden; abschluesse: davon bezahlt.
     * @return array{scans:int, besucher:int, anfragen:int, abschluesse:int, quote:?float}
     */
    public static function erfolg(int $partnerId, int $entwurfId): array
    {
        $k = self::kanal($entwurfId);
        $scans = (int) Db::wert('SELECT scans FROM wm_entwuerfe WHERE id = ? AND partner_id = ?', [$entwurfId, $partnerId], 0);
        $besucher = (int) Db::wert('SELECT COUNT(DISTINCT visitor_id) FROM spur_besuche WHERE partner_id = ? AND kanal = ? AND verdacht = 0', [$partnerId, $k], 0);
        $anfragen = (int) Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ? AND kanal = ?', [$partnerId, $k], 0);
        $abschluesse = (int) Db::wert("SELECT COUNT(DISTINCT pp.customer_id) FROM partner_provisionen pp
                                         JOIN partner_zuordnungen z ON z.customer_id = pp.customer_id AND z.partner_id = pp.partner_id
                                        WHERE pp.partner_id = ? AND z.kanal = ? AND pp.status NOT IN ('storniert','abgelehnt')", [$partnerId, $k], 0);
        return ['scans' => $scans, 'besucher' => $besucher, 'anfragen' => $anfragen, 'abschluesse' => $abschluesse,
                'quote' => $scans > 0 ? round(100 * $anfragen / $scans, 1) : null];
    }

    /**
     * Freigabe durch den Partner. Nur eigener Entwurf, nur im Status
     * „entwurf“, nur mit dem Hash, den er gesehen hat. Eine ältere Freigabe
     * desselben Produkts wird „ersetzt“ (bleibt aber erhalten).
     */
    public static function freigeben(int $partnerId, int $entwurfId, string $hash): bool
    {
        if (!preg_match('~^[0-9a-f]{64}$~', $hash)) { return false; }
        if (self::qrGesperrt($partnerId, $entwurfId)) { return false; }
        return (bool) Db::transaktion(static function () use ($partnerId, $entwurfId, $hash): bool {
            $e = Db::one("SELECT id, produkt_id FROM wm_entwuerfe WHERE id = ? AND partner_id = ? AND status = 'entwurf' AND datei_hash = ? FOR UPDATE",
                [$entwurfId, $partnerId, $hash]);
            if (!$e) { return false; }
            Db::run("UPDATE wm_entwuerfe SET status = 'ersetzt' WHERE partner_id = ? AND produkt_id = ? AND status = 'freigegeben'",
                [$partnerId, (int) $e['produkt_id']]);
            Db::run("UPDATE wm_entwuerfe SET status = 'freigegeben', freigegeben_am = NOW() WHERE id = ?", [$entwurfId]);
            return true;
        }, 3);
    }

    /** Eigener Entwurf, dessen Codes die QR-Prüfung nicht bestehen (ältere werden jetzt geprüft)? */
    public static function qrGesperrt(int $partnerId, int $entwurfId): bool
    {
        if (!Db::one('SELECT id FROM wm_entwuerfe WHERE id = ? AND partner_id = ?', [$entwurfId, $partnerId])) { return false; }
        require_once __DIR__ . '/QrPruefung.php';
        return !QrPruefung::ok($entwurfId);
    }

    /**
     * Der Partner verwirft seinen Entwurf vor der Freigabe (04.10.2026). Nur
     * eigener, nur im Status „entwurf“, nie einer, auf den eine Bestellung
     * zeigt. Eine bestehende Freigabe bleibt unberührt.
     */
    public static function entwurfVerwerfen(int $partnerId, int $entwurfId): bool
    {
        return Db::run("DELETE FROM wm_entwuerfe WHERE id = ? AND partner_id = ? AND status = 'entwurf'
                         AND NOT EXISTS (SELECT 1 FROM wm_positionen x WHERE x.entwurf_id = ?)", [$entwurfId, $partnerId, $entwurfId])->rowCount() === 1;
    }

    /** Der aktuelle Entwurf und die aktuelle Freigabe, ohne Datei. */
    public static function stand(int $partnerId, int $produktId): array
    {
        $felder = 'id, wahl, datei_hash, datei_bytes, status, created_at, freigegeben_am, scans, mockup_status, qr_ok, qr_pruefung, (datei_pf_vorn IS NOT NULL AND datei_pf_hinten IS NOT NULL) AS hat_pf';
        $hol = static function (string $status) use ($felder, $partnerId, $produktId): ?array {
            $r = Db::one("SELECT $felder FROM wm_entwuerfe WHERE partner_id = ? AND produkt_id = ? AND status = ? ORDER BY id DESC LIMIT 1",
                [$partnerId, $produktId, $status]);
            if ($r) { $r['wahl'] = (array) json_decode((string) $r['wahl'], true); }
            return $r;
        };
        return ['entwurf' => $hol('entwurf'), 'freigegeben' => $hol('freigegeben')];
    }

    /** Die Datei eines Entwurfs — für den Partner nur seine eigene ($partnerId), für den Admin jede (null). */
    /** Beschnitt (mm je Seite), den Flyeralarm für Flyer verlangt — Datenblatt, geprüft 04.10.2026. */
    public const FLYERALARM_BESCHNITT = 1.0;

    public static function datei(int $entwurfId, ?int $partnerId): ?array
    {
        $r = $partnerId === null
            ? Db::one('SELECT id, datei, datei_hash, datei_druck, partner_id, produkt_id FROM wm_entwuerfe WHERE id = ?', [$entwurfId])
            : Db::one('SELECT id, datei, datei_hash, partner_id, produkt_id FROM wm_entwuerfe WHERE id = ? AND partner_id = ?', [$entwurfId, $partnerId]);
        return $r ?: null;
    }

    /** Eine Seite der eingepassten Fassung (90 × 50 mm) — nur eigene Entwürfe des Partners. JPEG oder null. */
    public static function eingepasstBild(int $entwurfId, int $partnerId, string $seite): ?string
    {
        $spalte = $seite === 'hinten' ? 'datei_pf_hinten' : 'datei_pf_vorn';
        $b = Db::wert("SELECT $spalte FROM wm_entwuerfe WHERE id = ? AND partner_id = ?", [$entwurfId, $partnerId]);
        return is_string($b) && $b !== '' ? $b : null;
    }

    /** Für die Verwaltung: die letzten Freigaben mit Partner und Produkt. */
    public static function freigaben(int $anzahl = 100): array
    {
        return Db::all("SELECT e.id, e.wahl, e.status, e.freigegeben_am, e.datei_bytes, e.datei_hash, p.name AS partner, p.code, w.nummer, w.name_de, w.name_it
                          FROM wm_entwuerfe e JOIN partner p ON p.id = e.partner_id JOIN wm_produkte w ON w.id = e.produkt_id
                         WHERE e.status IN ('freigegeben', 'ersetzt') ORDER BY e.freigegeben_am DESC, e.id DESC LIMIT " . max(1, min(500, $anzahl)));
    }

    // ---- Helfer ---------------------------------------------------------------

    /** „85 × 55 mm“ aus den Zehntelmillimetern; leer, wenn kein Format. */
    public static function format(array $p): string
    {
        $b = (int) ($p['breite_zmm'] ?? 0); $h = (int) ($p['hoehe_zmm'] ?? 0);
        if ($b <= 0 || $h <= 0) { return ''; }
        $mm = fn(int $z) => $z % 10 === 0 ? (string) intdiv($z, 10) : str_replace('.', ',', number_format($z / 10, 1, '.', ''));
        return $mm($b) . ' × ' . $mm($h) . ' mm';
    }

    /** Betrag in Cent als „12,50 €“. */
    public static function euro(int $cent): string
    {
        return number_format($cent / 100, 2, ',', '.') . "\u{00A0}€";   // geschütztes Leerzeichen: kein Umbruch vor €
    }

    private static function feld(array $zeile, string $feld, string $sprache): string
    {
        $wert = trim((string) ($zeile[$feld . '_' . $sprache] ?? ''));
        return $wert !== '' ? $wert : (string) ($zeile[$feld . '_it'] ?? '');
    }

    private static function mmZuZmm(mixed $wert): int
    {
        $s = str_replace(',', '.', trim((string) $wert));
        if ($s === '' || !is_numeric($s)) { return 0; }
        return max(0, (int) round(((float) $s) * 10));
    }

    private static function leerOderZahl(mixed $wert, int $min, int $max): ?int
    {
        $s = trim((string) $wert);
        if ($s === '') { return null; }
        if (!preg_match('~^\d+$~', $s) || (int) $s < $min || (int) $s > $max) {
            throw new InvalidArgumentException("Wert muss zwischen $min und $max liegen.");
        }
        return (int) $s;
    }

    /**
     * „12,50“ / „12.50“ / „12“ → 1250 Cent, leer → null. Ohne Fließkomma
     * gerechnet: Euro und Cent werden als Text getrennt.
     */
    public static function leerOderEuro(mixed $wert): ?int
    {
        $s = str_replace([' ', '€'], '', trim((string) $wert));
        if ($s === '') { return null; }
        if (!preg_match('~^(\d{1,7})(?:[.,](\d{1,2}))?$~', $s, $m)) {
            throw new InvalidArgumentException('Betrag ungültig: ' . $s);
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    private static function einstellung(string $schluessel, string $ersatz): string
    {
        try {
            return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], $ersatz);
        } catch (Throwable $e) { return $ersatz; }
    }
}
