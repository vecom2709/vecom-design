<?php
declare(strict_types=1);

require_once __DIR__ . '/Config.php';

/**
 * Die gewaehlte Sprache -- ueber alle Seiten hinweg.
 *
 * DER BEFUND VOM 23.09.2026
 *
 * Uwe: "Entsprechend welche Sprache der Besucher waehlt, muss jede Seite,
 * jede Unterseite, egal wo, in der gewaehlten Sprache sein."
 *
 * So war es nicht. Jede Seite loeste die Frage fuer sich:
 *
 *   - Die statischen Seiten merkten die Wahl im localStorage und in einem
 *     Keks (vecomlang) -- aber nur sie. Keine einzige PHP-Seite hat den Keks
 *     je gesetzt; wer auf der Kundenseite auf DE schaltete, bekam auf
 *     legal.html wieder Italienisch.
 *   - Der Rechtsfuss verlinkte legal.html ohne Sprache. Die Seite dort faellt
 *     ohne Hinweis auf Italienisch zurueck -- also las ein deutscher Kunde
 *     die AGB auf Italienisch, ausgerechnet die.
 *   - angebot.php nahm allein die Sprache des Angebots und hatte gar keinen
 *     Umschalter: Wer ein italienisch erstelltes Angebot bekam, konnte nicht
 *     auf Deutsch lesen, was er da annimmt.
 *
 * JETZT
 *
 * Eine Stelle beantwortet die Frage, und sie beantwortet sie ueberall
 * gleich: ausdrueckliche Wahl in der Adresse (?lang=), sonst der Keks,
 * sonst was an der Sache haengt (Kunde, Angebot), sonst Italienisch. Jede
 * Seite merkt die Sprache, in der sie ausgeliefert wurde -- damit die
 * naechste Seite sie kennt, auch eine statische.
 */
final class Sprache
{
    public const ALLE = ['it', 'de', 'en'];
    public const STANDARD = 'it';

    /** Der Name des Kekses. Dieselbe Zeichenkette benutzt assets/js/app.js. */
    public const KEKS = 'vecomlang';

    /** Die erste gueltige Sprache aus der Reihe -- sonst Italienisch. */
    public static function waehlen(?string ...$kandidaten): string
    {
        foreach ($kandidaten as $k) {
            $s = strtolower(trim((string) $k));
            if (in_array($s, self::ALLE, true)) { return $s; }
        }
        return self::STANDARD;
    }

    /**
     * Die Sprache dieser Anfrage.
     *
     * Reihenfolge mit Absicht: Was in der Adresse steht, ist eine Wahl, die
     * der Besucher eben getroffen hat. Der Keks ist die Wahl von vorhin. Was
     * an der Sache haengt -- die Sprache des Kunden, des Angebots -- kommt
     * danach: Sie ist eine gute Vermutung, aber keine Wahl.
     */
    public static function ausAnfrage(?string ...$vorgaben): string
    {
        $keks = isset($_COOKIE[self::KEKS]) ? (string) $_COOKIE[self::KEKS] : null;
        return self::waehlen((string) ($_REQUEST['lang'] ?? ''), ...array_merge([$keks], $vorgaben));
    }

    /** Hat der Besucher die Sprache gerade ausdruecklich gewaehlt? */
    public static function gewaehlt(): bool
    {
        return in_array(strtolower(trim((string) ($_REQUEST['lang'] ?? ''))), self::ALLE, true);
    }

    /**
     * Die Sprache fuer die naechste Seite merken.
     *
     * Ein Jahr, nur uebertragen, wenn die Seite selbst uebertragen wird
     * (secure bei https), und ohne httponly: assets/js/app.js liest denselben
     * Keks, damit auch die statischen Seiten folgen.
     */
    public static function merken(string $sprache): void
    {
        $s = self::waehlen($sprache);
        if (headers_sent() || (($_COOKIE[self::KEKS] ?? '') === $s)) { return; }
        @setcookie(self::KEKS, $s, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::KEKS] = $s;
    }

    /**
     * Eine Adresse mit der Sprache versehen -- auch wenn schon ein Anker
     * oder andere Angaben dranhaengen.
     */
    public static function anhaengen(string $adresse, string $sprache): string
    {
        $s = self::waehlen($sprache);
        $anker = '';
        if (($raute = strpos($adresse, '#')) !== false) {
            $anker = substr($adresse, $raute);
            $adresse = substr($adresse, 0, $raute);
        }
        if (preg_match('~[?&]lang=~', $adresse)) {
            return preg_replace('~([?&]lang=)[a-zA-Z-]*~', '$1' . $s, $adresse) . $anker;
        }
        return $adresse . (str_contains($adresse, '?') ? '&' : '?') . 'lang=' . $s . $anker;
    }

    /** Die Rechtsseite in der richtigen Sprache, mit Anker. */
    public static function legal(string $sprache, string $anker = ''): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $ziel  = $basis . '/legal.html';
        return self::anhaengen($ziel . ($anker !== '' ? '#' . ltrim($anker, '#') : ''), $sprache);
    }

    /** Die Startseite in der gewaehlten Fassung: / bzw. /de/ und /en/. */
    public static function startseite(string $sprache): string
    {
        $s = self::waehlen($sprache);
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . ($s === 'it' ? '/' : '/' . $s . '/');
    }
}
