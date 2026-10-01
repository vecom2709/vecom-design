<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/MetaSeite.php';
require_once __DIR__ . '/AkquiseGate.php';

/* ==========================================================================
   MkKommentar.php — „Kommentiere STICHWORT“ → automatische Nachricht
   (01.10.2026, Uwe: Ja zu S1).

   Ein Reel oder Beitrag auf Instagram/Facebook sagt „Kommentiere CHECK“
   (it: „Commenta SITO“). Wer das Stichwort kommentiert, bekommt genau EINE
   private Nachricht mit dem Link zum kostenlosen Website-Check bzw. zur
   Demo-Vorschau. Erlaubt, weil der Betrieb selbst den Anstoß gibt
   (Kommentar = Anfrage); Meta erlaubt eine private Antwort je Kommentar
   innerhalb von 7 Tagen. Kein Nachfassen, keine weiteren Nachrichten.

   Meta meldet Kommentare an wa-webhook.php (dieselbe App wie WhatsApp und
   das Werbeformular): Seite → Feld „feed“, Instagram → Feld „comments“.
   Der Link ist eine eigene Kampagne je Land (Code km-it / km-de), damit
   Besuche und Anfragen aus den Antworten gezählt werden.
   ========================================================================== */
final class MkKommentar
{
    /** Stichwörter, die immer gelten (zusätzlich zu denen aus den Zielgruppen). Wort => Land. */
    public const GRUND = ['CHECK' => 'DE', 'WEBSITE' => 'DE', 'ANALYSE' => 'DE', 'SITO' => 'IT', 'ANALISI' => 'IT', 'DEMO' => ''];

    public const TEXTE = [
        'IT' => "Grazie del commento! Ecco l'analisi gratuita del suo sito — dodici punti con semaforo, in pochi secondi, senza registrazione:\n{link}\n\nNon ha ancora un sito? Dalla stessa pagina vede subito il prezzo, e se vuole le prepariamo un'anteprima gratuita della nuova home page.",
        'DE' => "Danke für Ihren Kommentar! Hier ist der kostenlose Website-Check — zwölf Punkte als Ampel, in Sekunden, ohne Anmeldung:\n{link}\n\nNoch keine Website? Auf derselben Seite sehen Sie gleich den Preis, und auf Wunsch bereiten wir Ihnen eine kostenlose Vorschau der neuen Startseite vor.",
    ];

