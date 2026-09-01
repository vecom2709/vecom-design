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
    /** So lange bleibt der private Zugang offen, wenn kein Auftrag daraus wird. */
    public const GUELTIG_TAGE = 90;

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

        // Der Zugang entsteht sofort mit. Er laeuft nach GUELTIG_TAGE ab: Wird
        // nichts daraus, soll kein Link ewig offen stehen.
        self::token($id);

        Events::protokoll('anfrage_neu', 'Anfrage von ' . $name, $kundeId);
        Events::melden('anfrage_neu', 'Neue Anfrage über die Website', 'gut',
            $name . ($paketName !== '' ? ' — ' . $paketName : ''), '/anfragen/' . $id);

        // Die Bestaetigung geht ganz zum Schluss und in einem eigenen Netz:
        // Die Anfrage steht bereits, ein stummer Mailserver darf sie nicht
        // mehr gefaehrden.
        try { self::bestaetigen($id); } catch (Throwable $e) {
            Events::melden('mail_fehler', 'Eingangsbestätigung nicht verschickt', 'schlecht',
                mb_substr($e->getMessage(), 0, 180), '/anfragen/' . $id);
        }

        return $id;
    }

    /** Erzeugt den Zugangsschluessel oder gibt den vorhandenen zurueck. */
    public static function token(int $anfrageId): string
    {
        $a = Db::one('SELECT token, token_bis FROM anfragen WHERE id = ?', [$anfrageId]);
        if (!$a) { throw new RuntimeException('Anfrage nicht gefunden.'); }
        $bis = date('Y-m-d H:i:s', strtotime('+' . self::GUELTIG_TAGE . ' days'));
        if ($a['token']) {
            // Vorhandenen Schluessel behalten, aber die Frist auffrischen —
            // wer sich meldet, soll nicht am naechsten Tag ausgesperrt sein.
            Db::update('anfragen', $anfrageId, ['token_bis' => $bis]);
            return (string) $a['token'];
        }
        for ($i = 0; $i < 5; $i++) {
            $neu = bin2hex(random_bytes(24));
            if (!Db::one('SELECT id FROM anfragen WHERE token = ?', [$neu])) {
                Db::update('anfragen', $anfrageId, ['token' => $neu, 'token_bis' => $bis]);
                return $neu;
            }
        }
        throw new RuntimeException('Zugang konnte nicht erzeugt werden.');
    }

    public static function link(string $token): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/vorgang.php?t=' . rawurlencode($token);
    }

    /** Findet die Anfrage zu einem gueltigen, nicht abgelaufenen Schluessel. */
    public static function ausToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{48}$/', $token)) { return null; }
        $a = Db::one('SELECT * FROM anfragen WHERE token = ?', [$token]);
        if (!$a) { return null; }
        if ($a['token_bis'] && strtotime((string) $a['token_bis']) < time()) { return null; }
        return $a;
    }

    /** Schickt dem Kunden die Eingangsbestaetigung. Nur einmal je Anfrage. */
    public static function bestaetigen(int $anfrageId, bool $erneut = false): bool
    {
        $a = Db::one('SELECT * FROM anfragen WHERE id = ?', [$anfrageId]);
        if (!$a) { return false; }
        if ($a['bestaetigt_am'] && !$erneut) { return false; }

        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Texte.php';
        $sprache = (string) ($a['sprache'] ?: 'it');
        $paketsatz = $a['paket_name']
            ? ['it' => ' per il pacchetto ' . $a['paket_name'],
               'de' => ' zum Paket ' . $a['paket_name'],
               'en' => ' about the ' . $a['paket_name'] . ' package'][$sprache] ?? ''
            : '';
        [$betreff, $text] = Texte::mail('anfrage_eingegangen', $sprache, [
            'name'      => (string) $a['name'],
            'paketsatz' => $paketsatz,
            'link'      => self::link(self::token($anfrageId)),
        ]);
        $ok = Mail::senden('anfrage_eingegangen', (string) $a['email'], $betreff, $text,
            ['customer_id' => $a['customer_id'] ? (int) $a['customer_id'] : null]);
        if ($ok) { Db::update('anfragen', $anfrageId, ['bestaetigt_am' => date('Y-m-d H:i:s')]); }
        return $ok;
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
