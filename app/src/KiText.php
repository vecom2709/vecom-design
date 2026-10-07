<?php
declare(strict_types=1);

require_once __DIR__ . '/Ki.php';
require_once __DIR__ . '/KiPruefer.php';
require_once __DIR__ . '/KiKontext.php';

/**
 * Individuelle Texte (07.10.2026, Uwe: „jede email oder whatsapp soll individuell angepasst und
 * intelligent sein“ — Ja zu allen 13 Vorschlägen).
 *
 * GRUNDSATZ: Der Pflichtteil bleibt fest. Die KI schreibt nur DAZU — einen kurzen persönlichen Absatz
 * aus der Akte und genau einen Satz, was jetzt zu tun ist. Betrag, Link, Frist und Rechtstext stehen
 * weiter in der Vorlage. Jeder KI-Satz geht durch den Prüfer; fällt er durch oder kommt nichts, geht
 * die Vorlage unverändert raus.
 */
final class KiText
{
    /** Kunden-Mails mit persönlichem Absatz (Vorschläge 1–5). Rechtliche und reine Technik-Mails bleiben fest. */
    public const KUNDE_ANLAESSE = [
        'anfrage_eingegangen' => 'Seine Anfrage ist gerade angekommen.',
        'anfrage_eingegangen_fb' => 'Sein Projekt ist angekommen, es fehlen noch Angaben.',
        'kundenseite' => 'Er bekommt den Link zu seiner persönlichen Seite.',
        'vorhaben_erinnerung' => 'Erinnerung: In seiner persönlichen Seite fehlt noch ein Schritt.',
        'fragebogen_vorab' => 'Einladung zum Fragebogen, bevor er einen Preis bekommt.',
        'fragebogen_erinnerung' => 'Erinnerung an den Fragebogen. Nenne genau, was noch fehlt.',
        'fragebogen_danke' => 'Dank für den ausgefüllten Fragebogen.',
        'angebot' => 'Sein persönliches Angebot ist fertig.',
        'zahlungslink' => 'Er bekommt den Zahlungslink.',
        'zahlung_ok' => 'Seine Anzahlung ist angekommen.',
        'zahlung_erinnerung' => 'Freundliche Erinnerung an eine offene Zahlung. Nenne, was nach der Zahlung passiert.',
        'vorschau' => 'Die Vorschau seiner Website ist sichtbar.',
        'abnahme' => 'Seine Website ist fertig und wartet auf seine Abnahme.',
        'restzahlung' => 'Die Seite ist fertig, es fehlt die Restzahlung.',
        'online' => 'Seine Website ist online.',
        'paket' => 'Seine Website ist auch zum Herunterladen bereit.',
        'uebergabe' => 'Übergabe: alles Wichtige zu seiner Website schriftlich.',
        'bewertung_bitte' => 'Bitte um eine Google-Bewertung.',
        'hosting_angebot' => 'Seine Wunschdomain ist frei.',
        'hosting_fertig' => 'Domain und Hosting sind eingerichtet.',
        'domain_aktiv' => 'Seine Domain ist registriert.',
        'betreuung_faellig' => 'Die monatliche Betreuung ist fällig.',
        'hosting_faellig' => 'Die Rate für Domain und Hosting ist fällig.',
    ];

    /** Partner-Mails mit Zahlen und einem Tipp (Vorschlag 10). */
    public const PARTNER_ANLAESSE = [
        'partner_neukunde' => 'Über seinen Link ist ein neuer Interessent gekommen.',
        'partner_verdient' => 'Ein Kunde von ihm hat bezahlt, er hat Provision verdient.',
        'partner_ruhend' => 'Sein Link wurde länger nicht geöffnet.',
        'partner_bericht' => 'Sein Monatsbericht.',
        'partner_auszahlung' => 'Seine Provision wurde ausgezahlt.',
    ];

    private const SPRACHE = ['it' => 'Italienisch (Sie-Form mit „Lei“)', 'de' => 'Deutsch (Sie-Form)', 'en' => 'Englisch (höflich)'];

