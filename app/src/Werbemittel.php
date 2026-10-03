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
    public const VORLAGEN = ['visitenkarte' => 'Visitenkarte (PartnerKarten)'];

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

    /** Verkaufspreis in Cent. 0 heißt: nicht bestellbar (kein Einkauf). */
    public static function preis(int $einkaufCent, int $margeProzent, int $mindestCent): int
    {
        if ($einkaufCent <= 0) { return 0; }
        $nachProzent = intdiv($einkaufCent * (100 + $margeProzent) + 99, 100);   // aufrunden
        $roh = max($nachProzent, $einkaufCent + $mindestCent);
        return intdiv($roh + 9, 10) * 10;
    }

    // ---- Partnerbereich ------------------------------------------------------

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
        $auto = self::automatischeAnbieter();
        if ($auto) {
            $ph = implode(',', array_fill(0, count($auto), '?'));
            $g = Db::one("SELECT anbieter, preis_cent FROM wm_anbieter_preise WHERE variante_id = ? AND land = ? AND anbieter IN ($ph) ORDER BY preis_cent, id LIMIT 1",
                array_merge([$varianteId, $land], $auto)) ?: null;
        }
        $g ??= Db::one('SELECT anbieter, preis_cent FROM wm_anbieter_preise WHERE variante_id = ? AND land = ? ORDER BY preis_cent, id LIMIT 1', [$varianteId, $land]);
        if ($g) { return ['cent' => (int) $g['preis_cent'], 'anbieter' => (string) $g['anbieter']]; }
        if ((int) Db::wert('SELECT COUNT(*) FROM wm_anbieter_preise WHERE variante_id = ?', [$varianteId]) > 0) { return null; }
        $h = (int) Db::wert('SELECT einkauf_cent FROM wm_varianten WHERE id = ?', [$varianteId], 0);
        return $h > 0 ? ['cent' => $h, 'anbieter' => null] : null;
    }

    /** Druckereien, die im Automatikbetrieb Aufträge per Schnittstelle bekommen können (heute: Gelato mit Schlüssel). */
    public static function automatischeAnbieter(): array
    {
        require_once __DIR__ . '/WmBestellung.php';
        require_once __DIR__ . '/Gelato.php';
        return WmBestellung::automatik() && Gelato::bereit() ? [Gelato::NAME] : [];
    }

    /** Endpreis für den Partner bei Lieferung nach $land; 0 = dorthin nicht bestellbar. */
    public static function preisFuer(int $varianteId, string $land, array $regel): int
    {
        $e = self::einkauf($varianteId, $land);
        return $e ? self::preis($e['cent'], $regel['marge_prozent'], $regel['mindestmarge_cent']) : 0;
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
                $liste[] = [
                    'id'        => (int) $p['id'],
                    'nummer'    => (string) $p['nummer'],
                    'name'      => self::feld($p, 'name', $sprache),
                    'text'      => self::feld($p, 'text', $sprache),
                    'format'    => self::format($p),
                    'vorlage'   => (string) $p['vorlage'],
                    'land'      => $land,
                    'ab_cent'   => $imLand ? min($imLand) : min(array_map(static fn($v) => min($v['preise']), $varianten)),
                    'varianten' => $varianten,
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
                    $v['laender'][$l] = ['einkauf_cent' => $e['cent'] ?? 0, 'anbieter' => $e['anbieter'] ?? null, 'preis_cent' => $preis, 'marge_cent' => $preis > 0 ? $preis - (int) $e['cent'] : 0];
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
        return ['kategorien' => $kats, 'produkte' => $prods, 'standard' => self::standard()];
    }

    /** Legt ein Produkt an oder ändert es. Gibt die id zurück. */
    public static function produktSpeichern(array $e, int $id = 0): int
    {
        $d = [
            'kategorie_id'  => (int) ($e['kategorie_id'] ?? 0),
            'breite_zmm'    => self::mmZuZmm($e['breite_mm'] ?? '0'),
            'hoehe_zmm'     => self::mmZuZmm($e['hoehe_mm'] ?? '0'),
            'beschnitt_zmm' => self::mmZuZmm($e['beschnitt_mm'] ?? '3'),
            'vorlage'       => array_key_exists((string) ($e['vorlage'] ?? ''), self::VORLAGEN) ? (string) $e['vorlage'] : '',
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
    public static function wahl(array $e): array
    {
        require_once __DIR__ . '/PartnerKarten.php';
        $w = [
            'stil'    => (string) ($e['stil'] ?? ''),
            'sprache' => (string) ($e['sprache'] ?? ''),
            'kontakt' => (string) ($e['kontakt'] ?? ''),
        ];
        if (!PartnerKarten::gibt($w['stil'])) { throw new InvalidArgumentException('Stil unbekannt.'); }
        if (!in_array($w['sprache'], self::SPRACHEN, true)) { throw new InvalidArgumentException('Sprache unbekannt.'); }
        if (!in_array($w['kontakt'], PartnerKarten::KONTAKTE, true)) { throw new InvalidArgumentException('Kontakt unbekannt.'); }
        return $w;
    }

    /**
     * Erzeugt die Druckdatei für die Wahl des Partners und legt sie als Entwurf
     * ab. Ein noch nicht freigegebener Entwurf desselben Produkts wird ersetzt.
     */
    public static function entwurfAnlegen(array $p, int $produktId, array $eingabe): int
    {
        $pr = Db::one('SELECT * FROM wm_produkte WHERE id = ? AND aktiv = 1', [$produktId]);
        if (!$pr || $pr['vorlage'] === '') { throw new InvalidArgumentException('Produkt nicht verfügbar.'); }
        $w = self::wahl($eingabe);
        $heute = (int) Db::wert('SELECT COUNT(*) FROM wm_entwuerfe WHERE partner_id = ? AND created_at >= CURDATE()', [(int) $p['id']]);
        if ($heute >= self::ENTWUERFE_JE_TAG) { throw new RuntimeException('zuviel'); }
        $pdf = match ((string) $pr['vorlage']) {
            'visitenkarte' => PartnerKarten::pdf($p, $w['stil'], $w['sprache'], $w['kontakt'], 'einzeln'),
            default => '',
        };
        if ($pdf === '') { throw new RuntimeException('Druckdatei ließ sich nicht erzeugen.'); }
        // Dieselbe Karte für den Druckanbieter (Gelato: 4 mm Beschnitt, 300 dpi) — im selben Moment
        // aus denselben Daten, damit nichts anderes gedruckt wird als freigegeben (Phase 4).
        $druck = match ((string) $pr['vorlage']) {
            'visitenkarte' => PartnerKarten::druckPdf($p, $w['stil'], $w['sprache'], $w['kontakt'], 4.0, 300),
            default => '',
        };
        return (int) Db::transaktion(static function () use ($p, $produktId, $w, $pdf, $druck): int {
            Db::run("DELETE FROM wm_entwuerfe WHERE partner_id = ? AND produkt_id = ? AND status = 'entwurf'", [(int) $p['id'], $produktId]);
            return Db::insert('wm_entwuerfe', [
                'partner_id' => (int) $p['id'], 'produkt_id' => $produktId,
                'wahl' => json_encode($w, JSON_UNESCAPED_UNICODE),
                'datei' => $pdf, 'datei_hash' => hash('sha256', $pdf), 'datei_bytes' => strlen($pdf),
                'datei_druck' => $druck !== '' ? $druck : null, 'datei_druck_hash' => $druck !== '' ? hash('sha256', $druck) : null,
            ]);
        }, 3);
    }

    /**
     * Freigabe durch den Partner. Nur eigener Entwurf, nur im Status
     * „entwurf“, nur mit dem Hash, den er gesehen hat. Eine ältere Freigabe
     * desselben Produkts wird „ersetzt“ (bleibt aber erhalten).
     */
    public static function freigeben(int $partnerId, int $entwurfId, string $hash): bool
    {
        if (!preg_match('~^[0-9a-f]{64}$~', $hash)) { return false; }
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

    /** Der aktuelle Entwurf und die aktuelle Freigabe, ohne Datei. */
    public static function stand(int $partnerId, int $produktId): array
    {
        $felder = 'id, wahl, datei_hash, datei_bytes, status, created_at, freigegeben_am';
        $hol = static function (string $status) use ($felder, $partnerId, $produktId): ?array {
            $r = Db::one("SELECT $felder FROM wm_entwuerfe WHERE partner_id = ? AND produkt_id = ? AND status = ? ORDER BY id DESC LIMIT 1",
                [$partnerId, $produktId, $status]);
            if ($r) { $r['wahl'] = (array) json_decode((string) $r['wahl'], true); }
            return $r;
        };
        return ['entwurf' => $hol('entwurf'), 'freigegeben' => $hol('freigegeben')];
    }

    /** Die Datei eines Entwurfs — für den Partner nur seine eigene ($partnerId), für den Admin jede (null). */
    public static function datei(int $entwurfId, ?int $partnerId): ?array
    {
        $r = $partnerId === null
            ? Db::one('SELECT id, datei, datei_hash, partner_id, produkt_id FROM wm_entwuerfe WHERE id = ?', [$entwurfId])
            : Db::one('SELECT id, datei, datei_hash, partner_id, produkt_id FROM wm_entwuerfe WHERE id = ? AND partner_id = ?', [$entwurfId, $partnerId]);
        return $r ?: null;
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
