<?php
declare(strict_types=1);

/* ==========================================================================
   Angebot.php — Aus einem Bedarf wird ein verbindlicher Preis.

   WARUM DIE MITTE UND NICHT DAS UNTERE ENDE

   Der Kunde hat eine Spanne gesehen, etwa 900 bis 1.150 Euro. Ein Angebot
   ueber 1.150 fuehlt sich an wie die Hoechststrafe, eines ueber 900 laesst
   Geld liegen. Deshalb steht in jeder Zeile zunaechst die Mitte, gerundet
   auf volle Euro — und daneben, woraus sie entstanden ist. Uwe hat nach dem
   Gespraech mehr Wissen als jede Formel; er aendert, was er besser weiss.

   DIE POSITIONEN LOESEN SICH VOM KATALOG

   Bezeichnung und Preis werden in die Angebotszeile kopiert, nicht
   verknuepft. Eine Preisrunde im Baukasten darf ein verschicktes Angebot
   niemals nachtraeglich veraendern — das waere ein gebrochenes Versprechen,
   und der Kunde haette recht.

   ANGENOMMEN WIRD OHNE KONTO

   Derselbe Gedanke wie ueberall: ein langer Schluessel in der Adresse. Beim
   Annehmen entsteht eine ganz normale Bestellung, damit alles Nachgelagerte
   unveraendert weiterlaeuft — Anzahlung, Projekt, Fragebogen, Belege.

   Dafuer braucht es ein Paket, denn daran haengt die bestehende Logik. Es
   gibt genau eines, es heisst "Individuelles Angebot", und es steht nie auf
   der Website.
   ========================================================================== */
final class Angebot
{
    /** Das interne Paket, an dem Angebots-Bestellungen haengen. */
    public const PAKET_SLUG = 'individuelles-angebot';

    /* ----------------------------------------------------------------------
       Anlegen
       ---------------------------------------------------------------------- */

    /** Die Nummer des naechsten Angebots: AN-2026-0001. */
    public static function naechsteNummer(): string
    {
        $praefix = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'angebot_praefix'", [], 'AN');
        $jahr    = date('Y');
        $hoechste = (string) Db::wert(
            "SELECT MAX(nummer) FROM angebote WHERE nummer LIKE ?", [$praefix . '-' . $jahr . '-%'], ''
        );
        // Vom Hoechstwert zaehlen, nicht von der Anzahl. Nach einer Loeschung
        // waere sonst eine Nummer zweimal vergeben.
        $lauf = $hoechste !== '' ? ((int) substr($hoechste, -4)) + 1 : 1;
        return sprintf('%s-%s-%04d', $praefix, $jahr, $lauf);
    }

    /** Sorgt dafuer, dass es das interne Paket gibt, und gibt seine Kennung. */
    public static function internesPaket(): int
    {
        $id = (int) Db::wert('SELECT id FROM packages WHERE slug = ?', [self::PAKET_SLUG], 0);
        if ($id > 0) { return $id; }

        return Db::insert('packages', [
            'slug'          => self::PAKET_SLUG,
            'name'          => 'Individuelles Angebot',
            'description'   => 'Sammelposten fuer Bestellungen aus einem Angebot. Steht nie auf der Website.',
            'price_cents'   => 0,
            'monthly_cents' => 0,
            'currency'      => 'EUR',
            // Beides ausdruecklich, nicht dem Vorgabewert ueberlassen: Die
            // Spalte oeffentlich steht per Vorgabe auf 1, und dann haengt es
            // allein an active = 0, dass dieser Sammelposten nicht als
            // Nullpreis-Karte auf der Website landet. Ein Haken zu viel in der
            // Verwaltung, und er stuende dort.
            'active'        => 0,
            'oeffentlich'   => 0,
            'art'           => 'zusatz',
        ]);
    }

    /**
     * Legt aus einem abgesendeten Bedarf ein Angebot im Entwurf an.
     *
     * Die Positionen kommen aus derselben Rechnung, die der Kunde gesehen
     * hat — und mit ihnen die Begruendung, warum der Preis so ist.
     */
    public static function ausBedarf(int $bedarfId): ?int
    {
        $bedarf = Db::one('SELECT * FROM bedarf WHERE id = ?', [$bedarfId]);
        if (!$bedarf || $bedarf['customer_id'] === null) { return null; }

        /* Gibt es schon eines, wird nicht ein zweites angelegt -- sondern
           das juengste geoeffnet. Seit es Neufassungen gibt, koennen mehrere
           an einem Bedarf haengen; gemeint ist immer die letzte. */
        $schon = (int) Db::wert(
            'SELECT id FROM angebote WHERE bedarf_id = ? ORDER BY id DESC LIMIT 1', [$bedarfId], 0);
        if ($schon > 0) { return $schon; }

        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Bedarf.php';
        $antworten = Bedarf::antworten($bedarf);
        $katalog   = Baukasten::katalog();
        $rechnung  = Baukasten::rechnen($antworten, $katalog);
        $sprache   = (string) $bedarf['sprache'];
        $tage      = max(1, (int) Db::wert("SELECT svalue FROM settings WHERE skey = 'angebot_gueltig_tage'", [], '14'));

        return (int) Db::transaktion(static function () use ($bedarf, $rechnung, $katalog, $sprache, $tage, $bedarfId) {
            $angebotId = Db::insert('angebote', [
                'nummer'      => self::naechsteNummer(),
                'customer_id' => (int) $bedarf['customer_id'],
                'bedarf_id'   => $bedarfId,
                'sprache'     => $sprache,
                'status'      => 'entwurf',
                'titel'       => 'Website für ' . ($bedarf['firma'] !== '' ? $bedarf['firma'] : $bedarf['name']),
                'token'       => bin2hex(random_bytes(24)),
                'gueltig_bis' => date('Y-m-d', strtotime("+$tage days")),
            ]);

            $sortierung = 0;
            foreach ($rechnung['positionen'] as $pos) {
                $b = $katalog[$pos['slug']] ?? null;
                if (!$b) { continue; }
                $einzel = self::mitte(
                    (int) $b['preis_cents'],
                    (int) $b['preis_bis_cents'] ?: (int) $b['preis_cents']
                );
                Db::insert('angebot_positionen', [
                    'angebot_id'    => $angebotId,
                    'baustein_slug' => (string) $pos['slug'],
                    'bezeichnung'   => Baukasten::name($b, $sprache),
                    'beschreibung'  => Baukasten::text($b, $sprache),
                    'menge'         => (int) $pos['menge'],
                    'einzel_cents'  => $einzel,
                    'summe_cents'   => $einzel * (int) $pos['menge'],
                    'monatlich'     => (int) $pos['monatlich'],
                    'sortierung'    => $sortierung += 10,
                ]);
            }

            self::summenNeu($angebotId);
            Db::update('bedarf', $bedarfId, ['status' => 'angebot']);
            return $angebotId;
        });
    }

