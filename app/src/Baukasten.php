<?php
declare(strict_types=1);

/* ==========================================================================
   Baukasten.php — Vom Bedarf zum Preis.

   WARUM ES DIESE DATEI GIBT

   Frueher stand auf der Website ein Preis, und der Kunde musste schauen, in
   welches Paket er hineinpasst. Jetzt beschreibt er, was er braucht, und der
   Preis entsteht daraus. Diese Datei ist die Uebersetzung zwischen beidem:
   acht Fragen auf der einen Seite, ein Katalog mit Preisen auf der anderen.

   ZWEI ZAHLEN, NICHT EINE

   Jeder Baustein hat einen unteren und einen oberen Preis. Was der Kunde am
   Ende sieht, ist eine Spanne — nie eine Zahl, die spaeter nicht haelt. Wer
   "980 Euro" liest und dann 1.240 bezahlen soll, fuehlt sich betrogen, auch
   wenn der Aufwand gestiegen ist. Eine Spanne sagt von Anfang an die
   Wahrheit: So genau kann man das vor dem Gespraech nicht wissen.

   GERUNDET WIRD ABSICHTLICH GROB

   743 bis 1.087 Euro liest sich wie eine Rechnung, die schon feststeht.
   700 bis 1.100 liest sich wie eine Schaetzung — und genau das ist es.
   Deshalb runden wir nach aussen auf volle fuenfzig Euro.

   DIE ANTWORTEN LIEGEN ALS JSON IN DER DATENBANK

   Die Fragen aendern sich schneller als eine Tabelle. Wer in einem Jahr eine
   neunte Frage stellt, soll dafuer keine Spalte anlegen muessen.
   ========================================================================== */
final class Baukasten
{
    /* Die Rundung waechst mit dem Betrag: unter 1.000 Euro in 25er-Schritten,
       darunter wuerde sie die Spanne kuenstlich aufblaehen (aus echten 299 bis
       349 wuerden sonst 250 bis 350 — plus vierzig Prozent statt plus
       siebzehn). Ab 3.000 Euro in Hunderterschritten, weil dort niemand mehr
       auf fuenfzig Euro genau schaetzt. */
    private const RUNDUNG_STUFEN = [
        [100000, 2500],    // bis  1.000 EUR -> 25er
        [300000, 5000],    // bis  3.000 EUR -> 50er
        [PHP_INT_MAX, 10000],
    ];

