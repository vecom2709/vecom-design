<?php
declare(strict_types=1);

/**
 * Die Haltung — was vor der Technik kommt.
 *
 * WARUM ES DIESE DATEI GIBT
 *
 * Technik.php beantwortet die Frage "womit". Sie beantwortet nicht die
 * Frage, an der die meisten Seiten scheitern: wozu. Zwei Seiten koennen
 * dieselbe Werkzeugliste haben und trotzdem ist die eine ein Auftritt und
 * die andere ein Formular mit Bildern. Der Unterschied entsteht vor der
 * ersten Zeile Code, in fuenf Saetzen, die fast nie jemand aufschreibt.
 *
 * Der Text, aus dem diese Datei stammt, ist deutlich laenger: siebenundsiebzig
 * Abschnitte ueber kreative Doktrin, Erlebnisgraphen, Signature Moments,
 * Faehigkeitsstufen von 0 bis 7. Vollstaendig in einen Auftrag gelegt wuerde
 * er dasselbe anrichten wie die vollstaendige Technikliste: Wer alles fordert,
 * fordert nichts. Ein Baecker in Ragusa bekommt dann eine Bildsprache mit
 * "emotionaler Architektur" und eine Seite, auf der niemand die
 * Oeffnungszeiten findet.
 *
 * Deshalb steht hier nur, was auf jeder Stufe wirklich traegt, und jeder
 * Block erscheint erst auf der Stufe, auf der er sich verdient:
 *
 *   These      — auf jeder Stufe. Fuenf Saetze vor jeder Zeile Code.
 *   Moment     — auf jeder Stufe, aber auf A ist es keine Animation,
 *                sondern eine Sache, die aussergewoehnlich gut ist.
 *   Bogen      — ab B. Vorher gibt es nichts zu dramatisieren.
 *   Szenen     — ab C. Vorher waere es eine Gliederung mit anderem Namen.
 *   Leiter     — ab C. Abstufung nach Geraet, Reihenfolge vorher festgelegt.
 *   Schiefgeht — auf jeder Stufe, mit mehr Faellen nach oben.
 *   Zeugnis    — auf jeder Stufe. Der Erstentwurf geht nie raus.
 *
 * WAS BEWUSST FEHLT
 *
 * "Baue keine Website, baue ein Erlebnis" als Parole ohne Gegengewicht.
 * Auf Stufe A ist das beste Erlebnis, dass alles sofort gefunden wird —
 * und ein Auftrag, der das nicht dazusagt, erzeugt genau die Seite, auf
 * der die Telefonnummer hinter einer Animation liegt.
 */
final class Haltung
{
    /** Die fuenf Saetze. Sie stehen auf jeder Stufe, auch auf A. */
    public const THESE = [
        'Kernidee in einem Satz — was diese Seite IST, nicht was sie enthaelt.',
        'Emotionales Versprechen in drei Worten — was der Besucher nach zehn '
            . 'Sekunden fuehlen soll.',
        'Visuelle Metapher — woraus die Seite gemacht ist: Material, Licht, '
            . 'Rhythmus, Temperatur.',
        'Der eine Moment — woran man sich am naechsten Tag noch erinnert.',
        'Der Satz, der nur hier stimmt — warum diese Seite so aussieht und '
            . 'die des Mitbewerbers nicht.',
    ];

    /**
     * Der eine Moment, je nach Fallhoehe.
     *
     * Auf A steht hier ausdruecklich keine Bewegung. Das ist der Punkt, an
     * dem eine uebernommene Kreativdoktrin sonst schadet: Sie kennt nur
     * Inszenierung als Antwort, und ein Schlosser braucht keine.
     */
    private const MOMENT = [
        'A' => [
            'Auf dieser Stufe ist der Moment kein Effekt, sondern eine Sache, die '
                . 'aussergewoehnlich gut ist: die Karte, die sofort dasteht. Die '
                . 'Nummer, die der Daumen trifft. Der Weg, der mit einem Griff im '
                . 'Navi liegt.',
            'Such genau eine solche Sache aus und mach sie besser, als sie sein '
                . 'muesste. Das ist hier die ganze Inszenierung.',
        ],
        'B' => [
            'Ein gestalteter Augenblick: ein Uebergang, ein Zustandswechsel, eine '
                . 'typografische Geste, ein Bild, das beim Ankommen scharf wird.',
            'Einer, nicht fuenf. Fuenf kleine Effekte ergeben keinen Moment, '
                . 'sondern Unruhe.',
        ],
        'C' => [
            'Ein inszenierter Moment mit Vorbereitung, Hoehepunkt und Nachhall — er '
                . 'hat einen Platz im Drehbuch, nicht irgendwo.',
            'Er gehoert diesem Kunden: aus seinem Material, seiner Geschichte, '
                . 'seinem Ort. Ein Moment, den man auf eine andere Seite kopieren '
                . 'koennte, ist keiner.',
            'Und er muss als Standbild funktionieren — fuer alle, die schnell '
                . 'scrollen oder Bewegung abgestellt haben.',
        ],
        'D' => [
            'Ein inszenierter Moment mit Vorbereitung, Hoehepunkt und Nachhall, aus '
                . 'dem Material dieses Kunden — nicht aus dem Vorrat an schoenen '
                . 'Effekten.',
            'Der Moment ist der GRUND fuer die Technik, nicht ihr Ergebnis. Waere '
                . 'die Szene ohne ihn genauso gut, streich die Szene.',
            'Er muss als Standbild funktionieren, und die Rueckfallebene muss '
                . 'dieselbe Aussage tragen — nicht eine aermere.',
        ],
    ];

