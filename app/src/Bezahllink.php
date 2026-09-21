<?php
declare(strict_types=1);

/**
 * Der Bezahllink, der nicht stirbt.
 *
 * DER BEFUND VOM 21.09.2026
 *
 * Eine Stripe-Bezahlseite lebt hoechstens 24 Stunden. Das ist keine
 * Einstellung, die sich hochdrehen liesse: expires_at darf zwischen
 * 30 Minuten und 24 Stunden liegen, ohne Angabe sind es 24 Stunden.
 *
 * Diese Anwendung hat die Adresse der Bezahlseite trotzdem wie einen
 * dauerhaften Link behandelt. Sie stand in jeder Zahlungsmail -- Anzahlung,
 * Restzahlung, Monatsrate fuer Betreuung und Hosting, Mahnung --, auf den
 * Knoepfen der Kundenseite, und die Verwaltung zeigte daneben "gueltig bis"
 * mit einem Datum vierzehn Tage spaeter. Die Monatsmail nannte dazu eine
 * Zahlungsfrist von sieben Tagen. Wer am zweiten Tag bezahlen wollte, landete
 * bei Stripe auf "Diese Seite ist abgelaufen" -- mit dem Link, den wir ihm
 * geschickt hatten.
 *
 * DIE LOESUNG
 *
 * Nach draussen geht nur noch eine Adresse auf dieser Domain:
 *
 *     /bezahlen.php?t=<Kundenschluessel>&z=<Rate>
 *
 * Sie haelt, solange die Rate offen ist. Erst beim Klick fragt sie Stripe:
 *   - Ist die letzte Bezahlseite schon bezahlt? Dann wird jetzt gebucht --
 *     auch ohne Webhook, auch ohne auf den Abgleich zu warten.
 *   - Laeuft sie noch? Dann geht es genau dorthin, es entsteht keine zweite.
 *   - Sonst entsteht eine frische, mit dem Betrag, der JETZT gilt.
 *
 * Der Schluessel ist derselbe wie der der Kundenseite: 48 Zeichen Zufall,
 * ruecknehmbar in der Kundenakte. Eine Rate, die einem anderen Kunden
 * gehoert, oeffnet er nicht.
 */
final class Bezahllink
{
    /** Status, in denen eine Rate noch bezahlt werden kann. */
    public const OFFEN = ['ausstehend', 'in_bearbeitung', 'fehlgeschlagen'];

    /** Die Adresse, die in jede Mail und auf jeden Knopf gehoert. */
    public static function fuer(int $zahlungId): string
    {
        require_once __DIR__ . '/Kundenzugang.php';
        $kundeId = self::kundeZu($zahlungId);
        if ($kundeId <= 0) {
            throw new RuntimeException('Zu dieser Rate gibt es keinen Kunden.');
        }
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/bezahlen.php?t=' . rawurlencode(Kundenzugang::token($kundeId)) . '&z=' . $zahlungId;
    }

