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
        require_once __DIR__ . '/AkquiseMail.php';
        $mail = AkquiseGate::einwilligungDeckt($f, 'email') || AkquiseMail::status($f) === AkquiseMail::FREI;
        $wa = AkquiseGate::einwilligungDeckt($f, 'whatsapp');
        if ($mail || $wa || (int) ($f['bestandskunde'] ?? 0) === 1) {
            return ['farbe' => 'gruen', 'wort' => 'Darf ' . ($mail && $wa ? 'per Mail und WhatsApp' : ($wa ? 'per WhatsApp' : 'per Mail')), 'schreiben' => true];
        }
        if (in_array((string) ($f['kontakt_status'] ?? ''), ['kontaktiert', 'geantwortet'], true)) {
            return ['farbe' => 'gelb', 'wort' => 'Wartet auf Antwort', 'schreiben' => false];
        }
        /* 07.10.2026, Uwe: „zeigt immer noch muss anrufen oder vorbeigehen“. Seit dem 06.10. öffnet die Mail auch ohne
           Zustimmung im eigenen Mailprogramm (nach bestätigtem Hinweis) -- die Ampel sagte trotzdem weiter „Erst
           anrufen oder besuchen“. Jetzt nennt sie, was wirklich geht. WhatsApp bleibt bis zur Zustimmung zu (Gate),
           deshalb zählt hier nur die Mail. Ohne Datenbankabfrage: Partner-Reservierung prüft erst die Firmenseite. */
        if (Akquise::normEmail((string) ($f['email'] ?? '')) !== null && AkquiseMail::status($f) !== AkquiseMail::NICHT) {
            return ['farbe' => 'blau', 'wort' => 'Schreiben per Mail', 'schreiben' => false];
        }
        return ['farbe' => 'grau', 'wort' => 'Erst anrufen oder besuchen', 'schreiben' => false];
    }

    /** Darf dieser Weg jetzt von Hand genutzt werden? Gate + Adresse. */
    public static function frei(array $f, string $kanal): bool
    {
        if ($kanal === 'email' && Akquise::normEmail((string) ($f['email'] ?? '')) === null) { return false; }
        /* E-Mail (06.10.2026): frei, wenn ein Versandgrund dokumentiert und freigegeben ist -- oder das Gate es ohnehin erlaubt. */
        if ($kanal === 'email') {
            require_once __DIR__ . '/AkquiseMail.php';
            $k = AkquiseMail::kann($f);
            if ($k['status'] === AkquiseMail::NICHT) { return false; }
            if ($k['senden']) { return true; }
        }
        if ($kanal === 'whatsapp' && strlen((string) preg_replace('~\D~', '', (string) ($f['whatsapp'] ?? ''))) < 8) { return false; }
        return in_array(AkquiseGate::pruefen($f, $kanal)['status'], [AkquiseGate::ERLAUBT, AkquiseGate::PRUEFEN], true);
    }

    /** Branchen, bei denen die Frage nach „Aufträgen“ passt (Handwerk, Bau, Werkstatt). */
    public const HANDWERK = ['handwerk', 'bau', 'werkstatt'];

    /** Domain des Betriebs, sonst der Host aus der Adresse (ohne www). */
    private static function domain(array $f): string
    {
        $d = trim((string) ($f['domain'] ?? ''));
        if ($d === '') { $d = (string) (parse_url((preg_match('~^https?://~i', (string) ($f['url'] ?? '')) ? '' : 'https://') . trim((string) ($f['url'] ?? '')), PHP_URL_HOST) ?? ''); }
        return (string) preg_replace('~^www\.~i', '', $d);
    }

    /** Uwes WhatsApp für die erste E-Mail (Einstellung „wa_anzeige“, sonst seine Nummer). */
    public static function waLink(): string
    {
        $nr = (string) preg_replace('~\D~', '', AkquiseGate::einstellung('wa_anzeige', ''));
        return 'https://wa.me/' . (strlen($nr) >= 8 ? $nr : '393801907017');
    }

    /** Die Satzbausteine je Sprache. Anweisungen an Uwe stehen in [eckigen Klammern] und auf Deutsch. */
    private const W = [
        'it' => [
            'hallo' => 'Buongiorno', 'gruss' => 'Cordiali saluti',
            'betreff' => 'Una domanda veloce per {firma}',
            'zona' => 'in zona {stadt}', 'zona_x' => 'in zona',
            'e_ohne' => 'cercavo un’attività {zona} e ho notato che online non avete un sito.',
            'e_web' => 'cercavo un’attività {zona} e sono capitato sul vostro sito {domain}.',
            'e_analyse' => 'L’ho guardato con calma: qui trova un’analisi gratuita di cosa funziona già e cosa si può migliorare: {analyse}',
            'e_analyse_ohne' => 'Ho preparato per voi una breve panoramica gratuita: {analyse}',
            'e_punkte' => 'Mi hanno colpito due cose:',
            'frage_ohne_h' => 'Siete ancora attivi e prendete lavori, o rinunciate di proposito a un sito?',
            'frage_ohne' => 'Siete ancora attivi e aperti ai clienti, o rinunciate di proposito a un sito?',
            'frage_web' => 'Il sito lo seguite ancora voi, o se ne occupa già qualcuno?',
            'wem_h' => 'agli artigiani come voi', 'wem' => 'alle attività come la vostra',
            'wir' => 'Sono {inhaber} di {absender}. Togliamo proprio questo pensiero {wem} – senza che dobbiate trovare tempo o combattere con la tecnica. Ci dite in due parole cosa vi serve, al resto pensiamo noi.',
            'link' => 'Come funziona lo vede qui in due minuti: {link}',
            'wa_cta' => 'Oppure mi scriva semplicemente su WhatsApp: {wa}',
            'wa_hallo' => 'Buongiorno, sono {inhaber} di {absender}.',
            'wa_kurz' => 'Togliamo proprio questo pensiero {wem}: niente tempo perso, niente stress con la tecnica.',
            'wa_link' => 'Come funziona: {link}',
            'stopp' => 'Se non desidera altri messaggi, risponda semplicemente «STOP».',
            'wa_stopp' => 'Se non desidera più messaggi, risponda semplicemente «STOP».',
            'a_hallo' => 'Buongiorno, sono {inhaber} di {absender}, web designer qui in provincia di Agrigento. Parlo con il titolare di {firma}?',
            'a_problem2' => 'E ancora: {punkt}',
            'a_nein' => 'Nessun problema, grazie e buona giornata.',
            'erlaubnis' => 'Ha trenta secondi? Poi la lascio subito lavorare.',
            'lob' => 'Vi ho trovati cercando {mz} {in} e mi è venuta subito un’idea per voi.',
            'kunde' => 'Oggi chi cerca {mz} {in} lo fa quasi sempre dal telefono.',
            'a_grund_web' => 'Sul vostro sito {domain} ho notato una cosa: {punkt}',
            'a_grund_web_x' => 'Sul vostro sito {domain} ho visto due o tre cose che si possono migliorare facilmente.',
            'a_grund_ohne' => '{firma} però non ha ancora un sito proprio: così chi vi cerca su Google trova soprattutto gli altri.',
            'zahl' => 'Non siete i soli: il {p} % dei {n} siti di {mz} {in} che abbiamo analizzato {worte}.',
            'a_loesung' => 'La nostra proposta: {loesung}. Senza impegno — prima vede tutto e poi decide lei.',
            'a_loesung_web_x' => 'La nostra proposta: un sito veloce sul telefono, facile da trovare su Google e con un tocco per chiamare o scrivere. Senza impegno — prima vede tutto e poi decide lei.',
            'a_loesung_ohne' => 'La nostra proposta: un sito semplice e curato per {firma}, che si trova su Google, mostra orari, foto e mappa, e con un tocco la chiamano o le scrivono. Senza impegno — prima vede tutto e poi decide lei.',
            'bitte' => 'Posso mandarle l’analisi gratuita? Se la guarda con calma: sono due minuti di lettura, e non deve decidere niente.',
            'a_email' => 'A quale indirizzo e-mail gliela mando? Me lo detta lettera per lettera?',
            'a_wahl' => 'Gliela mando per e-mail: la vuole anche su WhatsApp, o basta l’e-mail? A quale indirizzo e-mail? Me lo detta lettera per lettera?',
            'b_frage' => 'Le lascio volentieri il nostro volantino con il codice QR: lì vede l’analisi, gratis e senza impegno. Oppure gliela mando per e-mail?',
            'e_zeit_q' => '«Non ho tempo.»',
            'e_zeit_a' => 'La capisco benissimo. Proprio per questo le mando solo l’analisi: la guarda quando vuole, bastano due minuti.',
            'e_hat_q' => '«Il sito ce l’abbiamo già.»',
            'e_hat_a' => 'Perfetto, l’analisi riguarda proprio il vostro sito: le mostra cosa funziona già e cosa si può migliorare. Se va tutto bene, meglio ancora.',
            'e_social_q' => '«Abbiamo già Facebook e Instagram.»',
            'e_social_a' => 'Ottimo, è un buon inizio. Su Google però chi cerca {mz} trova soprattutto i siti: il sito lavora insieme ai social, non al loro posto.',
            'e_preis_q' => '«Quanto costa?»',
            'e_preis_a' => 'Dipende da cosa le serve davvero — ed è proprio quello che le mostra l’analisi, gratis. Poi decide lei, senza impegno.',
            'e_post_q' => '«Mi mandi qualcosa per posta.»',
            'e_post_a' => 'Volentieri in digitale: così vede subito le immagini del suo sito e tutti i dettagli. A quale e-mail gliela mando?',
            'p_hallo' => 'Buongiorno, sono {sprecher} e collaboro con {absender}, web design in provincia di Agrigento. Parlo con il titolare di {firma}?',
            'b_hallo' => 'Buongiorno, sono {inhaber} di {absender}, web designer qui in zona. Posso rubarle un minuto?',
        ],
        'de' => [
            'hallo' => 'Guten Tag', 'gruss' => 'Viele Grüße',
            'betreff' => 'Kurze Frage zu {firma}',
            'zona' => 'in der Gegend von {stadt}', 'zona_x' => 'in der Region',
            'e_ohne' => 'ich war auf der Suche nach einem Betrieb {zona} und mir ist aufgefallen, dass Sie online gar keine Webseite haben.',
            'e_web' => 'ich war auf der Suche nach einem Betrieb {zona} und bin dabei auf Ihre Webseite {domain} gestoßen.',
            'e_analyse' => 'Ich habe sie mir kurz genauer angesehen – hier eine kostenlose Analyse, was schon gut läuft und was besser ginge: {analyse}',
            'e_analyse_ohne' => 'Dazu habe ich Ihnen eine kurze, kostenlose Übersicht zusammengestellt: {analyse}',
            'e_punkte' => 'Zwei Dinge sind mir aufgefallen:',
            'frage_ohne_h' => 'Sind Sie eigentlich noch aktiv und nehmen Aufträge an, oder verzichten Sie ganz bewusst auf eine Homepage?',
            'frage_ohne' => 'Sind Sie eigentlich noch aktiv und für Ihre Kunden da, oder verzichten Sie ganz bewusst auf eine Homepage?',
            'frage_web' => 'Kümmern Sie sich selbst noch um die Seite, oder ist da schon jemand dran?',
            'wem_h' => 'Handwerksbetrieben wie Ihrem', 'wem' => 'Betrieben wie Ihrem',
            'wir' => 'Ich bin {inhaber} von {absender}. Wir nehmen {wem} genau diese Arbeit ab – ohne dass Sie dafür Zeit freischaufeln oder sich mit Technik herumschlagen müssen. Sie sagen kurz, was Sie brauchen, den Rest übernehmen wir.',
            'link' => 'Wie das abläuft, sehen Sie hier in zwei Minuten: {link}',
            'wa_cta' => 'Oder schreiben Sie mir einfach unkompliziert per WhatsApp: {wa}',
            'wa_hallo' => 'Guten Tag, hier ist {inhaber} von {absender}.',
            'wa_kurz' => 'Wir nehmen {wem} genau diese Arbeit ab – ohne Zeitaufwand und ohne Technik-Stress.',
            'wa_link' => 'Wie das abläuft: {link}',
            'stopp' => 'Wenn Sie keine weiteren Nachrichten möchten, antworten Sie einfach „STOPP“.',
            'wa_stopp' => 'Wenn Sie keine Nachrichten mehr möchten, antworten Sie einfach „STOPP“.',
            'a_hallo' => 'Guten Tag, hier ist {inhaber} von {absender}, Webdesign. Spreche ich mit dem Inhaber von {firma}?',
            'a_problem2' => 'Außerdem: {punkt}',
            'a_nein' => 'Kein Problem, vielen Dank und einen schönen Tag.',
            'erlaubnis' => 'Haben Sie kurz 30 Sekunden? Dann lasse ich Sie gleich weiterarbeiten.',
            'lob' => 'Ich bin bei der Suche nach {mz} {in} auf {firma} gestoßen und hatte gleich eine Idee für Sie.',
            'kunde' => 'Wer heute {mz} {in} sucht, macht das fast immer am Handy.',
            'a_grund_web' => 'Auf Ihrer Website {domain} ist mir etwas aufgefallen: {punkt}',
            'a_grund_web_x' => 'Auf Ihrer Website {domain} habe ich zwei, drei Dinge gesehen, die sich leicht verbessern lassen.',
            'a_grund_ohne' => '{firma} hat aber noch keine eigene Website – so finden Ihre künftigen Kunden bei Google vor allem die anderen.',
            'zahl' => 'Da sind Sie nicht allein: {p} % der {n} Websites von {mz} {in}, die wir geprüft haben, {worte}.',
            'a_loesung' => 'Unser Vorschlag: {loesung}. Ganz unverbindlich — Sie sehen erst alles und entscheiden dann selbst.',
            'a_loesung_web_x' => 'Unser Vorschlag: eine Website, die am Handy schnell lädt, bei Google gut gefunden wird und mit einem Tipp anrufen oder schreiben lässt. Ganz unverbindlich — Sie sehen erst alles und entscheiden dann selbst.',
            'a_loesung_ohne' => 'Unser Vorschlag: eine schlichte, gepflegte Website für {firma}, die bei Google gefunden wird, Öffnungszeiten, Fotos und Karte zeigt und mit einem Tipp anrufen oder schreiben lässt. Ganz unverbindlich — Sie sehen erst alles und entscheiden dann selbst.',
            'bitte' => 'Darf ich Ihnen die kostenlose Analyse schicken? Sie schauen sie sich in Ruhe an – zwei Minuten Lesezeit, und Sie müssen nichts entscheiden.',
            'a_email' => 'An welche E-Mail-Adresse darf ich sie Ihnen schicken? Buchstabieren Sie sie mir bitte kurz?',
            'a_wahl' => 'Ich schicke sie Ihnen per E-Mail – möchten Sie sie auch per WhatsApp, oder reicht die E-Mail? An welche E-Mail-Adresse? Buchstabieren Sie sie mir bitte kurz?',
            'b_frage' => 'Ich lasse Ihnen gern unseren Flyer mit QR-Code da: Darüber sehen Sie die Analyse, kostenlos und unverbindlich. Oder soll ich sie Ihnen per E-Mail schicken?',
            'e_zeit_q' => '„Keine Zeit.“',
            'e_zeit_a' => 'Verstehe ich gut. Genau deshalb schicke ich Ihnen nur die Analyse: Sie schauen sie sich an, wann es passt – zwei Minuten reichen.',
            'e_hat_q' => '„Wir haben schon eine Website.“',
            'e_hat_a' => 'Genau um die geht es: Die Analyse zeigt Ihnen, was schon gut läuft und was sich verbessern lässt. Ist alles gut, umso besser.',
            'e_social_q' => '„Wir haben Facebook und Instagram.“',
            'e_social_a' => 'Super, ein guter Anfang. Bei Google finden Suchende aber vor allem Websites – die Website arbeitet mit Ihren Kanälen zusammen, nicht statt ihnen.',
            'e_preis_q' => '„Was kostet das?“',
            'e_preis_a' => 'Das hängt davon ab, was Sie wirklich brauchen – genau das zeigt Ihnen die Analyse, kostenlos. Danach entscheiden Sie selbst, ganz unverbindlich.',
            'e_post_q' => '„Schicken Sie mir was per Post.“',
            'e_post_a' => 'Gern digital: Dann sehen Sie gleich Bilder Ihrer Website und alle Details. An welche E-Mail-Adresse darf ich sie schicken?',
            'p_hallo' => 'Guten Tag, hier ist {sprecher}, ich arbeite mit {absender} zusammen, Webdesign. Spreche ich mit dem Inhaber von {firma}?',
            'b_hallo' => 'Guten Tag, ich bin {inhaber} von {absender}, Webdesign hier aus der Gegend. Haben Sie eine Minute?',
        ],
        'en' => [
            'hallo' => 'Hello', 'gruss' => 'Kind regards',
            'betreff' => 'A quick question about {firma}',
            'zona' => 'around {stadt}', 'zona_x' => 'in the area',
            'e_ohne' => 'I was looking for a business {zona} and noticed that you don’t have a website at all.',
            'e_web' => 'I was looking for a business {zona} and came across your website {domain}.',
            'e_analyse' => 'I had a closer look: here is a free analysis of what already works and what could be better: {analyse}',
            'e_analyse_ohne' => 'I put together a short, free overview for you: {analyse}',
            'e_punkte' => 'Two things caught my eye:',
            'frage_ohne_h' => 'Are you still active and taking on work, or have you deliberately decided against a website?',
            'frage_ohne' => 'Are you still active and there for your customers, or have you deliberately decided against a website?',
            'frage_web' => 'Do you still look after the site yourselves, or is someone already on it?',
            'wem_h' => 'tradespeople like you', 'wem' => 'businesses like yours',
            'wir' => 'I’m {inhaber} from {absender}. We take exactly this work off the hands of {wem} – without you having to find time or wrestle with technology. You tell us briefly what you need, we take care of the rest.',
            'link' => 'You can see how it works here in two minutes: {link}',
            'wa_cta' => 'Or simply message me on WhatsApp: {wa}',
            'wa_hallo' => 'Hello, this is {inhaber} from {absender}.',
            'wa_kurz' => 'We take exactly this work off the hands of {wem} – no time lost, no tech stress.',
            'wa_link' => 'How it works: {link}',
            'stopp' => 'If you’d rather not receive further messages, just reply “STOP”.',
            'wa_stopp' => 'If you’d rather not get messages, just reply “STOP”.',
            'a_hallo' => 'Hello, this is {inhaber} from {absender}, web design. Am I speaking with the owner of {firma}?',
            'a_problem2' => 'Also: {punkt}',
            'a_nein' => 'No problem at all, thank you and have a nice day.',
            'erlaubnis' => 'Do you have 30 seconds? Then I’ll let you get straight back to work.',
            'lob' => 'I came across {firma} while looking for {mz} {in} and had an idea for you right away.',
            'kunde' => 'Today, people looking for {mz} {in} almost always do it on their phone.',
            'a_grund_web' => 'On your website {domain} I noticed something: {punkt}',
            'a_grund_web_x' => 'On your website {domain} I saw two or three things that are easy to improve.',
            'a_grund_ohne' => 'But {firma} doesn’t have its own website yet – so people searching on Google mostly find the others.',
            'zahl' => 'You’re not alone: {p} % of the {n} {mz} websites {in} we checked {worte}.',
            'a_loesung' => 'Our suggestion: {loesung}. No obligation — you see everything first and then decide.',
            'a_loesung_web_x' => 'Our suggestion: a website that loads fast on phones, is easy to find on Google and lets people call or write with one tap. No obligation — you see everything first and then decide.',
            'a_loesung_ohne' => 'Our suggestion: a simple, well-made website for {firma} that shows up on Google, shows opening hours, photos and a map, and lets people call or write with one tap. No obligation — you see everything first and then decide.',
            'bitte' => 'May I send you the free analysis? Have a look at it in your own time – two minutes of reading, and you don’t have to decide anything.',
            'a_email' => 'Which email address should I send it to? Could you spell it for me?',
            'a_wahl' => 'I’ll send it by email – would you like it on WhatsApp too, or is email enough? Which email address? Could you spell it for me?',
            'b_frage' => 'I’m happy to leave our flyer with the QR code: it shows the analysis, free and without obligation. Or shall I send it by email?',
            'e_zeit_q' => '“I don’t have time.”',
            'e_zeit_a' => 'I completely understand. That’s exactly why I’ll just send you the analysis: look at it whenever suits you – two minutes is enough.',
            'e_hat_q' => '“We already have a website.”',
            'e_hat_a' => 'Exactly, the analysis is about your website: it shows what already works and what could be better. If everything is fine, even better.',
            'e_social_q' => '“We have Facebook and Instagram.”',
            'e_social_a' => 'Great, that’s a good start. On Google, though, searchers mostly find websites – the website works together with your social channels, not instead of them.',
            'e_preis_q' => '“How much does it cost?”',
            'e_preis_a' => 'That depends on what you really need – and that’s exactly what the analysis shows you, free of charge. Then you decide, with no obligation.',
            'e_post_q' => '“Send me something by post.”',
            'e_post_a' => 'Happy to send it digitally: you’ll see pictures of your website and all the details right away. Which email should I send it to?',
            'p_hallo' => 'Hello, this is {sprecher}, I work with {absender}, web design. Am I speaking with the owner of {firma}?',
            'b_hallo' => 'Hello, I’m {inhaber} from {absender}, a local web designer. Do you have a minute?',
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
        /* Die Punkte: nur geprüfte Befunde mit gepflegtem Satz (dieselben wie auf der Analyse-Seite). */
        $punkte = []; $wirkungen = []; $loesungen = [];
        foreach (AkquiseScore::topBefunde($befunde) as $b) {
            if (($b['status'] ?? '') !== 'VERIFIED') { continue; }
            $x = AkquiseText::saetze($b, $f, $sp);
            if ($x !== null) { $punkte[] = $x[0]; $wirkungen[] = $x[1]; $loesungen[] = $x[2]; }
            if (count($punkte) >= 2) { break; }
        }
        $r = ['{firma}' => $firma, '{inhaber}' => $abs['inhaber'], '{absender}' => $abs['firma'], '{branche}' => $branche, '{in}' => $in,
              '{mz}' => $mz, '{domain}' => self::domain($f), '{telefon}' => (string) $abs['telefon']];
        $t = static fn(string $k, array $mehr = []): string => trim((string) preg_replace(['~\s+([.,:;])~u', '~ {2,}~u'], ['$1', ' '], strtr($W[$k], $mehr + $r)));
        $anrede = trim((string) ($f['ansprechpartner'] ?? ''));

        /* ---- E-Mail ---- */
        /* Erste E-Mail (06.10.2026, Uwe): keine erfundene Vorgeschichte („wie besprochen“), sondern die ehrliche
           Beobachtung, eine Frage auf Augenhöhe, wer schreibt und was wir abnehmen, dann Link und WhatsApp.
           Nie „Provision“, „Werbung“ oder „Verkauf“ (die Werkstatt hält solche Texte an). */
        $handwerk = in_array((string) ($f['branche'] ?? ''), self::HANDWERK, true);
        $r += ['{zona}' => $stadt !== '' ? strtr($W['zona'], ['{stadt}' => mb_convert_case(mb_strtolower($stadt), MB_CASE_TITLE)]) : $W['zona_x'],
               '{wem}' => $W[$handwerk ? 'wem_h' : 'wem'], '{analyse}' => $analyse, '{link}' => $richt, '{wa}' => self::waLink()];
        $t = static fn(string $k, array $mehr = []): string => trim((string) preg_replace(['~\s+([.,:;])~u', '~ {2,}~u'], ['$1', ' '], strtr($W[$k], $mehr + $r)));
        $z = [$W['hallo'] . ($anrede !== '' ? ' ' . $anrede : '') . ',', ''];
        if (!$hatWeb) {
            $z[] = $t('e_ohne'); if ($analyse !== '') { $z[] = $t('e_analyse_ohne'); }
            $z[] = ''; $z[] = $t($handwerk ? 'frage_ohne_h' : 'frage_ohne');
        } else {
            $z[] = $t('e_web');
            if ($analyse !== '') { $z[] = $t('e_analyse'); }
            elseif ($punkte) { $z[] = ''; $z[] = $W['e_punkte']; foreach ($punkte as $p) { $z[] = '– ' . $p; } }
            $z[] = ''; $z[] = $t('frage_web');
        }
        $z[] = ''; $z[] = $t('wir');
        $z[] = ''; $z[] = $t('link'); $z[] = $t('wa_cta');
        $z[] = ''; $z[] = $W['gruss']; $z[] = $abs['inhaber']; $z[] = $abs['firma'] . ' · ' . $abs['ort']; $z[] = $basis;
        $z[] = ''; $z[] = $W['stopp'];
        $betreff = $t('betreff');
        $mailText = implode("\n", array_values(array_filter($z, static fn($x) => $x !== null)));
        $mail = Akquise::normEmail((string) ($f['email'] ?? ''));

        /* ---- WhatsApp: dieselbe Haltung, kürzer ---- */
        $gross = static fn(string $x): string => mb_strtoupper(mb_substr($x, 0, 1)) . mb_substr($x, 1);
        $wz = [$t('wa_hallo') . ' ' . $gross($t($hatWeb ? 'e_web' : 'e_ohne')) . ' ' . $t($hatWeb ? 'frage_web' : ($handwerk ? 'frage_ohne_h' : 'frage_ohne')), ''];
        $wz[] = $t('wa_kurz');
        $wz[] = $t('wa_link', ['{link}' => $analyse !== '' ? $analyse : $richt]);
        $wz[] = ''; $wz[] = $W['wa_stopp'];
        $waText = implode("\n", $wz);
        $waNr = (string) preg_replace('~\D~', '', (string) ($f['whatsapp'] ?? ''));

        /* ---- Anruf und Besuch: Gesprächsleitfaden in seiner Sprache, Regieanweisungen auf Deutsch ---- */
        /* Problem und Lösung passend zum Betrieb (29.09.2026, Uwe): aus der Fehler-Analyse
           (geprüfte Befunde: was wir gesehen haben, was es bedeutet, was wir vorschlagen)
           oder -- ohne Website -- warum ihm Kunden entgehen und was wir bauen. Immer unverbindlich. */
        if (!$hatWeb) {
            $problem = $t('kunde') . ' ' . $t('a_grund_ohne');
            $loesung = $t('a_loesung_ohne');
        } elseif ($punkte) {
            $problem = $t('kunde') . ' ' . $t('a_grund_web', ['{punkt}' => $punkte[0]]) . ' ' . $wirkungen[0] . (isset($punkte[1]) ? ' ' . $t('a_problem2', ['{punkt}' => $punkte[1]]) : '');
            $loesung = $t('a_loesung', ['{loesung}' => implode($sp === 'en' ? ' and ' : ($sp === 'de' ? ' und ' : ' e '), array_values(array_unique($loesungen)))]);
        } else {
            $problem = $t('kunde') . ' ' . $t('a_grund_web_x');
            $loesung = $t('a_loesung_web_x');
        }
        /* Echte Zahl aus dem Ort (29.09.2026, S4) -- nur wenn die Branchen-Seite
           existiert, also mindestens 15 geprüfte Websites dieser Branche dort. */
        $zahl = '';
        if ($stadt !== '' && (string) ($f['branche'] ?? '') !== '') {
            try {
                $st = BranchenStatistik::laden(BranchenStatistik::slug((string) $f['branche'], $stadt));
                if ($st !== null) {
                    [$k, $pz] = BranchenStatistik::staerkste($st);
                    if ($pz >= 30 && isset(BranchenStatistik::WORTE[$k][$sp])) {
                        $zahl = $t('zahl', ['{p}' => (string) $pz, '{n}' => (string) (int) $st['geprueft'], '{worte}' => BranchenStatistik::WORTE[$k][$sp]]);
                    }
                }
            } catch (Throwable $e) { $zahl = ''; }
        }
        $satz = self::wortlaut($sp, self::nurMail($f));
        $email = $t(self::nurMail($f) ? 'a_email' : 'a_wahl');
        $hallo = ($sprecher !== null && trim($sprecher) !== '' ? $t('p_hallo', ['{sprecher}' => trim($sprecher)]) : $t('a_hallo')) . ' ' . $t('erlaubnis');
        $einwaende = [];
        foreach (['zeit', $hatWeb ? 'hat' : 'social', 'preis', 'post'] as $e) { $einwaende[] = [$t('e_' . $e . '_q'), $t('e_' . $e . '_a')]; }
        $mitte = array_values(array_filter([['Aufhänger', $t('lob')], ['Problem aus Sicht seiner Kunden', $problem], $zahl !== '' ? ['Echte Zahl aus dem Ort', $zahl] : null,
                                            ['Lösung (ohne Preis)', $loesung]]));
        $einw = array_map(static fn(array $x): array => ['Wenn er sagt ' . $x[0], $x[1]], $einwaende);
        $anruf = array_merge([['Begrüßen, um 30 Sekunden bitten', $hallo]], $mitte, [['Kleine Bitte (unverbindlich)', $t('bitte')],
                  ['Wenn ja: diese Frage vorlesen', $satz], ['Dann E-Mail erfragen und eintragen', $email]], $einw,
                  [['Wenn nein', $t('a_nein') . ' → unten „Kein Interesse“ antippen: Der Betrieb wird nie mehr angesprochen.']]);
        $besuch = array_merge([['Begrüßen', $t('b_hallo')]], $mitte, [['Kleine Bitte (unverbindlich)', $t('b_frage')],
                   ['Wenn ja: diese Frage vorlesen oder zeigen', $satz], ['Dann E-Mail erfragen und eintragen', $email . ' — oder „Vor Ort zeigen“: Er tippt sie selbst ein.']], $einw,
                   [['Wenn nein', $t('a_nein')]]);

        $frei = ['email' => self::frei($f, 'email'), 'whatsapp' => self::frei($f, 'whatsapp')];
        $tel = (string) preg_replace('~[^\d+]~', '', (string) ($f['telefon'] ?? ''));
        return [
            'sprache' => $sp,
            'email' => ['betreff' => $betreff, 'text' => $mailText,
                        'link' => $frei['email'] && $mail !== null ? 'mailto:' . rawurlencode($mail) . '?subject=' . rawurlencode($betreff) . '&body=' . rawurlencode($mailText) : null],
            'whatsapp' => ['text' => $waText, 'link' => $frei['whatsapp'] && strlen($waNr) >= 8 ? 'https://wa.me/' . $waNr . '?text=' . rawurlencode($waText) : null],
            'anruf' => $anruf, 'besuch' => $besuch, 'wortlaut' => $satz, 'frei' => $frei,
            'saetze' => ['hallo' => $hallo, 'lob' => $t('lob'), 'problem' => $problem, 'zahl' => $zahl, 'loesung' => $loesung, 'frage' => $t('bitte'),
                         'ja' => $satz, 'email' => $email, 'einwaende' => $einwaende, 'nein' => $t('a_nein')],
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
        AkquiseVersand::vonHand($firmaId, $kanal, 'Von Hand ' . ($kanal === 'email' ? 'per E-Mail' : 'per WhatsApp') . ' aus dem eigenen Programm geschrieben (' . ($kanal === 'email' ? 'Versandgrund dokumentiert' : 'Zustimmung liegt vor') . ')');
        return true;
    }
}
