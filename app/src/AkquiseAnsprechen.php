<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';
require_once __DIR__ . '/AkquiseScore.php';

/**
 * Ansprechen von Hand (29.09.2026, Uwe: Ja zu K1–K3).
 *
 * Bei JEDEM Betrieb stehen fertige Texte bereit -- E-Mail, WhatsApp, Anruf,
 * Besuch -- in seiner Sprache, mit seinem Namen, der größten Schwäche und
 * dem Link zur Analyse (ohne Website: typischer Preis und Richtpreis).
 *
 * E-Mail und WhatsApp öffnen sich in Uwes eigenem Programm, er liest und
 * drückt selbst auf Senden. Frei sind sie nur, wenn das Gate sie erlaubt,
 * also nach einer Zustimmung. Eine Werbe-Mail ohne Zustimmung bleibt in
 * Italien (Art. 130 Codice Privacy) und Deutschland (§ 7 UWG) verboten --
 * auch von Hand. Der Weg für alle anderen: anrufen oder vorbeigehen, den
 * Satz vorlesen, „Hat zugestimmt“ (AkquiseEinwilligung::muendlich) -- dann
 * sind beide sofort frei und die Folge-Mails laufen automatisch.
 *
 * Keine Rechtsberatung.
 */
final class AkquiseAnsprechen
{
    /** Stand für Liste und Kopf -- ohne weitere Datenbankabfragen. @return array{farbe:string,wort:string,schreiben:bool} */
    public static function stand(array $f): array
    {
        if ((int) ($f['gesperrt'] ?? 0) === 1 || in_array((string) ($f['kontakt_status'] ?? ''), ['abgelehnt', 'gesperrt'], true)) {
            return ['farbe' => 'rot', 'wort' => 'Nicht ansprechen', 'schreiben' => false];
        }
        $mail = AkquiseGate::einwilligungDeckt($f, 'email');
        $wa = AkquiseGate::einwilligungDeckt($f, 'whatsapp');
        if ($mail || $wa || (int) ($f['bestandskunde'] ?? 0) === 1) {
            return ['farbe' => 'gruen', 'wort' => 'Darf ' . ($mail && $wa ? 'per Mail und WhatsApp' : ($wa ? 'per WhatsApp' : 'per Mail')), 'schreiben' => true];
        }
        if (in_array((string) ($f['kontakt_status'] ?? ''), ['kontaktiert', 'geantwortet'], true)) {
            return ['farbe' => 'gelb', 'wort' => 'Wartet auf Antwort', 'schreiben' => false];
        }
        return ['farbe' => 'grau', 'wort' => 'Erst anrufen oder besuchen', 'schreiben' => false];
    }

    /** Darf dieser Weg jetzt von Hand genutzt werden? Gate + Adresse. */
    public static function frei(array $f, string $kanal): bool
    {
        if ($kanal === 'email' && Akquise::normEmail((string) ($f['email'] ?? '')) === null) { return false; }
        if ($kanal === 'whatsapp' && strlen((string) preg_replace('~\D~', '', (string) ($f['whatsapp'] ?? ''))) < 8) { return false; }
        return in_array(AkquiseGate::pruefen($f, $kanal)['status'], [AkquiseGate::ERLAUBT, AkquiseGate::PRUEFEN], true);
    }

