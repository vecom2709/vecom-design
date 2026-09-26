<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseScore.php';
require_once __DIR__ . '/AkquiseGate.php';

/**
 * Kontaktvorlagen — schreiben, pruefen, Antworten einordnen.
 *
 * ZWEI SCHREIBER, EIN PRUEFER
 *
 * Den natuerlichsten Text schreibt Claude im Worker, aus den Befunden und
 * dem Screenshot. Fehlt der Schluessel oder ist das Budget aus, schreibt
 * diese Klasse selbst: aus Bausteinen je Befund-Code, in drei Sprachen,
 * mit Varianten, die an der Firma haengen (nicht am Zufall -- derselbe Lead
 * bekommt beim Neuerzeugen denselben Einstieg, und zwei Leads bekommen
 * selten denselben). Beide Texte laufen danach durch denselben Pruefer.
 *
 * WAS DER PRUEFER VERHINDERT
 *
 *  - Angstverkauf ("verlieren jeden Tag Kunden", "Konkurrenz voraus")
 *  - Preise in der Erstansprache (Uwes Regel vom 04.09.2026)
 *  - Zahlen, die in keinem Beleg stehen: Jede Zahl im Text muss sich in
 *    einem Messwert oder Beleg wiederfinden. So kann auch Claude keine
 *    "40 % mehr Anfragen" erfinden.
 *  - interne Woerter (Score, Opportunity, Lead, UNVERIFIED)
 *  - fehlender Link auf vecom-design.it, fehlender Widerspruchshinweis
 *
 * Ein beanstandeter Text laesst sich speichern, aber nicht freigeben.
 */
final class AkquiseText
{
    public const SPRACHEN = ['de' => 'Deutsch', 'it' => 'Italiano', 'en' => 'English'];

    public const ANTWORT_KLASSEN = [
        'INTERESTED' => 'Interessiert', 'NOT_INTERESTED' => 'Kein Interesse', 'CALL_REQUEST' => 'Möchte Anruf',
        'PRICE_REQUEST' => 'Fragt nach Preis', 'MORE_INFO' => 'Möchte mehr wissen', 'DO_NOT_CONTACT' => 'Keine Kontaktaufnahme',
        'OUT_OF_OFFICE' => 'Abwesenheit', 'INVALID_ADDRESS' => 'Adresse ungültig', 'OTHER' => 'Sonstiges',
    ];

