<?php
declare(strict_types=1);

require_once __DIR__ . '/Mail.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Onboarding.php';

/**
 * Nachrichten zwischen Kunde und Verwaltung — an einem Ort statt verstreut
 * ueber E-Mail, WhatsApp und Telefon.
 *
 * Die E-Mail bleibt trotzdem der Weg, auf dem jemand erfaehrt, dass etwas
 * da ist: Niemand schaut aus Gewohnheit in eine Projektseite. Also geht bei
 * jeder Nachricht eine Benachrichtigung raus, und der Text steht gleich
 * darin — wer nur lesen will, muss nirgends klicken.
 */
final class Nachricht
{
    public const MAX_LAENGE = 5000;

    /**
     * Schreibt eine Nachricht und sagt der Gegenseite Bescheid.
     *
     * @param string $von 'admin' oder 'kunde'
     */
    /**
     * Schreiben, bevor es ein Projekt gibt. Dieselbe Tabelle, dasselbe
     * Postfach — nur haengt die Nachricht am Kunden statt am Projekt.
     * Absichtlich getrennt von schreiben(): Dort haengen Projektstand und
     * Projektlink mit drin, die es hier noch nicht gibt.
     */
    public static function vorab(int $kundeId, string $text, string $von, ?string $link = null): int
    {
        $text = trim($text);
        if ($text === '') { throw new RuntimeException('Die Nachricht ist leer.'); }
        $text = mb_substr($text, 0, self::MAX_LAENGE);

        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Kunde nicht gefunden.'); }

        $vomKunden = $von === 'kunde';
        $id = Db::insert('messages', [
            'project_id'  => null,
            'customer_id' => $kundeId,
            'sender'      => $vomKunden ? 'kunde' : 'admin',
            'user_id'     => $vomKunden ? null : Auth::id(),
            'body'        => $text,
            'read_at'     => $vomKunden ? null : date('Y-m-d H:i:s'),
        ]);

        // Vor dem Versand ins Haus melden. Die E-Mail ist der Weg nach draussen,
        // die Meldung der Weg nach innen — und nur die ueberlebt einen
        // stummen Mailserver. Ohne sie stand eine Kundennachricht vor dem
        // Auftrag nirgends im Dashboard: nicht unter den Aktivitaeten, nicht
        // unter den Benachrichtigungen. Genau so verschwindet etwas.
        if ($vomKunden) {
            try {
                Events::protokoll('nachricht_rein', 'Nachricht von ' . $k['name'], $kundeId);
                Events::melden('nachricht_rein', 'Neue Nachricht von ' . $k['name'], 'info',
                    mb_substr($text, 0, 200), '/kunden/' . $kundeId);
            } catch (Throwable $e) { /* die Nachricht steht, das zaehlt */ }
        }

        try {
            require_once __DIR__ . '/Mail.php';
            if ($vomKunden) {
                Mail::senden('nachricht_vorab', Mail::eigeneAdresse(),
                    'Nachricht von ' . $k['name'], $text . "\n\n— " . $k['name'] . ' <' . $k['email'] . '>',
                    ['customer_id' => $kundeId]);
            } else {
                $sprache = (string) ($k['sprache'] ?: 'it');
                $anrede = ['it' => 'Ciao', 'de' => 'Hallo', 'en' => 'Hello'][$sprache] ?? 'Ciao';
                $gruss  = ['it' => "A presto\nUwe Vetter · Vecom Design",
                           'de' => "Herzliche Grüße\nUwe Vetter · Vecom Design",
                           'en' => "Best regards\nUwe Vetter · Vecom Design"][$sprache] ?? '';
                $anhang = $link ? "\n\n" . (['it' => 'La tua pagina:', 'de' => 'Deine Seite:', 'en' => 'Your page:'][$sprache] ?? '') . ' ' . $link : '';
                $betreff = ['it' => 'Messaggio da Vecom Design', 'de' => 'Nachricht von Vecom Design',
                            'en' => 'A message from Vecom Design'][$sprache] ?? 'Vecom Design';
                Mail::senden('nachricht_vorab', (string) $k['email'], $betreff,
                    $anrede . ' ' . $k['name'] . ",\n\n" . $text . $anhang . "\n\n" . $gruss,
                    ['customer_id' => $kundeId]);
            }
        } catch (Throwable $e) { /* geschrieben ist geschrieben */ }

        return $id;
    }