    /** Wem die Rate gehoert -- ueber die Bestellung oder den Vertrag. */
    public static function kundeZu(int $zahlungId): int
    {
        return (int) Db::wert(
            'SELECT COALESCE(o.customer_id, a.customer_id)
               FROM payments z
               LEFT JOIN orders o ON o.id = z.order_id
               LEFT JOIN abos   a ON a.id = z.abo_id
              WHERE z.id = ?', [$zahlungId], 0);
    }

    /**
     * Was beim Klick passiert. Gibt das Ziel der Weiterleitung zurueck und
     * einen Grund, damit die Kettenpruefung sehen kann, welcher Weg es war.
     *
     * Der Anbieter laesst sich uebergeben -- aus demselben Grund wie beim
     * Abgleich: Ein Weg, der nur gegen das echte Stripe laufen kann, wird nie
     * geprueft.
     *
     * @return array{ziel:string, grund:string}
     *   grund: offen | neu | eben_bezahlt | abweichung | bezahlt | zu | aus | fremd
     */
    public static function oeffnen(string $token, int $zahlungId, ?object $anbieter = null): array
    {
        require_once __DIR__ . '/Kundenzugang.php';
        require_once __DIR__ . '/Zahlung/Anbieter.php';
        require_once __DIR__ . '/Zahlung/Stripe.php';

        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $kunde = Kundenzugang::ausToken($token);
        if (!$kunde) {
            return ['ziel' => $basis . '/', 'grund' => 'fremd'];
        }
        $seite = Kundenzugang::link($token);

        // Eine fremde Rate sieht aus wie eine, die es nicht gibt.
        if ($zahlungId <= 0 || self::kundeZu($zahlungId) !== (int) $kunde['id']) {
            return ['ziel' => $seite, 'grund' => 'fremd'];
        }

        $z = Db::one('SELECT * FROM payments WHERE id = ?', [$zahlungId]);
        if (!$z) { return ['ziel' => $seite, 'grund' => 'fremd']; }
        if ((string) $z['status'] === 'bezahlt') { return ['ziel' => $seite, 'grund' => 'bezahlt']; }
        if (!in_array((string) $z['status'], self::OFFEN, true)) {
            return ['ziel' => $seite, 'grund' => 'zu'];
        }

        $b = null;
        if ($z['order_id'] !== null) {
            $b = Db::one('SELECT * FROM orders WHERE id = ?', [(int) $z['order_id']]);
            if (!$b || (string) $b['status'] === 'storniert') {
                return ['ziel' => $seite, 'grund' => 'zu'];
            }
        }

        $stripe = $anbieter ?? new StripeAnbieter();
        if (!$stripe->bereit()) {
            // Ohne Stripe steht auf der Kundenseite, wie ueberwiesen wird.
            return ['ziel' => $seite, 'grund' => 'aus'];
        }

        /* 1. Die zuletzt erzeugte Bezahlseite fragen.

           Das ist nicht nur Sparsamkeit. Hat der Kunde gerade dort bezahlt und
           der Webhook kam nicht, bucht dieser Klick -- und er wird nicht auf
           eine zweite Bezahlseite geschickt, auf der er noch einmal zahlen
           koennte. */
        $sitzung = trim((string) ($z['provider_sitzung'] ?? ''));
        if ($sitzung !== '') {
            try {
                $s = $stripe->sitzungLesen($sitzung);
                if ($s['bezahlt']) {
                    $wie = Events::zahlungVonStripe($zahlungId, (string) $s['referenz'],
                        (int) $s['betrag'], (string) $s['waehrung']);
                    return ['ziel' => $seite, 'grund' => $wie === 'abweichung' ? 'abweichung' : 'eben_bezahlt'];
                }
                if (($s['status'] ?? '') === 'open' && trim((string) ($z['link_url'] ?? '')) !== '') {
                    return ['ziel' => (string) $z['link_url'], 'grund' => 'offen'];
                }
            } catch (Throwable $e) {
                /* Stripe kennt die Seite nicht mehr oder ist kurz weg: Dann
                   eben eine neue. Schlimmstenfalls entsteht eine Seite zu
                   viel -- das ist besser als ein Kunde, der nicht zahlen kann. */
            }
        }

        /* 2. Eine frische Bezahlseite, mit dem Betrag, der jetzt gilt.

           Monatsraten haben keine Bestellung. Die Bezahlseite braucht aber
           Nummer und Namen fuer ihre Zeile -- die kommen dann aus dem
           Vertrag, und nach dem Bezahlen geht es auf die Kundenseite, wo der
           Monat als bezahlt steht. */
        if ($b === null) {
            $paket = (string) Db::wert('SELECT paket_name FROM abos WHERE id = ?', [(int) ($z['abo_id'] ?? 0)], '');
            $b = ['id' => '', 'order_no' => (string) ($z['bezeichnung'] ?: 'Monatsrate'), 'package_name' => $paket];
            $erfolg = $seite;
        } else {
            $erfolg = null;
        }

        $url = $stripe->bezahlseite($z, $b, $kunde, $erfolg);
        Db::update('payments', $zahlungId, [
            'provider' => 'stripe', 'status' => 'in_bearbeitung',
            'provider_sitzung' => $stripe->letzteSitzung(),
            'link_url' => $url,
            /* Die Aufforderung laeuft weiter, solange sie lief; neu gesetzt
               wird nur, wenn noch keine Frist stand. Sonst verlaengerte
               jeder Klick die Zahlungsaufforderung um vierzehn Tage. */
            'link_bis' => (!empty($z['link_bis']) && strtotime((string) $z['link_bis']) > time())
                ? $z['link_bis'] : date('Y-m-d H:i:s', strtotime('+' . Events::LINK_GILT_TAGE . ' days')),
        ]);
        return ['ziel' => $url, 'grund' => 'neu'];
    }
}
