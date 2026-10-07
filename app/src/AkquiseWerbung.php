<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseMail.php';
require_once __DIR__ . '/AkquiseEinmal.php';

/**
 * WERBE-MAIL MIT BRANCHEN-FLYER AN BETRIEBE MIT ZUSTIMMUNG (07.10.2026, Uwe: „bei den betrieben wo zustimmung
 * erlaubt ist mache passend zum betrieb eine professionelle email mit branchen flyer und allem was nötig ist.
 * schlage vor ich sage ja oder nein“ — Flyer als PDF-Anhang, alle mit Zustimmung auf einmal).
 *
 *   vorschlagen()  je Betrieb mit dokumentierter Zustimmung ein Vorschlag in AI Freigaben: der Text aus dem
 *                  eigenen Audit (AkquiseText über regelVorlage), der Flyer seiner Branche in seiner Sprache.
 *                  Es geht nichts raus.
 *   einplanen()    Uwes Ja. Der Text wird freigegeben und kommt in den Plan; ist das Tageslimit frei,
 *                  geht er sofort, sonst mit dem nächsten Lauf.
 *   lauf()         der Cron (Regel akquise_folgen): schickt Geplantes, solange Tageslimit, Stundenlimit,
 *                  Pause und Not-Aus es zulassen — über den einen Weg AkquiseVersand::senden.
 *
 * Jeder Betrieb steht höchstens einmal im Plan (uq_werbung_firma): Das „einmalig“ hält die Datenbank.
 * Ein Nein gilt — wer abgelehnt wurde, wird nicht wieder vorgeschlagen.
 */
final class AkquiseWerbung
{
    public const ART = 'akquise_werbung';
    public const JE_KLICK = 50;
    public const JE_LAUF = 3;

    /** Akquise-Branche → Stichworte der mehrsprachigen Flyer (a-/b-/c-/d-…), das Passendste zuerst. */
    public const FLYER = [
        'restaurant' => ['restaurant', 'gastro'], 'bar_cafe' => ['gastro', 'restaurant'], 'baeckerei' => ['lebensmittel', 'gastro'],
        'hotel' => ['hotel', 'tourismus'], 'ferienwohnung' => ['hotel', 'tourismus'], 'agriturismo' => ['tourismus', 'landwirtschaft', 'hotel'],
        'tourismus' => ['tourismus', 'hotel'], 'handwerk' => ['handwerk'], 'bau' => ['handwerk'], 'immobilien' => ['immobilien'],
        'autohaus' => ['autohaus'], 'werkstatt' => ['autohaus', 'handwerk'], 'friseur' => ['beauty'], 'beauty' => ['beauty'],
        'fitness' => ['fitness'], 'einzelhandel' => ['mode', 'lebensmittel'], 'produzent' => ['lebensmittel', 'landwirtschaft'],
        'industrie' => ['industrie', 'logistik'], 'kanzlei' => ['kanzlei'], 'beratung' => ['steuerberater', 'kanzlei'], 'medizin' => ['arztpraxis'],
    ];

    private const PS = [
        'de' => 'PS: Im Anhang finden Sie unseren Flyer für Ihre Branche (PDF). Der QR-Code führt direkt zu Ihrer kostenlosen Analyse.',
        'it' => 'PS: In allegato trova il nostro volantino per il suo settore (PDF). Il codice QR porta direttamente alla sua analisi gratuita.',
        'en' => 'PS: Attached is our flyer for your industry (PDF). The QR code leads straight to your free analysis.',
    ];

    /** Der Flyer zu Branche und Sprache — null, wenn es keinen in dieser Sprache gibt (dann geht die Mail ohne). */
    public static function flyerFuer(array $f, string $sprache): ?string
    {
        require_once __DIR__ . '/PartnerFlyer.php';
        foreach (self::FLYER[(string) ($f['branche'] ?? '')] ?? [] as $wort) {
            $treffer = [];
            foreach (PartnerFlyer::liste() as $slug => $x) {
                if (preg_match('~^[a-d]-' . preg_quote($wort, '~') . '$~', (string) $slug) && in_array($sprache, (array) ($x['sp'] ?? []), true)) { $treffer[] = (string) $slug; }
            }
            if ($treffer) { sort($treffer); return $treffer[(int) $f['id'] % count($treffer)]; }
        }
        return null;
    }

