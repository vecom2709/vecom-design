<?php
declare(strict_types=1);

require_once __DIR__ . '/Monitoring.php';
require_once __DIR__ . '/Onboarding.php';
require_once __DIR__ . '/Cockpit.php';
require_once __DIR__ . '/Sicherung.php';

/**
 * Der regelmaessige Lauf. Auf dem Webspace gibt es kein SSH und keinen
 * eigenen Dienst — der Anstoss kommt vom Cronjob im KAS, der einfach eine
 * Adresse aufruft.
 *
 * Damit diese Adresse nicht jeder aufrufen kann, traegt sie einen
 * Schluessel. Er entsteht beim ersten Blick in die Verwaltung von selbst
 * und steht dort zum Kopieren.
 */
final class Cron
{
    /** Kuerzester Abstand zwischen zwei Laeufen — schuetzt vor versehentlichem Dauerfeuer. */
    public const MINDESTABSTAND_SEKUNDEN = 60;

    public static function schluessel(): string
    {
        $vorhanden = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_schluessel'", [], '');
        if ($vorhanden !== '') { return $vorhanden; }
        $neu = bin2hex(random_bytes(16));
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('cron_schluessel', ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$neu]);
        return $neu;
    }

    /** Die vollstaendige Adresse, die im KAS eingetragen wird. */
    public static function adresse(): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        return $basis . '/cron.php?schluessel=' . self::schluessel();
    }

    public static function schluesselStimmt(string $eingabe): bool
    {
        $soll = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_schluessel'", [], '');
        // Ohne hinterlegten Schluessel laeuft gar nichts — sonst waere die
        // Adresse offen, solange die Verwaltung noch nie aufgerufen wurde.
        if ($soll === '' || $eingabe === '') { return false; }
        return hash_equals($soll, $eingabe);
    }

    public static function zuletzt(): ?string
    {
        $w = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_zuletzt'", [], '');
        return $w !== '' ? $w : null;
    }

    public static function letzteBilanz(): ?array
    {
        $w = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cron_bilanz'", [], '');
        $d = $w !== '' ? json_decode($w, true) : null;
        return is_array($d) ? $d : null;
    }

    private static function merken(string $schluessel, string $wert): void
    {
        Db::run("INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [$schluessel, $wert]);
    }

    /**
     * Ein Durchlauf. Jede Aufgabe steht fuer sich: Faellt eine aus, laufen
     * die anderen trotzdem — und der Fehler steht in der Bilanz.
     *
     * @param bool $erzwingen Mindestabstand ueberspringen (Knopf in der Verwaltung)
     */
    public static function laufen(bool $erzwingen = false): array
    {
        $zuletzt = self::zuletzt();
        if (!$erzwingen && $zuletzt !== null && (time() - strtotime($zuletzt)) < self::MINDESTABSTAND_SEKUNDEN) {
            return ['uebersprungen' => true, 'grund' => 'Der letzte Lauf ist keine Minute her.'];
        }

        $anfang = microtime(true);
        $bilanz = ['zeit' => date('c')];

        $aufgaben = [
            'websites'    => static fn() => Monitoring::alle(),
            'ssl'         => static fn() => Monitoring::sslWarnungen(),
            'erinnerungen'=> static fn() => Onboarding::erinnerungen(),
            /* A1 + A3: alte Website und P. IVA lesen -- was der Fragebogen beim
               ersten Oeffnen nicht geschafft hat. Wenige je Lauf: Jeder
               Abruf ist Zeit auf einem fremden Server. */
            'vorwissen'   => static function () {
                require_once __DIR__ . '/Vorwissen.php';
                return Vorwissen::nachholen();
            },
            /* Der E-Mail-Einstieg (24.09.2026): hoechstens drei Erinnerungen
               (D3), und nie geoeffnete Adressen verschwinden nach Ablauf (E4). */
            'zugaenge'    => static function () {
                require_once __DIR__ . '/Zugang.php';
                return ['erinnert' => Zugang::erinnern(), 'geloescht' => Zugang::aufraeumen()];
            },
            /* ZUERST NACHFRAGEN, DANN ABLAUFEN LASSEN
               Der Abgleich steht bewusst vor dem Ablaufenlassen: Wer in der letzten
               Minute vor Ablauf bezahlt hat, soll gebucht werden und nicht
               erst auf "ausstehend" zurueckfallen. */
            'zahlabgleich'=> static fn() => self::zahlungenAbgleichen(),
            'zahllinks'   => static fn() => self::abgelaufeneZahlungslinks(),
            /* Phase 2: angekuendigte Raten am Tag abbuchen. Nach dem Abgleich,
               damit eine gerade eingegangene Lastschrift zaehlt, bevor
               irgendetwas neu versucht wird. */
            'abbuchungen' => static function () {
                require_once __DIR__ . '/Abbuchung.php';
                return Abbuchung::faellige();
            },
            /* Die erste Zahlungserinnerung, drei Tage nach Faelligkeit, mit
               frischem Link. Nur diese eine Stufe laeuft von selbst — die
               beiden schaerferen stehen auf "Heute" und warten auf Uwe. */
            'mahnungen'   => static function () {
                require_once __DIR__ . '/Mahnung.php';
                return Mahnung::automatisch();
            },
            /* Die faelligen Betreuungsmonate als offene Raten anlegen — nur
               anlegen, nicht anfordern. Die Aufforderung geht von Hand raus,
               damit keine Forderung ins Mahnwesen laeuft, von der der Kunde
               nichts weiss. */
            'betreuung'   => static function () {
                require_once __DIR__ . '/Abo.php';
                return Abo::abrechnungenAnlegen();
            },
            /* Die fertigen Seiten pruefen — hoechstens einmal am Tag je
               Seite, und gemeldet wird nur, was schlechter geworden ist.
               Damit faellt das Pruefen von Hand weg: Man hoert nur noch,
               wenn etwas kaputtgegangen ist. */
            'abnahme'     => static function () {
                require_once __DIR__ . '/Abnahme.php';
                return Abnahme::alleFaelligen();
            },
            // Ein Angebot, dessen Frist verstrichen ist, soll sich nicht mehr
            // annehmen lassen. Die Seite prueft das beim Ansehen ohnehin mit —
            // hier wandert der Status nach, damit die Liste in der Verwaltung
            // die Wahrheit sagt.
            'angebote'    => static function () {
                require_once __DIR__ . '/Angebot.php';
                return Angebot::abgelaufeneSchliessen();
            },
            /* Rueckrufe, die zu lange liegen. Eine Liste, die man vergisst
               zu oeffnen, ist keine Liste -- also meldet sie sich selbst,
               einmal am Tag und als EINE Meldung, nicht als zehn. */
            'rueckrufe'   => static function () {
                require_once __DIR__ . '/Telefon.php';
                return Telefon::rueckrufeMahnen();
            },
            /* Einmal die Woche nachsehen, ob der Telefonassistent noch das
               tut, was er soll. Er driftet still -- jedes einzelne Gespraech
               sieht in Ordnung aus, und erst das Muster ueber eine Woche
               zeigt, dass er ein Werkzeug nicht mehr benutzt. Geaendert wird
               nichts von allein: Es kommt eine Meldung mit Vorschlaegen, und
               eintragen tut sie ein Mensch. */
            'telefon_rueckblick' => static function () {
                require_once __DIR__ . '/Telefon.php';
                return Telefon::rueckblickMelden();
            },
            /* Die Gespraeche von STRATO herueberholen. Sie liegen dort hinter
               einer Anmeldung, in einer Liste ueber fuenf Seiten, und mit
               einer Aufbewahrungsfrist, die nicht uns gehoert. Hier stehen
               sie durchsuchbar, mit der maschinellen Auswertung je Anruf und
               neben unserer eigenen Spur.

               Ist kein Zugang hinterlegt, kostet das nichts: Die Aufgabe
               sieht einmal nach und ist fertig. */

            // Damit die Verwaltung auf jeder Seite warnen kann, ohne bei
            // jedem Aufruf eine HTTP-Anfrage zu stellen.
            'cockpit'     => static fn() => self::cockpitPruefen(),
            // Zurufe aufs Handy, die noch in der Warteschlange liegen. Auf
            // FastCGI gehen sie schon beim Ausloesen raus; wo der Server das
            // nicht kann, ist hier die Stelle. Ausserdem der zweite Versuch,
            // wenn der Dienst gerade nicht erreichbar war.
            'zurufe'      => static function () {
                require_once __DIR__ . '/Zuruf.php';
                return Zuruf::abarbeiten();
            },
        ];
        /* Die Gespraeche von STRATO -- einmal in der Stunde, nicht bei jedem
           Lauf. Sie liegen dort hinter einer Anmeldung, in einer Liste ueber
           fuenf Seiten, und mit einer Aufbewahrungsfrist, die nicht uns
           gehoert. Hier stehen sie durchsuchbar, mit der maschinellen
           Auswertung je Anruf und neben unserer eigenen Spur.

           WARUM STUENDLICH UND NICHT ALLE ZEHN MINUTEN: Jeder Abgleich
           braucht einen Zugangs-Token, und je oefter der erneuert wird,
           desto eher faellt eine Erneuerung mit einer anderen zusammen --
           dann widerruft Supabase die ganze Sitzung. Neue Anrufe eine halbe
           Stunde spaeter zu sehen kostet nichts; den Zugang zu verlieren
           kostet einen Handgriff und jedes Mal Ratlosigkeit. */
        if (self::stundeNochNicht('cron_gespraeche')) {
            $aufgaben['gespraeche'] = static function () {
                require_once __DIR__ . '/Strato.php';
                return Strato::abgleichen();
            };
        }

        // Einmal am Tag genuegt: alte Pruefungen wegraeumen.
        if (self::heuteNochNicht('cron_aufraeumen')) {
            $aufgaben['aufgeraeumt'] = static fn() => Monitoring::aufraeumen();
            // Und gelesene Meldungen, die aelter sind als ein Monat. Sonst
            // waechst die Liste ewig — und wo hundert alte Zeilen stehen,
            // sieht niemand mehr die eine neue. Ungelesenes bleibt stehen.
            $aufgaben['meldungen'] = static fn() => Events::meldungenAufraeumen();
            // Abgelaufene Hosting-Zugangsdaten loeschen: Der verschluesselte
            // Blob existiert nur bis zum einmaligen Abruf oder bis zur Frist.
            $aufgaben['hosting'] = static function () {
                require_once __DIR__ . '/Hosting.php';
                return ['geloescht' => Hosting::aufraeumen()];
            };
        }
        // Einmal taeglich nachfragen, ob der E-Mail-Versand ueberhaupt noch
        // geht. Der Grund steht in der Projektgeschichte: Der Brevo-Schluessel
        // war monatelang ein abgeschnittener Platzhalter, jede Mail scheiterte
        // still, und niemand erfuhr davon — weil niemand fragte. Eine Frage
        // am Tag kostet nichts und haette es am ersten Tag gezeigt.
        if (self::heuteNochNicht('cron_versand')) {
            $aufgaben['versand'] = static function () {
                require_once __DIR__ . '/Versand.php';
                $e = Versand::pruefen();
                if (!$e['ok']) {
                    Events::melden('mail_fehler', 'Der E-Mail-Versand antwortet nicht mehr', 'schlecht',
                        $e['text'], '/einstellungen');
                }
                return ['ok' => $e['ok'], 'text' => mb_substr($e['text'], 0, 120)];
            };
        }
        // Ebenfalls einmal taeglich: der Auszug der Datenbank. Er steht
        // bewusst am Ende der Liste — er dauert am laengsten, und wenn er
        // scheitert, sollen die schnellen Aufgaben trotzdem gelaufen sein.
        // Einmal taeglich nachsehen, ob die Absenderdomain noch richtig im DNS
        // steht. SPF, DKIM und DMARC richtet man einmal ein und sieht sie nie
        // wieder an — genau deshalb faellt es niemandem auf, wenn einer
        // verschwindet. Nach aussen merkt man davon nichts: Die Mails gehen
        // weiter raus, sie landen nur zunehmend im Spam.
        // Gekuendigte Vertraege, deren Datum durch ist, auf "beendet" setzen.
        // Das ist die Stelle, an der spaeter auch der Zahlungsanbieter
        // abbestellt wird — deshalb steht sie jetzt schon da.
        $aufgaben['abos'] = static function () {
            require_once __DIR__ . '/Abo.php';
            return Abo::taeglich();
        };
        if (self::heuteNochNicht('cron_zustellbarkeit')) {
            $aufgaben['zustellbarkeit'] = static function () {
                require_once __DIR__ . '/Zustellbarkeit.php';
                return Zustellbarkeit::taeglich();
            };
        }
        if (self::heuteNochNicht('cron_sicherung')) {
            $aufgaben['sicherung'] = static fn() => Sicherung::laufen();
        }
        // Ganz zuletzt das Jahrespaket fuers Finanzamt. Es baut jeden Beleg
        // als PDF neu und wird mit jedem Jahr laenger — und was am laengsten
        // dauert, darf nicht vor der Sicherung stehen: Steigt der Server
        // mittendrin aus, gilt der Tag als erledigt, und die Sicherung waere
        // still ausgefallen.
        //
        // Warum ueberhaupt taeglich und nicht auf Knopfdruck: Wer die Belege
        // einmal im Jahr zusammensucht, sucht im Maerz nach einem Beleg vom
        // Maerz davor. Liegt das Paket jeden Morgen fertig da, ist die Frage
        // "hast du alles?" mit einem Klick beantwortet.
        if (self::heuteNochNicht('cron_steuerakte')) {
            $aufgaben['steuerakte'] = static function () {
                require_once __DIR__ . '/Steuerakte.php';
                return Steuerakte::taeglich();
            };
        }

        foreach ($aufgaben as $name => $tun) {
            try { $bilanz[$name] = $tun(); }
            catch (Throwable $e) { $bilanz[$name] = ['fehler' => mb_substr($e->getMessage(), 0, 200)]; }
        }

        $bilanz['dauer_ms'] = (int) round((microtime(true) - $anfang) * 1000);
        self::merken('cron_zuletzt', date('Y-m-d H:i:s'));
        self::merken('cron_bilanz', json_encode($bilanz, JSON_UNESCAPED_UNICODE));
        return $bilanz;
    }

    /** Merkt sich, ob /cockpit/ geschuetzt ist. Meldet nur den Wechsel. */
    private static function cockpitPruefen(): string
    {
        $jetzt = Cockpit::geschuetzt();
        if ($jetzt === null) { return 'nicht erreichbar'; }
        $wert = $jetzt ? 'ja' : 'nein';
        $vorher = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'cockpit_geschuetzt'", [], '');
        self::merken('cockpit_geschuetzt', $wert);

        if ($vorher === 'ja' && $wert === 'nein') {
            Events::melden('cockpit_offen', 'Das Cockpit ist nicht mehr geschützt', 'schlecht',
                'Vorher war es geschützt, jetzt antwortet es ohne Passwort. In den Einstellungen wieder einrichten.',
                '/einstellungen');
        }
        return $wert;
    }

    /**
     * Einmal in der Stunde, nicht bei jedem Lauf.
     *
     * Der Cron laeuft alle zehn Minuten. Fuer manches ist das richtig, fuer
     * anderes ist es sechsmal zu oft -- und beim Abgleich mit STRATO war es
     * teuer: Jeder Lauf brauchte einen Zugangs-Token, und je oefter der
     * erneuert wird, desto eher faellt eine Erneuerung mit einer anderen
     * zusammen. Genau daran ist der Zugang am 7. September gestorben.
     */
    private static function stundeNochNicht(string $schluessel): bool
    {
        $w = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], '');
        if ($w === date('Y-m-d H')) { return false; }
        self::merken($schluessel, date('Y-m-d H'));
        return true;
    }

    private static function heuteNochNicht(string $schluessel): bool
    {
        $w = (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], '');
        if ($w === date('Y-m-d')) { return false; }
        self::merken($schluessel, date('Y-m-d'));
        return true;
    }

    /**
     * Ein Zahlungslink von Stripe gilt nur eine begrenzte Zeit. Ist er
     * abgelaufen und nichts eingegangen, faellt die Rate zurueck auf
     * "ausstehend" — sonst stuende sie fuer immer auf "in Bearbeitung".
     */
    public static function abgelaufeneZahlungslinks(): int
    {
        return Db::run(
            "UPDATE payments SET status = 'ausstehend', link_url = NULL, link_bis = NULL
             WHERE status = 'in_bearbeitung' AND link_bis IS NOT NULL AND link_bis < NOW()"
        )->rowCount();
    }

    /* ------------------------------------------------------------------ */

    /** Erst nachfragen, wenn der Webhook seine Gelegenheit hatte. */
    public const ABGLEICH_SCHONFRIST_MINUTEN = 3;

    /** Danach ist die Bezahlseite bei Stripe ohnehin verschwunden. */
    public const ABGLEICH_LAENGSTENS_TAGE = 35;

    /** Mehr als das je Lauf waere Dauerfeuer auf eine fremde Schnittstelle. */
    public const ABGLEICH_HOECHSTENS = 25;

    /**
     * Fragt bei Stripe nach, ob offene Raten inzwischen bezahlt sind — und
     * bucht sie, wenn ja.
     *
     * WARUM ES DAS GIBT
     *
     * Am 13.09.2026 hat ein Kunde mit Karte bezahlt und nie eine
     * Bestaetigung bekommen. Das Geld lag bei Stripe, die Rate stand hier
     * auf offen, und dazwischen lag genau ein Aufruf, der nie ankam: der
     * Webhook. Im Livemodus war kein Endpunkt eingetragen.
     *
     * Ein Webhook ist ein Anruf, den der ANDERE macht. Er kann ausfallen,
     * falsch unterschrieben sein oder ins Leere gehen — und in allen drei
     * Faellen sieht es hier gleich aus: Stille. Eine Stille, die wie "nicht
     * bezahlt" aussieht, ist der teuerste Zustand, den diese Anwendung
     * kennt: kein Beleg, keine Auftragsbestaetigung, kein Fragebogen, kein
     * Projekt — und ein Kunde, der wartet.
     *
     * Also der Rueckweg: Wir fragen selbst. Der Webhook bleibt der schnelle
     * Weg, dieser hier ist der sichere.
     *
     * WARUM DAS NICHTS DOPPELT BUCHT
     *
     * Gebucht wird durch dieselbe Tuer wie beim Webhook,
     * Events::zahlungBestaetigen(). Die steigt bei status = 'bezahlt' sofort
     * wieder aus. Kommen Webhook und Abgleich gleichzeitig, gewinnt einer,
     * und der andere tut nichts — ohne dass hier etwas zu wissen waere.
     *
     * WARUM ES SICH MELDET
     *
     * Wenn dieser Weg etwas bucht, hat der schnelle Weg versagt. Das ist
     * nicht die Nebensache, sondern der eigentliche Befund: Der Webhook
     * gehoert repariert. Sonst faengt der Abgleich jeden Kunden auf, und
     * niemand erfaehrt, warum jede Bestaetigung Minuten zu spaet kommt.
     *
     * Der Anbieter laesst sich uebergeben. Das ist keine Bequemlichkeit,
     * sondern die einzige Moeglichkeit, diesen Weg in der Kettenpruefung
     * wirklich durchzuspielen: Ein Abgleich, der nur im Echtbetrieb gegen
     * Stripe laufen kann, wird nie geprueft — und genau ungeprueft war der
     * Webhook, als er ausfiel.
     *
     * @return int wie viele Raten dabei gebucht wurden
     */
    public static function zahlungenAbgleichen(?object $anbieter = null): int
    {
        require_once __DIR__ . '/Zahlung/Anbieter.php';
        require_once __DIR__ . '/Zahlung/Stripe.php';
        require_once __DIR__ . '/Events.php';
        require_once __DIR__ . '/Fmt.php';

        $stripe = $anbieter ?? new StripeAnbieter();
        if (!$stripe->bereit()) { return 0; }

        /* Auch 'ausstehend' und 'fehlgeschlagen' kommen mit. Ein abgelaufener
           Link setzt die Rate auf 'ausstehend' zurueck (siehe oben) — wer in
           genau dieser Minute bezahlt hat, faende sonst nie statt. Und
           'fehlgeschlagen' heisst nur, dass der letzte Versuch danebenging,
           nicht dass der naechste es auch tat. */
        $offen = Db::all(
            "SELECT id, provider_sitzung, amount_cents, currency, bezeichnung
               FROM payments
              WHERE status IN ('in_bearbeitung', 'ausstehend', 'fehlgeschlagen')
                AND provider_sitzung IS NOT NULL AND provider_sitzung <> ''
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND updated_at > DATE_SUB(NOW(), INTERVAL ? DAY)
              ORDER BY updated_at DESC
              LIMIT " . self::ABGLEICH_HOECHSTENS,
            [self::ABGLEICH_SCHONFRIST_MINUTEN, self::ABGLEICH_LAENGSTENS_TAGE]
        );
        if (!$offen) { return 0; }

        $gebucht = 0;
        $fehler  = '';

        foreach ($offen as $z) {
            try {
                $s = $stripe->sitzungLesen((string) $z['provider_sitzung']);

                if ($s['bezahlt']) {
                    // Gebucht wird nur, wenn der Betrag passt -- siehe Events::zahlungVonStripe.
                    $wie = Events::zahlungVonStripe((int) $z['id'], (string) $s['referenz'],
                        (int) $s['betrag'], (string) $s['waehrung']);
                    if ($wie === 'abweichung') {
                        /* Gemeldet ist es. Nicht alle zehn Minuten wieder
                           fragen: Die Seite ist bezahlt, an ihr aendert sich
                           nichts mehr -- den Rest entscheidet ein Mensch. */
                        Db::update('payments', (int) $z['id'], ['provider_sitzung' => null]);
                        continue;
                    }
                    if ($wie !== 'gebucht') { continue; }
                    $gebucht++;
                    Events::protokoll('zahlung_abgleich',
                        'Beim Abgleich mit Stripe als bezahlt vorgefunden: '
                        . ($z['bezeichnung'] ?: 'Rate') . ' · '
                        . Fmt::geld((int) $z['amount_cents'], (string) $z['currency']));
                    continue;
                }

                /* Verfallene Bezahlseite: Da kommt nichts mehr. Die Nummer
                   loeschen, sonst fragt der Abgleich sie fuenf Wochen lang
                   vergeblich ab. */
                if ($s['abgelaufen']) {
                    Db::update('payments', (int) $z['id'], ['provider_sitzung' => null]);
                    /* Eine Lastschrift, die zurueckging: Das ist ein Scheitern,
                       kein Verfallen -- der Kunde bekommt den Zahlungslink. */
                    if (str_starts_with((string) $z['provider_sitzung'], 'pi_')) {
                        require_once __DIR__ . '/Abbuchung.php';
                        Abbuchung::gescheitert((int) $z['id'], (string) $s['status']);
                    }
                }
            } catch (Throwable $e) {
                // Eine Rate, die klemmt, darf die anderen nicht aufhalten.
                $fehler = mb_substr($e->getMessage(), 0, 200);
            }
        }

        if ($gebucht > 0) {
            Events::melden('zahlung_abgleich',
                $gebucht === 1 ? 'Eine Zahlung kam erst über den Abgleich an'
                               : $gebucht . ' Zahlungen kamen erst über den Abgleich an',
                'warnung',
                'Stripe hatte das Geld, hier stand die Rate noch offen — der Webhook hat also '
                . 'nicht gemeldet. Gebucht ist alles; nachsehen, ob der Webhook im richtigen '
                . 'Modus eingetragen ist und das Signaturgeheimnis stimmt.',
                '/integrationen');
        }

        if ($fehler !== '') {
            Db::run("UPDATE integrations SET last_error = ? WHERE ikey = 'stripe'",
                ['Abgleich: ' . $fehler]);
        }

        return $gebucht;
    }
}
