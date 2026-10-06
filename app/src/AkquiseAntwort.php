<?php
declare(strict_types=1);

/**
 * Akquise-CRM Modul E (06.10.2026): Antworten, Einwände, Nachfassen, Wiedervorlage, Zusammenfassung.
 *
 * Uwe: Antwortvorschläge aus „festen Vorlagen + Ton“, Nachfassen als „Erinnerung + fertiger Entwurf“,
 * Wiedervorlage und Zusammenfassung „aus den Daten“.
 *
 * Grundsätze aus der CRM-Vorgabe, hier umgesetzt:
 *   - „Die KI darf niemals Informationen erfinden.“ — Die Vorlagen enthalten nur Vecom-Fakten
 *     (Preis vorher fest und öffentlich, Kostenvoranschlag gratis, kostenloser Check, Preisrechner)
 *     und Links. Keine Zahlen, keine Versprechen. Die Werkstatt prüft jeden Entwurf wie jeden anderen.
 *   - „Eine Antwort eines Betriebs darf NICHT automatisch als pauschale Werbeeinwilligung
 *     interpretiert werden.“ — Nichts hier ändert den Kommunikationsstatus. Antworten geht
 *     über dieselbe mailto-Strecke mit demselben Versandgrund-Schloss.
 *   - Gesendet wird nichts automatisch; „Nachfassen“ ist eine Erinnerung mit Entwurf.
 */
final class AkquiseAntwort
{
    /** Einwand → [Bezeichnung, Muster in Kleinbuchstaben (alle drei Sprachen)] — Reihenfolge = Vorrang. */
    public const EINWAENDE = [
        'teuer'        => ['Zu teuer / kein Budget', ['troppo car', 'costa troppo', 'costoso', 'non abbiamo budget', 'non ho budget', 'non abbiamo soldi', 'zu teuer', 'kein budget', 'kein geld', 'kostet zu viel', 'too expensive', 'no budget', 'can\'t afford']],
        'hat_website'  => ['Hat schon eine Website', ['abbiamo già un sito', 'ho già un sito', 'abbiamo gia un sito', 'già un sito', 'haben schon eine website', 'haben bereits eine website', 'schon eine homepage', 'haben eine homepage', 'already have a website', 'we have a website']],
        'macht_jemand' => ['Jemand kümmert sich schon', ['se ne occupa', 'mio nipote', 'mio figlio', 'un amico', 'abbiamo già un tecnico', 'agenzia', 'macht mein sohn', 'macht mein neffe', 'kümmert sich', 'haben eine agentur', 'haben jemanden', 'someone does it', 'have an agency']],
        'social_reicht'=> ['Facebook/Instagram reicht', ['basta facebook', 'abbiamo facebook', 'basta instagram', 'pagina facebook', 'reicht facebook', 'haben facebook', 'instagram reicht', 'facebook reicht', 'facebook is enough', 'instagram is enough']],
        'keine_zeit'   => ['Keine Zeit', ['non ho tempo', 'non abbiamo tempo', 'sono impegnat', 'siamo impegnat', 'keine zeit', 'viel zu tun', 'no time', 'too busy']],
        'spaeter'      => ['Später / nach der Saison', ['più avanti', 'piu avanti', 'dopo l\'estate', 'a settembre', 'l\'anno prossimo', 'magari dopo', 'ne riparliamo', 'später', 'nach der saison', 'nächstes jahr', 'im herbst', 'melde mich', 'later', 'next year', 'after the season']],
    ];

    /** Klasse der Antwort → Vorlage, wenn kein Einwand erkannt wurde. */
    private const KLASSE_VORLAGE = ['PRICE_REQUEST' => 'preis', 'CALL_REQUEST' => 'anruf', 'MORE_INFO' => 'info', 'INTERESTED' => 'interesse'];

    /** Gründe für eine Wiedervorlage (Uwe: „Wiedervorlage mit Gründen“). */
    public const WIEDERVORLAGE = [
        'ruft_zurueck' => 'Ruft selbst zurück', 'chef_weg' => 'Inhaber nicht erreichbar', 'saison' => 'Nach der Saison / nach dem Urlaub',
        'budget' => 'Budget später', 'angebot' => 'Angebot liegt vor — nachfragen', 'termin' => 'Termin vorbereiten', 'sonstiges' => 'Sonstiges',
    ];