    /** Die Satzbausteine je Sprache. Anweisungen an Uwe stehen in [eckigen Klammern] und auf Deutsch. */
    private const W = [
        'it' => [
            'hallo' => 'Buongiorno', 'gruss' => 'Cordiali saluti',
            'betreff_a' => '{firma}: l’analisi del vostro sito, come d’accordo', 'betreff_o' => '{firma}: la proposta per il vostro sito, come d’accordo',
            'einstieg_a' => 'come d’accordo, le mando l’analisi del sito di {firma}:', 'einstieg_o' => 'come d’accordo, le scrivo due righe per {firma}.',
            'einstieg_x' => 'come d’accordo, le mando due o tre spunti per il sito di {firma}.',
            'gefunden' => 'Cosa abbiamo notato:',
            'ohne_web' => 'Oggi chi cerca {branche} {in} su Google trova soprattutto chi ha un sito. Un sito tipico per {mz} parte da {preis}; il prezzo indicativo esatto lo vede in 90 secondi, senza impegno:',
            'ohne_web_x' => 'Oggi chi cerca {branche} {in} su Google trova soprattutto chi ha un sito. Il prezzo indicativo per il suo sito lo vede in 90 secondi, senza impegno:',
            'richt_web' => 'Il prezzo indicativo per un sito nuovo lo vede in 90 secondi, senza impegno:',
            'bereich' => 'Nel suo spazio personale su Vecom Design trova tutto in un posto: analisi, prezzo indicativo e i prossimi passi.',
            'fragen' => 'Per qualsiasi domanda risponda pure a questa e-mail{tel}.', 'tel' => ' o mi chiami al {telefon}',
            'stopp' => 'Se non desidera altri messaggi, risponda semplicemente «STOP».',
            'wa_hallo' => 'Buongiorno, sono {inhaber} di {absender} – come d’accordo le scrivo qui.',
            'wa_analyse' => 'Ecco l’analisi del sito di {firma}: {link}', 'wa_ohne' => 'Il prezzo indicativo per il sito di {firma} lo vede in 90 secondi: {link}',
            'wa_punkt' => 'La cosa più importante: {punkt}',
            'wa_stopp' => 'Se non desidera più messaggi, risponda semplicemente «STOP».',
            'a_hallo' => 'Buongiorno, sono {inhaber} di {absender}, web designer qui in provincia di Agrigento. Parlo con il titolare di {firma}?',
            'a_grund_web' => 'Ho guardato il vostro sito {domain} e ho notato una cosa: {punkt}',
            'a_grund_web_x' => 'Ho guardato il vostro sito {domain} e ho due o tre spunti concreti per migliorarlo.',
            'a_grund_ohne' => 'Ho visto che {firma} non ha ancora un sito proprio – chi cerca {branche} {in} su Google trova soprattutto gli altri.',
            'a_frage' => 'Posso mandarle un’analisi gratuita e senza impegno, con queste proposte e un prezzo indicativo? Non deve decidere niente adesso.',
            'a_problem2' => 'E ancora: {punkt}',
            'a_loesung' => 'La nostra proposta: {loesung}. Senza impegno — prima vede tutto e poi decide lei.',
            'a_loesung_web_x' => 'La nostra proposta: un sito veloce sul telefono, facile da trovare su Google e con un tocco per chiamare o scrivere. Senza impegno — prima vede tutto e poi decide lei.',
            'a_loesung_ohne' => 'La nostra proposta: un sito semplice e curato per {firma}, che si trova su Google, mostra orari, foto e mappa, e con un tocco la chiamano o le scrivono. {preis_satz} Senza impegno — prima vede tutto e poi decide lei.',
            'preis_satz' => 'Per {mz} parte di solito da {preis}.',
            'a_email' => 'Perfetto, grazie! A quale indirizzo e-mail le mando l’analisi? Me lo detta lettera per lettera?',
            'a_wa' => 'E se preferisce, gliela mando anche su WhatsApp: è questo il numero giusto?',
            'a_nein' => 'Nessun problema, grazie e buona giornata.',
            'p_hallo' => 'Buongiorno, sono {sprecher} e collaboro con {absender}, web design in provincia di Agrigento. Parlo con il titolare di {firma}?',
            'b_hallo' => 'Buongiorno, sono {inhaber} di {absender}, web designer qui in zona. Posso rubarle un minuto?',
            'b_frage' => 'Le lascio volentieri il nostro volantino con il codice QR: lì vede l’analisi e il prezzo indicativo, gratis e senza impegno. Oppure gliela mando per e-mail?',
        ],
        'de' => [
            'hallo' => 'Guten Tag', 'gruss' => 'Viele Grüße',
            'betreff_a' => '{firma}: die Analyse Ihrer Website, wie besprochen', 'betreff_o' => '{firma}: der Vorschlag für Ihre Website, wie besprochen',
            'einstieg_a' => 'wie besprochen schicke ich Ihnen die Analyse der Website von {firma}:', 'einstieg_o' => 'wie besprochen ein paar Zeilen für {firma}.',
            'einstieg_x' => 'wie besprochen schicke ich Ihnen zwei, drei Hinweise zur Website von {firma}.',
            'gefunden' => 'Was uns aufgefallen ist:',
            'ohne_web' => 'Wer heute {branche} {in} bei Google sucht, findet vor allem Betriebe mit Website. Eine typische Website für {mz} beginnt bei {preis}; Ihren genauen Richtpreis sehen Sie in 90 Sekunden, unverbindlich:',
            'ohne_web_x' => 'Wer heute {branche} {in} bei Google sucht, findet vor allem Betriebe mit Website. Den Richtpreis für Ihre Website sehen Sie in 90 Sekunden, unverbindlich:',
            'richt_web' => 'Den Richtpreis für eine neue Website sehen Sie in 90 Sekunden, unverbindlich:',
            'bereich' => 'In Ihrem persönlichen Bereich bei Vecom Design finden Sie alles an einem Ort: Analyse, Richtpreis und die nächsten Schritte.',
            'fragen' => 'Bei Fragen antworten Sie einfach auf diese Mail{tel}.', 'tel' => ' oder rufen Sie mich an: {telefon}',
            'stopp' => 'Wenn Sie keine weiteren Nachrichten möchten, antworten Sie einfach „STOPP“.',
            'wa_hallo' => 'Guten Tag, hier ist {inhaber} von {absender} – wie besprochen schreibe ich Ihnen hier.',
            'wa_analyse' => 'Hier ist die Analyse der Website von {firma}: {link}', 'wa_ohne' => 'Den Richtpreis für die Website von {firma} sehen Sie in 90 Sekunden: {link}',
            'wa_punkt' => 'Das Wichtigste: {punkt}',
            'wa_stopp' => 'Wenn Sie keine Nachrichten mehr möchten, antworten Sie einfach „STOPP“.',
            'a_hallo' => 'Guten Tag, hier ist {inhaber} von {absender}, Webdesign. Spreche ich mit dem Inhaber von {firma}?',
            'a_grund_web' => 'Ich habe mir Ihre Website {domain} angesehen, und mir ist etwas aufgefallen: {punkt}',
            'a_grund_web_x' => 'Ich habe mir Ihre Website {domain} angesehen und habe zwei, drei konkrete Ideen, wie sie mehr bringt.',
            'a_grund_ohne' => 'Ich habe gesehen, dass {firma} noch keine eigene Website hat – wer {branche} {in} bei Google sucht, findet vor allem die anderen.',
            'a_frage' => 'Darf ich Ihnen eine kostenlose und unverbindliche Analyse mit diesen Vorschlägen und einem Richtpreis schicken? Sie müssen jetzt nichts entscheiden.',
            'a_problem2' => 'Außerdem: {punkt}',
            'a_loesung' => 'Unser Vorschlag: {loesung}. Ganz unverbindlich — Sie sehen erst alles und entscheiden dann selbst.',
            'a_loesung_web_x' => 'Unser Vorschlag: eine Website, die am Handy schnell lädt, bei Google gut gefunden wird und mit einem Tipp anrufen oder schreiben lässt. Ganz unverbindlich — Sie sehen erst alles und entscheiden dann selbst.',
            'a_loesung_ohne' => 'Unser Vorschlag: eine schlichte, gepflegte Website für {firma}, die bei Google gefunden wird, Öffnungszeiten, Fotos und Karte zeigt und mit einem Tipp anrufen oder schreiben lässt. {preis_satz} Ganz unverbindlich — Sie sehen erst alles und entscheiden dann selbst.',
            'preis_satz' => 'Für {mz} beginnt das meist bei {preis}.',
            'a_email' => 'Danke! An welche E-Mail-Adresse darf ich Ihnen die Analyse schicken? Buchstabieren Sie sie mir bitte kurz?',
            'a_wa' => 'Gern auch zusätzlich per WhatsApp: Ist das die richtige Nummer?',
            'a_nein' => 'Kein Problem, vielen Dank und einen schönen Tag.',
            'p_hallo' => 'Guten Tag, hier ist {sprecher}, ich arbeite mit {absender} zusammen, Webdesign. Spreche ich mit dem Inhaber von {firma}?',
            'b_hallo' => 'Guten Tag, ich bin {inhaber} von {absender}, Webdesign hier aus der Gegend. Haben Sie eine Minute?',
            'b_frage' => 'Ich lasse Ihnen gern unseren Flyer mit QR-Code da: Darüber sehen Sie die Analyse und den Richtpreis, kostenlos und unverbindlich. Oder soll ich sie Ihnen per E-Mail schicken?',
        ],
        'en' => [
            'hallo' => 'Hello', 'gruss' => 'Kind regards',
            'betreff_a' => '{firma}: your website analysis, as discussed', 'betreff_o' => '{firma}: the proposal for your website, as discussed',
            'einstieg_a' => 'as discussed, here is the website analysis for {firma}:', 'einstieg_o' => 'as discussed, a few lines for {firma}.',
            'einstieg_x' => 'as discussed, here are two or three pointers for the {firma} website.',
            'gefunden' => 'What we noticed:',
            'ohne_web' => 'People searching for {branche} {in} on Google mostly find businesses with a website. A typical website for {mz} starts at {preis}; you’ll see your exact guide price in 90 seconds, no obligation:',
            'ohne_web_x' => 'People searching for {branche} {in} on Google mostly find businesses with a website. You’ll see the guide price for your website in 90 seconds, no obligation:',
            'richt_web' => 'You’ll see the guide price for a new website in 90 seconds, no obligation:',
            'bereich' => 'Your personal area at Vecom Design keeps everything in one place: analysis, guide price and next steps.',
            'fragen' => 'If you have questions, just reply to this email{tel}.', 'tel' => ' or call me on {telefon}',
            'stopp' => 'If you’d rather not receive further messages, just reply “STOP”.',
            'wa_hallo' => 'Hello, this is {inhaber} from {absender} – as discussed, I’m writing to you here.',
            'wa_analyse' => 'Here is the website analysis for {firma}: {link}', 'wa_ohne' => 'You’ll see the guide price for the {firma} website in 90 seconds: {link}',
            'wa_punkt' => 'The most important point: {punkt}',
            'wa_stopp' => 'If you’d rather not get messages, just reply “STOP”.',
            'a_hallo' => 'Hello, this is {inhaber} from {absender}, web design. Am I speaking with the owner of {firma}?',
            'a_grund_web' => 'I had a look at your website {domain} and noticed something: {punkt}',
            'a_grund_web_x' => 'I had a look at your website {domain} and have two or three concrete ideas to make it work harder.',
            'a_grund_ohne' => 'I noticed that {firma} doesn’t have its own website yet – people searching for {branche} {in} on Google mostly find the others.',
            'a_frage' => 'May I send you a free, no-obligation analysis with these suggestions and a guide price? You don’t need to decide anything now.',
            'a_problem2' => 'Also: {punkt}',
            'a_loesung' => 'Our suggestion: {loesung}. No obligation — you see everything first and then decide.',
            'a_loesung_web_x' => 'Our suggestion: a website that loads fast on phones, is easy to find on Google and lets people call or write with one tap. No obligation — you see everything first and then decide.',
            'a_loesung_ohne' => 'Our suggestion: a simple, well-made website for {firma} that shows up on Google, shows opening hours, photos and a map, and lets people call or write with one tap. {preis_satz} No obligation — you see everything first and then decide.',
            'preis_satz' => 'For {mz} it usually starts at {preis}.',
            'a_email' => 'Thank you! Which email address should I send the analysis to? Could you spell it for me?',
            'a_wa' => 'Happy to send it on WhatsApp too: is this the right number?',
            'a_nein' => 'No problem at all, thank you and have a nice day.',
            'p_hallo' => 'Hello, this is {sprecher}, I work with {absender}, web design. Am I speaking with the owner of {firma}?',
            'b_hallo' => 'Hello, I’m {inhaber} from {absender}, a local web designer. Do you have a minute?',
            'b_frage' => 'I’m happy to leave our flyer with the QR code: it shows the analysis and the guide price, free and without obligation. Or shall I send it by email?',
        ],
    ];