    /**
     * Bausteine je Befund-Code: [Beobachtung, Wirkung, Loesung].
     * {wert} wird durch den Messwert ersetzt, wenn der Befund einen traegt.
     * Codes ohne Eintrag erscheinen nicht im Regeltext -- lieber drei
     * sichere Saetze als ein schiefer.
     */
    private const SAETZE = [
        'de' => [
            'kein_https' => ['Ihre Website wird ohne verschlüsselte Verbindung (HTTPS) ausgeliefert.', 'Browser markieren solche Seiten als „nicht sicher“ – das kann gerade beim ersten Besuch Vertrauen kosten.', 'eine saubere HTTPS-Umstellung mit Weiterleitung aller alten Adressen'],
            'https_auf_http' => ['Wer Ihre Seite mit https:// aufruft, wird auf die unverschlüsselte Fassung zurückgeleitet.', 'Der Browser zeigt dann „nicht sicher“ an, obwohl ein Zertifikat eigentlich möglich wäre.', 'eine saubere HTTPS-Umstellung mit Weiterleitung aller alten Adressen'],
            'ssl_fehler' => ['Beim Aufruf Ihrer Website meldet der Browser ein Problem mit dem Sicherheitszertifikat.', 'Viele Besucher brechen an dieser Warnseite ab, bevor sie Ihre Inhalte überhaupt sehen.', 'ein gültiges Zertifikat, das sich automatisch erneuert'],
            'seite_nicht_erreichbar' => ['Ihre Website war bei unseren Aufrufen nicht erreichbar.', 'Wer Sie über Google oder eine Empfehlung sucht, landet so im Leeren.', 'eine stabile, betreute Seite mit Überwachung, die Ausfälle sofort meldet'],
            'domain_tot' => ['Die Adresse {domain} führt derzeit ins Leere, wird aber noch an anderen Stellen im Netz genannt.', 'Interessenten, die dieser Spur folgen, finden Sie dort nicht.', 'die Adresse wieder mit einer aktuellen Seite zu verbinden'],
            'platzhalter_text' => ['Auf Ihrer Website stehen noch Platzhalter- bzw. Mustertexte ({wert}).', 'Besucher lesen das schnell als „hier kümmert sich niemand“ – obwohl Ihr Betrieb längst aktiv ist.', 'echte Texte über Ihr Angebot, kurz und in Ihrer Sprache'],
            'defekte_links' => ['Einige Verweise auf Ihrer Website führen auf Fehlerseiten ({wert}).', 'Wer dort klickt, kommt nicht weiter und muss neu suchen.', 'eine aufgeräumte Seitenstruktur ohne tote Wege'],
            'kaputte_bilder' => ['Einige Bilder auf Ihrer Website werden nicht geladen ({wert}).', 'Leere Bildrahmen wirken unfertig, gerade an Stellen, die Ihr Angebot zeigen sollen.', 'eine neue, schnell ladende Bildstrecke'],
            'langsam_lcp' => ['Auf dem Smartphone braucht Ihre Startseite in unserer Messung etwa {wert}, bis der Hauptinhalt steht.', 'Auf mobilen Verbindungen kann das Besucher abbremsen, bevor sie sehen, was Sie anbieten.', 'optimierte Bilder und ein schlanker Aufbau, damit das Wichtigste sofort da ist'],
            'seite_schwer' => ['Ihre Startseite lädt in unserer Messung rund {wert} Daten.', 'Auf dem Handy unterwegs kann das die Ladezeit spürbar verlängern.', 'Bilder in modernen Formaten und nur das, was die Seite wirklich braucht'],
            'bilder_gross' => ['Einige Bilder auf Ihrer Website sind deutlich größer als nötig ({wert}).', 'Das verlangsamt vor allem die mobile Darstellung.', 'Bilder in modernen Formaten, passend zur Bildschirmgröße'],
            'kein_viewport' => ['Ihre Website ist nicht für Smartphones eingerichtet – sie wird dort verkleinert wie am Computer angezeigt.', 'Besucher müssen zoomen und schieben, um etwas zu lesen oder anzutippen.', 'eine eigene mobile Fassung, die auf dem Handy von selbst gut lesbar ist'],
            'horizontal_scroll' => ['Auf dem Smartphone lässt sich Ihre Seite seitlich verschieben, Inhalte ragen über den Rand.', 'Das wirkt unruhig und erschwert das Lesen.', 'ein sauberes mobiles Layout ohne seitliches Verrutschen'],
            'schrift_klein' => ['Auf dem Smartphone ist ein Teil der Schrift sehr klein ({wert}).', 'Gerade ältere Besucher lesen dann nicht weiter.', 'gut lesbare Schriftgrößen auf allen Geräten'],
            'tap_ziele_klein' => ['Einige Knöpfe und Links sind auf dem Smartphone sehr klein oder liegen dicht beieinander.', 'Man tippt leicht daneben – das kann die Bedienung unnötig bremsen.', 'große, gut treffbare Bedienelemente'],
            'tel_nicht_klickbar' => ['Ihre Telefonnummer steht auf der Seite, lässt sich auf dem Smartphone aber nicht antippen.', 'Wer anrufen möchte, muss die Nummer abschreiben – ein kleiner, aber unnötiger Schritt.', 'eine antippbare Nummer und einen Anruf-Knopf, der auf dem Handy immer erreichbar ist'],
            'kontakt_mobil_weit' => ['Auf dem Smartphone liegt die erste Kontaktmöglichkeit erst weit unten auf der Startseite.', 'Wer schnell anfragen möchte, muss danach suchen.', 'einen direkten Kontakt- bzw. Anruf-Knopf gleich im ersten Bildschirm'],
            'kein_cta_oben' => ['Im ersten sichtbaren Bereich Ihrer Startseite gibt es keinen klaren nächsten Schritt (z. B. „Anfragen“ oder „Reservieren“).', 'Besucher sehen zwar Ihre Seite, aber nicht sofort, was sie jetzt tun können.', 'einen klaren Handlungsknopf im ersten Bildschirm'],
            'speisekarte_nur_pdf' => ['Ihre Speisekarte gibt es nur als PDF-Datei.', 'Auf dem Handy heißt das: herunterladen, zoomen, schieben – viele lesen dann nicht weiter.', 'eine Speisekarte direkt auf der Seite, schnell und handytauglich'],
            'buchung_nur_portal' => ['Gebucht wird bei Ihnen über ein externes Portal, nicht direkt auf Ihrer Seite.', 'Gäste verlassen Ihre Seite, bevor sie buchen, und landen zwischen anderen Angeboten.', 'eine Direktbuchung bzw. Anfrage auf Ihrer eigenen Seite'],
            'keine_sprachversion' => ['Ihre Website gibt es nur in einer Sprache.', 'Gerade Gäste aus dem Ausland finden sich so schwerer zurecht.', 'eine zweite (oder dritte) Sprachfassung mit natürlich klingenden Texten'],
            'title_schwach' => ['Der Seitentitel, den Google anzeigt, sagt wenig über Ihr Angebot und Ihren Ort ({wert}).', 'In den Suchergebnissen fällt Ihr Eintrag dadurch weniger auf.', 'Seitentitel und Beschreibungen, die Leistung und Ort klar nennen'],
            'meta_description_fehlt' => ['Für die Startseite ist keine Beschreibung für Suchmaschinen hinterlegt.', 'Google wählt dann selbst einen Textausschnitt – nicht immer den besten.', 'kurze, treffende Beschreibungen für die wichtigsten Seiten'],
            'ort_fehlt_im_title' => ['Ihr Ort taucht im Seitentitel nicht auf.', 'Für Suchen wie „{branche} {stadt}“ ist das ein Nachteil.', 'Seitentitel und Inhalte, die lokal gefunden werden'],
            'keine_strukturierten_daten' => ['Ihre Seite enthält keine strukturierten Unternehmensangaben für Suchmaschinen.', 'Öffnungszeiten, Adresse und Telefon lassen sich so schlechter direkt in Google anzeigen.', 'strukturierte Daten für Ihr Unternehmen'],
            'impressum_fehlt' => ['Wir haben auf Ihrer Website kein Impressum gefunden.', 'Neben dem Vertrauen der Besucher ist das in Deutschland auch eine Pflichtangabe.', 'ein vollständiges Impressum und eine aktuelle Datenschutzerklärung'],
            'piva_fehlt' => ['Auf Ihrer Website haben wir keine Partita IVA gefunden.', 'Für Unternehmensseiten in Italien ist sie vorgeschrieben – und sie schafft Vertrauen.', 'einen vollständigen Fußbereich mit allen Pflichtangaben'],
            'datenschutz_fehlt' => ['Wir haben keine Datenschutzerklärung auf Ihrer Seite gefunden.', 'Besucher und Formulare brauchen diese Grundlage.', 'eine aktuelle Datenschutzerklärung'],
            'copyright_alt' => ['Im Fußbereich Ihrer Website steht noch das Jahr {wert}.', 'Das kann den Eindruck erwecken, die Seite werde nicht mehr gepflegt.', 'eine Seite, die sichtbar aktuell ist und sich leicht pflegen lässt'],
            'keine_bewertungen' => ['Auf Ihrer Website sind keine Kundenstimmen oder Bewertungen zu sehen.', 'Dabei sind gerade sie für neue Kunden ein starkes Argument.', 'echte Kundenstimmen gut sichtbar einzubinden'],
        ],
        'it' => [
            'kein_https' => ['Il vostro sito viene caricato senza connessione protetta (HTTPS).', 'I browser segnalano queste pagine come “non sicure” e questo può far perdere fiducia già alla prima visita.', 'un passaggio completo a HTTPS con il reindirizzamento di tutti i vecchi indirizzi'],
            'https_auf_http' => ['Chi apre il vostro sito con https:// viene rimandato alla versione non protetta.', 'Il browser mostra così “non sicuro”, anche se un certificato sarebbe facilmente disponibile.', 'un passaggio completo a HTTPS con il reindirizzamento di tutti i vecchi indirizzi'],
            'ssl_fehler' => ['Aprendo il vostro sito, il browser segnala un problema con il certificato di sicurezza.', 'Molti visitatori si fermano davanti a quell’avviso, prima ancora di vedere i vostri contenuti.', 'un certificato valido che si rinnova da solo'],
            'seite_nicht_erreichbar' => ['Durante le nostre verifiche il vostro sito non era raggiungibile.', 'Chi vi cerca su Google o su consiglio di qualcuno non trova nulla.', 'un sito stabile e seguito, con un controllo che segnala subito eventuali interruzioni'],
            'domain_tot' => ['L’indirizzo {domain} al momento non porta a nessuna pagina, ma è ancora citato in altri punti del web.', 'Chi segue quel link non vi trova.', 'ricollegare l’indirizzo a un sito aggiornato'],
            'platzhalter_text' => ['Sul vostro sito sono ancora presenti testi segnaposto o di esempio ({wert}).', 'Per chi visita, questo può sembrare un sito abbandonato, anche se la vostra attività è pienamente operativa.', 'testi veri sulla vostra offerta, brevi e chiari'],
            'defekte_links' => ['Alcuni collegamenti del sito portano a pagine di errore ({wert}).', 'Chi ci clica non va avanti e deve ricominciare a cercare.', 'una struttura ordinata, senza percorsi interrotti'],
            'kaputte_bilder' => ['Alcune immagini del sito non vengono caricate ({wert}).', 'Riquadri vuoti danno un’impressione di lavoro non finito, proprio dove dovreste mostrare ciò che offrite.', 'una galleria nuova, veloce da caricare'],
            'langsam_lcp' => ['Sullo smartphone, nella nostra misurazione, la home page impiega circa {wert} prima che il contenuto principale sia visibile.', 'Con una connessione mobile questo può rallentare chi vi visita, prima ancora di capire cosa offrite.', 'immagini ottimizzate e una struttura leggera, così l’essenziale appare subito'],
            'seite_schwer' => ['Nella nostra misurazione la home page carica circa {wert} di dati.', 'In mobilità questo può allungare sensibilmente i tempi di caricamento.', 'immagini in formati moderni e solo ciò che serve davvero alla pagina'],
            'bilder_gross' => ['Alcune immagini del sito sono molto più pesanti del necessario ({wert}).', 'Questo rallenta soprattutto la visualizzazione da smartphone.', 'immagini in formati moderni, adatte alla dimensione dello schermo'],
            'kein_viewport' => ['Il sito non è impostato per gli smartphone: viene mostrato rimpicciolito, come su un computer.', 'Per leggere o toccare qualcosa bisogna ingrandire e scorrere.', 'una versione mobile vera, leggibile da sola sul telefono'],
            'horizontal_scroll' => ['Sullo smartphone la pagina si sposta di lato e alcuni contenuti escono dal bordo.', 'Questo rende la lettura scomoda.', 'un layout mobile pulito, che non scivola di lato'],
            'schrift_klein' => ['Sullo smartphone una parte del testo è molto piccola ({wert}).', 'Soprattutto chi è meno giovane rinuncia a leggere.', 'dimensioni del testo ben leggibili su ogni dispositivo'],
            'tap_ziele_klein' => ['Alcuni pulsanti e link sono molto piccoli o troppo vicini tra loro sullo smartphone.', 'È facile toccare quello sbagliato e questo rende l’uso meno immediato.', 'pulsanti grandi e facili da toccare'],
            'tel_nicht_klickbar' => ['Il vostro numero di telefono è sul sito, ma dallo smartphone non si può toccare per chiamare.', 'Chi vuole telefonare deve ricopiarlo: un passaggio piccolo, ma inutile.', 'un numero cliccabile e un pulsante “Chiama” sempre a portata di mano'],
            'kontakt_mobil_weit' => ['Sullo smartphone il primo modo per contattarvi compare solo molto in basso nella home page.', 'Chi vuole scrivervi o chiamarvi in fretta deve cercarlo.', 'un pulsante di contatto o di chiamata già nella prima schermata'],
            'kein_cta_oben' => ['Nella prima parte visibile della home page non c’è un passo successivo chiaro (per esempio “Prenota” o “Richiedi informazioni”).', 'Chi arriva vede il sito, ma non capisce subito cosa può fare.', 'un pulsante d’azione chiaro già nella prima schermata'],
            'speisekarte_nur_pdf' => ['Il vostro menù è disponibile solo come file PDF.', 'Dal telefono significa scaricare, ingrandire, scorrere: molti rinunciano.', 'un menù direttamente sulla pagina, veloce e comodo da smartphone'],
            'buchung_nur_portal' => ['Le prenotazioni passano da un portale esterno, non dal vostro sito.', 'Gli ospiti lasciano il vostro sito prima di prenotare e finiscono in mezzo ad altre offerte.', 'una prenotazione o richiesta diretta sul vostro sito'],
            'keine_sprachversion' => ['Il vostro sito è disponibile in una sola lingua.', 'Gli ospiti stranieri si orientano così con più difficoltà.', 'una seconda (o terza) lingua con testi naturali, non tradotti a macchina'],
            'title_schwach' => ['Il titolo che Google mostra per il vostro sito dice poco sulla vostra offerta e sulla località ({wert}).', 'Nei risultati di ricerca il vostro sito si nota di meno.', 'titoli e descrizioni che indichino chiaramente servizio e località'],
            'meta_description_fehlt' => ['Per la home page non è impostata una descrizione per i motori di ricerca.', 'Google sceglie allora da solo un estratto, non sempre il migliore.', 'descrizioni brevi e mirate per le pagine principali'],
            'ort_fehlt_im_title' => ['La vostra località non compare nel titolo del sito.', 'Per ricerche come “{branche} {stadt}” è uno svantaggio.', 'titoli e contenuti pensati per farvi trovare in zona'],
            'keine_strukturierten_daten' => ['Il sito non contiene dati strutturati sull’attività per i motori di ricerca.', 'Orari, indirizzo e telefono si mostrano così con più difficoltà direttamente su Google.', 'dati strutturati per la vostra attività'],
            'impressum_fehlt' => ['Non abbiamo trovato sul sito una pagina con i dati dell’azienda.', 'Oltre alla fiducia dei visitatori, sono informazioni che la legge richiede.', 'un’area completa con tutti i dati obbligatori'],
            'piva_fehlt' => ['Sul vostro sito non abbiamo trovato la Partita IVA.', 'Per i siti aziendali in Italia è obbligatoria e trasmette anche fiducia.', 'un piè di pagina completo con tutti i dati obbligatori'],
            'datenschutz_fehlt' => ['Non abbiamo trovato una privacy policy sul vostro sito.', 'Visitatori e moduli di contatto ne hanno bisogno.', 'un’informativa privacy aggiornata'],
            'copyright_alt' => ['Nel piè di pagina del sito compare ancora l’anno {wert}.', 'Può dare l’impressione che il sito non venga più curato.', 'un sito visibilmente aggiornato e facile da gestire'],
            'keine_bewertungen' => ['Sul sito non si vedono recensioni o opinioni dei clienti.', 'Eppure sono uno degli argomenti più forti per chi vi scopre per la prima volta.', 'mettere bene in evidenza le recensioni reali'],
        ],
        'en' => [
            'kein_https' => ['Your website is served without an encrypted connection (HTTPS).', 'Browsers label such pages “not secure”, which can cost trust on a first visit.', 'a clean switch to HTTPS, with all old addresses redirected'],
            'https_auf_http' => ['Visitors who open your site with https:// are sent back to the unencrypted version.', 'The browser then shows “not secure”, although a certificate would be easy to set up.', 'a clean switch to HTTPS, with all old addresses redirected'],
            'ssl_fehler' => ['When opening your website, the browser reports a problem with the security certificate.', 'Many visitors stop at that warning before they ever see your content.', 'a valid certificate that renews itself'],
            'seite_nicht_erreichbar' => ['Your website could not be reached during our checks.', 'People looking for you via Google or a recommendation find nothing.', 'a stable, maintained site with monitoring that reports outages at once'],
            'domain_tot' => ['The address {domain} currently leads nowhere, but it is still mentioned elsewhere online.', 'Anyone following that link will not find you.', 'reconnecting the address to an up-to-date site'],
            'platzhalter_text' => ['Your website still shows placeholder or sample text ({wert}).', 'Visitors may read that as an abandoned site, even though your business is fully active.', 'real copy about what you offer, short and clear'],
            'defekte_links' => ['Some links on your website lead to error pages ({wert}).', 'Whoever clicks them gets stuck and has to start searching again.', 'a tidy structure without dead ends'],
            'kaputte_bilder' => ['Some images on your website do not load ({wert}).', 'Empty frames look unfinished, right where you want to show what you offer.', 'a new, fast-loading gallery'],
            'langsam_lcp' => ['On a smartphone, your home page took about {wert} in our test before the main content appeared.', 'On mobile connections this can slow visitors down before they see what you offer.', 'optimised images and a lean build so the essentials appear at once'],
            'seite_schwer' => ['Your home page loads about {wert} of data in our test.', 'On the go this can noticeably lengthen loading times.', 'images in modern formats and only what the page really needs'],
            'bilder_gross' => ['Some images on your website are much larger than necessary ({wert}).', 'This mainly slows down the mobile view.', 'images in modern formats, sized to the screen'],
            'kein_viewport' => ['Your website is not set up for smartphones – it is shown shrunk, like on a desktop.', 'Visitors have to zoom and scroll to read or tap anything.', 'a real mobile version that is easy to read on its own'],
            'horizontal_scroll' => ['On a smartphone the page shifts sideways and content spills over the edge.', 'That makes reading awkward.', 'a clean mobile layout that stays in place'],
            'schrift_klein' => ['On a smartphone part of the text is very small ({wert}).', 'Older visitors in particular tend to stop reading.', 'readable text sizes on every device'],
            'tap_ziele_klein' => ['Some buttons and links are very small or close together on a smartphone.', 'It is easy to tap the wrong one, which makes the site less straightforward to use.', 'large buttons that are easy to hit'],
            'tel_nicht_klickbar' => ['Your phone number is on the site, but it cannot be tapped to call on a smartphone.', 'Anyone who wants to call has to copy it by hand – a small but unnecessary step.', 'a tappable number and a call button that is always within reach'],
            'kontakt_mobil_weit' => ['On a smartphone the first way to contact you appears far down the home page.', 'Visitors who want to get in touch quickly have to look for it.', 'a contact or call button right on the first screen'],
            'kein_cta_oben' => ['The first visible part of your home page has no clear next step (for example “Book” or “Enquire”).', 'Visitors see your site but not immediately what they can do next.', 'a clear call-to-action on the first screen'],
            'speisekarte_nur_pdf' => ['Your menu is only available as a PDF file.', 'On a phone that means download, zoom, scroll – many give up.', 'a menu right on the page, fast and phone-friendly'],
            'buchung_nur_portal' => ['Bookings go through an external portal rather than your own site.', 'Guests leave your site before booking and end up among other offers.', 'direct booking or enquiry on your own site'],
            'keine_sprachversion' => ['Your website is available in one language only.', 'Guests from abroad find their way around less easily.', 'a second (or third) language with natural-sounding copy'],
            'title_schwach' => ['The page title Google shows says little about your offer and location ({wert}).', 'Your listing stands out less in search results.', 'titles and descriptions that clearly name service and place'],
            'meta_description_fehlt' => ['The home page has no description set for search engines.', 'Google then picks an excerpt itself – not always the best one.', 'short, focused descriptions for the main pages'],
            'ort_fehlt_im_title' => ['Your town does not appear in the page title.', 'For searches like “{branche} {stadt}” that is a disadvantage.', 'titles and content built to be found locally'],
            'keine_strukturierten_daten' => ['Your site contains no structured business data for search engines.', 'Opening hours, address and phone are harder to show directly in Google.', 'structured data for your business'],
            'impressum_fehlt' => ['We could not find a legal notice (company details) on your website.', 'Besides visitor trust, this is legally required information.', 'complete legal details and an up-to-date privacy policy'],
            'piva_fehlt' => ['We could not find a VAT number (Partita IVA) on your website.', 'It is mandatory for business sites in Italy and also builds trust.', 'a complete footer with all required details'],
            'datenschutz_fehlt' => ['We could not find a privacy policy on your site.', 'Visitors and contact forms need that basis.', 'an up-to-date privacy policy'],
            'copyright_alt' => ['The footer of your website still shows the year {wert}.', 'That can suggest the site is no longer looked after.', 'a site that is visibly current and easy to maintain'],
            'keine_bewertungen' => ['No customer reviews or testimonials are visible on your website.', 'Yet they are one of the strongest arguments for new customers.', 'featuring genuine reviews prominently'],
        ],
    ];

