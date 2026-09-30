<?php
declare(strict_types=1);
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';

/**
 * Plattform-Anfragen (30.09.2026, Uwe: Ja zum Kundenfinder, Eingang 3).
 *
 * Auf Portalen wie ProntoPro oder Instapro schreibt ein Betrieb selbst
 * „cerco web designer“. Darauf darf man antworten -- er hat um Angebote
 * gebeten. Die Benachrichtigung des Portals landet im Akquise-Postfach
 * (direkt, wenn das Portal-Konto diese Adresse hat, oder weitergeleitet);
 * AkquisePostfach reicht alles, was keinem angeschriebenen Betrieb gehört,
 * hierher. Erkannt wird am Absender oder, bei einer Weiterleitung, an der
 * Portal-Adresse im Text.
 *
 * WAS NICHT PASSIERT: Das System antwortet nicht selbst. Die meisten Portale
 * nehmen Antworten nur auf ihrer eigenen Seite an, oft gegen Guthaben -- das
 * entscheidet Uwe je Anfrage. Hier liegt die Anfrage mit einer fertigen
 * Antwort (Analyse-Link, Konfigurator, keine Preise) zum Kopieren.
 */
final class AkquisePlattform
{
    /** Absender-Domain → Name des Portals. Weitere lassen sich unter settings.akq_plattformen ergänzen (domain=Name, je Zeile). */
    public const PLATTFORMEN = [
        'prontopro.it' => 'ProntoPro', 'instapro.it' => 'Instapro', 'habitissimo.it' => 'Habitissimo',
        'starofservice.it' => 'StarOfService', 'starofservice.com' => 'StarOfService',
        'myhammer.de' => 'MyHammer', 'blauarbeit.de' => 'Blauarbeit', 'check24.de' => 'Check24 Profis',
    ];
    public const AUFBEWAHRUNG_TAGE = 90;

