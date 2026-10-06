<?php
declare(strict_types=1);

/**
 * Akquise-CRM Modul D: Nachrichten-Werkstatt (06.10.2026).
 *
 * Uwe: „KEINE Nachricht darf automatisch ungeprüft versendet werden.“ und
 * „Eine E-Mail darf niemals ohne Betreff versendet werden.“ — beides galt
 * bisher nur für die Folge-Mails (AkquiseFolge → AkquiseText::pruefen). Der
 * Direktversand aus der Verwaltung (AkquiseMail::direktSenden) prüfte nur
 * Betreff und Länge. Die Werkstatt legt dieselbe Textprüfung über jeden
 * Entwurf und jeden Direktversand, sichtbar als Liste ✅/⚠/⛔:
 *
 *   ok      — erfüllt
 *   hinweis — darf raus, aber nur, wenn ein Mensch den Hinweis bestätigt
 *   stopp   — geht nicht raus, auch nicht mit Bestätigung
 *
 * Eine Regel steht an genau einer Stelle: Was AkquiseText::pruefen schon
 * prüft (Angstformulierungen, interne Wörter, unbelegte Zahlen, Preise,
 * Länge), wird hier nur eingestuft — nicht ein zweites Mal geschrieben.
 * Die Werkstatt erfindet nichts und schreibt keinen Text um.
 */
final class AkquiseWerkstatt
{
    public const OK = 'ok';
    public const HINWEIS = 'hinweis';
    public const STOPP = 'stopp';

    /** Zusicherungen, die Vecom nicht geben darf (Uwe: kein „rechtssicher“, kein „abmahnsicher“). */
    private const ZUSICHERUNG = '~\b(rechtssicher\w*|abmahnsicher\w*|dsgvo[- ]konform garantiert|100\s*%\s*(legal|rechtskonform|sicher)|a norma di legge garantit\w*|legally compliant guaranteed)\b~iu';

    /** Was nach einer Vorlage aussieht, die nicht ausgefüllt wurde. */
    private const PLATZHALTER = '~(\[[^\]\n]{1,40}\]|\{\{[^}]*\}\}|<\s*(name|firma|betrieb|nome|azienda)\s*>|\bX{3,}\b|\bTODO\b)~iu';

    /** Anreden in den drei Sprachen — der erste Satz soll den Betrieb ansprechen. */
    private const ANREDE = '~^\s*(guten (tag|morgen|abend)|hallo|sehr geehrte|liebe[rs]?\b|grüß gott|moin|buongiorno|buonasera|gentile|egregi|salve|ciao|spettabile|dear|hello|hi\b|good (morning|afternoon|evening))~iu';

