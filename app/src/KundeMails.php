<?php
declare(strict_types=1);

/**
 * Alle E-Mails an einen Kunden — für seine Akte (07.10.2026, Uwe: „wichtig in den jeweiligen
 * Kundenakten sollen auch alle versendeten E-Mails angezeigt werden“).
 *
 * Woher:
 *  - mails: alles, was das System über Brevo schickt (oder zurückhält / nicht schicken konnte).
 *    Zum Kunden gehört eine Zeile, wenn sie seine Kennung trägt, an einem seiner Projekte oder
 *    Bestellungen hängt oder an seine Adresse ging (ältere Zeilen ohne Kennung).
 *  - akq_versand: Akquise-Mails aus der Zeit, bevor er Kunde war. Über Brevo verschickte stehen
 *    mit Inhalt in mails (mail_id); per mailto geöffnete hat Uwes Mailprogramm verschickt — die
 *    erscheinen als „im Mailprogramm geöffnet“, ohne Inhalt, den kennen wir nicht.
 *
 * Inhalt (Text, Briefbogen, Namen der Anhänge) gibt es ab Migration 210; ältere Zeilen zeigen
 * Betreff, Empfänger und Stand.
 */
final class KundeMails
{
    public const STATUS = [
        'gesendet' => ['Gesendet', 'gut'], 'fehler' => ['Nicht gesendet', 'schlecht'], 'gehalten' => ['Zurückgehalten (Not-Aus)', 'warnung'],
        'von_hand' => ['Im Mailprogramm geöffnet', ''],
    ];

    /** @return list<array{quelle:string,id:int,zeit:string,betreff:string,an:string,status:string,fehler:string,anlass:string,anhaenge:list<array{name:string,groesse:int}>,inhalt:bool}> */
    public static function liste(int $kundeId, int $grenze = 200): array
    {
        $k = Db::one('SELECT id, email FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { return []; }
        $mail = mb_strtolower(trim((string) ($k['email'] ?? '')));
        $grenze = max(1, min(500, $grenze));
        $mitInhalt = self::spalteDa();
        $felder = 'id, anlass, empfaenger, betreff, status, fehler, created_at' . ($mitInhalt ? ', anhaenge, (inhalt IS NOT NULL OR html IS NOT NULL) AS hat_inhalt' : '')
            . (self::spurDa() ? ', ausloeser, ausloeser_ref, ausloeser_id, ausloeser_wer' : '');
        $zeilen = Db::all(
            "SELECT $felder FROM mails
              WHERE customer_id = ?
                 OR project_id IN (SELECT id FROM projects WHERE customer_id = ?)
                 OR order_id   IN (SELECT id FROM orders   WHERE customer_id = ?)
                 OR (customer_id IS NULL AND ? <> '' AND LOWER(empfaenger) = ?)
              ORDER BY id DESC LIMIT $grenze",
            [$kundeId, $kundeId, $kundeId, $mail, $mail]);
        $aus = [];
        $gesehen = [];
        foreach ($zeilen as $z) {
            $gesehen[(int) $z['id']] = true;
            $aus[] = self::zeile($z);
        }
        /* Akquise: über die verknüpfte Firma. Tabelle fehlt in alten Ständen — dann eben nicht. */
        try {
            $akq = Db::all(
                "SELECT v.id, v.kanal, v.an, v.status, v.grund, v.mail_id, v.actor, v.created_at FROM akq_versand v
                   JOIN akq_firmen f ON f.id = v.firma_id
                  WHERE f.customer_id = ? AND v.kanal = 'email' AND v.status IN ('gesendet','von_hand','fehler')
                  ORDER BY v.id DESC LIMIT $grenze", [$kundeId]);
            foreach ($akq as $v) {
                $mid = (int) ($v['mail_id'] ?? 0);
                if ($mid > 0) {
                    if (isset($gesehen[$mid])) { continue; }
                    $m = Db::one("SELECT $felder FROM mails WHERE id = ?", [$mid]);
                    if ($m) { $gesehen[$mid] = true; $aus[] = self::zeile($m); continue; }
                }
                $aus[] = ['quelle' => 'akq', 'id' => (int) $v['id'], 'zeit' => (string) $v['created_at'], 'betreff' => 'Akquise-Mail',
                    'an' => (string) ($v['an'] ?? ''), 'status' => (string) $v['status'], 'fehler' => (string) ($v['grund'] ?? ''),
                    'anlass' => 'akquise', 'anhaenge' => [], 'inhalt' => false, 'wer' => 'Akquise' . (!empty($v['actor']) ? ' · ' . (string) $v['actor'] : ''), 'freigabe' => 0];
            }
        } catch (Throwable $e) { /* ohne Akquise-Tabellen */ }
        usort($aus, static fn($a, $b) => strcmp($b['zeit'], $a['zeit']) ?: $b['id'] <=> $a['id']);
        return array_slice($aus, 0, $grenze);
    }

    /** Eine Mail mit Inhalt — nur, wenn sie wirklich zu diesem Kunden gehört. */
    public static function eine(int $kundeId, int $mailId): ?array
    {
        foreach (self::liste($kundeId, 500) as $e) {
            if ($e['quelle'] === 'mail' && $e['id'] === $mailId) {
                $voll = Db::one('SELECT * FROM mails WHERE id = ?', [$mailId]);
                return $voll ? $e + ['text' => (string) ($voll['inhalt'] ?? ''), 'html' => (string) ($voll['html'] ?? '')] : null;
            }
        }
        return null;
    }

    /** Das gespeicherte HTML darf nichts ausführen und nichts nachladen außer Bildern. */
    public const CSP = "sandbox; default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src https: data:";

    private static function zeile(array $z): array
    {
        $anh = json_decode((string) ($z['anhaenge'] ?? ''), true);
        return ['quelle' => 'mail', 'id' => (int) $z['id'], 'zeit' => (string) $z['created_at'], 'betreff' => (string) $z['betreff'],
            'an' => (string) $z['empfaenger'], 'status' => (string) $z['status'], 'fehler' => (string) ($z['fehler'] ?? ''),
            'anlass' => (string) $z['anlass'],
            'anhaenge' => is_array($anh) ? array_values(array_map(static fn($a) => ['name' => (string) ($a['name'] ?? ''), 'groesse' => (int) ($a['groesse'] ?? 0)], $anh)) : [],
            'inhalt' => !empty($z['hat_inhalt']),
            // Mail-Spur (07.10.2026): wodurch sie rausging — AI Freigabe, Knopf, automatisch oder Ablauf.
            'wer' => (static function () use ($z): string { require_once __DIR__ . '/MailSpur.php'; return MailSpur::wer($z); })(),
            'freigabe' => (string) ($z['ausloeser'] ?? '') === 'freigabe' ? (int) ($z['ausloeser_id'] ?? 0) : 0];
    }

    private static ?bool $spur = null;
    private static function spurDa(): bool
    {
        if (self::$spur !== null) { return self::$spur; }
        try { Db::wert('SELECT ausloeser FROM mails LIMIT 1', [], null); return self::$spur = true; }
        catch (Throwable $e) { return self::$spur = false; }
    }

    private static ?bool $spalte = null;
    private static function spalteDa(): bool
    {
        if (self::$spalte !== null) { return self::$spalte; }
        try { Db::wert('SELECT inhalt FROM mails LIMIT 1', [], null); return self::$spalte = true; }
        catch (Throwable $e) { return self::$spalte = false; }
    }
}
