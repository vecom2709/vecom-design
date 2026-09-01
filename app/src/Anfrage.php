<?php
declare(strict_types=1);

/* ==========================================================================
   Anfrage.php — Anfragen aus dem Kontaktformular.

   Eine Anfrage ist kein Auftrag. Sie bekommt deshalb eine eigene Tabelle und
   erscheint in keiner Umsatzzahl. Was sie leistet: Der Kunde steht ab dem
   Absenden in der Verwaltung, mit allem, was er selbst eingetippt hat — und
   aus der Anfrage wird auf einen Knopf eine Bestellung samt Anzahlung.

   Grundsatz beim Annehmen: Die E-Mail hat Vorrang. Faellt die Datenbank aus,
   darf die Anfrage trotzdem nicht verloren gehen; deshalb ruft formular.php
   diese Klasse erst NACH dem Versand auf und faengt jeden Fehler ab.
   ========================================================================== */
final class Anfrage
{
    /** Nimmt eine Anfrage an: Kunde anlegen oder finden, Anfrage festhalten. */
    public static function annehmen(array $d): ?int
    {
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $name  = trim((string) ($d['name'] ?? ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { return null; }

        // Der Kunde entsteht sofort — nach E-Mail, damit ein Stammkunde, der
        // ein zweites Mal anfragt, nicht doppelt in der Liste steht.
        $kundeId = Events::kundeFinden([
            'name'  => mb_substr($name, 0, 120),
            'email' => $email,
            'phone' => mb_substr(trim((string) ($d['telefon'] ?? '')), 0, 60) ?: null,
            'notes' => 'Über das Formular auf der Website angefragt.',
        ]);

        // Ein gewaehltes Paket kommt als Kennung mit; existiert es nicht mehr,
        // bleibt wenigstens der Name stehen.
        $paketId = null;
        $paketName = trim((string) ($d['paket_name'] ?? ''));
        $slug = trim((string) ($d['paket'] ?? ''));
        if ($slug !== '' && preg_match('/^[a-z0-9-]{1,60}$/', $slug)) {
            $p = Db::one('SELECT id, name FROM packages WHERE slug = ?', [$slug]);
            if ($p) { $paketId = (int) $p['id']; $paketName = (string) $p['name']; }
        }

        $id = Db::insert('anfragen', [
            'customer_id' => $kundeId,
            'package_id'  => $paketId,
            'paket_slug'  => $slug !== '' ? mb_substr($slug, 0, 60) : null,
            'paket_name'  => mb_substr($paketName, 0, 120) ?: null,
            'name'        => mb_substr($name, 0, 120),
            'email'       => mb_substr($email, 0, 190),
            'telefon'     => mb_substr(trim((string) ($d['telefon'] ?? '')), 0, 60) ?: null,
            'website'     => mb_substr(trim((string) ($d['website_url'] ?? '')), 0, 190) ?: null,
            'sprache'     => in_array(($d['sprache'] ?? 'it'), ['it', 'de', 'en'], true) ? $d['sprache'] : 'it',
            'nachricht'   => mb_substr((string) ($d['nachricht'] ?? ''), 0, 20000) ?: null,
            'status'      => 'neu',
        ]);

        Events::protokoll('anfrage_neu', 'Anfrage von ' . $name, $kundeId);
        Events::melden('anfrage_neu', 'Neue Anfrage über die Website', 'gut',
            $name . ($paketName !== '' ? ' — ' . $paketName : ''), '/anfragen/' . $id);

        return $id;
    }

    /** Macht aus einer Anfrage eine Bestellung. Der Kunde ist schon da. */
    public static function zuBestellung(int $anfrageId, int $paketId): int
    {
        $a = Db::one('SELECT * FROM anfragen WHERE id = ?', [$anfrageId]);
        if (!$a) { throw new RuntimeException('Anfrage nicht gefunden.'); }
        if ($a['order_id']) { return (int) $a['order_id']; }

        $kundeId = (int) ($a['customer_id'] ?: Events::kundeFinden([
            'name' => $a['name'], 'email' => $a['email'], 'phone' => $a['telefon'],
        ]));

        $bestellId = Events::bestellungAnlegen($kundeId, $paketId,
            'Aus der Anfrage vom ' . date('d.m.Y', strtotime((string) $a['created_at'])) . ' entstanden.');

        Db::update('anfragen', $anfrageId, [
            'order_id' => $bestellId, 'status' => 'bestellung', 'customer_id' => $kundeId,
        ]);
        return $bestellId;
    }

    /** Erledigt oder wieder offen — ohne die Anfrage zu loeschen. */
    public static function status(int $anfrageId, string $status): void
    {
        if (!in_array($status, ['neu', 'in_arbeit', 'erledigt'], true)) { return; }
        Db::update('anfragen', $anfrageId, ['status' => $status]);
    }

    public static function offene(): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM anfragen WHERE status IN ('neu','in_arbeit')", [], 0);
    }
}
