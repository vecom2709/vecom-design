<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Events.php';

/* ==========================================================================
   MkAnmeldungen.php — alle Anmeldungen an einer Stelle
   (01.10.2026, Uwe: „Wenn [Claude] nirgends selbst anmelden kann, dann gib
   alles in Verwaltung › Marketing, dass ich direkt auf die Seiten komme zum
   Registrieren — nach Bestätigung soll alles Weitere automatisch gehen.“)

   Konten anlegen, Passwörter, Captchas und Bestätigungsmails bleiben bei
   Uwe — das darf und soll keine Maschine für ihn tun. Alles davor und
   danach erledigt die Verwaltung:

     vorher   ein Knopf führt direkt auf die Anmeldeseite; bei Verzeichnissen
              legt derselbe Klick zuerst die eigenen Zähl-Links an
              (Verzeichnisse::vorbereiten), damit der Ausfüll-Knopf dort
              schon den richtigen Link einträgt
     danach   „Erledigt“ — bei Verzeichnissen gilt der Eintrag als
              eingereicht (Erinnerung nach einer Woche, Zählung über die
              eigenen Links); bei Konten wird es vermerkt, und sobald der
              Schlüssel unter Kanäle steht, postet Vecom selbst.

   Gespeichert wird nur ein Vermerk je Konto (settings mk_konto_SCHLUESSEL =
   Datum) — keine Zugangsdaten.
   ========================================================================== */

final class MkAnmeldungen
{
    /** Schlüssel => [Name, Anmeldeseite, was Uwe dort tut, was danach von selbst geht] */
    public const KONTEN = [
        'facebook'  => ['Facebook-Seite', 'https://www.facebook.com/pages/create',
                        'Seite „Vecom Design“ anlegen (Kategorie Webdesigner), danach unter Kanäle › Facebook-Seite verbinden.',
                        'Vecom postet zur Sendezeit selbst; „Kommentar → Nachricht“ lässt sich einschalten.'],
        'instagram' => ['Instagram (Profi-Konto)', 'https://www.instagram.com/accounts/emailsignup/',
                        'Konto anlegen, in den Einstellungen auf „Professionelles Konto“ umstellen und mit der Facebook-Seite verknüpfen.',
                        'Postet zusammen mit Facebook automatisch.'],
        'linkedin'  => ['LinkedIn-Unternehmensseite', 'https://www.linkedin.com/company/setup/new/',
                        'Seite anlegen. Den Antrag für automatisches Posten erst mit Partita IVA stellen — die Texte stehen unter Kanäle.',
                        'Bis zur Freigabe kommen die Beiträge aufs Handy, danach postet Vecom selbst.'],
        'youtube'   => ['YouTube-Kanal', 'https://www.youtube.com/create_channel',
                        'Kanal „Vecom Design“ anlegen; den Antrag mit den fertigen Texten unter Kanäle stellen.',
                        'Shorts kommen bis zur Freigabe aufs Handy, danach lädt Vecom sie selbst hoch.'],
        'tiktok'    => ['TikTok', 'https://www.tiktok.com/signup',
                        'Konto anlegen.',
                        'Videos kommen zur Sendezeit aufs Handy — ein Tipp auf „Teilen“.'],
    ];

    private static function wert(string $k): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''); } catch (Throwable $e) { return ''; }
    }

    /** Stand eines Kontos: verbunden (postet selbst), angelegt (vermerkt), offen. */
    public static function kontoStand(string $k): string
    {
        try {
            if (in_array($k, ['facebook', 'instagram'], true)) {
                require_once __DIR__ . '/MkKanaele.php';
                if (!empty(MkKanaele::stand()[$k]['bereit'])) { return 'verbunden'; }
            } else {
                require_once __DIR__ . '/MkPlattform.php';
                if (MkPlattform::bereit($k)) { return 'verbunden'; }
            }
        } catch (Throwable $e) { /* dann nach dem Vermerk */ }
        return self::wert('mk_konto_' . $k) !== '' ? 'angelegt' : 'offen';
    }

    /** Uwe hat das Konto angelegt (oder nimmt den Vermerk zurück). */
    public static function kontoVermerken(string $k, bool $angelegt = true): ?string
    {
        if (!isset(self::KONTEN[$k])) { return 'Unbekanntes Konto.'; }
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['mk_konto_' . $k, $angelegt ? date('Y-m-d') : '']);
        Events::pruefspur('konto_vermerkt', 'settings', null, [], ['konto' => $k, 'angelegt' => $angelegt]);
        return null;
    }

    /**
     * Was noch fehlt — Konten zuerst, dann die Verzeichnisse des Landes
     * (offene und eingereichte; „später“ und „nicht eintragen“ nicht).
     * @return array{konten:list<array>, eintraege:list<array>, offen:int}
     */
    public static function liste(string $land): array
    {
        $konten = [];
        foreach (self::KONTEN as $k => [$name, $url, $tun, $danach]) {
            $konten[] = ['schluessel' => $k, 'name' => $name, 'url' => $url, 'tun' => $tun, 'danach' => $danach, 'stand' => self::kontoStand($k)];
        }
        $eintraege = [];
        try {
            require_once __DIR__ . '/Verzeichnisse.php';
            $t = Verzeichnisse::fuerLand(Verzeichnisse::liste(), $land);
            foreach (['land' => array_merge($t['wirkt'], $t['weitere']), 'international' => $t['international']] as $teil => $reihe) {
                foreach ($reihe as $e) {
                    if ($e['art'] === 'kanal' || !in_array($e['status'], ['offen', 'eingereicht'], true)) { continue; }
                    $eintraege[] = $e + ['teil' => $teil];
                }
            }
        } catch (Throwable $e) { /* Migration noch offen */ }
        $offen = count(array_filter($konten, static fn($k) => $k['stand'] === 'offen')) + count(array_filter($eintraege, static fn($e) => $e['status'] === 'offen'));
        return ['konten' => $konten, 'eintraege' => $eintraege, 'offen' => $offen];
    }

    /**
     * Ein Verzeichnis öffnen: erst die eigenen Links anlegen, dann die
     * Adresse der Stelle zurückgeben (nur eine geprüfte https-Adresse aus
     * der eigenen Liste — nie eine aus der Anfrage).
     * @return array{ok:bool, url:string, text:string}
     */
    public static function verzeichnisOeffnen(int $id): array
    {
        require_once __DIR__ . '/Verzeichnisse.php';
        $e = Verzeichnisse::laden($id);
        if ($e === null) { return ['ok' => false, 'url' => '', 'text' => 'Diesen Eintrag gibt es nicht.']; }
        if (!Verzeichnisse::urlOk((string) $e['url'])) { return ['ok' => false, 'url' => '', 'text' => 'Die Adresse dieses Eintrags ist ungültig.']; }
        $v = Verzeichnisse::vorbereiten($id);
        return ['ok' => true, 'url' => (string) $e['url'], 'text' => $v['text']];
    }
}
