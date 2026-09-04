<?php
declare(strict_types=1);

/**
 * Die Technikpalette — welches Werkzeug zu welcher Seite passt.
 *
 * WARUM ES DIESE DATEI GIBT
 *
 * Es gibt eine lange Liste dessen, was das Web 2026 kann: von semantischem
 * HTML bis Gaussian Splatting. Sie ist richtig und sie ist nuetzlich — aber
 * vollstaendig in einen Auftrag gelegt macht sie das Ergebnis SCHLECHTER,
 * nicht besser. Wer alles nennt, hat nichts gesagt: Das Modell waehlt dann
 * aus, was am eindrucksvollsten klingt, und baut Bewegung in eine Seite, auf
 * der jemand nur die Oeffnungszeiten sucht.
 *
 * Deshalb steht die Liste hier und nicht im Prompt. Der Prompt bekommt genau
 * die Scheibe, die zu diesem Kunden passt: was immer gilt, was seine Stufe
 * dazunimmt, was seine Branche wirklich braucht — und was ohne ausdruecklichen
 * Grund nicht vorkommt.
 *
 * WAS BEWUSST FEHLT
 *
 * Alles, was eine Vecom-Seite nicht tragen kann oder nicht braucht: Blender,
 * Houdini, Unity WebGL, Photogrammetrie, WebXR, Multiplayer, CMS-Ketten,
 * Edge-Runtimes. Nicht weil es schlecht waere, sondern weil eine Seite, die
 * als statische Dateien auf All-Inkl liegt, davon nichts hat — und weil ein
 * Auftrag, der es nennt, den Bauenden in eine Richtung schickt, aus der er
 * nicht zurueckkommt. Braucht ein Projekt es doch, steht es im Briefing als
 * Kundenwunsch, und dann ist es eine Entscheidung und kein Reflex.
 */
final class Technik
{
    /* ==================================================================== */
    /*  Was auf jeder Seite gilt                                            */
    /* ==================================================================== */

    /**
     * Das Fundament. Es steht bewusst NICHT unter "Stufe A", sondern ueber
     * allem: Eine Seite fuer 450 Euro und eine fuer 3.000 unterscheiden sich
     * in der Inszenierung, nicht in der Sorgfalt. Wer hier spart, spart am
     * Einzigen, was jeder Besucher merkt.
     */
    public const IMMER = [
        'Semantisches HTML, eine Aufgabe je Seite, Struktur ohne CSS lesbar.',
        'CSS Grid und Flexbox fuer das Layout, Abstaende aus einer 4/8-Skala.',
        'Design-Tokens als CSS-Variablen; fluide Typografie und Abstaende ueber clamp().',
        'Container Queries statt reiner Breakpoints, wo eine Komponente in mehreren Umgebungen steht.',
        'Bilder als AVIF/WebP mit width, height und srcset; das LCP-Bild fetchpriority="high", der Rest loading="lazy".',
        'Schriften mit font-display:swap, Zeichensatz beschnitten, Anzeigeschrift vorgeladen, size-adjust gegen Sprünge.',
        'prefers-reduced-motion respektiert: Bewegung reduziert, Inhalt sofort im Endzustand, nichts verschwindet.',
        'Tastaturbedienung vollstaendig, sichtbarer Fokus, Kontrast 4.5:1 im Text und 3:1 in der Bedienung, Touchziele ab 44px.',
        'Metadaten, Open Graph, JSON-LD (LocalBusiness und was sonst zutrifft), sitemap.xml und robots.txt.',
        'Core Web Vitals als Zusage: LCP unter 2,5 s auf Mobilfunk, kein Layoutsprung, Seite deutlich unter 1 MB.',
    ];

    /* ==================================================================== */
    /*  Was die Stufe dazunimmt                                             */
    /* ==================================================================== */

