<?php
declare(strict_types=1);
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';

/**
 * Website-Tipp der Woche (28.09.2026, Uwe: Ja zu D5).
 *
 * Abonnieren auf analisi.php und auf der Startseite: E-Mail, Häkchen unter
 * dem Wortlaut, dann nur die Bestätigungsmail. Erst der Klick darin (auf
 * tipp.php, bestätigt per Knopf -- Postfach-Filter rufen Links vorab auf)
 * macht das Abo aktiv. Dienstags geht an jedes aktive Abo der nächste Tipp,
 * mit dem Link zur kostenlosen Analyse und zum persönlichen Bereich.
 * Abbestellen: ein Klick unten in jeder Mail. Die Notbremse der Akquise und
 * der Schalter „tipp“ halten alles an.
 */
final class WebTipp
{
    public const DOI_TAGE = 7;
    public const JE_LAUF = 40;
    public const TAG = 2;   // Dienstag

    public const WORTLAUT = [
        'it' => '{firma} ({inhaber}) può inviarmi una volta alla settimana via e-mail un consiglio per il mio sito, con il link all’analisi gratuita. Posso annullare in qualsiasi momento con un clic.',
        'de' => '{firma} ({inhaber}) darf mir einmal pro Woche per E-Mail einen Tipp für meine Website schicken, mit dem Link zur kostenlosen Analyse. Ich kann das jederzeit mit einem Klick abbestellen.',
        'en' => '{firma} ({inhaber}) may send me one tip for my website by email once a week, with the link to the free analysis. I can unsubscribe at any time with one click.',
    ];