    /* ----------------------------------------------------------------------
       Der Gegenvorschlag des Kunden
       ---------------------------------------------------------------------- */

    /** Der gespeicherte Wunsch, oder null. */
    public static function wunsch(array $a): ?array
    {
        $roh = trim((string) ($a['wunsch'] ?? ''));
        if ($roh === '') { return null; }
        $w = json_decode($roh, true);
        return is_array($w) && !empty($w['positionen']) ? $w : null;
    }

    /**
     * Was der Kunde auf seinem Angebot zusammengestellt hat.
     *
     * WAS HIER NICHT PASSIERT
     *
     * Das Angebot selbst bleibt unberuehrt. Seine Zeilen sind das, was der
     * Kunde gelesen hat -- liesse man ihn daran ruehren, wuesste hinterher
     * niemand, worauf sich eine Zusage bezog. Der Wunsch liegt daneben und
     * ist ein Vorschlag, bis Uwe daraus eine Fassung macht.
     *
     * WARUM DIE PREISE HIER NEU GEHOLT WERDEN
     *
     * Was der Browser mitschickt, sind Kreuze und Mengen -- keine Betraege.
     * Ein Preis, den der Kunde mitschicken darf, ist ein Preis, den er
     * bestimmen darf. Die Zahlen kommen deshalb aus dem Angebot selbst
     * (fuer Posten, die schon drinstehen) und sonst aus dem Katalog.
     *
     * @param array<string,int> $mengen slug => Menge; fehlt ein Slug, ist er abgewaehlt
     * @return bool Ob etwas gespeichert wurde.
     */
    public static function wunschSpeichern(int $angebotId, array $mengen): bool
    {
        $a = Db::one('SELECT * FROM angebote WHERE id = ?', [$angebotId]);
        if (!$a || (string) $a['status'] !== 'gesendet') { return false; }

        require_once __DIR__ . '/Baukasten.php';
        $sprache = (string) $a['sprache'];
        $katalog = Baukasten::katalog();
        $imAngebot = [];
        foreach (self::positionen($angebotId) as $p) {
            $imAngebot[(string) $p['baustein_slug']] = $p;
        }

        // Gar nichts angekreuzt ist kein Gegenvorschlag, sondern eine Absage.
        if (!$mengen) { return false; }

        /* Das Grundgeruest bleibt drin, auch wenn es im Formular fehlt. Der
           Haken dafuer laesst sich im Browser nicht loesen -- aber ein
           Formular kommt nicht immer aus einem Browser, und eine Website
           ohne Grundgeruest gibt es nicht. */
        foreach (Baukasten::FEST as $pflicht) {
            if (isset($imAngebot[$pflicht]) && !isset($mengen[$pflicht])) {
                $mengen[$pflicht] = (int) $imAngebot[$pflicht]['menge'];
            }
        }

        /* In der Reihenfolge des Angebots, damit die Liste beim Kunden und
           die in der Verwaltung gleich aussehen; alles Dazugenommene haengt
           hinten an. */
        $sortiert = [];
        foreach (array_keys($imAngebot) as $slug) {
            if (isset($mengen[$slug])) { $sortiert[$slug] = $mengen[$slug]; }
        }
        foreach ($mengen as $slug => $menge) {
            if (!isset($sortiert[$slug])) { $sortiert[$slug] = $menge; }
        }
        $mengen = $sortiert;

        $positionen = []; $summe = 0; $monatlich = 0; $offen = [];

        foreach ($mengen as $slug => $menge) {
            $slug  = (string) $slug;
            $menge = max(1, min(99, (int) $menge));
            $alt   = $imAngebot[$slug] ?? null;
            $b     = $katalog[$slug] ?? null;
            if (!$alt && !$b) { continue; }

            /* Was nur auf Anfrage geht, wandert ohne Zahl mit: Der Kunde sagt,
               dass er es will, den Preis nennt Uwe. */
            if (!$alt && in_array($slug, Baukasten::NUR_AUF_ANFRAGE, true)) {
                $offen[] = Baukasten::name((array) $b, $sprache);
                continue;
            }

            if ($alt) {
                $einzel = (int) $alt['einzel_cents'];
                $wort   = (string) $alt['bezeichnung'];
                $mtl    = (int) $alt['monatlich'];
                $neu    = 0;
            } else {
                $einzel = self::mitte((int) $b['preis_cents'],
                                      (int) $b['preis_bis_cents'] ?: (int) $b['preis_cents']);
                $wort   = Baukasten::name($b, $sprache);
                $mtl    = (int) $b['monatlich'];
                $neu    = 1;
            }
            if (!($b['je_einheit'] ?? 0) && !$alt) { $menge = 1; }

            $zeile = $einzel * $menge;
            if ($mtl) { $monatlich += $zeile; } else { $summe += $zeile; }
            $positionen[] = [
                'slug' => $slug, 'bezeichnung' => $wort, 'menge' => $menge,
                'einzel_cents' => $einzel, 'summe_cents' => $zeile,
                'monatlich' => $mtl, 'neu' => $neu,
            ];
        }

        // Ganz ohne Posten ist kein Gegenvorschlag, sondern eine Absage.
        if (!$positionen && !$offen) { return false; }

        Db::update('angebote', $angebotId, [
            'wunsch' => json_encode([
                'positionen'      => $positionen,
                'auf_anfrage'     => $offen,
                'summe_cents'     => $summe,
                'monatlich_cents' => $monatlich,
            ], JSON_UNESCAPED_UNICODE),
            'wunsch_am'     => date('Y-m-d H:i:s'),
            'wunsch_runden' => min(255, (int) $a['wunsch_runden'] + 1),
        ]);

        try {
            Events::melden('angebot_wunsch', 'Gegenvorschlag zum Angebot ' . $a['nummer'], 'warnung',
                Fmt::geld($summe) . ' statt ' . Fmt::geld((int) $a['summe_cents']),
                '/angebote/' . $angebotId);
        } catch (Throwable $e) { /* Beiwerk */ }

        return true;
    }