    public static function schreiben(int $projektId, string $text, string $von): int
    {
        $text = trim($text);
        if ($text === '') { throw new RuntimeException('Die Nachricht ist leer.'); }
        $text = mb_substr($text, 0, self::MAX_LAENGE);

        $p = Db::one(
            'SELECT p.*, c.name AS kunde, c.company AS firma, c.email AS kunde_email, c.sprache AS kunde_sprache
             FROM projects p JOIN customers c ON c.id = p.customer_id WHERE p.id = ?',
            [$projektId]
        );
        if (!$p) { throw new RuntimeException('Projekt nicht gefunden.'); }

        $vomKunden = $von === 'kunde';
        $id = Db::insert('messages', [
            'project_id'  => $projektId,
            'customer_id' => (int) $p['customer_id'],
            'sender'      => $vomKunden ? 'kunde' : 'admin',
            'user_id'     => $vomKunden ? null : Auth::id(),
            'body'        => $text,
            // Was ich selbst schreibe, habe ich gelesen.
            'read_at'     => $vomKunden ? null : date('Y-m-d H:i:s'),
        ]);

        // Der Versand steht ausserhalb: Eine Nachricht ist geschrieben, auch
        // wenn der Mailserver gerade nicht mag.
        try {
            $vomKunden ? self::anUwe($p, $text) : self::anKunden($p, $text);
        } catch (Throwable $e) { /* im Protokoll steht der Fehlschlag */ }

        return $id;
    }

