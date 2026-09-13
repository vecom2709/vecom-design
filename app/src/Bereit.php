<?php
declare(strict_types=1);

/**
 * Damit alles läuft — was eingerichtet sein muss, und was davon steht.
 *
 * WARUM ES DIESE SEITE GIBT
 *
 * Am 13.09.2026 hat ein Kunde mit Karte bezahlt und nie eine Bestaetigung
 * bekommen. Der Grund war eine einzige fehlende Angabe: Im Livemodus war bei
 * Stripe kein Webhook eingetragen. Nichts war kaputt, nichts hat gemeldet —
 * es fehlte nur etwas, das man einmal einrichtet und danach vergisst.
 *
 * Die Hinweise darauf gab es. Sie standen verstreut: ein gelber Balken oben
 * auf jeder Seite, wenn das Cockpit offen ist; ein Kaestchen auf der
 * Werkstatt, wenn das Claude-Projekt fehlt; eine Meldung, wenn eine Mail
 * scheiterte. Jeder fuer sich richtig, und zusammen nirgends. Niemand kann
 * sagen "alles steht", wenn er dafuer sieben Seiten aufmachen muss.
 *
 * Hier steht alles untereinander, je Zeile gruen oder rot, und bei rot der
 * Weg dorthin. Eine Seite, die man einmal im Monat aufmacht und nach
 * zwanzig Sekunden wieder zu.
 *
 * WAS HIER NICHT STEHT
 *
 * Vermutungen. Jede Zeile misst etwas, das wirklich in den Daten steht —
 * wann der Cronjob zuletzt lief, ob ein Webhook je ankam, ob eine Mail
 * durchging. Ein gruener Haken, der nur bedeutet "ist konfiguriert", waere
 * schlimmer als keiner: Er beruhigt, ohne etwas zu wissen.
 */
final class Bereit
{
    /* Ein Punkt ist entweder in Ordnung, eine Warnung oder ein Fehler.
       Drei Stufen, nicht zwei: „Der Cronjob lief zuletzt vor zwei Stunden"
       ist nicht dasselbe wie „es gibt keinen", und „Testmodus" ist kein
       Fehler, solange man noch nicht verkauft. */
    public const GUT     = 'gut';
    public const WARNUNG = 'warnung';
    public const FEHLER  = 'fehler';

    /**
     * Alle Punkte, in der Reihenfolge, in der sie wehtun.
     *
     * @return list<array{schluessel:string,was:string,stand:string,text:string,
     *                    warum:string,ziel:?string,wohin:?string}>
     */
    public static function punkte(): array
    {
        return [
            self::cronjob(),
            self::mailversand(),
            self::bezahlung(),
            self::webhook(),
            self::cockpit(),
            self::firma(),
            self::ablage(),
            self::datenbank(),
            self::beispieldaten(),
            self::werkstatt(),
        ];
    }

    /** Wie viele Punkte in welchem Zustand sind. */
    public static function bilanz(?array $punkte = null): array
    {
        $punkte ??= self::punkte();
        $z = [self::GUT => 0, self::WARNUNG => 0, self::FEHLER => 0];
        foreach ($punkte as $p) { $z[$p['stand']] = ($z[$p['stand']] ?? 0) + 1; }
        return $z + ['gesamt' => count($punkte)];
    }

    /* ====================================================================== */

    /** Ein Wert aus den Einstellungen, ohne dass eine fehlende Tabelle stoert. */
    private static function wert(string $schluessel, string $ersatz = ''): string
    {
        try {
            return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?',
                [$schluessel], $ersatz);
        } catch (Throwable $e) { return $ersatz; }
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /* ---------------------------------------------------------------------
       1. DER CRONJOB

       Steht zuerst, weil ohne ihn nachts gar nichts laeuft: keine
       Betreuungsmonate, keine Zahlungserinnerung, kein Monitoring, keine
       Abnahme — und seit dem 13.09.2026 auch kein Zahlungsabgleich, der
       genau den Fall auffaengt, der diese Seite ausgeloest hat.
       --------------------------------------------------------------------- */
    private static function cronjob(): array
    {
        require_once __DIR__ . '/Cron.php';
        $lauf = self::still(static fn() => Cron::zuletzt(), null);
        $alter = $lauf !== null ? time() - strtotime((string) $lauf) : null;

        if ($lauf === null) {
            return self::punkt('cron', 'Der nächtliche Lauf', self::FEHLER,
                'Läuft nicht — er ist noch nie gelaufen.',
                'Ohne ihn passiert nachts nichts von selbst: keine Betreuungsmonate, keine '
                . 'Zahlungserinnerung, kein Monitoring, keine Abnahme, kein Zahlungsabgleich.',
                'monitoring', 'Adresse und Anleitung');
        }
        if ($alter > 3 * 3600) {
            return self::punkt('cron', 'Der nächtliche Lauf', self::FEHLER,
                'Zuletzt ' . Fmt::seit((string) $lauf) . ' — das ist zu lange her.',
                'Er sollte mindestens stündlich laufen. Steht er, merkt man es erst daran, '
                . 'dass wochenlang keine Erinnerung rausging.',
                'monitoring', 'nachsehen');
        }
        if ($alter > 3600) {
            return self::punkt('cron', 'Der nächtliche Lauf', self::WARNUNG,
                'Zuletzt ' . Fmt::seit((string) $lauf) . '.',
                'Etwas über eine Stunde her. Einmal ist das nichts; bleibt es so, stimmt '
                . 'der Takt im KAS nicht.',
                'monitoring', 'nachsehen');
        }
        return self::punkt('cron', 'Der nächtliche Lauf', self::GUT,
            'Läuft — zuletzt ' . Fmt::seit((string) $lauf) . '.');
    }

