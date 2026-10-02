<?php
declare(strict_types=1);

require_once __DIR__ . '/MkInhalt.php';
require_once __DIR__ . '/MkMedium.php';
require_once __DIR__ . '/MkVeroeffentlichen.php';

/* ==========================================================================
   MkPartnerBeitraege.php — fertige Beiträge aus dem Marketing-Studio für die
   Partner (Marketing-Studio 8, 01.10.2026, Uwe: „ja“ zu S3 — „fertiges
   Partner-Paket mit Texten, Bildern und QR-Code“).

   Ein freigegebener Beitrag kann mit einem Haken „Partnern zum Teilen“
   gegeben werden. Im Partnerportal (Reiter Werben) steht er dann mit Bild
   und Text — und mit dem Link DES PARTNERS statt dem Kampagnenlink. So
   bleibt die Provision beim Partner, und die Verwaltung sieht den Klick in
   seiner Spur (/p/CODE/beitrag).

   Das Bild kommt über m.php (Zufallsschlüssel je Medium, nur gewählte
   Medien freigegebener Inhalte) — dieselbe Tür wie für Meta und Telegram.
   ========================================================================== */

final class MkPartnerBeitraege
{
    /** Kanal im Partnerlink — so sieht man in der Auswertung, was aus den Vecom-Beiträgen kam. */
    public const KANAL = 'beitrag';
    public const HOECHSTENS = 8;

    public const TEXTE = [
        'titel'   => ['it' => 'Post pronti di Vecom', 'de' => 'Fertige Beiträge von Vecom', 'en' => 'Ready-made posts from Vecom'],
        'text'    => ['it' => 'Immagine e testo pronti, con il suo link già dentro: li pubblichi o li inoltri così. Ogni clic conta per Lei.',
                      'de' => 'Bild und Text fertig, Ihr Link steckt schon drin: so posten oder weiterleiten. Jeder Klick zählt für Sie.',
                      'en' => 'Image and text ready, your link already inside: post or forward them as they are. Every click counts for you.'],
        'bild'    => ['it' => 'Scarica immagine', 'de' => 'Bild laden', 'en' => 'Download image'],
        'whatsapp' => ['it' => 'Su WhatsApp', 'de' => 'Per WhatsApp', 'en' => 'On WhatsApp'],
        'facebook' => ['it' => 'Su Facebook', 'de' => 'Auf Facebook', 'en' => 'On Facebook'],
    ];

    public static function t(string $k, string $sprache): string
    {
        return (string) (self::TEXTE[$k][$sprache] ?? self::TEXTE[$k]['de'] ?? '');
    }

    /** Haken setzen oder entfernen — nur für freigegebene oder veröffentlichte Inhalte. */
    public static function setzen(int $id, bool $an): ?string
    {
        $x = MkInhalt::laden($id);
        if ($x === null) { return 'Inhalt nicht gefunden.'; }
        if ($an && !in_array($x['status'], ['freigegeben', 'veroeffentlicht'], true)) { return 'Erst freigeben — dann kann er an die Partner.'; }
        if ($an && $x['art'] === 'bezahlt') { return 'Anzeigen bleiben bei Vecom — Partner bekommen nur organische Beiträge.'; }
        Db::run('UPDATE mk_inhalte SET partner = ? WHERE id = ?', [$an ? 1 : 0, $id]);
        Events::pruefspur('inhalt_partner', 'mk_inhalte', $id, ['partner' => (int) ($x['partner'] ?? 0)], ['partner' => $an ? 1 : 0]);
        return null;
    }

    /**
     * Die Beiträge für einen Partner: nur in seiner Sprache, die neuesten zuerst.
     * (02.10.2026: vorher kamen nach den eigenen alle übrigen -- ein deutscher
     * Partner bekam italienische Posts zum Teilen.) Mit Bild-Adresse und Text samt Partnerlink.
     * @return list<array{id:int, titel:string, land:string, bild:?string, text:string, whatsapp:string, facebook:string}>
     */
    public static function fuerPartner(array $p, string $sprache): array
    {
        require_once __DIR__ . '/PartnerWerbung.php';
        $link = PartnerWerbung::link($p, self::KANAL);
        $zeilen = Db::all("SELECT * FROM mk_inhalte WHERE partner = 1 AND status IN ('freigegeben', 'veroeffentlicht') AND art = 'organisch'
                              AND sprache = ? ORDER BY id DESC LIMIT " . self::HOECHSTENS, [$sprache]);
        $aus = [];
        foreach ($zeilen as $z) {
            $x = MkInhalt::laden((int) $z['id']);
            if ($x === null) { continue; }
            $m = MkMedium::gewaehlt((int) $x['id'], 'bild');
            $text = self::text($x, $link);
            $aus[] = ['id' => (int) $x['id'], 'titel' => (string) $x['titel'], 'land' => (string) $x['land'],
                      'bild' => $m ? MkVeroeffentlichen::oeffentlich($m) . '&f=jpg' : null, 'text' => $text,
                      'whatsapp' => 'https://wa.me/?text=' . rawurlencode($text),
                      'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($link)];
        }
        return $aus;
    }

    /** Der Beitragstext mit dem Partnerlink statt dem Vecom-Kampagnenlink (oder ohne „Link in Bio“). */
    public static function text(array $x, string $partnerLink): string
    {
        $t = MkInhalt::kopiertext($x);
        $eigen = MkInhalt::link($x);
        if ($eigen) { $t = str_replace($eigen, $partnerLink, $t); }
        /* „Link in Bio“ gilt für Vecoms Profil, nicht für den Partner — dort steht jetzt sein Link selbst. */
        $t = (string) preg_replace('/\b(link in bio|link nella bio|link in der bio)\b/iu', $partnerLink, $t, 1);
        $t = trim((string) preg_replace('/\s*\(?\b(link in bio|link nella bio|link in der bio)\b\)?[.!]?/iu', '', $t));
        if (!str_contains($t, $partnerLink)) { $t .= "\n\n" . $partnerLink; }
        return $t;
    }
}