    /** Der emotionale Bogen. Ab B. */
    private const BOGEN = [
        'Sag fuer jeden Abschnitt in EINEM Wort, was er ausloesen soll, und pruef '
            . 'dann die Reihenfolge: erst Gefuehl, dann Beleg, dann der leichteste '
            . 'moegliche Schritt.',
        'Ein Bogen hat eine Spitze. Wenn jeder Abschnitt gleich laut ist, hat die '
            . 'Seite keine — und alles gleich wichtig heisst nichts wichtig.',
    ];

    /** Szenen statt Abschnitte. Ab C. */
    private const SZENEN = [
        'Denk in Szenen, nicht in Abschnitten: Jede Szene hat eine Absicht, einen '
            . 'Eintritt, einen Kern und einen Austritt — und beantwortet genau eine '
            . 'Frage des Besuchers.',
        'Die Uebergaenge zwischen den Szenen sind Gestaltung, nicht Luecke. Was von '
            . 'einer Szene in die naechste weiterlebt (eine Farbe, ein Element, eine '
            . 'Bewegungsrichtung), entscheidet, ob es eine Seite ist oder eine '
            . 'Sammlung von Abschnitten.',
        'Schreib das Drehbuch als Tabelle — Szene, Frage, Bild, Bewegung, '
            . 'Standbild-Test — und zeig es mir, BEVOR du animierst. Die Tabelle ist '
            . 'die eigentliche Arbeit; der Rest ist Umsetzung.',
    ];

    /** Abstufung nach Geraet. Ab C. */
    private const LEITER = [
        'Die Inszenierung wird nach Geraet abgestuft, und die Reihenfolge steht '
            . 'VORHER fest, nicht erst wenn es ruckelt: volle Fassung — weniger '
            . 'Aufloesung und Partikel — Postprocessing aus — Bewegung nur noch als '
            . 'Uebergang — Standbilder.',
        'Auf jeder dieser Stufen ist die Aussage dieselbe. Was auf der untersten '
            . 'fehlt, war Schmuck; was dort fehlt und weh tut, war falsch geplant.',
        'Die Framezeit wird gemessen, nicht geschaetzt. Die Schleife haelt an, wenn '
            . 'der Tab im Hintergrund liegt.',
    ];

    /* ==================================================================== */

    /**
     * Die kreativen Bloecke fuer diese Stufe, in der Reihenfolge, in der sie
     * im Auftrag stehen.
     *
     * @return list<array{0:string,1:list<string>}> [Ueberschrift, Zeilen]
     */
    public static function these(string $stufe): array
    {
        $stufe = self::sicher($stufe);

        $kopf = $stufe === 'A'
            ? 'Auch auf dieser Stufe gilt: Du baust keine Seite, die Angaben '
                . 'enthaelt, sondern einen Auftritt, der etwas ausloest. Hier ist '
                . 'das Ausgeloeste Vertrauen und Tempo — nicht Staunen.'
            : 'Du baust keine Seite, die Angaben enthaelt, sondern einen Auftritt, '
                . 'der etwas ausloest. Was er ausloest, entscheidest du zuerst und '
                . 'schreibst es auf — sonst entscheidet es der Zufall.';

        $bloecke = [];

        $these = array_merge(
            [$kopf . ' Schreib dafuer diese fuenf Saetze auf und zeig sie mir, '
                . 'bevor du irgendetwas baust:'],
            self::THESE,
            ['Pruefung: Wenn dieselbe These auch beim Mitbewerber stimmen wuerde, '
                . 'ist es keine These, sondern eine Beschreibung. Dann nochmal.']
        );
        $bloecke[] = ['KREATIVE THESE — VOR DER ERSTEN ZEILE CODE', $these];

        $bloecke[] = ['DER EINE MOMENT', self::MOMENT[$stufe]];

        if ($stufe !== 'A') {
            $bloecke[] = ['EMOTIONALER BOGEN', self::BOGEN];
        }
        if ($stufe === 'C' || $stufe === 'D') {
            $bloecke[] = ['SZENEN STATT ABSCHNITTE', self::SZENEN];
            $bloecke[] = ['WAS AUF SCHWACHEN GERAETEN WEGFAELLT', self::LEITER];
        }

        return $bloecke;
    }