    /** Was einer Branche fehlt: fehlt_<x>. Beobachtung, Wirkung, Loesung. */
    private const FEHLT = [
        'de' => [
            'reservierung' => ['eine Möglichkeit, direkt einen Tisch zu reservieren', 'Wer spontan reservieren möchte, muss dafür einen anderen Weg suchen.', 'eine Tischreservierung direkt auf der Seite'],
            'speisekarte' => ['eine Speisekarte', 'Gäste entscheiden oft anhand der Karte, ob sie kommen.', 'eine gut lesbare Speisekarte auf der Seite'],
            'oeffnungszeiten' => ['Ihre Öffnungszeiten', 'Die Frage „Haben Sie heute offen?“ bleibt so unbeantwortet.', 'Öffnungszeiten gut sichtbar – auch für Google'],
            'anfahrt' => ['eine Anfahrtsbeschreibung oder Karte', 'Wer Sie besuchen möchte, muss den Weg anderswo suchen.', 'eine Karte mit direktem Routen-Knopf'],
            'buchung' => ['eine Möglichkeit, direkt online zu buchen', 'Wer spontan buchen möchte, muss den Weg dorthin erst selbst suchen.', 'eine Direktbuchung auf Ihrer eigenen Seite'],
            'zimmer' => ['eine Übersicht Ihrer Zimmer bzw. Unterkünfte', 'Gäste möchten vor der Anfrage sehen, wo sie wohnen werden.', 'eine ansprechende Zimmerübersicht mit großen Bildern'],
            'galerie' => ['eine Bildergalerie', 'Gerade hier entscheiden Bilder oft mit.', 'eine hochwertige Bildstrecke'],
            'leistungen' => ['eine klare Übersicht Ihrer Leistungen', 'Besucher erkennen nicht sofort, was Sie genau anbieten.', 'eine klare Leistungsübersicht'],
            'referenzen' => ['Projektbeispiele oder Referenzen', 'Gerade Referenzen zeigen neuen Kunden, was Sie können.', 'eine Projektgalerie mit Ihren Arbeiten'],
            'angebot_cta' => ['einen direkten Weg zur Angebotsanfrage', 'Wer ein Angebot möchte, muss erst suchen, wie.', 'einen klaren Knopf „Angebot anfragen“'],
            'objekte' => ['eine übersichtliche Darstellung Ihres Angebots bzw. Ihrer Objekte', 'Interessenten sehen nicht auf einen Blick, was verfügbar ist.', 'eine übersichtliche Objektdarstellung mit großen Bildern'],
            'termin' => ['eine Möglichkeit, online einen Termin anzufragen', 'Wer einen Termin möchte, muss anrufen – auch außerhalb Ihrer Zeiten.', 'eine Terminanfrage direkt auf der Seite'],
            'preise' => ['Preisangaben oder eine Preisübersicht', 'Viele fragen erst an, wenn sie grob wissen, was sie erwartet.', 'eine klare Übersicht Ihrer Leistungen mit Preisrahmen'],
            'produkte' => ['eine Übersicht Ihrer Produkte', 'Besucher sehen nicht, was es bei Ihnen gibt.', 'eine ansprechende Produktübersicht'],
            'shop' => ['eine Möglichkeit, online zu bestellen', 'Wer Ihre Produkte von weiter weg kaufen möchte, findet keinen Weg.', 'eine einfache Bestellmöglichkeit'],
            'bestellung' => ['eine Vorbestellmöglichkeit', 'Gerade vor Feiertagen fragen Kunden danach.', 'eine einfache Vorbestellung'],
            'sprachen' => ['eine zweite Sprachfassung', 'Gäste aus dem Ausland finden sich so schwerer zurecht.', 'Sprachfassungen mit natürlichen Texten'],
            'einsatzgebiet' => ['eine Angabe, in welchem Gebiet Sie arbeiten', 'Interessenten wissen nicht, ob Sie zu ihnen kommen.', 'ein klar benanntes Einsatzgebiet'],
            'zertifikate' => ['Hinweise auf Zertifikate oder Qualifikationen', 'Solche Nachweise schaffen bei neuen Kunden Vertrauen.', 'Ihre Nachweise gut sichtbar einzubinden'],
            'team' => ['eine Vorstellung Ihres Teams', 'Menschen möchten wissen, mit wem sie es zu tun haben.', 'eine persönliche Team-Seite'],
            'probetraining' => ['eine Möglichkeit, ein Probetraining zu vereinbaren', 'Der einfachste Einstieg für neue Mitglieder fehlt.', 'einen klaren Knopf für ein Probetraining'],
            'kurse' => ['einen Kursplan', 'Interessierte sehen nicht, wann was stattfindet.', 'einen übersichtlichen Kursplan'],
            'suche' => ['eine Such- oder Filterfunktion', 'Interessenten müssen sich durch alles klicken.', 'eine einfache Suche mit Filtern'],
            'angebote' => ['eine Übersicht Ihrer Angebote bzw. Touren', 'Besucher sehen nicht auf einen Blick, was sie buchen können.', 'eine klare Angebotsübersicht mit Buchungsweg'],
        ],
        'it' => [
            'reservierung' => ['un modo per prenotare direttamente un tavolo', 'Chi vuole prenotare al momento deve cercare un’altra strada.', 'una prenotazione del tavolo direttamente sul sito'],
            'speisekarte' => ['il menù', 'Spesso è proprio il menù a far decidere se venire.', 'un menù ben leggibile sul sito'],
            'oeffnungszeiten' => ['gli orari di apertura', 'La domanda “siete aperti oggi?” resta senza risposta.', 'orari ben visibili, anche per Google'],
            'anfahrt' => ['indicazioni o una mappa per raggiungervi', 'Chi vuole venire deve cercare la strada altrove.', 'una mappa con il pulsante per le indicazioni'],
            'buchung' => ['un modo per prenotare direttamente online', 'Chi vuole prenotare al momento deve prima cercare come fare.', 'una prenotazione diretta sul vostro sito'],
            'zimmer' => ['una presentazione delle camere o degli alloggi', 'Gli ospiti vogliono vedere dove dormiranno prima di chiedere.', 'una presentazione delle camere con immagini grandi'],
            'galerie' => ['una galleria fotografica', 'Qui le immagini spesso fanno la differenza.', 'una galleria di qualità'],
            'leistungen' => ['una panoramica chiara dei vostri servizi', 'Chi arriva non capisce subito cosa offrite esattamente.', 'una panoramica chiara dei servizi'],
            'referenzen' => ['esempi di lavori o referenze', 'Sono proprio i lavori realizzati a mostrare ai nuovi clienti cosa sapete fare.', 'una galleria dei vostri lavori'],
            'angebot_cta' => ['un modo diretto per chiedere un preventivo', 'Chi desidera un preventivo deve prima capire come fare.', 'un pulsante chiaro “Richiedi un preventivo”'],
            'objekte' => ['una presentazione chiara della vostra offerta o dei vostri immobili', 'Chi è interessato non vede a colpo d’occhio cosa è disponibile.', 'una presentazione chiara con immagini grandi'],
            'termin' => ['un modo per chiedere un appuntamento online', 'Chi desidera un appuntamento deve telefonare, anche fuori orario.', 'una richiesta di appuntamento direttamente sul sito'],
            'preise' => ['indicazioni di prezzo o un listino', 'Molti chiedono informazioni solo quando sanno più o meno cosa aspettarsi.', 'una panoramica chiara dei servizi con fasce di prezzo'],
            'produkte' => ['una panoramica dei vostri prodotti', 'Chi visita non vede cosa potete offrire.', 'una bella presentazione dei prodotti'],
            'shop' => ['un modo per ordinare online', 'Chi vuole acquistare i vostri prodotti da lontano non trova la strada.', 'un sistema di ordinazione semplice'],
            'bestellung' => ['un modo per ordinare in anticipo', 'Soprattutto prima delle feste i clienti lo chiedono.', 'una prenotazione degli ordini semplice'],
            'sprachen' => ['una seconda lingua', 'Gli ospiti stranieri si orientano con più difficoltà.', 'versioni in altre lingue con testi naturali'],
            'einsatzgebiet' => ['l’indicazione della zona in cui lavorate', 'Chi è interessato non sa se arrivate fino a lui.', 'una zona di intervento indicata chiaramente'],
            'zertifikate' => ['riferimenti a certificazioni o qualifiche', 'Sono prove che danno fiducia ai nuovi clienti.', 'mettere in evidenza certificazioni e qualifiche'],
            'team' => ['una presentazione del vostro team', 'Le persone vogliono sapere con chi avranno a che fare.', 'una pagina del team, personale'],
            'probetraining' => ['un modo per prenotare una prova gratuita', 'Manca il primo passo più semplice per i nuovi iscritti.', 'un pulsante chiaro per la prova'],
            'kurse' => ['il calendario dei corsi', 'Chi è interessato non vede quando si svolge cosa.', 'un calendario dei corsi chiaro'],
            'suche' => ['una ricerca o dei filtri', 'Chi cerca deve scorrere tutto a mano.', 'una ricerca semplice con filtri'],
            'angebote' => ['una panoramica delle vostre offerte o escursioni', 'Chi visita non vede subito cosa può prenotare.', 'una panoramica chiara delle offerte con percorso di prenotazione'],
        ],
        'en' => [
            'reservierung' => ['a way to book a table directly', 'Anyone who wants to reserve on the spot has to find another way.', 'table booking right on the site'],
            'speisekarte' => ['a menu', 'Guests often decide by the menu whether to come.', 'an easy-to-read menu on the site'],
            'oeffnungszeiten' => ['your opening hours', '“Are you open today?” stays unanswered.', 'opening hours clearly visible, also for Google'],
            'anfahrt' => ['directions or a map', 'Visitors have to look up the way elsewhere.', 'a map with a direct route button'],
            'buchung' => ['a way to book directly online', 'Anyone who wants to book on the spot first has to work out how.', 'direct booking on your own site'],
            'zimmer' => ['an overview of your rooms or accommodation', 'Guests want to see where they will stay before they enquire.', 'an appealing room overview with large images'],
            'galerie' => ['a photo gallery', 'Here, pictures often make the decision.', 'a high-quality gallery'],
            'leistungen' => ['a clear overview of your services', 'Visitors do not immediately see what exactly you offer.', 'a clear overview of services'],
            'referenzen' => ['project examples or references', 'References are what show new clients what you can do.', 'a gallery of your work'],
            'angebot_cta' => ['a direct way to request a quote', 'Anyone wanting a quote first has to work out how.', 'a clear “Request a quote” button'],
            'objekte' => ['a clear presentation of your listings or offer', 'Prospects cannot see at a glance what is available.', 'a clear listing presentation with large images'],
            'termin' => ['a way to request an appointment online', 'Anyone who wants an appointment has to call, even outside hours.', 'appointment requests right on the site'],
            'preise' => ['prices or a price overview', 'Many only enquire once they roughly know what to expect.', 'a clear service overview with price ranges'],
            'produkte' => ['an overview of your products', 'Visitors cannot see what you offer.', 'an appealing product overview'],
            'shop' => ['a way to order online', 'People who want your products from further away find no route.', 'a simple ordering option'],
            'bestellung' => ['a way to pre-order', 'Customers ask for it especially before holidays.', 'simple pre-ordering'],
            'sprachen' => ['a second language version', 'Guests from abroad find their way around less easily.', 'language versions with natural copy'],
            'einsatzgebiet' => ['the area you cover', 'Prospects do not know whether you come to them.', 'a clearly stated service area'],
            'zertifikate' => ['certificates or qualifications', 'Such proof builds trust with new clients.', 'showing your credentials clearly'],
            'team' => ['an introduction of your team', 'People want to know who they will deal with.', 'a personal team page'],
            'probetraining' => ['a way to book a trial session', 'The easiest first step for new members is missing.', 'a clear trial-session button'],
            'kurse' => ['a class schedule', 'Prospects cannot see what happens when.', 'a clear class schedule'],
            'suche' => ['a search or filter function', 'Visitors have to click through everything.', 'a simple search with filters'],
            'angebote' => ['an overview of your offers or tours', 'Visitors do not see at a glance what they can book.', 'a clear overview of offers with a booking path'],
        ],
    ];