    /** Nachfassen: zweite Nachricht ab Tag 3, dritte und letzte ab Tag 7 — gezählt ab der ersten, ohne Antwort dazwischen. */
    public const NACHFASSEN_TAGE = [1 => 3, 2 => 7];

    /** Vorlage → Sprache → [Betreff, Text]. Platzhalter: {anrede} {firma} {preise} {check} {bedarf}. */
    private const VORLAGEN = [
        'teuer' => [
            'it' => ['Il prezzo, prima e senza sorprese', "{anrede}\n\ngrazie per la risposta sincera. Il prezzo lo vede prima, nero su bianco, e non cambia: {preise}\n\nSpesso basta una pagina sola, fatta bene. Il preventivo è gratuito e senza impegno: mi dica cosa le serve davvero e le preparo una proposta adatta al vostro budget."],
            'de' => ['Der Preis — vorher und ohne Überraschung', "{anrede}\n\ndanke für die offene Antwort. Den Preis sehen Sie vorher, schwarz auf weiß, und er ändert sich nicht: {preise}\n\nOft reicht eine einzige, gut gemachte Seite. Der Kostenvoranschlag ist kostenlos und unverbindlich: Sagen Sie mir, was Sie wirklich brauchen, und ich mache Ihnen einen Vorschlag, der zu Ihrem Budget passt."],
            'en' => ['The price — up front, no surprises', "{anrede}\n\nthank you for the honest reply. You see the price up front, in writing, and it does not change: {preise}\n\nOften a single, well-made page is enough. The quote is free and without obligation: tell me what you really need and I will prepare a proposal that fits your budget."],
        ],
        'hat_website' => [
            'it' => ['Partiamo dal sito che avete', "{anrede}\n\nottimo, allora partiamo da lì. Con il check gratuito vede in pochi secondi cosa funziona già e cosa si può migliorare, soprattutto sul telefono: {check}\n\nNon serve rifare tutto: a volte bastano pochi interventi."],
            'de' => ['Wir fangen bei Ihrer Website an', "{anrede}\n\nsehr gut, dann fangen wir dort an. Mit dem kostenlosen Check sehen Sie in Sekunden, was schon gut läuft und was sich verbessern lässt — vor allem auf dem Handy: {check}\n\nEs muss nicht alles neu werden. Manchmal reichen wenige Änderungen."],
            'en' => ['Starting from the website you have', "{anrede}\n\ngreat, then let us start there. The free check shows you in seconds what already works and what could be improved, especially on mobile: {check}\n\nThere is no need to rebuild everything — sometimes a few changes are enough."],
        ],
        'macht_jemand' => [
            'it' => ['Un secondo parere, se le serve', "{anrede}\n\nbene che qualcuno se ne occupi già. Se un giorno vuole un secondo parere, il check gratuito resta a disposizione, anche da girare a chi segue il sito: {check}\n\nNessun impegno da parte sua."],
            'de' => ['Eine zweite Meinung, wenn Sie mögen', "{anrede}\n\nschön, dass sich schon jemand darum kümmert. Wenn Sie einmal eine zweite Meinung möchten, steht der kostenlose Check bereit — auch zum Weitergeben an die Person, die Ihre Website betreut: {check}\n\nFür Sie ganz unverbindlich."],
            'en' => ['A second opinion, if useful', "{anrede}\n\ngood to hear someone already takes care of it. If you ever want a second opinion, the free check is there — you can also pass it on to whoever looks after the site: {check}\n\nNo obligation at all."],
        ],
        'social_reicht' => [
            'it' => ['Facebook va bene — un sito è vostro', "{anrede}\n\ncapisco, molti clienti vi trovano già su Facebook o Instagram. Un sito proprio però vi appartiene: lo trova su Google anche chi non usa i social, e nessuna piattaforma lo può chiudere o cambiare. Qui vede cosa costerebbe, senza impegno: {bedarf}"],
            'de' => ['Facebook ist gut — eine Website gehört Ihnen', "{anrede}\n\nverstehe, viele Kunden finden Sie schon über Facebook oder Instagram. Eine eigene Website gehört aber Ihnen: Auf Google finden Sie auch Menschen ohne Social Media, und keine Plattform kann sie schließen oder ändern. Hier sehen Sie unverbindlich, was es kosten würde: {bedarf}"],
            'en' => ['Facebook is fine — a website is yours', "{anrede}\n\nI understand, many customers already find you on Facebook or Instagram. A website of your own belongs to you, though: people find it on Google even without social media, and no platform can close or change it. Here you can see what it would cost, without obligation: {bedarf}"],
        ],
        'keine_zeit' => [
            'it' => ['Le serve solo poco tempo', "{anrede}\n\nnessun problema, so che il lavoro viene prima. Da parte sua servono solo poche informazioni: il resto lo preparo io, e lei vede tutto prima di decidere. Quando ha un momento, il prezzo indicativo lo trova qui: {bedarf}"],
            'de' => ['Sie brauchen dafür kaum Zeit', "{anrede}\n\nkein Problem, die Arbeit geht vor. Von Ihnen brauche ich nur wenige Angaben — den Rest bereite ich vor, und Sie sehen alles, bevor Sie entscheiden. Wenn Sie einen Moment haben, finden Sie hier den ungefähren Preis: {bedarf}"],
            'en' => ['It takes very little of your time', "{anrede}\n\nno problem, work comes first. I only need a few details from you — I prepare the rest, and you see everything before you decide. When you have a moment, the indicative price is here: {bedarf}"],
        ],
        'spaeter' => [
            'it' => ['Ne riparliamo più avanti', "{anrede}\n\nva benissimo. Mi segno di riscriverle più avanti — se preferisce un momento preciso, me lo dica pure. Nel frattempo il check gratuito resta a disposizione: {check}"],
            'de' => ['Dann sprechen wir später', "{anrede}\n\ndas passt. Ich melde mich später wieder — wenn Ihnen ein bestimmter Zeitpunkt lieber ist, sagen Sie es mir gern. Bis dahin steht der kostenlose Check bereit: {check}"],
            'en' => ['Let us talk again later', "{anrede}\n\nthat is perfectly fine. I will write again later — if you prefer a specific time, just let me know. In the meantime the free check is available: {check}"],
        ],
        'preis' => [
            'it' => ['I prezzi, chiari e pubblici', "{anrede}\n\ngrazie per la domanda. I prezzi sono pubblici e fissi, li trova qui: {preise}\n\nPer il vostro caso il preventivo è gratuito; nel calcolatore vede subito la sua fascia di prezzo: {bedarf}\n\nSe preferisce, la chiamo e ne parliamo a voce."],
            'de' => ['Die Preise — offen und fest', "{anrede}\n\ndanke für Ihre Frage. Die Preise sind öffentlich und fest, Sie finden sie hier: {preise}\n\nFür Ihren Fall ist der Kostenvoranschlag kostenlos; im Preisrechner sehen Sie gleich Ihre Preisspanne: {bedarf}\n\nWenn Sie mögen, rufe ich Sie an und wir sprechen kurz darüber."],
            'en' => ['Prices — clear and public', "{anrede}\n\nthank you for asking. Our prices are public and fixed; you find them here: {preise}\n\nFor your case the quote is free; the price calculator shows your price range right away: {bedarf}\n\nIf you prefer, I can call you and we talk it through."],
        ],
        'anruf' => [
            'it' => ['La chiamo volentieri', "{anrede}\n\nvolentieri! Mi dica pure quando le fa comodo e a quale numero preferisce essere chiamato: mi adeguo ai suoi orari."],
            'de' => ['Ich rufe Sie gern an', "{anrede}\n\nsehr gern! Sagen Sie mir einfach, wann es Ihnen passt und unter welcher Nummer ich Sie am besten erreiche — ich richte mich nach Ihnen."],
            'en' => ['Happy to call you', "{anrede}\n\nwith pleasure! Just tell me when suits you and which number is best — I will fit around your schedule."],
        ],
        'info' => [
            'it' => ['Le informazioni che cercava', "{anrede}\n\ncon piacere. Qui trova il check gratuito del suo sito con i punti principali, spiegati in modo semplice: {check}\n\nE qui come lavoriamo e cosa costa: {preise}\n\nSe ha domande precise, risponda pure a questa e-mail."],
            'de' => ['Die Informationen, die Sie wollten', "{anrede}\n\ngern. Hier finden Sie den kostenlosen Check Ihrer Website mit den wichtigsten Punkten, einfach erklärt: {check}\n\nUnd hier, wie wir arbeiten und was es kostet: {preise}\n\nWenn Sie konkrete Fragen haben, antworten Sie einfach auf diese E-Mail."],
            'en' => ['The information you asked for', "{anrede}\n\nwith pleasure. Here is the free check of your website with the main points, explained simply: {check}\n\nAnd here is how we work and what it costs: {preise}\n\nIf you have specific questions, just reply to this e-mail."],
        ],
        'interesse' => [
            'it' => ['Il prossimo passo, semplice', "{anrede}\n\nche bello sentirla! Il passo più semplice: nel calcolatore vede subito la sua fascia di prezzo, senza impegno: {bedarf}\n\nOppure mi dica quando posso chiamarla e ne parliamo insieme."],
            'de' => ['Der nächste Schritt — ganz einfach', "{anrede}\n\nschön, von Ihnen zu hören! Der einfachste nächste Schritt: Im Preisrechner sehen Sie gleich Ihre Preisspanne, unverbindlich: {bedarf}\n\nOder sagen Sie mir, wann ich Sie anrufen darf, und wir besprechen es gemeinsam."],
            'en' => ['The next step, made simple', "{anrede}\n\ngreat to hear from you! The simplest next step: the price calculator shows your price range right away, without obligation: {bedarf}\n\nOr tell me when I may call you and we go through it together."],
        ],
        'allgemein' => [
            'it' => ['Grazie per la risposta', "{anrede}\n\ngrazie per la risposta. Mi dica pure come posso esserle utile: rispondo personalmente."],
            'de' => ['Danke für Ihre Antwort', "{anrede}\n\ndanke für Ihre Antwort. Sagen Sie mir gern, wie ich Ihnen helfen kann — ich antworte persönlich."],
            'en' => ['Thank you for your reply', "{anrede}\n\nthank you for your reply. Just let me know how I can help — I answer personally."],
        ],
        'nachfassen1' => [
            'it' => ['Ha avuto modo di dare un\'occhiata?', "{anrede}\n\nle avevo scritto qualche giorno fa a proposito del sito di {firma}. Ha avuto modo di dare un'occhiata? Se ha domande, risponda pure a questa e-mail.\n\nIl check gratuito: {check}"],
            'de' => ['Konnten Sie schon einen Blick darauf werfen?', "{anrede}\n\nich hatte Ihnen vor ein paar Tagen wegen der Website von {firma} geschrieben. Konnten Sie schon einen Blick darauf werfen? Bei Fragen antworten Sie einfach auf diese E-Mail.\n\nDer kostenlose Check: {check}"],
            'en' => ['Did you have a chance to take a look?', "{anrede}\n\nI wrote to you a few days ago about the website of {firma}. Did you have a chance to take a look? If you have questions, just reply to this e-mail.\n\nThe free check: {check}"],
        ],
        'nachfassen2' => [
            'it' => ['Un\'ultima domanda', "{anrede}\n\nnon voglio disturbarla oltre: è l'ultima volta che le scrivo su questo. Se un giorno vorrà un sito per {firma}, mi trova qui: {preise}\n\nBuon lavoro!"],
            'de' => ['Eine letzte Frage', "{anrede}\n\nich möchte Sie nicht weiter stören — das ist meine letzte Nachricht dazu. Wenn Sie irgendwann eine Website für {firma} möchten, finden Sie mich hier: {preise}\n\nWeiterhin gute Geschäfte!"],
            'en' => ['One last question', "{anrede}\n\nI do not want to bother you further — this is my last message about it. If one day you want a website for {firma}, you will find me here: {preise}\n\nAll the best!"],
        ],
    ];

