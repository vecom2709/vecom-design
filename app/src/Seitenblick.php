<?php
declare(strict_types=1);

/**
 * Ein Blick auf die Website des Anrufers, waehrend er noch am Hoerer ist.
 *
 * WARUM ES DAS GIBT
 *
 * Ein Telefonassistent, der Allgemeinplaetze sagt, klingt wie jeder andere.
 * Einer, der nach zwei Sekunden sagt „auf dem Handy schiebt sich Ihre Seite
 * seitlich weg, und Ihre Telefonnummer ist kein Link zum Antippen", ist
 * etwas anderes: Er hat hingesehen. Das ist der Unterschied zwischen einem
 * Verkaufsgespraech und einer Beratung, und er kostet acht Sekunden Technik.
 *
 * WAS HIER BEWUSST NICHT PASSIERT
 *
 * Kein Geschmacksurteil. Diese Klasse sagt nie, eine Seite sei haesslich,
 * altmodisch oder unprofessionell — das waere eine Behauptung ueber etwas,
 * das sie nicht gemessen hat, und der Anrufer hoert es als Verkaufsmasche.
 * Gemeldet wird nur, was im HTML und in den Kopfzeilen nachweislich steht:
 * kein Viewport, kein https, keine anklickbare Nummer, kein Impressum. Jeder
 * Befund ist eine Tatsache, die der Anrufer selbst nachpruefen kann.
 *
 * DIE ZWEITE GRENZE: FREMDE SERVER
 *
 * Hier ruft unser Server eine Adresse ab, die jemand am Telefon genannt hat.
 * Das ist genau die Stelle, an der man sich sonst einen offenen Tuersteher
 * einbaut, der auf Zuruf ins eigene Netz greift. Deshalb geht nichts los,
 * bevor Domainpruefung::normalisieren() den Namen als echten Domainnamen
 * bestaetigt hat: Adressen wie localhost, 127.0.0.1 oder 10.0.0.5 fallen
 * schon an dieser Regel durch, weil sie keine Endung aus Buchstaben haben.
 */
final class Seitenblick
{
    /** Am Telefon ist alles ueber acht Sekunden Stille ein verlorener Satz. */
    public const ZEITLIMIT = 8;

    /** So viel HTML reicht fuer jedes Urteil hier. Der Rest waere Ballast. */
    public const HOECHST_BYTES = 400000;

    /** Zweimal dieselbe Seite in einer Stunde muss niemand abrufen. */
    public const FRISCH_MINUTEN = 60;

    /** Langsamer als das faellt einem Besucher auf. */
    public const LANGSAM_MS = 2500;

    /**
     * Wie schwer ein Befund wiegt.
     *
     * Die Reihenfolge ist die Reihenfolge, in der gesprochen wird. Drei
     * Saetze sind das Aeusserste, was am Telefon ankommt — danach hoert
     * niemand mehr zu, und aus Beratung wird eine Mangelliste.
     */
    public const GEWICHT = ['schwer' => 3, 'mittel' => 2, 'klein' => 1];

    /** So viele Befunde werden zum Sprechen zurueckgegeben. */
    public const HOECHSTENS = 3;

    /* ==================================================================== */
    /*  Der Blick                                                           */
    /* ==================================================================== */

    /**
     * @return array{gefunden:bool,adresse:?string,befunde:list<array{art:string,gewicht:string,satz:string}>,
     *               messwerte:array<string,mixed>,hinweis:string}
     */
    public static function ansehen(string $roh, string $sprache = 'it'): array
    {
        require_once __DIR__ . '/Domainpruefung.php';

        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $name = Domainpruefung::normalisieren($roh);
        if ($name === null) {
            return self::leer($sprache, 'adresse_unklar');
        }

        /* Wer eine Facebook-Seite nennt, hat keine Website — und das ist ein
           eigener Befund, kein Fehler. Ihn als "nicht erreichbar" zu melden
           waere schlicht falsch. */
        if (self::istFremdesProfil($name)) {
            return [
                'gefunden'  => true,
                'adresse'   => $name,
                'befunde'   => [self::befund('nur_profil', 'schwer', $sprache)],
                'messwerte' => ['profil' => true],
                'hinweis'   => self::hinweis('profil', $sprache),
            ];
        }

        /* Zwischengespeichert wird das Urteil, nicht die fremde Seite. Was
           man nicht aufhebt, kann man auch nicht versehentlich weitergeben —
           und gebraucht wird ohnehin nur der Befund. */
        $urteil = self::ausSpeicher($name);
        if ($urteil === null) {
            $urteil = self::beurteilen(self::holen($name));
            self::inSpeicher($name, $urteil);
        }

        return self::inWorte($name, $urteil, $sprache);
    }