    /**
     * Eine zweite Fassung eines schon verschickten Angebots.
     *
     * WARUM NICHT EINFACH DAS ALTE AUFSCHLIESSEN
     *
     * Weil der Kunde den Link hat. Ein Angebot, das sich unter seinem Klick
     * veraendert, ist keines mehr -- und wer eine Zusage bekommt, muss sagen
     * koennen, worauf sie sich bezog. Also entsteht eine Kopie als Entwurf,
     * mit allen Posten der alten drin: Wer eine Seite dazunimmt oder die
     * Speisekarte streicht, aendert genau das eine und nicht alles neu.
     *
     * Die alte Fassung wird dabei zurueckgezogen. Ihr Link zeigt weiter das
     * alte Blatt -- es zu verstecken waere unhoeflich, der Kunde hat es ja
     * gelesen --, nimmt aber keine Zusage mehr an. Sonst haette man zwei
     * gueltige Angebote ueber dieselbe Sache im Umlauf, und welches gilt,
     * entschiede der Zufall.
     *
     * @return int|null Die Kennung der neuen Fassung, oder null wenn diese
     *                  Fassung keine Neufassung vertraegt.
     */
    public static function neuFassung(int $angebotId, bool $ausWunsch = false): ?int
    {
        $alt = Db::one('SELECT * FROM angebote WHERE id = ?', [$angebotId]);
        if (!$alt) { return null; }

        /* Ein Entwurf braucht keine: den kann man aendern. Ein angenommenes
           Angebot darf keine: daran haengt eine Bestellung, und die Zusage
           bezog sich auf dieses Blatt. Bleibt, was beim Kunden lag oder
           liegt. */
        if (!in_array((string) $alt['status'], ['gesendet', 'abgelehnt', 'abgelaufen'], true)) {
            return null;
        }
        if ($alt['ersetzt_durch'] !== null) { return (int) $alt['ersetzt_durch']; }

        $tage = max(1, (int) Db::wert("SELECT svalue FROM settings WHERE skey = 'angebot_gueltig_tage'", [], '14'));

        return (int) Db::transaktion(static function () use ($alt, $angebotId, $tage, $ausWunsch) {
            $neuId = Db::insert('angebote', [
                'nummer'            => self::naechsteNummer(),
                'customer_id'       => (int) $alt['customer_id'],
                'bedarf_id'         => $alt['bedarf_id'] !== null ? (int) $alt['bedarf_id'] : null,
                'vorgaenger_id'     => $angebotId,
                'fassung'           => min(255, (int) $alt['fassung'] + 1),
                'sprache'           => (string) $alt['sprache'],
                'status'            => 'entwurf',
                'titel'             => (string) $alt['titel'],
                'einleitung'        => $alt['einleitung'],
                'currency'          => (string) $alt['currency'],
                'anzahlung_prozent' => (int) $alt['anzahlung_prozent'],
                'token'             => bin2hex(random_bytes(24)),
                'gueltig_bis'       => date('Y-m-d', strtotime("+$tage days")),
            ]);

            /* Zwei Quellen fuer dieselbe Liste: Ohne Wunsch werden die Zeilen
               der alten Fassung uebernommen; mit Wunsch genau das, was der
               Kunde zusammengestellt hat -- damit die Fassung schon stimmt und
               nicht erst zusammengeklickt werden muss. */
            $wunsch = $ausWunsch ? self::wunsch($alt) : null;
            $quelle = [];
            if ($wunsch !== null) {
                $alteTexte = [];
                foreach (Db::all('SELECT * FROM angebot_positionen WHERE angebot_id = ?',
                                 [$angebotId]) as $p) {
                    $alteTexte[(string) $p['baustein_slug']] = (string) $p['beschreibung'];
                }
                require_once __DIR__ . '/Baukasten.php';
                $katalog = Baukasten::katalog();
                $sort = 0;
                foreach ($wunsch['positionen'] as $w) {
                    $slug = (string) $w['slug'];
                    $b    = $katalog[$slug] ?? null;
                    $quelle[] = [
                        'baustein_slug' => $slug,
                        'bezeichnung'   => (string) $w['bezeichnung'],
                        'beschreibung'  => $alteTexte[$slug]
                            ?? ($b ? Baukasten::text($b, (string) $alt['sprache']) : ''),
                        'menge'         => (int) $w['menge'],
                        'einzel_cents'  => (int) $w['einzel_cents'],
                        'summe_cents'   => (int) $w['summe_cents'],
                        'monatlich'     => (int) $w['monatlich'],
                        'sortierung'    => $sort += 10,
                    ];
                }
            } else {
                foreach (Db::all(
                    'SELECT * FROM angebot_positionen WHERE angebot_id = ? ORDER BY sortierung, id',
                    [$angebotId]) as $p) {
                    $quelle[] = [
                        'baustein_slug' => $p['baustein_slug'],
                        'bezeichnung'   => (string) $p['bezeichnung'],
                        'beschreibung'  => $p['beschreibung'],
                        'menge'         => (int) $p['menge'],
                        'einzel_cents'  => (int) $p['einzel_cents'],
                        'summe_cents'   => (int) $p['summe_cents'],
                        'monatlich'     => (int) $p['monatlich'],
                        'sortierung'    => (int) $p['sortierung'],
                    ];
                }
            }

            foreach ($quelle as $z) {
                Db::insert('angebot_positionen', ['angebot_id' => $neuId] + $z);
            }

            self::summenNeu($neuId);

            Db::update('angebote', $angebotId, [
                'status'        => 'zurueckgezogen',
                'ersetzt_durch' => $neuId,
            ]);

            try {
                Events::protokoll('angebot_neufassung',
                    'Angebot ' . $alt['nummer'] . ' zurueckgezogen, Neufassung angelegt',
                    (int) $alt['customer_id']);
            } catch (Throwable $e) { /* Beiwerk */ }

            return $neuId;
        });
    }