    /** Woran man die Vorlage erkennt — für die Auswahl in der Oberfläche. */
    public const VORLAGEN_NAMEN = [
        'teuer' => 'Einwand: zu teuer', 'hat_website' => 'Einwand: hat schon eine Website', 'macht_jemand' => 'Einwand: jemand kümmert sich',
        'social_reicht' => 'Einwand: Facebook reicht', 'keine_zeit' => 'Einwand: keine Zeit', 'spaeter' => 'Einwand: später',
        'preis' => 'Preisfrage', 'anruf' => 'Möchte Anruf', 'info' => 'Möchte mehr wissen', 'interesse' => 'Interesse', 'allgemein' => 'Allgemein',
        'nachfassen1' => 'Nachfassen (Tag 3)', 'nachfassen2' => 'Nachfassen (Tag 7, letztes Mal)',
    ];

    /** @return list<string> erkannte Einwände (Schlüssel), stärkster zuerst */
    public static function einwaende(string $text): array
    {
        $t = ' ' . mb_strtolower($text) . ' ';
        $aus = [];
        foreach (self::EINWAENDE as $k => [, $muster]) {
            foreach ($muster as $m) { if (str_contains($t, $m)) { $aus[] = $k; break; } }
        }
        return $aus;
    }

    /** Die passende Vorlage für eine Antwort: erst der Einwand, dann die Klasse, sonst allgemein. */
    public static function vorlageFuer(array $antwort): string
    {
        $e = self::einwaende((string) ($antwort['betreff'] ?? '') . ' ' . (string) ($antwort['text'] ?? ''));
        return $e[0] ?? self::KLASSE_VORLAGE[(string) ($antwort['klasse'] ?? '')] ?? 'allgemein';
    }