    /** @return array<string,string> */
    public static function plattformen(): array
    {
        $alle = self::PLATTFORMEN;
        foreach (preg_split('~\R~', (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_plattformen'", [], '')) ?: [] as $z) {
            if (preg_match('~^\s*([a-z0-9.-]+\.[a-z]{2,})\s*=\s*(.{2,40})$~i', $z, $t)) { $alle[mb_strtolower($t[1])] = trim($t[2]); }
        }
        return $alle;
    }

    /** Welches Portal? Absender-Domain (auch Subdomains wie notifiche.prontopro.it) oder bei Weiterleitung die Adresse im Text. */
    public static function erkennen(array $m): ?array
    {
        $alle = self::plattformen();
        $domain = mb_strtolower(substr((string) strrchr((string) $m['von_adresse'], '@'), 1));
        foreach ($alle as $d => $name) {
            if ($domain === $d || str_ends_with($domain, '.' . $d)) { return ['name' => $name, 'domain' => $d]; }
        }
        $weiter = (bool) preg_match('~^\s*(fwd?|wg|i|tr|inoltro)\s*:~i', (string) $m['betreff']);
        if ($weiter) {
            $kopf = mb_substr((string) $m['text'], 0, 4000);
            foreach ($alle as $d => $name) {
                if (preg_match('~[@.]' . preg_quote($d, '~') . '\b~i', $kopf)) { return ['name' => $name, 'domain' => $d]; }
            }
        }
        return null;
    }

    /** @return string neu | doppelt | fremd */
    public static function aufnehmen(array $m, string $nachrichtId): string
    {
        $p = self::erkennen($m);
        if ($p === null) { return 'fremd'; }
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_plattform WHERE nachricht_id = ?', [$nachrichtId], 0) > 0) { return 'doppelt'; }
        $sprache = preg_match('~\.(de|at|ch)$~', $p['domain']) ? 'de' : 'it';
        $id = (int) Db::insert('akq_plattform', [
            'plattform' => $p['name'], 'von' => mb_substr((string) $m['von'], 0, 190), 'betreff' => mb_substr((string) $m['betreff'], 0, 255),
            'text' => mb_substr(trim((string) $m['text']), 0, 6000), 'sprache' => $sprache, 'nachricht_id' => mb_substr($nachrichtId, 0, 190),
            'eingang_am' => $m['datum'] ?? date('Y-m-d H:i:s'),
        ]);
        try { Events::melden('akquise_plattform', 'Neue Plattform-Anfrage: ' . $p['name'], 'gut', mb_substr((string) $m['betreff'], 0, 200) . ' — Antwort liegt bereit', 'akquise#plattform'); } catch (Throwable $e) { }
        try {
            require_once __DIR__ . '/Zuruf.php';
            Zuruf::vormerken('akquise_plattform', 'Neue Kunden finden: Neue Anfrage auf ' . $p['name'] . '. Die Antwort liegt fertig in der Verwaltung.', 10);
        } catch (Throwable $e) { }
        return $id > 0 ? 'neu' : 'fremd';
    }

    /** Die vorbereitete Antwort -- ohne Preise, mit Analyse und Konfigurator (Uwes Regel vom 04.09.2026). */
    public static function antwort(string $sprache): string
    {
        $w = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        if ($sprache === 'de') {
            return "Guten Tag,\n\ndanke für Ihre Anfrage. Ich bin Uwe von Vecom Design in Aragona (AG) und baue Websites für Betriebe wie Ihren.\n\n"
                . "Wenn Sie schon eine Website haben, sehen Sie hier in wenigen Sekunden, wie sie dasteht (kostenlos, ohne Anmeldung):\n$w/analisi.php?lang=de\n\n"
                . "Noch keine Website? Hier sehen Sie sofort eine Skizze mit Ihrem Namen:\n$w/de/#vorschau\n\n"
                . "Eine ehrliche Spanne für Ihr Vorhaben bekommen Sie in zwei Minuten, unverbindlich:\n$w/bedarf.php?lang=de\n\n"
                . "Gern schicke ich Ihnen die ausführliche Analyse per E-Mail -- schreiben Sie mir einfach Ihre Adresse.\n\nViele Grüße\nUwe · Vecom Design";
        }
        return "Buongiorno,\n\ngrazie per la sua richiesta. Sono Uwe di Vecom Design, ad Aragona (AG), e realizzo siti per attività come la sua.\n\n"
            . "Se ha già un sito, qui vede in pochi secondi com’è messo (gratis, senza registrazione):\n$w/analisi.php?lang=it\n\n"
            . "Non ha ancora un sito? Qui vede subito una bozza con il suo nome:\n$w/#vorschau\n\n"
            . "Una stima onesta per il suo progetto in due minuti, senza impegno:\n$w/bedarf.php?lang=it\n\n"
            . "Se vuole, le mando l’analisi completa via e-mail: mi scriva pure il suo indirizzo.\n\nCordiali saluti\nUwe · Vecom Design";
    }

    /** @return list<array<string,mixed>> */
    public static function offen(int $n = 20): array
    {
        return Db::all("SELECT id, plattform, von, betreff, text, sprache, eingang_am FROM akq_plattform WHERE status = 'offen' ORDER BY id DESC LIMIT " . max(1, $n));
    }

    public static function erledigen(int $id): void
    {
        Db::run("UPDATE akq_plattform SET status = 'erledigt', erledigt_am = NOW() WHERE id = ? AND status = 'offen'", [$id]);
    }

    /** Text und Absender nach 90 Tagen leeren -- die Zahl je Portal bleibt für die Auswertung. */
    public static function aufraeumen(?int $jetzt = null): int
    {
        $grenze = date('Y-m-d H:i:s', ($jetzt ?? time()) - self::AUFBEWAHRUNG_TAGE * 86400);
        return Db::run("UPDATE akq_plattform SET text = NULL, von = '', betreff = '' WHERE created_at < ? AND (text IS NOT NULL OR von <> '')", [$grenze])->rowCount();
    }
}
