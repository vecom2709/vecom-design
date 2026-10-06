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

    /* ================================================================== */
    /*  D-2: Töne über den PC (06.10.2026, Uwe: KI „weiter über den        */
    /*  PC-Worker“). Der Server ruft keine KI auf: Er legt einen Auftrag   */
    /*  an (mk_auftraege, art = ton), der PC holt ihn alle fünf Minuten,   */
    /*  Claude Code schreibt ohne Werkzeuge um, der Vorschlag kommt hier   */
    /*  an. Übernommen wird von Hand; gesendet wird nie.                   */
    /* ================================================================== */

    /** Ton → [Knopf, Anweisung für Claude] */
    public const TOENE = [
        'kuerzer'         => ['Kürzer', 'Kürzer: höchstens etwa die Hälfte der Länge. Weglassen statt umschreiben — Füllsätze, Wiederholungen, Höflichkeitsschleifen. Alles Wesentliche (Anliegen, Links, Abmeldesatz, Gruß) bleibt.'],
        'lockerer'        => ['Lockerer', 'Lockerer: wärmer und persönlicher, wie ein Mensch, der vor Ort arbeitet — aber in derselben Anredeform (Lei/Sie bleibt). Keine Witze, keine Emojis, keine Umgangssprache, die unseriös wirkt.'],
        'professioneller' => ['Professioneller', 'Professioneller: klarer, sachlicher, ruhiger. Kein Werbesprech, keine Superlative, keine Ausrufezeichen. Höflich, präzise, kurz.'],
    ];
    /** Schutz fürs Claude-Abo. */
    public const TON_PRO_TAG = 40;

    /**
     * Einen Ton anfordern. @return int|string Vorschlagsnummer oder Hinweis
     */
    public static function tonAnfordern(int $firmaId, string $kanal, string $ton, string $betreff, string $text, string $wer): int|string
    {
        if (!isset(self::TOENE[$ton])) { return 'Unbekannter Ton.'; }
        $kanal = $kanal === 'whatsapp' ? 'whatsapp' : 'email';
        $f = Db::one('SELECT id, land, sprache, name FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { return 'Betrieb nicht gefunden.'; }
        $betreff = mb_substr(trim(strip_tags($betreff)), 0, 300);
        $text = trim(str_replace("\r\n", "\n", strip_tags($text)));
        if (mb_strlen($text) < 20) { return 'Erst einen Text schreiben — umformulieren lässt sich nur, was da ist.'; }
        if (mb_strlen($text) > 6000) { return 'Der Text ist zu lang zum Umformulieren (höchstens 6000 Zeichen).'; }
        require_once __DIR__ . '/MkAuftrag.php';
        MkAuftrag::aufraeumen();
        $offen = Db::one("SELECT v.id FROM akq_textvorschlaege v JOIN mk_auftraege a ON a.id = v.auftrag_id
                           WHERE v.firma_id = ? AND v.kanal = ? AND a.status IN ('wartet','laeuft') LIMIT 1", [$firmaId, $kanal]);
        if ($offen) { return 'Für diesen Text wartet schon ein Vorschlag auf deinen PC.'; }
        if ((int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'ton' AND created_at >= CURDATE()", [], 0) >= self::TON_PRO_TAG) {
            return 'Heute schon ' . self::TON_PRO_TAG . ' Umformulierungen — das schont dein Claude-Abo. Morgen geht es weiter.';
        }
        return Db::transaktion(static function () use ($f, $firmaId, $kanal, $ton, $betreff, $text, $wer): int {
            $aid = (int) Db::insert('mk_auftraege', ['art' => 'ton', 'branche' => '', 'land' => strtoupper((string) $f['land']) === 'DE' ? 'DE' : 'IT',
                'parameter' => json_encode(['firma_id' => $firmaId, 'kanal' => $kanal, 'ton' => $ton], JSON_UNESCAPED_UNICODE)]);
            $vid = (int) Db::insert('akq_textvorschlaege', ['auftrag_id' => $aid, 'firma_id' => $firmaId, 'kanal' => $kanal, 'ton' => $ton,
                'betreff_vorher' => $kanal === 'email' ? $betreff : null, 'text_vorher' => $text, 'erstellt_von' => mb_substr($wer !== '' ? $wer : 'Verwaltung', 0, 80)]);
            Akquise::protokoll($firmaId, 'werkstatt', 'Umformulieren angefordert: „' . self::TOENE[$ton][0] . '“ (' . ($kanal === 'email' ? 'E-Mail' : 'WhatsApp') . ')');
            return $vid;
        });
    }

    /** Was der PC für einen Ton-Auftrag braucht — oder null, wenn der Vorschlag nicht mehr da ist. */
    public static function fuerPc(array $a): ?array
    {
        $v = Db::one('SELECT * FROM akq_textvorschlaege WHERE auftrag_id = ?', [(int) $a['id']]);
        if (!$v) { return null; }
        $f = (array) Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $v['firma_id']]);
        require_once __DIR__ . '/AkquiseText.php';
        $sprache = $f ? AkquiseText::spracheFuer($f) : 'it';
        [$zahlen, $links] = self::fakten((string) $v['betreff_vorher'] . "\n" . (string) $v['text_vorher']);
        return [
            'ton' => (string) $v['ton'], 'anweisung' => self::TOENE[(string) $v['ton']][1] ?? '', 'kanal' => (string) $v['kanal'],
            'sprache' => $sprache, 'betreff' => (string) ($v['betreff_vorher'] ?? ''), 'text' => (string) $v['text_vorher'],
            'zahlen' => $zahlen, 'links' => $links,
        ];
    }

    /** Zahlen (außerhalb von Links) und Links eines Textes. @return array{0:list<string>,1:list<string>} */
    public static function fakten(string $text): array
    {
        preg_match_all('~https?://[^\s<>"]+|\b[\w.-]+@[\w-]+\.[\w.]+\b|\b[\w-]+(?:\.[\w-]+)*\.(?:it|de|com|eu|net|org)\b(?:/[^\s<>"]*)?~iu', $text, $l);
        $links = array_values(array_unique(array_map(static fn($x) => rtrim(mb_strtolower($x), '.,;:)'), $l[0])));
        $ohne = (string) preg_replace('~https?://[^\s<>"]+|\b[\w.-]+@[\w-]+\.[\w.]+\b|\b[\w-]+(?:\.[\w-]+)*\.(?:it|de|com|eu|net|org)\b(?:/[^\s<>"]*)?~iu', ' ', $text);
        preg_match_all('~\d+(?:[.,]\d+)?~u', $ohne, $z);
        return [array_values(array_unique($z[0])), $links];
    }

    /**
     * Der PC liefert den umgeschriebenen Text. Abgelehnt wird, was etwas dazuerfindet:
     * eine Zahl, ein Link oder eine Adresse, die im Original nicht standen (Uwe: „Die KI darf niemals Informationen erfinden.“).
     */
    public static function tonMelden(array $d): array
    {
        $aid = (int) ($d['id'] ?? 0);
        $a = Db::one("SELECT * FROM mk_auftraege WHERE id = ? AND art = 'ton'", [$aid]);
        $v = $a ? Db::one('SELECT * FROM akq_textvorschlaege WHERE auftrag_id = ?', [$aid]) : null;
        if (!$a || !$v) { return ['ok' => false, 'hinweis' => 'Auftrag unbekannt.']; }
        if ($a['status'] !== 'laeuft') { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
        $betreff = mb_substr(trim(strip_tags((string) ($d['betreff'] ?? ''))), 0, 300);
        $text = trim(str_replace("\r\n", "\n", strip_tags((string) ($d['text'] ?? ''))));
        $grund = null;
        if (mb_strlen($text) < 20) { $grund = 'Claude hat keinen brauchbaren Text geliefert.'; }
        elseif ($v['kanal'] === 'email' && $betreff === '') { $grund = 'Claude hat den Betreff weggelassen.'; }
        else {
            [$zAlt, $lAlt] = self::fakten((string) $v['betreff_vorher'] . "\n" . (string) $v['text_vorher']);
            [$zNeu, $lNeu] = self::fakten($betreff . "\n" . $text);
            $neuZ = array_values(array_diff($zNeu, $zAlt));
            $neuL = array_values(array_diff($lNeu, $lAlt));
            if ($neuZ || $neuL) { $grund = 'Abgelehnt — Claude hat etwas dazugeschrieben, das im Original nicht stand: ' . implode(', ', array_merge($neuZ, $neuL)) . '.'; }
        }
        $jetzt = date('Y-m-d H:i:s');
        if ($grund !== null) {
            Db::update('akq_textvorschlaege', (int) $v['id'], ['status' => 'abgelehnt', 'grund' => mb_substr($grund, 0, 500), 'betreff' => $betreff ?: null, 'text' => $text ?: null, 'fertig_am' => $jetzt]);
            Db::update('mk_auftraege', $aid, ['status' => 'fehler', 'ergebnis' => mb_substr($grund, 0, 1000), 'fertig_am' => $jetzt]);
            return ['ok' => false, 'hinweis' => $grund];
        }
        /* Schon verworfen (jemand hat nicht gewartet)? Dann bleibt er verworfen — nichts taucht ungefragt wieder auf. */
        Db::update('akq_textvorschlaege', (int) $v['id'], ['status' => $v['status'] === 'verworfen' ? 'verworfen' : 'fertig', 'betreff' => $v['kanal'] === 'email' ? $betreff : null, 'text' => $text, 'fertig_am' => $jetzt]);
        Db::update('mk_auftraege', $aid, ['status' => 'fertig', 'ergebnis' => 'Vorschlag „' . (self::TOENE[(string) $v['ton']][0] ?? $v['ton']) . '“ liegt bereit.', 'fertig_am' => $jetzt]);
        return ['ok' => true];
    }

    /** Der jüngste Vorschlag für einen Text (für die Anzeige), samt Stand des Auftrags. */
    public static function vorschlag(int $firmaId, string $kanal): ?array
    {
        $v = Db::one("SELECT v.*, a.status AS auftrag, a.ergebnis, a.created_at AS angelegt FROM akq_textvorschlaege v JOIN mk_auftraege a ON a.id = v.auftrag_id
                       WHERE v.firma_id = ? AND v.kanal = ? AND v.created_at >= NOW() - INTERVAL 2 DAY
                       ORDER BY v.id DESC LIMIT 1", [$firmaId, $kanal === 'whatsapp' ? 'whatsapp' : 'email']);
        /* Nur der jüngste zählt: Wer ihn übernommen oder verworfen hat, sieht keinen älteren, gescheiterten wieder auftauchen. */
        if (!$v || in_array($v['status'], ['uebernommen', 'verworfen'], true)) { return null; }
        $stand = match (true) {
            $v['status'] === 'fertig' => 'fertig',
            $v['status'] === 'abgelehnt' || $v['auftrag'] === 'fehler' || $v['auftrag'] === 'abgebrochen' => 'fehler',
            $v['auftrag'] === 'laeuft' => 'laeuft',
            default => 'wartet',
        };
        return ['id' => (int) $v['id'], 'ton' => (string) $v['ton'], 'wort' => self::TOENE[(string) $v['ton']][0] ?? (string) $v['ton'], 'stand' => $stand,
                'betreff' => (string) ($v['betreff'] ?? ''), 'text' => (string) ($v['text'] ?? ''),
                'grund' => $stand === 'fehler' ? (string) ($v['grund'] ?: $v['ergebnis'] ?: 'Nicht geklappt.') : '', 'seit' => (string) $v['angelegt']];
    }

    /** Übernommen oder verworfen — nur zum Aufräumen und für den Verlauf; der Text selbst wird im Formular ersetzt. */
    public static function vorschlagSchliessen(int $id, bool $uebernommen): bool
    {
        $v = Db::one('SELECT * FROM akq_textvorschlaege WHERE id = ?', [$id]);
        if (!$v || in_array($v['status'], ['uebernommen', 'verworfen'], true)) { return false; }
        Db::update('akq_textvorschlaege', $id, ['status' => $uebernommen ? 'uebernommen' : 'verworfen']);
        Akquise::protokoll((int) $v['firma_id'], 'werkstatt', 'Vorschlag „' . (self::TOENE[(string) $v['ton']][0] ?? $v['ton']) . '“ ' . ($uebernommen ? 'übernommen' : 'verworfen'));
        if ($uebernommen) {   // Was Mitarbeit oder Partner annehmen, soll Uwe nachvollziehen können
            Events::pruefspur('akquise_ton_uebernommen', 'akq_firmen', (int) $v['firma_id'], ['text' => mb_substr((string) $v['text_vorher'], 0, 400)], ['text' => mb_substr((string) $v['text'], 0, 400)]);
        }
        return true;
    }
}
