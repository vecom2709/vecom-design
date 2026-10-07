<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';
require_once __DIR__ . '/KiText.php';

/**
 * Antworten auf Antworten (07.10.2026, Vorschläge 7 und 8).
 *
 * Schreibt ein Betrieb zurück, liegt ein fertiger Antwortentwurf in „AI Freigaben“ — raus geht er erst
 * mit Uwes Ja. Einzige Ausnahme: Per WhatsApp, innerhalb der 24 Stunden, die Meta für freie Antworten
 * erlaubt, beantwortet die KI zwei eindeutige Fälle sofort selbst — „Ich möchte einen Anruf“ (mit dem
 * Terminlink) und „Ich möchte mehr wissen“ (mit dem persönlichen Bereich). Nur, wenn der Betrieb
 * WhatsApp ausdrücklich erlaubt hat und der Text durch den Prüfer kommt.
 */
final class KiAntwort
{
    /** Diese Antworten bekommen keinen Entwurf: Wer nicht mehr will, bekommt nichts mehr. */
    public const OHNE = ['DO_NOT_CONTACT', 'NOT_INTERESTED', 'INVALID_ADDRESS', 'OUT_OF_OFFICE'];
    /** Diese beantwortet die KI per WhatsApp im 24-Stunden-Fenster direkt. */
    public const DIREKT = ['CALL_REQUEST', 'MORE_INFO'];

    /** Prüfnaht: ersetzt WhatsAppCloud::textSenden in der Kette. fn(string $an, string $text): bool */
    public static $waSenden = null;

    public static function nachEingang(int $antwortId, string $kanal): ?string
    {
        try {
            $a = Db::one('SELECT * FROM akq_antworten WHERE id = ?', [$antwortId]);
            if (!$a || in_array((string) $a['klasse'], self::OHNE, true)) { return null; }
            $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $a['firma_id']]);
            if (!$f || (int) $f['gesperrt'] === 1) { return null; }
            $sprache = AkquiseText::spracheFuer($f);
            $links = self::links($f, $sprache);
            require_once __DIR__ . '/AkquiseFolge.php';
            $vorher = AkquiseFolge::vorher((int) $f['id']);
            $e = KiText::antwortEntwurf($f, (string) $a['text'], (string) $a['klasse'], $sprache, $kanal, $vorher, array_values($links), (string) ($a['von'] ?? ''));
            if ($e === null) { return null; }