    /**
     * Je Stufe NUR das Zusaetzliche. A steht leer da, und das ist keine
     * Luecke: Auf dieser Stufe ist das Fundament die ganze Arbeit, und jede
     * Zeile mehr waere eine Einladung, etwas einzubauen, das niemand braucht.
     */
    public const STUFE = [
        'A' => [],

        'B' => [
            'Erst die Plattform, dann eine Bibliothek: scroll-driven animations (animation-timeline: scroll()/view()), View Transitions, :has(), @starting-style, Anchor Positioning, Cascade Layers, Subgrid — das laeuft auf dem Compositor und kostet 0 KB.',
            'Endzustand als Vorgabe schreiben, die Animation per @supports darueberlegen — dann steht die Seite auch ohne.',
            'Micro-Interactions mit Richtung: Hover weiss, woher der Zeiger kam; Fokus ist gestaltet, nicht geduldet; Ladezustaende sind Gestaltung, keine Spinner.',
            'SVG als Werkzeug: Masken, Clip-Paths, Filter, Pfadanimation, Verlaeufe — leichter als jedes Bild und in jeder Aufloesung scharf.',
            'backdrop-filter und mix-blend-mode sparsam und mit Grund, nie als Grundstimmung.',
        ],

        'C' => [
            'GSAP mit ScrollTrigger fuer alles, was CSS nicht kann: verkettete Timelines, scrub, Pinning, synchrone Choreografie. Seit 2025 samt Plugins kostenlos, auch kommerziell.',
            'SplitText fuer kinetische Typografie — Zeichen, Wort, Zeile; Text-Reveal ueber Masken statt ueber Deckkraft allein.',
            'Lenis nur, wenn weiches Scrollen zur Gestaltung gehoert — und dann als EINZIGES Scrollsystem, sauber an ScrollTrigger gekoppelt. Nie zwei.',
            'Seitenuebergaenge ueber die View Transition API; Shared-Element-Uebergaenge dort, wo dasselbe Ding weiterlebt.',
            'Bildsequenz-Scrubbing auf Canvas mit Preload-Fenster, wenn eine Bewegung erzaehlt werden soll, fuer die ein Video zu schwer waere.',
            'Eigener Cursor nur am Zeigergeraet und nur, wenn er etwas zeigt — Vorschau, Richtung, Zustand. Nicht als Schmuck.',
            'Rive oder Lottie fuer wiederkehrende Bewegtmarken; Theatre.js, wenn eine Szene wirklich als Timeline gebaut werden muss.',
            'Drehbuch vor Code: Beats von 0.0 Etablieren ueber 0.3 Reveal und 0.6 Wendung bis 1.0 Schluss. Jeder Beat muss als Standbild funktionieren.',
        ],

        'D' => [
            'Three.js — Neubauten auf three/webgpu mit automatischem WebGL2-Rueckfall; Shader in TSL statt doppelter GLSL/WGSL-Basis. Der Rueckfall wird real benutzt, also wird er getestet.',
            'R3F und Drei nur, wenn die Seite ohnehin React ist; sonst Three.js pur — ein Framework wegen einer Szene ist kein Grund.',
            'Licht vor Material vor Kamera vor Geometrie vor Postprocessing. Bloom rettet kein schlechtes Licht.',
            'PBR mit HDRI/IBL, Reflexionssonden; Postprocessing zurueckhaltend: Bloom, Tiefenschaerfe, Koernung, Farbgradierung — dieselbe Gradierung auf 3D, Video und Foto, sonst faellt die Seite auseinander.',
            'GPU-Partikel und GPGPU ueber Ping-Pong-Texturen, wenn Masse gebraucht wird; Rapier fuer Physik, wenn sie etwas erzaehlt.',
            'Kamera ausschliesslich ueber ein gedaempftes Rig (orbit/dolly/track/crane/spline), in Brennweite gedacht, Daempfung 0.05-0.12 — nie direkte Positionen.',
            'glTF-Kette: validieren, prunen, Meshopt oder Draco, KTX2-Texturen, LOD. Ein 40-MB-Modell ist kein Modell, sondern ein Problem.',
            'Adaptive Qualitaet von Anfang an, nicht als Nachruestung; OffscreenCanvas und Web Worker, damit der Hauptthread frei bleibt; Frame-Loop haelt im Hintergrund-Tab an.',
            'Inhalt liegt im DOM, die Inszenierung ist eine Schicht darueber — nie Text ausschliesslich in WebGL oder in einer Scrub-Phase.',
        ],
    ];

