<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkInhalt.php';
require_once __DIR__ . '/MkVeroeffentlichen.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   MkTelegramSpiegel.php — freigegebene Beiträge auch in den Telegram-Kanal
   (02.10.2026, Uwe: „Beiträge spiegeln“ als Hebel für die ersten 100
   Abonnenten).

   WARUM
   Der Kanal hatte nach drei Tagen fünf Abonnenten und drei Beiträge. Wer
   über einen Link kommt und einen leeren Kanal sieht, geht wieder. Was auf
   Instagram oder Facebook freigegeben ist, ist gut genug für den Kanal —
   also bekommt der Kanal jeden freigegebenen Beitrag und jedes Karussell
   als eigenen Telegram-Beitrag, am nächsten freien Sendeplatz.

   WAS NICHT
   Keine neue Freigabe nötig, weil Uwe genau diesen Text schon freigegeben
   hat — aber nichts, was er nicht freigegeben hat. Kein Reel (ohne Video
   wäre es nur ein Drehbuch), keine Story, keine Anzeige. Derselbe Text
   (Facebook und Instagram aus einem Auftrag) geht nur einmal raus.
   ========================================================================== */
final class MkTelegramSpiegel
{
    public const FORMATE = ['beitrag', 'karussell'];
    public const QUELLEN = ['facebook', 'instagram'];

    /** Text für Telegram: Beitrag wie er ist, Karussell als Aufhänger plus Folien. */
    public static function text(array $x): string
    {
        $f = $x['f'];
        if ($x['format'] === 'karussell') {
            $teile = [trim((string) ($f['hook'] ?? ''))];
            foreach ((array) ($f['folien'] ?? []) as $fo) {
                $z = trim(trim((string) ($fo['titel'] ?? '')) . ' — ' . trim((string) ($fo['text'] ?? '')), " —");
                if ($z !== '') { $teile[] = '• ' . $z; }
            }
            if (trim((string) ($f['text'] ?? '')) !== '') { $teile[] = trim((string) $f['text']); }
            return trim(implode("\n\n", array_filter($teile, static fn($t) => $t !== '')));
        }
        return trim((string) ($f['text'] ?? ''));
    }

    /** Schon gespiegelt (dieser Inhalt oder derselbe Text in den letzten 30 Tagen)? */
    private static function schonDa(int $quelleId, string $text): bool
    {
        foreach (Db::all("SELECT felder FROM mk_inhalte WHERE plattform = 'telegram' AND created_at >= NOW() - INTERVAL 30 DAY") as $z) {
            $f = json_decode((string) $z['felder'], true) ?: [];
            if ((int) ($f['spiegel_von'] ?? 0) === $quelleId || trim((string) ($f['text'] ?? '')) === $text) { return true; }
        }
        return false;
    }

    /** Einen freigegebenen Beitrag spiegeln. @return ?int neue Inhalts-ID (null = nicht gespiegelt) */
    public static function spiegeln(int $quelleId): ?int
    {
        $x = MkInhalt::laden($quelleId);
        if ($x === null || !in_array($x['status'], ['freigegeben', 'veroeffentlicht'], true)) { return null; }
        if (!in_array($x['plattform'], self::QUELLEN, true) || !in_array($x['format'], self::FORMATE, true) || $x['art'] !== 'organisch') { return null; }
        if (!empty($x['f']['kanal_werbung'])) { return null; }   // Werbung für den Kanal gehört nicht in den Kanal
        if (Telegram::kanal()['id'] === '' || !Telegram::bereit()) { return null; }
        $text = self::text($x);
        if (mb_strlen($text) < 20 || self::schonDa($quelleId, $text)) { return null; }
        $felder = ['text' => $text, 'spiegel_von' => $quelleId];
        if (trim((string) ($x['f']['knopf'] ?? '')) !== '') { $felder['knopf'] = $x['f']['knopf']; }
        $neu = (int) Db::insert('mk_inhalte', [
            'auftrag_id' => $x['auftrag_id'] ?? null, 'zielgruppe_id' => $x['zielgruppe_id'] ?? null, 'kunde_id' => $x['kunde_id'] ?? null,
            'branche' => $x['branche'], 'land' => $x['land'], 'sprache' => $x['sprache'], 'art' => 'organisch', 'format' => 'telegram', 'plattform' => 'telegram',
            'titel' => mb_substr('Telegram · ' . (string) $x['titel'], 0, 190), 'felder' => json_encode($felder, JSON_UNESCAPED_UNICODE),
            'uebersetzung' => $x['format'] === 'beitrag' ? ($x['uebersetzung'] ?? null) : null,
            'kampagne_id' => $x['kampagne_id'] ?? null, 'status' => 'entwurf',
        ]);
        /* Das gewählte Bild geht mit — dieselbe Datei, ein eigener Eintrag (eigene öffentliche Adresse). */
        $bild = Db::one("SELECT * FROM mk_medien WHERE inhalt_id = ? AND art = 'bild' AND status = 'gewaehlt' ORDER BY id DESC LIMIT 1", [$quelleId]);
        if ($bild) {
            Db::insert('mk_medien', ['inhalt_id' => $neu, 'auftrag_id' => $bild['auftrag_id'], 'art' => 'bild', 'datei' => $bild['datei'], 'mime' => $bild['mime'],
                'bytes' => $bild['bytes'], 'sha256' => $bild['sha256'], 'format' => $bild['format'], 'modell' => $bild['modell'], 'credits' => 0,
                'prompt' => $bild['prompt'], 'status' => 'gewaehlt']);
        }
        if (MkInhalt::freigeben($neu) !== null) { Db::run("DELETE FROM mk_medien WHERE inhalt_id = ?", [$neu]); Db::run('DELETE FROM mk_inhalte WHERE id = ?', [$neu]); return null; }
        MkVeroeffentlichen::planen($neu, MkVeroeffentlichen::naechsterSlot('telegram'));
        Events::pruefspur('inhalt_gespiegelt', 'mk_inhalte', $neu, [], ['von' => $quelleId, 'plattform' => 'telegram']);
        return $neu;
    }

    /** Täglich: alles Freigegebene der letzten Tage, das noch keinen Spiegel hat. @return int Anzahl */
    public static function nachholen(): int
    {
        if (Telegram::kanal()['id'] === '' || !Telegram::bereit()) { return 0; }
        $n = 0;
        foreach (Db::all("SELECT id FROM mk_inhalte WHERE plattform IN ('facebook', 'instagram') AND format IN ('beitrag', 'karussell') AND art = 'organisch'
                           AND (status = 'freigegeben' OR (status = 'veroeffentlicht' AND veroeffentlicht_am >= NOW() - INTERVAL 3 DAY)) ORDER BY id LIMIT 20") as $z) {
            try { if (self::spiegeln((int) $z['id']) !== null) { $n++; } } catch (Throwable $e) { /* der nächste Lauf versucht es wieder */ }
        }
        return $n;
    }
}