    /** Befunde, die gemessen und von jedem nachpruefbar sind. */
    private const HART = ['domain_tot', 'seite_nicht_erreichbar', 'ssl_fehler', 'kein_https', 'https_auf_http', 'kein_viewport',
        'horizontal_scroll', 'tel_nicht_klickbar', 'langsam_lcp', 'platzhalter_text', 'defekte_links', 'kaputte_bilder',
        'speisekarte_nur_pdf', 'buchung_nur_portal', 'seite_schwer', 'bilder_gross', 'schrift_klein', 'kontakt_mobil_weit',
        'kein_cta_oben', 'copyright_alt', 'title_schwach'];

    /** Verbotene Wendungen (Angstverkauf, Uebertreibung). Kleinbuchstaben, Regex. */
    private const VERBOTEN = [
        '~verlier\w*\s+(jeden tag|täglich|tausende|kunden)~u', '~tausende?\s+euro~u', '~konkurrenz\s+(ist\s+)?(ihnen\s+)?(weit\s+)?voraus~u',
        '~keine kunden gewinnen~u', '~(ihre|die) website ist (schlecht|hässlich|veraltet und)~u', '~sieht (schlecht|furchtbar|schrecklich) aus~u',
        '~perd\w*\s+(clienti|migliaia)~u', '~migliaia di euro~u', '~concorren\w+\s+(è\s+)?(molto\s+)?avanti~u', '~non (potete|può) (acquisire|trovare) clienti~u',
        '~(il vostro|il suo) sito (è|e) (brutto|orribile)~u', '~losing (customers|thousands)~u', '~competitors are (far )?ahead~u', '~garantiert~u', '~garantit~u', '~guarantee~u',
        '~\bsofort handeln\b~u', '~\bagire subito\b~u', '~\blast chance\b~u', '~\bletzte chance\b~u', '~\bultima occasione\b~u',
    ];

