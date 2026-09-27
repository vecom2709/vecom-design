<?php
declare(strict_types=1);

/**
 * „Rufen Sie mich zurück“ auf der Empfehlungsseite (27.09.2026, Uwe: Ja).
 *
 * Wer nicht tippen will, hinterlässt Name, Nummer und ein Zeitfenster. Das
 * landet dort, wo jeder andere Rückrufwunsch auch landet -- in der
 * Rückrufliste (Telefon::rueckrufe liest activities.type = 'telefon_melde'
 * mit art = 'rueckruf'). Keine zweite Liste, keine zweite Wahrheit.
 *
 * DER PARTNER: steht mit Name und Code im Anliegen, damit Uwe beim Anruf
 * weiß, wer empfohlen hat, und die Zuordnung setzen kann, sobald daraus ein
 * Kunde wird. Der Partner selbst erfährt nur, DASS jemand über seine Seite
 * zurückgerufen werden will -- nie wer.
 *
 * GEGEN MISSBRAUCH: ein verstecktes Feld, das nur Maschinen füllen; ein
 * signierter Zeitstempel (wer in unter drei Sekunden absendet, hat nicht
 * gelesen; älter als zwei Stunden: Seite neu laden); höchstens zehn
 * Wünsche je Partner und Tag. Keine IP wird gespeichert -- die Seite
 * verspricht das.
 */
final class PartnerRueckruf
{
    public const JE_TAG = 10;
    public const FENSTER = ['vormittag' => '9–12', 'mittag' => '12–15', 'nachmittag' => '15–18', 'abend' => '18–20'];
    public const MIN_SEKUNDEN = 3;
    public const MAX_SEKUNDEN = 7200;

    /** Die drei wählbaren Tage ab heute, ohne Sonntag. @return array<string,string> Datum → Wochentag-Schlüssel */
    public static function tage(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $aus = [];
        for ($i = 0; count($aus) < 3 && $i < 5; $i++) {
            $t = strtotime('+' . $i . ' days', $jetzt);
            if ((int) date('N', $t) === 7) { continue; }
            $aus[date('Y-m-d', $t)] = $i === 0 ? 'heute' : ($i === 1 ? 'morgen' : 'tag' . date('N', $t));
        }
        return $aus;
    }

    /** Signierter Zeitstempel fürs Formular. */
    public static function stempel(string $code, ?int $jetzt = null): string
    {
        $t = (string) ($jetzt ?? time());
        return $t . '.' . substr(hash_hmac('sha256', $code . '|' . $t, self::geheimnis()), 0, 20);
    }

    /**
     * @param array{name?:string, telefon?:string, tag?:string, fenster?:string, ok?:string, website?:string, st?:string} $d
     * @return string ok|rr_name|rr_telefon|rr_wann|rr_ok|rr_zeit|rr_genug|rr_falle
     */
    public static function anlegen(array $p, array $d, string $sprache, ?int $jetzt = null): string
    {
        $jetzt ??= time();
        if (trim((string) ($d['website'] ?? '')) !== '') { return 'rr_falle'; }   // Honigtopf: still „ok“ zeigen wäre netter, aber dann gäbe es keinen Test
        [$t, $sig] = array_pad(explode('.', (string) ($d['st'] ?? ''), 2), 2, '');
        if (!ctype_digit($t) || !hash_equals(substr(hash_hmac('sha256', $p['code'] . '|' . $t, self::geheimnis()), 0, 20), $sig)
            || $jetzt - (int) $t < self::MIN_SEKUNDEN || $jetzt - (int) $t > self::MAX_SEKUNDEN) { return 'rr_zeit'; }
        $name = trim(mb_substr(preg_replace('/\s+/u', ' ', strip_tags((string) ($d['name'] ?? ''))) ?? '', 0, 80));
        if (mb_strlen($name) < 2) { return 'rr_name'; }
        $tel = preg_replace('~[^\d+]~', '', (string) ($d['telefon'] ?? '')) ?? '';
        if (str_starts_with($tel, '00')) { $tel = '+' . substr($tel, 2); }
        $ziffern = strlen(preg_replace('~\D~', '', $tel) ?? '');
        if ($ziffern < 6 || $ziffern > 15 || substr_count($tel, '+') > 1 || (str_contains($tel, '+') && $tel[0] !== '+')) { return 'rr_telefon'; }
        $tage = self::tage($jetzt);
        $tag = (string) ($d['tag'] ?? ''); $fenster = (string) ($d['fenster'] ?? '');
        if (!isset($tage[$tag]) || !isset(self::FENSTER[$fenster])) { return 'rr_wann'; }
        if (empty($d['ok'])) { return 'rr_ok'; }
        if (!PartnerRecherche::zaehlen((int) $p['id'], 'rueckruf', self::JE_TAG)) { return 'rr_genug'; }

        $erreichbar = date('d.m.', strtotime($tag)) . ' ' . self::FENSTER[$fenster] . ' Uhr';
        $anliegen = 'Rückruf gewünscht über die Empfehlungsseite von ' . $p['name'] . ' (Code ' . $p['code'] . ', Sprache ' . $sprache . ')';
        Events::protokoll('telefon_melde', 'Rückrufwunsch über Partnerseite — ' . $name, null, null, null, [
            'art' => 'rueckruf', 'name' => $name, 'nummer' => $tel, 'erreichbar' => $erreichbar, 'anliegen' => $anliegen,
            'quelle' => 'partnerseite', 'partner_id' => (int) $p['id'], 'partner_code' => (string) $p['code'], 'sprache' => $sprache,
        ]);
        Events::melden('telefon_rueckruf', 'Rückrufwunsch: ' . $name . ' (über ' . $p['name'] . ')', 'warnung',
            'Nummer ' . $tel . ' · erreichbar ' . $erreichbar . ' · Sprache ' . strtoupper($sprache) . ' · empfohlen von ' . $p['name'] . ' (' . $p['code'] . ')', '/heute');
        $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
        try {
            PartnerPost::push((int) $p['id'], Texte::h(Texte::PARTNER_SEITE['rr_push_t'], $sp), Texte::h(Texte::PARTNER_SEITE['rr_push_x'], $sp), Partner::portalLink($p));
        } catch (Throwable $e) { /* Hinweis an den Partner ist Beiwerk */ }
        return 'ok';
    }

    private static function geheimnis(): string
    {
        $g = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'partner_formular_geheim'", [], '');
        if (strlen($g) < 32) {
            $g = bin2hex(random_bytes(32));
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('partner_formular_geheim', ?) ON DUPLICATE KEY UPDATE svalue = IF(CHAR_LENGTH(svalue) >= 32, svalue, VALUES(svalue))", [$g]);
            $g = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'partner_formular_geheim'", [], $g);
        }
        return $g;
    }
}