    /** Wohin der QR-Code führt: derselbe Einstieg wie der Link in der Mail. */
    public static function ziel(string $sprache): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/zugang.php?lang=' . $sprache;
    }

    public static function flyerPdf(string $slug, string $sprache): string
    {
        require_once __DIR__ . '/PartnerFlyer.php';
        return PartnerFlyer::pdfMitZiel($slug, $sprache, self::ziel($sprache), (string) preg_replace('~^https?://~', '', rtrim((string) Config::get('website', 'https://vecom-design.it'), '/')));
    }

    public static function dateiname(string $slug, string $sprache): string
    {
        return 'vecom-design-' . preg_replace('~^[a-d]-~', '', $slug) . '-' . $sprache . '.pdf';
    }

    /** Text mit dem Flyer-PS — einmal, nicht bei jedem neuen Vorschlag noch einmal. */
    public static function mitPs(string $text, string $sprache): string
    {
        $ps = self::PS[$sprache] ?? self::PS['it'];
        return str_contains($text, $ps) ? $text : rtrim($text) . "\n\n" . $ps;
    }

    /** Betriebe, die schon einen Vorschlag haben (offen, entschieden, abgelehnt) oder im Plan stehen. */
    private static function schonVorgeschlagen(): array
    {
        $aus = [];
        foreach (Db::all('SELECT daten FROM ai_freigaben WHERE art = ?', [self::ART]) as $r) {
            $d = json_decode((string) $r['daten'], true);
            if (is_array($d) && isset($d['firma'])) { $aus[(int) $d['firma']] = true; }
        }
        foreach (Db::all('SELECT firma_id FROM akq_werbung_plan') as $r) { $aus[(int) $r['firma_id']] = true; }
        return $aus;
    }

    /**
     * @return array{vorgeschlagen:int, uebersprungen:list<array{id:int,name:string,grund:string}>, rest:int, ohne_flyer:int}
     */
    public static function vorschlagen(int $max = self::JE_KLICK): array
    {
        require_once __DIR__ . '/AkquiseVersand.php';
        require_once __DIR__ . '/AkquiseWerkstatt.php';
        require_once __DIR__ . '/AkquiseText.php';
        require_once __DIR__ . '/Freigabe.php';
        $l = AkquiseEinmal::lage(AkquiseGate::grenzen());
        $schon = self::schonVorgeschlagen();
        $reihe = [];
        foreach ($l['firmen'] as $f) {
            if (isset($schon[(int) $f['id']]) || Akquise::normEmail((string) $f['email']) === null) { continue; }
            $kann = AkquiseMail::kann($f);
            if (AkquiseEinmal::grund($f, $kann, $l) !== null) { continue; }
            if (!$kann['werbung'] && !AkquiseGate::einwilligungDeckt($f, 'email')) { continue; }   // nur mit Zustimmung
            $reihe[] = $f;
        }
        $neu = 0; $weg = []; $ohneFlyer = 0; $versucht = 0;
        foreach ($reihe as $f) {
            if ($neu >= $max) { break; }
            $versucht++;
            $id = (int) $f['id'];
            try {
                $vid = $l['texte'][$id] ?? AkquiseVersand::regelVorlage($id, null, 'email');
                $v = Db::one("SELECT * FROM akq_vorlagen WHERE id = ? AND firma_id = ? AND kanal = 'email'", [$vid, $id]);
                if (!$v || trim((string) $v['betreff']) === '') { throw new RuntimeException('Kein Text mit Betreff.'); }
                $sp = (string) ($v['sprache'] ?: AkquiseText::spracheFuer($f));
                $flyer = self::flyerFuer($f, $sp);
                $text = $flyer !== null ? self::mitPs((string) $v['text'], $sp) : (string) $v['text'];
                $liste = AkquiseWerkstatt::pruefliste($f, 'email', (string) $v['betreff'], $text, true);
                $stopp = array_column(array_filter($liste, static fn($x) => $x['stufe'] === AkquiseWerkstatt::STOPP), 'text');
                if ($stopp) { throw new RuntimeException('Prüfung: ' . implode(' ', $stopp)); }
                $hinweise = array_column(array_filter($liste, static fn($x) => $x['stufe'] === AkquiseWerkstatt::HINWEIS), 'text');
                /* Dieselbe Prüfung wie beim Freigeben eines Entwurfs — sonst sagte Uwe Ja zu einem Text, der dann hängen bliebe. */
                if ((string) $v['status'] === 'entwurf') {
                    $beanstandet = AkquiseText::pruefen((string) $v['betreff'], $text, $sp, $v['audit_id'] ? Akquise::befunde((int) $v['audit_id']) : [], 'email', $f);
                    if ($beanstandet) { throw new RuntimeException('Text hat Beanstandungen: ' . implode(' · ', $beanstandet)); }
                }
            } catch (Throwable $e) {
                $weg[] = ['id' => $id, 'name' => (string) $f['name'], 'grund' => mb_substr($e->getMessage(), 0, 200)];
                continue;
            }
            if ($flyer === null) { $ohneFlyer++; }
            require_once __DIR__ . '/PartnerFlyer.php';
            Freigabe::vorschlagen(self::ART, ['firma' => $id, 'vorlage' => (int) $v['id'], 'betreff' => (string) $v['betreff'], 'text' => $text, 'flyer' => (string) $flyer, 'sprache' => $sp], [
                'titel' => 'Werbe-Mail: ' . $f['name'] . (trim((string) ($f['stadt'] ?? '')) !== '' ? ' (' . $f['stadt'] . ')' : ''),
                'system' => 'Kunden finden', 'von' => 'Kunden finden',
                'grund' => 'Hat zugestimmt: ' . (trim((string) ($f['einwilligung'] ?? '')) !== '' ? (string) $f['einwilligung'] : (AkquiseMail::GRUENDE[(string) ($f['email_legal_basis'] ?? '')][0] ?? 'dokumentierter Versandgrund')) . '.',
                'ist' => 'An ' . Akquise::normEmail((string) $f['email']) . ' · ' . strtoupper($sp) . ' · ' . Akquise::branchenName((string) ($f['branche'] ?? '')),
                'soll' => $flyer !== null ? 'Die Mail geht über den Server raus, mit Flyer „' . PartnerFlyer::name($flyer, 'de') . '“ (' . strtoupper($sp) . ') als PDF-Anhang und Abmeldelink. Nach dem Tageslimit am nächsten Tag.'
                    : 'Die Mail geht über den Server raus, mit Abmeldelink — ohne Flyer: für diese Branche gibt es keinen in dieser Sprache.',
                'empfehlung' => $hinweise ? 'Hinweise der Prüfung: ' . implode(' · ', $hinweise) : null,
                'still' => true,
            ]);
            $neu++;
        }
        if ($neu > 0) {
            try {
                require_once __DIR__ . '/Events.php';
                Events::melden('ai_freigabe', $neu . ' Werbe-Mails mit Flyer warten auf dein Ja', 'hinweis', 'Kunden finden › Einmal an alle: Betriebe mit Zustimmung.', '/ai-freigaben');
            } catch (Throwable $e) { }
        }
        return ['vorgeschlagen' => $neu, 'uebersprungen' => $weg, 'rest' => max(0, count($reihe) - $versucht), 'ohne_flyer' => $ohneFlyer];
    }

    /** Wie viele Vorschläge warten, wie viele geplant und gesendet sind — für die Seite. */
    public static function stand(): array
    {
        $z = static fn(string $sql, array $w = []): int => (int) Db::wert($sql, $w, 0);
        return [
            'offen' => $z("SELECT COUNT(*) FROM ai_freigaben WHERE art = ? AND status IN ('offen','zurueckgestellt')", [self::ART]),
            'geplant' => $z("SELECT COUNT(*) FROM akq_werbung_plan WHERE status = 'geplant'"),
            'gesendet' => $z("SELECT COUNT(*) FROM akq_werbung_plan WHERE status IN ('gesendet','simuliert')"),
            'abgelehnt' => $z("SELECT COUNT(*) FROM ai_freigaben WHERE art = ? AND status = 'abgelehnt'", [self::ART]),
            'liegen' => $z("SELECT COUNT(*) FROM akq_werbung_plan WHERE status IN ('blockiert','fehler')"),
        ];
    }

    /**
     * Uwes Ja (aus Freigabe::ausfuehren). Mail::$ausloeser trägt dort schon die Freigabe — sie kommt in den Plan,
     * damit auch die später vom Cron verschickte Mail „nach Uwes Ja“ heißt.
     */
    public static function einplanen(array $d): string
    {
        require_once __DIR__ . '/AkquiseVersand.php';
        require_once __DIR__ . '/Mail.php';
        $fid = (int) $d['firma']; $vid = (int) $d['vorlage'];
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$fid]);
        $v = Db::one("SELECT * FROM akq_vorlagen WHERE id = ? AND firma_id = ? AND kanal = 'email'", [$vid, $fid]);
        if (!$f || !$v) { throw new RuntimeException('Betrieb oder Text gibt es nicht mehr.'); }
        $kann = AkquiseMail::kann($f);
        $grund = AkquiseEinmal::grund($f, $kann, AkquiseEinmal::lage(AkquiseGate::grenzen()));
        if ($grund !== null && $grund !== 'domain') { throw new RuntimeException('Nicht eingeplant: ' . AkquiseEinmal::GRUENDE[$grund] . '.'); }
        if (!$kann['werbung'] && !AkquiseGate::einwilligungDeckt($f, 'email')) { throw new RuntimeException('Nicht eingeplant: Die Zustimmung deckt keine Werbe-Mail (mehr).'); }
        if (!in_array((string) $v['status'], ['entwurf', 'freigegeben'], true)) { throw new RuntimeException('Der Text ist schon benutzt oder verworfen.'); }
        $betreff = trim(mb_substr((string) ($d['betreff'] ?? ''), 0, 200));
        $text = trim((string) ($d['text'] ?? ''));
        if ($betreff === '') { throw new RuntimeException('Ohne Betreff geht keine Mail raus.'); }
        if (mb_strlen($text) < 20) { throw new RuntimeException('Der Text ist zu kurz.'); }
        Db::update('akq_vorlagen', $vid, ['betreff' => $betreff, 'text' => $text]);   // so, wie Uwe ihn genehmigt hat
        if ((string) $v['status'] === 'entwurf') { AkquiseVersand::freigeben($vid); }
        $a = Mail::$ausloeser ?? [];
        try {
            Db::insert('akq_werbung_plan', [
                'firma_id' => $fid, 'vorlage_id' => $vid, 'freigabe_id' => isset($a['id']) ? (int) $a['id'] : null,
                'flyer' => trim((string) ($d['flyer'] ?? '')) !== '' ? (string) $d['flyer'] : null,
                'sprache' => (string) ($d['sprache'] ?? 'it'), 'wer' => isset($a['wer']) ? mb_substr((string) $a['wer'], 0, 80) : null,
            ]);
        } catch (Throwable $e) {
            if (Db::doppelt($e, 'uq_werbung_firma')) { throw new RuntimeException('Dieser Betrieb steht schon im Plan — er bekommt die Mail nur einmal.'); }
            throw $e;
        }
        Akquise::protokoll($fid, 'werbung', 'Werbe-Mail mit Flyer eingeplant (Ja in AI Freigaben' . (isset($a['wer']) ? ' von ' . $a['wer'] : '') . ')');
        $r = self::lauf(1, $fid);
        if ($r['gesendet'] > 0) { return 'Die Mail ist raus an ' . Akquise::normEmail((string) $f['email']) . '.'; }
        if ($r['simuliert'] > 0) { return 'Testbetrieb: Die Mail wurde nur simuliert.'; }
        return 'Eingeplant — sie geht raus, sobald Tageslimit und Pause es zulassen' . ($r['hinweis'] !== '' ? ' (' . $r['hinweis'] . ')' : '') . '.';
    }

    /**
     * Geplantes verschicken. Hält an, sobald eine Grenze greift — das Geplante bleibt geplant und kommt beim
     * nächsten Lauf dran. Was dauerhaft nicht geht (Widerspruch, Zustimmung weg), wird mit Grund abgelegt.
     *
     * @return array{gesendet:int, simuliert:int, abgelegt:int, wartet:int, hinweis:string}
     */
    public static function lauf(int $max = self::JE_LAUF, ?int $nurFirma = null): array
    {
        require_once __DIR__ . '/AkquiseVersand.php';
        require_once __DIR__ . '/Automation.php';
        require_once __DIR__ . '/Mail.php';
        $bilanz = ['gesendet' => 0, 'simuliert' => 0, 'abgelegt' => 0, 'wartet' => 0, 'hinweis' => ''];
        try { $frei = (int) Db::wert("SELECT GET_LOCK('vecom_akq_werbung', 0)", [], 1); } catch (Throwable $e) { $frei = 1; }
        if ($frei !== 1) { return ['hinweis' => 'Ein anderer Lauf ist noch dabei.'] + $bilanz; }
        try {
            $plan = Db::all("SELECT * FROM akq_werbung_plan WHERE status = 'geplant'" . ($nurFirma !== null ? ' AND firma_id = ' . (int) $nurFirma : '') . ' ORDER BY id LIMIT ' . max(1, $max * 3));
            foreach ($plan as $p) {
                if ($bilanz['gesendet'] + $bilanz['simuliert'] >= $max) { break; }
                if (Automation::ausgangGesperrt()) { $bilanz['hinweis'] = 'Not-Aus — nichts geht raus.'; break; }
                $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $p['firma_id']]);
                $kann = $f ? AkquiseMail::kann($f) : null;
                $grund = $f ? AkquiseEinmal::grund($f, $kann, ['schon' => [], 'bounce' => [], 'domain' => []]) : 'nicht';
                if ($grund === null && !$kann['werbung'] && !AkquiseGate::einwilligungDeckt($f, 'email')) { $grund = 'zustimmung'; }
                if ($grund !== null) {
                    Db::update('akq_werbung_plan', (int) $p['id'], ['status' => 'blockiert', 'grund' => mb_substr($grund === 'zustimmung' ? 'Zustimmung deckt keine Werbung mehr' : (AkquiseEinmal::GRUENDE[$grund] ?? $grund), 0, 255), 'erledigt_am' => date('Y-m-d H:i:s')]);
                    $bilanz['abgelegt']++;
                    continue;
                }
                /* Erst die Grenzen, die für alle gelten (ohne Domain): Greift eine, wartet der ganze Plan. Greift nur die
                   Domain-Frist, betrifft es diesen einen Betrieb — er wird abgelegt, damit er die Reihe nicht aufhält. */
                $sperre = AkquiseGate::versandSperre(['domain' => ''] + $f);
                if ($sperre !== null) { $bilanz['hinweis'] = $sperre; break; }
                $nurDomain = AkquiseGate::versandSperre($f);
                if ($nurDomain !== null) {
                    Db::update('akq_werbung_plan', (int) $p['id'], ['status' => 'blockiert', 'grund' => mb_substr($nurDomain, 0, 255), 'erledigt_am' => date('Y-m-d H:i:s')]);
                    $bilanz['abgelegt']++;
                    continue;
                }
                $anhaenge = [];
                if (!empty($p['flyer'])) {
                    $pdf = self::flyerPdf((string) $p['flyer'], (string) $p['sprache']);
                    if ($pdf !== '') { $anhaenge[] = ['name' => self::dateiname((string) $p['flyer'], (string) $p['sprache']), 'daten' => $pdf]; }
                }
                $vorher = Mail::$ausloeser;
                Mail::$ausloeser = ['art' => 'freigabe', 'ref' => self::ART, 'id' => $p['freigabe_id'] !== null ? (int) $p['freigabe_id'] : null, 'wer' => (string) ($p['wer'] ?? '')];
                try {
                    $versandId = AkquiseVersand::senden((int) $p['vorlage_id'], 'Geprüft und genehmigt in AI Freigaben' . ($p['wer'] ? ' von ' . $p['wer'] : ''), $anhaenge);
                    $sim = (string) Db::wert('SELECT status FROM akq_versand WHERE id = ?', [$versandId], '') === 'simuliert';
                    Db::update('akq_werbung_plan', (int) $p['id'], ['status' => $sim ? 'simuliert' : 'gesendet', 'versand_id' => $versandId, 'erledigt_am' => date('Y-m-d H:i:s')]);
                    $bilanz[$sim ? 'simuliert' : 'gesendet']++;
                } catch (Throwable $e) {
                    Db::update('akq_werbung_plan', (int) $p['id'], ['status' => 'fehler', 'grund' => mb_substr($e->getMessage(), 0, 255), 'erledigt_am' => date('Y-m-d H:i:s')]);
                    $bilanz['abgelegt']++;
                    $bilanz['hinweis'] = mb_substr($e->getMessage(), 0, 160);
                    if (!str_starts_with($e->getMessage(), 'Nicht verschickt:')) { break; }   // Brevo scheitert: erst nachsehen, nicht weiterfeuern
                } finally {
                    Mail::$ausloeser = $vorher;
                }
            }
            $bilanz['wartet'] = (int) Db::wert("SELECT COUNT(*) FROM akq_werbung_plan WHERE status = 'geplant'", [], 0);
        } finally {
            try { Db::wert("SELECT RELEASE_LOCK('vecom_akq_werbung')"); } catch (Throwable $e) { }
        }
        return $bilanz;
    }
}