    /** Interne Woerter, die nie rausgehen. */
    private const INTERN = ['score', 'opportunity', 'lead', 'unverified', 'verified', 'akquise', 'top opportunity', 'kaltakquise'];

    /* ================================================================== */
    /*  Sprache                                                           */
    /* ================================================================== */

    public static function spracheFuer(array $f): string
    {
        $s = strtolower((string) ($f['sprache'] ?? ''));
        if (isset(self::SPRACHEN[$s])) { return $s; }
        return strtoupper((string) ($f['land'] ?? '')) === 'DE' ? 'de' : 'it';
    }

    /* ================================================================== */
    /*  Regeltext                                                         */
    /* ================================================================== */

    /**
     * Schreibt eine Vorlage aus den belegten Befunden.
     *
     * @param list<array<string,mixed>> $befunde
     * @return array{betreff:string,text:string,verwendet:list<string>}
     */
    public static function erzeugen(array $f, ?array $audit, array $befunde, string $sprache, string $kanal = 'email'): array
    {
        $sprache = isset(self::SPRACHEN[$sprache]) ? $sprache : 'it';
        $belegt = array_values(array_filter(AkquiseScore::topBefunde($befunde),
            static fn($b) => ($b['status'] ?? '') === 'VERIFIED' && self::bausteinFuer($b, $sprache) !== null));
        /* Gemessenes vor Nicht-Gefundenem. "Die Telefonnummer ist nicht
           antippbar" laesst sich mit einem Handy nachpruefen; "wir haben
           keine Partita IVA gefunden" kann an unserer Erkennung liegen
           (so geschehen beim ersten echten Lauf). Im Brief an einen
           Inhaber stehen deshalb zuerst die Befunde, die niemand bestreiten
           kann. */
        usort($belegt, static function ($a, $b) {
            $ha = in_array((string) $a['code'], self::HART, true) ? 1 : 0;
            $hb = in_array((string) $b['code'], self::HART, true) ? 1 : 0;
            return $ha !== $hb ? $hb <=> $ha : ((int) $b['schwere'] <=> (int) $a['schwere']);
        });
        $wahl = array_slice($belegt, 0, 3);
        if (!$wahl) {
            throw new RuntimeException('Es gibt keinen belegten Befund, zu dem sich ein ehrlicher Satz schreiben lässt. '
                . 'Ohne konkrete Beobachtung keine Ansprache.');
        }
        $saat = crc32((string) ($f['kennung'] ?? $f['id'] ?? ''));
        $v = static fn(array $moeglich) => $moeglich[$saat % count($moeglich)];

        $name   = (string) ($f['name'] ?? '');
        $domain = (string) ($f['domain'] ?? '');
        $stadt  = (string) ($f['stadt'] ?? '');
        $person = trim((string) ($f['ansprechpartner'] ?? ''));

        $zeilen = [];
        foreach ($wahl as $i => $b) {
            [$beob, $wirk, $loes] = self::bausteinFuer($b, $sprache);
            $zeilen[] = ['beob' => self::einsetzen($beob, $b, $f, $sprache), 'wirk' => self::einsetzen($wirk, $b, $f, $sprache), 'loes' => $loes];
        }
        $loesungen = array_values(array_unique(array_map(static fn($z) => $z['loes'], $zeilen)));

        $abs = self::absender();
        $link = 'https://www.vecom-design.it';
        $bedarf = 'https://vecom-design.it/zugang.php?lang=' . $sprache;   // seit 24.09.2026 der E-Mail-Einstieg (wie {konfigurator})
        $mitKonfigurator = AkquiseGate::einstellung('akq_konfigurator_link', '1') === '1';
        $b = Akquise::branchen()[(string) ($f['branche'] ?? '')] ?? [];
        /* Die Experience-Idee steht in audit.experience auf Deutsch (fuer Uwe).
           In den Text kommt sie nur, wenn Claude sie in der Sprache der Firma
           mitgeliefert hat (ki.experience_text.<sprache>) -- eine deutsche
           Zeile mitten in einer italienischen Mail waere schlimmer als keine. */
        $expIdee = '';
        $kiDaten = is_string($audit['ki'] ?? null) ? (json_decode((string) $audit['ki'], true) ?: []) : (array) ($audit['ki'] ?? []);
        $expBefund = array_values(array_filter($befunde, static fn($x) => ($x['kategorie'] ?? '') === 'experience' && (int) ($x['schwere'] ?? 0) >= 4));
        if ($expBefund && !empty($kiDaten['experience_text'][$sprache])) { $expIdee = trim((string) $kiDaten['experience_text'][$sprache]); }

        $n = count($zeilen);
        if ($sprache === 'de') {
            $betreff = $v(["Drei Beobachtungen zu {$domain}", "Kurze Rückmeldung zu Ihrer Website {$domain}", "{$name}: ein paar konkrete Punkte zu Ihrer Website"]);
            if ($n < 3) { $betreff = $v(["Eine Beobachtung zu {$domain}", "Kurze Rückmeldung zu Ihrer Website {$domain}"]); }
            $t  = ($person !== '' ? "Guten Tag {$person}," : 'Guten Tag,') . "\n\n";
            $t .= $v([
                "mein Name ist {$abs['inhaber']}, ich betreibe das Webdesign-Studio {$abs['firma']}. Ich habe mir die Website von {$name} angesehen und möchte Ihnen kurz schreiben, was mir dabei aufgefallen ist.",
                "ich bin {$abs['inhaber']} von {$abs['firma']}. Beim Durchsehen von Websites " . ($stadt !== '' ? "aus {$stadt} " : '') . "bin ich auf {$domain} gestoßen – und ein paar Dinge sind mir konkret aufgefallen.",
                "ich schreibe Ihnen, weil mir bei einem Blick auf {$domain} " . ($n === 1 ? 'etwas aufgefallen ist, das sich' : 'einige Punkte aufgefallen sind, die sich') . " mit überschaubarem Aufwand verbessern ließen. Kurz zu mir: {$abs['inhaber']}, {$abs['firma']}.",
            ]) . "\n\n";
            foreach ($zeilen as $i => $z) {
                $t .= ($n > 1 ? ($i + 1) . '. ' : '') . $z['beob'] . ' ' . $z['wirk'] . "\n\n";
            }
            $t .= $v(['Wie wir das angehen würden:', 'Unser Vorschlag dazu:', 'Konkret würden wir hier ansetzen:']) . "\n" . self::liste($loesungen) . "\n\n";
            if ($expIdee !== '') { $t .= "Und falls Sie Lust auf mehr haben: {$expIdee}\n\n"; }
            $t .= "Solche Lösungen entwickeln wir bei {$abs['firma']}. Was wir für Unternehmenswebsites umsetzen, sehen Sie unter {$link}";
            $t .= $mitKonfigurator ? "\nWenn Sie mögen, zeigt Ihnen unser Bedarfsrechner in zwei Minuten und unverbindlich, was für Sie sinnvoll wäre: {$bedarf}\n\n" : "\n\n";
            $t .= $v(['Gern schicke ich Ihnen die Beobachtungen auch ausführlicher, mit Screenshots.', 'Wenn es Sie interessiert, gehe ich die Punkte gern in einem kurzen Gespräch mit Ihnen durch.']) . "\n\n";
            $t .= "Viele Grüße\n{$abs['inhaber']}\n{$abs['firma']} · {$abs['ort']}\n{$abs['email']}" . ($abs['telefon'] !== '' ? " · {$abs['telefon']}" : '') . "\n{$link}\n\n";
            $t .= '— Ihre Kontaktdaten stammen aus öffentlich zugänglichen Quellen (Ihre Website bzw. OpenStreetMap). '
                . 'Wenn Sie keine weitere Nachricht von uns möchten, genügt eine kurze Antwort – dann melden wir uns nicht mehr.';
        } elseif ($sprache === 'it') {
            $betreff = $v(["Tre osservazioni su {$domain}", "Un breve riscontro sul vostro sito {$domain}", "{$name}: alcuni punti concreti sul vostro sito"]);
            if ($n < 3) { $betreff = $v(["Un’osservazione su {$domain}", "Un breve riscontro sul vostro sito {$domain}"]); }
            $t  = ($person !== '' ? "Buongiorno {$person}," : 'Buongiorno,') . "\n\n";
            $t .= $v([
                "mi chiamo {$abs['inhaber']} e seguo lo studio di web design {$abs['firma']}. Ho dato un’occhiata al sito di {$name} e vorrei segnalarvi brevemente cosa ho notato.",
                "sono {$abs['inhaber']} di {$abs['firma']}. Guardando alcuni siti " . ($stadt !== '' ? "di {$stadt} " : '') . "sono arrivato a {$domain} e ho notato alcune cose concrete.",
                "vi scrivo perché, guardando {$domain}, " . ($n === 1 ? 'ho notato un aspetto che si potrebbe migliorare' : 'ho notato alcuni aspetti che si potrebbero migliorare') . " senza grandi interventi. Mi presento: {$abs['inhaber']}, {$abs['firma']}.",
            ]) . "\n\n";
            foreach ($zeilen as $i => $z) {
                $t .= ($n > 1 ? ($i + 1) . '. ' : '') . $z['beob'] . ' ' . $z['wirk'] . "\n\n";
            }
            $t .= $v(['Come lo affronteremmo:', 'La nostra proposta:', 'In concreto partiremmo da qui:']) . "\n" . self::liste($loesungen) . "\n\n";
            if ($expIdee !== '') { $t .= "E se aveste voglia di qualcosa in più: {$expIdee}\n\n"; }
            $t .= "Sono soluzioni che sviluppiamo in {$abs['firma']}. Cosa realizziamo per i siti aziendali lo trovate su {$link}";
            $t .= $mitKonfigurator ? "\nSe vi va, il nostro calcolatore vi mostra in due minuti, senza impegno, cosa avrebbe senso per voi: {$bedarf}\n\n" : "\n\n";
            $t .= $v(['Se vi interessa, vi mando volentieri le osservazioni in modo più dettagliato, con gli screenshot.', 'Se vi fa piacere, possiamo vedere insieme questi punti in una breve telefonata.']) . "\n\n";
            $t .= "Cordiali saluti\n{$abs['inhaber']}\n{$abs['firma']} · {$abs['ort']}\n{$abs['email']}" . ($abs['telefon'] !== '' ? " · {$abs['telefon']}" : '') . "\n{$link}\n\n";
            $t .= '— I vostri recapiti provengono da fonti pubblicamente accessibili (il vostro sito o OpenStreetMap). '
                . 'Se non desiderate ricevere altri messaggi, basta una breve risposta: non vi scriveremo più.';
        } else {
            $betreff = $v(["Three observations about {$domain}", "Quick feedback on your website {$domain}", "{$name}: a few concrete points about your website"]);
            if ($n < 3) { $betreff = $v(["One observation about {$domain}", "Quick feedback on your website {$domain}"]); }
            $t  = ($person !== '' ? "Hello {$person}," : 'Hello,') . "\n\n";
            $t .= $v([
                "my name is {$abs['inhaber']} and I run the web design studio {$abs['firma']}. I had a look at the {$name} website and would like to share briefly what I noticed.",
                "I am {$abs['inhaber']} from {$abs['firma']}. While looking at websites " . ($stadt !== '' ? "in {$stadt} " : '') . "I came across {$domain} and noticed a few concrete things.",
                "I am writing because, looking at {$domain}, I noticed " . ($n === 1 ? 'something that could be improved' : 'a few points that could be improved') . " without much effort. About me: {$abs['inhaber']}, {$abs['firma']}.",
            ]) . "\n\n";
            foreach ($zeilen as $i => $z) {
                $t .= ($n > 1 ? ($i + 1) . '. ' : '') . $z['beob'] . ' ' . $z['wirk'] . "\n\n";
            }
            $t .= $v(['How we would approach it:', 'Our suggestion:', 'Concretely, we would start here:']) . "\n" . self::liste($loesungen) . "\n\n";
            if ($expIdee !== '') { $t .= "And if you fancy something more: {$expIdee}\n\n"; }
            $t .= "These are the kind of solutions we build at {$abs['firma']}. You can see what we do for business websites at {$link}";
            $t .= $mitKonfigurator ? "\nIf you like, our needs calculator shows you in two minutes, with no obligation, what would make sense for you: {$bedarf}\n\n" : "\n\n";
            $t .= $v(['I am happy to send you the observations in more detail, with screenshots.', 'If you are interested, I would be glad to walk you through the points in a short call.']) . "\n\n";
            $t .= "Kind regards\n{$abs['inhaber']}\n{$abs['firma']} · {$abs['ort']}\n{$abs['email']}" . ($abs['telefon'] !== '' ? " · {$abs['telefon']}" : '') . "\n{$link}\n\n";
            $t .= '— Your contact details come from publicly accessible sources (your website or OpenStreetMap). '
                . 'If you would rather not hear from us again, a short reply is enough and we will not write again.';
        }
        return ['betreff' => $betreff, 'text' => $t, 'verwendet' => array_map(static fn($b) => (string) $b['code'], $wahl)];
    }

