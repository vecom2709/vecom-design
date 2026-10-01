<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   TelegramApp.php — der Preis-Rechner als Telegram-Mini-App (30.09.2026,
   Uwe: „kann man es so machen, dass der Bot direkt im Kanal ist“ → „ja mach
   automatisch“).

   WARUM EINE MINI-APP UND KEIN GESPRÄCH IM KANAL

   Was ein Bot in einen Kanal schreibt, sehen alle Abonnenten; ein privates
   Gespräch im Kanal gibt es bei Telegram nicht. Eine Mini-App dagegen
   öffnet sich als Fenster ÜBER dem Kanal — schließen, und man ist wieder
   dort, wo man war. Der Knopf im Kanal ist ein Link
   t.me/BOT/KURZNAME?startapp=kanal-de-preis.

   KEIN ZWEITES SYSTEM

   Das Fenster zeigt bedarf.php — den Konfigurator der Website, mit
   denselben acht Fragen, demselben Live-Richtwert und demselben Absenden
   (Bedarf::absenden → Anfrage::annehmen, Herkunft „telegram“). Hier steht
   nur, wie der Start-Parameter gelesen und der Bedarf angelegt wird.

   WAS TELEGRAM MITGIBT — UND WAS WIR DAMIT TUN

   Telegram hängt Startdaten (Kennung, Vorname, Sprache, Signatur) an die
   Adresse — hinter das #. Was hinter dem # steht, schickt der Browser nie an
   den Server, und unser Skript liest es nicht aus. Beim Server kommt nur
   tgWebAppStartParam an, also das, was im Knopf steht (kanal-de-preis).
   ========================================================================== */
final class TelegramApp
{
    /** Einstiege wie im Bot-Menü; „neu“ beantwortet die Bestandsfrage schon. */
    public const EINSTIEGE = ['preis', 'neu', 'besser'];

    /**
     * Ziele des Vecom-Fensters (telegram-menue.php) — seit dem 01.10.2026
     * der Weg für alle außer dem Admin: Menü, Website-Check, Themen mit
     * Anfrage, Partner, Domain, Kundenbereich.
     */
    public const ZIELE = ['menu', 'pruefen', 'ki', 'bots', 'dreid', 'logo', 'mensch', 'partner', 'hosting', 'kunde'];

    /** Quelle aus einem Link: ein Wort (kanal, web, bot …), eine Kampagne (m_CODE[_WM]) oder ein Partner (p_CODE). */
    public const QUELLE_MUSTER = '/^(?:[a-z]{2,12}|m_[a-z0-9][a-z0-9-]{2,23}(?:_[a-z0-9][a-z0-9-]{0,11})?|p_[a-z0-9]{5,16})$/';

    public static function quelleOk(string $q): bool { return preg_match(self::QUELLE_MUSTER, $q) === 1; }

