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

    /**
     * Der Katalog, wie ein Partner ihn sieht.
     * @return list<array{slug:string,name:string,produkte:list<array>}>
     */
    public static function katalog(string $sprache, bool $auchAus = false): array
    {
        /* $auchAus nur für „Als Partner ansehen“: Uwe sieht ein Produkt, bevor
           er es einschaltet. Dann trägt jedes Produkt 'sichtbar'. */
        $sprache = in_array($sprache, self::SPRACHEN, true) ? $sprache : 'it';
        $kats = Db::all('SELECT * FROM wm_kategorien WHERE aktiv = 1 ORDER BY sortierung, id');
        $prods = Db::all('SELECT * FROM wm_produkte' . ($auchAus ? '' : ' WHERE aktiv = 1') . ' ORDER BY sortierung, id');
        $vars = Db::all('SELECT * FROM wm_varianten WHERE aktiv = 1 AND einkauf_cent > 0 ORDER BY sortierung, auflage, id');
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
                    $varianten[] = [
                        'id'         => (int) $v['id'],
                        'name'       => self::feld($v, 'name', $sprache),
                        'auflage'    => (int) $v['auflage'],
                        'preis_cent' => self::preis((int) $v['einkauf_cent'], $r['marge_prozent'], $r['mindestmarge_cent']),
                    ];
                }
                $liste[] = [
                    'id'        => (int) $p['id'],
                    'nummer'    => (string) $p['nummer'],
                    'name'      => self::feld($p, 'name', $sprache),
                    'text'      => self::feld($p, 'text', $sprache),
                    'format'    => self::format($p),
                    'vorlage'   => (string) $p['vorlage'],
                    'ab_cent'   => min(array_column($varianten, 'preis_cent')),
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

    /** Alles, mit Einkauf, Regel, Preis und Marge je Variante. */
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
                $preis = self::preis((int) $v['einkauf_cent'], $r['marge_prozent'], $r['mindestmarge_cent']);
                $v['preis_cent'] = $preis;
                $v['marge_cent'] = $preis > 0 ? $preis - (int) $v['einkauf_cent'] : 0;
                $p['varianten'][] = $v;
            }
            $p['bestellbar'] = (bool) array_filter($p['varianten'], fn($v) => (int) $v['aktiv'] === 1 && (int) $v['preis_cent'] > 0);
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
            Db::update('wm_varianten', $id, $d);
            return $id;
        }
        $d['produkt_id'] = $produktId;
        return Db::insert('wm_varianten', $d);
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
        return (int) Db::transaktion(static function () use ($p, $produktId, $w, $pdf): int {
            Db::run("DELETE FROM wm_entwuerfe WHERE partner_id = ? AND produkt_id = ? AND status = 'entwurf'", [(int) $p['id'], $produktId]);
            return Db::insert('wm_entwuerfe', [
                'partner_id' => (int) $p['id'], 'produkt_id' => $produktId,
                'wahl' => json_encode($w, JSON_UNESCAPED_UNICODE),
                'datei' => $pdf, 'datei_hash' => hash('sha256', $pdf), 'datei_bytes' => strlen($pdf),
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