    /** Die Frage, die am Telefon oder im Laden vorgelesen wird -- sein Ja darauf ist die Zustimmung (gespeichert als Wortlaut). */
    public const WORTLAUT = [
        'it' => 'Va bene se {absender} ({inhaber}) le invia per e-mail e/o su WhatsApp messaggi sul suo sito e su offerte adatte? Può revocare il consenso in qualsiasi momento con un semplice «STOP».',
        'de' => 'Ist es in Ordnung, wenn {absender} ({inhaber}) Ihnen per E-Mail und/oder WhatsApp Nachrichten zu Ihrer Website und zu passenden Angeboten schickt? Sie können das jederzeit mit einem einfachen „STOPP“ widerrufen.',
        'en' => 'Is it all right if {absender} ({inhaber}) sends you messages about your website and suitable offers by email and/or WhatsApp? You can withdraw this at any time with a simple “STOP”.',
    ];
    /* Deutsche Betriebe nur per E-Mail (Uwe, 29.09.2026) -- die Frage nennt dann auch nur die E-Mail. */
    public const WORTLAUT_MAIL = [
        'it' => 'Va bene se {absender} ({inhaber}) le invia per e-mail messaggi sul suo sito e su offerte adatte? Può revocare il consenso in qualsiasi momento con un semplice «STOP».',
        'de' => 'Ist es in Ordnung, wenn {absender} ({inhaber}) Ihnen per E-Mail Nachrichten zu Ihrer Website und zu passenden Angeboten schickt? Sie können das jederzeit mit einem einfachen „STOPP“ widerrufen.',
        'en' => 'Is it all right if {absender} ({inhaber}) sends you messages about your website and suitable offers by email? You can withdraw this at any time with a simple “STOP”.',
    ];
    public const WORTLAUT_VERSION = 'v3m-2909';