    /**
     * Die Mitte zwischen zwei Preisen.
     *
     * Die Regel liegt jetzt in Baukasten::mitte(), weil die Verwaltung
     * denselben Preis schon vor dem Angebot anzeigt und dem Kunden nennt.
     * Zwei Kopien derselben Rundung waeren zwei Zahlen, die irgendwann
     * auseinanderlaufen — und zwar unbemerkt.
     */
    private static function mitte(int $von, int $bis): int
    {
        require_once __DIR__ . '/Baukasten.php';
        return Baukasten::mitte($von, $bis);
    }

    /* ----------------------------------------------------------------------
       Positionen
       ---------------------------------------------------------------------- */

    public static function positionen(int $angebotId): array
    {
        return Db::all('SELECT * FROM angebot_positionen WHERE angebot_id = ? ORDER BY sortierung, id', [$angebotId]);
    }

    /** Legt einen Baustein aus dem Katalog als Position dazu. */
    public static function bausteinDazu(int $angebotId, string $slug, int $menge = 1): bool
    {
        $a = Db::one('SELECT * FROM angebote WHERE id = ?', [$angebotId]);
        if (!$a || !self::aenderbar($a)) { return false; }

        require_once __DIR__ . '/Baukasten.php';
        $b = Db::one('SELECT * FROM bausteine WHERE slug = ?', [$slug]);
        if (!$b) { return false; }

        $einzel = self::mitte((int) $b['preis_cents'], (int) $b['preis_bis_cents'] ?: (int) $b['preis_cents']);
        $menge  = max(1, $menge);
        $letzte = (int) Db::wert('SELECT COALESCE(MAX(sortierung),0) FROM angebot_positionen WHERE angebot_id = ?', [$angebotId], 0);

        Db::insert('angebot_positionen', [
            'angebot_id'    => $angebotId,
            'baustein_slug' => $slug,
            'bezeichnung'   => Baukasten::name($b, (string) $a['sprache']),
            'beschreibung'  => Baukasten::text($b, (string) $a['sprache']),
            'menge'         => $menge,
            'einzel_cents'  => $einzel,
            'summe_cents'   => $einzel * $menge,
            'monatlich'     => (int) $b['monatlich'],
            'sortierung'    => $letzte + 10,
        ]);
        self::summenNeu($angebotId);
        return true;
    }

    /** Eine freie Zeile, die es im Katalog nicht gibt. */
    public static function freieZeile(int $angebotId, string $bezeichnung, int $einzelCents,
                                      int $menge = 1, bool $monatlich = false): bool
    {
        $a = Db::one('SELECT * FROM angebote WHERE id = ?', [$angebotId]);
        if (!$a || !self::aenderbar($a) || trim($bezeichnung) === '') { return false; }

        $menge  = max(1, $menge);
        $letzte = (int) Db::wert('SELECT COALESCE(MAX(sortierung),0) FROM angebot_positionen WHERE angebot_id = ?', [$angebotId], 0);
        Db::insert('angebot_positionen', [
            'angebot_id'   => $angebotId,
            'bezeichnung'  => mb_substr(trim($bezeichnung), 0, 200),
            'menge'        => $menge,
            'einzel_cents' => max(0, $einzelCents),
            'summe_cents'  => max(0, $einzelCents) * $menge,
            'monatlich'    => $monatlich ? 1 : 0,
            'sortierung'   => $letzte + 10,
        ]);
        self::summenNeu($angebotId);
        return true;
    }