    /** Was die letzte einbauen()-Runde ergab — für die Kette und die Akte. */
    public static ?string $letzterGrund = null;

    private static function grundregeln(string $sprache): string
    {
        return "Du schreibst für Uwe Vetter von Vecom Design (Webdesign aus Sizilien) an echte Menschen. Schreibe auf "
            . (self::SPRACHE[$sprache] ?? self::SPRACHE['it']) . ". Regeln, ohne Ausnahme:\n"
            . "- Nur Tatsachen aus den FAKTEN und dem VERLAUF. Nichts erfinden: keine Zahl, kein Datum, keine Frist, kein Preis, keine Zusage, die dort nicht steht.\n"
            . "- Immer siezen. Warm, klar, kurz, ohne Floskeln, ohne Werbesprech, ohne Druck, ohne Angstmache.\n"
            . "- Keine Anrede (kein „Buongiorno“, kein „Guten Tag“) und keine Grußformel — die stehen schon in der Mail.\n"
            . "- Keine Links, Mailadressen oder Telefonnummern — die stehen schon in der Mail.\n"
            . "- Wiederhole nichts, was im VERLAUF schon gesagt wurde, und nichts, was die feste Mail ohnehin sagt.\n"
            . "- Hat der Empfänger im VERLAUF eine Frage gestellt, die sich aus den FAKTEN beantworten lässt, beantworte sie kurz. Sonst schreibe, dass Uwe sich persönlich dazu meldet.\n"
            . "- Erwähne nie, dass ein Programm oder eine KI schreibt.\n"
            . "- Antworte NUR mit dem Text selbst, ohne Anführungszeichen, ohne Überschrift.";
    }

    /* ================================================================== */
    /*  Kunden- und Partner-Mails                                          */
    /* ================================================================== */

