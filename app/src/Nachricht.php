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
    public static function vorab(int $kundeId, string $text, string $von, ?string $link = null,
                                 ?string $betreff = null): int
    {
        $text = trim($text);
        if ($text === '') { throw new RuntimeException('Die Nachricht ist leer.'); }
        $text = mb_substr($text, 0, self::MAX_LAENGE);

        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { throw new RuntimeException('Kunde nicht gefunden.'); }

        $vomKunden = $von === 'kunde';
        $id = Db::insert('messages', self::spalten([
            'project_id'  => null,
            'customer_id' => $kundeId,
            'sender'      => $vomKunden ? 'kunde' : 'admin',
            'betreff'     => $betreff !== null && trim($betreff) !== '' ? mb_substr(trim($betreff), 0, 200) : null,
            'user_id'     => $vomKunden ? null : Auth::id(),
            'body'        => $text,
            'read_at'     => $vomKunden ? null : date('Y-m-d H:i:s'),
        ]));

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
                // Der Link nur, wenn er nicht ohnehin schon im Text steht —
                // eine Vorlage bringt ihn oft selbst mit.
                $anhang = ($link && !str_contains($text, $link))
                    ? "\n\n" . (['it' => 'La tua pagina:', 'de' => 'Deine Seite:', 'en' => 'Your page:'][$sprache] ?? '') . ' ' . $link
                    : '';

                // Ein eigener Betreff heisst: Der Text ist ein fertiger Brief
                // mit Anrede und Gruss. Dann darf kein zweiter Rahmen drum.
                require_once __DIR__ . '/Vorlage.php';
                if ($betreff !== null && trim($betreff) !== '') {
                    $zeile = Vorlage::betreff($kundeId, $betreff);
                    $rumpf = $text . $anhang;
                } else {
                    $zeile = Vorlage::betreff($kundeId,
                        ['it' => 'Messaggio da Vecom Design', 'de' => 'Nachricht von Vecom Design',
                         'en' => 'A message from Vecom Design'][$sprache] ?? 'Vecom Design');
                    $rumpf = $anrede . ' ' . $k['name'] . ",\n\n" . $text . $anhang . "\n\n" . $gruss;
                }
                Mail::senden('nachricht_vorab', (string) $k['email'], $zeile, $rumpf,
                    ['customer_id' => $kundeId]);
            }
        } catch (Throwable $e) { /* geschrieben ist geschrieben */ }

        return $id;
    }

    public static function schreiben(int $projektId, string $text, string $von, ?string $betreff = null): int
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
        $id = Db::insert('messages', self::spalten([
            'project_id'  => $projektId,
            'customer_id' => (int) $p['customer_id'],
            'sender'      => $vomKunden ? 'kunde' : 'admin',
            'betreff'     => $betreff !== null && trim($betreff) !== '' ? mb_substr(trim($betreff), 0, 200) : null,
            'user_id'     => $vomKunden ? null : Auth::id(),
            'body'        => $text,
            // Was ich selbst schreibe, habe ich gelesen.
            'read_at'     => $vomKunden ? null : date('Y-m-d H:i:s'),
        ]));

        // Der Versand steht ausserhalb: Eine Nachricht ist geschrieben, auch
        // wenn der Mailserver gerade nicht mag.
        try {
            $vomKunden ? self::anUwe($p, $text) : self::anKunden($p, $text, $betreff);
        } catch (Throwable $e) { /* im Protokoll steht der Fehlschlag */ }

        return $id;
    }

    private static function anKunden(array $p, string $text, ?string $eigenerBetreff = null): void
    {
        $sprache = strtolower((string) ($p['kunde_sprache'] ?? 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        require_once __DIR__ . '/Vorlage.php';
        $kid  = (int) $p['customer_id'];
        $link = self::link((int) $p['id']) ?? rtrim((string) Config::get('website', ''), '/');

        if ($eigenerBetreff !== null && trim($eigenerBetreff) !== '') {
            // Ein Brief mit eigenem Betreff traegt Anrede und Gruss schon in
            // sich. Der alte Rahmen ("wir haben dir geschrieben:") waere ein
            // zweiter Umschlag um einen fertigen Brief.
            $betreff = Vorlage::betreff($kid, $eigenerBetreff);
            $inhalt  = $text . (str_contains($text, $link) ? '' : "\n\n"
                . (['it' => 'La tua pagina:', 'de' => 'Deine Seite:', 'en' => 'Your page:'][$sprache] ?? '') . ' ' . $link);
        } else {
            [$betreff, $inhalt] = Texte::mail('nachricht', $sprache, [
                'name' => (string) $p['kunde'],
                'text' => $text,
                'link' => $link,
            ]);
            $betreff = Vorlage::betreff($kid, $betreff);
        }

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
        require_once __DIR__ . '/Kundenzugang.php';
        $kid = (int) Db::wert('SELECT customer_id FROM projects WHERE id = ?', [$projektId], 0);
        if ($kid > 0) {
            try { return Kundenzugang::linkFuer($kid); } catch (Throwable $e) { /* dann der alte Weg */ }
        }
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

    /**
     * Die Auftragsbestaetigung — auf einem dauerhaften Datentraeger.
     *
     * Art. 51 Abs. 7 Codice del Consumo verlangt sie bei einem
     * Fernabsatzvertrag mit einem Verbraucher, spaetestens bevor die
     * Leistung beginnt. Deshalb geht sie hier raus, wenn die Anzahlung
     * bestaetigt ist: Ab dann wird gearbeitet.
     *
     * Sie traegt, was Art. 49 Abs. 1 verlangt — wer die Leistung erbringt
     * samt Anschrift und Kontakt, was bestellt wurde, der Gesamtpreis, die
     * Raten, das Widerrufsrecht mit Frist und Verfahren — und im Anhang das
     * Muster-Widerrufsformular. Liegt schon ein Beleg vor, kommt er mit:
     * Ein Dokument, das nur zum Herunterladen irgendwo liegt, erreicht
     * niemanden.
     *
     * Geht genau einmal raus. Das Protokoll in der Tabelle mails ist das
     * Gedaechtnis dafuer.
     */
    public static function auftragsbestaetigung(int $projektId): bool
    {
        $p = self::projektMitKunde($projektId);
        if (!$p || $p['order_id'] === null) { return false; }
        $bestellId = (int) $p['order_id'];
        if (Mail::schonGeschickt('auftragsbestaetigung', 'order_id', $bestellId)) { return false; }

        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Widerruf.php';
        require_once __DIR__ . '/Rechnung.php';

        $sprache = self::sprache($p);
        $b = Db::one('SELECT * FROM orders WHERE id = ?', [$bestellId]);
        if (!$b) { return false; }
        $waehrung = (string) ($b['currency'] ?? 'EUR');
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');

        /* Die Raten, so wie sie tatsaechlich in der Bestellung stehen. */
        $zeilen = [];
        foreach (Db::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id', [$bestellId]) as $z) {
            $stand = (string) $z['status'] === 'bezahlt'
                ? ['it' => 'pagato', 'de' => 'bezahlt', 'en' => 'paid'][$sprache]
                : ['it' => 'da pagare', 'de' => 'offen', 'en' => 'outstanding'][$sprache];
            // mb_str_pad, nicht str_pad: Ein "Ü" ist zwei Bytes, und mit
            // str_pad rutscht jede Zeile mit Umlaut aus der Spalte.
            $bez = (string) $z['bezeichnung'];
            $fuell = max(0, 34 - mb_strlen($bez));
            $zeilen[] = '            ' . $bez . str_repeat(' ', $fuell)
                . Fmt::geld((int) $z['amount_cents'], $waehrung) . '  (' . $stand . ')';
        }

        /* Wer die Leistung erbringt — Art. 49 Abs. 1 Buchstabe c. */
        $firma = array_merge(Firma::anschrift(), array_filter([
            Firma::get('telefon') !== '' ? 'Tel. ' . Firma::get('telefon') : '',
            Firma::get('email'),
            Firma::get('steuernr') !== '' ? 'C.F. ' . Firma::get('steuernr') : '',
            Firma::get('piva') !== '' ? 'P. IVA ' . Firma::get('piva') : '',
        ]));

        /* Was der Kunde beim Buchen bestaetigt hat, im Wortlaut und mit
           Zeitpunkt — das ist der Nachweis nach Art. 51 Abs. 8. */
        $zustimmung = '';
        if (!empty($b['zustimmung_text'])) {
            $wann = (string) ($b['widerruf_ok_am'] ?? $b['agb_ok_am'] ?? '');
            $kopf = ['it' => 'Hai confermato al momento dell\'ordine',
                     'de' => 'Beim Bestellen hast du bestätigt',
                     'en' => 'At the time of ordering you confirmed'][$sprache];
            $zustimmung = $kopf . ($wann !== '' ? ' (' . Fmt::datum($wann) . '):' : ':') . "\n"
                . preg_replace('~^~m', '  ', trim((string) $b['zustimmung_text']));
        }

        [$betreff, $text] = Texte::mail('auftragsbestaetigung', $sprache, [
            'name'      => (string) $p['kunde'],
            'paket'     => (string) ($p['paket'] ?? $b['package_name'] ?? ''),
            'bestellnr' => (string) $b['order_no'],
            'datum'     => Fmt::datum((string) $b['created_at']),
            'gesamt'    => Fmt::geld((int) $b['price_cents'], $waehrung),
            'raten'     => $zeilen ? "\n" . implode("\n", $zeilen) : '',
            'firma'     => implode("\n", $firma),
            'widerruf'  => Widerruf::t('widText', $sprache),
            'zustimmung'=> $zustimmung,
            'agb'       => $basis . '/legal.html#agb',
            'privacy'   => $basis . '/legal.html#privacy',
            'link'      => self::link($projektId) ?? $basis,
        ]);

        /* Anhaenge: das Formular immer, der Beleg wenn es ihn gibt. */
        $anhaenge = [[
            'name'  => Widerruf::dateiname($sprache),
            'daten' => Widerruf::formularPdf($sprache, [
                'leistung'  => trim((string) ($p['paket'] ?? '') . ' — ' . $b['order_no']),
                'datum'     => Fmt::datum((string) $b['created_at']),
                'name'      => (string) $p['kunde'],
                'anschrift' => self::kundenanschrift((int) $p['customer_id']),
            ]),
        ]];
        foreach (Db::all('SELECT * FROM invoices WHERE order_id = ? ORDER BY id', [$bestellId]) as $r) {
            try {
                $anhaenge[] = ['name' => Rechnung::dateiname($r), 'daten' => Rechnung::pdf($r)];
            } catch (Throwable $e) { /* der Beleg liegt auch auf der Projektseite */ }
        }

        return Mail::senden('auftragsbestaetigung', (string) $p['kunde_email'], $betreff, $text, [
            'customer_id' => (int) $p['customer_id'],
            'project_id'  => (int) $p['id'],
            'order_id'    => $bestellId,
            'antwortAn'   => Mail::eigeneAdresse(),
            'anhaenge'    => $anhaenge,
        ]);
    }

    /** Die Anschrift des Kunden in einer Zeile, fuer das Formular. */
    private static function kundenanschrift(int $kundeId): string
    {
        $k = Db::one('SELECT street, zip, city, country FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { return ''; }
        $teile = array_filter([
            trim((string) $k['street']),
            trim(trim((string) $k['zip']) . ' ' . trim((string) $k['city'])),
            trim((string) $k['country']),
        ], static fn($z) => $z !== '');
        return implode(', ', $teile);
    }

    /**
     * Laesst die Spalte betreff weg, solange die Migration noch nicht durch
     * ist. Zwischen Deploy und naechstem Cronlauf liegen bis zu zehn Minuten —
     * in denen darf keine Nachricht an einer fehlenden Spalte scheitern.
     */
    private static function spalten(array $daten): array
    {
        static $gibtBetreff = null;
        if ($gibtBetreff === null) {
            try { Db::run('SELECT betreff FROM messages LIMIT 1'); $gibtBetreff = true; }
            catch (Throwable $e) { $gibtBetreff = false; }
        }
        if (!$gibtBetreff) { unset($daten['betreff']); }
        return $daten;
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