    public static function wortlaut(string $sprache, bool $nurMail = false): string
    {
        $abs = AkquiseText::absender();
        $v = $nurMail ? self::WORTLAUT_MAIL : self::WORTLAUT;
        return strtr($v[$sprache] ?? $v['it'], ['{absender}' => $abs['firma'], '{inhaber}' => $abs['inhaber']]);
    }

    /** Deutsche Betriebe: nur E-Mail. */
    public static function nurMail(array $f): bool
    {
        return strtoupper((string) ($f['land'] ?? '')) === 'DE';
    }

    /**
     * Alle fertigen Texte für einen Betrieb.
     *
     * @param list<array<string,mixed>> $befunde
     * @return array{sprache:string,email:array{betreff:string,text:string,link:?string},whatsapp:array{text:string,link:?string},
     *               anruf:list<array{0:string,1:string}>,besuch:list<array{0:string,1:string}>,wortlaut:string,frei:array{email:bool,whatsapp:bool},tel:?string}
     */
    public static function paket(array $f, array $befunde, ?string $sprache = null, string $analyse = '', ?string $sprecher = null): array
    {
        $sp = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : AkquiseText::spracheFuer($f);
        $W = self::W[$sp];
        $abs = AkquiseText::absender();
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $richt = $basis . '/bedarf.php?lang=' . $sp;
        $hatWeb = trim((string) ($f['url'] ?? '')) !== '';
        $firma = (string) $f['name'];

        $stadt = trim((string) ($f['stadt'] ?? ''));
        require_once __DIR__ . '/BranchenStatistik.php';
        $in = $stadt !== '' ? BranchenStatistik::in(mb_convert_case(mb_strtolower($stadt), MB_CASE_TITLE), $sp) : '';
        $mz = BranchenStatistik::mehrzahl((string) ($f['branche'] ?? ''), $sp);
        $branche = $mz;   // „chi cerca bar e caffè a Favara“ statt „bar / caffè“
        $preis = '';
        if (!$hatWeb && isset(BranchenStatistik::TYPISCH[(string) ($f['branche'] ?? '')])) {
            try { require_once __DIR__ . '/Baukasten.php'; $preis = Baukasten::geldText(BranchenStatistik::typischerPreis((string) $f['branche']), $sp); } catch (Throwable $e) { $preis = ''; }
        }
        /* Die Punkte: nur geprüfte Befunde mit gepflegtem Satz (dieselben wie auf der Analyse-Seite). */
        $punkte = []; $wirkungen = []; $loesungen = [];
        foreach (AkquiseScore::topBefunde($befunde) as $b) {
            if (($b['status'] ?? '') !== 'VERIFIED') { continue; }
            $x = AkquiseText::saetze($b, $f, $sp);
            if ($x !== null) { $punkte[] = $x[0]; $wirkungen[] = $x[1]; $loesungen[] = $x[2]; }
            if (count($punkte) >= 2) { break; }
        }
        $r = ['{firma}' => $firma, '{inhaber}' => $abs['inhaber'], '{absender}' => $abs['firma'], '{branche}' => $branche, '{in}' => $in,
              '{mz}' => $mz, '{preis}' => $preis, '{domain}' => (string) ($f['domain'] ?? ''), '{telefon}' => (string) $abs['telefon']];
        $t = static fn(string $k, array $mehr = []): string => trim((string) preg_replace(['~\s+([.,:;])~u', '~ {2,}~u'], ['$1', ' '], strtr($W[$k], $mehr + $r)));
        $anrede = trim((string) ($f['ansprechpartner'] ?? ''));

        /* ---- E-Mail ---- */
        $z = [$W['hallo'] . ($anrede !== '' ? ' ' . $anrede : '') . ',', ''];
        if ($analyse !== '') { $z[] = $t('einstieg_a'); $z[] = $analyse; }
        elseif ($hatWeb) { $z[] = $t('einstieg_x'); }
        else { $z[] = $t('einstieg_o'); }
        if ($punkte) { $z[] = ''; $z[] = $W['gefunden']; foreach ($punkte as $p) { $z[] = '– ' . $p; } }
        if (!$hatWeb) { $z[] = ''; $z[] = $t($preis !== '' ? 'ohne_web' : 'ohne_web_x'); $z[] = $richt; }
        elseif ($analyse === '') { $z[] = ''; $z[] = $t('richt_web'); $z[] = $richt; }
        $z[] = ''; $z[] = $W['bereich'];
        $z[] = $t('fragen', ['{tel}' => $abs['telefon'] !== '' ? $t('tel') : '']);
        $z[] = ''; $z[] = $W['gruss']; $z[] = $abs['inhaber']; $z[] = $abs['firma'] . ' · ' . $abs['ort']; $z[] = $basis;
        $z[] = ''; $z[] = $W['stopp'];
        $betreff = $t($analyse !== '' || $hatWeb ? 'betreff_a' : 'betreff_o');
        $mailText = implode("\n", array_values(array_filter($z, static fn($x) => $x !== null)));
        $mail = Akquise::normEmail((string) ($f['email'] ?? ''));

        /* ---- WhatsApp ---- */
        $wz = [$t('wa_hallo'), ''];
        $wz[] = $analyse !== '' ? $t('wa_analyse', ['{link}' => $analyse]) : $t('wa_ohne', ['{link}' => $richt]);
        if ($punkte) { $wz[] = ''; $wz[] = $t('wa_punkt', ['{punkt}' => $punkte[0]]); }
        $wz[] = ''; $wz[] = $W['wa_stopp'];
        $waText = implode("\n", $wz);
        $waNr = (string) preg_replace('~\D~', '', (string) ($f['whatsapp'] ?? ''));

        /* ---- Anruf und Besuch: Gesprächsleitfaden in seiner Sprache, Regieanweisungen auf Deutsch ---- */
        /* Problem und Lösung passend zum Betrieb (29.09.2026, Uwe): aus der Fehler-Analyse
           (geprüfte Befunde: was wir gesehen haben, was es bedeutet, was wir vorschlagen)
           oder -- ohne Website -- warum ihm Kunden entgehen und was wir bauen. Immer unverbindlich. */
        if (!$hatWeb) {
            $problem = $t('a_grund_ohne');
            $loesung = $t('a_loesung_ohne', ['{preis_satz}' => $preis !== '' ? $t('preis_satz') : '']);
        } elseif ($punkte) {
            $problem = $t('a_grund_web', ['{punkt}' => $punkte[0]]) . ' ' . $wirkungen[0] . (isset($punkte[1]) ? ' ' . $t('a_problem2', ['{punkt}' => $punkte[1]]) : '');
            $loesung = $t('a_loesung', ['{loesung}' => implode($sp === 'en' ? ' and ' : ($sp === 'de' ? ' und ' : ' e '), array_values(array_unique($loesungen)))]);
        } else {
            $problem = $t('a_grund_web_x');
            $loesung = $t('a_loesung_web_x');
        }
        $grund = $problem;
        $satz = self::wortlaut($sp, self::nurMail($f));
        $email = $t('a_email') . (self::nurMail($f) ? '' : ' ' . $t('a_wa'));
        $hallo = $sprecher !== null && trim($sprecher) !== '' ? $t('p_hallo', ['{sprecher}' => trim($sprecher)]) : $t('a_hallo');
        $anruf = [['Begrüßen', $hallo], ['Problem', $problem], ['Lösung', $loesung], ['Frage (unverbindlich)', $t('a_frage')],
                  ['Wenn ja: diese Frage vorlesen', $satz], ['Dann E-Mail erfragen und eintragen', $email],
                  ['Wenn nein', $t('a_nein') . ' → unten „Kein Interesse“ antippen: Der Betrieb wird nie mehr angesprochen.']];
        $besuch = [['Begrüßen', $t('b_hallo')], ['Problem', $problem], ['Lösung', $loesung], ['Frage (unverbindlich)', $t('b_frage')],
                   ['Wenn ja: diese Frage vorlesen oder zeigen', $satz], ['Dann E-Mail erfragen und eintragen', $email . ' — oder „Vor Ort zeigen“: Er tippt sie selbst ein.'],
                   ['Wenn nein', $t('a_nein')]];

        $frei = ['email' => self::frei($f, 'email'), 'whatsapp' => self::frei($f, 'whatsapp')];
        $tel = (string) preg_replace('~[^\d+]~', '', (string) ($f['telefon'] ?? ''));
        return [
            'sprache' => $sp,
            'email' => ['betreff' => $betreff, 'text' => $mailText,
                        'link' => $frei['email'] && $mail !== null ? 'mailto:' . rawurlencode($mail) . '?subject=' . rawurlencode($betreff) . '&body=' . rawurlencode($mailText) : null],
            'whatsapp' => ['text' => $waText, 'link' => $frei['whatsapp'] && strlen($waNr) >= 8 ? 'https://wa.me/' . $waNr . '?text=' . rawurlencode($waText) : null],
            'anruf' => $anruf, 'besuch' => $besuch, 'wortlaut' => $satz, 'frei' => $frei,
            'saetze' => ['hallo' => $hallo, 'problem' => $problem, 'loesung' => $loesung, 'frage' => $t('a_frage'), 'ja' => $satz, 'email' => $email, 'nein' => $t('a_nein')],
            'tel' => strlen((string) preg_replace('~\D~', '', $tel)) >= 6 ? 'tel:' . $tel : null,
        ];
    }

    /**
     * Uwe hat E-Mail oder WhatsApp aus seinem Programm geöffnet: als Kontakt
     * vermerken (einmal je Weg in 30 Minuten). Nur wenn der Weg frei ist.
     */
    public static function vermerken(int $firmaId, string $kanal): bool
    {
        if (!in_array($kanal, ['email', 'whatsapp'], true)) { return false; }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f || !self::frei($f, $kanal)) { return false; }
        if (Db::wert("SELECT id FROM akq_versand WHERE firma_id = ? AND kanal = ? AND status = 'von_hand' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)", [$firmaId, $kanal], null) !== null) {
            return false;
        }
        require_once __DIR__ . '/AkquiseVersand.php';
        AkquiseVersand::vonHand($firmaId, $kanal, 'Von Hand ' . ($kanal === 'email' ? 'per E-Mail' : 'per WhatsApp') . ' aus dem eigenen Programm geschrieben (Zustimmung liegt vor)');
        return true;
    }
}