    /**
     * Beobachtung, Wirkung, Loesung zu einem Befund in der Sprache der Firma
     * -- fuer die Analyse-Seite. Null, wenn es keinen gepflegten Satz gibt:
     * Dann erscheint der Befund dort nicht, statt schief uebersetzt.
     *
     * @return array{0:string,1:string,2:string}|null
     */
    public static function saetze(array $b, array $f, string $sprache): ?array
    {
        $x = self::bausteinFuer($b, $sprache);
        if ($x === null) { return null; }
        return [self::einsetzen($x[0], $b, $f, $sprache), self::einsetzen($x[1], $b, $f, $sprache), $x[2]];
    }

    /** @return array{0:string,1:string,2:string}|null */
    private static function bausteinFuer(array $b, string $sprache): ?array
    {
        $code = (string) ($b['code'] ?? '');
        if (isset(self::SAETZE[$sprache][$code])) { return self::SAETZE[$sprache][$code]; }
        if (str_starts_with($code, 'fehlt_')) {
            $x = substr($code, 6);
            $f = self::FEHLT[$sprache][$x] ?? null;
            if ($f === null) { return null; }
            $beob = match ($sprache) {
                'de' => 'Auf Ihrer Website haben wir ' . $f[0] . ' nicht gefunden.',
                'it' => 'Sul vostro sito non abbiamo trovato ' . $f[0] . '.',
                default => 'We could not find ' . $f[0] . ' on your website.',
            };
            return [$beob, $f[1], $f[2]];
        }
        return null;
    }