    /**
     * Den Entwurf füllen. Nur Vecom-Fakten und Links; die Werkstatt prüft danach wie jeden Text.
     * @return array{betreff:string,text:string,sprache:string}
     */
    public static function entwurf(array $f, string $vorlage, ?string $sprache = null, ?array $antwort = null): array
    {
        require_once __DIR__ . '/AkquiseText.php';
        require_once __DIR__ . '/AkquiseFolge.php';
        $sp = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : AkquiseText::spracheFuer($f);
        [$betreff, $text] = self::VORLAGEN[$vorlage][$sp] ?? self::VORLAGEN['allgemein'][$sp];
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $links = [
            '{preise}' => $basis . ['it' => '/prezzi.html', 'de' => '/de/preise.html', 'en' => '/en/pricing.html'][$sp],
            '{check}'  => $basis . '/website-check.php' . ($sp !== 'it' ? '?lang=' . $sp : ''),
            '{bedarf}' => $basis . '/bedarf.php?lang=' . $sp,
            '{firma}'  => (string) ($f['name'] ?? ''),
            '{anrede}' => AkquiseFolge::anrede($f, $sp),
        ];
        $abs = AkquiseText::absender();
        $gruss = ['it' => 'Cordiali saluti', 'de' => 'Viele Grüße', 'en' => 'Kind regards'][$sp];
        $text = strtr($text, $links) . "\n\n" . $gruss . "\n" . $abs['inhaber'] . "\n" . $abs['firma'] . ' · ' . $abs['ort'] . (trim((string) $abs['telefon']) !== '' ? "\n" . $abs['telefon'] : '');
        /* Auf eine Antwort antwortet man im selben Faden. */
        $alt = trim((string) ($antwort['betreff'] ?? ''));
        if ($alt !== '' && !str_starts_with($vorlage, 'nachfassen')) { $betreff = preg_match('~^(re|aw|r)\s*:~iu', $alt) ? $alt : 'Re: ' . $alt; }
        return ['betreff' => mb_substr($betreff, 0, 200), 'text' => $text, 'sprache' => $sp];
    }