    /* ----------------------------------------------------------------------
       Die acht Fragen.

       Mehr als acht beantwortet niemand auf dem Handy. Jede Frage muss sich
       rechtfertigen: Sie steht hier nur, wenn ihre Antwort den Preis oder das
       Gespraech wirklich veraendert.

       art: 'einfach' = eine Antwort, 'mehrfach' = mehrere ankreuzbar
       ---------------------------------------------------------------------- */
    public const FRAGEN = [

        'zweck' => [
            'art' => 'mehrfach',
            'frage' => [
                'it' => 'Che cosa deve fare il sito?',
                'de' => 'Was soll die Website leisten?',
                'en' => 'What should the website do?',
            ],
            'hilfe' => [
                'it' => 'Scegli tutto quello che serve. Puoi anche sceglierne uno solo.',
                'de' => 'Wähle alles aus, was du brauchst. Eines reicht auch.',
                'en' => 'Pick everything you need. One is fine too.',
            ],
            'optionen' => [
                'zeigen'      => ['it' => 'Far vedere chi siamo',            'de' => 'Zeigen, wer wir sind',            'en' => 'Show who we are'],
                'kontakt'     => ['it' => 'Farmi contattare dai clienti',    'de' => 'Kunden sollen mich erreichen',    'en' => 'Let customers reach me'],
                'speisekarte' => ['it' => 'Mostrare menu o listino',         'de' => 'Speisekarte oder Angebot zeigen', 'en' => 'Show a menu or price list'],
                'termine'     => ['it' => 'Appuntamenti o prenotazione tavoli', 'de' => 'Termine oder Tischreservierung', 'en' => 'Appointments or table booking'],
                'buchung'     => ['it' => 'Prenotazioni di camere o case',   'de' => 'Zimmer oder Ferienwohnung buchen', 'en' => 'Room or holiday-home booking'],
                'shop'        => ['it' => 'Vendere online',                  'de' => 'Online verkaufen',                'en' => 'Sell online'],
            ],
        ],

        'umfang' => [
            'art' => 'einfach',
            'frage' => [
                'it' => 'Quanto deve essere grande?',
                'de' => 'Wie groß soll sie werden?',
                'en' => 'How big should it be?',
            ],
            'hilfe' => [
                'it' => 'Una stima basta. Si può cambiare dopo.',
                'de' => 'Eine Schätzung genügt. Das lässt sich später ändern.',
                'en' => 'An estimate is enough. This can change later.',
            ],
            'optionen' => [
                'eine'     => ['it' => 'Una pagina sola',        'de' => 'Nur eine Seite',        'en' => 'A single page'],
                'wenige'   => ['it' => 'Poche pagine (3–5)',     'de' => 'Wenige Seiten (3–5)',   'en' => 'A few pages (3–5)'],
                'mehrere'  => ['it' => 'Diverse pagine (6–10)',  'de' => 'Mehrere Seiten (6–10)', 'en' => 'Several pages (6–10)'],
                'viele'    => ['it' => 'Molte pagine (oltre 10)','de' => 'Viele Seiten (über 10)','en' => 'Many pages (over 10)'],
            ],
        ],

        'sprachen' => [
            'art' => 'einfach',
            'frage' => [
                'it' => 'In quante lingue?',
                'de' => 'In wie vielen Sprachen?',
                'en' => 'In how many languages?',
            ],
            'optionen' => [
                '1' => ['it' => 'Una lingua',   'de' => 'Eine Sprache',  'en' => 'One language'],
                '2' => ['it' => 'Due lingue',   'de' => 'Zwei Sprachen', 'en' => 'Two languages'],
                '3' => ['it' => 'Tre lingue',   'de' => 'Drei Sprachen', 'en' => 'Three languages'],
            ],
        ],

        'material' => [
            'art' => 'mehrfach',
            'frage' => [
                'it' => 'Che cosa hai già pronto?',
                'de' => 'Was hast du schon fertig?',
                'en' => 'What do you already have?',
            ],
            'hilfe' => [
                'it' => 'Quello che manca posso occuparmene io — per questo la domanda.',
                'de' => 'Was fehlt, kann ich übernehmen — deshalb die Frage.',
                'en' => 'Whatever is missing, I can take care of — that is why I ask.',
            ],
            'optionen' => [
                'texte' => ['it' => 'I testi',      'de' => 'Die Texte',   'en' => 'The texts'],
                'fotos' => ['it' => 'Le foto',      'de' => 'Die Fotos',   'en' => 'The photos'],
                'logo'  => ['it' => 'Il logo',      'de' => 'Das Logo',    'en' => 'The logo'],
            ],
        ],

        'bestand' => [
            'art' => 'einfach',
            'frage' => [
                'it' => 'Hai già un sito?',
                'de' => 'Gibt es schon eine Website?',
                'en' => 'Do you already have a website?',
            ],
            'optionen' => [
                'neu'        => ['it' => 'No, si parte da zero',       'de' => 'Nein, alles neu',            'en' => 'No, starting from scratch'],
                'erneuern'   => ['it' => 'Sì, ma va rifatto',          'de' => 'Ja, sie soll erneuert werden','en' => 'Yes, but it needs replacing'],
                'ueberarb'   => ['it' => 'Sì, va solo sistemato',      'de' => 'Ja, sie soll nur überarbeitet werden', 'en' => 'Yes, it just needs tidying up'],
            ],
        ],

        'zeit' => [
            'art' => 'einfach',
            'frage' => [
                'it' => 'Per quando ti serve?',
                'de' => 'Bis wann brauchst du sie?',
                'en' => 'When do you need it?',
            ],
            'optionen' => [
                'offen'    => ['it' => 'Non ho fretta',              'de' => 'Keine Eile',                  'en' => 'No rush'],
                'wochen'   => ['it' => 'Nelle prossime settimane',   'de' => 'In den nächsten Wochen',      'en' => 'In the next few weeks'],
                'schnell'  => ['it' => 'Il prima possibile',         'de' => 'So schnell wie möglich',      'en' => 'As soon as possible'],
            ],
        ],

        'betreuung' => [
            'art' => 'einfach',
            'frage' => [
                'it' => 'Vuoi che me ne occupi anche dopo?',
                'de' => 'Soll ich mich auch danach kümmern?',
                'en' => 'Should I look after it afterwards?',
            ],
            'hilfe' => [
                'it' => 'Aggiornamenti, copie di sicurezza, piccole modifiche. È un contratto a parte.',
                'de' => 'Aktualisierungen, Sicherungen, kleine Änderungen. Das ist ein eigener Vertrag.',
                'en' => 'Updates, backups, small changes. That is a separate contract.',
            ],
            'optionen' => [
                'ja'         => ['it' => 'Sì, volentieri',      'de' => 'Ja, gerne',        'en' => 'Yes, please'],
                'nein'       => ['it' => 'No, faccio da solo',  'de' => 'Nein, mache ich selbst', 'en' => 'No, I will manage'],
                'vielleicht' => ['it' => 'Non lo so ancora',    'de' => 'Weiß ich noch nicht','en' => 'I am not sure yet'],
            ],
        ],

        'branche' => [
            'art' => 'einfach',
            'frage' => [
                'it' => 'Di che cosa ti occupi?',
                'de' => 'Was machst du?',
                'en' => 'What do you do?',
            ],
            'hilfe' => [
                'it' => 'Serve solo a capire il tono giusto — non cambia il prezzo.',
                'de' => 'Das hilft mir nur beim richtigen Ton — auf den Preis wirkt es nicht.',
                'en' => 'This only helps me find the right tone — it does not affect the price.',
            ],
            'optionen' => [
                'gastro'    => ['it' => 'Ristorazione',            'de' => 'Gastronomie',              'en' => 'Food and drink'],
                'handel'    => ['it' => 'Negozio o commercio',     'de' => 'Laden oder Handel',        'en' => 'Shop or retail'],
                'handwerk'  => ['it' => 'Artigianato o servizi',   'de' => 'Handwerk oder Dienstleistung', 'en' => 'Trade or services'],
                'tourismus' => ['it' => 'Turismo o affitti',       'de' => 'Tourismus oder Vermietung','en' => 'Tourism or rentals'],
                'anderes'   => ['it' => 'Altro',                   'de' => 'Etwas anderes',            'en' => 'Something else'],
            ],
        ],
    ];

