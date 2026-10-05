<?php
declare(strict_types=1);

require_once __DIR__ . '/PartnerBranche.php';
require_once __DIR__ . '/Texte.php';

/* ==========================================================================
   PartnerKurzlink.php — /go/name und /go/name/branche (Phase 4, 05.10.2026,
   Uwe: „Partner wählt selbst“).

   KEINE ZWEITE WELT: go.php löst den Namen in den Code des Partners auf und
   gibt an p.php weiter, als wäre /p/CODE/go-gastronomie aufgerufen worden.
   Klick, Keks, Spur, Zuordnung und Provision laufen über denselben Weg wie
   jeder andere Partnerlink. Der Kanal heißt „go“ oder „go-<branche>“.

   NAMEN BLEIBEN: Ein Name gehört für immer dem Partner, der ihn zuerst nahm.
   Wer umbenennt, behält den alten als „alt“ — sonst führte ein gedruckter
   QR-Code nach der Umbenennung auf die Startseite, und ein anderer Partner
   könnte den freien Namen nehmen und fremde Flyer für sich zählen lassen.
   Höchstens NAMEN_MAX Namen je Partner; danach ändert nur Uwe.
   ========================================================================== */
final class PartnerKurzlink
{
    public const NAMEN_MAX = 3;

    /** Wörter, die kein Partner bekommt: Wege der Website, Vecom selbst, große Marken. */
    public const GESPERRT = ['admin', 'administrator', 'vecom', 'vecomdesign', 'vecom-design', 'design', 'partner', 'partners', 'go', 'api', 'www', 'web',
        'mail', 'email', 'test', 'demo', 'support', 'hilfe', 'aiuto', 'help', 'info', 'shop', 'login', 'logout', 'konto', 'account', 'kontakt', 'contatti',
        'contact', 'impressum', 'privacy', 'datenschutz', 'agb', 'termini', 'preise', 'prezzi', 'pricing', 'assistenza', 'cockpit', 'app', 'root', 'system',
        'null', 'undefined', 'offiziell', 'ufficiale', 'official', 'google', 'facebook', 'instagram', 'whatsapp', 'tiktok', 'telegram', 'linkedin', 'amazon',
        'apple', 'microsoft', 'meta', 'stripe', 'paypal', 'wise', 'aruba', 'kas', 'allinkl', 'all-inkl', 'polizia', 'polizei', 'police', 'comune', 'regione'];

    /**
     * Die Branche hinter dem Namen, in drei Sprachen — der Partner gibt Italienern /go/ulli/ristoranti,
     * Deutschen /go/ulli/gastronomie. Alle führen auf dieselbe Branche. „andere“ hat keine Seite.
     */
    public const SLUGS = [
        'gastronomie'  => ['it' => 'ristoranti',  'de' => 'gastronomie',  'en' => 'restaurants'],
        'unterkunft'   => ['it' => 'alloggi',     'de' => 'unterkunft',   'en' => 'hotels'],
        'handwerk'     => ['it' => 'artigiani',   'de' => 'handwerk',     'en' => 'trades'],
        'einzelhandel' => ['it' => 'negozi',      'de' => 'einzelhandel', 'en' => 'shops'],
        'beauty'       => ['it' => 'bellezza',    'de' => 'beauty',       'en' => 'beauty'],
        'gesundheit'   => ['it' => 'salute',      'de' => 'gesundheit',   'en' => 'health'],
        'fitness'      => ['it' => 'fitness',     'de' => 'fitness',      'en' => 'fitness'],
        'auto'         => ['it' => 'auto',        'de' => 'auto',         'en' => 'cars'],
        'immobilien'   => ['it' => 'immobiliare', 'de' => 'immobilien',   'en' => 'realestate'],
        'beratung'     => ['it' => 'consulenza',  'de' => 'beratung',     'en' => 'consulting'],
        'tourismus'    => ['it' => 'turismo',     'de' => 'tourismus',    'en' => 'tourism'],
        'lebensmittel' => ['it' => 'alimentari',  'de' => 'lebensmittel', 'en' => 'food'],
    ];

    /**
     * Wie die Empfehlungsseite für eine Branche aussieht: Textgruppe (Texte::SEITE_BRANCHEN) und Titelbild
     * (PartnerSeite::BILDER). Bild null = das Bild, das der Partner gewählt hat.
     */
    public const SEITE = [
        'gastronomie' => ['gastro', 'gastro'], 'unterkunft' => ['hotel', 'hotel'], 'handwerk' => ['handwerk', 'kueche'],
        'einzelhandel' => ['handel', 'mode'], 'beauty' => ['beauty', 'friseur'], 'gesundheit' => ['praxis', null], 'fitness' => ['fitness', null],
        'auto' => ['auto', 'auto'], 'immobilien' => ['immobilien', null], 'beratung' => ['beratung', null], 'tourismus' => ['tourismus', 'villa_garten'],
        'lebensmittel' => ['produkte', 'wein'],
    ];

