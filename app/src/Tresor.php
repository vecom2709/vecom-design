<?php
declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Hosting.php';

/* ==========================================================================
   Tresor.php — Geheimnisse in settings nur versiegelt (Etappe 0b, 05.10.2026,
   Uwe: „Ja“ zu „Schnittstellen-Schlüssel verschlüsselt in settings“).

   Die Zugänge zu fremden Diensten (WhatsApp, Telegram, Meta, Briefdienst,
   Postfach, Strato …) lagen schon versiegelt in settings. Im Klartext standen
   noch sechs eigene Geheimnisse: die Schlüssel für Telefon-, Werkstatt- und
   Akquise-Worker auf Uwes PC, der Cron-Schlüssel, das Geheimnis hinter den
   Kundenstimmen-Formularen und der private Web-Push-Schlüssel. Wer eine
   Sicherung der Datenbank in die Hand bekommt, hätte damit die Worker steuern,
   den Cron auslösen und Hinweise im Namen von Vecom schicken können.

   Versiegelt wird mit Hosting::versiegeln (AES-256-GCM, Schlüssel nur in
   config.local.php — nicht in der Datenbank). Ein Datenbank-Abzug allein
   reicht damit nicht mehr.

   UMSTIEG OHNE AUSFALL: Ein alter Klartext-Wert wird beim ersten Lesen
   versiegelt zurückgeschrieben (nur, wenn noch derselbe Klartext dasteht —
   ein gleichzeitiger neuer Wert wird nie überschrieben). Der Wert selbst
   bleibt gleich: Die Worker auf Uwes PC und der Cron im KAS merken nichts.
   Gibt es keinen Hosting-Schlüssel (frische Einrichtung ohne
   config.local.php), bleibt es beim Klartext wie bisher, statt auszusperren.
   ========================================================================== */
final class Tresor
{
    /** Diese Einträge in settings sind Geheimnisse und werden nur versiegelt gespeichert. */
    public const SCHLUESSEL = ['telefon_schluessel', 'werkstatt_schluessel', 'akq_worker_schluessel',
        'cron_schluessel', 'partner_formular_geheim', 'webpush_privat',
        'brevo_key'];   // Phase 7a (05.10.2026): der Brevo-Schlüssel, derselbe Wert, nur versiegelt (Versand::schluessel)

    /** Klartext des Geheimnisses oder ''. */
    public static function lesen(string $skey): string
    {
        $roh = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$skey], '');
        if ($roh === '') { return ''; }
        $offen = Hosting::entsiegeln($roh);
        if (is_array($offen) && isset($offen['wert'])) { return (string) $offen['wert']; }
        // Ohne Hosting-Schlüssel lässt sich nichts öffnen und nichts versiegeln: dann bleibt es, wie es ist.
        // (Versiegeln würde hier sonst einen NEUEN Schlüssel anlegen und den Wert doppelt verpacken.)
        if ((string) Config::get('hosting_geheim', '') === '') { return $roh; }
        // Noch Klartext von früher: jetzt versiegeln — nur, wenn niemand inzwischen etwas Neues geschrieben hat.
        $blob = Hosting::versiegeln(['wert' => $roh]);
        if ($blob !== null) {
            Db::run('UPDATE settings SET svalue = ? WHERE skey = ? AND svalue = ?', [$blob, $skey, $roh]);
        }
        return $roh;
    }

    /** Speichert versiegelt (ohne Hosting-Schlüssel im Klartext, wie bisher). */
    public static function schreiben(string $skey, string $wert): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$skey, self::blob($wert)]);
    }

    /**
     * Nur anlegen, wenn es noch keinen Wert gibt (zwei gleichzeitige erste Aufrufe:
     * der erste gewinnt, beide lesen danach dasselbe). @return string der gültige Klartext
     */
    public static function anlegen(string $skey, string $wert): string
    {
        Db::run('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)', [$skey, self::blob($wert)]);
        return self::lesen($skey);
    }

    /** Vergleich in konstanter Zeit; ohne hinterlegten Wert immer falsch (der Zugang ist dann zu). */
    public static function stimmt(string $skey, string $eingabe): bool
    {
        $soll = self::lesen($skey);
        return $soll !== '' && $eingabe !== '' && hash_equals($soll, $eingabe);
    }

    private static function blob(string $wert): string
    {
        if ((string) Config::get('hosting_geheim', '') === '') { return $wert; }   // kein Schlüssel: Klartext wie bisher, nie einen neuen anlegen
        return Hosting::versiegeln(['wert' => $wert]) ?? $wert;
    }
}