            if ($kanal === 'whatsapp' && in_array((string) $a['klasse'], self::DIREKT, true) && Ki::bereichAn('whatsapp_direkt')
                && AkquiseGate::einwilligungDeckt($f, 'whatsapp') && self::imFenster($a)) {
                $an = preg_replace('~\D~', '', (string) ($a['von'] ?? $f['whatsapp'] ?? '')) ?? '';
                if ($an !== '' && self::waSenden($an, $e['text'])) {
                    self::vermerken($f, 'whatsapp', '+' . $an, 'gesendet', 'KI-Antwort im 24-Stunden-Fenster (' . AkquiseText::ANTWORT_KLASSEN[$a['klasse']] . ')');
                    Db::update('akq_antworten', $antwortId, ['erledigt' => 1]);
                    Akquise::protokoll((int) $f['id'], 'antwort', 'WhatsApp sofort beantwortet: „' . mb_substr($e['text'], 0, 160) . '“');
                    try { Events::melden('akquise_antwort', 'WhatsApp beantwortet: ' . $f['name'], 'gut', mb_substr($e['text'], 0, 300), 'akquise/' . $f['id']); } catch (Throwable $x) { }
                    return 'direkt';
                }
            }
            require_once __DIR__ . '/Freigabe.php';
            Freigabe::vorschlagen('akquise_antwort', ['antwort' => $antwortId, 'kanal' => $kanal, 'betreff' => $e['betreff'], 'text' => $e['text']], [
                'titel' => 'Antwort an ' . mb_substr((string) $f['name'], 0, 120) . ($kanal === 'whatsapp' ? ' (WhatsApp)' : ''),
                'grund' => 'Er schrieb: „' . mb_substr(trim((string) $a['text']), 0, 300) . '“',
                'ist' => AkquiseText::ANTWORT_KLASSEN[$a['klasse']] ?? (string) $a['klasse'],
                'soll' => 'Diesen Entwurf schicken — Text vorher ändern, wenn du willst.',
                'von' => 'KI-Texte', 'system' => 'Akquise',
            ]);
            return 'entwurf';
        } catch (Throwable $e) {
            return null;   // ein Entwurf ist Hilfe, kein Muss
        }
    }

    /** @return array<string,string> Termin und persönlicher Bereich */
    public static function links(array $f, string $sprache): array
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $l = ['termin' => $basis . '/termin.php?lang=' . $sprache];
        try {
            require_once __DIR__ . '/AkquiseFolge.php';
            $l['bereich'] = AkquiseFolge::dashboardLink($f, $sprache);
        } catch (Throwable $e) { }
        return array_filter($l, static fn($x) => is_string($x) && $x !== '');
    }

    public static function imFenster(array $a): bool
    {
        return strtotime((string) $a['eingang_am']) >= time() - 23 * 3600;   // eine Stunde Sicherheitsabstand zu Metas 24 Stunden
    }

    private static function waSenden(string $an, string $text): bool
    {
        if (self::$waSenden !== null) { return (bool) (self::$waSenden)($an, $text); }
        require_once __DIR__ . '/WhatsAppCloud.php';
        return WhatsAppCloud::textSenden($an, $text);
    }

    private static function vermerken(array $f, string $kanal, string $an, string $status, string $grund, ?int $mailId = null): void
    {
        try {
            $actor = class_exists('Auth', false) && Auth::angemeldet() ? Auth::name() : 'System';
            $z = ['firma_id' => (int) $f['id'], 'kanal' => $kanal, 'an' => mb_substr($an, 0, 190), 'status' => $status, 'compliance' => 'antwort',
                  'grund' => mb_substr($grund, 0, 255), 'abmelde_token' => bin2hex(random_bytes(20)), 'actor' => $actor];
            if ($mailId !== null) { $z['mail_id'] = $mailId; }
            Db::insert('akq_versand', $z);
        } catch (Throwable $e) { }
    }

    /**
     * Uwe hat Ja gesagt (AI Freigaben). Schickt den (vielleicht geänderten) Entwurf.
     */
    public static function senden(int $antwortId, string $kanal, string $betreff, string $text): string
    {
        $a = Db::one('SELECT * FROM akq_antworten WHERE id = ?', [$antwortId]);
        if (!$a) { throw new RuntimeException('Die Antwort gibt es nicht mehr.'); }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $a['firma_id']]);
        if (!$f) { throw new RuntimeException('Den Betrieb gibt es nicht mehr.'); }
        if ((int) $f['gesperrt'] === 1) { throw new RuntimeException('Der Betrieb ist inzwischen gesperrt — es geht nichts raus.'); }
        if (trim($text) === '') { throw new RuntimeException('Der Text ist leer.'); }
        $sprache = AkquiseText::spracheFuer($f);
        if ($kanal === 'whatsapp') {
            if (!self::imFenster($a)) { throw new RuntimeException('Das 24-Stunden-Fenster von WhatsApp ist vorbei — frei schreiben geht jetzt nur noch, wenn der Betrieb wieder schreibt.'); }
            $an = preg_replace('~\D~', '', (string) ($a['von'] ?? $f['whatsapp'] ?? '')) ?? '';
            if ($an === '' || !self::waSenden($an, $text)) { throw new RuntimeException('WhatsApp hat die Nachricht nicht angenommen.'); }
            self::vermerken($f, 'whatsapp', '+' . $an, 'gesendet', 'Antwort (Freigabe)');
        } else {
            $an = trim((string) ($a['von'] ?? ''));
            if (!filter_var($an, FILTER_VALIDATE_EMAIL)) { $an = trim((string) ($f['email'] ?? '')); }
            if (!filter_var($an, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('Keine gültige Mailadresse für die Antwort.'); }
            require_once __DIR__ . '/Mail.php';
            $ok = Mail::senden('akquise_antwort', $an, trim($betreff) !== '' ? $betreff : 'Re: ' . (string) $f['name'], $text,
                ['nurText' => true, 'sprache' => $sprache, 'ohne_ki' => true]);
            if (!$ok) { throw new RuntimeException('Die Mail ging nicht raus — siehe E-Mail-Protokoll.'); }
            self::vermerken($f, 'email', $an, 'gesendet', 'Antwort (Freigabe)', Mail::$letzteId);
        }
        Db::update('akq_antworten', $antwortId, ['erledigt' => 1]);
        Akquise::protokoll((int) $f['id'], 'antwort', 'Antwort verschickt (' . $kanal . '): „' . mb_substr($text, 0, 160) . '“');
        return 'Antwort an ' . $f['name'] . ' ist raus.';
    }
}