    /** Die offene (unerledigte) Antwort eines Betriebs, jüngste zuerst. */
    public static function offen(int $firmaId): ?array
    {
        return Db::one('SELECT * FROM akq_antworten WHERE firma_id = ? AND erledigt = 0 AND klasse NOT IN (\'OUT_OF_OFFICE\',\'INVALID_ADDRESS\') ORDER BY eingang_am DESC, id DESC LIMIT 1', [$firmaId]);
    }

    /** Eine Antwort als erledigt markieren (beantwortet oder nichts zu tun). */
    public static function erledigen(int $firmaId, int $antwortId, string $wie): bool
    {
        $n = Db::run('UPDATE akq_antworten SET erledigt = 1 WHERE id = ? AND firma_id = ? AND erledigt = 0', [$antwortId, $firmaId])->rowCount();
        if ($n > 0) {
            Akquise::protokoll($firmaId, 'antwort', 'Antwort ' . ($wie === 'beantwortet' ? 'beantwortet' : 'als erledigt markiert'), ['antwort' => $antwortId]);
            require_once __DIR__ . '/AkquisePrio.php';
            AkquisePrio::aktualisieren($firmaId);
        }
        return $n > 0;
    }

    /**
     * Nachfassen fällig? Eigene Nachrichten (E-Mail, auch die über das eigene Mailprogramm), danach keine Antwort.
     * Tag 3 nach der ersten → zweite Nachricht, Tag 7 nach der zweiten → dritte und letzte. Wer automatische
     * Folge-Mails bekommt (nach einer Zustimmung), fällt hier heraus — da kümmert sich AkquiseFolge.
     * @return array{schritt:int,seit:string,tage:int}|null
     */
    public static function nachfassen(array $f): ?array
    {
        $id = (int) $f['id'];
        if ((int) ($f['gesperrt'] ?? 0) === 1 || in_array((string) ($f['kontakt_status'] ?? ''), ['kunde', 'abgelehnt', 'gesperrt', 'geantwortet'], true)) { return null; }
        $raus = Db::all("SELECT created_at FROM akq_versand WHERE firma_id = ? AND kanal = 'email' AND status IN ('gesendet','von_hand') ORDER BY created_at", [$id]);
        $n = count($raus);
        if ($n < 1 || $n > 2) { return null; }
        $erste = (string) $raus[0]['created_at'];
        $letzte = (string) $raus[$n - 1]['created_at'];
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_antworten WHERE firma_id = ? AND eingang_am >= ?', [$id, $erste], 0) > 0) { return null; }
        try { if ((int) Db::wert("SELECT COUNT(*) FROM akq_folgen WHERE firma_id = ? AND status IN ('laeuft','pausiert')", [$id], 0) > 0) { return null; } }
        catch (Throwable $e) { }
        /* Tag 3 und Tag 7 zählen ab der ersten Nachricht; zwischen zwei Nachrichten liegen mindestens zwei Tage. */
        $tage = (int) floor((time() - strtotime($erste)) / 86400);
        $pause = (int) floor((time() - strtotime($letzte)) / 86400);
        return $tage >= self::NACHFASSEN_TAGE[$n] && $pause >= 2 ? ['schritt' => $n, 'seit' => $erste, 'tage' => $tage] : null;
    }

