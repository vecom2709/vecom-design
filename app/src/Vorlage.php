<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';

/**
 * Vorlagen fuer Nachrichten an den Kunden — und der Betreff dazu.
 *
 * WARUM ES SIE GIBT
 *
 * Vorher gab es vier Vorlagen und einen einzigen Betreff: "Eine Nachricht zu
 * deinem Projekt". Zehn Nachrichten, zehn gleiche Betreffzeilen. Der Kunde
 * findet nichts wieder, und gleichlautende Serienbetreffe sind ein Merkmal,
 * auf das Spamfilter achten.
 *
 * WARUM DREISPRACHIG
 *
 * Der freie Text geht woertlich raus. Wer deutsch an eine italienische Kundin
 * schreibt, schickt ihr deutsch — nur der Rahmen ist italienisch. Eine Vorlage
 * kennt die Sprache des Kunden und setzt den richtigen Text ein.
 *
 * WAS HIER NICHT DRINSTEHT
 *
 * Anrede und Gruss. Die haengen an der Sprache, nicht am Anlass, und stuenden
 * sonst zweiundvierzig Mal in dieser Datei. fuer() setzt sie davor und
 * dahinter.
 */
final class Vorlage
{
    /**
     * Vorlagen und Gruppen liegen daneben in vorlagen.json — vierunddreissig
     * Vorlagen mal drei Sprachen sind zu viel fuer eine PHP-Konstante, und in
     * JSON lassen sie sich ohne Escaping pflegen. Dieselbe Loesung wie bei
     * standardpakete.json.
     */
    private static function daten(): array
    {
        static $d = null;
        if ($d === null) {
            $datei = __DIR__ . '/vorlagen.json';
            $roh = is_readable($datei) ? json_decode((string) file_get_contents($datei), true) : null;
            $d = is_array($roh) ? $roh : ['gruppen' => [], 'betreuung' => [], 'vorlagen' => []];
        }
        return $d;
    }

    /** @return array<string,array<string,string>> */
    public static function gruppen(): array
    {
        return (array) (self::daten()['gruppen'] ?? []);
    }

    private const ANREDE = ['it' => 'Ciao', 'de' => 'Hallo', 'en' => 'Hello'];
    private const GRUSS  = [
        'it' => "A presto\nUwe Vetter · Vecom Design",
        'de' => "Herzliche Grüße\nUwe Vetter · Vecom Design",
        'en' => "Best regards\nUwe Vetter · Vecom Design",
    ];

    /**
     * Anrede und Gruss um einen fertigen Text legen.
     *
     * Die beiden Konstanten sind privat, und das soll so bleiben — aber die
     * Preisnachricht aus dem Bedarf braucht denselben Rahmen wie jede
     * Vorlage. Ohne diese Methode haette sie ihre eigene Anrede, und
     * spaetestens beim ersten "Hallo" gegen "Ciao" faellt auf, dass es zwei
     * Wahrheiten gibt.
     */
    public static function rahmen(string $sprache, string $vorname, string $text): string
    {
        $anrede = (self::ANREDE[$sprache] ?? self::ANREDE['it']) . ' ' . $vorname . ",\n\n";
        $gruss  = "\n\n" . (self::GRUSS[$sprache] ?? self::GRUSS['it']);
        return $anrede . $text . $gruss;
    }

