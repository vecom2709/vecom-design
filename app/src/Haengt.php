<?php
declare(strict_types=1);

/**
 * Was gerade hängt — eine Liste statt dreier.
 *
 * WARUM ES DIESE LISTE GIBT
 *
 * Auf „Heute" standen drei Kästen, die alle dasselbe meinten und es nur
 * verschieden nannten:
 *
 *   „Das läuft nicht"      — was gemeldet wurde: eine Mail ging nicht raus,
 *                            ein Webhook kam nicht an.
 *   „Demnächst fällig"     — was eine Frist hat: ein Angebot läuft ab, eine
 *                            Restzahlung steht an.
 *   und nichts dazwischen  — die Fälle, in denen einfach nichts passiert.
 *
 * Der dritte ist der gefährlichste und hatte keinen Kasten. Stille löst
 * nichts aus: Ein Vorgang, bei dem seit drei Wochen niemand etwas getan hat,
 * erzeugt keine Meldung und hat keine Frist. Er steht in der Arbeitsliste
 * ganz normal zwischen den anderen — als wäre er von gestern.
 *
 * Hier ist Stille ein Eintrag. Jede Zeile sagt, WAS hängt, SEIT WANN, und
 * hat einen Knopf.
 *
 * WAS NICHT HIERHER GEHOERT
 *
 * Alles, was ganz normal läuft. Ein Kunde, der seit zwei Tagen nicht
 * geantwortet hat, hängt nicht — er antwortet nur noch nicht. Eine Liste,
 * in der jeder Vorgang steht, ist keine Liste, sondern die Arbeitsliste
 * noch einmal.
 */
final class Haengt
{
    /* Ab wann Stille auffällt.
       Getrennt, weil es zwei verschiedene Dinge sind: Wenn ICH seit einer
       Woche nichts getan habe, lasse ich jemanden warten. Wenn der KUNDE
       seit zwei Wochen schweigt, ist es Zeit nachzufassen — aber erst dann,
       sonst fasst man Menschen nach, die noch am Überlegen sind. */
    public const STILL_BEI_MIR   = 7;
    public const STILL_BEIM_KUNDEN = 14;

    /** Höchstens so viele Zeilen. Eine Liste, die scrollt, ist keine Liste.
        Beim ersten Blick standen zwölf da und schoben „Du bist dran" wieder
        aus dem Bild — genau der Fehler, den der Deckel verhindern sollte.
        Sechs sind ein Blick; was nicht mehr hineinpasst, steht in der Zeile
        darunter als Zahl und unter „Meldungen" vollständig. */
    public const HOECHSTENS = 6;

    /* Und höchstens so viele je Art.
       WARUM DAS NOETIG IST: Beim ersten Lauf am 13.09.2026 standen achtzehn
       Störungen an, jede davon dringend. Sie nahmen alle zwölf Plätze — die
       ablaufenden Angebote und die seit Wochen stillen Vorgänge kamen gar
       nicht mehr vor. Eine Liste, die nur noch eine Art zeigt, ist wieder
       der Kasten, den sie ersetzen sollte. */
    public const JE_ART = 3;

    /**
     * Alles, was hängt — in der Reihenfolge, in der es wehtut.
     *
     * @param array{du:list<array>,kunde:list<array>,ruht:list<array>}|null $arbeit
     *        Die Arbeitsliste, falls der Aufrufer sie ohnehin schon hat.
     *        Ohne sie wird sie hier geholt; das kostet den ganzen Durchlauf
     *        durch alle Vorgänge ein zweites Mal.
     * @return list<array<string,mixed>>
     */
    public static function alles(?array $arbeit = null): array
    {
        /* Sortiert nach Dringlichkeit, dann nach Dauer: Was eilt, steht oben;
           darunter das, was am längsten liegt. Gleiches Alter, gleiche
           Reihenfolge wie geladen — stabil, damit die Liste beim Neuladen
           nicht springt. */
        $ordnen = static function (array $l): array {
            usort($l, static function (array $a, array $b): int {
                if ($a['eilig'] !== $b['eilig']) { return $a['eilig'] ? -1 : 1; }
                return (int) $b['tage'] <=> (int) $a['tage'];
            });
            return $l;
        };

        $arten = [
            $ordnen(self::stoerungen()),
            $ordnen(self::fristen()),
            $ordnen(self::stille($arbeit)),
        ];

        /* Erst bekommt jede Art ihre Plätze, dann füllen die Übriggebliebenen
           auf, was frei blieb. So verdrängt keine Art die anderen, und ein
           ruhiger Tag zeigt trotzdem eine volle Liste. */
        $zeilen = $rest = [];
        foreach ($arten as $l) {
            $zeilen = array_merge($zeilen, array_slice($l, 0, self::JE_ART));
            $rest   = array_merge($rest, array_slice($l, self::JE_ART));
        }
        $zeilen = $ordnen($zeilen);
        if (count($zeilen) < self::HOECHSTENS) {
            $zeilen = array_merge($zeilen,
                array_slice($ordnen($rest), 0, self::HOECHSTENS - count($zeilen)));
        }

        return array_slice($zeilen, 0, self::HOECHSTENS);
    }