    /** Wiedervorlage mit Grund: schreibt den nächsten Schritt (eine Wahrheit mit Profil und „Heute“). */
    public static function wiedervorlage(int $firmaId, string $grund, string $notiz, ?string $datum, int $tage): array
    {
        if (!isset(self::WIEDERVORLAGE[$grund])) { return ['ok' => false, 'fehler' => 'Bitte einen Grund wählen.']; }
        $am = $datum !== null && preg_match('~^\d{4}-\d{2}-\d{2}$~', $datum) ? $datum : ($tage > 0 ? date('Y-m-d', strtotime('+' . min($tage, 365) . ' days')) : null);
        if ($am === null || $am < date('Y-m-d')) { return ['ok' => false, 'fehler' => 'Bitte ein Datum ab heute wählen.']; }
        $schritt = mb_substr('Wiedervorlage: ' . self::WIEDERVORLAGE[$grund] . (trim($notiz) !== '' ? ' — ' . trim(strip_tags($notiz)) : ''), 0, 160);
        Db::update('akq_firmen', $firmaId, ['naechster_am' => $am, 'naechster_schritt' => $schritt]);
        Akquise::protokoll($firmaId, 'wiedervorlage', $schritt . ' (am ' . date('d.m.Y', strtotime($am)) . ')');
        require_once __DIR__ . '/AkquisePrio.php';
        AkquisePrio::aktualisieren($firmaId);
        return ['ok' => true, 'am' => $am, 'schritt' => $schritt];
    }