    /* ==================================================================== */
    /*  Was die Branche wirklich braucht                                    */
    /* ==================================================================== */

    /**
     * Je Branche zwei bis vier Saetze, und jeder nennt eine Sache, die dieses
     * Gewerbe verkauft — nicht eine Technik, die gerade schoen ist.
     *
     * Der zweite Teil jedes Eintrags ist der wichtigere: was hier NICHT
     * hingehoert. Bei einer Werkstatt ist Scroll-Kino nicht zu wenig Aufwand,
     * sondern der falsche Aufwand.
     *
     * @var array<string,array{0:list<string>,1:list<string>,2:list<string>}>
     *      [Stichwoerter, Was diese Branche braucht, Was hier nichts zu suchen hat]
     */
    public const BRANCHE = [
        'gastronomie' => [
            ['ristorante', 'restaurant', 'trattoria', 'osteria', 'pizzeria', 'bar', 'cafe', 'caffè', 'gastronom', 'panific', 'baecker', 'bäcker', 'gelater', 'macell'],
            [
                'Die Karte als echter Text auf der Seite, nicht als PDF: Sie ist das Meistgesuchte und muss auf dem Telefon in zwei Sekunden dastehen.',
                'Oeffnungszeiten, Telefonnummer als tel:-Link und der Weg als Karten-Link — sichtbar auf jeder Seite, im Daumenbereich.',
                'Speisen-Fotos mit einer einheitlichen Farbgradierung; lieber sechs gute als dreissig. Warme Toene, echtes Licht, keine Stockbilder von fremdem Essen.',
                'Reservierung oder WhatsApp direkt erreichbar — in Sizilien ruft man an oder schreibt, ein Formular allein ist eine geschlossene Tuer.',
            ],
            ['3D-Modelle von Gerichten', 'Karussell fuer die Karte', 'PDF-Downloads als einzige Speisekarte'],
        ],

        'beherbergung' => [
            ['hotel', 'albergo', 'b&b', 'bed', 'agriturismo', 'ferienwohn', 'casa vacanz', 'appartament', 'pension'],
            [
                'Ein Zimmer ist eine Szene: Bildsequenz oder Panorama je Zimmer, gescrubbt statt automatisch abgespielt — der Gast bestimmt das Tempo.',
                'Preis, Belegung und Kontakt ohne Umweg; wenn ein Buchungssystem dranhaengt, fuehrt genau ein Knopf dorthin.',
                'Die Umgebung verkauft mit: Strand, Ort, Anfahrt, Entfernungen in Minuten statt in Kilometern.',
                'Kapitel-Scroll ueber die Lage funktioniert hier — aber jeder Beat muss als Standbild lesbar bleiben.',
            ],
            ['automatisch startende Videos mit Ton', 'Preise, die man erst nach dem Formular sieht'],
        ],

        'handwerk' => [
            ['handwerk', 'schlosser', 'elektr', 'idraulic', 'installat', 'maler', 'imbianch', 'schreiner', 'faleg', 'muratore', 'bau', 'edil', 'kfz', 'meccanic', 'officina', 'werkstatt', 'transport', 'trasport'],
            [
                'Vorher-Nachher als Schieberegler ueber clip-path — die billigste Technik der Liste und die ueberzeugendste fuer dieses Gewerbe.',
                'Anrufen muss die erste und einfachste Handlung sein: Nummer gross, tel:-Link, Erreichbarkeit dabei.',
                'Leistungen in klaren Worten, keine Bilderstrecken ohne Beschriftung. Wer hier landet, hat ein Problem und sucht eine Loesung.',
                'Einzugsgebiet ausdruecklich nennen — die Frage "kommt der ueberhaupt zu mir" entscheidet den Anruf.',
            ],
            ['Scroll-Kino', '3D', 'weiches Scrollen', 'alles, was zwischen den Besucher und die Telefonnummer kommt'],
        ],

        'schoenheit' => [
            ['friseur', 'parrucchier', 'salon', 'beauty', 'estetic', 'kosmetik', 'nagel', 'unghie', 'barbier', 'spa', 'wellness', 'massag'],
            [
                'Die Galerie ist das Produkt: Masonry-Raster, Lightbox, schnelle Bilder — und jedes Bild mit dem, was gemacht wurde.',
                'Terminwunsch in Reichweite, WhatsApp gleichberechtigt neben dem Telefon.',
                'Preisliste sichtbar; wer sie versteckt, verliert genau die Kundin, die vergleicht.',
                'Kinetische Typografie auf der Markenzeile darf hier sein — einmal, im Kopf der Seite, nicht auf jedem Abschnitt.',
            ],
            ['Bildkarussell als einzige Galerie', 'Stockfotos von fremden Frisuren'],
        ],

        'wein' => [
            ['cantina', 'wein', 'vino', 'weingut', 'vigna', 'oliv', 'olio', 'azienda agricol', 'frantoio'],
            [
                'Die Herkunft ist die Geschichte: vom Hang ueber die Ernte bis zur Flasche — ein Kapitel-Scroll, der etwas erzaehlt und nicht nur bewegt.',
                'Parallax in Ebenen mit Bezug zur Landschaft, nicht als Effekt ueber allem.',
                'Jede Sorte mit Jahrgang, Rebsorte, Lage — die Angaben, nach denen tatsaechlich gesucht wird.',
                'Ab Stufe D: die Flasche als 3D-Objekt, aber nur wenn sie sich drehen laesst und etwas zeigt, das ein Foto nicht kann.',
            ],
            ['Weinglas-Animation ohne Aussage', 'Hintergrundvideo als Dauerschleife'],
        ],

        'laden' => [
            ['laden', 'negozio', 'boutique', 'shop', 'mode', 'moda', 'schmuck', 'gioiell', 'blumen', 'fior', 'buchhandl', 'librer'],
            [
                'Ein ruhiges Produktraster mit echten Bildern; Schnellansicht statt Seitenwechsel fuer den ersten Blick.',
                'Bestellen ueber WhatsApp oder Anruf, wenn es keinen Shop gibt — und das ehrlich benennen statt einen Warenkorb vorzutaeuschen.',
                'Neuheiten und Oeffnungszeiten oben; der Laden lebt von Leuten, die vorbeikommen.',
            ],
            ['Shop-Optik ohne Shop', 'endloses Scrollen ohne Filter'],
        ],

        'praxis' => [
            ['arzt', 'medic', 'studio medico', 'zahn', 'dentist', 'physio', 'therap', 'anwalt', 'avvocat', 'notar', 'commercialist', 'steuerber', 'verein', 'associazion', 'schule', 'scuola'],
            [
                'Klarheit vor Wirkung: Sprechzeiten, Anfahrt, Unterlagen, Formulare — und alles ohne Suchen erreichbar.',
                'Barrierefreiheit ist hier keine Kuer, sondern die Zielgruppe: grosse Schrift, hoher Kontrast, saubere Tastaturbedienung.',
                'Formulare, die auf dem Telefon funktionieren, mit Fehlermeldungen, die sagen, was zu tun ist.',
            ],
            ['Bewegung um der Bewegung willen', 'dunkler Grund mit duennem Text'],
        ],

        'immobilien' => [
            ['immobil', 'makler', 'agenzia immobiliar', 'architek', 'architett', 'ingegner', 'geometra'],
            [
                'Das Objekt ist die Seite: Galerie, Grundriss zum Vergroessern, Lage auf der Karte, Eckdaten als Tabelle.',
                'Ein Referenzprojekt als Kapitel-Scroll funktioniert — Zustand, Eingriff, Ergebnis.',
                'Kontakt am Objekt, nicht auf einer eigenen Seite.',
            ],
            ['virtuelle Rundgaenge, die niemand pflegt', 'Rendering ohne Kennzeichnung als Rendering'],
        ],
    ];

