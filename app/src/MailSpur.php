<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Mail-Spur (07.10.2026, Uwe: „wenn eine email versendet wurde soll dies auch ganz klar makiert
 * sein inklusive des verlaufs was geschrieben wurde“; „AI Freigaben, Kundenakte, an der Stelle
 * der Tat, Akquise und Partner“; „Band + Text aufklappbar“; Telegram „ja, kurz“).
 *
 * Seit Migration 210 steht jede Mail mit Text in `mails`, seit 215 auch, wodurch sie rausging.
 * Diese Klasse sucht die Mails zu einer Sache zusammen — zu einer AI-Freigabe, einem Angebot,
 * einem Exit-Paket, einer Zahlung, einem Projekt, einem Partner, einem Akquise-Betrieb — und
 * bringt sie in eine Form, die app/views/mailspur.php als Band zeigt. Sie liest nur.
 */
final class MailSpur
{
    public const STATUS = [
        'gesendet' => ['✉ Gesendet', 'gut'],
        'gehalten' => ['✉ Vom Not-Aus zurückgehalten', 'warnung'],
        'fehler'   => ['✉ Nicht gesendet', 'schlecht'],
        'von_hand' => ['✉ Im eigenen Mailprogramm geöffnet', ''],
    ];

    private const FELDER = 'id, anlass, empfaenger, betreff, status, fehler, created_at, customer_id, project_id, payment_id';

    private static ?bool $mitInhalt = null;
    private static ?bool $mitSpur = null;

    private static function spalte(string $name): bool
    {
        try { return Db::one("SHOW COLUMNS FROM mails LIKE '" . $name . "'") !== null; } catch (Throwable $e) { return false; }
    }

    private static function spur(): bool
    {
        return self::$mitSpur ??= self::spalte('ausloeser');
    }

    private static function felder(): string
    {
        self::$mitInhalt ??= self::spalte('inhalt');
        self::$mitSpur ??= self::spalte('ausloeser');
        return self::FELDER . (self::$mitInhalt ? ', inhalt, anhaenge' : '') . (self::$mitSpur ? ', ausloeser, ausloeser_ref, ausloeser_id, ausloeser_wer, ref_art, ref_id' : '');
    }

    /** @return list<array> neueste zuerst */
    private static function zeilen(string $wo, array $werte, int $grenze = 20): array
    {
        try {
            return array_map([self::class, 'form'], Db::all('SELECT ' . self::felder() . " FROM mails WHERE $wo ORDER BY id DESC LIMIT " . max(1, min(200, $grenze)), $werte));
        } catch (Throwable $e) { return []; }
    }

    /** Eine Zeile für die Anzeige: Status, Text, Anhänge und der Satz, wodurch sie rausging. */
    public static function form(array $z): array
    {
        $anh = json_decode((string) ($z['anhaenge'] ?? ''), true);
        return [
            'id' => (int) $z['id'], 'zeit' => (string) $z['created_at'], 'an' => (string) $z['empfaenger'],
            'betreff' => (string) $z['betreff'], 'status' => (string) $z['status'], 'fehler' => (string) ($z['fehler'] ?? ''),
            'text' => (string) ($z['inhalt'] ?? ''), 'anhaenge' => is_array($anh) ? array_values(array_filter($anh, 'is_array')) : [],
            'anlass' => (string) $z['anlass'], 'kunde' => $z['customer_id'] !== null ? (int) $z['customer_id'] : null,
            'zahlung' => $z['payment_id'] !== null ? (int) $z['payment_id'] : null,
            'wer' => self::wer($z),
        ];
    }

    /**
     * Wodurch die Mail rausging, in einem Satz.
     * Leer heißt: verschickt, bevor die Verwaltung das festhielt (vor Migration 215).
     */
    public static function wer(array $z): string
    {
        $wer = trim((string) ($z['ausloeser_wer'] ?? ''));
        $ref = (string) ($z['ausloeser_ref'] ?? '');
        switch ((string) ($z['ausloeser'] ?? '')) {
            case 'freigabe':
                return 'nach ' . ($wer !== '' ? $wer . 's' : 'deinem') . ' Ja in AI Freigaben' . (!empty($z['ausloeser_id']) ? ' (#' . (int) $z['ausloeser_id'] . ')' : '');
            case 'knopf':
                return 'per Knopf „' . self::tatWort($ref) . '“' . ($wer !== '' ? ' von ' . $wer : '');
            case 'automatisch':
                require_once __DIR__ . '/Automation.php';
                return 'automatisch: ' . (Automation::REGELN[$ref][0] ?? ($ref !== '' ? $ref : 'Zeitplan'));
            case 'ablauf':
                return 'vom Ablauf ohne Klick (Bestellung, Kundenbereich oder Eingang)';
            default:
                return '';
        }
    }