    public static function an(): bool
    {
        return (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'mk_kommentar_an'", [], '') === '1';
    }

    public static function schalten(bool $an): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('mk_kommentar_an', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$an ? '1' : '0']);
        Events::pruefspur('kommentar_automatik', 'settings', 0, [], ['an' => $an]);
    }

    /** Alle gültigen Stichwörter: Grundliste plus die Kundenwege der freigegebenen Zielgruppen. @return array<string,string> Wort => Land ('' = beide) */
    public static function stichworte(): array
    {
        $aus = self::GRUND;
        try {
            foreach (Db::all("SELECT land, profil FROM mk_zielgruppen WHERE status = 'freigegeben'") as $z) {
                $p = json_decode((string) $z['profil'], true) ?: [];
                $w = (string) ($p['kundenweg']['stichwort'] ?? '');
                if ($w !== '' && preg_match('/^[\p{Lu}\p{N}]{2,20}$/u', $w)) { $aus[$w] = isset($aus[$w]) && $aus[$w] !== (string) $z['land'] ? '' : (string) $z['land']; }
            }
        } catch (Throwable $e) { }
        return $aus;
    }

    /** Welches Stichwort steht im Kommentar (als eigenes Wort)? @return ?array{0:string,1:string} [Wort, Land] */
    public static function treffer(string $text): ?array
    {
        $gross = mb_strtoupper($text);
        foreach (self::stichworte() as $w => $land) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($w, '/') . '(?![\p{L}\p{N}])/u', $gross)) { return [$w, $land]; }
        }
        return null;
    }

    /** Die Kampagne für die Antworten eines Landes (einmal angelegt). */
    public static function link(string $land): string
    {
        require_once __DIR__ . '/MkKampagne.php';
        $code = $land === 'DE' ? 'km-de' : 'km-it';
        $k = Db::one('SELECT * FROM mk_kampagnen WHERE code = ?', [$code]);
        if (!$k) {
            $id = MkKampagne::anlegen(['name' => 'Kommentar → Nachricht (' . ($land === 'DE' ? 'Deutschland' : 'Italien') . ')', 'plattform' => 'instagram', 'code' => $code,
                'ziel' => '/analisi.php' . ($land === 'DE' ? '?lang=de' : ''), 'ziel_art' => 'leads', 'land' => $land === 'DE' ? 'DE' : 'IT', 'notiz' => 'Automatische Antwort auf „Kommentiere STICHWORT“ (S1)']);
            $k = is_int($id) ? MkKampagne::laden($id) : null;
        }
        return $k ? MkKampagne::link($k) : 'https://vecom-design.it/analisi.php' . ($land === 'DE' ? '?lang=de' : '');
    }

    /** Sprache aus dem Text raten, wenn das Stichwort für beide gilt (DEMO). */
    public static function landAusText(string $text, string $land): string
    {
        if ($land !== '') { return $land; }
        return preg_match('/\b(der|die|das|und|ich|bitte|danke|gerne|ja)\b/iu', $text) ? 'DE' : 'IT';
    }

    /**
     * Meldung aus dem Webhook. Seite: object=page, field=feed, item=comment, verb=add.
     * Instagram: object=instagram, field=comments. @return int beantwortete Kommentare
     */
    public static function verarbeiten(array $nutzlast): int
    {
        if (!self::an()) { return 0; }
        $obj = (string) ($nutzlast['object'] ?? '');
        $e = MetaSeite::einstellungen();
        $n = 0;
        foreach ((array) ($nutzlast['entry'] ?? []) as $eintrag) {
            foreach ((array) ($eintrag['changes'] ?? []) as $ae) {
                $v = (array) ($ae['value'] ?? []);
                if ($obj === 'page' && ($ae['field'] ?? '') === 'feed' && ($v['item'] ?? '') === 'comment' && ($v['verb'] ?? '') === 'add') {
                    if ((string) ($v['from']['id'] ?? '') === $e['seite_id']) { continue; }   // eigene Antworten nie beantworten
                    $n += self::antworten('facebook', (string) ($v['comment_id'] ?? ''), (string) ($v['message'] ?? ''), (string) ($v['post_id'] ?? ''));
                } elseif ($obj === 'instagram' && ($ae['field'] ?? '') === 'comments') {
                    if ((string) ($v['from']['id'] ?? '') === $e['ig_id']) { continue; }
                    $n += self::antworten('instagram', (string) ($v['id'] ?? ''), (string) ($v['text'] ?? ''), (string) ($v['media']['id'] ?? ''));
                }
            }
        }
        return $n;
    }

    /** Genau eine private Antwort je Kommentar. @return int 1 wenn geantwortet */
    public static function antworten(string $plattform, string $kommentarId, string $text, string $beitrag = ''): int
    {
        $kommentarId = preg_replace('~[^0-9_]~', '', $kommentarId) ?? '';
        if ($kommentarId === '' || mb_strlen($text) > 2000) { return 0; }
        $t = self::treffer($text);
        if ($t === null) { return 0; }
        [$wort, $land] = $t;
        $land = self::landAusText($text, $land);
        try { $zeile = (int) Db::insert('mk_kommentare', ['plattform' => $plattform, 'kommentar_id' => $kommentarId, 'beitrag_id' => mb_substr($beitrag, 0, 64), 'stichwort' => $wort, 'land' => $land, 'status' => 'neu']); }
        catch (Throwable $x) { return 0; }   // schon beantwortet (eindeutiger Schlüssel) — nie zweimal
        $e = MetaSeite::einstellungen();
        $absender = $plattform === 'instagram' ? $e['ig_id'] : $e['seite_id'];
        if ($absender === '') { Db::update('mk_kommentare', $zeile, ['status' => 'fehler', 'grund' => 'Seite bzw. Instagram-Konto nicht eingerichtet.']); return 0; }
        $nachricht = strtr(self::TEXTE[$land] ?? self::TEXTE['IT'], ['{link}' => self::link($land)]);
        $r = MetaSeite::graph('POST', $absender . '/messages', ['recipient' => ['comment_id' => $kommentarId], 'message' => ['text' => $nachricht]]);
        $ok = ($r['status'] ?? 0) === 200 && !empty($r['json']['message_id'] ?? $r['json']['recipient_id'] ?? null);
        Db::update('mk_kommentare', $zeile, ['status' => $ok ? 'beantwortet' : 'fehler', 'grund' => $ok ? null : MetaSeite::fehler($r)]);
        if (!$ok) {
            try { Events::melden('kommentar_fehler', 'Kommentar-Antwort gescheitert (' . $plattform . ')', 'warnung', MetaSeite::fehler($r) . ' — Berechtigungen der Meta-App prüfen (Seite: pages_messaging; Instagram: instagram_manage_messages, instagram_manage_comments).', 'kanaele#kommentar'); } catch (Throwable $y) { }
        }
        return $ok ? 1 : 0;
    }

    /** Abo der Seite um Kommentare erweitern (Werbeformular bleibt). */
    public static function abonnieren(): array
    {
        if (!MetaSeite::bereit()) { return ['ok' => false, 'grund' => 'Facebook-Seite ist noch nicht eingerichtet (Akquise › Regeln › Facebook-Seite).']; }
        $r = MetaSeite::graph('POST', MetaSeite::einstellungen()['seite_id'] . '/subscribed_apps?subscribed_fields=leadgen,feed');
        return !empty($r['json']['success']) ? ['ok' => true, 'grund' => null] : ['ok' => false, 'grund' => MetaSeite::fehler($r)];
    }

    /** Zahlen für die Verwaltung: beantwortet / Fehler in 30 Tagen. */
    public static function zahlen(): array
    {
        $aus = ['beantwortet' => 0, 'fehler' => 0, 'letzte' => []];
        try {
            foreach (Db::all("SELECT status, COUNT(*) AS n FROM mk_kommentare WHERE created_at >= NOW() - INTERVAL 30 DAY GROUP BY status") as $r) {
                if (isset($aus[$r['status']])) { $aus[$r['status']] = (int) $r['n']; }
            }
            $aus['letzte'] = Db::all('SELECT plattform, stichwort, land, status, grund, created_at FROM mk_kommentare ORDER BY id DESC LIMIT 5');
        } catch (Throwable $e) { }
        return $aus;
    }
}
