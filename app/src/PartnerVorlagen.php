<?php
declare(strict_types=1);

/**
 * Werbevorlagen, FAQ und Leitfaden im Admin pflegen (27.09.2026, Uwe: Ja).
 *
 * Die Standardtexte stehen weiter in Texte.php (dreisprachig, geprüft von der
 * Kette). Uwe kann jeden davon je Sprache überschreiben; ein leeres Feld heißt
 * „Standard“. So bleibt der Standard die Wahrheit, bis Uwe bewusst etwas
 * anderes will -- und ein Fehler in seiner Fassung ist mit einem Klick weg.
 *
 * PLATZHALTER SIND PFLICHT: Steht im Standard {link}, muss er auch in der
 * eigenen Fassung stehen. Eine Vorlage ohne Link schickt Kunden ins Leere und
 * dem Partner geht die Provision verloren -- genau das, was dieses ganze
 * Paket verhindern soll.
 */
final class PartnerVorlagen
{
    public const MAX = 4000;
    private const PLATZHALTER = ['{link}', '{name}', '{satz}', '{min}', '{tage}', '{zuordnung}'];

    /** @var array<string,array<string,string>>|null */
    private static ?array $eigen = null;

    /**
     * Alles, was sich pflegen lässt.
     * @return array<string, array{gruppe:string, titel:string, standard:array<string,string>}>
     */
    public static function katalog(): array
    {
        require_once __DIR__ . '/Texte.php';
        $aus = [];
        foreach (Texte::PARTNER_WERBUNG['vorlagen'] as $kanal => $liste) {
            foreach ($liste as $id => $v) {
                $name = is_array(Texte::PARTNER_WERBUNG['namen'][$kanal] ?? null) ? Texte::PARTNER_WERBUNG['namen'][$kanal]['de'] : (string) (Texte::PARTNER_WERBUNG['namen'][$kanal] ?? $kanal);
                if (isset($v['betreff'])) {
                    $aus["werbung.$kanal.$id.betreff"] = ['gruppe' => 'Werbevorlagen · ' . $name, 'titel' => $v['titel']['de'] . ' — Betreff', 'standard' => $v['betreff']];
                }
                $aus["werbung.$kanal.$id.text"] = ['gruppe' => 'Werbevorlagen · ' . $name, 'titel' => $v['titel']['de'], 'standard' => $v['text']];
            }
        }
        $aus['faq'] = ['gruppe' => 'Häufige Fragen der Partner', 'titel' => 'FAQ (Partnerseite)', 'standard' => Texte::PARTNER['faq']];
        foreach (Texte::PARTNER_LEITFADEN as $i => $a) {
            $aus["leitfaden.$i.titel"] = ['gruppe' => 'Gesprächsleitfaden', 'titel' => 'Abschnitt ' . ($i + 1) . ' — Überschrift', 'standard' => $a['titel']];
            $aus["leitfaden.$i.text"] = ['gruppe' => 'Gesprächsleitfaden', 'titel' => 'Abschnitt ' . ($i + 1) . ' — ' . $a['titel']['de'], 'standard' => $a['text']];
        }
        return $aus;
    }

    /** Der gültige Text: Uwes Fassung, sonst der Standard. */
    public static function text(string $schluessel, string $sprache, string $standard): string
    {
        self::$eigen ??= self::laden();
        $t = self::$eigen[$schluessel][$sprache] ?? '';
        return $t !== '' ? $t : $standard;
    }

    /** @return array<string,string> Sprache → eigener Text (nur vorhandene) */
    public static function eigene(string $schluessel): array
    {
        self::$eigen ??= self::laden();
        return self::$eigen[$schluessel] ?? [];
    }

    /**
     * Speichert Uwes Fassung (leer = zurück zum Standard).
     * @return string ok|unbekannt|zu_lang|platzhalter:{link}
     */
    public static function speichern(string $schluessel, string $sprache, string $text): string
    {
        $kat = self::katalog();
        if (!isset($kat[$schluessel]) || !in_array($sprache, ['it', 'de', 'en'], true)) { return 'unbekannt'; }
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '' || $text === trim((string) ($kat[$schluessel]['standard'][$sprache] ?? ''))) {
            Db::run('DELETE FROM partner_vorlagen_text WHERE schluessel = ? AND sprache = ?', [$schluessel, $sprache]);
            self::$eigen = null;
            return 'ok';
        }
        if (mb_strlen($text) > self::MAX) { return 'zu_lang'; }
        foreach (self::PLATZHALTER as $ph) {
            if (str_contains((string) ($kat[$schluessel]['standard'][$sprache] ?? ''), $ph) && !str_contains($text, $ph)) { return 'platzhalter:' . $ph; }
        }
        Db::run('INSERT INTO partner_vorlagen_text (schluessel, sprache, text, am) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE text = VALUES(text), am = VALUES(am)', [$schluessel, $sprache, $text, date('Y-m-d H:i:s')]);
        self::$eigen = null;
        return 'ok';
    }

    /** Vorschau mit Beispielwerten -- so sieht es beim Partner „Maria Rossi“ aus. */
    public static function vorschau(string $schluessel, string $text): string
    {
        $kanal = explode('.', $schluessel)[1] ?? 'whatsapp';
        return strtr($text, ['{link}' => 'https://vecom-design.it/p/MARIA26/' . $kanal, '{name}' => 'Maria Rossi', '{satz}' => '10 %', '{min}' => '50,00 €', '{tage}' => '14', '{zuordnung}' => '12']);
    }

    /** Für die Kette. */
    public static function vergessen(): void { self::$eigen = null; }

    /** @return array<string,array<string,string>> */
    private static function laden(): array
    {
        $aus = [];
        try {
            foreach (Db::all('SELECT schluessel, sprache, text FROM partner_vorlagen_text') as $z) { $aus[(string) $z['schluessel']][(string) $z['sprache']] = (string) $z['text']; }
        } catch (Throwable $e) { /* Tabelle noch nicht da: Standard */ }
        return $aus;
    }
}