    /**
     * Den persönlichen Absatz einbauen. Gibt den (vielleicht unveränderten) Text zurück; $bezug bekommt
     * ki_teil (der Absatz) und ki_fertig (damit eine zurückgehaltene Mail beim Senden keinen zweiten bekommt).
     */
    public static function einbauen(string $anlass, string $text, array &$bezug, string $sprache): string
    {
        self::$letzterGrund = null;
        if (!empty($bezug['ki_fertig']) || !empty($bezug['ohne_ki'])) { return $text; }
        /* Im Stripe-Webhook nie: Stripe wartet nur kurz auf die Antwort und schickt das Ereignis sonst noch einmal. */
        if (class_exists('Automation', false) && str_starts_with(Automation::herkunft(), 'stripe')) { self::$letzterGrund = 'Stripe-Webhook'; return $text; }
        $sprache = isset(self::SPRACHE[$sprache]) ? $sprache : 'it';
        $kontext = null; $anlassSatz = '';
        if (isset(self::KUNDE_ANLAESSE[$anlass]) && !empty($bezug['customer_id']) && Ki::bereichAn('kunde')) {
            $kid = (int) $bezug['customer_id'];
            try { if ((int) Db::wert('SELECT ki_aus FROM customers WHERE id = ?', [$kid], 0) === 1) { self::$letzterGrund = 'für diesen Kunden aus'; return $text; } }
            catch (Throwable $e) { return $text; }   // ohne Migration 216: lieber ohne KI
            $kontext = KiKontext::kunde($kid, $anlass, $sprache);
            $anlassSatz = self::KUNDE_ANLAESSE[$anlass];
        } elseif (isset(self::PARTNER_ANLAESSE[$anlass]) && !empty($bezug['partner_id']) && Ki::bereichAn('partner')) {
            $pk = KiKontext::partner((int) $bezug['partner_id']);
            if ($pk) { $kontext = $pk + ['stil' => 'knapp', 'naechster' => '', 'verlauf' => '']; }
            $anlassSatz = self::PARTNER_ANLAESSE[$anlass];
        }
        if ($kontext === null) { return $text; }

        $partner = isset(self::PARTNER_ANLAESSE[$anlass]);
        $auftrag = $partner
            ? "Schreibe ZWEI kurze Sätze, die dem Partner etwas bringen: die wichtigste Zahl aus den FAKTEN in Worten eingeordnet und einen konkreten, ehrlichen Tipp, was er diese Woche tun kann (z. B. einen frischen Interessenten anrufen). Gibt es keine Zahlen, einen ermutigenden, konkreten Tipp."
            : "Schreibe einen persönlichen Absatz aus zwei bis drei Sätzen, der zeigt, dass Uwe diesen Kunden und sein Projekt kennt (Branche, Stand, was er zuletzt geschrieben oder geschickt hat). "
              . ($kontext['naechster'] !== '' ? "Schließe mit genau EINEM Satz, was er jetzt tun kann: " . $kontext['naechster'] . '.' : "Schließe mit genau einem Satz, was als Nächstes passiert — nur, wenn es aus den FAKTEN hervorgeht.")
              . " Ton: " . ['knapp' => 'kurz und direkt, er schreibt selbst knapp', 'ausführlich' => 'etwas ausführlicher und erklärend, er schreibt selbst ausführlich', 'normal' => 'freundlich und normal lang'][$kontext['stil']] . '.';
        $nutzer = "ANLASS: $anlassSatz\n\nFESTE MAIL (wird unverändert verschickt, dein Text kommt nach der Anrede hinein):\n" . mb_substr($text, 0, 2500)
            . "\n\nFAKTEN:\n" . $kontext['fakten'] . ($kontext['verlauf'] !== '' ? "\n\nVERLAUF (älteste zuerst):\n" . $kontext['verlauf'] : '')
            . "\n\nAUFTRAG: $auftrag";
        $antwort = Ki::schreiben($partner ? 'partner' : 'kunde', self::grundregeln($sprache), $nutzer, 300);
        if ($antwort === null) { self::$letzterGrund = 'keine Antwort'; return $text; }
        $antwort = self::saeubern($antwort);
        $maengel = KiPruefer::pruefen($antwort, $kontext['fakten'] . "\n" . $kontext['verlauf'] . "\n" . $text, $sprache, ['max' => 650]);
        Ki::vermerken($maengel === []);
        if ($maengel) { self::$letzterGrund = 'Prüfer: ' . implode('; ', $maengel); return $text; }
        $bezug['ki_teil'] = $antwort;
        $bezug['ki_fertig'] = true;
        return self::nachAnrede($text, $antwort);
    }

    /** Anführungszeichen und Überschriften weg, Leerzeilen zusammen. */
    public static function saeubern(string $t): string
    {
        $t = trim($t);
        $t = (string) preg_replace('~^["„“»«]+|["“”»«]+$~u', '', $t);
        $t = (string) preg_replace('~^\s*#+\s.*$~mu', '', $t);
        $t = (string) preg_replace("~\n{3,}~", "\n\n", $t);
        return trim($t);
    }

    /** Den Absatz nach der ersten Zeile (der Anrede) einsetzen; ohne erkennbare Anrede ganz oben. */
    public static function nachAnrede(string $text, string $absatz): string
    {
        $teile = preg_split("~\r?\n\s*\r?\n~", $text, 2);
        if (count($teile) === 2 && mb_strlen(trim($teile[0])) <= 80) {
            return rtrim($teile[0]) . "\n\n" . $absatz . "\n\n" . ltrim($teile[1]);
        }
        return $absatz . "\n\n" . $text;
    }

    /* ================================================================== */
    /*  Akquise: Folge-Mails (Vorschlag 6) und WhatsApp-Vorlagen (9)        */
    /* ================================================================== */