    /** Aendert Menge und Einzelpreis mehrerer Zeilen auf einmal. */
    public static function zeilenSpeichern(int $angebotId, array $mengen, array $preise): int
    {
        $a = Db::one('SELECT * FROM angebote WHERE id = ?', [$angebotId]);
        if (!$a || !self::aenderbar($a)) { return 0; }

        $wie = 0;
        Db::transaktion(static function () use ($angebotId, $mengen, $preise, &$wie) {
            foreach ($mengen as $pid => $menge) {
                $pid = (int) $pid;
                $z = Db::one('SELECT * FROM angebot_positionen WHERE id = ? AND angebot_id = ?', [$pid, $angebotId]);
                if (!$z) { continue; }
                require_once __DIR__ . '/Baukasten.php';
                $m = max(1, (int) $menge);
                $e = Baukasten::centsAus((string) ($preise[$pid] ?? '0'));
                Db::update('angebot_positionen', $pid, [
                    'menge' => $m, 'einzel_cents' => $e, 'summe_cents' => $e * $m,
                ]);
                $wie++;
            }
        });
        self::summenNeu($angebotId);
        return $wie;
    }

    public static function zeileWeg(int $angebotId, int $posId): bool
    {
        $a = Db::one('SELECT * FROM angebote WHERE id = ?', [$angebotId]);
        if (!$a || !self::aenderbar($a)) { return false; }
        Db::run('DELETE FROM angebot_positionen WHERE id = ? AND angebot_id = ?', [$posId, $angebotId]);
        self::summenNeu($angebotId);
        return true;
    }

    /** Rechnet die Summen aus den Zeilen. Einmalig und monatlich getrennt. */
    public static function summenNeu(int $angebotId): void
    {
        $einmal = (int) Db::wert(
            'SELECT COALESCE(SUM(summe_cents),0) FROM angebot_positionen WHERE angebot_id = ? AND monatlich = 0',
            [$angebotId], 0);
        $monat = (int) Db::wert(
            'SELECT COALESCE(SUM(summe_cents),0) FROM angebot_positionen WHERE angebot_id = ? AND monatlich = 1',
            [$angebotId], 0);
        Db::update('angebote', $angebotId, ['summe_cents' => $einmal, 'monatlich_cents' => $monat]);
    }

    /** Nur ein Entwurf laesst sich aendern. Was raus ist, ist raus. */
    public static function aenderbar(array $a): bool
    {
        return in_array((string) $a['status'], ['entwurf'], true);
    }

    /* ----------------------------------------------------------------------
       Versand
       ---------------------------------------------------------------------- */

    /** Schickt das Angebot an den Kunden und macht es damit verbindlich. */
    public static function senden(int $angebotId): bool
    {
        $a = Db::one('SELECT * FROM angebote WHERE id = ?', [$angebotId]);
        if (!$a || $a['status'] !== 'entwurf') { return false; }
        if ((int) $a['summe_cents'] <= 0 && (int) $a['monatlich_cents'] <= 0) { return false; }

        $tage = max(1, (int) Db::wert("SELECT svalue FROM settings WHERE skey = 'angebot_gueltig_tage'", [], '14'));
        Db::update('angebote', $angebotId, [
            'status'      => 'gesendet',
            'gesendet_am' => date('Y-m-d H:i:s'),
            // Die Frist laeuft ab dem Verschicken, nicht ab dem Anlegen.
            'gueltig_bis' => date('Y-m-d', strtotime("+$tage days")),
        ]);

        try {
            Events::protokoll('angebot_gesendet', 'Angebot ' . $a['nummer'] . ' verschickt', (int) $a['customer_id']);
        } catch (Throwable $e) { /* Beiwerk */ }

        return true;
    }

