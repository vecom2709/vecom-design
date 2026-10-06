<?php
declare(strict_types=1);

/**
 * Der Umsatz-Spürhund (AI Office Stufe 3, V5, 07.10.2026).
 *
 * Uwe: sucht nach „Online ohne Betreuung“, „Seite ohne Hosting bei uns“,
 * „Angebote nachfassen“, „Wartende Interessenten“; täglich, im Briefing und
 * auf einer eigenen Seite.
 *
 * NUR FINDEN
 * Er liest und merkt sich, was er gefunden hat. Er schreibt niemandem. Was
 * daraus wird, entscheidet Uwe — oder Claude legt ihm über den Connector einen
 * Vorschlag in AI Freigaben (ClaudeEintragen::vorschlag), und raus geht es erst
 * mit Uwes Ja.
 *
 * EINE ZEILE JE CHANCE
 * Art + Bezug sind eindeutig (z. B. „angebot“ + „angebot:12“). So bleibt eine
 * Chance dieselbe, solange sie besteht; „neu“ heißt wirklich neu. Ist der
 * Anlass weg (Vertrag abgeschlossen, Angebot beantwortet), steht sie als
 * „erledigt“ da. Verworfen bleibt verworfen — sonst stünde sie morgen wieder da.
 *
 * Werte sind Richtwerte aus der eigenen Preisliste (packages), keine Zusage:
 * Betreuung zum Basispreis, Hosting zum Monatspreis, Angebote mit ihrer Summe.
 */
final class Spuerhund
{
    public const ARTEN = [
        'betreuung'   => ['Online ohne Betreuung', 'Betreuung anbieten'],
        'hosting'     => ['Seite ohne Hosting bei uns', 'Hosting anbieten'],
        'angebot'     => ['Angebot nachfassen', 'Kurz nachfragen oder neu auflegen'],
        'interessent' => ['Interessent wartet', 'Antworten — er hat Interesse gezeigt'],
    ];
    /** Ab wann ein gesendetes Angebot „ohne Antwort“ ist, und wie lange ein abgelaufenes noch zählt. */
    public const ANGEBOT_TAGE = 7;
    public const ABGELAUFEN_TAGE = 30;
    /** Ab wann eine interessierte Antwort „wartet“. */
    public const INTERESSENT_STUNDEN = 48;

    /** Für die Prüfung: eine feste Uhr. */
    public static ?int $jetzt = null;

