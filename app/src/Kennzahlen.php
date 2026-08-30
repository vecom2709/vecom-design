<?php
declare(strict_types=1);

/**
 * Alle Zahlen des Dashboards. Jede einzelne wird bei jedem Aufruf aus den
 * echten Tabellen gerechnet — nichts wird zwischengespeichert, nichts ist
 * fest eingetragen. Das Dashboard ist eine Ansicht, keine Datenquelle.
 */
final class Kennzahlen
{
    public static function alle(): array
    {
        return [
            'finanzen'   => self::finanzen(),
            'bestellungen'=> self::bestellungen(),
            'projekte'   => self::projekte(),
            'kunden'     => self::kunden(),
            'kommunikation' => self::kommunikation(),
            'onboarding' => self::onboarding(),
            'websites'   => self::websites(),
            'integrationen' => self::integrationen(),
        ];
    }

    public static function finanzen(): array
    {
        $bezahlt = "SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'bezahlt'";
        return [
            'gesamtumsatz'  => (int) Db::wert($bezahlt),
            'monatsumsatz'  => (int) Db::wert($bezahlt . " AND paid_at >= ?", [date('Y-m-01 00:00:00')]),
            'heute'         => (int) Db::wert($bezahlt . " AND paid_at >= ?", [date('Y-m-d 00:00:00')]),
            // Dieselbe Abgrenzung wie Events::offenerBetrag(): Eine fehlgeschlagene
            // Zahlung ist Geld, das weiterhin aussteht. Ohne das zeigten Dashboard
            // und Bestellung zwei verschiedene Summen fuer dasselbe Wort.
            'offen'         => (int) Db::wert("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status IN ('ausstehend','in_bearbeitung','fehlgeschlagen')"),
            'bezahlte_bestellungen' => (int) Db::wert("SELECT COUNT(DISTINCT order_id) FROM payments WHERE status = 'bezahlt'"),
            'fehlgeschlagen'=> (int) Db::wert("SELECT COUNT(*) FROM payments WHERE status = 'fehlgeschlagen'"),
            'schnitt'       => (int) Db::wert("SELECT COALESCE(AVG(amount_cents),0) FROM payments WHERE status = 'bezahlt'"),
        ];
    }

    public static function bestellungen(): array
    {
        return [
            'neu'           => (int) Db::wert("SELECT COUNT(*) FROM orders WHERE status IN ('neu','zahlung_ausstehend')"),
            'in_bearbeitung'=> (int) Db::wert("SELECT COUNT(*) FROM orders WHERE status IN ('bezahlt','onboarding','in_bearbeitung','feedback','aenderungen')"),
            'abgeschlossen' => (int) Db::wert("SELECT COUNT(*) FROM orders WHERE status IN ('fertig','abgeschlossen')"),
            'gesamt'        => (int) Db::wert("SELECT COUNT(*) FROM orders"),
        ];
    }

    public static function projekte(): array
    {
        return [
            'neu'      => (int) Db::wert("SELECT COUNT(*) FROM projects WHERE status IN ('bestellung_eingegangen','zahlung_bestaetigt')"),
            'laufend'  => (int) Db::wert("SELECT COUNT(*) FROM projects WHERE status IN ('onboarding','informationen_erhalten','design','entwicklung','vorschau','aenderungen','finale_freigabe','veroeffentlichung')"),
            'feedback' => (int) Db::wert("SELECT COUNT(*) FROM projects WHERE status = 'kundenfeedback'"),
            'deadline' => (int) Db::wert("SELECT COUNT(*) FROM projects WHERE deadline IS NOT NULL AND deadline <= ? AND status <> 'abgeschlossen'", [date('Y-m-d', strtotime('+7 days'))]),
            'fertig'   => (int) Db::wert("SELECT COUNT(*) FROM projects WHERE status IN ('online','abgeschlossen')"),
        ];
    }

    public static function kunden(): array
    {
        return [
            'gesamt' => (int) Db::wert("SELECT COUNT(*) FROM customers"),
            'neu'    => (int) Db::wert("SELECT COUNT(*) FROM customers WHERE created_at >= ?", [date('Y-m-01 00:00:00')]),
            'aktiv'  => (int) Db::wert("SELECT COUNT(DISTINCT customer_id) FROM projects WHERE status NOT IN ('abgeschlossen')"),
            'offene_aufgaben' => (int) Db::wert("SELECT COUNT(DISTINCT p.customer_id) FROM projects p JOIN questionnaires q ON q.project_id = p.id WHERE q.status = 'offen'"),
        ];
    }