    private static function anKunden(array $p, string $text): void
    {
        $sprache = strtolower((string) ($p['kunde_sprache'] ?? 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        [$betreff, $inhalt] = Texte::mail('nachricht', $sprache, [
            'name' => (string) $p['kunde'],
            'text' => $text,
            'link' => self::link((int) $p['id']) ?? rtrim((string) Config::get('website', ''), '/'),
        ]);
        Mail::senden('nachricht', (string) $p['kunde_email'], $betreff, $inhalt, [
            'customer_id' => (int) $p['customer_id'],
            'project_id'  => (int) $p['id'],
            'order_id'    => $p['order_id'] !== null ? (int) $p['order_id'] : null,
            'antwortAn'   => Mail::eigeneAdresse(),
        ]);
        Events::protokoll('nachricht_raus', 'Nachricht an ' . ($p['firma'] ?: $p['kunde']),
            (int) $p['customer_id'], $p['order_id'] !== null ? (int) $p['order_id'] : null, (int) $p['id']);
    }

    private static function anUwe(array $p, string $text): void
    {
        $wer = (string) ($p['firma'] ?: $p['kunde']);
        Events::protokoll('nachricht_rein', 'Nachricht von ' . $wer,
            (int) $p['customer_id'], $p['order_id'] !== null ? (int) $p['order_id'] : null, (int) $p['id']);
        Events::melden('nachricht_rein', 'Neue Nachricht von ' . $wer, 'info',
            mb_substr($text, 0, 200), '/projekte/' . (int) $p['id']);

        Mail::senden('nachricht_rein', Mail::eigeneAdresse(),
            'Nachricht von ' . $wer,
            $wer . " hat zum Projekt \"" . (string) $p['name'] . "\" geschrieben:\n\n" . $text
            . "\n\nAntworten in der Verwaltung: " . rtrim((string) Config::get('website', ''), '/')
            . Config::basis() . '/projekte/' . (int) $p['id'] . "\n",
            [
                'customer_id' => (int) $p['customer_id'],
                'project_id'  => (int) $p['id'],
                'antwortAn'   => (string) $p['kunde_email'],
            ]);
    }

    /**
     * Die Adresse, unter der der Kunde sein Projekt sieht. Sie benutzt
     * denselben Schluessel wie der Fragebogen — es ist derselbe Mensch, und
     * ein zweiter Schluessel waere ein zweiter, der verloren gehen kann.
     */
    public static function link(int $projektId): ?string
    {
        $f = Db::one('SELECT id FROM questionnaires WHERE project_id = ?', [$projektId]);
        if (!$f) { return null; }
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/projekt.php?t=' . rawurlencode(Onboarding::token((int) $f['id']));
    }

    /* ---------- Was der Kunde von allein erfaehrt ---------- */

    /**
     * Sagt dem Kunden Bescheid, wenn sich am Projekt etwas aendert, das ihn
     * angeht. Wird beim Statuswechsel gerufen — aber nur bei einem, den ein
     * Mensch ausgeloest hat, nie bei den automatischen Zwischenschritten.
     *
     * Jede dieser Nachrichten geht genau einmal raus. Das Protokoll in der
     * Tabelle mails ist das Gedaechtnis dafuer: Wer den Status zweimal
     * setzt, schickt nicht zweimal.
     */
    public static function beiStatuswechsel(int $projektId, string $neu): void
    {
        try {
            match ($neu) {
                'vorschau'        => self::vorschauBereit($projektId),
                'finale_freigabe' => self::restzahlungAnfordern($projektId),
                'online'          => self::onlineUndRest($projektId),
                default           => null,
            };
        } catch (Throwable $e) {
            // Eine E-Mail darf einen Statuswechsel nicht rueckgaengig machen.
            try {
                Events::melden('mail_fehler', 'Nachricht an den Kunden ging nicht raus', 'schlecht',
                    $e->getMessage(), '/projekte/' . $projektId);
            } catch (Throwable $e2) { }
        }
    }

    /** Die Vorschau steht — mit Link auf die Kundenseite, wo sie verlinkt ist. */
    public static function vorschauBereit(int $projektId): bool
    {
        $p = self::projektMitKunde($projektId);
        if (!$p) { return false; }
        if (Mail::schonGeschickt('vorschau', 'project_id', $projektId)) { return false; }

        [$betreff, $text] = Texte::mail('vorschau', self::sprache($p), [
            'name'  => (string) $p['kunde'],
            'paket' => (string) ($p['paket'] ?? ''),
            'link'  => self::link($projektId) ?? (string) $p['preview_url'],
        ]);
        return self::raus('vorschau', $p, $betreff, $text);
    }

    /** Die Seite ist online — und wenn noch Geld offen ist, steht es dabei. */
    private static function onlineUndRest(int $projektId): void
    {
        $p = self::projektMitKunde($projektId);
        if (!$p) { return; }

        if (!Mail::schonGeschickt('online', 'project_id', $projektId)) {
            $w = Db::one('SELECT url FROM websites WHERE project_id = ?', [$projektId]);
            [$betreff, $text] = Texte::mail('online', self::sprache($p), [
                'name'  => (string) $p['kunde'],
                'paket' => (string) ($p['paket'] ?? ''),
                'link'  => (string) ($w['url'] ?? self::link($projektId) ?? ''),
            ]);
            self::raus('online', $p, $betreff, $text);
        }
        // Falls die finale Freigabe uebersprungen wurde, jetzt nachholen.
        self::restzahlungAnfordern($projektId);
    }

    /**
     * Fordert die zweite Haelfte an. Bewusst bei der finalen Freigabe und
     * nicht erst, wenn die Seite online ist: Danach hat man nichts mehr in
     * der Hand.
     */
    public static function restzahlungAnfordern(int $projektId): bool
    {
        $p = self::projektMitKunde($projektId);
        if (!$p || $p['order_id'] === null) { return false; }
        $bestellId = (int) $p['order_id'];

        $z = Db::one("SELECT * FROM payments WHERE order_id = ? AND art = 'restzahlung'
                      AND status IN ('ausstehend','fehlgeschlagen') ORDER BY id LIMIT 1", [$bestellId]);
        if (!$z) { return false; }   // nichts offen
        if (Mail::schonGeschickt('restzahlung', 'order_id', $bestellId)) { return false; }

        // Wenn Stripe bereit ist, kommt ein Bezahlknopf in die E-Mail. Wenn
        // nicht, bekommt der Kunde trotzdem Bescheid — dann eben mit dem
        // Hinweis auf seine Projektseite.
        $link = self::link($projektId) ?? '';
        try {
            require_once __DIR__ . '/Zahlung/Anbieter.php';
            require_once __DIR__ . '/Zahlung/Stripe.php';
            $stripe = new StripeAnbieter();
            if ($stripe->bereit()) {
                $b = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
                $k = Db::one('SELECT * FROM customers WHERE id = ?', [(int) $p['customer_id']]);
                $link = $stripe->bezahlseite($z, $b, $k);
                Db::update('payments', (int) $z['id'], [
                    'provider' => 'stripe', 'status' => 'in_bearbeitung',
                    'link_url' => $link, 'link_bis' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                ]);
            }
        } catch (Throwable $e) {
            Events::melden('integration_fehler', 'Zahlungslink für die Restzahlung ging nicht', 'warnung',
                $e->getMessage() . ' — die E-Mail geht trotzdem raus, mit dem Link auf die Projektseite.',
                '/bestellungen/' . $bestellId);
        }

        [$betreff, $text] = Texte::mail('restzahlung', self::sprache($p), [
            'name'   => (string) $p['kunde'],
            'paket'  => (string) ($p['paket'] ?? ''),
            'betrag' => Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
            'link'   => $link,
        ]);
        $ok = self::raus('restzahlung', $p, $betreff, $text);
        if ($ok) {
            Events::protokoll('restzahlung_angefordert',
                'Restzahlung angefordert: ' . Fmt::geld((int) $z['amount_cents'], (string) $z['currency']),
                (int) $p['customer_id'], $bestellId, $projektId);
        }
        return $ok;
    }

    /** @return array<string,mixed>|null */
    private static function projektMitKunde(int $projektId): ?array
    {
        return Db::one(
            'SELECT p.*, c.name AS kunde, c.company AS firma, c.email AS kunde_email,
                    c.sprache AS kunde_sprache, o.package_name AS paket
             FROM projects p
             JOIN customers c ON c.id = p.customer_id
             LEFT JOIN orders o ON o.id = p.order_id
             WHERE p.id = ?',
            [$projektId]
        );
    }

    private static function sprache(array $p): string
    {
        $s = strtolower((string) ($p['kunde_sprache'] ?? 'it'));
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }

    private static function raus(string $anlass, array $p, string $betreff, string $text): bool
    {
        return Mail::senden($anlass, (string) $p['kunde_email'], $betreff, $text, [
            'customer_id' => (int) $p['customer_id'],
            'project_id'  => (int) $p['id'],
            'order_id'    => $p['order_id'] !== null ? (int) $p['order_id'] : null,
            'antwortAn'   => Mail::eigeneAdresse(),
        ]);
    }

    /** Alles vom Kunden auf gelesen setzen. */
    public static function gelesen(int $projektId): int
    {
        return Db::run(
            "UPDATE messages SET read_at = NOW() WHERE project_id = ? AND read_at IS NULL AND sender = 'kunde'",
            [$projektId]
        )->rowCount();
    }

    /**
     * Dasselbe fuer die Nachrichten, die noch an keinem Projekt haengen.
     *
     * Ohne diesen Weg blieb der Zaehler neben "Nachrichten" fuer immer
     * stehen: Der Zaehler zaehlt jede ungelesene Kundennachricht, gelesen
     * gesetzt wurde aber nur, was ein Projekt hatte. Ein Zaehler, der nie
     * wieder auf null geht, wird nach zwei Wochen nicht mehr angesehen —
     * und dann faellt auch die naechste echte Nachricht nicht mehr auf.
     */
    public static function gelesenKunde(int $kundeId): int
    {
        return Db::run(
            "UPDATE messages SET read_at = NOW()
             WHERE customer_id = ? AND project_id IS NULL AND read_at IS NULL AND sender = 'kunde'",
            [$kundeId]
        )->rowCount();
    }
}