    /* ==================================================================== */
    /*  Was ohne ausdruecklichen Grund nicht vorkommt                       */
    /* ==================================================================== */

    /**
     * Die Gegenliste. Sie steht in jedem Auftrag, unabhaengig von der Stufe,
     * weil genau diese Dinge auftauchen, wenn ein Modell beeindrucken will.
     */
    public const NIE_OHNE_GRUND = [
        'Kein Framework ohne vier gute Antworten (welches Problem, geht es nativ, wird es gepflegt, was kostet es an Ladezeit). Der Regelfall ist eine Datei je Seite, statisch ausgeliefert.',
        'Kein zweites Scrollsystem, keine zwei Animationsbibliotheken fuer dieselbe Sache.',
        'Kein Hintergrundvideo als Dauerschleife, kein Ton ohne Klick, kein Karussell fuer Wichtiges.',
        'Kein 3D, das nichts erzaehlt — und keins ohne Rueckfallebene, die dieselbe Aussage traegt.',
        'Keine Animation, die den Besucher warten laesst. Bewegung braucht einen Grund: Orientierung, Rueckmeldung, Kontinuitaet oder Dramaturgie.',
    ];

    /* ==================================================================== */

    /**
     * Die Scheibe fuer dieses eine Projekt.
     *
     * @param array<string,mixed> $antworten Fragebogenantworten
     * @return array{immer:list<string>,stufe:list<string>,branche:?array{name:string,braucht:list<string>,nicht:list<string>},nie:list<string>}
     */
    public static function fuer(string $stufe, string $branche, array $antworten = []): array
    {
        $stufe = strtoupper(trim($stufe));
        if (!isset(self::STUFE[$stufe])) { $stufe = 'B'; }

        /* Die Stufen bauen aufeinander auf: C bekommt auch, was B kann.
           Sonst stuende auf einer Kino-Seite nichts ueber Micro-Interactions,
           und die entscheiden dort genauso. */
        $reihe = ['A', 'B', 'C', 'D'];
        $dazu = [];
        foreach ($reihe as $s) {
            foreach (self::STUFE[$s] as $z) { $dazu[] = $z; }
            if ($s === $stufe) { break; }
        }

        $suche = mb_strtolower($branche . ' '
            . (string) ($antworten['branche'] ?? '') . ' '
            . (string) ($antworten['beschreibung'] ?? ''));

        $treffer = null;
        foreach (self::BRANCHE as $name => [$worte, $braucht, $nicht]) {
            foreach ($worte as $wort) {
                if ($wort !== '' && str_contains($suche, $wort)) {
                    $treffer = ['name' => $name, 'braucht' => $braucht, 'nicht' => $nicht];
                    break 2;
                }
            }
        }

        return [
            'immer'   => self::IMMER,
            'stufe'   => $dazu,
            'branche' => $treffer,
            'nie'     => self::NIE_OHNE_GRUND,
        ];
    }

    /** Die Ueberschrift fuer den Branchenblock, in Uwes Worten. */
    public static function brancheWort(string $name): string
    {
        return [
            'gastronomie'  => 'Gastronomie',
            'beherbergung' => 'Beherbergung',
            'handwerk'     => 'Handwerk und Technik',
            'schoenheit'   => 'Salon und Wellness',
            'wein'         => 'Wein, Öl, Landwirtschaft',
            'laden'        => 'Laden und Handel',
            'praxis'       => 'Praxis, Kanzlei, Verein',
            'immobilien'   => 'Immobilien und Architektur',
        ][$name] ?? $name;
    }
}