    /**
     * Die Prüfliste für einen Text.
     *
     * @param array<string,mixed> $f Betrieb (akq_firmen)
     * @param string $kanal email|whatsapp
     * @param bool $senden true = es geht um den Versand (dann zählt der Kommunikationsstatus), false = Entwurf
     * @return list<array{stufe:string,text:string}>
     */
    public static function pruefliste(array $f, string $kanal, string $betreff, string $text, bool $senden = false): array
    {
        require_once __DIR__ . '/AkquiseText.php';
        require_once __DIR__ . '/AkquiseMail.php';
        $kanal = $kanal === 'whatsapp' ? 'whatsapp' : 'email';
        $betreff = trim($betreff);
        $text = trim(str_replace("\r\n", "\n", $text));
        $sprache = AkquiseText::spracheFuer($f);
        $l = [];
        $neu = static function (string $stufe, string $was) use (&$l): void { $l[] = ['stufe' => $stufe, 'text' => $was]; };

        /* Betreff — Pflicht bei E-Mail (Uwe, wörtlich: „niemals ohne Betreff“). */
        if ($kanal === 'email') {
            if ($betreff === '') { $neu(self::STOPP, 'Der Betreff fehlt — ohne Betreff geht keine E-Mail raus.'); }
            elseif (mb_strlen($betreff) > 90) { $neu(self::HINWEIS, 'Der Betreff ist lang (' . mb_strlen($betreff) . ' Zeichen) — im Postfach wird er abgeschnitten.'); }
            else { $neu(self::OK, 'Betreff vorhanden.'); }
        }

        /* Länge */
        if (mb_strlen($text) < 20) { $neu(self::STOPP, 'Der Text ist zu kurz.'); }

        /* Platzhalter aus Vorlagen */
        if (preg_match(self::PLATZHALTER, $betreff . "\n" . $text, $m)) {
            $neu(self::STOPP, 'Platzhalter nicht ausgefüllt: „' . trim($m[0]) . '“.');
        } elseif ($text !== '') { $neu(self::OK, 'Keine offenen Platzhalter.'); }

        /* Zusicherungen, die niemand geben kann */
        if (preg_match(self::ZUSICHERUNG, $betreff . "\n" . $text, $m)) {
            $neu(self::STOPP, 'Zusicherung, die Vecom nicht geben darf: „' . $m[0] . '“.');
        }

        /* Was AkquiseText schon prüft — hier nur eingestuft. */
        $erst = self::erstkontakt($f);
        $befunde = self::befunde($f);
        $sauber = ['angst' => true, 'intern' => true, 'zahl' => true];
        foreach (AkquiseText::pruefen($betreff, self::ohneLinks($text), $sprache, $befunde, $kanal, $f) as $m) {
            if (str_starts_with($m, 'Angst- oder Übertreibung')) { $neu(self::STOPP, $m . '.'); $sauber['angst'] = false; }
            elseif (str_starts_with($m, 'Internes Wort')) { $neu(self::STOPP, $m . ' — das ist Vecom-Sprache, nicht für den Betrieb.'); $sauber['intern'] = false; }
            elseif (str_starts_with($m, 'Zahl ohne Beleg')) {
                /* Zahlen aus Vecoms eigener Vorlage („in 90 Sekunden“) sind Vecoms Aussage über sich, nicht über den Betrieb. */
                if (preg_match('~„([^“]+)“~u', $m, $z) && in_array($z[1], self::vorlagenZahlen($f, $befunde), true)) { continue; }
                $neu(self::HINWEIS, $m . ' Nur schreiben, was belegt ist.'); $sauber['zahl'] = false;
            }
            elseif (str_starts_with($m, 'Preisangabe')) { if ($erst) { $neu(self::HINWEIS, $m . ' — beim ersten Kontakt besser im Gespräch klären.'); } }
            elseif (str_starts_with($m, 'Der Link auf vecom-design.it')) { if ($erst) { $neu(self::HINWEIS, 'Kein Link auf vecom-design.it — der Betrieb kann nicht nachsehen, wer schreibt.'); } }
            elseif (str_starts_with($m, 'Der Hinweis, wie man weitere Nachrichten ablehnt')) {
                if ($kanal === 'whatsapp') { $neu(self::HINWEIS, 'Kein Satz, wie man weitere Nachrichten ablehnt (z. B. „Antworten Sie STOP“).'); }
            }
            elseif (str_starts_with($m, 'Der Text ist zu lang')) { $neu(self::HINWEIS, $m); }
        }
        if ($sauber['angst']) { $neu(self::OK, 'Keine Angst- oder Übertreibungsformulierung.'); }
        if ($sauber['intern']) { $neu(self::OK, 'Keine internen Wörter (Score, Lead …).'); }
        if ($sauber['zahl']) { $neu(self::OK, 'Jede Zahl ist belegt.'); }
        if ($kanal === 'email') { $neu(self::OK, 'Abmeldelink und List-Unsubscribe werden beim Senden angehängt.'); }

        /* Anrede */
        if ($text !== '' && !preg_match(self::ANREDE, $text)) { $neu(self::HINWEIS, 'Keine Anrede am Anfang — der Text beginnt mit „' . mb_substr(strtok($text, "\n") ?: '', 0, 40) . '“.'); }

        /* Darf es überhaupt raus? Nur beim Versand eine Bedingung, im Entwurf eine Auskunft. */
        if ($kanal === 'email') {
            $k = AkquiseMail::kann($f);
            if ($k['senden']) {
                $neu(self::OK, 'Versandgrund dokumentiert: ' . (AkquiseMail::GRUENDE[(string) ($f['email_legal_basis'] ?? '')][0] ?? 'Einwilligung') . '.');
                if (!$k['werbung']) { $neu(self::HINWEIS, 'Der dokumentierte Grund deckt keine Werbung — nur Antwort bzw. geschäftliche Nachricht, kein Angebot.'); }
            } elseif ($senden) {
                $neu(self::STOPP, (string) ($k['grund'] ?? 'Kein Versandgrund dokumentiert.'));
            } else {
                $neu(self::HINWEIS, 'Entwurf: ' . (string) ($k['grund'] ?? 'Kein Versandgrund dokumentiert.') . ' Senden geht erst danach.');
            }
        }

        /* Stopp zuerst, dann Hinweise, dann Erfülltes — wer liest, sieht das Wichtige oben. */
        $rang = [self::STOPP => 0, self::HINWEIS => 1, self::OK => 2];
        usort($l, static fn($a, $b) => $rang[$a['stufe']] <=> $rang[$b['stufe']]);
        return $l;
    }

    /** @param list<array{stufe:string,text:string}> $liste */
    public static function zahl(array $liste, string $stufe): int
    {
        return count(array_filter($liste, static fn($x) => $x['stufe'] === $stufe));
    }