    /**
     * Ein Absatz für Folge-Schritt $schritt: bezieht sich auf die erste Mail und nennt — bei den
     * Schritten 1–3 — genau den übergebenen, noch nicht genannten Befund.
     */
    public static function folgeAbsatz(array $f, int $schritt, string $sprache, string $vorlage, ?array $befund, string $vorher): ?string
    {
        if (!Ki::bereichAn('folge')) { return null; }
        $fakten = self::firmaFakten($f) . ($befund ? "\nBeobachtung auf der Website: " . (string) $befund['titel']
            . (trim((string) ($befund['beschreibung'] ?? '')) !== '' ? ' — ' . trim((string) $befund['beschreibung']) : '')
            . (trim((string) ($befund['beleg'] ?? '')) !== '' ? ' (Beleg: ' . mb_substr(trim((string) $befund['beleg']), 0, 200) . ')' : '') : '');
        $auftrag = $befund
            ? 'Schreibe zwei Sätze für diese Folge-Nachricht an den Betrieb: knüpfe kurz an die erste Nachricht an und nenne genau die eine BEOBACHTUNG oben — sachlich, hilfreich, ohne zu werten, was der Betrieb davon hat, wenn sie behoben ist.'
            : 'Schreibe einen bis zwei Sätze für diese Folge-Nachricht an den Betrieb: knüpfe freundlich an die vorigen Nachrichten an, ohne sie zu wiederholen, ohne Druck.';
        $nutzer = "SCHRITT $schritt von 5.\n\nFESTE NACHRICHT (bleibt, dein Text kommt nach der Anrede):\n" . mb_substr($vorlage, 0, 2000)
            . "\n\nFAKTEN:\n$fakten" . ($vorher !== '' ? "\n\nVORIGE NACHRICHTEN AN DIESEN BETRIEB:\n" . mb_substr($vorher, 0, 3000) : '') . "\n\nAUFTRAG: $auftrag";
        $a = Ki::schreiben('folge', self::grundregeln($sprache), $nutzer, 220);
        if ($a === null) { return null; }
        $a = self::saeubern($a);
        $m = KiPruefer::pruefen($a, $fakten . "\n" . $vorher . "\n" . $vorlage, $sprache, ['max' => 450]);
        Ki::vermerken($m === []);
        return $m === [] ? $a : null;
    }

    /** Ein Satz für den freien Platzhalter der WhatsApp-Vorlage (Meta: eine Zeile, keine Zeilenumbrüche). */
    public static function waSatz(array $f, int $schritt, string $sprache, ?array $befund): ?string
    {
        if (!Ki::bereichAn('whatsapp')) { return null; }
        $fakten = self::firmaFakten($f) . ($befund ? "\nBeobachtung auf der Website: " . (string) $befund['titel'] : '');
        $nutzer = "WhatsApp-Folgenachricht Schritt $schritt von 5 an den Betrieb.\n\nFAKTEN:\n$fakten\n\nAUFTRAG: Schreibe EINEN kurzen Satz (höchstens 140 Zeichen), "
            . ($befund ? 'der die Beobachtung freundlich und sachlich nennt.' : 'der persönlich an den Betrieb anknüpft.') . ' Kein Zeilenumbruch.';
        $a = Ki::schreiben('whatsapp', self::grundregeln($sprache), $nutzer, 90);
        if ($a === null) { return null; }
        $a = trim((string) preg_replace('~\s+~u', ' ', self::saeubern($a)));
        $m = KiPruefer::pruefen($a, $fakten, $sprache, ['max' => 160]);
        Ki::vermerken($m === []);
        return $m === [] ? $a : null;
    }

    public static function firmaFakten(array $f): string
    {
        $z = ['Betrieb: ' . (string) ($f['name'] ?? '')];
        foreach (['branche' => 'Branche', 'stadt' => 'Ort', 'domain' => 'Website'] as $k => $w) {
            if (trim((string) ($f[$k] ?? '')) !== '') { $z[] = $w . ': ' . trim((string) $f[$k]); }
        }
        return implode("\n", $z);
    }

    /* ================================================================== */
    /*  Antworten auf Antworten (Vorschläge 7 und 8)                        */
    /* ================================================================== */

