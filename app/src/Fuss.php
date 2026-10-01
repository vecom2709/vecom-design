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
        /* MIT SPRACHE (23.09.2026)
           legal.html faellt ohne Hinweis auf Italienisch zurueck. Ein
           deutscher Kunde las also die AGB auf Italienisch -- ausgerechnet
           die Seite, auf die es ankommt. Jetzt steht die Sprache in der
           Adresse, und die Seite dort nimmt sie. */
        require_once __DIR__ . '/Sprache.php';

        $h = static fn(string $x): string => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
        $teile = [];
        foreach (['impressum', 'privacy', 'agb', 'widerruf'] as $anker) {
            $teile[] = '<a href="' . $h(Sprache::legal($s, $anker)) . '" target="_blank" rel="noopener">'
                . $h($w[$anker]) . '</a>';
        }

        /* Der Telegram-Kanal (01.10.2026, Uwe: „Alles“ — Vorschlag 1): auf Angebots- und Projektseiten,
           über /kanal.php?w=kunde — Beitritte von hier zählen für sich. Nur, wenn der Kanal verbunden ist. */
        try {
            require_once __DIR__ . '/Telegram.php';
            if (preg_match('~^https://t\.me/[A-Za-z0-9_]{4,64}~', (string) (Telegram::kanal()['link'] ?? ''))) {
                $teile[] = '<a href="' . $h(rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/kanal.php?w=kunde') . '" target="_blank" rel="noopener">Telegram</a>';
            }
        } catch (Throwable $e) { /* der Rechtsfuß steht auch ohne */ }

        return '<footer class="rechtsfuss">' . implode('<span aria-hidden="true">·</span>', $teile)
            . '</footer>';
    }
}