    /**
     * Was es jetzt gibt — frisch aus den Tabellen, ohne zu speichern.
     * @return list<array{art:string,bezug:string,kunde_id:?int,projekt_id:?int,angebot_id:?int,firma_id:?int,titel:string,grund:string,vorschlag:string,wert_cents:?int,wert_art:?string}>
     */
    public static function finden(): array
    {
        $jetzt = self::$jetzt ?? time();
        $aus = [];
        $still = static function (callable $fn): array { try { return $fn(); } catch (Throwable $e) { return []; } };
        $preis = static fn(string $slug): ?int => (static function () use ($slug): ?int {
            try { $w = Db::wert('SELECT monthly_cents FROM packages WHERE slug = ?', [$slug], null); return $w === null ? null : (int) $w; } catch (Throwable $e) { return null; }
        })();
        $kundeName = static fn(array $r): string => trim((string) ($r['company'] ?? '')) !== '' ? (string) $r['company'] : (string) $r['name'];
        $fertig = "('online','abgeschlossen')";

        /* Fertige Seite, aber kein laufender Betreuungsvertrag */
        $basis = $preis('betreuung-basis');
        foreach ($still(static fn() => Db::all("SELECT c.id, c.name, c.company, MAX(p.id) AS projekt, MAX(COALESCE(p.veroeffentlicht_am, p.updated_at)) AS seit
                FROM customers c JOIN projects p ON p.customer_id = c.id AND p.demo = 0 AND p.status IN $fertig
               WHERE c.demo = 0 AND c.anonym_am IS NULL
                 AND NOT EXISTS (SELECT 1 FROM abos a WHERE a.customer_id = c.id AND a.paket_slug LIKE 'betreuung%' AND a.status IN ('angelegt','aktiv','gekuendigt'))
               GROUP BY c.id, c.name, c.company ORDER BY seit")) as $r) {
            $aus[] = ['art' => 'betreuung', 'bezug' => 'kunde:' . (int) $r['id'], 'kunde_id' => (int) $r['id'], 'projekt_id' => (int) $r['projekt'], 'angebot_id' => null, 'firma_id' => null,
                'titel' => $kundeName($r) . ': Seite online, keine Betreuung',
                'grund' => 'Die Seite ist ' . ($r['seit'] ? 'seit ' . date('d.m.Y', strtotime((string) $r['seit'])) . ' ' : '') . 'fertig; es läuft kein Betreuungsvertrag.',
                'vorschlag' => 'Betreuung anbieten' . ($basis ? ' (Basis ' . self::eur($basis) . ' im Monat)' : ''), 'wert_cents' => $basis, 'wert_art' => 'monat'];
        }

        /* Fertige Seite, aber kein Hosting bei Vecom */
        $hosting = $preis('hosting');
        foreach ($still(static fn() => Db::all("SELECT c.id, c.name, c.company, MAX(p.id) AS projekt, MAX(p.veroeffentlicht_domain) AS domain
                FROM customers c JOIN projects p ON p.customer_id = c.id AND p.demo = 0 AND p.status IN $fertig
               WHERE c.demo = 0 AND c.anonym_am IS NULL
                 AND NOT EXISTS (SELECT 1 FROM hosting_auftraege h WHERE h.customer_id = c.id AND h.status <> 'abgelehnt')
                 AND NOT EXISTS (SELECT 1 FROM abos a WHERE a.customer_id = c.id AND a.paket_slug = 'hosting' AND a.status IN ('angelegt','aktiv','gekuendigt'))
               GROUP BY c.id, c.name, c.company")) as $r) {
            $aus[] = ['art' => 'hosting', 'bezug' => 'kunde:' . (int) $r['id'], 'kunde_id' => (int) $r['id'], 'projekt_id' => (int) $r['projekt'], 'angebot_id' => null, 'firma_id' => null,
                'titel' => $kundeName($r) . ': Seite ohne Hosting bei uns',
                'grund' => 'Die Seite' . ($r['domain'] ? ' (' . $r['domain'] . ')' : '') . ' ist fertig; es gibt keinen Hosting-Auftrag bei Vecom.',
                'vorschlag' => 'Domain & Hosting anbieten' . ($hosting ? ' (' . self::eur($hosting) . ' im Monat)' : ''), 'wert_cents' => $hosting, 'wert_art' => 'monat'];
        }

        /* Angebote: gesendet ohne Antwort, oder kürzlich abgelaufen */
        $grenze = date('Y-m-d H:i:s', $jetzt - self::ANGEBOT_TAGE * 86400);
        $abgelaufenAb = date('Y-m-d', $jetzt - self::ABGELAUFEN_TAGE * 86400);
        foreach ($still(static fn() => Db::all("SELECT a.id, a.nummer, a.titel, a.status, a.summe_cents, a.monatlich_cents, a.gesendet_am, a.gueltig_bis, a.customer_id,
                       c.name, c.company
                  FROM angebote a LEFT JOIN customers c ON c.id = a.customer_id
                 WHERE a.demo = 0 AND a.angenommen_am IS NULL AND a.abgelehnt_am IS NULL AND a.ersetzt_durch IS NULL
                   AND ((a.status = 'gesendet' AND a.gesendet_am < ?) OR (a.status = 'abgelaufen' AND a.gueltig_bis >= ?))
                 ORDER BY a.gesendet_am", [$grenze, $abgelaufenAb])) as $r) {
            $tage = $r['gesendet_am'] ? (int) floor(($jetzt - strtotime((string) $r['gesendet_am'])) / 86400) : null;
            $wer = $r['customer_id'] ? $kundeName($r) : 'ohne Kunde';
            $aus[] = ['art' => 'angebot', 'bezug' => 'angebot:' . (int) $r['id'], 'kunde_id' => $r['customer_id'] ? (int) $r['customer_id'] : null, 'projekt_id' => null,
                'angebot_id' => (int) $r['id'], 'firma_id' => null,
                'titel' => $wer . ': Angebot ' . $r['nummer'] . ((string) $r['status'] === 'abgelaufen' ? ' abgelaufen' : ' ohne Antwort'),
                'grund' => (string) $r['status'] === 'abgelaufen'
                    ? 'Gültig bis ' . date('d.m.Y', strtotime((string) $r['gueltig_bis'])) . ', keine Antwort.'
                    : 'Gesendet am ' . date('d.m.Y', strtotime((string) $r['gesendet_am'])) . ($tage !== null ? ' — seit ' . $tage . ' Tagen' : '') . ' keine Antwort.',
                'vorschlag' => (string) $r['status'] === 'abgelaufen' ? 'Neu auflegen oder kurz nachfragen' : 'Kurz nachfragen',
                'wert_cents' => (int) $r['summe_cents'] > 0 ? (int) $r['summe_cents'] : ((int) $r['monatlich_cents'] > 0 ? (int) $r['monatlich_cents'] : null),
                'wert_art' => (int) $r['summe_cents'] > 0 ? 'einmal' : ((int) $r['monatlich_cents'] > 0 ? 'monat' : null)];
        }

        /* Interessenten: eine interessierte Antwort liegt seit 48 Stunden — je Betrieb einmal */
        $interesse = "'INTERESTED','CALL_REQUEST','PRICE_REQUEST','MORE_INFO'";
        foreach ($still(static fn() => Db::all("SELECT f.id, f.name, f.stadt, MIN(a.eingang_am) AS seit, COUNT(*) AS n
                  FROM akq_antworten a JOIN akq_firmen f ON f.id = a.firma_id
                 WHERE a.erledigt = 0 AND a.klasse IN ($interesse) AND a.eingang_am < ? AND f.gesperrt = 0
                 GROUP BY f.id, f.name, f.stadt ORDER BY seit", [date('Y-m-d H:i:s', $jetzt - self::INTERESSENT_STUNDEN * 3600)])) as $r) {
            $tage = (int) floor(($jetzt - strtotime((string) $r['seit'])) / 86400);
            $aus[] = ['art' => 'interessent', 'bezug' => 'firma:' . (int) $r['id'], 'kunde_id' => null, 'projekt_id' => null, 'angebot_id' => null, 'firma_id' => (int) $r['id'],
                'titel' => $r['name'] . ($r['stadt'] ? ' (' . $r['stadt'] . ')' : '') . ' wartet auf Antwort',
                'grund' => 'Interessierte Antwort seit ' . date('d.m.Y H:i', strtotime((string) $r['seit'])) . ($tage > 0 ? ' — ' . $tage . ' Tag' . ($tage === 1 ? '' : 'e') : '') . ', noch nicht erledigt.',
                'vorschlag' => 'Antworten oder anrufen', 'wert_cents' => null, 'wert_art' => null];
        }
        return $aus;
    }

    /**
     * Einmal am Tag (Cron): Gefundenes eintragen, Verschwundenes als erledigt.
     * @return array{neu:int, offen:int, erledigt:int}
     */
    public static function lauf(): array
    {
        $jetzt = date('Y-m-d H:i:s', self::$jetzt ?? time());
        $gefunden = self::finden();
        $neu = 0; $da = [];
        foreach ($gefunden as $c) {
            $da[$c['art'] . '|' . $c['bezug']] = true;
            $alt = Db::one('SELECT id, status FROM umsatz_chancen WHERE art = ? AND bezug = ?', [$c['art'], $c['bezug']]);
            $felder = ['kunde_id' => $c['kunde_id'], 'projekt_id' => $c['projekt_id'], 'angebot_id' => $c['angebot_id'], 'firma_id' => $c['firma_id'],
                'titel' => mb_substr($c['titel'], 0, 255), 'grund' => $c['grund'], 'vorschlag' => mb_substr($c['vorschlag'], 0, 255),
                'wert_cents' => $c['wert_cents'], 'wert_art' => $c['wert_art'], 'zuletzt_am' => $jetzt];
            if ($alt === null) {
                Db::insert('umsatz_chancen', ['art' => $c['art'], 'bezug' => $c['bezug'], 'status' => 'offen', 'gefunden_am' => $jetzt] + $felder);
                $neu++;
            } elseif ((string) $alt['status'] === 'erledigt') {
                // War weg und ist wieder da (z. B. Vertrag gekündigt): eine neue Chance.
                Db::update('umsatz_chancen', (int) $alt['id'], ['status' => 'offen', 'gefunden_am' => $jetzt, 'erledigt_am' => null] + $felder);
                $neu++;
            } elseif ((string) $alt['status'] === 'offen') {
                Db::update('umsatz_chancen', (int) $alt['id'], $felder);
            }
            // verworfen: bleibt verworfen, wird nicht einmal aufgefrischt
        }
        $erledigt = 0;
        foreach (Db::all("SELECT id, art, bezug FROM umsatz_chancen WHERE status = 'offen'") as $o) {
            if (!isset($da[$o['art'] . '|' . $o['bezug']])) {
                Db::run("UPDATE umsatz_chancen SET status = 'erledigt', erledigt_am = ? WHERE id = ?", [$jetzt, (int) $o['id']]);
                $erledigt++;
            }
        }
        return ['neu' => $neu, 'offen' => (int) Db::wert("SELECT COUNT(*) FROM umsatz_chancen WHERE status = 'offen'", [], 0), 'erledigt' => $erledigt];
    }

    /** @return list<array> offene Chancen, nach Art und Alter */
    public static function offen(): array
    {
        return Db::all("SELECT * FROM umsatz_chancen WHERE status = 'offen'
                         ORDER BY FIELD(art, 'interessent', 'angebot', 'betreuung', 'hosting'), gefunden_am");
    }

    /** @return list<array> zuletzt erledigte oder verworfene */
    public static function vorbei(int $n = 15): array
    {
        return Db::all("SELECT * FROM umsatz_chancen WHERE status IN ('erledigt','verworfen') ORDER BY COALESCE(erledigt_am, zuletzt_am) DESC LIMIT " . max(1, min(100, $n)));
    }

    /** Für das Briefing: offen je Art und was seit einem Zeitpunkt neu ist. @return array{offen:int, neu:int, je_art:array<string,int>, neue:list<string>, wert_monat:int, wert_einmal:int} */
    public static function stand(string $neuSeit): array
    {
        $offen = self::offen();
        $je = [];
        $neue = [];
        $monat = 0; $einmal = 0;
        foreach ($offen as $c) {
            $je[$c['art']] = ($je[$c['art']] ?? 0) + 1;
            if ((string) $c['gefunden_am'] >= $neuSeit) { $neue[] = (string) $c['titel']; }
            if ($c['wert_cents'] !== null) { if ($c['wert_art'] === 'monat') { $monat += (int) $c['wert_cents']; } else { $einmal += (int) $c['wert_cents']; } }
        }
        return ['offen' => count($offen), 'neu' => count($neue), 'je_art' => $je, 'neue' => array_slice($neue, 0, 3), 'wert_monat' => $monat, 'wert_einmal' => $einmal];
    }

    /** Uwe: „lohnt nicht“ — bleibt dann weg. */
    public static function verwerfen(int $id, string $grund, string $wer): bool
    {
        $n = Db::run("UPDATE umsatz_chancen SET status = 'verworfen', verworfen_grund = ?, verworfen_von = ?, erledigt_am = NOW()
                       WHERE id = ? AND status = 'offen'", [mb_substr(trim($grund), 0, 255) ?: null, mb_substr($wer, 0, 80), $id])->rowCount();
        if ($n > 0) {
            try { require_once __DIR__ . '/Events.php'; Events::pruefspur('umsatz_chance_verworfen', 'umsatz_chancen', $id, [], ['grund' => $grund]); } catch (Throwable $e) { }
        }
        return $n > 0;
    }

    public static function eur(int $cent): string { return number_format($cent / 100, 2, ',', '.') . ' €'; }
}