    /**
     * Was passieren muss, wenn etwas nicht da ist.
     *
     * Die Faelle stehen auf jeder Stufe, weil sie auf jeder Stufe eintreten.
     * Nach oben kommen die dazu, die es nur dort gibt.
     *
     * @return list<string>
     */
    public static function schiefgeht(string $stufe): array
    {
        $stufe = self::sicher($stufe);

        $faelle = ['langsames Netz', 'fehlendes oder spaet geladenes Bild',
                   'leeres Feld ohne Inhalt', 'dreimal so langer Text wie geplant',
                   'abgeschaltetes JavaScript', 'Formular, das nicht durchgeht'];
        if ($stufe === 'C' || $stufe === 'D') {
            $faelle[] = 'reduzierte Bewegung';
            $faelle[] = 'Datensparmodus';
        }
        if ($stufe === 'D') {
            $faelle[] = 'kein WebGL';
            $faelle[] = 'Fremddienst antwortet nicht';
        }

        return [
            'Fuer jeden dieser Faelle gibt es eine gestaltete Antwort, keine '
                . 'kaputte Seite: ' . implode(' · ', $faelle) . '.',
            'Leere Zustaende sind Gestaltung: Sie sagen, was fehlt und was zu tun '
                . 'ist. Eine leere Flaeche ist kein Zustand, sondern ein Versaeumnis.',
            'Gestaltet wird gegen drei Datensaetze: den kuerzesten, den mittleren '
                . 'und den laengsten realistischen. Nur mit dem mittleren zu bauen '
                . 'ist der Grund, warum Seiten beim echten Text auseinanderfallen.',
        ];
    }

    /**
     * Das Zeugnis, das sich der Bauende selbst ausstellt.
     *
     * Der Erstentwurf ist nie das Ergebnis. Eine Runde gegen eine Rubrik ist
     * der groesste Qualitaetsunterschied, den es fuer null Aufwand gibt — und
     * der am haeufigsten uebersprungene Schritt.
     *
     * @return list<string>
     */
    public static function zeugnis(string $stufe): array
    {
        $stufe = self::sicher($stufe);
        $streng = $stufe === 'A'
            ? 'Geschwindigkeit und Auffindbarkeit zaehlen hier doppelt.'
            : 'Eigenstaendigkeit zaehlt hier doppelt: Der Kunde zahlt fuer eine '
                . 'Seite, die nicht aussieht wie alle anderen.';

        return [
            'Bevor du mir etwas zeigst, bewerte es selbst — Gestaltung, '
                . 'Bedienbarkeit, Eigenstaendigkeit, Inhalt, Geschwindigkeit, je 1 '
                . 'bis 5 mit einem Satz Begruendung. ' . $streng,
            'Nenn den schwaechsten Punkt, bessere genau dort eine Runde nach, dann '
                . 'zeig es mir. Wenn alle fuenf hoch aussehen, ist die Bewertung '
                . 'falsch, nicht die Seite gut.',
            'Und nenn mir drei Dinge, die ein Baukasten nicht hervorgebracht haette. '
                . 'Fallen dir keine drei ein, fehlt die These — nicht der Effekt.',
        ];
    }

    /** Der Satz gegen Ballast. Steht im Schlussauftrag, auf jeder Stufe. */
    public static function ballast(): string
    {
        return 'Jede eingebaute Technik braucht einen Satz, der sagt, was sie '
             . 'traegt. Was nur beeindruckt, fliegt vorher raus — nicht erst, '
             . 'wenn ich danach frage.';
    }

    /* ------------------------------------------------------------------ */

    private static function sicher(string $stufe): string
    {
        $stufe = strtoupper(trim($stufe));
        return isset(self::MOMENT[$stufe]) ? $stufe : 'B';
    }
}
