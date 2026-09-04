<?php
declare(strict_types=1);

require_once __DIR__ . '/Config.php';

/**
 * Der Rechtsfuss unter den Kundenseiten.
 *
 * WARUM DIESE KLASSE EXISTIERT
 *
 * Auf den statischen Seiten — Startseite, Preise, Betreuung — stand der Fuss
 * mit Impressum, Datenschutz und AGB von Anfang an. Auf den Seiten, die der
 * Kunde per Link bekommt, stand er nirgends: nicht auf dem Angebot, auf dem
 * der Vertrag geschlossen wird, nicht auf der Projektseite, nicht auf dem
 * Fragebogen. Genau diese Seiten sind aber die, auf denen der Kunde die
 * meiste Zeit verbringt und auf denen er entscheidet.
 *
 * Die Impressumspflicht macht keinen Unterschied zwischen einer Seite, die
 * bei Google steht, und einer, die man nur mit Schluessel erreicht. Und wer
 * gerade lesen will, was er da annimmt, soll die AGB von dort aus finden,
 * wo er steht — nicht ueber den Umweg der Startseite.
 *
 * Ein Ort fuer den Wortlaut, sechs Seiten, die ihn rufen. Zwei Wortlaute
 * waeren zwei Wahrheiten, sobald einer davon geaendert wird.
 */
final class Fuss
{
    /** @var array<string,array<string,string>> */
    private const WORTE = [
        'it' => ['impressum' => 'Note legali', 'privacy' => 'Privacy',
                 'agb' => 'Condizioni', 'widerruf' => 'Recesso'],
        'de' => ['impressum' => 'Impressum',   'privacy' => 'Datenschutz',
                 'agb' => 'AGB', 'widerruf' => 'Widerruf'],
        'en' => ['impressum' => 'Legal notice','privacy' => 'Privacy',
                 'agb' => 'Terms', 'widerruf' => 'Withdrawal'],
    ];

    /**
     * Der fertige Fuss als HTML.
     *
     * Die Adressen stehen absolut, mit dem eingestellten Webauftritt davor:
     * Diese Seiten liegen im Wurzelverzeichnis, aber sie werden auch unter
     * kurzen Adressen ausgeliefert (/k/…, /e/…), und ein relativer Verweis
     * zeigt dann ins Leere.
     */
    public static function html(string $sprache): string
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        $w = self::WORTE[$s];
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/legal.html';

        $h = static fn(string $x): string => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
        $teile = [];
        foreach (['impressum', 'privacy', 'agb', 'widerruf'] as $anker) {
            $teile[] = '<a href="' . $h($basis . '#' . $anker) . '" target="_blank" rel="noopener">'
                . $h($w[$anker]) . '</a>';
        }

        return '<footer class="rechtsfuss">' . implode('<span aria-hidden="true">·</span>', $teile)
            . '</footer>';
    }
}