    /**
     * Formatiert einen Messwert in der Sprache des Textes.
     *
     * Der Worker liefert Zahlen mit Einheit ({"wert":6.2,"einheit":"s"}),
     * keine fertigen Woerter -- sonst stand in einer italienischen Mail
     * "6,2 Sekunden". Nur 'text' (z. B. ein zitierter Platzhalter wie
     * "Lorem ipsum") geht unveraendert durch.
     */
    public static function messwertText(mixed $m, string $sprache): string
    {
        if (is_string($m)) { $j = json_decode($m, true); $m = is_array($j) ? $j : $m; }
        if (!is_array($m)) { return is_scalar($m) ? (string) $m : ''; }
        if (isset($m['wert'], $m['einheit']) && is_numeric($m['wert'])) {
            $w = (float) $m['wert'];
            $zahl = static fn(float $x, int $st) => $sprache === 'en'
                ? number_format($x, $st, '.', ',') : number_format($x, $st, ',', '.');
            return match ((string) $m['einheit']) {
                's'      => $zahl($w, 1) . ' ' . ['de' => 'Sekunden', 'it' => 'secondi', 'en' => 'seconds'][$sprache],
                'MB'     => $zahl($w, 1) . ' MB',
                'KB'     => $zahl($w, 0) . ' KB',
                'px'     => $zahl($w, 0) . ' px',
                'anzahl' => (string) (int) $w,
                'jahr'   => (string) (int) $w,
                default  => $zahl($w, 1),
            };
        }
        return isset($m['text']) ? (string) $m['text'] : '';
    }

    private static function einsetzen(string $satz, array $b, array $f, string $sprache): string
    {
        $wert = self::messwertText($b['messwert'] ?? null, $sprache);
        if ($wert === '' && str_contains($satz, '({wert})')) { $satz = str_replace(' ({wert})', '', $satz); }
        $branche = Akquise::branchenName((string) ($f['branche'] ?? ''), $sprache);
        return strtr($satz, [
            '{wert}' => $wert, '{domain}' => (string) ($f['domain'] ?? ''),
            '{stadt}' => (string) ($f['stadt'] ?? ''), '{branche}' => mb_strtolower($branche),
        ]);
    }