    /**
     * Die Kennung fuer den Betreff. Die Bestellnummer, wenn es eine gibt —
     * sonst eine aus der Kundennummer gebildete. Berechnet, nicht gespeichert:
     * eine weitere Spalte waere eine weitere Stelle, die auseinanderlaufen kann.
     */
    public static function kennung(int $kundeId): string
    {
        $nr = (string) self::still(fn() => Db::wert(
            'SELECT order_no FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId], ''), '');
        if ($nr !== '') { return $nr; }
        return 'VD-K-' . str_pad((string) $kundeId, 4, '0', STR_PAD_LEFT);
    }

    /** Kennung vor den Betreff — genau einmal, auch wenn sie schon dasteht. */
    public static function betreff(int $kundeId, string $betreff): string
    {
        $kennung = self::kennung($kundeId);
        $betreff = trim($betreff);
        if ($betreff === '') { $betreff = 'Nachricht von Vecom Design'; }
        if (str_starts_with($betreff, '[' . $kennung . ']')) { return $betreff; }
        return '[' . $kennung . '] ' . $betreff;
    }

    /**
     * Alle Vorlagen, gefuellt und in der Sprache des Kunden.
     *
     * @return list<array{schluessel:string,gruppe:string,name:string,betreff:string,text:string}>
     */
    public static function fuer(int $kundeId): array
    {
        $k = self::still(fn() => Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]), null);
        if (!$k) { return []; }

        $sprache = strtolower((string) ($k['sprache'] ?? 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $werte = self::werte($kundeId, $k, $sprache);
        $anrede = (self::ANREDE[$sprache] ?? 'Ciao') . ' ' . $werte['{vorname}'] . ",\n\n";
        $gruss  = "\n\n" . (self::GRUSS[$sprache] ?? '');

        $aus = [];
        foreach ((array) (self::daten()['vorlagen'] ?? []) as $schluessel => $v) {
            $betreff = (string) ($v['betreff'][$sprache] ?? $v['betreff']['it'] ?? '');
            $text    = (string) ($v['text'][$sprache] ?? $v['text']['it'] ?? '');

            // Wer ein Angebot schreibt, hat noch keine Bestellung — die Preise
            // koennen also nicht aus einer kommen. Solche Vorlagen nageln ihr
            // Paket selbst fest, damit sie genau dann gefuellt sind, wenn man
            // sie braucht.
            $werteHier = $werte;
            if (!empty($v['paket'])) {
                $werteHier = array_merge($werte, self::paketWerte((string) $v['paket'], $sprache));
            }
            $aus[] = [
                'schluessel' => (string) $schluessel,
                'gruppe'     => (string) ($v['gruppe'] ?? 'anfrage'),
                'name'       => (string) ($v['name'] ?? $schluessel),
                'betreff'    => strtr($betreff, $werteHier),
                'text'       => $anrede . strtr($text, $werteHier) . $gruss,
            ];
        }
        return $aus;
    }

    /**
     * Was in die Platzhalter kommt. Unbekanntes wird zu "…" — eine leere
     * Stelle rutscht beim Lesen durch, drei Punkte nicht.
     *
     * @return array<string,string>
     */
    private static function werte(int $kundeId, array $k, string $sprache): array
    {
        $name  = trim((string) ($k['name'] ?? ''));
        $firma = trim((string) ($k['company'] ?? ''));

        // Das zuletzt bestellte Paket bestimmt Preis und Betreuung. Gibt es
        // keins, bleiben die Platzhalter sichtbar leer — dann steht dort "…"
        // und faellt beim Lesen auf, statt als Luecke durchzurutschen.
        $b = (array) self::still(fn() => Db::one(
            'SELECT o.package_name, o.price_cents, o.currency, p.slug, p.monthly_cents
               FROM orders o LEFT JOIN packages p ON p.id = o.package_id
              WHERE o.customer_id = ? ORDER BY o.id DESC LIMIT 1', [$kundeId]), []);

        $paket   = trim((string) ($b['package_name'] ?? ''));
        $waehr   = (string) ($b['currency'] ?? 'EUR');
        $preis   = (int) ($b['price_cents'] ?? 0);
        $monat   = (int) ($b['monthly_cents'] ?? 0);
        $slug    = strtolower((string) ($b['slug'] ?? ''));

        $offen = (int) self::still(fn() => Db::wert(
            "SELECT COALESCE(SUM(p.amount_cents),0) FROM payments p
             JOIN orders o ON o.id = p.order_id
             WHERE o.customer_id = ? AND p.status <> 'bezahlt'", [$kundeId], 0), 0);

        $seite = (string) self::still(function () use ($kundeId) {
            require_once __DIR__ . '/Kundenzugang.php';
            return Kundenzugang::linkFuer($kundeId);
        }, '');

        $vorschau = (string) self::still(fn() => Db::wert(
            'SELECT preview_url FROM projects WHERE customer_id = ? AND preview_url IS NOT NULL
             ORDER BY id DESC LIMIT 1', [$kundeId], ''), '');

        $punkte = static fn(string $s): string => $s !== '' ? $s : '…';

        /* Die oeffentlichen Wege, die es seit heute gibt: der Konfigurator,
           die Preisseite und der eigene Empfehlungslink. Ohne sie muesste
           Uwe jede dieser Adressen von Hand in die Nachricht tippen — und
           die Preisseite heisst in jeder Sprache anders. */
        $web = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $preisseite = $web . ['it' => '/prezzi.html', 'de' => '/de/preise.html', 'en' => '/en/pricing.html'][$sprache];

        /* Der Empfehlungscode wird hier angelegt, falls es noch keinen gibt —
           sonst waere die Empfehlungsvorlage beim ersten Oeffnen leer, also
           genau dann, wenn man sie braucht. codeFuer() kehrt sofort zurueck,
           sobald einer existiert: ein Schreibvorgang pro Kunde, nie mehr. */
        $code = (string) self::still(function () use ($kundeId) {
            require_once __DIR__ . '/Empfehlung.php';
            return Empfehlung::codeFuer($kundeId);
        }, '');
        $rabatt = (int) self::still(function () {
            require_once __DIR__ . '/Empfehlung.php';
            return Empfehlung::prozent();
        }, 15);
        $monate = (int) self::still(function () {
            require_once __DIR__ . '/Empfehlung.php';
            return Empfehlung::monate();
        }, 12);

        /* Das juengste Angebot dieses Kunden -- gemeint ist immer das, an dem
           gerade gearbeitet wird. Gibt es keines, bleiben Punkte stehen und
           fallen beim Lesen auf, statt als Luecke durchzurutschen. */
        $ang = (array) self::still(fn() => Db::one(
            'SELECT * FROM angebote WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]), []);
        $anglink = '';
        if ($ang) {
            $anglink = (string) self::still(function () use ($ang) {
                require_once __DIR__ . '/Angebot.php';
                return Angebot::link($ang);
            }, '');
        }

        return [
            '{angebotlink}'    => $anglink !== '' ? $anglink : '…',
            '{angebotnummer}'  => $punkte((string) ($ang['nummer'] ?? '')),
            '{angebotsumme}'   => isset($ang['summe_cents']) && (int) $ang['summe_cents'] > 0
                                    ? Fmt::geld((int) $ang['summe_cents'], (string) ($ang['currency'] ?? 'EUR')) : '…',
            '{konfigurator}'   => $web . '/bedarf.php?lang=' . $sprache,
            '{preisseite}'     => $preisseite,
            '{empfehlungslink}'=> $code !== '' ? $web . '/e/' . $code : '…',
            '{empfehlungscode}'=> $punkte($code),
            '{rabatt}'         => $rabatt . ' %',
            '{rabattmonate}'   => (string) $monate,
            '{vorname}'        => $punkte(explode(' ', $name)[0] ?? ''),
            '{name}'           => $punkte($name),
            '{firma}'          => $punkte($firma !== '' ? $firma : $name),
            '{paket}'          => $punkte($paket),
            '{paketpreis}'     => $preis > 0 ? Fmt::geld($preis, $waehr) : '…',
            '{anzahlung}'      => $preis > 0 ? Fmt::geld((int) round($preis / 2), $waehr) : '…',
            '{rest}'           => $preis > 0 ? Fmt::geld($preis - (int) round($preis / 2), $waehr) : '…',
            '{betreuung}'      => $monat > 0 ? Fmt::geld($monat, $waehr) : '…',
            '{betreuunginhalt}'=> self::betreuungInhalt($slug, $sprache),
            '{paketinhalt}'    => self::paketInhalt($slug, $sprache),
            '{alle_pakete}'    => self::allePakete($sprache),
            '{alle_betreuung}' => self::alleBetreuung($sprache),
            '{bestandsaufnahme}' => self::preisVon('bestandsaufnahme'),
            '{betrag}'         => $offen > 0 ? Fmt::geld($offen) : '…',
            '{seite}'          => $punkte($seite),
            '{vorschau}'       => $punkte($vorschau),
        ];
    }

    /**
     * Die paketabhaengigen Platzhalter fuer ein bestimmtes Paket — unabhaengig
     * davon, ob der Kunde schon etwas bestellt hat.
     *
     * @return array<string,string>
     */
    private static function paketWerte(string $slug, string $sprache): array
    {
        $p = (array) self::still(fn() => Db::one(
            'SELECT * FROM packages WHERE slug = ?', [$slug]), []);
        if (!$p) { return []; }

        $waehr = (string) ($p['currency'] ?? 'EUR');
        $preis = (int) ($p['price_cents'] ?? 0);
        $monat = (int) ($p['monthly_cents'] ?? 0);

        $t = ($p['texte'] ?? '') !== '' ? json_decode((string) $p['texte'], true) : null;
        $name = (string) (is_array($t) ? ($t[$sprache]['name'] ?? $p['name']) : $p['name']);

        return [
            '{paket}'           => $name,
            '{paketpreis}'      => $preis > 0 ? Fmt::geld($preis, $waehr) : '…',
            '{anzahlung}'       => $preis > 0 ? Fmt::geld((int) round($preis / 2), $waehr) : '…',
            '{rest}'            => $preis > 0 ? Fmt::geld($preis - (int) round($preis / 2), $waehr) : '…',
            '{betreuung}'       => $monat > 0 ? Fmt::geld($monat, $waehr) : '…',
            '{paketinhalt}'     => self::paketInhalt($slug, $sprache),
            '{betreuunginhalt}' => self::betreuungInhalt($slug, $sprache),
        ];
    }

    /**
     * Was die monatliche Betreuung dieses Pakets enthaelt — vollstaendig.
     *
     * Die Pakete bauen aufeinander auf, und in den Merkmalen steht deshalb
     * "Alles aus Starter, plus:". In einem Brief an den Kunden ist das
     * wertlos: Er hat die Starter-Liste nie gesehen. Also wird sie hier
     * aufsummiert, von der kleinsten Stufe bis zu seiner.
     */
    private static function betreuungInhalt(string $slug, string $sprache): string
    {
        $alle  = (array) (self::daten()['betreuung'] ?? []);
        $reihe = (array) (self::daten()['betreuung_reihe'] ?? array_keys($alle));
        if ($slug === '' || !in_array($slug, $reihe, true)) { $slug = (string) ($reihe[0] ?? ''); }

        $zeilen = [];
        foreach ($reihe as $stufe) {
            $z = (array) ($alle[$stufe] ?? []);
            foreach ((array) ($z[$sprache] ?? $z['it'] ?? []) as $eintrag) {
                $zeilen[] = (string) $eintrag;
            }
            if ($stufe === $slug) { break; }
        }
        if (!$zeilen) { return '…'; }

        $titel = (array) (self::daten()['betreuung_titel'] ?? []);
        $kopf  = (string) ($titel[$sprache] ?? $titel['it'] ?? '');
        return ($kopf !== '' ? $kopf . "\n" : '') . implode("\n", $zeilen);
    }

    /**
     * Die Merkmale des Pakets — vollstaendig und widerspruchsfrei.
     *
     * Auf der Website stehen sie aufeinander aufbauend ("Alles aus Starter,
     * plus:"). Wer sie nur aneinanderhaengt, bekommt in einem Angebot
     * "Website bis 5 Seiten" und zwei Zeilen weiter "bis zu 10 Seiten".
     * Die aufgeloesten Listen liegen deshalb fertig in vorlagen.json.
     */
    private static function paketInhalt(string $slug, string $sprache): string
    {
        $alle = (array) (self::daten()['paketinhalt'] ?? []);
        $z = (array) ($alle[$slug] ?? []);
        $zeilen = (array) ($z[$sprache] ?? $z['it'] ?? []);
        if (!$zeilen) { return '…'; }

        $titel = (array) (self::daten()['betreuung_titel'] ?? []);
        $kopf  = (string) ($titel[$sprache] ?? $titel['it'] ?? '');
        return ($kopf !== '' ? $kopf . "\n" : '') . implode("\n", $zeilen);
    }

    /** Alle Pakete mit Preis — fuer die Antwort auf "Was kostet eine Website?". */
    private static function allePakete(string $sprache): string
    {
        // Nur die Website-Pakete. Seit Erstellung und Betreuung getrennte
        // Produkte sind, stuenden hier sonst Zeilen mit 0 € Einmalpreis.
        $liste = (array) self::still(fn() => Db::all(
            "SELECT * FROM packages WHERE active = 1 AND art = 'website' ORDER BY sort, price_cents"), []);
        if (!$liste) {
            $liste = (array) self::still(fn() => Db::all(
                'SELECT * FROM packages WHERE active = 1 ORDER BY sort, price_cents'), []);
        }
        if (!$liste) { return '…'; }

        $wort = ['it' => ['una tantum', 'al mese'], 'de' => ['einmalig', 'im Monat'],
                 'en' => ['one-off', 'per month']][$sprache] ?? ['una tantum', 'al mese'];

        $aus = [];
        foreach ($liste as $p) {
            $t = $p['texte'] !== null && $p['texte'] !== '' ? json_decode((string) $p['texte'], true) : null;
            $name = (string) (is_array($t) ? ($t[$sprache]['name'] ?? $p['name']) : $p['name']);
            $sub  = (string) (is_array($t) ? ($t[$sprache]['sub'] ?? ($p['sub'] ?? '')) : ($p['sub'] ?? ''));
            // Ohne Monatspreis: Die Betreuung steht als eigener Block darunter,
            // weil sie ein eigenes Produkt ist und nicht am Paket haengt.
            $zeile = $name . ' — ' . Fmt::geld((int) $p['price_cents'], (string) $p['currency']) . ' ' . $wort[0];
            $aus[] = $zeile . ($sub !== '' ? "\n  " . $sub : '');
        }
        return implode("\n\n", $aus);
    }

    /** Die Betreuungspakete mit Preis und Inhalt — fuer ein Angebot ohne Website. */
    private static function alleBetreuung(string $sprache): string
    {
        $liste = (array) self::still(fn() => Db::all(
            "SELECT * FROM packages WHERE active = 1 AND art = 'betreuung' ORDER BY sort, monthly_cents"), []);
        if (!$liste) { return '…'; }

        $wort = ['it' => 'al mese', 'de' => 'im Monat', 'en' => 'per month'][$sprache] ?? 'al mese';
        $aus = [];
        foreach ($liste as $p) {
            $t = ($p['texte'] ?? '') !== '' ? json_decode((string) $p['texte'], true) : null;
            $name = (string) (is_array($t) ? ($t[$sprache]['name'] ?? $p['name']) : $p['name']);
            $merk = is_array($t) ? (array) ($t[$sprache]['features'] ?? []) : [];

            $zeilen = [$name . ' — ' . Fmt::geld((int) $p['monthly_cents'], (string) $p['currency']) . ' ' . $wort];
            foreach ($merk as $i => $m) {
                $m = trim((string) $m);
                if ($m === '' || ($i === 0 && str_ends_with($m, ':'))) { continue; }
                $zeilen[] = '  · ' . $m;
            }
            $aus[] = implode("\n", $zeilen);
        }
        return implode("\n\n", $aus);
    }

    /** Der Einmalpreis eines Zusatzes, etwa der Bestandsaufnahme. */
    private static function preisVon(string $slug): string
    {
        $p = (array) self::still(fn() => Db::one(
            'SELECT price_cents, currency FROM packages WHERE slug = ?', [$slug]), []);
        return !empty($p['price_cents'])
            ? Fmt::geld((int) $p['price_cents'], (string) ($p['currency'] ?? 'EUR'))
            : '…';
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
