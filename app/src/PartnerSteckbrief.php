<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseText.php';
require_once __DIR__ . '/BranchenStatistik.php';

/**
 * Was der Partner vor dem Anruf über einen Betrieb wissen muss
 * (29.09.2026, Uwe: Ja zu D1–D4).
 *
 * D1 Steckbrief: Adresse mit Karte, Website als Link, Ansprechpartner.
 * D2 Analyse:    Chance, Datum und die wichtigsten Fehler in einfachen Worten.
 * D3 Öffnungszeiten laut eigener Website (nie Google Maps) + „jetzt offen?“.
 * D4 Beste Anrufzeit je Branche -- nie in der Stoßzeit, nie sonntags.
 *
 * Reine Aufbereitung: Nichts hier schreibt in die Datenbank.
 */
final class PartnerSteckbrief
{
    private const TAGE = [
        'it' => [1 => 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'],
        'de' => [1 => 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
        'en' => [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    ];

    /**
     * D4: wann der Chef Zeit hat. 'it' ersetzt die Zeiten in Italien, wo
     * mittags länger geschlossen ist. 'nicht' ist die Stoßzeit in Worten.
     */
    private const ANRUFZEIT = [
        'restaurant' => ['gut' => [['10:00', '11:30'], ['15:00', '18:00']],
            'nicht' => ['it' => 'durante il servizio (12–15 e 19–22)', 'de' => 'während des Service (12–15 und 19–22 Uhr)', 'en' => 'during service (12–3 pm and 7–10 pm)']],
        'bar_cafe' => ['gut' => [['10:00', '11:30'], ['15:00', '17:30']],
            'nicht' => ['it' => 'a colazione e all’aperitivo (7–10 e 18–20)', 'de' => 'zum Frühstück und Aperitif (7–10 und 18–20 Uhr)', 'en' => 'at breakfast and aperitivo time (7–10 am, 6–8 pm)']],
        'hotel' => ['gut' => [['10:00', '12:00'], ['15:00', '17:00']],
            'nicht' => ['it' => 'presto al mattino (check-out) e la sera (arrivi)', 'de' => 'früh morgens (Check-out) und abends (Anreise)', 'en' => 'early morning (check-out) and evening (arrivals)']],
        'baeckerei' => ['gut' => [['10:00', '12:00']],
            'nicht' => ['it' => 'al mattino presto, fino alle 9', 'de' => 'früh morgens bis 9 Uhr', 'en' => 'early morning until 9 am']],
        'friseur' => ['gut' => [['09:00', '10:30']], 'it' => [['09:00', '10:00'], ['15:30', '16:30']],
            'nicht' => ['it' => 'il sabato e nel tardo pomeriggio', 'de' => 'samstags und am späten Nachmittag', 'en' => 'on Saturdays and late afternoon']],
        'handwerk' => ['gut' => [['07:30', '08:30'], ['17:00', '18:30']],
            'nicht' => ['it' => 'di giorno, quando è in cantiere', 'de' => 'tagsüber, wenn er auf der Baustelle ist', 'en' => 'during the day when on site']],
        'werkstatt' => ['gut' => [['08:30', '10:00'], ['14:30', '16:30']], 'it' => [['08:30', '10:00'], ['15:30', '17:30']],
            'nicht' => ['it' => 'a mezzogiorno e poco prima della chiusura', 'de' => 'mittags und kurz vor Feierabend', 'en' => 'at lunchtime and just before closing']],
        'einzelhandel' => ['gut' => [['10:00', '11:30'], ['14:00', '16:00']], 'it' => [['10:00', '12:30'], ['17:00', '19:00']],
            'nicht' => ['it' => 'il sabato e nella pausa pranzo', 'de' => 'samstags und in der Mittagspause', 'en' => 'on Saturdays and at lunchtime']],
        'fitness' => ['gut' => [['10:00', '12:00'], ['14:00', '16:00']],
            'nicht' => ['it' => 'al mattino presto e la sera (ore di punta)', 'de' => 'früh morgens und abends (Stoßzeit)', 'en' => 'early morning and evening (peak time)']],
        '*' => ['gut' => [['09:30', '12:00'], ['14:30', '16:30']], 'it' => [['09:30', '12:30'], ['16:00', '18:00']],
            'nicht' => ['it' => 'nella pausa pranzo e dopo le 18', 'de' => 'in der Mittagspause und nach 18 Uhr', 'en' => 'at lunchtime and after 6 pm']],
    ];
    /** Branchen, die wie eine andere behandelt werden. */
    private const WIE = ['ferienwohnung' => 'hotel', 'agriturismo' => 'hotel', 'tourismus' => 'hotel', 'beauty' => 'friseur',
        'bau' => 'handwerk', 'autohaus' => 'werkstatt'];

    private const W = [
        'adresse'  => ['it' => 'Indirizzo', 'de' => 'Adresse', 'en' => 'Address'],
        'karte'    => ['it' => 'Mappa', 'de' => 'Karte', 'en' => 'Map'],
        'web'      => ['it' => 'Sito web', 'de' => 'Website', 'en' => 'Website'],
        'ohne_web' => ['it' => 'Nessun sito trovato', 'de' => 'Keine Website gefunden', 'en' => 'No website found'],
        'person'   => ['it' => 'Referente', 'de' => 'Ansprechpartner', 'en' => 'Contact'],
        'zeiten'   => ['it' => 'Orari (dal sito)', 'de' => 'Geöffnet (laut Website)', 'en' => 'Opening hours (website)'],
        'zeiten_leer' => ['it' => 'Il sito non indica orari', 'de' => 'Keine Öffnungszeiten auf der Website', 'en' => 'No opening hours on the website'],
        'offen'    => ['it' => 'ora aperto', 'de' => 'jetzt geöffnet', 'en' => 'open now'],
        'zu'       => ['it' => 'ora chiuso', 'de' => 'jetzt geschlossen', 'en' => 'closed now'],
        'beste'    => ['it' => 'Quando chiamare', 'de' => 'Beste Anrufzeit', 'en' => 'Best time to call'],
        'nicht'    => ['it' => 'Non chiamare {x}, né la domenica.', 'de' => 'Nicht anrufen {x} und sonntags.', 'en' => 'Don’t call {x} or on Sundays.'],
        'gut_jetzt' => ['it' => 'Ora è un buon momento', 'de' => 'Jetzt ist eine gute Zeit', 'en' => 'Now is a good time'],
        'analyse'  => ['it' => 'Cosa ha trovato l’analisi', 'de' => 'Was die Prüfung gefunden hat', 'en' => 'What the check found'],
        'chance'   => ['it' => 'Opportunità {n}/100', 'de' => 'Chance {n}/100', 'en' => 'Chance {n}/100'],
        'am'       => ['it' => 'analisi del {d}', 'de' => 'geprüft am {d}', 'en' => 'checked on {d}'],
        'nie'      => ['it' => 'Sito non ancora analizzato.', 'de' => 'Website noch nicht geprüft.', 'en' => 'Website not checked yet.'],
        'kein_web_analyse' => ['it' => 'Nessun sito funzionante: il vantaggio più grande è averne uno.', 'de' => 'Keine funktionierende Website: Der größte Gewinn ist, überhaupt eine zu haben.', 'en' => 'No working website: the biggest win is having one at all.'],
        'keine'    => ['it' => 'Nessun errore accertato con certezza.', 'de' => 'Keine sicher belegten Fehler.', 'en' => 'No confirmed errors.'],
        'sonst'    => ['it' => 'Altri punti tecnici', 'de' => 'Weitere technische Punkte', 'en' => 'Other technical points'],
    ];

    public static function wort(string $k, string $sp): string
    {
        return self::W[$k][$sp] ?? self::W[$k]['de'];
    }

    /** D1: Adresse, Kartenlink, Website, Ansprechpartner. */
    public static function steckbrief(array $f): array
    {
        $ort = trim(trim((string) ($f['plz'] ?? '')) . ' ' . self::ortName((string) ($f['stadt'] ?? '')));
        $adresse = implode(', ', array_filter([trim((string) ($f['adresse'] ?? '')), $ort]));
        $karte = $adresse !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim((string) $f['name'] . ', ' . $adresse)) : '';
        $url = trim((string) ($f['url'] ?? ''));
        if ($url === '' && trim((string) ($f['domain'] ?? '')) !== '') { $url = 'https://' . trim((string) $f['domain']) . '/'; }
        if ($url !== '' && !preg_match('~^https?://~i', $url)) { $url = 'https://' . $url; }
        $zeigen = $url !== '' ? preg_replace('~^https?://(www\.)?~i', '', rtrim($url, '/')) : '';
        return ['adresse' => $adresse, 'karte' => $karte, 'url' => $url, 'url_zeigen' => (string) $zeigen,
            'person' => trim((string) ($f['ansprechpartner'] ?? ''))];
    }

    private static function ortName(string $s): string
    {
        return $s === mb_strtoupper($s) ? mb_convert_case(mb_strtolower($s), MB_CASE_TITLE) : $s;
    }

    /** D3: gespeicherte Öffnungszeiten oder null. */
    public static function zeiten(array $f): ?array
    {
        $j = json_decode((string) ($f['oeffnungszeiten'] ?? ''), true);
        return is_array($j) && !empty($j['z']) && is_array($j['z']) ? $j : null;
    }

    /** „Lun–Ven 12:00–15:00, 19:00–23:00 · Sab 19:00–24:00“ */
    public static function zeitenText(array $z, string $sp): string
    {
        $tag = self::TAGE[$sp] ?? self::TAGE['de'];
        $proTag = [];
        foreach ($z as $x) {
            foreach ($x['t'] as $d) { $proTag[(int) $d][] = $x['v'] . '–' . ($x['b'] === '00:00' && $x['v'] !== '00:00' ? '24:00' : $x['b']); }
        }
        $gruppen = [];   // gleiche Zeiten an aufeinanderfolgenden Tagen zusammenfassen
        for ($d = 1; $d <= 7; $d++) {
            if (!isset($proTag[$d])) { continue; }
            $zeit = implode(', ', array_unique($proTag[$d]));
            $zeit = $zeit === '00:00–00:00' ? '24 h' : $zeit;
            $letzte = count($gruppen) - 1;
            if ($letzte >= 0 && $gruppen[$letzte]['z'] === $zeit && $gruppen[$letzte]['bis'] === $d - 1) { $gruppen[$letzte]['bis'] = $d; }
            else { $gruppen[] = ['von' => $d, 'bis' => $d, 'z' => $zeit]; }
        }
        return implode(' · ', array_map(static fn($g) => $tag[$g['von']] . ($g['bis'] > $g['von'] ? '–' . $tag[$g['bis']] : '') . ' ' . $g['z'], $gruppen));
    }

    /** Ist der Betrieb jetzt offen? Berücksichtigt Zeiten über Mitternacht. */
    public static function offen(array $z, DateTimeImmutable $jetzt): bool
    {
        $d = (int) $jetzt->format('N');
        $gestern = $d === 1 ? 7 : $d - 1;
        $hm = $jetzt->format('H:i');
        foreach ($z as $x) {
            $t = array_map('intval', $x['t']);
            if ($x['v'] === '00:00' && $x['b'] === '00:00' && in_array($d, $t, true)) { return true; }
            if ($x['b'] > $x['v'] || ($x['b'] === '00:00')) {
                $bis = $x['b'] === '00:00' ? '24:00' : $x['b'];
                if (in_array($d, $t, true) && $hm >= $x['v'] && $hm < $bis) { return true; }
            } else {   // über Mitternacht: heute ab v, oder gestern begonnen und noch vor b
                if (in_array($d, $t, true) && $hm >= $x['v']) { return true; }
                if (in_array($gestern, $t, true) && $hm < $x['b']) { return true; }
            }
        }
        return false;
    }

    /** D4: gute Zeitfenster, Stoßzeit in Worten, und ob jetzt ein gutes Fenster ist. */
    public static function anrufzeit(array $f, string $sp, DateTimeImmutable $jetzt): array
    {
        $b = (string) ($f['branche'] ?? '');
        $b = self::WIE[$b] ?? $b;
        $a = self::ANRUFZEIT[$b] ?? self::ANRUFZEIT['*'];
        $gut = strtoupper((string) ($f['land'] ?? '')) === 'IT' && isset($a['it']) ? $a['it'] : $a['gut'];
        $hm = $jetzt->format('H:i');
        $jetztGut = (int) $jetzt->format('N') !== 7
            && (bool) array_filter($gut, static fn($s) => $hm >= $s[0] && $hm < $s[1]);
        return ['gut' => implode(' · ', array_map(static fn($s) => $s[0] . '–' . $s[1], $gut)),
            'nicht' => strtr(self::wort('nicht', $sp), ['{x}' => $a['nicht'][$sp] ?? $a['nicht']['de']]),
            'jetzt' => $jetztGut];
    }

    /** Uhrzeit am Ort des Betriebs (Italien und Deutschland: gleiche Zone, aber sauber getrennt). */
    public static function ortszeit(array $f): DateTimeImmutable
    {
        $zone = strtoupper((string) ($f['land'] ?? '')) === 'DE' ? 'Europe/Berlin' : 'Europe/Rome';
        return new DateTimeImmutable('now', new DateTimeZone($zone));
    }

    /**
     * D2: Chance, Datum und bis zu fünf Punkte in einfachen Worten.
     * Punkte kommen aus den Gruppen der Branchen-Seiten (in der Sprache des
     * Partners), mit Messwert, wo einer da ist. Nur belegte Befunde.
     */
    public static function analyse(array $f, ?array $audit, array $befunde, string $sp): array
    {
        if (!$audit || (string) ($audit['status'] ?? '') !== 'fertig') { return ['geprueft' => false]; }
        $gruppeVon = [];
        foreach (BranchenStatistik::KENNZAHLEN as $k => $codes) { foreach ($codes as $c) { $gruppeVon[$c] = $k; } }
        foreach (['domain_tot', 'seite_nicht_erreichbar', 'platzhalter_text'] as $c) { $gruppeVon[$c] = 'ohne'; }
        usort($befunde, static fn($a, $b) => [(string) $b['status'] === 'VERIFIED', (int) $b['schwere']] <=> [(string) $a['status'] === 'VERIFIED', (int) $a['schwere']]);
        $punkte = []; $sonst = 0; $ohneWeb = false;
        foreach ($befunde as $b) {
            if ((string) $b['status'] !== 'VERIFIED') { continue; }
            $g = $gruppeVon[(string) $b['code']] ?? null;
            if ($g === null) { $sonst++; continue; }
            if ($g === 'ohne') { $ohneWeb = true; }
            if (isset($punkte[$g])) { continue; }
            $wert = AkquiseText::messwertText($b['messwert'] ?? null, $sp);
            $punkte[$g] = BranchenStatistik::LABEL[$g][$sp] ?? BranchenStatistik::LABEL[$g]['de'];
            if ($wert !== '' && preg_match('~\d~', $wert) && mb_strlen($wert) <= 24) { $punkte[$g] .= ' (' . $wert . ')'; }
        }
        $liste = array_slice(array_values($punkte), 0, 5);
        if ($sonst > 0 && count($liste) < 5) { $liste[] = self::wort('sonst', $sp) . ': ' . $sonst; }
        $datum = (string) ($audit['beendet_am'] ?? $audit['gestartet_am'] ?? '');
        return ['geprueft' => true, 'score' => $f['score'] !== null ? (int) $f['score'] : null,
            'am' => $datum !== '' ? date('d.m.Y', strtotime($datum)) : '', 'punkte' => $liste, 'ohne_web' => $ohneWeb];
    }
}