    /** Kanal für p.php: „go“ oder „go-gastronomie“ (höchstens 15 Zeichen, Partner::kanal nimmt 20). */
    public static function kanal(?string $branche): string
    {
        return $branche !== null && isset(self::SLUGS[$branche]) ? 'go-' . $branche : 'go';
    }

    /** Aus dem Kanal die Branche — oder null (kein Kurzlink oder ohne Branche). */
    public static function ausKanal(?string $kanal): ?string
    {
        return $kanal !== null && preg_match('/^go-([a-z]{3,12})$/', $kanal, $m) && isset(self::SLUGS[$m[1]]) ? $m[1] : null;
    }

    /**
     * Ein Branchenwort aus der Adresse → [Branche, Sprache]. Sprache null, wenn das Wort in mehreren
     * Sprachen gleich ist (fitness, beauty, auto). Unbekannt → null.
     * @return ?array{0:string,1:?string}
     */
    public static function branche(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        foreach (self::SLUGS as $b => $je) {
            $l = array_keys($je, $slug, true);
            if ($l) { return [$b, count($l) === 1 ? $l[0] : null]; }
        }
        return null;
    }

    /** Einen Wunsch in Form bringen: klein, ä→ae, Leerzeichen → Bindestrich. Nichts wird erraten, was nicht dasteht. */
    public static function normal(string $wunsch): string
    {
        $s = mb_strtolower(trim($wunsch));
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'è' => 'e', 'é' => 'e', 'ê' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ç' => 'c', 'ñ' => 'n']);
        $s = (string) preg_replace('/[\s_.]+/', '-', $s);
        return trim((string) preg_replace('/-{2,}/', '-', $s), '-');
    }