    /** Der Link, unter dem der Kunde sein Angebot sieht. */
    public static function link(array $a): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/angebot.php?t=' . rawurlencode((string) $a['token']);
    }

    /* ----------------------------------------------------------------------
       Die Kundenseite
       ---------------------------------------------------------------------- */

    public static function laden(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) { return null; }
        $a = Db::one(
            'SELECT a.*, c.name AS kunde, c.company AS firma, c.email AS kunde_email
               FROM angebote a JOIN customers c ON c.id = a.customer_id
              WHERE a.token = ?', [$token]);
        if (!$a) { return null; }
        // Ein Entwurf ist noch nicht fuer den Kunden bestimmt.
        if ($a['status'] === 'entwurf') { return null; }
        return $a;
    }

    /** Ist es abgelaufen? Wird beim Ansehen mitgeprueft, nicht nur im Cronjob. */
    public static function abgelaufen(array $a): bool
    {
        return $a['status'] === 'gesendet'
            && $a['gueltig_bis'] !== null
            && (string) $a['gueltig_bis'] < date('Y-m-d');
    }

    /**
     * Der Kunde nimmt an. Daraus entsteht eine Bestellung mit Anzahlung.
     *
     * Alles Nachgelagerte bleibt unveraendert: Zahlungsraten, Projekt,
     * Fragebogen, Belege. Deshalb der Umweg ueber Events::bestellungAnlegen
     * statt einer zweiten, eigenen Bestelllogik.
     */
    public static function annehmen(string $token, array $zustimmung = []): ?int
    {
        $a = self::laden($token);
        if (!$a || $a['status'] !== 'gesendet' || self::abgelaufen($a)) { return null; }
        return self::zusagen($a, $zustimmung);
    }

    /**
     * Zusage von Hand -- weil sie am Telefon kam.
     *
     * WARUM ES DEN WEG BRAUCHT
     *
     * Der Kunde nimmt normalerweise ueber seinen Link an, und daraus entsteht
     * die Bestellung von selbst, mit dem Betrag und den Posten aus dem
     * Angebot. Sagt er stattdessen am Telefon zu, gab es diesen Weg nicht:
     * Uwe musste eine Bestellung von Hand erfassen, ein Paket waehlen, das
     * nicht passt, und den verhandelten Betrag abtippen. Dabei geht genau
     * das verloren, wofuer das Angebot da war -- die Posten, die Zusage und
     * die Verbindung zwischen beiden.
     *
     * Abgelaufen ist hier kein Hindernis: Ob die Frist noch laeuft, weiss
     * derjenige am besten, der gerade mit dem Kunden gesprochen hat.
     */
    public static function zusagenVonHand(int $angebotId): ?int
    {
        $a = Db::one('SELECT a.*, c.name AS kunde FROM angebote a
                        JOIN customers c ON c.id = a.customer_id
                       WHERE a.id = ?', [$angebotId]);
        if (!$a || !in_array((string) $a['status'], ['gesendet', 'abgelaufen'], true)) { return null; }
        return self::zusagen($a);
    }

    /** Der gemeinsame Rumpf: Aus dem Angebot wird eine Bestellung. */
    private static function zusagen(array $a, array $zustimmung = []): ?int
    {
        $paketId = self::internesPaket();
        $notiz   = 'Aus Angebot ' . $a['nummer'] . ".\n" . self::alsText((int) $a['id']);

        $bestellId = Events::bestellungAnlegen(
            (int) $a['customer_id'],
            $paketId,
            $notiz,
            (int) $a['summe_cents'],
            (int) $a['anzahlung_prozent'],
            (string) ($a['titel'] !== '' ? $a['titel'] : 'Individuelles Angebot')
        );

        Db::update('angebote', (int) $a['id'], [
            'status'        => 'angenommen',
            'angenommen_am' => date('Y-m-d H:i:s'),
            'order_id'      => $bestellId,
        ]);

        /* WAS DER KUNDE BEIM ANNEHMEN BESTAETIGT HAT
           ----------------------------------------------------------------
           Bis hierher gab es das nur bei der Direktbuchung. Wer ueber ein
           Angebot zusagte, schloss den Vertrag, ohne die Widerrufsbelehrung
           gesehen zu haben — und ohne das ausdrueckliche Verlangen nach
           Art. 51 Abs. 8 Codice del Consumo. Ohne dieses Verlangen erlischt
           das Widerrufsrecht NICHT mit der vollstaendig erbrachten Leistung:
           Der Kunde koennte nach der fertigen Website noch widerrufen.

           Festgehalten wird nicht der Haken, sondern der Wortlaut, den er vor
           sich hatte. Was heute auf der Seite steht, kann morgen anders
           lauten; belegbar ist nur das Gezeigte.

           Leer bleibt es bei der Zusage von Hand — die kam am Telefon, dort
           hat niemand etwas angekreuzt, und eine erfundene Zustimmung waere
           schlimmer als gar keine. */
        if (!empty($zustimmung['text'])) {
            try {
                $jetzt = date('Y-m-d H:i:s');
                Db::update('orders', $bestellId, [
                    'agb_ok_am'       => $jetzt,
                    'widerruf_ok_am'  => $jetzt,
                    'zustimmung_lang' => (string) ($zustimmung['sprache'] ?? 'it'),
                    'zustimmung_text' => (string) $zustimmung['text'],
                ]);
            } catch (Throwable $e) { /* die Bestellung steht, das ist das Wichtige */ }
        }

        /* DIE ANFRAGE GEHOERT JETZT ZUR BESTELLUNG
           ----------------------------------------------------------------
           Aus dem Konfigurator entstehen ein Bedarf und eine Anfrage. Die
           Anfrage ist, solange sie keine Bestellung hat, ein eigener Vorgang
           in der Fuehrung -- "es wird geredet". Wird aus dem Angebot eine
           Bestellung, ist das Reden vorbei, aber die Anfrage wusste nichts
           davon und stand weiter als offener Vorgang da, neben der
           Bestellung, die daraus geworden ist. Zwei Zeilen fuer eine Sache,
           und die eine mit einer Meldung, die nicht stimmte.

           Ein Verweis genuegt: Ab hier laeuft alles ueber die Bestellung. */
        try {
            $anfrageId = (int) Db::wert(
                'SELECT a.id FROM anfragen a
                   LEFT JOIN bedarf b ON b.anfrage_id = a.id
                  WHERE a.order_id IS NULL
                    AND (b.id = ? OR a.customer_id = ?)
                  ORDER BY a.id DESC LIMIT 1',
                [$a['bedarf_id'] !== null ? (int) $a['bedarf_id'] : 0, (int) $a['customer_id']], 0);
            if ($anfrageId > 0) { Db::update('anfragen', $anfrageId, ['order_id' => $bestellId]); }
        } catch (Throwable $e) { /* die Bestellung steht, das ist das Wichtige */ }

        // Eine Empfehlung, die an diesem Kunden haengt, gehoert jetzt an die
        // Bestellung — verdient wird sie spaeter beim Bezahlen.
        try {
            require_once __DIR__ . '/Empfehlung.php';
            Empfehlung::anBestellung((int) $a['customer_id'], $bestellId);
        } catch (Throwable $e) { /* nachtragbar */ }

        try {
            Events::melden('angebot_angenommen', 'Angebot angenommen', 'gut',
                $a['kunde'] . ' — ' . Fmt::geld((int) $a['summe_cents']), '/bestellungen/' . $bestellId);
        } catch (Throwable $e) { /* Beiwerk */ }

        return $bestellId;
    }

    public static function ablehnen(string $token, string $grund = ''): bool
    {
        $a = self::laden($token);
        if (!$a || $a['status'] !== 'gesendet') { return false; }

        Db::update('angebote', (int) $a['id'], [
            'status'          => 'abgelehnt',
            'abgelehnt_am'    => date('Y-m-d H:i:s'),
            'abgelehnt_grund' => mb_substr(trim($grund), 0, 400),
        ]);
        try {
            Events::melden('angebot_abgelehnt', 'Angebot abgelehnt', 'warnung',
                $a['kunde'] . ($grund !== '' ? ' — ' . mb_substr($grund, 0, 120) : ''),
                '/angebote/' . (int) $a['id']);
        } catch (Throwable $e) { /* Beiwerk */ }
        return true;
    }

    /** Setzt abgelaufene Angebote um. Fuer den regelmaessigen Lauf. */
    public static function abgelaufeneSchliessen(): int
    {
        try {
            $st = Db::run(
                "UPDATE angebote SET status = 'abgelaufen'
                  WHERE status = 'gesendet' AND gueltig_bis IS NOT NULL AND gueltig_bis < CURDATE()");
            return $st->rowCount();
        } catch (Throwable $e) { return 0; }
    }

    /* ----------------------------------------------------------------------
       Darstellung
       ---------------------------------------------------------------------- */

    /**
     * Das Angebot als PDF.
     *
     * Bewusst dieselbe Anmutung wie der Zahlungsbeleg: derselbe Briefkopf,
     * dieselben Farben, derselbe Aufbau. Wer beides nacheinander bekommt,
     * soll sehen, dass es von derselben Hand stammt.
     *
     * Kein Steuerausweis. Ein Angebot ist keine Rechnung, und ohne Partita
     * IVA waere jede Steuerzeile darauf schlicht falsch.
     */
    public static function pdf(int $angebotId): string
    {
        require_once __DIR__ . '/Pdf.php';
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Rechnung.php';

        $a = Db::one('SELECT a.*, c.name AS kunde, c.company AS firma, c.street AS strasse,
                             c.zip AS plz, c.city AS ort
                        FROM angebote a JOIN customers c ON c.id = a.customer_id
                       WHERE a.id = ?', [$angebotId]);
        if (!$a) { return ''; }
        $zeilen = self::positionen($angebotId);

        $blau  = [0.024, 0.282, 0.910];
        $cyan  = [0.122, 0.910, 1.0];
        $tinte = [0.051, 0.106, 0.165];
        $grau  = [0.42, 0.46, 0.53];
        $leise = [0.60, 0.64, 0.70];
        $linie = [0.87, 0.89, 0.92];

        // Der Kunde bekommt sein Angebot in seiner Sprache — auch als PDF.
        // Dreisprachig ist hier Pflicht, nicht Kuer, und ein deutsches
        // "Gueltig bis" auf einem italienischen Angebot faellt sofort auf.
        require_once __DIR__ . '/Texte.php';
        $spr = (string) $a['sprache'];
        if (!in_array($spr, ['it', 'de', 'en'], true)) { $spr = 'it'; }
        $T = static fn(string $k): string => Texte::h(Texte::ANGEBOT[$k] ?? [], $spr);

        $p = new Pdf();
        $rand   = 56.0;
        $rechts = Pdf::A4_BREIT - $rand;

        /* ---------- Briefkopf ---------- */
        $logo = Rechnung::logo();
        $gesetzt = $logo !== null ? $p->bild($logo, $rand, 44, 98, 67) : false;
        if (!$gesetzt) {
            $bv = $p->text($rand, 62, 'VECOM', 17, true, 'links', $blau);
            $p->text($rand + $bv + 5, 62, 'DESIGN', 17, true, 'links', $tinte);
        }
        $y = 46;
        foreach (Firma::anschrift() as $i => $z) {
            $p->text($rechts, $y, $z, 8.5, $i === 0, 'rechts', $i === 0 ? $tinte : $grau);
            $y += 11.5;
        }
        $p->flaeche($rand, 124, ($rechts - $rand) * 0.38, 1.6, $blau);
        $p->flaeche($rand + ($rechts - $rand) * 0.38, 124, ($rechts - $rand) * 0.12, 1.6, $cyan);

        /* ---------- Empfaenger und Eckdaten ---------- */
        $y = 156;
        $p->text($rand, $y, $T('pdfAn'), 8, false, 'links', $leise);
        $y += 14;
        foreach (array_filter([
            trim((string) ($a['firma'] ?? '')) ?: null,
            (string) $a['kunde'],
            trim((string) ($a['strasse'] ?? '')) ?: null,
            trim(((string) ($a['plz'] ?? '')) . ' ' . ((string) ($a['ort'] ?? ''))) ?: null,
        ]) as $i => $z) {
            $p->text($rand, $y, $z, 10, $i === 0, 'links', $tinte);
            $y += 13.5;
        }

        /* Die Kundennummer steht auch hier. Das Angebot ist oft das erste
           Blatt, das der Kunde bekommt — dann hat er seine Nummer von
           Anfang an, und nicht erst mit dem ersten Beleg. */
        require_once __DIR__ . '/Kunde.php';
        $knr = Kunde::nummer((int) $a['customer_id']);

        $ey = 156;
        foreach (array_filter([
            [$T('nummer'),    (string) $a['nummer']],
            [$T('pdfDatum'),  date('d.m.Y', (int) strtotime((string) ($a['gesendet_am'] ?: $a['created_at'])))],
            [$T('pdfGueltig'), $a['gueltig_bis'] ? date('d.m.Y', (int) strtotime((string) $a['gueltig_bis'])) : '—'],
            [$T('pdfKunde'),  $knr],
        ], static fn($z) => trim((string) $z[1]) !== '') as [$was, $wert]) {
            /* Erst den Wert setzen, dann die Beschriftung links davor.
               Eine feste Spalte bei -96 reichte fuer "Datum", nicht fuer
               "Valida fino al" — dort klebte die Beschriftung am Datum. */
            $wb = $p->text($rechts, $ey, $wert, 9.5, true, 'rechts', $tinte);
            $p->text($rechts - $wb - 10, $ey, $was, 8.5, false, 'rechts', $leise);
            $ey += 15;
        }

        /* ---------- Titel ---------- */
        $y = max($y, $ey) + 18;
        if (trim((string) $a['titel']) !== '') {
            $p->text($rand, $y, (string) $a['titel'], 14, true, 'links', $tinte);
            $y += 24;
        }

        /* ---------- Positionen ---------- */
        $spalteGeld = $rechts;
        $p->text($rand, $y, $T('pdfWas'), 8, false, 'links', $leise);
        $p->text($spalteGeld, $y, $T('pdfBetrag'), 8, false, 'rechts', $leise);
        $y += 8;
        $p->linie($rand, $y, $rechts, $y, 0.7, $linie);
        $y += 16;

        $einmal = [];
        $monat  = [];
        // Kein Ternaer im Schreibkontext: PHP laesst das nicht zu, und es liest
        // sich ohnehin schlechter als zwei klare Zeilen.
        foreach ($zeilen as $z) {
            if ((int) $z['monatlich']) { $monat[] = $z; } else { $einmal[] = $z; }
        }

        $zeichnen = static function (array $z) use ($p, $rand, $rechts, $spalteGeld, $tinte, $leise, $linie, &$y, $a) {
            $bez = (string) $z['bezeichnung'] . ((int) $z['menge'] > 1 ? '  × ' . (int) $z['menge'] : '');
            $p->text($rand, $y, $bez, 10.5, true, 'links', $tinte);
            $p->text($spalteGeld, $y, Fmt::geld((int) $z['summe_cents'], (string) $a['currency']), 10.5, false, 'rechts', $tinte);
            $y += 13;
            $text = trim((string) $z['beschreibung']);
            if ($text !== '') {
                foreach ($p->umbrechen($text, ($rechts - $rand) - 110, 9) as $teil) {
                    $p->text($rand, $y, $teil, 9, false, 'links', $leise);
                    $y += 11.5;
                }
            }
            $y += 6;
            $p->linie($rand, $y - 3, $rechts, $y - 3, 0.4, $linie);
            $y += 8;
        };
        foreach ($einmal as $z) { $zeichnen($z); }

        /* ---------- Summe ---------- */
        $y += 4;
        $p->flaeche($rand, $y - 4, $rechts - $rand, 30, [0.965, 0.972, 0.980]);
        $p->text($rand + 12, $y + 15, $T('summe'), 11, true, 'links', $tinte);
        $p->text($rechts - 12, $y + 15, Fmt::geld((int) $a['summe_cents'], (string) $a['currency']), 13, true, 'rechts', $tinte);
        $y += 44;

        foreach ($monat as $z) {
            $p->text($rand, $y, (string) $z['bezeichnung'], 10, false, 'links', $grau);
            $p->text($spalteGeld, $y, Fmt::geld((int) $z['summe_cents'], (string) $a['currency']) . ' ' . $T('proMonat'), 10, false, 'rechts', $grau);
            $y += 15;
        }

        /* ---------- Zahlung und Hinweise ---------- */
        $y += 10;
        $anzahlung = (int) round((int) $a['summe_cents'] * (int) $a['anzahlung_prozent'] / 100);
        $saetze = [
            strtr($T('zahlung'), ['{anzahlung}' => Fmt::geld($anzahlung, (string) $a['currency'])]),
            $T('pdfFest'),
        ];
        // Bewusst OHNE Firma::pflichthinweis(): Der Satz gehoert auf einen
        // Zahlungsbeleg und lautet "keine Rechnung im steuerlichen Sinn". Auf
        // einem Angebot stand er kurz drauf und war schlicht falsch — hier ist
        // noch gar nichts bezahlt.

        foreach ($saetze as $satz) {
            foreach ($p->umbrechen($satz, $rechts - $rand, 9.5) as $teil) {
                $p->text($rand, $y, $teil, 9.5, false, 'links', $grau);
                $y += 12.5;
            }
            $y += 5;
        }

        /* ---------- Fusszeile ---------- */
        $fy = Pdf::A4_HOCH - 82;
        $p->linie($rand, $fy - 14, $rechts, $fy - 14, 0.5, $linie);
        foreach (Firma::fusszeilen() as $z) {
            $p->text($rand, $fy, $z, 7.6, false, 'links', $leise);
            $fy += 10;
        }

        return $p->fertig();
    }

    /** Der Dateiname, unter dem der Kunde es speichert. */
    public static function dateiname(array $a): string
    {
        return 'Angebot-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $a['nummer']) . '.pdf';
    }

    /** Die Positionen als Text — fuer die Notiz an der Bestellung. */
    public static function alsText(int $angebotId): string
    {
        $zeilen = [];
        foreach (self::positionen($angebotId) as $p) {
            $zeilen[] = ((int) $p['menge'] > 1 ? $p['menge'] . '× ' : '')
                . $p['bezeichnung'] . ': ' . Fmt::geld((int) $p['summe_cents'])
                . ((int) $p['monatlich'] ? ' im Monat' : '');
        }
        return implode("\n", $zeilen);
    }
}