    /**
     * Darf der Text raus? Stopp → nie. Hinweise → nur bestätigt.
     * Wirft mit allen Gründen, damit auf dem Bildschirm steht, was zu tun ist.
     *
     * @param list<array{stufe:string,text:string}> $liste
     */
    public static function freigeben(array $liste, bool $hinweiseGelesen): void
    {
        $stopp = array_column(array_filter($liste, static fn($x) => $x['stufe'] === self::STOPP), 'text');
        if ($stopp) { throw new RuntimeException('Nicht gesendet: ' . implode(' ', $stopp)); }
        if (self::zahl($liste, self::HINWEIS) > 0 && !$hinweiseGelesen) {
            throw new RuntimeException('Nicht gesendet: Es gibt Hinweise zum Text. Bitte lesen und „Hinweise gelesen“ ankreuzen.');
        }
    }

    /**
     * Wie die E-Mail beim Betrieb ankommt — Absender, Antwort an, Betreff, Text samt Fußzeile.
     * Der Abmeldelink entsteht erst beim Senden (eigenes Token je Versand); hier steht seine Form.
     *
     * @return array{an:string,von:string,antwort:?string,betreff:string,text:string}
     */
    public static function vorschau(array $f, string $betreff, string $text): array
    {
        require_once __DIR__ . '/AkquiseMail.php';
        require_once __DIR__ . '/AkquiseText.php';
        $abs = AkquiseMail::absender();
        $web = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $fuss = ['de' => 'Keine weiteren Nachrichten: ', 'it' => 'Non ricevere altri messaggi: ', 'en' => 'No further messages: '][AkquiseText::spracheFuer($f)] ?? '';
        $von = (string) ($abs['email'] ?? AkquiseText::absender()['email'] ?? '');
        return [
            'an' => (string) ($f['email'] ?? ''),
            /* Seit 06.10.2026 geht die Mail aus dem eigenen Programm (mailto) -- dann steht dort die eigene Adresse. */
            'von' => ($abs['email'] ?? null) === null ? 'deine eigene Adresse (aus deinem Mailprogramm)' : trim((string) ($abs['name'] ?? '') . ' <' . $von . '>'),
            'antwort' => ($abs['antwort'] ?? null) !== null && $abs['antwort'] !== $von ? (string) $abs['antwort'] : null,
            'betreff' => trim($betreff),
            'text' => trim(str_replace("\r\n", "\n", $text)) . "\n\n" . $fuss . $web . '/widerspruch.php?t=…',
        ];
    }

    /**
     * Links stehen für ihren Ort, nicht für eine Behauptung: Ziffern in einem Pfad (?t=…, /check/90) sind keine Zahl,
     * die belegt werden muss. Der eigene Auftritt (Config „website“, lokal z. B. 127.0.0.1:8080) zählt als vecom-design.it.
     */
    public static function ohneLinks(string $text): string
    {
        $eigen = strtolower((string) parse_url((string) Config::get('website', 'https://vecom-design.it'), PHP_URL_HOST));
        return (string) preg_replace_callback('~https?://[^\s<>"]+~iu', static function (array $m) use ($eigen): string {
            $host = strtolower((string) parse_url($m[0], PHP_URL_HOST));
            if ($host === $eigen || str_ends_with($host, 'vecom-design.it')) { return 'vecom-design.it'; }
            return preg_match('~^[\d.]+$~', $host) ? 'link' : $host;
        }, $text);
    }

    /** @return list<string> Zahlen, die in Vecoms fertigen Texten für diesen Betrieb stehen (alle drei Sprachen). */
    private static function vorlagenZahlen(array $f, array $befunde): array
    {
        static $merk = [];
        $k = (int) ($f['id'] ?? 0);
        if (!isset($merk[$k])) {
            require_once __DIR__ . '/AkquiseAnsprechen.php';
            $alles = '';
            foreach (['it', 'de', 'en'] as $sp) {
                try { $p = AkquiseAnsprechen::paket($f, $befunde, $sp); $alles .= ' ' . ($p['email']['text'] ?? '') . ' ' . ($p['email']['betreff'] ?? '') . ' ' . ($p['whatsapp']['text'] ?? ''); }
                catch (Throwable $e) { }
            }
            preg_match_all('~\d+(?:[.,]\d+)?~u', self::ohneLinks($alles), $t);
            $merk[$k] = array_values(array_unique($t[0]));
        }
        return $merk[$k];
    }

    /** Erster Kontakt = noch nichts gesendet und keine Antwort. */
    private static function erstkontakt(array $f): bool
    {
        if (empty($f['id'])) { return true; }
        if (!in_array((string) ($f['kontakt_status'] ?? 'neu'), ['neu', 'qualifiziert', 'vorlage', 'freigegeben'], true)) { return false; }
        return (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand')", [(int) $f['id']], 0) === 0
            && (int) Db::wert('SELECT COUNT(*) FROM akq_antworten WHERE firma_id = ?', [(int) $f['id']], 0) === 0;
    }

    /** Belege für Zahlen: die Befunde der letzten Prüfung. */
    private static function befunde(array $f): array
    {
        if (empty($f['id'])) { return []; }
        $audit = Akquise::letzterAudit((int) $f['id']);
        return $audit ? Akquise::befunde((int) $audit['id']) : [];
    }
}