    /**
     * Darf der Name sein? Fehler als Schlüssel (Texte::PARTNER_KAMPAGNE['kl_f'][…]), damit der Partnerbereich
     * dreisprachig antwortet und die Verwaltung deutsch.
     * @return array{name:?string, fehler:?string}  fehler: form | gesperrt | belegt
     */
    public static function pruefen(string $wunsch, ?int $partnerId = null): array
    {
        $n = self::normal($wunsch);
        if (!preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $n) || strlen($n) < 3 || strlen($n) > 30) { return ['name' => null, 'fehler' => 'form']; }
        if (in_array($n, self::GESPERRT, true) || self::branche($n) !== null) { return ['name' => null, 'fehler' => 'gesperrt']; }
        $wem = Db::one('SELECT partner_id, status FROM partner_kurznamen WHERE name = ?', [$n]);
        if ($wem && ((int) $wem['partner_id'] !== (int) $partnerId || $wem['status'] === 'gesperrt')) { return ['name' => null, 'fehler' => 'belegt']; }
        return ['name' => $n, 'fehler' => null];
    }

    /** Der jetzige Name eines Partners — oder null. */
    public static function name(int $partnerId): ?string
    {
        $n = Db::wert("SELECT name FROM partner_kurznamen WHERE partner_id = ? AND status = 'aktiv' ORDER BY created_at DESC LIMIT 1", [$partnerId], null);
        return is_string($n) && $n !== '' ? $n : null;
    }

    /** Alle Namen eines Partners, der jetzige zuerst. @return list<array{name:string,status:string,created_at:string}> */
    public static function liste(int $partnerId): array
    {
        return Db::all("SELECT name, status, created_at FROM partner_kurznamen WHERE partner_id = ? ORDER BY status = 'aktiv' DESC, created_at DESC", [$partnerId]);
    }

    /** Wie oft der Partner noch selbst ändern darf. */
    public static function rest(int $partnerId): int
    {
        return max(0, self::NAMEN_MAX - (int) Db::wert('SELECT COUNT(*) FROM partner_kurznamen WHERE partner_id = ?', [$partnerId], 0));
    }

    /**
     * Den Namen setzen. Der bisherige wird „alt“ und führt weiter zum Partner. $verwaltung = Uwe (ohne Obergrenze).
     * @return ?string Fehler-Schlüssel (form | gesperrt | belegt | zu_oft) oder null
     */
    public static function setzen(int $partnerId, string $wunsch, bool $verwaltung = false): ?string
    {
        $c = self::pruefen($wunsch, $partnerId);
        if ($c['fehler'] !== null) { return $c['fehler']; }
        $neu = (string) $c['name'];
        try {
            return Db::transaktion(static function () use ($partnerId, $neu, $verwaltung): ?string {
                // Sperrend lesen: Zwei Tipps auf „Speichern“ dürfen nicht zweimal zählen.
                $alle = Db::all('SELECT name, status FROM partner_kurznamen WHERE partner_id = ? FOR UPDATE', [$partnerId]);
                $jetzt = null;
                foreach ($alle as $z) { if ($z['status'] === 'aktiv') { $jetzt = (string) $z['name']; } }
                if ($jetzt === $neu) { return null; }
                $schon = array_values(array_filter($alle, static fn($z) => $z['name'] === $neu));
                if (!$schon && !$verwaltung && count($alle) >= self::NAMEN_MAX) { return 'zu_oft'; }
                Db::run("UPDATE partner_kurznamen SET status = 'alt', geaendert_am = NOW() WHERE partner_id = ? AND status = 'aktiv'", [$partnerId]);
                if ($schon) {   // ein eigener alter Name wird wieder der jetzige
                    Db::run("UPDATE partner_kurznamen SET status = 'aktiv', geaendert_am = NOW() WHERE name = ? AND partner_id = ? AND status = 'alt'", [$neu, $partnerId]);
                } else {
                    Db::run('INSERT INTO partner_kurznamen (name, partner_id, status) VALUES (?, ?, ?)', [$neu, $partnerId, 'aktiv']);
                }
                Events::pruefspur('partner_kurzname', 'partner', $partnerId, ['name' => $jetzt], ['name' => $neu, 'verwaltung' => $verwaltung]);
                return null;
            }, 3);
        } catch (Throwable $e) {
            if (Db::doppelt($e, 'PRIMARY')) { return 'belegt'; }   // ein anderer Partner war eine Zehntelsekunde schneller
            throw $e;
        }
    }

    /** Uwe sperrt einen Namen: Er führt nirgends mehr hin und wird nie neu vergeben. */
    public static function sperren(string $name): bool
    {
        $z = Db::one('SELECT partner_id, status FROM partner_kurznamen WHERE name = ?', [self::normal($name)]);
        if (!$z || $z['status'] === 'gesperrt') { return false; }
        Db::run("UPDATE partner_kurznamen SET status = 'gesperrt', geaendert_am = NOW() WHERE name = ?", [self::normal($name)]);
        Events::pruefspur('partner_kurzname_gesperrt', 'partner', (int) $z['partner_id'], ['name' => self::normal($name), 'status' => $z['status']], ['status' => 'gesperrt']);
        return true;
    }

    /** Name aus der Adresse → aktiver Partner — oder null (unbekannt, gesperrt, Partner pausiert). */
    public static function aufloesen(string $name): ?array
    {
        $n = strtolower(trim($name));
        if (!preg_match('/^[a-z0-9-]{3,30}$/', $n)) { return null; }
        $p = Db::one("SELECT p.* FROM partner_kurznamen k JOIN partner p ON p.id = k.partner_id
                       WHERE k.name = ? AND k.status IN ('aktiv','alt') AND p.status = 'aktiv'", [$n]);
        return $p ?: null;
    }

    /** Der Kurzlink, vollständig. Ohne Namen null. $branche = einer der zwölf (oder null für den Hauptlink). */
    public static function link(int $partnerId, ?string $branche, string $sprache): ?string
    {
        $n = self::name($partnerId);
        if ($n === null) { return null; }
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/go/' . $n;
        if ($branche === null || !isset(self::SLUGS[$branche])) { return $basis; }
        return $basis . '/' . (self::SLUGS[$branche][$sprache] ?? self::SLUGS[$branche]['it']);
    }

    /** Ein Vorschlag für den ersten Namen: der Vorname, sonst die Firma; belegt → mit Ziffer. */
    public static function vorschlag(array $p): string
    {
        $roh = trim((string) (preg_split('/\s+/', trim((string) $p['name']))[0] ?? ''));
        foreach ([$roh, (string) ($p['firma'] ?? '')] as $versuch) {
            $b = substr(self::normal($versuch), 0, 26);
            if (strlen($b) < 3) { continue; }
            for ($i = 1; $i <= 9; $i++) {
                $n = $i === 1 ? $b : $b . $i;
                if (self::pruefen($n, (int) $p['id'])['fehler'] === null) { return $n; }
            }
        }
        return '';
    }

    /**
     * Die Gestaltung der Empfehlungsseite für eine Branche: Überschrift, Text und drei Punkte der Branche,
     * dazu ihr Titelbild. Nur für diesen Aufruf — gespeichert wird nichts, die Seite des Partners bleibt.
     */
    public static function gestaltung(array $g, string $branche): array
    {
        [$gruppe, $bild] = self::SEITE[$branche] ?? [null, null];
        $t = $gruppe !== null ? (Texte::SEITE_BRANCHEN[$gruppe] ?? null) : null;
        if ($t === null) { return $g; }
        foreach (['it', 'de', 'en'] as $l) {
            foreach (['titel', 'lead', 'p1', 'p2', 'p3'] as $k) {
                if (isset($t[$l][$k])) { $g['texte'][$l][$k] = $t[$l][$k]; unset($g['auto'][$l][$k]); }
            }
        }
        if ($bild !== null) { $g['bild'] = $bild; }
        $g['ab'] = null;   // der Überschriften-Test gilt der eigenen Seite, nicht der Branchenseite
        return $g;
    }
}