    /* ----------------------------------------------------------------------
       Wie die Fragen auf Seiten verteilt sind.

       Steht hier und nicht in bedarf.php, weil zwei Stellen dieselbe Zahl
       brauchen: die Seite zum Anzeigen und der Datensatz zum Begrenzen des
       gespeicherten Schritts. Als das getrennt war, stand in der Verwaltung
       "Schritt 8 von 5" — die eine Seite zaehlte Fragen, die andere Seiten.

       Acht Fragen auf einmal sind auf dem Handy eine Wand. Die erste steht
       allein: Sie ist die wichtigste und hat die meisten Antworten.
       ---------------------------------------------------------------------- */
    public const SCHRITTE = [
        ['zweck'],
        ['umfang', 'sprachen'],
        ['material', 'bestand'],
        ['zeit', 'betreuung', 'branche'],
    ];

    /** Seitenschritte einschliesslich der Ergebnisseite am Ende. */
    public static function schrittZahl(): int { return count(self::SCHRITTE) + 1; }

    /* Wie viele Seiten ueber die erste hinaus. Die Mitte der genannten Spanne. */
    private const SEITEN = ['eine' => 0, 'wenige' => 4, 'mehrere' => 8, 'viele' => 14];

    /* ----------------------------------------------------------------------
       Bausteine, die nie von allein in einer Rechnung landen.

       Das Logo ist der Fall, fuer den es diese Liste gibt. Wer keins hat,
       will deswegen noch lange keins kaufen — viele Betriebe haben einen
       Schriftzug, ein altes Schild oder schlicht kein Interesse daran. Es
       ungefragt einzurechnen macht das Angebot teurer, als der Kunde gedacht
       hat, und kostet damit genau die Zusage, um die es geht.

       Der Baustein bleibt im Katalog: Er erscheint in der Verwaltung als
       Vorschlag, wenn die Antworten dafuer sprechen, und wandert per Knopf
       ins Angebot. Angeboten wird er also — nur von einem Menschen, nicht
       von einer Formel.
       ---------------------------------------------------------------------- */
    public const NUR_AUF_ANFRAGE = ['logo'];

    /* ----------------------------------------------------------------------
       Katalog
       ---------------------------------------------------------------------- */