    /**
     * Kurz zusammengefasst — nur aus dem, was in der Datenbank steht. Jede Zeile hat ihre Quelle in einer Tabelle.
     * @return list<string>
     */
    public static function zusammenfassung(array $f): array
    {
        $id = (int) $f['id'];
        $d = static fn(?string $t): string => $t ? date('d.m.Y', strtotime($t)) : '—';
        $z = [];
        require_once __DIR__ . '/AkquiseCrm.php';
        $z[] = 'Gefunden am ' . $d((string) ($f['recherchiert_am'] ?? $f['created_at'] ?? '')) . ' über ' . AkquiseCrm::quelle($f) . '.';
        $raus = Db::all("SELECT kanal, status, created_at FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand') ORDER BY created_at", [$id]);
        $hand = Db::all("SELECT kanal, benutzt_am FROM akq_kanaele WHERE firma_id = ? AND benutzt_am IS NOT NULL ORDER BY benutzt_am", [$id]);
        if ($raus) {
            $z[] = count($raus) === 1 ? 'Eine Nachricht am ' . $d($raus[0]['created_at']) . ' (' . $raus[0]['kanal'] . ').'
                : count($raus) . ' Nachrichten, erste am ' . $d($raus[0]['created_at']) . ', letzte am ' . $d($raus[count($raus) - 1]['created_at']) . '.';
        } elseif (!$hand) {
            $z[] = 'Keine eigene Nachricht im Verlauf.';
        }
        foreach ($hand as $h) { $z[] = 'Kontakt von Hand vermerkt (' . (AkquiseCrm::KANAELE[(string) $h['kanal']] ?? $h['kanal']) . ') am ' . $d($h['benutzt_am']) . '.'; }
        $antw = Db::all('SELECT klasse, betreff, text, eingang_am, erledigt FROM akq_antworten WHERE firma_id = ? ORDER BY eingang_am', [$id]);
        if ($antw) {
            $l = $antw[count($antw) - 1];
            require_once __DIR__ . '/AkquiseText.php';
            $e = self::einwaende((string) $l['betreff'] . ' ' . (string) $l['text']);
            $z[] = (count($antw) > 1 ? count($antw) . ' Antworten, letzte' : 'Antwort') . ' am ' . $d($l['eingang_am']) . ': ' . (AkquiseText::ANTWORT_KLASSEN[(string) $l['klasse']] ?? $l['klasse'])
                . ($e ? ' · Einwand: ' . implode(', ', array_map(static fn($k) => self::EINWAENDE[$k][0], $e)) : '') . ((int) $l['erledigt'] === 1 ? ' (erledigt).' : ' — noch offen.');
        }
        try {
            $t = Db::one("SELECT beginn, status FROM akq_termine WHERE firma_id = ? ORDER BY beginn DESC LIMIT 1", [$id]);
            if ($t) { $z[] = 'Termin am ' . $d((string) $t['beginn']) . ' (' . $t['status'] . ').'; }
        } catch (Throwable $e) { }
        if (trim((string) ($f['einwilligung'] ?? '')) !== '' || (int) ($f['email_send_allowed'] ?? 0) === 1) {
            $z[] = 'Versandgrund/Zustimmung dokumentiert.';
        } else {
            $z[] = 'Keine dokumentierte Kommunikationsfreigabe vorhanden.';
        }
        if (!empty($f['naechster_schritt']) || !empty($f['naechster_am'])) {
            $z[] = 'Nächster Schritt: ' . trim((string) ($f['naechster_schritt'] ?? '—')) . (!empty($f['naechster_am']) ? ' (am ' . $d((string) $f['naechster_am']) . ')' : '') . '.';
        }
        if (!empty($f['sperr_art'])) { $z[] = 'Gesperrt: ' . (AkquiseCrm::SPERR_ARTEN[(string) $f['sperr_art']] ?? $f['sperr_art']) . '.'; }
        return $z;
    }
}