    /** Die Tipps: [Titel, Text]. Keine Zahlen ohne Beleg, keine Angstsätze. */
    public const TIPPS = [
        'it' => [
            ['Il test del telefono', 'Apra il suo sito sul telefono, con una mano sola, come farebbe un cliente in macchina o in fila. Trova in pochi secondi telefono, indirizzo e orari? Se deve ingrandire con le dita, è il primo punto da sistemare.'],
            ['Orari sempre aggiornati', 'Chi trova sul sito orari vecchi e poi la porta chiusa non torna volentieri. Controlli orari, ferie e giorni di chiusura anche sulla scheda Google: devono dire la stessa cosa.'],
            ['La scheda Google della sua attività', 'Quando qualcuno cerca il suo nome, Google mostra spesso prima la scheda dell’attività che il sito. Foto recenti, orari giusti e il link al sito la rendono il suo biglietto da visita migliore.'],
            ['Foto vere, non di catalogo', 'Le persone vogliono vedere il posto, le persone e il lavoro vero. Tre foto buone fatte da lei valgono più di dieci immagini prese da internet, che tanti clienti riconoscono subito.'],
            ['Il pulsante per chiamare', 'Sul telefono il numero deve essere un pulsante: si tocca e parte la chiamata. Se il numero è solo testo o dentro un’immagine, molti rinunciano prima di copiarlo.'],
            ['«Non sicuro» accanto all’indirizzo', 'Se il browser scrive «Non sicuro» accanto al suo sito, manca il collegamento cifrato (https). Di solito si attiva presso il fornitore del dominio con poco lavoro, e il messaggio sparisce.'],
            ['Come la presenta Google', 'Su Google ogni pagina appare con un titolo e due righe di descrizione. Cerchi il suo sito: il titolo dice chi è e dove si trova? Se no, vale la pena riscriverlo.'],
            ['L’anteprima quando qualcuno condivide', 'Quando un cliente manda il suo sito su WhatsApp, appare un’immagine con il nome? Se esce solo un link grigio, manca l’immagine di anteprima: si aggiunge una volta e funziona per sempre.'],
            ['Il menù o il listino come testo', 'Un PDF da scaricare sul telefono è scomodo e Google lo legge male. Menù, servizi e prezzi scritti direttamente nella pagina si leggono subito e si trovano meglio.'],
            ['Partita IVA e privacy', 'Per un’attività in Italia la partita IVA deve comparire sul sito, di solito in fondo alla pagina, insieme a una pagina privacy. Controlli che ci siano entrambe e che siano aggiornate.'],
        ],
        'de' => [
            ['Der Handy-Test', 'Öffnen Sie Ihre Website auf dem Handy, mit einer Hand, so wie ein Kunde unterwegs. Finden Sie in wenigen Sekunden Telefon, Adresse und Öffnungszeiten? Müssen Sie mit den Fingern vergrößern, ist das der erste Punkt.'],
            ['Öffnungszeiten immer aktuell', 'Wer auf der Website alte Zeiten findet und dann vor verschlossener Tür steht, kommt ungern wieder. Prüfen Sie Zeiten, Urlaub und Ruhetage auch im Google-Eintrag: Beide müssen dasselbe sagen.'],
            ['Ihr Google-Unternehmensprofil', 'Sucht jemand nach Ihrem Namen, zeigt Google oft zuerst Ihr Unternehmensprofil, dann die Website. Aktuelle Fotos, richtige Zeiten und der Link zur Seite machen es zu Ihrer besten Visitenkarte.'],
            ['Echte Fotos statt Katalog', 'Menschen wollen den Ort, die Menschen und die echte Arbeit sehen. Drei gute eigene Fotos wirken mehr als zehn Bilder aus dem Internet, die viele Kunden sofort erkennen.'],
            ['Der Anruf-Knopf', 'Auf dem Handy muss die Nummer ein Knopf sein: antippen, Anruf startet. Steht sie nur als Text oder in einem Bild, geben viele auf, bevor sie sie abgetippt haben.'],
            ['„Nicht sicher“ neben der Adresse', 'Zeigt der Browser „Nicht sicher“ neben Ihrer Seite, fehlt die verschlüsselte Verbindung (https). Meist lässt sie sich beim Anbieter Ihrer Domain mit wenig Aufwand einschalten, und der Hinweis verschwindet.'],
            ['Wie Google Sie vorstellt', 'Bei Google erscheint jede Seite mit einem Titel und zwei Zeilen Beschreibung. Suchen Sie Ihre Website: Sagt der Titel, wer Sie sind und wo? Wenn nicht, lohnt es sich, ihn neu zu schreiben.'],
            ['Die Vorschau beim Teilen', 'Schickt ein Kunde Ihre Seite per WhatsApp weiter, erscheint dann ein Bild mit Ihrem Namen? Kommt nur ein grauer Link, fehlt das Vorschaubild — einmal eingerichtet, wirkt es bei jedem Teilen.'],
            ['Karte und Preisliste als Text', 'Ein PDF zum Herunterladen ist auf dem Handy mühsam, und Google liest es schlecht. Speisekarte, Leistungen und Preise direkt auf der Seite sind sofort lesbar und werden besser gefunden.'],
            ['Impressum und Datenschutz', 'Für geschäftliche Websites in Deutschland sind Impressum und Datenschutzerklärung Pflicht und sollten von jeder Seite aus erreichbar sein. Prüfen Sie, ob beide da und aktuell sind.'],
        ],
        'en' => [
            ['The phone test', 'Open your website on your phone, one-handed, the way a customer would on the go. Can you find phone number, address and opening hours within seconds? If you have to pinch to zoom, that is the first thing to fix.'],
            ['Opening hours kept current', 'Someone who finds old hours online and then a closed door rarely comes back. Check hours, holidays and closing days on your Google listing too: both should say the same.'],
            ['Your Google business profile', 'When people search for your name, Google often shows your business profile before your website. Recent photos, correct hours and a link to your site make it your best business card.'],
            ['Real photos, not stock', 'People want to see the place, the people and the real work. Three good photos of your own do more than ten images from the internet that many customers recognise.'],
            ['The call button', 'On a phone, your number should be a button: tap it and the call starts. If it is plain text or inside an image, many people give up before typing it in.'],
            ['“Not secure” next to your address', 'If the browser says “Not secure” next to your site, the encrypted connection (https) is missing. It can usually be switched on at your domain provider with little effort, and the warning disappears.'],
            ['How Google presents you', 'On Google every page appears with a title and two lines of description. Search for your site: does the title say who you are and where? If not, it is worth rewriting.'],
            ['The preview when shared', 'When a customer sends your site on WhatsApp, does an image with your name appear? If it is just a grey link, the preview image is missing — set it up once and it works every time.'],
            ['Menu and price list as text', 'A PDF download is awkward on a phone and Google reads it poorly. Menus, services and prices written on the page are readable at once and found more easily.'],
            ['Legal pages', 'Business websites usually need legal information and a privacy page, reachable from every page. Check that both are there and up to date for your country.'],
        ],
    ];

    public static function wortlaut(string $sprache): string
    {
        $abs = AkquiseText::absender();
        return strtr(self::WORTLAUT[$sprache] ?? self::WORTLAUT['it'], ['{firma}' => $abs['firma'], '{inhaber}' => $abs['inhaber']]);
    }