    /** Legt die Startwerte an, falls der Katalog noch leer ist. Laeuft genau einmal. */
    public static function sicherstellen(): void
    {
        try {
            if ((int) Db::wert('SELECT COUNT(*) FROM bausteine') > 0) { return; }
        } catch (Throwable $e) { return; }

        $roh = @file_get_contents(__DIR__ . '/standardbausteine.json');
        if ($roh === false) { return; }
        $daten = json_decode($roh, true);
        if (!is_array($daten) || empty($daten['bausteine'])) { return; }

        foreach ($daten['bausteine'] as $b) {
            $slug = (string) ($b['slug'] ?? '');
            if ($slug === '') { continue; }
            try {
                Db::insert('bausteine', [
                    'slug'            => $slug,
                    'gruppe'          => (string) ($b['gruppe'] ?? 'funktion'),
                    'name_it'         => (string) ($b['name']['it'] ?? $slug),
                    'name_de'         => (string) ($b['name']['de'] ?? ''),
                    'name_en'         => (string) ($b['name']['en'] ?? ''),
                    'text_it'         => (string) ($b['text']['it'] ?? ''),
                    'text_de'         => (string) ($b['text']['de'] ?? ''),
                    'text_en'         => (string) ($b['text']['en'] ?? ''),
                    'preis_cents'     => (int) ($b['preis_cents'] ?? 0),
                    'preis_bis_cents' => (int) ($b['preis_bis_cents'] ?? 0),
                    'monatlich'       => (int) ($b['monatlich'] ?? 0),
                    'je_einheit'      => (int) ($b['je_einheit'] ?? 0),
                    'sortierung'      => (int) ($b['sortierung'] ?? 0),
                ]);
            } catch (Throwable $e) { /* ein Baustein weniger ist kein Grund aufzuhoeren */ }
        }
    }

    /** @return array<string,array> Katalog nach slug. */
    public static function katalog(bool $nurAktive = true): array
    {
        $wo = $nurAktive ? 'WHERE aktiv = 1' : '';
        $reihen = Db::all("SELECT * FROM bausteine $wo ORDER BY sortierung, id");
        $nach = [];
        foreach ($reihen as $r) { $nach[(string) $r['slug']] = $r; }
        return $nach;
    }

    /** Name eines Bausteins in der Sprache des Kunden. */
    public static function name(array $b, string $sprache): string
    {
        $s = trim((string) ($b['name_' . $sprache] ?? ''));
        return $s !== '' ? $s : (string) $b['name_it'];
    }

    /** Beschreibung eines Bausteins in der Sprache des Kunden. */
    public static function text(array $b, string $sprache): string
    {
        $s = trim((string) ($b['text_' . $sprache] ?? ''));
        return $s !== '' ? $s : (string) ($b['text_it'] ?? '');
    }

    /* ----------------------------------------------------------------------
       Die Rechnung
       ---------------------------------------------------------------------- */

    /**
     * Uebersetzt die Antworten in Positionen und Summen.
     *
     * Gibt zurueck:
     *   positionen       Liste mit slug, menge, von_cents, bis_cents, monatlich
     *   von_cents        untere Summe, einmalig
     *   bis_cents        obere Summe, einmalig
     *   monatlich_cents  Betreuung, falls gewuenscht
     */
    public static function rechnen(array $antworten, ?array $katalog = null): array
    {
        $katalog ??= self::katalog();

        $zweck    = (array) ($antworten['zweck'] ?? []);
        $material = (array) ($antworten['material'] ?? []);
        $umfang   = (string) ($antworten['umfang'] ?? 'wenige');
        $sprachen = max(1, min(3, (int) ($antworten['sprachen'] ?? 1)));
        $bestand  = (string) ($antworten['bestand'] ?? 'neu');
        $zeit     = (string) ($antworten['zeit'] ?? 'offen');
        $betreu   = (string) ($antworten['betreuung'] ?? 'nein');

        /* slug => Menge. Menge 0 heisst: kommt nicht vor. */
        $mengen = [
            'basis'   => 1,
            'seite'   => self::SEITEN[$umfang] ?? 4,
            'sprache' => $sprachen - 1,
        ];

        foreach (['speisekarte', 'termine', 'buchung', 'shop'] as $f) {
            $mengen[$f] = in_array($f, $zweck, true) ? 1 : 0;
        }

        /* Was der Kunde NICHT hat, muss gemacht werden. Andersherum gedacht
           als die Frage: Angekreuzt ist, was da ist. */
        $mengen['texte'] = in_array('texte', $material, true) ? 0 : 1;
        $mengen['fotos'] = in_array('fotos', $material, true) ? 0 : 1;
        // Das Logo bewusst NICHT hier: siehe NUR_AUF_ANFRAGE. Ob es sich
        // anzubieten lohnt, steht weiter unten als Vorschlag.
        $ohneLogo = !in_array('logo', $material, true);

        $mengen['uebernahme'] = in_array($bestand, ['erneuern', 'ueberarb'], true) ? 1 : 0;
        $mengen['express']    = $zeit === 'schnell' ? 1 : 0;
        $mengen['betreuung_basis'] = $betreu === 'ja' ? 1 : 0;

        $positionen = [];
        $von = 0; $bis = 0; $monatlich = 0;

        foreach ($katalog as $slug => $b) {
            if (in_array($slug, self::NUR_AUF_ANFRAGE, true)) { continue; }
            $menge = (int) ($mengen[$slug] ?? 0);
            if ($menge < 1) { continue; }
            if (!(int) $b['je_einheit']) { $menge = 1; }

            $eVon = (int) $b['preis_cents'];
            $eBis = (int) $b['preis_bis_cents'] ?: $eVon;

            if ((int) $b['monatlich']) {
                $monatlich += $eVon * $menge;
            } else {
                $von += $eVon * $menge;
                $bis += $eBis * $menge;
            }

            $positionen[] = [
                'slug'      => $slug,
                'menge'     => $menge,
                'von_cents' => $eVon * $menge,
                'bis_cents' => $eBis * $menge,
                'monatlich' => (int) $b['monatlich'],
            ];
        }

        /* Was ich Uwe zum Anbieten vorlege — nicht dem Kunden, und nirgends
           in der Spanne. Nur die Bausteine, fuer die die Antworten sprechen. */
        $vorschlaege = [];
        if ($ohneLogo && isset($katalog['logo'])) { $vorschlaege[] = 'logo'; }

        return [
            'positionen'      => $positionen,
            'vorschlaege'     => $vorschlaege,
            'von_cents'       => $von,
            'bis_cents'       => max($von, $bis),
            'monatlich_cents' => $monatlich,
        ];
    }