    /* ---------------------------------------------------------------------
       2. DER MAILVERSAND

       Geprueft wird nicht, ob ein Schluessel dasteht, sondern ob die letzte
       Mail durchkam. Ein hinterlegter Schluessel, der nicht mehr gilt, sieht
       von aussen genauso aus wie ein guter — und genau so ist es hier schon
       einmal gelaufen: Der Schluessel war monatelang ein abgeschnittener
       Platzhalter, saemtliche Post verschwand, und niemand erfuhr davon.
       --------------------------------------------------------------------- */
    private static function mailversand(): array
    {
        require_once __DIR__ . '/Versand.php';
        $hat = (bool) self::still(static fn() => Versand::eigenerSchluessel(), false);
        if (!$hat) {
            return self::punkt('mail', 'E-Mail-Versand', self::FEHLER,
                'Kein Schlüssel hinterlegt.',
                'Ohne ihn geht keine einzige E-Mail raus — kein Fragebogen, kein Zahlungslink, '
                . 'kein Beleg. Der Kunde merkt es als Erster.',
                'einstellungen?b=email', 'Schlüssel eintragen');
        }

        $zeile = self::still(static fn() => Db::one(
            "SELECT status, last_error, last_sync_at FROM integrations WHERE ikey = 'brevo'"), null);
        $stand = (string) ($zeile['status'] ?? '');
        $wann  = (string) ($zeile['last_sync_at'] ?? '');

        /* Der letzte Fehlschlag zaehlt nur, solange er der letzte VERSUCH war.
           Ist danach etwas durchgegangen, ist der Fehler Geschichte. */
        if ($stand === 'fehler') {
            return self::punkt('mail', 'E-Mail-Versand', self::FEHLER,
                'Der letzte Versand ist gescheitert'
                . ($wann !== '' ? ' (' . Fmt::seit($wann) . ')' : '') . '.',
                trim((string) ($zeile['last_error'] ?? '')) !== ''
                    ? (string) $zeile['last_error']
                    : 'Solange das so bleibt, bekommt kein Kunde Post.',
                'einstellungen?b=email', 'prüfen');
        }
        if ($stand === '' || $wann === '') {
            return self::punkt('mail', 'E-Mail-Versand', self::WARNUNG,
                'Ein Schlüssel liegt vor, aber es ging noch nie etwas raus.',
                'Ob er wirklich gilt, weiß man erst nach der ersten echten Mail. '
                . 'In den Einstellungen gibt es dafür eine Probe.',
                'einstellungen?b=email', 'Probe schicken');
        }
        return self::punkt('mail', 'E-Mail-Versand', self::GUT,
            'Die letzte Mail ging durch (' . Fmt::seit($wann) . ').');
    }

    /* ---------------------------------------------------------------------
       3. DIE BEZAHLUNG
       --------------------------------------------------------------------- */
    private static function bezahlung(): array
    {
        require_once __DIR__ . '/Zahlung/Anbieter.php';
        require_once __DIR__ . '/Zahlung/Stripe.php';
        $s = self::still(static fn() => new StripeAnbieter(), null);
        if ($s === null || !$s->bereit()) {
            return self::punkt('stripe', 'Bezahlung über Stripe', self::FEHLER,
                'Kein Schlüssel hinterlegt.',
                'Ohne ihn lässt sich kein Zahlungslink erzeugen — jede Rate müsste von Hand '
                . 'gebucht werden.',
                'einstellungen?b=bezahlung', 'Schlüssel eintragen');
        }
        if ($s->modus() !== 'live') {
            return self::punkt('stripe', 'Bezahlung über Stripe', self::WARNUNG,
                'Testmodus.',
                'Zahlungen sind nicht echt. Zum Verkaufen muss der Livemodus an sein — '
                . 'und dazu gehört ein eigener Webhook (Zeile darunter).',
                'einstellungen?b=bezahlung', 'umstellen');
        }
        return self::punkt('stripe', 'Bezahlung über Stripe', self::GUT, 'Livemodus.');
    }