    /* ==================================================================== */
    /*  Holen                                                               */
    /* ==================================================================== */

    /** @return array<string,mixed> */
    private static function holen(string $name): array
    {
        /* Erst https, und nur wenn das gar nicht geht, http. Andersherum
           bekaeme jede Seite mit einer Weiterleitung faelschlich "kein
           https" bescheinigt. */
        $versuche = ['https://' . $name . '/', 'http://' . $name . '/'];
        $letzte   = ['erreichbar' => false, 'fehler' => 'nicht_erreichbar'];

        foreach ($versuche as $i => $url) {
            $a = self::einAbruf($url);
            if ($a['erreichbar']) {
                $a['ueber_https'] = $i === 0;
                return $a;
            }
            $letzte = $a;
        }
        return $letzte;
    }

    /** @return array<string,mixed> */
    private static function einAbruf(string $url): array
    {
        $kopf = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => self::ZEITLIMIT,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'Vecom-Design-Seitenblick/1.0 (+https://vecom-design.it)',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml'],
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADERFUNCTION => static function ($ch, $zeile) use (&$kopf): int {
                $t = explode(':', $zeile, 2);
                if (count($t) === 2) { $kopf[mb_strtolower(trim($t[0]))] = trim($t[1]); }
                return strlen($zeile);
            },
            /* Nach 400 KB ist jedes Urteil hier gefaellt. Weiterzuladen
               kostet nur Zeit, die am Telefon als Stille ankommt. */
            CURLOPT_BUFFERSIZE     => 16384,
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => static function ($r, $dltotal, $dlnow): int {
                return $dlnow > self::HOECHST_BYTES ? 1 : 0;
            },
        ]);

        $anfang = microtime(true);
        $inhalt = curl_exec($ch);
        $ms     = (int) round((microtime(true) - $anfang) * 1000);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $endUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $fehler = curl_errno($ch);
        curl_close($ch);

        /* Abgebrochen, weil die Seite groesser war als noetig: Das ist kein
           Fehler, sondern genau der Plan. */
        $abgeschnitten = $fehler === CURLE_ABORTED_BY_CALLBACK;
        if ($fehler !== 0 && !$abgeschnitten) {
            return ['erreichbar' => false, 'fehler' => 'nicht_erreichbar', 'ms' => $ms];
        }
        if ($status >= 400 || $status === 0) {
            return ['erreichbar' => false, 'fehler' => 'nicht_erreichbar',
                    'status' => $status, 'ms' => $ms];
        }

        return [
            'erreichbar'    => true,
            'status'        => $status,
            'ms'            => $ms,
            'bytes'         => is_string($inhalt) ? strlen($inhalt) : 0,
            'html'          => is_string($inhalt) ? substr($inhalt, 0, self::HOECHST_BYTES) : '',
            'end_url'       => $endUrl,
            'ueber_https'   => str_starts_with(mb_strtolower($endUrl), 'https://'),
            'zuletzt'       => $kopf['last-modified'] ?? null,
            'abgeschnitten' => $abgeschnitten,
        ];
    }

    /* ==================================================================== */
    /*  Urteilen                                                            */
    /* ==================================================================== */

    /**
     * Aus dem Abruf wird ein Urteil — ohne ein Wort in einer Sprache.
     *
     * Die Trennung ist Absicht: Das Urteil ist sprachunabhaengig und
     * deshalb zwischenspeicherbar; die Saetze entstehen erst beim Sprechen.
     * Sonst laege derselbe Befund dreimal im Speicher.
     *
     * @param  array<string,mixed> $d
     * @return array{erreichbar:bool,roh:list<array{0:string,1:string}>,messwerte:array<string,mixed>}
     */
    private static function beurteilen(array $d): array
    {
        if (empty($d['erreichbar'])) {
            return ['erreichbar' => false,
                    'roh' => [['nicht_erreichbar', 'schwer']],
                    'messwerte' => ['status' => $d['status'] ?? null, 'ms' => $d['ms'] ?? null]];
        }

        $html  = (string) ($d['html'] ?? '');
        $klein = mb_strtolower($html);
        $roh   = [];

        if (empty($d['ueber_https'])) {
            $roh[] = ['kein_https', 'schwer'];
        }
        if (!str_contains($klein, 'name="viewport"') && !str_contains($klein, "name='viewport'")) {
            $roh[] = ['nicht_mobil', 'schwer'];
        }

        /* Eine Nummer, die dasteht, aber nicht anklickbar ist, kostet auf dem
           Handy genau den Anruf, um den es geht. Gemeldet wird sie nur, wenn
           wirklich eine Nummer im Text steht — sonst waere es eine
           Beschwerde ueber etwas, das es gar nicht gibt. */
        $hatNummer = (bool) preg_match('~(?:\+\d{1,3}[\s./-]?)?(?:\d[\s./-]?){7,}~', strip_tags($html));
        if ($hatNummer && !str_contains($klein, 'href="tel:') && !str_contains($klein, "href='tel:")) {
            $roh[] = ['telefon_nicht_klickbar', 'schwer'];
        }

        if (!preg_match('~(impressum|note legali|privacy|informativa|datenschutz|legal notice)~i', $html)) {
            $roh[] = ['kein_impressum', 'mittel'];
        }
        if ((int) ($d['ms'] ?? 0) > self::LANGSAM_MS) {
            $roh[] = ['langsam', 'mittel'];
        }
        if (!preg_match('~<title[^>]*>\s*\S~i', $html)) {
            $roh[] = ['kein_titel', 'mittel'];
        }
        if (!preg_match('~<meta[^>]+name=["\']description["\']~i', $html)) {
            $roh[] = ['keine_beschreibung', 'mittel'];
        }
        if (!preg_match('~<h1[\s>]~i', $html)) {
            $roh[] = ['kein_h1', 'klein'];
        }
        if (preg_match('~<frameset|\.swf["\']~i', $html)) {
            $roh[] = ['veraltete_technik', 'schwer'];
        }

        /* Ein Bauzaun aus einem alten Jahr ist der ehrlichste Hinweis, dass
           sich seit Langem niemand gekuemmert hat — aber nur, wenn er weit
           genug zurueckliegt. Voriges Jahr sagt nichts. */
        $jahr = (int) date('Y');
        if (preg_match_all('~(?:©|&copy;|copyright)[^0-9]{0,12}(20[0-2][0-9])~i', $html, $m)) {
            $juengstes = max(array_map('intval', $m[1]));
            if ($juengstes > 0 && $juengstes <= $jahr - 3) {
                $roh[] = ['veraltet', 'mittel'];
            }
        }

        usort($roh, static fn($a, $b) => self::GEWICHT[$b[1]] <=> self::GEWICHT[$a[1]]);

        return [
            'erreichbar' => true,
            'roh'        => array_values($roh),
            'messwerte'  => [
                'https'   => (bool) ($d['ueber_https'] ?? false),
                'ms'      => (int) ($d['ms'] ?? 0),
                'bytes'   => (int) ($d['bytes'] ?? 0),
                'status'  => (int) ($d['status'] ?? 0),
                'zuletzt' => $d['zuletzt'] ?? null,
                'anzahl'  => count($roh),
            ],
        ];
    }

    /**
     * Aus dem Urteil werden Saetze, die sie sprechen kann.
     *
     * @param array{erreichbar:bool,roh:list<array{0:string,1:string}>,messwerte:array<string,mixed>} $u
     */
    private static function inWorte(string $name, array $u, string $sprache): array
    {
        $roh = $u['roh'] ?? [];

        if (empty($u['erreichbar'])) {
            return ['gefunden' => false, 'adresse' => $name,
                    'befunde' => [self::befund('nicht_erreichbar', 'schwer', $sprache)],
                    'messwerte' => $u['messwerte'] ?? [],
                    'hinweis' => self::hinweis('nicht_erreichbar', $sprache)];
        }

        $befunde = [];
        foreach (array_slice($roh, 0, self::HOECHSTENS) as $b) {
            $befunde[] = self::befund((string) $b[0], (string) $b[1], $sprache);
        }
        if (!$befunde) {
            $befunde[] = self::befund('nichts_gefunden', 'klein', $sprache);
        }

        return ['gefunden' => true, 'adresse' => $name, 'befunde' => $befunde,
                'messwerte' => $u['messwerte'] ?? [],
                'hinweis' => self::hinweis($roh ? 'befunde' : 'sauber', $sprache)];
    }

    /* ==================================================================== */
    /*  Worte                                                              */
    /* ==================================================================== */

    /**
     * Was sie sagt.
     *
     * Jeder Satz ist so gebaut, dass er als gesprochener Satz funktioniert:
     * eine Beobachtung, eine Folge, kein Fachwort. „Kein Viewport-Tag" sagt
     * dem Anrufer nichts; „auf dem Handy schiebt sich Ihre Seite seitlich
     * weg" sagt ihm alles.
     */
    public const SAETZE = [
        'nicht_erreichbar' => [
            'it' => 'Il sito non risponde: dal mio server non si apre. Può essere un problema temporaneo, ma vale la pena controllarlo.',
            'de' => 'Die Seite antwortet nicht — von meinem Server aus lässt sie sich nicht öffnen. Das kann vorübergehend sein, aber es lohnt sich nachzusehen.',
            'en' => 'The site does not respond — it will not open from my server. That may be temporary, but it is worth checking.',
        ],
        'nur_profil' => [
            'it' => 'Quello è un profilo social, non un sito suo: se la piattaforma cambia le regole, il suo indirizzo sparisce con loro.',
            'de' => 'Das ist ein Profil bei einer Plattform, keine eigene Seite — ändert die Plattform ihre Regeln, ist Ihre Adresse mit weg.',
            'en' => 'That is a profile on a platform, not a site of your own — if the platform changes its rules, your address goes with it.',
        ],
        'kein_https' => [
            'it' => 'Il sito non è cifrato: il browser mostra «non sicuro» accanto all’indirizzo, e questo allontana le persone prima ancora che leggano.',
            'de' => 'Die Seite ist nicht verschlüsselt — der Browser schreibt „nicht sicher" neben die Adresse, und das schreckt Leute ab, bevor sie überhaupt lesen.',
            'en' => 'The site is not encrypted — the browser shows “not secure” next to the address, which puts people off before they read a word.',
        ],
        'nicht_mobil' => [
            'it' => 'Sul telefono il sito si sposta di lato e bisogna ingrandire per leggere. Oggi la maggior parte delle persone arriva dal telefono.',
            'de' => 'Auf dem Handy schiebt sich die Seite seitlich weg, man muss zum Lesen vergrößern. Die meisten Besucher kommen heute vom Handy.',
            'en' => 'On a phone the page slides sideways and has to be zoomed to read. Most visitors arrive on a phone these days.',
        ],
        'telefon_nicht_klickbar' => [
            'it' => 'Il suo numero è scritto ma non si può toccare per chiamare — dal telefono va copiato a mano, e molti lasciano perdere.',
            'de' => 'Ihre Nummer steht da, ist aber nicht zum Antippen — vom Handy aus muss man sie abschreiben, und viele lassen es dann.',
            'en' => 'Your number is written out but cannot be tapped to call — on a phone it has to be copied by hand, and many give up.',
        ],
        'kein_impressum' => [
            'it' => 'Non trovo note legali né informativa: in Italia sono obbligatorie e la loro assenza può costare cara.',
            'de' => 'Ich finde kein Impressum und keine Datenschutzerklärung — beides ist Pflicht, und das Fehlen kann teuer werden.',
            'en' => 'I find no legal notice and no privacy statement — both are required, and their absence can be expensive.',
        ],
        'langsam' => [
            'it' => 'Il sito impiega parecchio ad aprirsi. Chi aspetta più di tre secondi spesso torna indietro.',
            'de' => 'Die Seite braucht spürbar lange zum Öffnen. Wer über drei Sekunden wartet, geht oft wieder zurück.',
            'en' => 'The page takes a noticeable while to open. People who wait more than three seconds often go back.',
        ],
        'kein_titel' => [
            'it' => 'La pagina non ha un titolo proprio: su Google appare come una riga vuota, e nessuno clicca su una riga vuota.',
            'de' => 'Die Seite hat keinen eigenen Titel — bei Google steht dort eine leere Zeile, und auf eine leere Zeile klickt niemand.',
            'en' => 'The page has no title of its own — on Google that shows as an empty line, and nobody clicks an empty line.',
        ],
        'keine_beschreibung' => [
            'it' => 'Manca la descrizione per Google: il motore si inventa due righe a caso al posto delle sue.',
            'de' => 'Es fehlt die Beschreibung für Google — die Suchmaschine denkt sich dann zwei beliebige Zeilen aus statt Ihrer.',
            'en' => 'The description for Google is missing — the search engine then makes up two arbitrary lines instead of yours.',
        ],
        'kein_h1' => [
            'it' => 'La pagina non ha un titolo principale nel testo: Google fatica a capire di che cosa parla.',
            'de' => 'Im Text fehlt eine Hauptüberschrift — Google erkennt dadurch schlechter, worum es auf der Seite geht.',
            'en' => 'The text has no main heading — Google finds it harder to tell what the page is about.',
        ],
        'veraltete_technik' => [
            'it' => 'Il sito usa una tecnica che i browser di oggi non mostrano più correttamente.',
            'de' => 'Die Seite benutzt eine Technik, die heutige Browser nicht mehr richtig darstellen.',
            'en' => 'The site uses a technique that today’s browsers no longer display properly.',
        ],
        'veraltet' => [
            'it' => 'In fondo alla pagina c’è ancora un anno vecchio: chi lo vede pensa che l’attività non ci sia più.',
            'de' => 'Unten auf der Seite steht noch eine alte Jahreszahl — wer die sieht, denkt, den Betrieb gibt es nicht mehr.',
            'en' => 'The foot of the page still shows an old year — anyone who sees it assumes the business has closed.',
        ],
        'nichts_gefunden' => [
            'it' => 'Ho guardato: tecnicamente il sito è a posto. Non le vendo qualcosa che non le serve.',
            'de' => 'Ich habe nachgesehen — technisch ist die Seite in Ordnung. Ich verkaufe Ihnen nichts, was Sie nicht brauchen.',
            'en' => 'I looked — technically the site is fine. I am not going to sell you something you do not need.',
        ],
    ];

    /** Was sie mit dem Befund anfangen soll. Fuer das Modell, nicht fuer den Anrufer. */
    private const HINWEISE = [
        'befunde' => 'Nenne hoechstens zwei dieser Saetze, nicht alle. Danach eine Frage, keine Empfehlung.',
        'sauber'  => 'Nichts anzubieten ist auch eine Antwort. Frag, was ihn zum Anruf bewogen hat.',
        'profil'  => 'Nicht ueber die Plattform herziehen. Eine eigene Adresse ist der Punkt, mehr nicht.',
        'nicht_erreichbar' => 'Nicht behaupten, die Seite sei kaputt. Adresse noch einmal buchstabieren lassen.',
        'adresse_unklar'   => 'Die Adresse war keine gueltige Domain. Buchstabieren lassen, hoechstens einmal.',
    ];

    /** @return array{art:string,gewicht:string,satz:string} */
    private static function befund(string $art, string $gewicht, string $sprache): array
    {
        return ['art' => $art, 'gewicht' => $gewicht,
                'satz' => self::SAETZE[$art][$sprache] ?? self::SAETZE[$art]['it'] ?? ''];
    }

    private static function hinweis(string $fall, string $sprache): string
    {
        return self::HINWEISE[$fall] ?? '';
    }

    /** @return array{gefunden:bool,adresse:?string,befunde:array,messwerte:array,hinweis:string} */
    private static function leer(string $sprache, string $fall): array
    {
        return ['gefunden' => false, 'adresse' => null, 'befunde' => [],
                'messwerte' => [], 'hinweis' => self::hinweis($fall, $sprache)];
    }

    private static function istFremdesProfil(string $name): bool
    {
        foreach (['facebook.com', 'instagram.com', 'tiktok.com', 'linkedin.com',
                  'business.site', 'wixsite.com', 'blogspot.com', 'wordpress.com'] as $p) {
            if ($name === $p || str_ends_with($name, '.' . $p)) { return true; }
        }
        return false;
    }

    /* ==================================================================== */
    /*  Zwischenspeicher                                                    */
    /* ==================================================================== */

    /**
     * Warum ueberhaupt einer: Zwei Anrufe desselben Interessenten in einer
     * Stunde sollen nicht zweimal auf seinem Server landen. Das ist Anstand
     * gegenueber fremder Technik, und es spart am Telefon die Wartezeit.
     *
     * @return array<string,mixed>|null
     */
    private static function ausSpeicher(string $name): ?array
    {
        $roh = (string) Db::wert(
            "SELECT svalue FROM settings WHERE skey = ?",
            ['seitenblick_' . md5($name)], '');
        if ($roh === '') { return null; }
        $d = json_decode($roh, true);
        if (!is_array($d) || !isset($d['zeit'], $d['urteil'])) { return null; }
        if ((time() - (int) $d['zeit']) > self::FRISCH_MINUTEN * 60) { return null; }
        return is_array($d['urteil']) ? $d['urteil'] : null;
    }

    /** @param array<string,mixed> $urteil */
    private static function inSpeicher(string $name, array $urteil): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
                ['seitenblick_' . md5($name),
                 json_encode(['zeit' => time(), 'urteil' => $urteil], JSON_UNESCAPED_UNICODE)]);
    }
}
