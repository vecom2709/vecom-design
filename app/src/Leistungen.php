<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';

/**
 * Was laeuft, was es einbringt und was auf Uwe wartet (Phase 4, 25.09.2026).
 *
 * WARUM ES DAS GIBT
 *
 * Monatsvertraege, Abbuchungen und Hosting-Auftraege standen an drei Orten:
 * die Vertraege unter "Betreuung", das Hosting nur in der jeweiligen
 * Kundenakte, eine abgelehnte Abbuchung als Meldung auf "Heute", die man
 * wegklickt. Welche Domain noch Handarbeit braucht, liess sich nur finden,
 * indem man jeden Kunden oeffnete. Hier steht es auf einer Seite.
 *
 * WAS HIER NICHT PASSIERT
 *
 * Nichts wird geaendert, nichts verschickt. Es wird nur gelesen -- die
 * Taten bleiben dort, wo ihre Rueckfrage steht (Kundenakte, Ablauf::TRAGWEITE).
 */
final class Leistungen
{
    /** So weit voraus meldet sich ein Vertrag, der ausläuft. */
    public const AUSLAUF_TAGE = 30;

    /** @return array<string,int> */
    public static function kennzahlen(): array
    {
        $z = static fn(string $sql, array $p = []): int => (int) self::still(fn() => Db::wert($sql, $p, 0), 0);
        return [
            'monatlich'    => $z("SELECT COALESCE(SUM(betrag_cents),0) FROM abos WHERE status IN ('aktiv','gekuendigt')"),
            'vertraege'    => $z("SELECT COUNT(*) FROM abos WHERE status IN ('aktiv','gekuendigt')"),
            'automatisch'  => $z("SELECT COUNT(*) FROM abos WHERE status IN ('aktiv','gekuendigt') AND zahlmittel_id IS NOT NULL"),
            'auto_summe'   => $z("SELECT COALESCE(SUM(betrag_cents),0) FROM abos WHERE status IN ('aktiv','gekuendigt') AND zahlmittel_id IS NOT NULL"),
            'ueberfaellig' => $z("SELECT COUNT(*) FROM payments WHERE abo_id IS NOT NULL AND status <> 'bezahlt'
                                    AND status NOT IN ('rueckerstattet','teilweise_erstattet','storniert') AND faellig_am < CURDATE()
                                    AND COALESCE(method,'') <> 'abbuchung'"),
            'offen_summe'  => $z("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE abo_id IS NOT NULL AND status <> 'bezahlt'
                                    AND status NOT IN ('rueckerstattet','teilweise_erstattet','storniert') AND faellig_am < CURDATE()
                                    AND COALESCE(method,'') <> 'abbuchung'"),
            'hosting'      => $z("SELECT COUNT(*) FROM hosting_auftraege WHERE status IN ('angelegt','aktiv')"),
            'hosting_arbeit' => $z("SELECT COUNT(*) FROM hosting_auftraege WHERE status = 'in_arbeit'"),
        ];
    }

    /**
     * Was auf Uwe wartet -- je Zeile ein Satz und ein Weg dorthin.
     *
     * @return list<array{ton:string,marke:string,titel:string,text:string,link:string}>
     */
    public static function warten(): array
    {
        $aus = [];

        /* Hosting-Schritte, die Handarbeit geworden sind oder noch laufen. */
        foreach ((array) self::still(fn() => Db::all(
            "SELECT h.id, h.customer_id, h.domain, h.status AS auftrag,
                    GROUP_CONCAT(CONCAT(s.schritt, ':', s.status) ORDER BY s.id SEPARATOR ',') AS schritte
               FROM hosting_auftraege h JOIN hosting_schritte s ON s.auftrag_id = h.id
              WHERE s.status IN ('hand','fehler')
              GROUP BY h.id, h.customer_id, h.domain, h.status ORDER BY h.id"), []) as $r) {
            $hand = substr_count((string) $r['schritte'], ':hand');
            $aus[] = ['ton' => $hand > 0 ? 'schlecht' : 'warnung', 'marke' => $hand > 0 ? 'von Hand' : 'wird wiederholt',
                      'titel' => 'Hosting ' . $r['domain'],
                      'text' => $hand > 0 ? $hand . ' Schritt(e) von Hand — in der Kundenakte steht, welche.'
                                          : 'Ein Schritt wird gerade wiederholt.',
                      'link' => 'kunden/' . (int) $r['customer_id']];
        }

        /* Abgelehnte Abbuchungen und ueberfaellige Raten -- jeweils mit Kunde. */
        foreach ((array) self::still(fn() => Db::all(
            "SELECT p.id, p.bezeichnung, p.amount_cents, p.currency, p.status, p.faellig_am, a.customer_id,
                    COALESCE(NULLIF(c.company,''), NULLIF(c.name,''), c.email) AS wer
               FROM payments p JOIN abos a ON a.id = p.abo_id JOIN customers c ON c.id = a.customer_id
              WHERE p.status NOT IN ('bezahlt','rueckerstattet','teilweise_erstattet','storniert')
                AND COALESCE(p.method,'') <> 'abbuchung'
                AND (p.status = 'fehlgeschlagen' OR p.faellig_am < CURDATE())
              ORDER BY p.faellig_am, p.id LIMIT 30"), []) as $r) {
            $aus[] = ['ton' => 'warnung', 'marke' => (string) $r['status'] === 'fehlgeschlagen' ? 'fehlgeschlagen' : 'überfällig',
                      'titel' => Fmt::name($r['wer']) . ': ' . Fmt::geld((int) $r['amount_cents'], (string) $r['currency']),
                      'text' => ((string) $r['status'] === 'fehlgeschlagen' ? 'Zahlung fehlgeschlagen' : 'Überfällig seit ' . Fmt::datum((string) $r['faellig_am']))
                              . ' — ' . (string) $r['bezeichnung'],
                      'link' => 'kunden/' . (int) $r['customer_id']];
        }

        /* Vertrag vorbei, KAS-Zugang noch offen: sperren, nicht loeschen --
           und nur auf Uwes Klick in der Kundenakte (Rueckfrage). */
        try {
            require_once __DIR__ . '/Hosting.php';
            foreach (Hosting::zumSperren() as $r) {
                $aus[] = ['ton' => 'warnung', 'marke' => 'Zugang offen',
                          'titel' => Fmt::name($r['wer']) . ': ' . (string) $r['domain'],
                          'text' => 'Der Vertrag ist beendet, der KAS-Zugang steht noch offen — in der Kundenakte sperren.',
                          'link' => 'kunden/' . (int) $r['customer_id']];
            }
        } catch (Throwable $e) { /* vor Migration 062 */ }

        /* Vertraege, die bald auslaufen -- Zeit fuer ein Gespraech, nicht fuer eine Mahnung. */
        foreach ((array) self::still(fn() => Db::all(
            "SELECT a.customer_id, a.paket_name, a.laeuft_bis,
                    COALESCE(NULLIF(c.company,''), NULLIF(c.name,''), c.email) AS wer
               FROM abos a JOIN customers c ON c.id = a.customer_id
              WHERE a.status = 'gekuendigt' AND a.laeuft_bis >= CURDATE()
                AND a.laeuft_bis <= CURDATE() + INTERVAL " . (int) self::AUSLAUF_TAGE . " DAY
              ORDER BY a.laeuft_bis"), []) as $r) {
            $aus[] = ['ton' => '', 'marke' => 'läuft aus',
                      'titel' => Fmt::name($r['wer']) . ': ' . (string) $r['paket_name'],
                      'text' => 'Gekündigt, läuft am ' . Fmt::datum((string) $r['laeuft_bis']) . ' aus.',
                      'link' => 'kunden/' . (int) $r['customer_id']];
        }
        return $aus;
    }

    /**
     * Die Hosting-Auftraege mit einer Zeile Stand.
     *
     * @return list<array<string,mixed>>
     */
    public static function hosting(): array
    {
        return (array) self::still(fn() => Db::all(
            "SELECT h.*, COALESCE(NULLIF(c.company,''), NULLIF(c.name,''), c.email) AS wer,
                    (SELECT COUNT(*) FROM hosting_schritte s WHERE s.auftrag_id = h.id AND s.status IN ('fertig','entfaellt')) AS erledigt,
                    (SELECT COUNT(*) FROM hosting_schritte s WHERE s.auftrag_id = h.id) AS schritte,
                    (SELECT COUNT(*) FROM hosting_schritte s WHERE s.auftrag_id = h.id AND s.status = 'hand') AS hand
               FROM hosting_auftraege h JOIN customers c ON c.id = h.customer_id
              WHERE h.status <> 'abgelehnt'
              ORDER BY FIELD(h.status,'in_arbeit','zugestimmt','vorgeschlagen','angelegt','aktiv'), h.id DESC"), []);
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