    /**
     * Liest einen Eurobetrag aus einem Textfeld und gibt ganze Cent zurueck.
     *
     * Hier ist schon oft Geld verlorengegangen. Uwe tippt "1.299,50", ein
     * Formular liefert "1299.50", jemand kopiert "1 299,50 EUR" aus einer
     * Mail. Ein blindes (int)((float) $wert * 100) macht daraus der Reihe
     * nach 100, 129950 und 100 Cent — und der Fehler faellt erst auf der
     * Rechnung auf.
     *
     * Regel: Alles ausser Ziffern, Komma und Punkt fliegt raus. Von den
     * verbleibenden Trennzeichen ist das LETZTE das Dezimaltrennzeichen,
     * aber nur, wenn danach hoechstens zwei Ziffern stehen — sonst ist es
     * ein Tausenderpunkt. Gerundet wird kaufmaennisch, nie abgeschnitten.
     */
    public static function centsAus(string $wert): int
    {
        $w = preg_replace('/[^0-9,.]/', '', $wert) ?? '';
        if ($w === '') { return 0; }

        $letzte = max(strrpos($w, ',') === false ? -1 : strrpos($w, ','),
                      strrpos($w, '.') === false ? -1 : strrpos($w, '.'));

        if ($letzte >= 0 && strlen($w) - $letzte - 1 <= 2 && strlen($w) - $letzte - 1 > 0) {
            $ganz = preg_replace('/[^0-9]/', '', substr($w, 0, $letzte)) ?? '0';
            $rest = substr($w, $letzte + 1);
            $cents = (int) $ganz * 100 + (int) str_pad($rest, 2, '0');
        } else {
            $cents = (int) (preg_replace('/[^0-9]/', '', $w) ?? '0') * 100;
        }
        return max(0, $cents);
    }

    /**
     * Rundet die Spanne nach aussen auf volle fuenfzig Euro.
     *
     * Untere Grenze nach unten, obere nach oben — nie andersherum. Eine
     * Schaetzung darf grosszuegig aussehen, aber sie darf nicht zu niedrig
     * anfangen: Der erste Eindruck ist die Zahl, an die der Kunde sich
     * erinnert.
     */
    public static function spanne(int $vonCents, int $bisCents): array
    {
        $r = self::RUNDUNG_STUFEN[count(self::RUNDUNG_STUFEN) - 1][1];
        foreach (self::RUNDUNG_STUFEN as [$grenze, $stufe]) {
            if ($vonCents <= $grenze) { $r = $stufe; break; }
        }
        $von = (int) (floor($vonCents / $r) * $r);
        $bis = (int) (ceil($bisCents / $r) * $r);
        if ($von < $r) { $von = $r; }
        if ($bis <= $von) { $bis = $von + $r; }
        return ['von_cents' => $von, 'bis_cents' => $bis];
    }
}