    private static function basis(): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
    }

    /** @return string ok | email | zuviel */
    public static function anmelden(string $email, bool $ja, string $sprache, string $quelle = 'seite', string $ip = ''): string
    {
        $sprache = isset(self::WORTLAUT[$sprache]) ? $sprache : 'it';
        $email = Akquise::normEmail($email);
        if ($email === null || !$ja) { return 'email'; }
        $hash = $ip !== '' ? hash('sha256', $ip . '|' . date('Ymd') . '|tipp') : null;
        if ($hash !== null && (int) Db::wert('SELECT COUNT(*) FROM akq_tipp_abos WHERE ip_hash = ? AND angefragt_am >= CURDATE()', [$hash], 0) >= 5) { return 'zuviel'; }
        $alt = Db::one('SELECT * FROM akq_tipp_abos WHERE email = ?', [$email]);
        if ($alt && $alt['status'] === 'aktiv') { return 'ok'; }   // schon dabei -- nichts verraten, nichts doppelt
        if ($alt && $alt['status'] === 'angefragt' && strtotime((string) $alt['angefragt_am']) > time() - 600) { return 'ok'; }
        try { if (AkquiseGate::trifftSperrliste(['email' => $email]) !== null) { return 'ok'; } } catch (Throwable $e) { }
        $doi = bin2hex(random_bytes(20));
        $werte = ['sprache' => $sprache, 'status' => 'angefragt', 'doi_token' => $doi, 'wortlaut' => self::wortlaut($sprache),
                  'quelle' => mb_substr(preg_replace('~[^a-z]~', '', $quelle) ?: 'seite', 0, 20), 'ip_hash' => $hash, 'angefragt_am' => date('Y-m-d H:i:s')];
        if ($alt) { Db::update('akq_tipp_abos', (int) $alt['id'], $werte); }
        else { Db::insert('akq_tipp_abos', $werte + ['email' => $email, 'token' => bin2hex(random_bytes(20))]); }
        $link = self::basis() . '/tipp.php?b=' . $doi;
        $abs = AkquiseText::absender();
        [$betreff, $text] = [
            'it' => ['Confermi il consiglio settimanale per il suo sito', "Buongiorno,\n\nè stato richiesto il consiglio settimanale di {$abs['firma']} per questo indirizzo. Se è stato lei, confermi con un clic:\n\n{$link}\n\nCosì accetta: «" . self::wortlaut('it') . "»\n\nSe non è stato lei, ignori questa e-mail: senza clic non succede nulla.\n\n{$abs['inhaber']} · {$abs['firma']}"],
            'de' => ['Bitte bestätigen: Website-Tipp der Woche', "Guten Tag,\n\nfür diese Adresse wurde der Website-Tipp der Woche von {$abs['firma']} bestellt. Wenn Sie das waren, bestätigen Sie bitte mit einem Klick:\n\n{$link}\n\nSie stimmen damit zu: „" . self::wortlaut('de') . "“\n\nWaren Sie es nicht, ignorieren Sie diese Mail einfach — ohne Klick passiert nichts.\n\n{$abs['inhaber']} · {$abs['firma']}"],
            'en' => ['Please confirm: website tip of the week', "Hello,\n\nthe weekly website tip from {$abs['firma']} was requested for this address. If that was you, please confirm with one click:\n\n{$link}\n\nYou agree to: “" . self::wortlaut('en') . "”\n\nIf it wasn’t you, simply ignore this email — nothing happens without a click.\n\n{$abs['inhaber']} · {$abs['firma']}"],
        ][$sprache];
        require_once __DIR__ . '/Mail.php';
        Mail::senden('tipp_bestaetigung', $email, $betreff, $text, ['nurText' => true, 'sprache' => $sprache]);
        return 'ok';
    }

    /** Klick auf den Knopf nach dem Bestätigungslink. @return array{ok:bool, sprache:string} */
    public static function bestaetigen(string $doi): array
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $doi)) { return ['ok' => false, 'sprache' => 'it']; }
        $a = Db::one('SELECT * FROM akq_tipp_abos WHERE doi_token = ?', [$doi]);
        if (!$a) { return ['ok' => false, 'sprache' => 'it']; }
        if ($a['status'] === 'aktiv') { return ['ok' => true, 'sprache' => (string) $a['sprache']]; }
        if ($a['status'] !== 'angefragt' || strtotime((string) $a['angefragt_am'] . ' +' . self::DOI_TAGE . ' days') < time()) {
            if ($a['status'] === 'angefragt') { Db::update('akq_tipp_abos', (int) $a['id'], ['status' => 'abgelaufen']); }
            return ['ok' => false, 'sprache' => (string) $a['sprache']];
        }
        Db::update('akq_tipp_abos', (int) $a['id'], ['status' => 'aktiv', 'bestaetigt_am' => date('Y-m-d H:i:s')]);
        try { Events::melden('akquise_tipp', 'Neues Abo „Website-Tipp der Woche“ (' . strtoupper((string) $a['sprache']) . ')', 'gut', null, 'akquise/regeln#tipp'); } catch (Throwable $e) { }
        return ['ok' => true, 'sprache' => (string) $a['sprache']];
    }

    public static function abmelden(string $token): ?string
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $token)) { return null; }
        $a = Db::one('SELECT * FROM akq_tipp_abos WHERE token = ?', [$token]);
        if (!$a) { return null; }
        if ($a['status'] !== 'abgemeldet') { Db::update('akq_tipp_abos', (int) $a['id'], ['status' => 'abgemeldet', 'abgemeldet_am' => date('Y-m-d H:i:s')]); }
        return (string) $a['sprache'];
    }

    /** Text des nächsten Tipps für ein Abo. @return array{0:string,1:string} [Betreff, Text] */
    public static function mail(array $a, int $nr): array
    {
        $sp = isset(self::TIPPS[$a['sprache']]) ? (string) $a['sprache'] : 'it';
        [$titel, $text] = self::TIPPS[$sp][$nr % count(self::TIPPS[$sp])];
        require_once __DIR__ . '/Zugang.php';
        $bereich = (string) (Zugang::vorbereiten((string) $a['email'], $sp, 0) ?? (self::basis() . '/' . ($sp === 'it' ? '' : $sp . '/') . '#contact'));
        $analisi = self::basis() . '/analisi.php?lang=' . $sp;
        $ab = self::basis() . '/tipp.php?ab=' . $a['token'];
        $abs = AkquiseText::absender();
        $W = [
            'it' => ['Consiglio della settimana: ', 'Buongiorno,', 'Com’è messo il suo sito? Analisi gratuita in pochi secondi:', 'Il suo spazio personale su Vecom Design:', 'Non vuole più ricevere i consigli? Un clic qui:'],
            'de' => ['Tipp der Woche: ', 'Guten Tag,', 'Wie steht Ihre Website da? Kostenlose Analyse in wenigen Sekunden:', 'Ihr persönlicher Bereich bei Vecom Design:', 'Keine Tipps mehr? Ein Klick genügt:'],
            'en' => ['Tip of the week: ', 'Hello,', 'How is your website doing? Free analysis in a few seconds:', 'Your personal area at Vecom Design:', 'No more tips? One click:'],
        ][$sp];
        return [$W[0] . $titel, "{$W[1]}\n\n{$titel}\n\n{$text}\n\n{$W[2]}\n{$analisi}\n\n{$W[3]}\n{$bereich}\n\n—\n{$abs['inhaber']} · {$abs['firma']}\n\n{$W[4]}\n{$ab}"];
    }

    /** Dienstags der nächste Tipp an jedes aktive Abo. @return array{geschickt:int} */
    public static function lauf(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $n = 0;
        if ((int) date('N', $jetzt) !== self::TAG || !AkquiseGate::schalter('tipp')) { return ['geschickt' => 0]; }
        try { if (!empty(AkquiseGate::grenzen()['stop'])) { return ['geschickt' => 0]; } } catch (Throwable $e) { }
        require_once __DIR__ . '/Mail.php';
        $grenze = date('Y-m-d H:i:s', $jetzt - 6 * 86400);
        foreach (Db::all("SELECT * FROM akq_tipp_abos WHERE status = 'aktiv' AND (letzter_am IS NULL OR letzter_am < ?) ORDER BY id LIMIT " . self::JE_LAUF, [$grenze]) as $a) {
            [$betreff, $text] = self::mail($a, (int) $a['letzte_nr']);
            Db::update('akq_tipp_abos', (int) $a['id'], ['letzte_nr' => (int) $a['letzte_nr'] + 1, 'letzter_am' => date('Y-m-d H:i:s', $jetzt)]);
            try { Mail::senden('tipp_woche', (string) $a['email'], $betreff, $text, ['nurText' => true, 'sprache' => (string) $a['sprache']]); $n++; } catch (Throwable $e) { }
        }
        return ['geschickt' => $n];
    }
}