    /** Loesungen als kurze Liste -- drei Vorschlaege in einem Satz lesen sich wie ein Vertrag. */
    private static function liste(array $teile): string
    {
        return implode("\n", array_map(static fn($t) => '– ' . mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1), $teile));
    }

    public static function absender(): array
    {
        require_once __DIR__ . '/Firma.php';
        return [
            'inhaber' => Firma::get('inhaber', 'Uwe Vetter'),
            'firma'   => Firma::get('name', 'Vecom Design'),
            'ort'     => Firma::get('ort', 'Aragona (AG)'),
            'email'   => Firma::get('email', 'kontakt@vecom-design.it'),
            'telefon' => Firma::get('telefon', AkquiseGate::einstellung('akq_absender_telefon', '')),
        ];
    }

    /* ================================================================== */
    /*  Pruefen                                                           */
    /* ================================================================== */

    /**
     * Was an einem Text nicht stimmt. Leere Liste = freigabefaehig.
     *
     * @param list<array<string,mixed>> $befunde die Befunde des zugehoerigen Audits
     * @return list<string>
     */
    public static function pruefen(string $betreff, string $text, string $sprache, array $befunde, string $kanal = 'email', array $f = []): array
    {
        $fehler = [];
        $alles = mb_strtolower($betreff . "\n" . $text);
        foreach (self::VERBOTEN as $muster) {
            if (preg_match($muster, $alles, $m)) { $fehler[] = 'Angst- oder Übertreibungsformulierung: „' . $m[0] . '“'; }
        }
        foreach (self::INTERN as $wort) {
            if (preg_match('~\b' . preg_quote($wort, '~') . '\b~u', $alles)) { $fehler[] = 'Internes Wort im Text: „' . $wort . '“'; }
        }
        if (preg_match('~(\d[\d.,]*\s*(€|eur\b|euro))|((€|eur)\s*\d)~u', $alles, $m)) {
            $fehler[] = 'Preisangabe in der Erstansprache: „' . trim($m[0]) . '“';
        }
        if (!str_contains($alles, 'vecom-design.it')) { $fehler[] = 'Der Link auf vecom-design.it fehlt.'; }
        $widerspruch = ['de' => ['keine weitere', 'nicht mehr', 'abmelden'], 'it' => ['non desiderate', 'non vi scriveremo', 'cancell'],
                        'en' => ['rather not', 'not write again', 'unsubscribe']][$sprache] ?? [];
        $hat = false;
        foreach ($widerspruch as $w) { if (str_contains($alles, $w)) { $hat = true; break; } }
        if (!$hat) { $fehler[] = 'Der Hinweis, wie man weitere Nachrichten ablehnt, fehlt.'; }

        // Jede Zahl muss belegt sein.
        $belegt = mb_strtolower(implode(' ', array_map(static fn($b) =>
            (string) ($b['titel'] ?? '') . ' ' . (string) ($b['beleg'] ?? '') . ' ' . (string) (is_string($b['messwert'] ?? null) ? $b['messwert'] : json_encode($b['messwert'] ?? '')),
            $befunde)));
        $erlaubt = self::erlaubteZahlen();
        // Zahlen aus Name, Adresse und Domain der Firma sind keine Behauptung.
        preg_match_all('~\d+(?:[.,]\d+)?~u', implode(' ', [(string) ($f['name'] ?? ''), (string) ($f['domain'] ?? ''),
            (string) ($f['adresse'] ?? ''), (string) ($f['plz'] ?? ''), (string) ($f['stadt'] ?? ''), (string) ($f['url'] ?? '')]), $eigene);
        $erlaubt = array_merge($erlaubt, $eigene[0]);
        preg_match_all('~\d+(?:[.,]\d+)?~u', $text, $treffer);
        foreach (array_unique($treffer[0]) as $zahl) {
            if (in_array($zahl, $erlaubt, true)) { continue; }
            $norm = str_replace(',', '.', $zahl);
            if (str_contains($belegt, $zahl) || str_contains($belegt, $norm)) { continue; }
            $fehler[] = 'Zahl ohne Beleg: „' . $zahl . '“ — sie steht in keinem Messwert.';
        }
        if (mb_strlen($text) > 3500) { $fehler[] = 'Der Text ist zu lang (' . mb_strlen($text) . ' Zeichen, höchstens 3500).'; }
        if (mb_strlen(trim($betreff)) === 0 && $kanal === 'email') { $fehler[] = 'Der Betreff fehlt.'; }
        return array_values(array_unique($fehler));
    }

    /** Zahlen, die im Absenderblock und in Links vorkommen duerfen. */
    private static function erlaubteZahlen(): array
    {
        $abs = self::absender();
        preg_match_all('~\d+(?:[.,]\d+)?~u', implode(' ', $abs), $m);
        return array_merge($m[0], ['1', '2', '3']);  // Aufzaehlung, "in zwei Minuten" ist ausgeschrieben
    }

    public static function fingerabdruck(string $betreff, string $text): string
    {
        $norm = mb_strtolower(preg_replace('~\s+~u', ' ', $betreff . '|' . $text) ?? '');
        return hash('sha256', $norm);
    }

    /* ================================================================== */
    /*  Antworten einordnen                                               */
    /* ================================================================== */

    /** Reihenfolge ist Absicht: "non sono interessato" ist kein INTERESTED. */
    private const ANTWORT_MUSTER = [
        'INVALID_ADDRESS' => ['mailer-daemon', 'undeliverable', 'delivery status notification', 'unzustellbar', 'non recapitabile', 'address not found', 'user unknown', 'recipient address rejected', 'mailbox unavailable'],
        'OUT_OF_OFFICE'   => ['out of office', 'abwesenheitsnotiz', 'bin abwesend', 'im urlaub', 'automatische antwort', 'risposta automatica', 'fuori ufficio', 'fuori sede', 'sono assente', 'in ferie', 'automatic reply', 'auto-reply'],
        'DO_NOT_CONTACT'  => ['abmelden', 'keine weiteren', 'nicht mehr kontaktieren', 'nicht mehr anschreiben', 'unsubscribe', 'remove me', 'cancellami', 'cancellatemi', 'non contattatemi', 'non contattarmi', 'non scrivetemi', 'rimuovete', 'diffida', 'stop', 'spam', 'datenschutz', 'garante'],
        'NOT_INTERESTED'  => ['kein interesse', 'nicht interessiert', 'kein bedarf', 'brauchen wir nicht', 'non sono interessat', 'non siamo interessat', 'non ci interessa', 'non mi interessa', 'no grazie', 'not interested', 'no thanks', 'no thank you'],
        'PRICE_REQUEST'   => ['was kostet', 'kosten', 'preis', 'angebot', 'quanto costa', 'prezzo', 'costo', 'preventivo', 'how much', 'price', 'quote', 'cost'],
        'CALL_REQUEST'    => ['rufen sie', 'anrufen', 'telefonieren', 'rückruf', 'chiamatemi', 'chiamami', 'telefonata', 'sentirci', 'call me', 'give me a call', 'phone call'],
        'MORE_INFO'       => ['mehr informationen', 'mehr details', 'genauer', 'screenshots', 'più informazioni', 'maggiori informazioni', 'dettagli', 'more information', 'more details', 'tell me more'],
        'INTERESTED'      => ['interessiert', 'interessant', 'gerne', 'klingt gut', 'interessat', 'volentieri', 'mi interessa', 'ci interessa', 'interested', 'sounds good', 'let\'s talk'],
    ];

    public static function klassifizieren(string $betreff, string $text): string
    {
        $t = ' ' . mb_strtolower($betreff . ' ' . $text) . ' ';
        foreach (self::ANTWORT_MUSTER as $klasse => $muster) {
            foreach ($muster as $m) {
                if (preg_match('~(?<![\p{L}])' . preg_quote($m, '~') . '~u', $t)) { return $klasse; }
            }
        }
        return 'OTHER';
    }
}