    /**
     * Den Start-Parameter lesen: QUELLE-SPRACHE-EINSTIEG, jedes Stück optional.
     * Unbekanntes fällt still auf den Normalfall zurück — ein kaputter Link
     * soll den Rechner öffnen, nicht eine Fehlermeldung.
     *
     * @return array{quelle:string, sprache:string, einstieg:string}
     */
    public static function lesen(string $param, string $sprache = ''): array
    {
        $a = ['quelle' => 'telegram', 'sprache' => in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it', 'einstieg' => 'preis',
              'rechner' => false, 'ziel' => 'menu'];
        $param = strtolower($param);
        /* Kampagne oder Partner (01.10.2026): der ganze Parameter ist die Quelle,
           geöffnet wird das Menü — Kampagnen-Codes enthalten selbst Bindestriche. */
        if (preg_match('/^[mp]_/', $param)) {
            if (self::quelleOk($param)) { $a['quelle'] = $param; }
            return $a;
        }
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $param)) { return $a; }
        foreach (explode('-', $param) as $i => $teil) {
            if (in_array($teil, ['it', 'de', 'en'], true)) { $a['sprache'] = $teil; }
            elseif (in_array($teil, self::EINSTIEGE, true)) { $a['einstieg'] = $teil; $a['rechner'] = true; }
            elseif ($i > 0 && in_array($teil, self::ZIELE, true)) { $a['ziel'] = $teil; }
            elseif ($i === 0 && preg_match('/^[a-z]{2,12}$/', $teil)) { $a['quelle'] = $teil; }
        }
        // Ein Link ohne Ziel und ohne Einstieg (z. B. „kanal-de“) öffnete bis heute den Rechner — das bleibt so.
        if (!$a['rechner'] && $a['ziel'] === 'menu' && !preg_match('/-menu(?:-|$)/', $param) && substr_count($param, '-') >= 1) { $a['rechner'] = true; }
        return $a;
    }

    /** Einen neuen Bedarf anlegen, wie der Bot es tut. @return string Schlüssel */
    public static function neuerBedarf(array $start): string
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';
        Baukasten::sicherstellen();
        $b = Bedarf::starten($start['sprache']);
        // Growth Engine T2: Preisrechner gestartet (je Rechner einmal — hier entsteht er). „Mini-App geöffnet“ zählt telegram-app.php beim Öffnen.
        require_once __DIR__ . '/TelegramWachstum.php';
        TelegramWachstum::zaehlen('rechner', (string) $start['quelle']);
        if ($start['einstieg'] === 'neu') {
            $schritt = 1;
            foreach (Baukasten::SCHRITTE as $i => $namen) { if (in_array('bestand', $namen, true)) { $schritt = $i + 1; } }
            Bedarf::speichern((int) $b['id'], ['bestand' => 'neu'], $schritt);
        }
        return (string) $b['token'];
    }

    /**
     * Kam das Fenster über eine Kampagne (m_CODE) oder einen Partner (p_CODE),
     * gilt dasselbe wie für einen Klick auf /k/CODE bzw. /p/CODE: ein Besuch
     * in der Spur, beim Partner der Klick und sein Keks (mit dem fragt später
     * die Anfrage, wem der Kunde gehört). Der Kanal heißt „telegram“.
     * Unbekannte oder pausierte Codes: nichts, still.
     */
    public static function quelleMerken(string $quelle, string $sprache): void
    {
        $s = ['ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'referrer' => '',
              'sprache' => $sprache, 'get' => ['utm_source' => 'telegram'], 'einstieg' => '/telegram-app.php', 'ref_link' => 'telegram:' . $quelle];
        if (str_starts_with($quelle, 'm_')) {
            require_once __DIR__ . '/TelegramWachstum.php';
            [$k, $cr] = TelegramWachstum::kampagneAusStart($quelle);
            if ($k === null) { return; }
            require_once __DIR__ . '/Spur.php';
            Spur::kampagnenBesuch($k, $cr, $s);
            return;
        }
        if (str_starts_with($quelle, 'p_')) {
            require_once __DIR__ . '/Partner.php';
            $p = Partner::ausCode(substr($quelle, 2));
            if ($p === null) { return; }
            if (Partner::echterBesuch($p, $s['ua'], $_COOKIE)) { Partner::klick((int) $p['id'], 'telegram'); }
            // SameSite=None: Telegram Web zeigt das Fenster in einem fremden Rahmen — sonst käme der Keks nie an.
            setcookie(Partner::KEKS, (string) $p['code'] . ':telegram', ['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
            $_COOKIE[Partner::KEKS] = (string) $p['code'] . ':telegram';
            require_once __DIR__ . '/Spur.php';
            Spur::partnerBesuch($p, 'telegram', $s);
        }
    }

    /** Kurzname der Mini-App bei @BotFather (leer = noch nicht angemeldet). */
    public static function name(): string
    {
        $n = Telegram::einstellung('tg_app_name');
        return preg_match('/^[A-Za-z0-9_]{3,30}$/', $n) ? $n : '';
    }

    /** Link, der die Mini-App öffnet — oder leer, solange es keine gibt. */
    public static function link(string $param): string
    {
        $bot = Telegram::einstellung('tg_name');
        $app = self::name();
        if ($bot === '' || $app === '') { return ''; }
        return 'https://t.me/' . $bot . '/' . $app . '?startapp=' . rawurlencode($param);
    }

    /** Die Adresse, die bei @BotFather als Web-App-URL eingetragen wird. */
    public static function adresse(): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/telegram-app.php';
    }

    /**
     * Wer darf die Seite einbetten? Telegram Web zeigt Mini-Apps in einem
     * iframe; die App auf dem Handy und am Rechner in einer eigenen Ansicht.
     * frame-ancestors hat in allen heutigen Browsern Vorrang vor dem
     * X-Frame-Options aus der .htaccess.
     */
    public const EINBETTEN = "frame-ancestors 'self' https://web.telegram.org https://*.web.telegram.org";
}
