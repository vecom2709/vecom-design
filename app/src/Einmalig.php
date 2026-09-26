<?php
declare(strict_types=1);

/**
 * Einmal zu erledigen — die Aufgaben aus dem Cockpit, jetzt unter „Heute“
 * (26.09.2026, Uwe: „ja“ zu Vorschlag 6).
 *
 * Das Cockpit führte eine eigene Liste, von Hand gepflegt, Stand 29.08.
 * Zwei Startseiten mit zwei Listen sind zwei Wahrheiten: Die Kundenstimme
 * stand dort noch als „fehlt“, als es das Stimmen-System längst gab. Hier
 * steht, was davon am 26.09. noch offen sein KANN; wo die Datenbank oder
 * das Repository es belegt, hakt es sich selbst ab, sonst genügt Uwes Klick.
 * Die Betreuungslaufzeit fehlt mit Absicht: „[bitte ergänzen]“ steht auf
 * keiner Seite mehr.
 */
final class Einmalig
{
    /** Schlüssel => [Titel, Warum, Selbstprüfung oder null] */
    public static function liste(): array
    {
        return [
            'angebote_bestand' => ['Angebote an Cavaleri, Charme Color und Boulevard schicken',
                'Laufende Projekte ohne unterschriebenes Angebot: Bei Streit hast du nichts in der Hand. Im Konfigurator erfassen, Angebot daraus erzeugen.', null],
            'zugaenge_erneuern' => ['FTP-Passwort ändern und altes GitHub-Token widerrufen',
                'Beide standen im Klartext in einem Chatverlauf. Neues FTP-Passwort im KAS, dann bei GitHub das Secret FTP_PASSWORD anpassen und einmal pushen.', null],
            'kanaele' => ['Instagram und TikTok für Vecom Design anlegen',
                'Facebook ist eingetragen. Die Symbole für die anderen stehen schon in der Fußzeile und warten nur auf die Adressen (assets/js/social.js).',
                static function (): bool {
                    $js = (string) @file_get_contents(dirname(__DIR__, 2) . '/assets/js/social.js');
                    return preg_match("~instagram:\\s*'https?://~", $js) === 1 && preg_match("~tiktok:\\s*'https?://~", $js) === 1;
                }],
            'kundenstimme' => ['Erste Kundenstimme auf die Website bringen',
                'Ein echter Satz eines Kunden wiegt mehr als jede weitere Funktion. Der Kunde schreibt ihn auf seiner Seite, du gibst ihn unter Weiterempfehlung → Kundenstimmen frei.',
                static fn(): bool => (int) Db::wert('SELECT COUNT(*) FROM stimmen WHERE veroeffentlicht_am IS NOT NULL AND demo = 0', [], 0) > 0],
        ];
    }

    /** @return list<array{schluessel:string,titel:string,warum:string,selbst:bool}> nur die offenen */
    public static function offen(): array
    {
        $raus = [];
        foreach (self::liste() as $k => [$titel, $warum, $pruef]) {
            if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', ['einmalig_' . $k], '') !== '') { continue; }
            $selbstErledigt = false;
            if ($pruef !== null) { try { $selbstErledigt = (bool) $pruef(); } catch (Throwable $e) { } }
            if ($selbstErledigt) { continue; }
            $raus[] = ['schluessel' => $k, 'titel' => $titel, 'warum' => $warum, 'selbst' => $pruef !== null];
        }
        return $raus;
    }

    public static function erledigt(string $k): bool
    {
        if (!array_key_exists($k, self::liste())) { return false; }
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
                ['einmalig_' . $k, date('Y-m-d H:i')]);
        return true;
    }
}
