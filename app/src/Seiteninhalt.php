<?php
declare(strict_types=1);

/**
 * Was auf der alten Website schon steht (A1, 25.09.2026).
 *
 * WARUM ES DAS GIBT
 *
 * Wer eine Website hat, hat Telefon, E-Mail, Anschrift, Profile und meist
 * die P. IVA laengst einmal aufgeschrieben -- dort. Sie im Fragebogen noch
 * einmal zu verlangen, ist Arbeit, die schon getan ist. Hier wird gelesen,
 * nicht geurteilt: Das Urteil ueber die Seite macht Seitenblick.
 *
 * WORAUF SICH DAS LESEN STUETZT
 *
 * Zuerst auf das, was die Seite ausdruecklich ueber sich sagt: strukturierte
 * Daten (JSON-LD, schema.org), og:site_name, tel:- und mailto:-Links. Das
 * hat jemand mit Absicht so hinterlegt. Erst danach auf Muster im Text
 * (P. IVA, "90100 Palermo (PA)"), und die nur, wo ein Fehlgriff auffiele und
 * nichts kostet: Der Kunde sieht jede Vorbelegung und bestaetigt sie.
 *
 * WAS BEWUSST NICHT PASSIERT
 *
 * Keine Texte uebernehmen (ausser der Kurzbeschreibung, die die Seite selbst
 * als solche ausweist), keine Bilder, keine Farben raten. Das waere Inhalt
 * und Gestaltung -- darueber entscheidet der Kunde, nicht ein Parser.
 */
final class Seiteninhalt
{
    /** Hosts, die als Social-Profil zaehlen -- mit Teilen-Links, die keine sind. */
    private const PROFILE = ['instagram.com', 'facebook.com', 'tiktok.com', 'linkedin.com',
                             'youtube.com', 'x.com', 'twitter.com', 'tripadvisor.it', 'tripadvisor.com',
                             'tripadvisor.de', 'pinterest.com', 'pinterest.it'];

