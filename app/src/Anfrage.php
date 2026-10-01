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

        /* Woher sie kommt (30.09.2026): Ohne Angabe die Website wie bisher.
           Kommt sie aus dem Telegram-Bot, sagen Meldung, Zuruf und Akte das —
           der Posteingang bleibt derselbe. */
        $telegram = ($d['herkunft'] ?? '') === 'telegram';
        $ueber = $telegram ? 'Telegram' : 'die Website';

        // Der Kunde entsteht sofort — nach E-Mail, damit ein Stammkunde, der
        // ein zweites Mal anfragt, nicht doppelt in der Liste steht.
        $kundeId = Events::kundeFinden([
            'name'  => mb_substr($name, 0, 120),
            'email' => $email,
            'phone' => mb_substr(trim((string) ($d['telefon'] ?? '')), 0, 60) ?: null,
            'firma' => mb_substr(trim((string) ($d['firma'] ?? '')), 0, 160) ?: null,
            'notes' => $telegram ? 'Über den Telegram-Bot angefragt.' : 'Über das Formular auf der Website angefragt.',
        ]);

        /* LEERE FELDER DER AKTE ERGAENZEN, NIE UEBERSCHREIBEN (24.09.2026)
           Seit dem E-Mail-Einstieg entsteht der Kunde, bevor er seinen Namen
           nennt (D2) -- kundeFinden() findet ihn dann und laesst die Akte,
           wie sie ist. Ohne diese Zeilen stuende er fuer immer namenlos in
           der Verwaltung, auf Angebot und Beleg. Was schon drinsteht, bleibt:
           Eine Anfrage darf keine gepflegte Akte umschreiben. */
        foreach (['name' => $name, 'phone' => trim((string) ($d['telefon'] ?? '')),
                  'company' => trim((string) ($d['firma'] ?? ''))] as $spalte => $wert) {
            if ($wert === '') { continue; }
            Db::run("UPDATE customers SET $spalte = ? WHERE id = ? AND ($spalte IS NULL OR $spalte = '')",
                [mb_substr($wert, 0, $spalte === 'phone' ? 60 : 160), $kundeId]);
        }

        // Die Sprache gehoert an den KUNDEN, nicht nur an die Anfrage.
        //
        // Hier lag ein Fehler, den man erst am Ende der Kette sieht: Die
        // Eingangsbestaetigung nimmt die Sprache aus der Anfrage und kommt
        // richtig an. Jede spaetere Mail — Zahlung, Vorschau, Restzahlung,
        // "deine Seite ist online" — liest sie vom Kunden. Und dort stand sie
        // nie. Ein deutscher Kunde bekam eine deutsche Bestaetigung und danach
        // vier italienische Mails.
        $sprache = in_array(($d['sprache'] ?? 'it'), ['it', 'de', 'en'], true) ? (string) $d['sprache'] : 'it';
        require_once __DIR__ . '/Onboarding.php';
        /* Kam die Sprache aus einer Frage an den Kunden, ist sie eine
           Auskunft und wird als solche festgehalten. Kam sie nur daher,
           welche Fassung der Seite offen war, bleibt sie eine Vermutung --
           und ueberschreibt dann auch keine fruehere Auskunft. */
        Onboarding::spracheMerken($kundeId, $sprache, !empty($d['sprache_gefragt']));

        // Ein gewaehltes Paket kommt als Kennung mit; existiert es nicht mehr,
        // bleibt wenigstens der Name stehen.
        $paketId = null;
        $paketName = trim((string) ($d['paket_name'] ?? ''));
        $slug = trim((string) ($d['paket'] ?? ''));
        if ($slug !== '' && preg_match('/^[a-z0-9-]{1,60}$/', $slug)) {
            $p = Db::one('SELECT id, name FROM packages WHERE slug = ?', [$slug]);
            if ($p) { $paketId = (int) $p['id']; $paketName = (string) $p['name']; }
        }

        $felder = [
            'customer_id' => $kundeId,
            'package_id'  => $paketId,
            'paket_slug'  => $slug !== '' ? mb_substr($slug, 0, 60) : null,
            'paket_name'  => mb_substr($paketName, 0, 120) ?: null,
            'name'        => mb_substr($name, 0, 120),
            'email'       => mb_substr($email, 0, 190),
            'telefon'     => mb_substr(trim((string) ($d['telefon'] ?? '')), 0, 60) ?: null,
            'website'     => mb_substr(trim((string) ($d['website_url'] ?? '')), 0, 190) ?: null,
            'sprache'     => $sprache,
            'nachricht'   => mb_substr((string) ($d['nachricht'] ?? ''), 0, 20000) ?: null,
            'status'      => 'neu',
        ];
        /* Kam er über den E-Mail-Einstieg, hat er schon eine Platzhalter-Anfrage
           (Zugang::anfrageSicherstellen, K1 01.10.2026). Die wird jetzt die echte —
           sonst stünde derselbe Mensch mit zwei Anfragen da. */
        require_once __DIR__ . '/Zugang.php';
        $platz = (int) Db::wert("SELECT id FROM anfragen WHERE customer_id = ? AND order_id IS NULL AND status = 'neu' AND package_id IS NULL AND nachricht LIKE ? ORDER BY id DESC LIMIT 1",
            [$kundeId, Zugang::EINSTIEG_TEXT . '%'], 0);
        if ($platz > 0) {
            Db::update('anfragen', $platz, $felder + ['created_at' => date('Y-m-d H:i:s')]);
            $id = $platz;
        } else {
            $id = Db::insert('anfragen', $felder);
        }

        // Der Zugang entsteht sofort mit. Er laeuft nach GUELTIG_TAGE ab: Wird
        // nichts daraus, soll kein Link ewig offen stehen.
        self::token($id);

        /* Partner-Tracking (30.09.2026): Anfrage = Lead, am Besuch oder am zugeordneten Kunden. */
        try { require_once __DIR__ . '/Spur.php'; Spur::ereignis('lead_created', ['customer_id' => $kundeId, 'anfrage_id' => $id, 'meta' => ['art' => 'anfrage']]); } catch (Throwable $e) { }

        Events::protokoll('anfrage_neu', 'Anfrage von ' . $name . ($telegram ? ' (über Telegram)' : ''), $kundeId);
        Events::melden('anfrage_neu', 'Neue Anfrage über ' . $ueber, 'gut',
            $name . ($paketName !== '' ? ' — ' . $paketName : ''), '/anfragen/' . $id);

        // Und ein Zuruf aufs Handy. Ohne Namen und ohne den Text der
        // Anfrage — wer sie geschrieben hat, steht drei Sekunden spaeter in
        // der Verwaltung. Keine Sperre: Eine Anfrage ist selten genug, dass
        // jede einzelne klingeln darf.
        try {
            require_once __DIR__ . '/Zuruf.php';
            Zuruf::vormerken('anfrage',
                'Vecom Design: Neue Anfrage über ' . $ueber
                    . ($paketName !== '' ? ' (' . $paketName . ')' : '') . ".\n"
                    . rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/app/heute');
        } catch (Throwable $e) { /* der Zuruf ist Beiwerk */ }

        // Die Bestaetigung geht ganz zum Schluss und in einem eigenen Netz:
        // Die Anfrage steht bereits, ein stummer Mailserver darf sie nicht
        // mehr gefaehrden.
        // Kommt die Anfrage aus den acht Fragen, geht der Fragebogen weiter --
        // die Bestätigung sagt das, statt ein Angebot „innerhalb eines
        // Werktags“ zu versprechen, das bis zum fertigen Fragebogen gesperrt ist.
        try { self::bestaetigen($id, false, !empty($d['fragebogen_folgt']), trim((string) ($d['empfohlen_von'] ?? ''))); } catch (Throwable $e) {
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

    /**
     * Der Link, den der Kunde bekommt. Er zeigt auf die eine Kundenseite —
     * dieselbe Adresse vom ersten Kontakt bis lange nach dem Onlinegang.
     * Nur wenn zur Anfrage kein Kunde gefunden wird (sehr alte Daten), bleibt
     * es beim alten Weg; der leitet seinerseits hierher weiter.
     */
    public static function link(string $token): string
    {
        require_once __DIR__ . '/Kundenzugang.php';
        try {
            $kid = Kundenzugang::kundeZuAltemToken('anfrage', $token);
            if ($kid) { return Kundenzugang::linkFuer($kid); }
        } catch (Throwable $e) { /* dann der alte Weg */ }

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
    public static function bestaetigen(int $anfrageId, bool $erneut = false, bool $fragebogenFolgt = false, string $empfohlenVon = ''): bool
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
        require_once __DIR__ . '/Fmt.php';
        require_once __DIR__ . '/Ablage.php';
        // Vom Partner vorgestellt: eigene Mail, die sagt, wer uns den Kontakt gab.
        $anlass = $empfohlenVon !== '' ? 'partner_vorstellung' : ($fragebogenFolgt ? 'anfrage_eingegangen_fb' : 'anfrage_eingegangen');
        [$betreff, $text] = Texte::mail($anlass, $sprache, [
            'partner'   => $empfohlenVon,
            'name'      => (string) $a['name'],
            'paketsatz' => $paketsatz,
            'link'      => self::link(self::token($anfrageId)),
            // Die echte Grenze des Servers, nicht die im Kopf: Auf manchen
            // Tarifen ist sie kleiner als das, was die Anwendung erlaubt.
            'maxdatei'  => Fmt::bytes(Ablage::grenze()),
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