    /** Die Tat in Uwes Worten: das Ja der Rückfrage ohne „Ja, “ — sonst der Name der Tat lesbar. */
    public static function tatWort(string $tat): string
    {
        require_once __DIR__ . '/Ablauf.php';
        $ja = (string) (Ablauf::TRAGWEITE[$tat][2] ?? '');
        if ($ja !== '') { return ucfirst(trim(preg_replace('~^Ja,\s*~u', '', $ja) ?? $ja)); }
        return $tat !== '' ? ucfirst(str_replace('_', ' ', $tat)) : 'unbekannt';
    }

    /* ------------------------------------------------------------ Zu einer Sache */

    public static function zuFreigabe(int $freigabeId): array
    {
        return self::spur() ? self::zeilen("ausloeser = 'freigabe' AND ausloeser_id = ?", [$freigabeId]) : [];
    }

    /** Mails mit festem Bezug (angebot, exit). */
    public static function zuRef(string $art, int $id): array
    {
        return self::spur() ? self::zeilen('ref_art = ? AND ref_id = ?', [$art, $id]) : [];
    }

    /**
     * Die Mail zu einem Angebot: seit 215 über den Bezug; davor die Angebotsmail an diesen Kunden
     * innerhalb von zehn Minuten um den Versand herum.
     */
    public static function zuAngebot(int $angebotId, int $kundeId, string $gesendetAm): array
    {
        $m = self::zuRef('angebot', $angebotId);
        if ($m || $gesendetAm === '') { return $m; }
        return self::zeilen("customer_id = ? AND anlass = 'angebot' AND created_at BETWEEN ? - INTERVAL 10 MINUTE AND ? + INTERVAL 10 MINUTE", [$kundeId, $gesendetAm, $gesendetAm], 3);
    }

    /** @param list<string> $anlaesse */
    public static function zuKunde(int $kundeId, array $anlaesse = [], int $grenze = 20): array
    {
        if ($anlaesse === []) { return self::zeilen('customer_id = ?', [$kundeId], $grenze); }
        return self::zeilen('customer_id = ? AND anlass IN (' . implode(',', array_fill(0, count($anlaesse), '?')) . ')', array_merge([$kundeId], $anlaesse), $grenze);
    }

    public static function zuZahlung(int $zahlungId): array { return self::zeilen('payment_id = ?', [$zahlungId]); }

    public static function zuProjekt(int $projektId, int $grenze = 30): array { return self::zeilen('project_id = ?', [$projektId], $grenze); }

    /** @return array<int, list<array>> Zahlung-ID => Mails (eine Abfrage für die ganze Tabelle) */
    public static function zuZahlungen(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return []; }
        $aus = [];
        foreach (self::zeilen('payment_id IN (' . implode(',', $ids) . ')', [], 200) as $m) {
            $aus[(int) $m['zahlung']][] = $m;
        }
        return $aus;
    }

    /** Mails an einen Partner: mit Partner-Kennung (seit 215) oder an seine Adresse mit einem Partner-Anlass. */
    public static function zuPartner(int $partnerId, string $email, int $grenze = 30): array
    {
        $email = mb_strtolower(trim($email));
        if (self::spur()) {
            return self::zeilen("partner_id = ? OR (? <> '' AND LOWER(empfaenger) = ? AND anlass LIKE 'partner%')", [$partnerId, $email, $email], $grenze);
        }
        return $email !== '' ? self::zeilen("LOWER(empfaenger) = ? AND anlass LIKE 'partner%'", [$email], $grenze) : [];
    }

    /** Für die Akquise: die Mail zu einer Zeile aus akq_versand, wenn sie über den Server ging. */
    public static function zuMailIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) { return []; }
        $aus = [];
        foreach (self::zeilen('id IN (' . implode(',', $ids) . ')', [], 200) as $m) { $aus[$m['id']] = $m; }
        return $aus;
    }

    /** Für Telegram und die Hinweiszeile: ein kurzer Satz je Mail. */
    public static function kurz(array $mails): string
    {
        $s = [];
        foreach ($mails as $m) {
            $s[] = match ($m['status']) {
                'gesendet' => '✉ Gesendet an ' . $m['an'] . ' — „' . $m['betreff'] . '“',
                'gehalten' => '✉ Vom Not-Aus zurückgehalten: „' . $m['betreff'] . '“',
                default    => '✉ Nicht gesendet: „' . $m['betreff'] . '“' . ($m['fehler'] !== '' ? ' — ' . $m['fehler'] : ''),
            };
        }
        return implode("\n", $s);
    }
}