    /* ---------------------------------------------------------------------
       4. DER WEBHOOK — DIE ZEILE, DERENTWEGEN ES DIESE SEITE GIBT

       Zwei Fragen, nicht eine. Ob ein Geheimnis hinterlegt ist, sagt nur,
       dass jemand etwas eingetragen hat. Ob je ein Webhook ANKAM, sagt, ob
       Stripe uns wirklich findet. Am 13.09.2026 war das Erste in Ordnung
       und das Zweite nicht, und der Unterschied hat einen Kunden gekostet.
       --------------------------------------------------------------------- */
    private static function webhook(): array
    {
        require_once __DIR__ . '/Zahlung/Stripe.php';
        $s = self::still(static fn() => new StripeAnbieter(), null);
        $hat = $s !== null && $s->webhookBereit();
        $live = $s !== null && $s->modus() === 'live';

        $letzter = (string) self::still(static fn() => (string) Db::wert(
            "SELECT created_at FROM webhook_events
              WHERE provider = 'stripe' AND signature_ok = 1
              ORDER BY id DESC LIMIT 1", [], ''), '');

        if (!$hat) {
            return self::punkt('webhook', 'Stripe meldet Zahlungen zurück', self::FEHLER,
                'Kein Signaturgeheimnis hinterlegt.',
                'Ohne den Webhook erfährt die Verwaltung nichts von einer Zahlung. Genau so '
                . 'ist es am 13.09.2026 passiert: Das Geld war bei Stripe, hier stand die Rate '
                . 'auf offen, und der Kunde bekam keine Bestätigung. Der Abgleich fängt das '
                . 'inzwischen ab — aber erst Minuten später und nur, solange er läuft.',
                'einstellungen?b=bezahlung', 'einrichten');
        }
        if ($letzter === '') {
            return self::punkt('webhook', 'Stripe meldet Zahlungen zurück',
                $live ? self::FEHLER : self::WARNUNG,
                'Ein Geheimnis liegt vor, aber es kam noch nie ein Webhook an.',
                'Hinterlegt heißt nicht eingerichtet: Der Endpunkt muss auch bei Stripe stehen, '
                . 'im richtigen Modus, auf https://vecom-design.it/stripe-webhook.php. '
                . 'Solange keiner ankommt, weiß niemand, ob er funktioniert.',
                'einstellungen?b=bezahlung', 'nachsehen');
        }
        $alt = time() - strtotime($letzter) > 90 * 86400;
        return self::punkt('webhook', 'Stripe meldet Zahlungen zurück',
            $alt ? self::WARNUNG : self::GUT,
            'Zuletzt angekommen ' . Fmt::seit($letzter) . '.',
            $alt ? 'Über drei Monate her. Wenn seither jemand bezahlt hat, kam die Meldung '
                 . 'nicht an.' : '');
    }

    /* ---------------------------------------------------------------------
       5. DAS COCKPIT
       --------------------------------------------------------------------- */
    private static function cockpit(): array
    {
        $stand = self::wert('cockpit_geschuetzt');
        if ($stand === 'nein') {
            return self::punkt('cockpit', 'Das Cockpit ist geschützt', self::FEHLER,
                'Es steht offen.',
                'Jeder, der die Adresse kennt, sieht deine Zahlen.',
                'einstellungen?b=zugaenge', 'schützen');
        }
        if ($stand === '') {
            return self::punkt('cockpit', 'Das Cockpit ist geschützt', self::WARNUNG,
                'Noch nie geprüft.',
                'Der nächtliche Lauf sieht nach; steht hier nichts, ist er noch nicht gelaufen.',
                'einstellungen?b=zugaenge', 'nachsehen');
        }
        return self::punkt('cockpit', 'Das Cockpit ist geschützt', self::GUT, 'Passwort steht.');
    }

    /* ---------------------------------------------------------------------
       6. DIE FIRMENDATEN

       Sie stehen auf jedem Beleg. Fehlt die Partita IVA, stellt die
       Verwaltung Zahlungsbelege aus statt Rechnungen — das ist richtig so,
       aber es soll eine Entscheidung sein und kein Versehen.
       --------------------------------------------------------------------- */
    private static function firma(): array
    {
        require_once __DIR__ . '/Firma.php';
        $f = (array) self::still(static fn() => Firma::alle(), []);
        $fehlt = [];
        foreach (['firma_name' => 'Firmenname', 'firma_strasse' => 'Anschrift',
                  'firma_ort' => 'Ort'] as $k => $wort) {
            if (trim((string) ($f[$k] ?? '')) === '') { $fehlt[] = $wort; }
        }
        if ($fehlt) {
            return self::punkt('firma', 'Firmendaten auf den Belegen', self::FEHLER,
                'Es fehlt: ' . implode(', ', $fehlt) . '.',
                'Das steht oben auf jedem Beleg, den ein Kunde bekommt.',
                'einstellungen?b=firma', 'eintragen');
        }
        if (trim((string) ($f['firma_piva'] ?? '')) === '') {
            return self::punkt('firma', 'Firmendaten auf den Belegen', self::WARNUNG,
                'Ohne Partita IVA.',
                'Die Verwaltung stellt deshalb Zahlungsbelege aus, keine Rechnungen im '
                . 'steuerlichen Sinn. Das ist richtig, solange es so gewollt ist.',
                'einstellungen?b=firma', 'ansehen');
        }
        return self::punkt('firma', 'Firmendaten auf den Belegen', self::GUT, 'Vollständig.');
    }

    /* ---------------------------------------------------------------------
       7. DER ABLAGEORDNER
       --------------------------------------------------------------------- */
    private static function ablage(): array
    {
        require_once __DIR__ . '/Ablage.php';
        $ok = (bool) self::still(static fn() => Ablage::bereit(), false);
        return $ok
            ? self::punkt('ablage', 'Dateien lassen sich ablegen', self::GUT, 'Der Ordner ist beschreibbar.')
            : self::punkt('ablage', 'Dateien lassen sich ablegen', self::FEHLER,
                'Der Ordner app/uploads/ lässt sich nicht beschreiben.',
                'Kein Kunde kann etwas hochladen, und kein Website-Paket lässt sich hinterlegen.',
                'einstellungen?b=daten', 'Rechte prüfen');
    }

    /* ---------------------------------------------------------------------
       8. DIE DATENBANK
       --------------------------------------------------------------------- */
    private static function datenbank(): array
    {
        require_once __DIR__ . '/Einrichtung.php';
        $offen = (array) self::still(static fn() => Einrichtung::offene(), []);
        return $offen === []
            ? self::punkt('datenbank', 'Die Datenbank ist auf Stand', self::GUT, 'Keine offene Aktualisierung.')
            : self::punkt('datenbank', 'Die Datenbank ist auf Stand', self::FEHLER,
                count($offen) . ' Aktualisierung' . (count($offen) === 1 ? '' : 'en') . ' offen.',
                'Solange sie nicht durchgelaufen sind, fehlen Spalten, die neue Seiten brauchen.',
                'einstellungen?b=daten', 'nachholen');
    }

    /* ---------------------------------------------------------------------
       9. DIE BEISPIELDATEN
       --------------------------------------------------------------------- */
    private static function beispieldaten(): array
    {
        $n = (int) self::still(static fn() => (int) Db::wert(
            'SELECT COUNT(*) FROM customers WHERE demo = 1', [], 0), 0);
        return $n === 0
            ? self::punkt('beispiel', 'Keine Beispieldaten mehr', self::GUT, 'Alles echt.')
            : self::punkt('beispiel', 'Keine Beispieldaten mehr', self::WARNUNG,
                $n . ' erfundene Kunden liegen noch da.',
                'Solange sie da sind, sind alle Zahlen zu hoch — und irgendwann hält man '
                . 'erfundene Umsätze für die eigenen.',
                'einstellungen?b=daten', 'löschen');
    }

    /* ---------------------------------------------------------------------
       10. DIE WERKSTATT
       --------------------------------------------------------------------- */
    private static function werkstatt(): array
    {
        require_once __DIR__ . '/Werkstatt.php';
        $hat = trim((string) self::still(static fn() => Werkstatt::schluessel(), '')) !== '';
        return $hat
            ? self::punkt('werkstatt', 'Claude Code darf herein', self::GUT, 'Ein Schlüssel liegt vor.')
            : self::punkt('werkstatt', 'Claude Code darf herein', self::WARNUNG,
                'Kein Werkstatt-Schlüssel.',
                'Ohne ihn kommt Claude Code nicht an Briefing und Material — Kundenseiten '
                . 'müssten von Hand zusammengesucht werden.',
                'standard', 'Schlüssel erzeugen');
    }

    /* ====================================================================== */

    /** @return array<string,mixed> */
    private static function punkt(string $schluessel, string $was, string $stand, string $text,
                                  string $warum = '', ?string $ziel = null, ?string $wohin = null): array
    {
        return ['schluessel' => $schluessel, 'was' => $was, 'stand' => $stand,
                'text' => $text, 'warum' => $warum, 'ziel' => $ziel, 'wohin' => $wohin];
    }
}
