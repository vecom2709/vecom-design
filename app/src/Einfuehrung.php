<?php
declare(strict_types=1);

/* ==========================================================================
   Einfuehrung.php — Niedrige Preise auf Zeit, und was danach passiert.

   DIE ZAHL WIRD GERECHNET, NIE GESPEICHERT

   Es waere bequem, einen Zaehler hochzuzaehlen. Er waere irgendwann falsch —
   eine stornierte Bestellung, eine Rueckerstattung, eine von Hand gebuchte
   Zahlung, und niemand merkt es. Deshalb kommt der Stand jedes Mal frisch aus
   den Zahlungen. Das kostet eine Abfrage und spart einen Irrtum, den man
   nicht sieht.

   ABGESCHLOSSEN HEISST VOLL BEZAHLT

   Nicht angezahlt, nicht online, nicht freigegeben: voll bezahlt. Die Summe
   der Zahlungen mit Status "bezahlt" hat den Bestellpreis erreicht. Alles
   andere waere eine Zahl, die sich spaeter noch rueckwaerts bewegen kann.

   WARUM DIE ERHOEHUNG NICHT VON ALLEIN PASSIERT

   Eine Preisrunde, die nachts durchlaeuft, erwischt einen mitten im
   Gespraech: Man hat vormittags eine Zahl genannt und weiss nachmittags
   nicht mehr, ob sie noch gilt. Deshalb meldet sich diese Klasse nur — mit
   einer fertigen Vorher-Nachher-Liste. Angehoben wird auf Knopfdruck.

   Die Betreuung bleibt aussen vor. Sie ist ein laufender Vertrag; wer ihn
   hat, hat einen Preis vereinbart.
   ========================================================================== */
final class Einfuehrung
{
    /** Diese Bausteingruppe wird von der Erhoehung nie erfasst. */
    private const AUSGENOMMEN = ['betreuung'];

    /* ----------------------------------------------------------------------
       Stand
       ---------------------------------------------------------------------- */

    public static function laeuft(): bool
    {
        return self::einstellung('einfuehrung_aktiv', '1') === '1'
            && self::einstellung('einfuehrung_erledigt', '0') !== '1';
    }

    public static function ziel(): int
    {
        return max(1, (int) self::einstellung('einfuehrung_ziel', '10'));
    }

    public static function erhoehung(): int
    {
        return max(0, min(200, (int) self::einstellung('einfuehrung_erhoehung', '20')));
    }

    /**
     * Wie viele Websites voll bezahlt sind.
     *
     * Beispieldaten zaehlen nicht mit, Bestellungen ohne Preis auch nicht —
     * eine Nullbestellung waere sonst ein Gratis-Abschluss.
     */
    public static function zaehler(): int
    {
        try {
            return (int) Db::wert(
                "SELECT COUNT(*) FROM orders o
                  WHERE o.demo = 0 AND o.price_cents > 0
                    AND (SELECT COALESCE(SUM(p.amount_cents), 0) FROM payments p
                          WHERE p.order_id = o.id AND p.status = 'bezahlt') >= o.price_cents",
                [], 0
            );
        } catch (Throwable $e) { return 0; }
    }

    /** Wie viele Plaetze noch offen sind. Nie unter null. */
    public static function restplaetze(): int
    {
        return max(0, self::ziel() - self::zaehler());
    }

    /** Ist die Phase am Ende? */
    public static function erreicht(): bool
    {
        return self::laeuft() && self::zaehler() >= self::ziel();
    }

    /* ----------------------------------------------------------------------
       Die Erhoehung
       ---------------------------------------------------------------------- */

    /**
     * Was die Erhoehung mit jedem Baustein machen wuerde.
     *
     * Wird zweimal gebraucht und muss beide Male dasselbe sagen: einmal fuer
     * die Liste, die Uwe vor dem Knopfdruck sieht, und einmal fuer das
     * Anheben selbst. Deshalb eine Quelle.
     *
     * @return list<array{slug:string,name:string,alt_von:int,alt_bis:int,neu_von:int,neu_bis:int}>
     */
    public static function vorschau(?int $prozent = null): array
    {
        $prozent ??= self::erhoehung();
        $zeilen = [];
        try {
            $bausteine = Db::all('SELECT * FROM bausteine ORDER BY sortierung, id');
        } catch (Throwable $e) { return []; }

        foreach ($bausteine as $b) {
            if (in_array((string) $b['gruppe'], self::AUSGENOMMEN, true)) { continue; }
            $altVon = (int) $b['preis_cents'];
            $altBis = (int) $b['preis_bis_cents'];
            $zeilen[] = [
                'slug'    => (string) $b['slug'],
                'name'    => Baukasten::name($b, 'de'),
                'alt_von' => $altVon,
                'alt_bis' => $altBis,
                'neu_von' => self::anheben1($altVon, $prozent),
                'neu_bis' => self::anheben1($altBis, $prozent),
            ];
        }
        return $zeilen;
    }

    /**
     * Ein einzelner Preis, erhoeht und auf volle Euro gerundet.
     *
     * Krumme Cent-Betraege in einem Katalog sehen nach Rechenfehler aus.
     * 299 plus zwanzig Prozent ist 358,80 — im Katalog steht danach 359.
     */
    private static function anheben1(int $cents, int $prozent): int
    {
        if ($cents <= 0) { return 0; }
        return (int) (round($cents * (100 + $prozent) / 100 / 100) * 100);
    }

    /**
     * Hebt die Preise an und beendet die Einfuehrungsphase.
     *
     * In einer Transaktion, damit nicht die halbe Liste teurer wird und die
     * andere Haelfte nicht. Gibt zurueck, wie viele Bausteine sich geaendert
     * haben.
     */
    public static function anwenden(?int $prozent = null): int
    {
        // Nur einmal. Ohne diese Sperre macht ein zweimal abgeschicktes
        // Formular aus 299 erst 359 und dann 431 — und niemand sieht dem
        // Katalog hinterher an, dass er doppelt angehoben wurde.
        if (!self::laeuft()) { return 0; }

        $prozent ??= self::erhoehung();
        $zeilen  = self::vorschau($prozent);
        if (!$zeilen) { return 0; }

        $wie = 0;
        Db::transaktion(static function () use ($zeilen, &$wie) {
            foreach ($zeilen as $z) {
                $b = Db::one('SELECT id FROM bausteine WHERE slug = ?', [$z['slug']]);
                if (!$b) { continue; }
                Db::update('bausteine', (int) $b['id'], [
                    'preis_cents'     => $z['neu_von'],
                    'preis_bis_cents' => $z['neu_bis'],
                ]);
                $wie++;
            }
            Db::run("INSERT INTO settings (skey, svalue) VALUES ('einfuehrung_erledigt', '1')
                     ON DUPLICATE KEY UPDATE svalue = '1'");
        });

        try {
            Events::protokoll('einfuehrung_ende',
                'Einfuehrungspreise beendet, ' . $wie . ' Bausteine um ' . $prozent . ' Prozent angehoben');
        } catch (Throwable $e) { /* das Protokoll ist Beiwerk */ }

        return $wie;
    }

    /* ----------------------------------------------------------------------
       Klein
       ---------------------------------------------------------------------- */

    private static function einstellung(string $schluessel, string $ersatz): string
    {
        try {
            return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$schluessel], $ersatz);
        } catch (Throwable $e) { return $ersatz; }
    }
}