    public static function kommunikation(): array
    {
        return [
            'ungelesen' => (int) Db::wert("SELECT COUNT(*) FROM messages WHERE read_at IS NULL AND sender = 'kunde'"),
            'gesamt'    => (int) Db::wert("SELECT COUNT(*) FROM messages"),
        ];
    }

    public static function onboarding(): array
    {
        return [
            'offen'        => (int) Db::wert("SELECT COUNT(*) FROM questionnaires WHERE status = 'offen'"),
            'abgeschlossen'=> (int) Db::wert("SELECT COUNT(*) FROM questionnaires WHERE status = 'abgeschlossen'"),
        ];
    }

    public static function websites(): array
    {
        return [
            'gesamt'      => (int) Db::wert("SELECT COUNT(*) FROM websites"),
            'online'      => (int) Db::wert("SELECT COUNT(*) FROM websites WHERE status = 'online'"),
            'offline'     => (int) Db::wert("SELECT COUNT(*) FROM websites WHERE status = 'offline'"),
            'fehler'      => (int) Db::wert("SELECT COUNT(*) FROM websites WHERE status = 'fehler'"),
            'ssl'         => (int) Db::wert("SELECT COUNT(*) FROM websites WHERE status = 'ssl_problem'"),
            'domain'      => (int) Db::wert("SELECT COUNT(*) FROM websites WHERE status = 'domain_problem'"),
        ];
    }

    public static function integrationen(): array
    {
        return [
            'verbunden' => (int) Db::wert("SELECT COUNT(*) FROM integrations WHERE status = 'verbunden'"),
            'fehler'    => (int) Db::wert("SELECT COUNT(*) FROM integrations WHERE status = 'fehler'"),
            'webhook_fehler' => (int) Db::wert("SELECT COUNT(*) FROM webhook_events WHERE status = 'fehler'"),
        ];
    }

    /** Umsatz der letzten zwoelf Monate, fuer das Diagramm. */
    public static function umsatzverlauf(int $monate = 12): array
    {
        $reihen = Db::all(
            "SELECT DATE_FORMAT(paid_at,'%Y-%m') AS monat, SUM(amount_cents) AS summe
             FROM payments WHERE status = 'bezahlt' AND paid_at >= ?
             GROUP BY monat ORDER BY monat",
            [date('Y-m-01', strtotime(date('Y-m-01') . ' -' . ($monate - 1) . ' months'))]
        );
        $karte = array_column($reihen, 'summe', 'monat');
        $aus = [];
        // Immer vom Monatsersten aus rechnen — sonst springt der 31. Maerz
        // beim Abziehen eines Monats auf den 3. Maerz.
        $anker = date('Y-m-01');
        for ($i = $monate - 1; $i >= 0; $i--) {
            $m = date('Y-m', strtotime("$anker -$i months"));
            $aus[] = ['monat' => $m, 'summe' => (int) ($karte[$m] ?? 0)];
        }
        return $aus;
    }

    public static function beliebtestePakete(int $limit = 5): array
    {
        return Db::all(
            "SELECT o.package_name AS name, COUNT(*) AS anzahl,
                    COALESCE(SUM(CASE WHEN p.status='bezahlt' THEN p.amount_cents END),0) AS umsatz
             FROM orders o LEFT JOIN payments p ON p.order_id = o.id
             GROUP BY o.package_name ORDER BY anzahl DESC, umsatz DESC LIMIT $limit"
        );
    }

    public static function letzteAktivitaeten(int $limit = 12): array
    {
        return Db::all("SELECT * FROM activities ORDER BY created_at DESC, id DESC LIMIT $limit");
    }

    public static function naheDeadlines(int $limit = 6): array
    {
        return Db::all(
            "SELECT p.*, c.name AS kunde FROM projects p JOIN customers c ON c.id = p.customer_id
             WHERE p.deadline IS NOT NULL AND p.status <> 'abgeschlossen'
             ORDER BY p.deadline ASC LIMIT $limit"
        );
    }

    public static function meldungen(int $limit = 8): array
    {
        return Db::all("SELECT * FROM notifications WHERE read_at IS NULL ORDER BY created_at DESC LIMIT $limit");
    }
}