    /**
     * Ein Antwortentwurf auf die Nachricht eines Betriebs.
     * @param list<string> $links Links, die in der Antwort stehen dürfen (Termin, persönlicher Bereich)
     * @return array{betreff:string, text:string}|null
     */
    public static function antwortEntwurf(array $f, string $eingang, string $klasse, string $sprache, string $kanal, string $vorher, array $links, string $absender): ?array
    {
        if (!Ki::bereichAn('antwort')) { return null; }
        $fakten = self::firmaFakten($f) . "\nAbsender der Antwort: $absender";
        $ziel = ['CALL_REQUEST' => 'Er möchte einen Anruf: biete an, dass er sich über den Terminlink eine Zeit aussucht, oder dass Uwe ihn anruft.',
                 'PRICE_REQUEST' => 'Er fragt nach dem Preis: nenne KEINEN Betrag; erkläre, dass er in seinem persönlichen Bereich in zwei Minuten einen Richtpreis bekommt, und dass Uwe gern alles bespricht.',
                 'MORE_INFO' => 'Er möchte mehr wissen: verweise auf seinen persönlichen Bereich mit der Analyse und Beispielen und biete ein kurzes Gespräch an.',
                 'INTERESTED' => 'Er hat Interesse: bedanke dich und schlage den einfachsten nächsten Schritt vor (persönlicher Bereich oder kurzes Gespräch).'][$klasse]
            ?? 'Beantworte seine Nachricht freundlich und kurz; wenn unklar ist, was er möchte, frage höflich nach.';
        $nutzer = "EINGEGANGENE NACHRICHT ($kanal):\n„" . mb_substr($eingang, 0, 2000) . "“\n\nFAKTEN:\n$fakten"
            . ($vorher !== '' ? "\n\nUNSERE VORIGEN NACHRICHTEN:\n" . mb_substr($vorher, 0, 2500) : '')
            . ($links ? "\n\nERLAUBTE LINKS (genau so übernehmen, wenn passend):\n" . implode("\n", $links) : '')
            . "\n\nAUFTRAG: $ziel " . ($kanal === 'whatsapp'
                ? 'Schreibe eine WhatsApp-Antwort, höchstens drei kurze Sätze, mit kurzer Anrede, unterschrieben mit „Uwe“.'
                : 'Schreibe eine kurze E-Mail-Antwort (höchstens sechs Sätze) mit Anrede und Gruß, unterschrieben mit „Uwe Vetter · Vecom Design“. Erste Zeile: „Betreff: …“, dann eine Leerzeile, dann der Text.');
        $a = Ki::schreiben('antwort', str_replace(["- Keine Anrede (kein „Buongiorno“, kein „Guten Tag“) und keine Grußformel — die stehen schon in der Mail.\n", "- Keine Links, Mailadressen oder Telefonnummern — die stehen schon in der Mail.\n"],
            ['', "- Links nur aus den ERLAUBTEN LINKS.\n"], self::grundregeln($sprache)), $nutzer, 400);
        if ($a === null) { return null; }
        $a = self::saeubern($a);
        $betreff = '';
        if (preg_match('~^(betreff|oggetto|subject)\s*:\s*(.+)$~imu', $a, $mm)) {
            $betreff = trim($mm[2]);
            $a = trim((string) preg_replace('~^(betreff|oggetto|subject)\s*:.*$~imu', '', $a, 1));
        }
        if ($kanal === 'email' && $betreff === '') { $betreff = ['it' => 'Re: il sito di ', 'de' => 'Re: die Website von ', 'en' => 'Re: the website of '][$sprache] . (string) $f['name']; }
        $m = KiPruefer::pruefen($a . ' ' . $betreff, $fakten . "\n" . $vorher . "\n" . $eingang . "\n" . implode("\n", $links), $sprache,
            ['max' => $kanal === 'whatsapp' ? 600 : 1400, 'links' => $links, 'anrede_ok' => true]);
        Ki::vermerken($m === []);
        if ($m) { self::$letzterGrund = 'Prüfer: ' . implode('; ', $m); return null; }
        return ['betreff' => mb_substr($betreff, 0, 200), 'text' => $a];
    }
}