    /**
     * @param array<string,string> $unterseiten Adresse => HTML
     * @return array<string,string> gefundene Angaben (nur, was wirklich da ist)
     */
    public static function lesen(string $html, string $url, array $unterseiten = []): array
    {
        $alle = array_merge([$url => $html], $unterseiten);
        $host = mb_strtolower(preg_replace('~^www\.~', '', (string) parse_url($url, PHP_URL_HOST)) ?? '');
        $aus  = [];

        /* 1. Strukturierte Daten -- was die Seite ausdruecklich ueber sich sagt. */
        $ld = [];
        foreach ($alle as $h) { $ld = array_merge($ld, self::jsonLd($h)); }
        $firma = null;
        foreach ($ld as $o) {
            if (isset($o['address']) || isset($o['telephone']) || isset($o['vatID'])) { $firma = $o; break; }
        }
        if ($firma) {
            $aus['name']        = self::text($firma['name'] ?? null);
            $aus['firmierung']  = self::text($firma['legalName'] ?? null);
            $aus['telefon']     = self::text($firma['telephone'] ?? null);
            $aus['email']       = self::text(preg_replace('~^mailto:~i', '', (string) ($firma['email'] ?? '')));
            $aus['beschreibung']= self::text($firma['description'] ?? null);
            $aus['piva']        = self::text($firma['vatID'] ?? ($firma['taxID'] ?? null));
            $adr = $firma['address'] ?? null;
            if (is_array($adr) && isset($adr[0])) { $adr = $adr[0]; }
            if (is_array($adr)) {
                $aus['strasse'] = self::text($adr['streetAddress'] ?? null);
                $aus['plz']     = self::text($adr['postalCode'] ?? null);
                $aus['ort']     = self::text($adr['addressLocality'] ?? null);
            } elseif (is_string($adr)) {
                $aus['anschrift'] = self::text($adr);
            }
            $same = (array) ($firma['sameAs'] ?? []);
            $aus['social'] = implode(', ', array_filter(array_map(
                static fn($u) => is_string($u) && self::istProfilLink($u) ? $u : null, $same)));
        }

        /* 2. Was im Kopf steht. */
        if (empty($aus['name']) && preg_match('~<meta[^>]+property=["\']og:site_name["\'][^>]*content=["\']([^"\']{2,80})~i', $html, $m)) {
            $aus['name'] = self::text($m[1]);
        }
        if (empty($aus['name']) && preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
            /* "Trattoria Sole | Cucina siciliana a Palermo" -- der Name ist
               das kuerzere Stueck, meist das erste. */
            $teile = array_values(array_filter(array_map('trim',
                preg_split('~\s+[|–—·•:-]\s+~u', self::text($m[1]) ?? '') ?: [])));
            if ($teile && mb_strlen($teile[0]) >= 2 && mb_strlen($teile[0]) <= 60
                && !preg_match('~^(home|homepage|startseite|benvenuti|willkommen|welcome)$~i', $teile[0])) {
                $aus['name'] = $teile[0];
            }
        }
        if (empty($aus['beschreibung'])
            && preg_match('~<meta[^>]+name=["\']description["\'][^>]*content=["\']([^"\']{40,400})~i', $html, $m)) {
            $aus['beschreibung'] = self::text($m[1]);
        }

        /* 3. Links, die jemand mit Absicht gesetzt hat. */
        $zusammen = implode("\n", $alle);
        if (empty($aus['telefon']) && preg_match('~href=["\']tel:([+\d][\d\s./()-]{5,24})["\']~i', $zusammen, $m)) {
            $aus['telefon'] = self::text(rawurldecode($m[1]));
        }
        if (empty($aus['email']) && preg_match_all('~href=["\']mailto:([^"\'?]+)~i', $zusammen, $m)) {
            /* Die Adresse der eigenen Domain zuerst -- ein mailto an den
               Webdesigner im Fuss ist nicht die Adresse des Betriebs. */
            $mails = array_values(array_unique(array_map(static fn($x) => mb_strtolower(trim(rawurldecode($x))), $m[1])));
            $mails = array_values(array_filter($mails, static fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
            usort($mails, static fn($a, $b) => (int) !str_ends_with($a, '@' . $host) <=> (int) !str_ends_with($b, '@' . $host));
            if ($mails) { $aus['email'] = $mails[0]; }
        }
        if (empty($aus['social']) && preg_match_all('~href=["\'](https?://[^"\']+)["\']~i', $zusammen, $m)) {
            $je = [];
            foreach ($m[1] as $u) {
                if (!self::istProfilLink($u)) { continue; }
                $h = preg_replace('~^(www\.|m\.|it-it\.|de-de\.)~', '', mb_strtolower((string) parse_url($u, PHP_URL_HOST))) ?? '';
                $je[$h] ??= html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $aus['social'] = implode(', ', array_slice(array_values($je), 0, 5));
        }

        /* 4. Muster im Text -- nur, was sich selbst pruefen laesst. */
        $text = html_entity_decode(strip_tags(preg_replace('~<(script|style)\b.*?</\1>~is', ' ', $zusammen) ?? ''),
                                   ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('~\s+~u', ' ', $text) ?? '';
        if (empty($aus['piva'])
            && preg_match_all('~(?:P\.?\s?IVA|Partita\s+IVA|VAT(?:\s+(?:No|n)\.?)?|C\.?F\.?\s*/\s*P\.?\s?IVA)[\s.:n°#-]*(?:IT)?\s?(\d{11})~iu', $text, $m)) {
            require_once __DIR__ . '/Vies.php';
            foreach ($m[1] as $nr) { if (Vies::italienischGueltig($nr)) { $aus['piva'] = 'IT' . $nr; break; } }
        }
        if (empty($aus['piva']) && preg_match('~USt[.-]?\s?Id[.-]?\s?Nr\.?[\s:]*?(DE\s?\d{9})~iu', $text, $m)) {
            $aus['piva'] = str_replace(' ', '', $m[1]);
        }
        if (empty($aus['ort']) && preg_match('~\b(\d{5})\s+([A-ZÀ-Ý][\p{L}\' ]{2,30}?)\s*\(([A-Z]{2})\)~u', $text, $m)) {
            $aus['plz'] ??= $m[1];
            $aus['ort'] = trim($m[2]);
        }

        return array_filter(array_map(static fn($v) => is_string($v) ? trim($v) : '', $aus),
                            static fn($v) => $v !== '');
    }

    /** @return list<array<string,mixed>> alle Objekte aus allen JSON-LD-Bloecken, flach */
    private static function jsonLd(string $html): array
    {
        $raus = [];
        if (!preg_match_all('~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $m)) {
            return $raus;
        }
        $sammeln = static function ($d) use (&$sammeln, &$raus): void {
            if (!is_array($d)) { return; }
            if (isset($d['@graph'])) { $sammeln($d['@graph']); }
            if (array_is_list($d)) { foreach ($d as $x) { $sammeln($x); } return; }
            if (isset($d['@type'])) { $raus[] = $d; }
            foreach (['publisher', 'provider', 'author', 'mainEntity', 'about'] as $k) {
                if (isset($d[$k]) && is_array($d[$k])) { $sammeln($d[$k]); }
            }
        };
        foreach ($m[1] as $block) { $sammeln(json_decode(trim($block), true)); }
        return $raus;
    }

    private static function istProfilLink(string $u): bool
    {
        $h = preg_replace('~^(www\.|m\.|[a-z]{2}-[a-z]{2}\.)~', '', mb_strtolower((string) parse_url($u, PHP_URL_HOST))) ?? '';
        if (!in_array($h, self::PROFILE, true)) { return false; }
        /* Teilen-Knoepfe und eingebettete Beitraege sind kein Profil. */
        $pfad = (string) parse_url($u, PHP_URL_PATH);
        return trim($pfad, '/') !== ''
            && !preg_match('~(sharer|share|intent|dialog|plugins|embed|/p/|/reel/|/watch|/posts/|/status/)~i', $u);
    }

    private static function text(mixed $w): ?string
    {
        if (!is_string($w) && !is_numeric($w)) { return null; }
        $t = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string) $w), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        return $t === '' ? null : mb_substr($t, 0, 400);
    }
}