    /* ---------------------------------------------------------------------
       1. WAS GEMELDET WURDE

       Nur Warnungen und Fehler. Info-Meldungen gehören nicht auf eine Liste
       von Dingen, die hängen — sonst sieht man den Fehler zwischen zwanzig
       Hinweisen nicht mehr.
       --------------------------------------------------------------------- */
    private static function stoerungen(): array
    {
        $zeilen = (array) self::still(static fn() => Db::all(
            "SELECT * FROM notifications
              WHERE read_at IS NULL AND level IN ('warnung','schlecht')
              ORDER BY id DESC LIMIT 20"), []);

        $aus = [];
        foreach ($zeilen as $z) {
            $tage = self::tageSeit((string) ($z['created_at'] ?? ''));
            $aus[] = [
                'art'    => 'stoerung',
                'titel'  => (string) $z['title'],
                'wer'    => '',
                'warum'  => mb_substr(trim((string) ($z['body'] ?? '')), 0, 220),
                'seit'   => (string) ($z['created_at'] ?? ''),
                'tage'   => $tage,
                /* Eine Störung eilt immer: Sie ist nicht „bald fällig",
                   sondern schon passiert. */
                'eilig'  => true,
                'ziel'   => trim((string) ($z['link'] ?? '')) !== ''
                    ? ltrim((string) $z['link'], '/') : 'benachrichtigungen',
                'wohin'  => 'Ansehen',
                /* Erledigt heisst gelesen, nicht geloescht: Die Meldung
                   verschwindet von hier, bleibt aber unter
                   Benachrichtigungen stehen. */
                'tat'    => 'meldung_gelesen',
                'tatId'  => (int) $z['id'],
                'tatWort' => 'Erledigt',
            ];
        }
        return $aus;
    }

    /* ---------------------------------------------------------------------
       2. WAS EINE FRIST HAT
       --------------------------------------------------------------------- */
    private static function fristen(): array
    {
        require_once __DIR__ . '/Vorgang.php';
        $aus = [];
        foreach ((array) self::still(static fn() => Vorgang::faellig(), []) as $f) {
            $aus[] = [
                'art'    => 'frist',
                'titel'  => (string) $f['was'],
                'wer'    => (string) $f['wer'],
                'warum'  => (string) $f['warum'],
                'seit'   => '',
                /* Fristen zählen rückwärts: Was in zwei Tagen fällig ist,
                   soll über dem stehen, was in zehn fällig ist. Deshalb das
                   Vorzeichen — gemeinsam sortiert wird nach „Dauer", und
                   eine kurze Restfrist ist eine lange Dringlichkeit. */
                'tage'   => -(int) $f['tage'],
                'eilig'  => (bool) $f['eilig'],
                'ziel'   => (string) $f['ziel'],
                'wohin'  => 'Ansehen',
            ];
        }
        return $aus;
    }

    /* ---------------------------------------------------------------------
       3. WO EINFACH NICHTS PASSIERT

       Der Fall ohne Kasten. Kein Fehler, keine Frist — nur Stille. Genau
       deshalb fällt er sonst niemandem auf.
       --------------------------------------------------------------------- */
    private static function stille(?array $arbeit): array
    {
        require_once __DIR__ . '/Vorgang.php';
        $arbeit ??= (array) self::still(static fn() => Vorgang::arbeitsliste(),
            ['du' => [], 'kunde' => [], 'ruht' => []]);

        $aus = [];
        foreach (['du' => self::STILL_BEI_MIR, 'kunde' => self::STILL_BEIM_KUNDEN] as $wo => $grenze) {
            foreach ((array) ($arbeit[$wo] ?? []) as $v) {
                $tage = (int) self::still(static fn() => Vorgang::ruhtSeitTagen($v), 0);
                if ($tage < $grenze) { continue; }

                $s = $v['schritt'] ?? null;
                $aus[] = [
                    'art'    => 'stille',
                    'titel'  => trim((string) ($v['firma'] ?: $v['kunde'])),
                    'wer'    => (string) ($v['stufe_wort'] ?? ''),
                    'warum'  => $wo === 'du'
                        ? 'Seit ' . $tage . ' Tagen liegt das bei dir. ' . (string) $v['warum']
                        : 'Seit ' . $tage . ' Tagen keine Reaktion vom Kunden. '
                          . 'Weder ein Fehler noch eine Frist — es passiert einfach nichts.',
                    'seit'   => '',
                    'tage'   => $tage,
                    /* Bei mir eilt es ab zwei Wochen, beim Kunden ab vier:
                       Was ich liegen lasse, lasse ich jemanden warten. */
                    'eilig'  => $tage >= ($wo === 'du' ? 14 : 28),
                    'ziel'   => 'vorgaenge/' . (string) $v['schluessel'],
                    'wohin'  => $s !== null ? (string) $s['knopf'] : 'Öffnen',
                ];
            }
        }
        return $aus;
    }

    /* ====================================================================== */

    /**
     * Wie viel insgesamt hängt — auch das, was nicht mehr in die Liste passt.
     *
     * Ohne diese Zahl wäre die Deckelung eine Lüge: Zwölf Zeilen sähen wie
     * zwölf Probleme aus, und die dreizehnte gäbe es für niemanden mehr.
     */
    public static function anzahl(?array $arbeit = null): int
    {
        return count(self::stoerungen()) + count(self::fristen()) + count(self::stille($arbeit));
    }

    private static function tageSeit(string $datum): int
    {
        if (trim($datum) === '') { return 0; }
        $z = strtotime($datum);
        return $z === false ? 0 : (int) floor((time() - $z) / 86400);
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
