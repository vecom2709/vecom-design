<?php
declare(strict_types=1);
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';

/**
 * Formular in der Google-Anzeige (28.09.2026, Uwe: Ja zu D3).
 *
 * Google Ads schickt jedes ausgefüllte Lead-Formular als JSON an
 * google-lead.php, zusammen mit dem Schlüssel, den Uwe dort eingetragen hat.
 * Hier wird daraus dasselbe wie beim Werbeformular von Facebook (Z5): Nur wer
 * die Einwilligungsfrage mit „Sì/Ja“ beantwortet hat, bekommt die
 * Bestätigungsmail -- sonst wird nichts gespeichert außer dem Hinweis.
 * Testmeldungen aus Google Ads („is_test“) legen nichts an.
 */
final class GoogleLead
{
    public static function adresse(): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/google-lead.php';
    }

    /** Der Schlüssel für Google Ads -- verschlüsselt abgelegt, angezeigt nur in der Verwaltung. */
    public static function schluessel(): string
    {
        $blob = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', ['google_lead_schluessel'], '');
        if ($blob === '') { return ''; }
        require_once __DIR__ . '/Hosting.php';
        return (string) (Hosting::entsiegeln($blob)['wert'] ?? '');
    }

    public static function schluesselNeu(): string
    {
        require_once __DIR__ . '/Hosting.php';
        $k = 'vd' . bin2hex(random_bytes(16));
        $blob = (string) Hosting::versiegeln(['wert' => $k]);
        if ($blob === '') { throw new RuntimeException('Der Schlüssel ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).'); }
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['google_lead_schluessel', $blob]);
        return $k;
    }

    /** Felder aus user_column_data -- tolerant gegenüber den Namen der eigenen Fragen. */
    public static function felder(array $d): array
    {
        $x = ['url' => '', 'email' => '', 'whatsapp' => '', 'betrieb' => '', 'ja' => false, 'antwort' => false];
        foreach ((array) ($d['user_column_data'] ?? []) as $c) {
            $id = mb_strtoupper((string) ($c['column_id'] ?? ''));
            $name = mb_strtolower((string) ($c['column_name'] ?? ''));
            $v = trim((string) ($c['string_value'] ?? ''));
            if ($v === '') { continue; }
            if (in_array($id, ['EMAIL', 'WORK_EMAIL'], true)) { $x['email'] = $v; }
            elseif (in_array($id, ['PHONE_NUMBER', 'WORK_PHONE'], true)) { $x['whatsapp'] = $v; }
            elseif ($id === 'COMPANY_NAME') { $x['betrieb'] = $v; }
            elseif (preg_match('~consen|einwillig|einverstanden|zustimm|consent|accett|erlaub~u', $name)) {
                $x['antwort'] = true;
                $x['ja'] = (bool) preg_match('~^\s*(sì|si|ja|yes)\b~iu', $v);
            }
            elseif (preg_match('~sito|website|webseite|internet|url|indirizzo del|adresse ihrer~u', $name)) { $x['url'] = $v; }
        }
        return $x;
    }

    /** @return string ok|test|doppelt|ohne_haken|schluessel|... */
    public static function verarbeiten(array $d): string
    {
        $soll = self::schluessel();
        if ($soll === '' || !hash_equals($soll, (string) ($d['google_key'] ?? ''))) { return 'schluessel'; }
        if (!empty($d['is_test'])) { return 'test'; }
        $leadId = 'g:' . substr(sha1((string) ($d['lead_id'] ?? '')), 0, 36);
        if (trim((string) ($d['lead_id'] ?? '')) === '') { return 'fehler'; }
        try { Db::insert('akq_meta_leads', ['lead_id' => $leadId, 'status' => 'neu']); }
        catch (Throwable $e) { if (Db::doppelt($e, 'uq_akq_meta_lead')) { return 'doppelt'; } throw $e; }
        $zeile = (int) Db::wert('SELECT id FROM akq_meta_leads WHERE lead_id = ?', [$leadId], 0);
        $x = self::felder($d);
        if (!$x['ja']) {
            Db::update('akq_meta_leads', $zeile, ['status' => 'ohne_haken', 'grund' => 'Google: ' . ($x['antwort'] ? 'Einwilligungsfrage mit Nein beantwortet' : 'Formular ohne Einwilligungsfrage') . ' — nichts gespeichert.']);
            if (!$x['antwort']) {
                try { Events::melden('akquise_formular', 'Google-Formular ohne Einwilligungsfrage', 'warnung', 'Bitte im Google-Formular die Frage mit dem Wortlaut einbauen (Anleitung unter Akquise → Regeln → Wege zum Ja).', 'akquise/regeln#wege'); } catch (Throwable $y) { }
            }
            return 'ohne_haken';
        }
        require_once __DIR__ . '/MetaSeite.php';
        require_once __DIR__ . '/AkquiseKurz.php';
        $s = AkquiseKurz::einwilligen(['url' => $x['url'], 'betrieb' => $x['betrieb'], 'email' => $x['email'], 'whatsapp' => $x['whatsapp'] !== '' ? $x['whatsapp'] : null,
            'ja' => true, 'sprache' => MetaSeite::leadSprache($x['url']), 'quelle' => 'anzeige']);
        Db::update('akq_meta_leads', $zeile, ['status' => $s, 'grund' => $s === 'ok' ? 'Google-Anzeige' : 'Google: Einwilligung ' . $s]);
        return $s;
    }
}
