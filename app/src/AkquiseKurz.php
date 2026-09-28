<?php
declare(strict_types=1);
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';
require_once __DIR__ . '/AkquiseEinwilligung.php';

/**
 * Kurz-Check und Ja in einem Schritt (28.09.2026, Uwe: Ja zu Z2).
 *
 * Der Betrieb hat gerade die Ampel seiner Website gesehen (analisi.php, die
 * Partnerseiten, der WhatsApp-Assistent, ein Werbeformular). Direkt darunter
 * trägt er seine E-Mail ein, auf Wunsch seine WhatsApp-Nummer, und hakt den
 * Wortlaut an. Hier entsteht daraus der Betrieb in der Akquise (oder er wird
 * ergänzt) und die Bestätigungsmail geht raus. Ab dem Klick darin läuft alles
 * wie bei jeder Einwilligung: Folge-Nachrichten, persönlicher Bereich.
 *
 * Nichts davon schreibt jemanden an, der nicht gerade selbst gefragt hat.
 */
final class AkquiseKurz
{
    /** Woher die Einwilligung kam -- steht im Beleg. */
    public const QUELLEN = ['check', 'partner', 'anzeige', 'whatsapp'];

    /** Ein lesbarer Name aus der Adresse, wenn der Betrieb keinen genannt hat: „trattoria-rossi.it“ → „Trattoria Rossi“. */
    public static function nameAusHost(string $host): string
    {
        $h = preg_replace('~^www\.~', '', mb_strtolower($host)) ?? $host;
        $stamm = explode('.', $h)[0] ?? $h;
        return mb_convert_case(str_replace(['-', '_'], ' ', $stamm), MB_CASE_TITLE, 'UTF-8');
    }

    /** Den Betrieb zur Adresse finden oder anlegen. @return array{id:int, host:string, url:string}|null */
    public static function betrieb(string $roh, string $name = ''): ?array
    {
        foreach (['Domainpruefung', 'PartnerCheck'] as $k) { require_once __DIR__ . "/$k.php"; }
        $url = PartnerCheck::adresse($roh);
        if ($url === null) { return null; }
        $host = (string) (Akquise::normDomain($url) ?? parse_url($url, PHP_URL_HOST));
        $land = preg_match('~\.(de|at|ch)$~', $host) ? 'DE' : 'IT';
        $name = trim(strip_tags($name));
        $m = Akquise::firmaMelden(['name' => mb_substr($name !== '' ? $name : self::nameAusHost($host), 0, 190), 'land' => $land, 'url' => $url,
                                   'quelle' => mb_substr('kurzcheck:' . $host, 0, 80)]);
        $id = (int) $m['id'];
        /* Die ausführliche Prüfung holt dein PC -- wer gefragt hat, kommt nach vorn. */
        $f = Db::one('SELECT audit_status, geprueft_am FROM akq_firmen WHERE id = ?', [$id]) ?? [];
        if (in_array((string) ($f['audit_status'] ?? ''), ['', 'fertig', 'fehler'], true) && strtotime((string) ($f['geprueft_am'] ?? '2000-01-01')) < strtotime('-1 day')) {
            Db::update('akq_firmen', $id, ['audit_status' => 'offen']);
        }
        return ['id' => $id, 'host' => $host, 'url' => $url];
    }

    /**
     * @param array{url:string, betrieb?:string, email:string, whatsapp?:?string, ja:bool, sprache:string, quelle:string, partner?:?array, ip?:string} $e
     * @return string ok | adresse | email | whatsapp | zuviel | gesperrt
     */
    public static function einwilligen(array $e): string
    {
        $quelle = in_array($e['quelle'] ?? '', self::QUELLEN, true) ? (string) $e['quelle'] : 'check';
        $b = self::betrieb((string) ($e['url'] ?? ''), (string) ($e['betrieb'] ?? ''));
        if ($b === null) { return 'adresse'; }
        try { $link = AkquiseEinwilligung::link($b['id'], $quelle); } catch (RuntimeException $x) { return 'gesperrt'; }
        $wa = isset($e['whatsapp']) && $e['whatsapp'] !== null && trim((string) $e['whatsapp']) !== '' ? (string) $e['whatsapp'] : null;
        $r = AkquiseEinwilligung::anfragen((string) $link['link_token'], (string) ($e['email'] ?? ''), !empty($e['ja']), (string) ($e['sprache'] ?? 'it'), (string) ($e['ip'] ?? ''), $wa);
        if ($r !== 'ok') { return $r; }
        $partner = $e['partner'] ?? null;
        if (is_array($partner) && !empty($partner[0]['id'])) {
            try {
                require_once __DIR__ . '/Partner.php';
                Partner::vormerken((int) $partner[0]['id'], (string) $e['email'], $wa, 'check', 'link', $partner[1] ?? null);
            } catch (Throwable $x) { /* Zuordnung ist nachtragbar */ }
        }
        /* Er hat die Analyse und seinen persönlichen Bereich angefordert (28.09.2026): der kommt sofort,
           als eigene Mail -- getrennt von der Bestätigungsmail, die keine weiteren Inhalte tragen darf. */
        try {
            require_once __DIR__ . '/Zugang.php';
            $f = Db::one('SELECT name FROM akq_firmen WHERE id = ?', [$b['id']]);
            if (AkquiseGate::schalterSelbst('bereich')) Zugang::bereichSchicken((string) $e['email'], (string) ($e['sprache'] ?? 'it'), $b['id'], (string) ($f['name'] ?? ''));
        } catch (Throwable $x) { }
        Akquise::protokoll($b['id'], 'einwilligung', 'Kurz-Check mit Einwilligung (' . ['check' => 'vecom-design.it', 'partner' => 'Partnerseite', 'anzeige' => 'Werbeformular', 'whatsapp' => 'WhatsApp'][$quelle]
            . ') — Bestätigungsmail an ' . mb_strtolower(trim((string) $e['email'])));
        try { Events::melden('akquise_kurzcheck', 'Kurz-Check mit Einwilligung: ' . $b['host'], 'gut', 'Wartet auf den Klick in der Bestätigungsmail', 'akquise/' . $b['id']); } catch (Throwable $x) { }
        return 'ok';
    }
}
