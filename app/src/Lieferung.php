<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Bausperre.php';
require_once __DIR__ . '/Versionen.php';

/**
 * AutoBuild Phase 9 — Lieferprüfung, Livegang, Übergabe (06.10.2026).
 *
 *   Lieferprüfung  : automatische Punkte (Stand aus dem System) + Punkte, die
 *                    ein Mensch selbst angesehen hat. Abgehakt wird für GENAU
 *                    eine Fassung; erst dann lässt Veroeffentlichung::stand sie live.
 *   Livegang       : weiter über Veroeffentlichung (Sicherung vorher) — nur Admin.
 *   Live-Prüfung   : Domain antwortet mit 200 und zeigt den Titel der Fassung.
 *   Übergabe       : Dokument in der Sprache des Kunden — ohne Passwörter;
 *                    der Kunde sieht es erst, wenn Vecom es freigibt.
 *
 * Nichts davon behauptet „alles funktioniert“: Die Liste sagt, was geprüft
 * wurde, von wem und wann — und was nicht.
 */
final class Lieferung
{
    /** Diese Punkte muss ein Mensch selbst angesehen haben. */
    public const MANUELL = [
        'mobil'       => 'Auf dem Handy und am Rechner durchgeklickt — nichts verrutscht, alles lesbar',
        'kontakt'     => 'Kontaktwege ausprobiert: Telefon, WhatsApp, E-Mail, Karte führen an die richtige Stelle',
        'texte'       => 'Texte, Preise, Öffnungszeiten stimmen mit den Angaben des Kunden überein',
        'rechtliches' => 'Impressum / Datenschutz / Cookie-Hinweis vorhanden und passend zum Land',
        'bilder'      => 'Bilder und Schriften dürfen verwendet werden (eigene, vom Kunden oder mit Lizenz)',
        'seo'         => 'Seitentitel und Beschreibung sinnvoll, Seite darf von Suchmaschinen gefunden werden',
    ];

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /**
     * Die automatischen Punkte für eine Fassung. „schwer“ = verhindert das Abhaken.
     * @return list<array{key:string,text:string,ok:bool,schwer:bool,detail:string}>
     */
    public static function automatisch(int $pid, ?array $v): array
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]) ?: [];
        $r = [];
        $add = static function (string $k, string $t, bool $ok, bool $schwer, string $d = '') use (&$r): void { $r[] = ['key' => $k, 'text' => $t, 'ok' => $ok, 'schwer' => $schwer, 'detail' => $d]; };
        $add('fassung', 'Fassung auf der Testfassung angesehen und als geprüft markiert', $v !== null && !empty($v['staging_url']) && !empty($v['geprueft_am']), true,
            $v ? 'V' . (int) $v['nummer'] . (!empty($v['geprueft_von']) ? ' · geprüft von ' . $v['geprueft_von'] : '') : 'keine Fassung');
        $tests = $v !== null ? json_decode((string) ($v['tests'] ?? ''), true) : null;
        if ($v !== null && !is_array($tests)) {   // Fassung von Hand/aus der Werkstatt: Tests jetzt nachholen
            require_once __DIR__ . '/BauPruefung.php';
            $tests = BauPruefung::pruefen(Versionen::dateien((int) $v['id'], false));
            self::still(static fn() => Db::update('projekt_versionen', (int) $v['id'], ['tests' => json_encode($tests, JSON_UNESCAPED_UNICODE), 'tests_ok' => BauPruefung::bestanden($tests) ? 1 : 0]), null);
        }
        $fehl = array_values(array_filter((array) $tests, static fn($t) => !empty($t['schwer']) && empty($t['ok'])));
        $add('tests', 'Automatische Tests ohne schweren Fehler', $v !== null && !$fehl, true, $fehl ? implode(', ', array_column($fehl, 'name')) : '');
        $add('review', 'Review durch Claude bestanden', ($v['review_urteil'] ?? '') === 'bestanden', false,
            $v === null ? '' : (($v['review_urteil'] ?? '') === '' ? 'kein Review gelaufen — Hinweis, kein Hindernis' : 'Review: ' . $v['review_urteil']));
        require_once __DIR__ . '/Wunsch.php';
        $offen = (int) self::still(static fn() => Db::wert("SELECT COUNT(*) FROM projekt_wuensche WHERE project_id = ? AND status IN ('neu','im_umfang','zusatz_angenommen')", [$pid], 0), 0);
        $add('wuensche', 'Keine offenen Kundenwünsche (neu, im Umfang oder angenommener Zusatz)', $offen === 0, true, $offen > 0 ? $offen . ' offen' : '');
        $bs = Bausperre::darfBauen($p ?: $pid);
        $add('notaus', 'Kein Not-Aus', !$bs['stopp'], true, $bs['stopp'] ? $bs['grund'] : '');
        $add('pflichtenheft', 'Pflichtenheft übernommen', trim((string) ($p['pflichtenheft'] ?? '')) !== '', false, 'Hinweis — ohne Pflichtenheft fehlt die Grundlage für die Abnahme');
        return $r;
    }

    /** Die gültige Lieferprüfung für diese Fassung (oder null). */
    public static function fuer(int $versionId): ?array
    {
        return self::still(static fn() => Db::one('SELECT * FROM projekt_lieferung WHERE version_id = ? ORDER BY id DESC LIMIT 1', [$versionId]) ?: null, null);
    }

    /** @param list<string> $haken die abgehakten MANUELL-Schlüssel */
    public static function bestaetigen(int $pid, int $versionId, array $haken, string $wer, string $notiz = ''): void
    {
        $v = Versionen::laden($versionId);
        if (!$v || (int) $v['project_id'] !== $pid) { throw new RuntimeException('Diese Fassung gehört nicht zu diesem Projekt.'); }
        $fehlt = array_diff(array_keys(self::MANUELL), $haken);
        if ($fehlt) { throw new RuntimeException('Noch nicht abgehakt: ' . implode('; ', array_map(static fn($k) => self::MANUELL[$k], $fehlt))); }
        $auto = self::automatisch($pid, $v);
        $schwer = array_filter($auto, static fn($a) => $a['schwer'] && !$a['ok']);
        if ($schwer) { throw new RuntimeException('Erst erledigen: ' . implode('; ', array_map(static fn($a) => $a['text'] . ($a['detail'] !== '' ? ' (' . $a['detail'] . ')' : ''), $schwer))); }
        $id = (int) Db::insert('projekt_lieferung', ['project_id' => $pid, 'version_id' => $versionId, 'von' => mb_substr($wer, 0, 120),
            'notiz' => ($n = mb_substr(trim(strip_tags($notiz)), 0, 500)) !== '' ? $n : null,
            'punkte' => json_encode(['manuell' => array_values($haken), 'automatisch' => $auto], JSON_UNESCAPED_UNICODE)]);
        Events::pruefspur('lieferpruefung', 'projekt_lieferung', $id, [], ['projekt' => $pid, 'version' => (int) $v['nummer'], 'von' => $wer]);
    }

    /**
     * Für Veroeffentlichung::stand: darf diese Fassung live? null = ja.
     * Zurückrollen auf eine schon einmal live gewesene Fassung braucht keine neue Prüfung.
     */
    public static function sperre(?array $v): ?string
    {
        if ($v === null || !empty($v['live_am'])) { return null; }
        return self::fuer((int) $v['id']) === null ? 'Lieferprüfung für V' . (int) $v['nummer'] . ' noch nicht abgehakt (Karte „Livegang“).' : null;
    }

    /**
     * Nach dem Livegang: antwortet die Domain, und zeigt sie die Fassung?
     * @param null|callable(string):array{0:int,1:string} $http
     * @return array{ok:bool, text:string}
     */
    public static function liveCheck(int $pid, ?callable $http = null): array
    {
        $p = Db::one('SELECT veroeffentlicht_domain, live_version_id FROM projects WHERE id = ?', [$pid]);
        $domain = (string) ($p['veroeffentlicht_domain'] ?? '');
        if ($domain === '') { return ['ok' => false, 'text' => 'Noch nicht veröffentlicht.']; }
        $url = 'https://' . $domain . '/';
        $http ??= static function (string $u): array {
            if (!function_exists('curl_init')) { return [0, '']; }
            $c = curl_init($u);
            curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'Vecom-Livecheck']);
            $b = (string) curl_exec($c); $s = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
            return [$s, $b];
        };
        [$status, $body] = $http($url);
        $titel = '';
        if ((int) ($p['live_version_id'] ?? 0) > 0) {
            $idx = Versionen::dateien((int) $p['live_version_id'], true)['index.html'] ?? '';
            if (preg_match('~<title>\s*([^<]+?)\s*</title>~i', $idx, $m)) { $titel = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
        }
        $passt = $titel === '' || str_contains(html_entity_decode((string) $body, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $titel);
        $ok = $status === 200 && $passt;
        $text = $ok ? $url . ' antwortet (200)' . ($titel !== '' ? ' und zeigt „' . mb_substr($titel, 0, 60) . '“' : '') . '.'
            : ($status !== 200 ? $url . ' antwortet mit ' . ($status ?: 'keiner Verbindung') . '.' : $url . ' antwortet, zeigt aber nicht den Titel der Live-Fassung — Cache, alte Dateien oder falscher Ordner?');
        Db::update('projects', $pid, ['livecheck' => json_encode(['ok' => $ok, 'status' => $status, 'text' => $text], JSON_UNESCAPED_UNICODE), 'livecheck_am' => date('Y-m-d H:i:s')]);
        return ['ok' => $ok, 'text' => $text];
    }

    /* ------------------------------------------------------------------ */
    /*  Übergabe                                                          */
    /* ------------------------------------------------------------------ */

    public const UEBERGABE = [
        'titel'     => ['it' => 'Consegna del suo sito', 'de' => 'Übergabe Ihrer Website', 'en' => 'Handover of your website'],
        'online'    => ['it' => 'Il suo sito è online', 'de' => 'Ihre Website ist online', 'en' => 'Your website is live'],
        'adresse'   => ['it' => 'Indirizzo', 'de' => 'Adresse', 'en' => 'Address'],
        'fassung'   => ['it' => 'Versione pubblicata', 'de' => 'Veröffentlichte Fassung', 'en' => 'Published version'],
        'am'        => ['it' => 'Pubblicato il', 'de' => 'Veröffentlicht am', 'en' => 'Published on'],
        'umfang'    => ['it' => 'Cosa comprende', 'de' => 'Was enthalten ist', 'en' => 'What’s included'],
        'wuensche'  => ['it' => 'Modifiche realizzate su sua richiesta', 'de' => 'Auf Ihren Wunsch umgesetzt', 'en' => 'Changes made at your request'],
        'aendern'   => ['it' => 'Modifiche in futuro', 'de' => 'Änderungen in Zukunft', 'en' => 'Future changes'],
        'aendernT'  => ['it' => 'Mi scriva dalla sua pagina cliente. Le modifiche comprese le faccio subito; per tutto il resto riceve prima un preventivo — senza il suo ok non parte niente.',
                        'de' => 'Schreiben Sie mir über Ihre Kundenseite. Was enthalten ist, setze ich um; für alles andere bekommen Sie vorher ein Angebot — ohne Ihr Ja passiert nichts.',
                        'en' => 'Write to me from your customer page. Included changes I make right away; for anything else you get a quote first — nothing happens without your go-ahead.'],
        'sicher'    => ['it' => 'Sicurezza', 'de' => 'Sicherheit', 'en' => 'Safety'],
        'sicherT'   => ['it' => 'Prima di ogni pubblicazione salvo una copia di quanto è online, così si può tornare indietro in qualsiasi momento.',
                        'de' => 'Vor jeder Veröffentlichung sichere ich, was online liegt — so lässt sich jederzeit zurückkehren.',
                        'en' => 'Before every publication I save a copy of what is online, so we can always go back.'],
        'zugang'    => ['it' => 'Accessi', 'de' => 'Zugänge', 'en' => 'Access'],
        'zugangT'   => ['it' => 'Le password non sono in questo documento. Le trova nella sua pagina cliente o me le chieda.',
                        'de' => 'Passwörter stehen nicht in diesem Dokument. Sie finden sie auf Ihrer Kundenseite oder fragen mich.',
                        'en' => 'Passwords are not in this document. You’ll find them on your customer page, or just ask me.'],
        'kontakt'   => ['it' => 'Contatto', 'de' => 'Kontakt', 'en' => 'Contact'],
    ];

    private static function t(string $k, string $s): string
    {
        return (string) (self::UEBERGABE[$k][$s] ?? self::UEBERGABE[$k]['it'] ?? $k);
    }

    /** Das Übergabe-Dokument (Markdown) in der Sprache des Kunden erzeugen und am Projekt speichern. */
    public static function uebergabeErstellen(int $pid, string $wer): string
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$pid]);
        if (!$p || (string) ($p['veroeffentlicht_domain'] ?? '') === '') { throw new RuntimeException('Erst veröffentlichen — die Übergabe beschreibt die Seite, die online ist.'); }
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $p['customer_id']]) ?: [];
        $s = in_array((string) ($k['sprache'] ?? 'it'), ['it', 'de', 'en'], true) ? (string) $k['sprache'] : 'it';
        $v = (int) ($p['live_version_id'] ?? 0) > 0 ? Versionen::laden((int) $p['live_version_id']) : null;
        require_once __DIR__ . '/BauAuftrag.php';
        $umfang = BauAuftrag::umfang($p);
        $wuensche = self::still(static fn() => Db::all("SELECT text FROM projekt_wuensche WHERE project_id = ? AND status = 'umgesetzt' ORDER BY id", [$pid]), []);
        $md = '# ' . self::t('titel', $s) . ' — ' . ((string) ($k['company'] ?? '') ?: (string) ($k['name'] ?? '')) . "\n\n"
            . '## ' . self::t('online', $s) . "\n"
            . '- ' . self::t('adresse', $s) . ': https://' . $p['veroeffentlicht_domain'] . "\n"
            . ($v ? '- ' . self::t('fassung', $s) . ': V' . (int) $v['nummer'] . "\n" : '')
            . (!empty($p['veroeffentlicht_am']) ? '- ' . self::t('am', $s) . ': ' . date('d.m.Y', strtotime((string) $p['veroeffentlicht_am'])) . "\n" : '')
            . ($umfang ? "\n## " . self::t('umfang', $s) . "\n" . implode("\n", array_map(static fn($u) => '- ' . ($u['menge'] > 1 ? $u['menge'] . '× ' : '') . $u['bezeichnung'], $umfang)) . "\n" : '')
            . ($wuensche ? "\n## " . self::t('wuensche', $s) . "\n" . implode("\n", array_map(static fn($w) => '- ' . mb_strimwidth(preg_replace('~\s+~', ' ', (string) $w['text']), 0, 160, '…'), $wuensche)) . "\n" : '')
            . "\n## " . self::t('aendern', $s) . "\n" . self::t('aendernT', $s) . "\n"
            . "\n## " . self::t('sicher', $s) . "\n" . self::t('sicherT', $s) . "\n"
            . "\n## " . self::t('zugang', $s) . "\n" . self::t('zugangT', $s) . "\n"
            . "\n## " . self::t('kontakt', $s) . "\n" . (string) Config::get('firma', 'Vecom Design') . ' · ' . rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . "\n";
        Db::update('projects', $pid, ['uebergabe' => $md, 'uebergabe_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('uebergabe_erstellt', 'projects', $pid, [], ['von' => $wer, 'sprache' => $s]);
        return $md;
    }

    public static function uebergabeFreigeben(int $pid, string $wer, bool $frei = true): void
    {
        $p = Db::one('SELECT uebergabe FROM projects WHERE id = ?', [$pid]);
        if ($frei && trim((string) ($p['uebergabe'] ?? '')) === '') { throw new RuntimeException('Erst die Übergabe erstellen.'); }
        Db::update('projects', $pid, ['uebergabe_frei_am' => $frei ? date('Y-m-d H:i:s') : null]);
        Events::pruefspur($frei ? 'uebergabe_frei' : 'uebergabe_zu', 'projects', $pid, [], ['von' => $wer]);
    }
}
