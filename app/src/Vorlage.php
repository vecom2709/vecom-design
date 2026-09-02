<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Fmt.php';

/**
 * Vorlagen fuer Nachrichten an den Kunden — und der Betreff dazu.
 *
 * WARUM ES SIE GIBT
 *
 * Vorher gab es vier Vorlagen und einen einzigen Betreff: "Eine Nachricht zu
 * deinem Projekt". Zehn Nachrichten, zehn gleiche Betreffzeilen. Der Kunde
 * findet nichts wieder, und gleichlautende Serienbetreffe sind ein Merkmal,
 * auf das Spamfilter achten.
 *
 * WARUM DREISPRACHIG
 *
 * Der freie Text geht woertlich raus. Wer deutsch an eine italienische Kundin
 * schreibt, schickt ihr deutsch — nur der Rahmen ist italienisch. Eine Vorlage
 * kennt die Sprache des Kunden und setzt den richtigen Text ein.
 *
 * WAS HIER NICHT DRINSTEHT
 *
 * Anrede und Gruss. Die haengen an der Sprache, nicht am Anlass, und stuenden
 * sonst zweiundvierzig Mal in dieser Datei. fuer() setzt sie davor und
 * dahinter.
 */
final class Vorlage
{
    public const GRUPPEN = [
        'vorher'   => 'Vor dem Auftrag',
        'waehrend' => 'Während der Arbeit',
        'danach'   => 'Danach',
    ];

    private const ANREDE = ['it' => 'Ciao', 'de' => 'Hallo', 'en' => 'Hello'];
    private const GRUSS  = [
        'it' => "A presto\nUwe Vetter · Vecom Design",
        'de' => "Herzliche Grüße\nUwe Vetter · Vecom Design",
        'en' => "Best regards\nUwe Vetter · Vecom Design",
    ];

    /**
     * Die Vorlagen. Platzhalter in geschweiften Klammern werden gefuellt:
     * {vorname} {firma} {paket} {betrag} {seite} {vorschau}
     * Was unbekannt ist, wird zu "…" — dann faellt es beim Lesen auf, statt
     * als leere Stelle durchzurutschen.
     */
    public const ALLE = [

        /* ---------------- Vor dem Auftrag ---------------- */

        'rueckfrage' => ['gruppe' => 'vorher', 'name' => 'Rückfrage vor dem Angebot',
            'betreff' => [
                'it' => 'Una domanda sulla tua richiesta',
                'de' => 'Kurze Rückfrage zu deiner Anfrage',
                'en' => 'One question about your enquiry'],
            'text' => [
                'it' => "grazie per la tua richiesta. Prima di darti un prezzo fisso mi manca ancora un'informazione:\n\n\nAppena me lo dici ti mando la proposta — di solito entro un giorno lavorativo.",
                'de' => "danke für deine Anfrage. Bevor ich dir einen Festpreis nennen kann, fehlt mir noch eine Angabe:\n\n\nSobald ich das weiß, schicke ich dir den Vorschlag — meist innerhalb eines Werktags.",
                'en' => "thank you for your enquiry. Before I can quote a fixed price, one thing is still missing:\n\n\nAs soon as I know, I'll send you the proposal — usually within one working day."]],

        'angebot' => ['gruppe' => 'vorher', 'name' => 'Angebot zum Festpreis',
            'betreff' => [
                'it' => 'La mia proposta per {firma}',
                'de' => 'Mein Vorschlag für {firma}',
                'en' => 'My proposal for {firma}'],
            'text' => [
                'it' => "ecco la mia proposta:\n\n\nÈ un prezzo fisso: quello che c'è scritto è quello che paghi, senza sorprese. La proposta non ti impegna — un incarico nasce soltanto quando ci accordiamo per iscritto.\n\nSe ti va bene, ti mando il link per l'acconto e partiamo. Se qualcosa non torna, dimmelo pure.",
                'de' => "hier mein Vorschlag:\n\n\nDas ist ein Festpreis: Was dasteht, zahlst du — keine Überraschungen. Der Vorschlag ist unverbindlich; ein Auftrag entsteht erst, wenn wir uns schriftlich einig sind.\n\nWenn es so passt, schicke ich dir den Link für die Anzahlung und wir legen los. Wenn etwas nicht stimmt, sag es einfach.",
                'en' => "here is my proposal:\n\n\nIt is a fixed price: what it says is what you pay, no surprises. The proposal commits you to nothing — a project only comes about once we agree in writing.\n\nIf it suits you, I'll send the deposit link and we start. If something is off, just say so."]],

        'termin' => ['gruppe' => 'vorher', 'name' => 'Termin vorschlagen',
            'betreff' => [
                'it' => 'Ci sentiamo dieci minuti?',
                'de' => 'Kurz telefonieren?',
                'en' => 'A short call?'],
            'text' => [
                'it' => "per andare più veloci propongo una telefonata di dieci minuti — spesso si chiarisce in due frasi quello che per iscritto richiede tre giri.\n\nTi andrebbe bene uno di questi momenti?\n\n\nSe preferisci scrivere, va benissimo lo stesso.",
                'de' => "damit wir schneller vorankommen, schlage ich ein kurzes Telefonat vor — zehn Minuten. Vieles ist in zwei Sätzen geklärt, was schriftlich drei Runden braucht.\n\nWürde dir einer dieser Zeitpunkte passen?\n\n\nWenn du lieber schreibst, ist das genauso in Ordnung.",
                'en' => "to move faster I'd suggest a short call — ten minutes. Much of it is settled in two sentences that would otherwise take three rounds in writing.\n\nWould one of these times suit you?\n\n\nIf you'd rather write, that's just as fine."]],

        'nachfassen' => ['gruppe' => 'vorher', 'name' => 'Nachfassen',
            'betreff' => [
                'it' => 'Tutto chiaro sulla mia proposta?',
                'de' => 'Ist mein Vorschlag angekommen?',
                'en' => 'Did my proposal reach you?'],
            'text' => [
                'it' => "volevo solo sapere se il mio messaggio è arrivato e se è rimasta qualche domanda aperta.\n\nSe adesso non è il momento giusto, nessun problema — dimmelo e ti ricontatto più avanti.",
                'de' => "ich wollte kurz nachhaken, ob meine Nachricht angekommen ist und ob noch Fragen offen sind.\n\nWenn es gerade nicht passt, ist das kein Problem — sag Bescheid, dann melde ich mich später wieder.",
                'en' => "I just wanted to check that my message arrived and whether any questions are still open.\n\nIf now isn't the right time, that's no problem — say so and I'll come back to you later."]],

        'absage' => ['gruppe' => 'vorher', 'name' => 'Absage',
            'betreff' => [
                'it' => 'La tua richiesta — una risposta onesta',
                'de' => 'Deine Anfrage — eine ehrliche Antwort',
                'en' => 'Your enquiry — an honest answer'],
            'text' => [
                'it' => "grazie per l'interesse. Per questo progetto non sono la persona giusta — te lo dico subito invece di farti perdere tempo.\n\nSe ti serve, ti indico volentieri qualcuno che se ne occupa meglio di me.",
                'de' => "danke für dein Interesse. Für dieses Vorhaben bin ich nicht der Richtige — ich sage das lieber gleich, als deine Zeit zu binden.\n\nWenn du magst, nenne ich dir gern jemanden, der das besser kann als ich.",
                'en' => "thank you for your interest. I'm not the right person for this project — I'd rather say so now than take up your time.\n\nIf it helps, I'm happy to point you to someone better suited."]],

        /* ---------------- Während der Arbeit ---------------- */

        'material' => ['gruppe' => 'waehrend', 'name' => 'Material anfordern',
            'betreff' => [
                'it' => 'Mi serve ancora il tuo materiale',
                'de' => 'Mir fehlt noch dein Material',
                'en' => 'Your material is still missing'],
            'text' => [
                'it' => "per andare avanti mi manca ancora qualcosa da parte tua:\n\n· Logo (meglio se in alta risoluzione o vettoriale)\n· Foto della tua attività, dei prodotti, del team\n· Testi che vuoi assolutamente sulla pagina\n\nPuoi caricare tutto qui, direttamente dal telefono:\n{seite}\n\nSe una cosa non ce l'hai, dimmelo: si trova sempre una soluzione.",
                'de' => "um weiterzukommen, fehlt mir noch etwas von dir:\n\n· Logo (am besten hochauflösend oder als Vektordatei)\n· Fotos von deinem Betrieb, den Produkten, dem Team\n· Texte, die auf jeden Fall auf die Seite sollen\n\nDu kannst alles hier hochladen, direkt vom Handy:\n{seite}\n\nWenn du etwas nicht hast, sag Bescheid — dafür findet sich immer eine Lösung.",
                'en' => "to move on, I still need a few things from you:\n\n· Your logo (high resolution or vector if possible)\n· Photos of your business, products, team\n· Any text that definitely belongs on the site\n\nYou can upload everything here, straight from your phone:\n{seite}\n\nIf you don't have something, tell me — there's always a way around it."]],

        'fragebogen' => ['gruppe' => 'waehrend', 'name' => 'Fragebogen-Erinnerung',
            'betreff' => [
                'it' => 'Mancano ancora le tue informazioni',
                'de' => 'Deine Angaben fehlen noch',
                'en' => 'Your details are still missing'],
            'text' => [
                'it' => "per il tuo sito mancano ancora le informazioni sul questionario. Sono quattro passaggi brevi, circa dieci minuti, e si salva da solo — puoi interrompere e riprendere quando vuoi.\n\nLo trovi qui:\n{seite}\n\nSenza quelle informazioni non posso partire davvero, e non vorrei farti aspettare per questo.",
                'de' => "für deine Seite fehlen noch die Angaben aus dem Fragebogen. Es sind vier kurze Schritte, etwa zehn Minuten, und er speichert von allein — du kannst zwischendurch aufhören und später weitermachen.\n\nHier ist er:\n{seite}\n\nOhne die Angaben kann ich nicht richtig loslegen, und ich möchte dich deswegen nicht warten lassen.",
                'en' => "the details from the questionnaire are still missing. It's four short steps, about ten minutes, and it saves by itself — you can stop and come back any time.\n\nHere it is:\n{seite}\n\nWithout them I can't really start, and I'd rather not keep you waiting for that."]],

        'vorschau' => ['gruppe' => 'waehrend', 'name' => 'Vorschau ist fertig',
            'betreff' => [
                'it' => 'La tua anteprima è pronta',
                'de' => 'Deine Vorschau steht',
                'en' => 'Your preview is ready'],
            'text' => [
                'it' => "l'anteprima di {firma} è pronta. Guardala con calma, anche dal telefono:\n{seite}\n\nSe va bene così, la approvi con un clic sulla stessa pagina. Se qualcosa non ti convince, scrivilo nel campo lì sotto — le modifiche fanno parte del lavoro, non costano extra.\n\nDue cose che aiutano: guardala una volta sul telefono e una sul computer, e dimmi cosa penserebbe un tuo cliente, non cosa piace a me.",
                'de' => "die Vorschau von {firma} ist fertig. Sieh sie dir in Ruhe an, gern auch auf dem Handy:\n{seite}\n\nWenn sie so passt, gibst du sie dort mit einem Klick frei. Wenn dir etwas nicht gefällt, schreib es ins Feld darunter — Änderungen gehören dazu und kosten nichts extra.\n\nZwei Dinge helfen: Schau sie einmal auf dem Handy und einmal am Rechner an, und sag mir, was ein Kunde von dir denken würde, nicht was mir gefällt.",
                'en' => "the preview of {firma} is ready. Take your time with it, on your phone too:\n{seite}\n\nIf it's right, you approve it there with one click. If something doesn't sit well, write it in the field below — changes are part of the job and cost nothing extra.\n\nTwo things help: look at it once on a phone and once on a computer, and tell me what one of your customers would think, not what I'd like."]],

        'aenderungen' => ['gruppe' => 'waehrend', 'name' => 'Änderungen umgesetzt',
            'betreff' => [
                'it' => 'Modifiche fatte — dai un\'altra occhiata',
                'de' => 'Änderungen sind drin — schau nochmal',
                'en' => 'Changes are in — have another look'],
            'text' => [
                'it' => "ho sistemato quello che mi hai scritto:\n\n\nLo trovi qui:\n{seite}\n\nSe adesso va bene, puoi approvare direttamente da lì. Se è rimasto qualcosa, dimmelo pure.",
                'de' => "ich habe umgesetzt, was du geschrieben hast:\n\n\nHier ist es:\n{seite}\n\nWenn es jetzt passt, kannst du dort direkt freigeben. Wenn noch etwas offen ist, sag es einfach.",
                'en' => "I've made the changes you asked for:\n\n\nHere it is:\n{seite}\n\nIf it's right now, you can approve it there. If anything is still open, just tell me."]],

        'zahlung' => ['gruppe' => 'waehrend', 'name' => 'Zahlungserinnerung',
            'betreff' => [
                'it' => 'Un promemoria: {betrag} ancora aperti',
                'de' => 'Kurze Erinnerung: {betrag} offen',
                'en' => 'A reminder: {betrag} outstanding'],
            'text' => [
                'it' => "mi risulta ancora aperto un importo di {betrag}. Probabilmente è solo sfuggito — capita.\n\nTrovi il link per il pagamento sulla tua pagina:\n{seite}\n\nSe invece c'è qualcosa che non va, scrivimi: si sistema.",
                'de' => "bei mir steht noch ein Betrag von {betrag} offen. Wahrscheinlich ist es einfach untergegangen — das passiert.\n\nDen Zahlungslink findest du auf deiner Seite:\n{seite}\n\nWenn stattdessen etwas nicht stimmt, schreib mir: Dann klären wir das.",
                'en' => "there's still {betrag} outstanding on my side. It probably just slipped through — that happens.\n\nYou'll find the payment link on your page:\n{seite}\n\nIf something is wrong instead, write to me and we'll sort it out."]],

        /* ---------------- Danach ---------------- */

        'online' => ['gruppe' => 'danach', 'name' => 'Seite ist online',
            'betreff' => [
                'it' => '{firma} è online',
                'de' => '{firma} ist online',
                'en' => '{firma} is live'],
            'text' => [
                'it' => "il sito è online:\n{seite}\n\nDue cose che vale la pena fare adesso:\n\n· Metti l'indirizzo su Google Business, sui social e sulla firma delle tue e-mail\n· Dai un'occhiata dal telefono di un conoscente — così vedi il sito come lo vede un cliente\n\nLa pagina che conosci resta valida: lì vedi tutto e da lì puoi chiedermi modifiche anche fra mesi.\n\nGrazie della fiducia.",
                'de' => "die Website ist online:\n{seite}\n\nZwei Dinge lohnen sich jetzt:\n\n· Trag die Adresse bei Google Business, in deinen Social-Media-Profilen und in deine E-Mail-Signatur ein\n· Schau sie einmal auf dem Handy von jemand anderem an — so siehst du sie, wie ein Kunde sie sieht\n\nDeine gewohnte Seite bleibt gültig: Dort siehst du alles und kannst mir auch in Monaten noch Änderungen schreiben.\n\nDanke für dein Vertrauen.",
                'en' => "the site is live:\n{seite}\n\nTwo things worth doing now:\n\n· Add the address to Google Business, your social profiles and your email signature\n· Look at it on someone else's phone — that's how a customer sees it\n\nThe page you know stays valid: everything is there, and you can send me changes from it months from now.\n\nThank you for your trust."]],

        'betreuung' => ['gruppe' => 'danach', 'name' => 'Betreuung anbieten',
            'betreff' => [
                'it' => 'Il sito resta aggiornato?',
                'de' => 'Soll die Seite gepflegt bleiben?',
                'en' => 'Keeping the site looked after'],
            'text' => [
                'it' => "il sito è online e funziona. Una domanda onesta: vuoi occupartene tu o preferisci che ci pensi io?\n\nCon la manutenzione mensile ci sono aggiornamenti, backup, piccole modifiche di testo e foto, e un occhio su velocità e raggiungibilità. Senza, il sito continua a funzionare — solo che le piccole cose si accumulano.\n\nNessuna fretta e nessun obbligo: se preferisci di no, va benissimo così.",
                'de' => "die Seite ist online und läuft. Eine ehrliche Frage: Willst du dich selbst darum kümmern oder soll ich das übernehmen?\n\nZur monatlichen Betreuung gehören Aktualisierungen, Sicherungen, kleine Text- und Bildänderungen und ein Auge auf Tempo und Erreichbarkeit. Ohne läuft die Seite weiter — nur sammeln sich die kleinen Dinge mit der Zeit an.\n\nKein Druck und keine Verpflichtung: Wenn du lieber nicht möchtest, ist das völlig in Ordnung.",
                'en' => "the site is live and running. An honest question: do you want to look after it yourself, or shall I?\n\nMonthly care covers updates, backups, small text and image changes, and an eye on speed and uptime. Without it the site keeps working — the small things just pile up over time.\n\nNo pressure and no obligation: if you'd rather not, that's perfectly fine."]],

        'bewertung' => ['gruppe' => 'danach', 'name' => 'Um Bewertung bitten',
            'betreff' => [
                'it' => 'Due frasi da te varrebbero molto',
                'de' => 'Zwei Sätze von dir wären viel wert',
                'en' => 'Two sentences from you would mean a lot'],
            'text' => [
                'it' => "se sei contento del risultato, mi faresti un grande favore con due frasi: come è andata la collaborazione e cosa è cambiato per la tua attività.\n\nBastano davvero due righe, e puoi scrivermele qui. Se preferisci, le metto sul mio sito con il nome della tua azienda — solo se me lo permetti.\n\nSe qualcosa non ti è piaciuto, quello mi interessa ancora di più: così la volta dopo lo faccio meglio.",
                'de' => "wenn du mit dem Ergebnis zufrieden bist, würdest du mir mit zwei Sätzen sehr helfen: wie die Zusammenarbeit war und was sich für deinen Betrieb geändert hat.\n\nZwei Zeilen reichen wirklich, du kannst sie mir einfach hier schreiben. Wenn du magst, stelle ich sie mit deinem Firmennamen auf meine Seite — nur wenn du es erlaubst.\n\nWenn dir etwas nicht gefallen hat, interessiert mich das noch mehr: Dann mache ich es beim nächsten Mal besser.",
                'en' => "if you're happy with the result, two sentences from you would help me a great deal: how the work went and what changed for your business.\n\nTwo lines is genuinely enough, and you can write them right here. If you like, I'll put them on my site with your company name — only with your permission.\n\nIf something wasn't right, I want to hear that even more: then I do it better next time."]],

        'ruht' => ['gruppe' => 'danach', 'name' => 'Ruhendes Projekt',
            'betreff' => [
                'it' => 'Andiamo avanti con {firma}?',
                'de' => 'Machen wir bei {firma} weiter?',
                'en' => 'Shall we carry on with {firma}?'],
            'text' => [
                'it' => "da un po' non ci sentiamo e il progetto è fermo. Non è un rimprovero — succede, e di solito perché nel frattempo c'è dell'altro.\n\nDue possibilità: riprendiamo, e allora dimmi solo cosa ti serve da me; oppure lo mettiamo in pausa e ti ricontatto più avanti.\n\nQualsiasi risposta va bene, anche «adesso no».",
                'de' => "wir haben länger nichts voneinander gehört und das Projekt liegt gerade. Das ist kein Vorwurf — das passiert, meist weil zwischendurch anderes dazwischenkommt.\n\nZwei Möglichkeiten: Wir machen weiter, dann sag mir nur, was du von mir brauchst. Oder wir legen es auf Eis und ich melde mich später wieder.\n\nJede Antwort ist mir recht, auch «gerade nicht».",
                'en' => "we haven't heard from each other in a while and the project is on hold. That's no reproach — it happens, usually because something else came up.\n\nTwo options: we carry on, and you just tell me what you need from me. Or we park it and I come back to you later.\n\nAny answer is fine, including \"not right now\"."]],
    ];

    /**
     * Die Kennung fuer den Betreff. Die Bestellnummer, wenn es eine gibt —
     * sonst eine aus der Kundennummer gebildete. Berechnet, nicht gespeichert:
     * eine weitere Spalte waere eine weitere Stelle, die auseinanderlaufen kann.
     */
    public static function kennung(int $kundeId): string
    {
        $nr = (string) self::still(fn() => Db::wert(
            'SELECT order_no FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId], ''), '');
        if ($nr !== '') { return $nr; }
        return 'VD-K-' . str_pad((string) $kundeId, 4, '0', STR_PAD_LEFT);
    }

    /** Kennung vor den Betreff — genau einmal, auch wenn sie schon dasteht. */
    public static function betreff(int $kundeId, string $betreff): string
    {
        $kennung = self::kennung($kundeId);
        $betreff = trim($betreff);
        if ($betreff === '') { $betreff = 'Nachricht von Vecom Design'; }
        if (str_starts_with($betreff, '[' . $kennung . ']')) { return $betreff; }
        return '[' . $kennung . '] ' . $betreff;
    }

    /**
     * Alle Vorlagen, gefuellt und in der Sprache des Kunden.
     *
     * @return list<array{schluessel:string,gruppe:string,name:string,betreff:string,text:string}>
     */
    public static function fuer(int $kundeId): array
    {
        $k = self::still(fn() => Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]), null);
        if (!$k) { return []; }

        $sprache = strtolower((string) ($k['sprache'] ?? 'it'));
        if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

        $werte = self::werte($kundeId, $k);
        $anrede = (self::ANREDE[$sprache] ?? 'Ciao') . ' ' . $werte['{vorname}'] . ",\n\n";
        $gruss  = "\n\n" . (self::GRUSS[$sprache] ?? '');

        $aus = [];
        foreach (self::ALLE as $schluessel => $v) {
            $aus[] = [
                'schluessel' => $schluessel,
                'gruppe'     => (string) $v['gruppe'],
                'name'       => (string) $v['name'],
                'betreff'    => strtr((string) ($v['betreff'][$sprache] ?? $v['betreff']['it']), $werte),
                'text'       => $anrede . strtr((string) ($v['text'][$sprache] ?? $v['text']['it']), $werte) . $gruss,
            ];
        }
        return $aus;
    }

    /**
     * Was in die Platzhalter kommt. Unbekanntes wird zu "…" — eine leere
     * Stelle rutscht beim Lesen durch, drei Punkte nicht.
     *
     * @return array<string,string>
     */
    private static function werte(int $kundeId, array $k): array
    {
        $name  = trim((string) ($k['name'] ?? ''));
        $firma = trim((string) ($k['company'] ?? ''));

        $paket = (string) self::still(fn() => Db::wert(
            'SELECT package_name FROM orders WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId], ''), '');

        $offen = (int) self::still(fn() => Db::wert(
            "SELECT COALESCE(SUM(p.amount_cents),0) FROM payments p
             JOIN orders o ON o.id = p.order_id
             WHERE o.customer_id = ? AND p.status <> 'bezahlt'", [$kundeId], 0), 0);

        $seite = (string) self::still(function () use ($kundeId) {
            require_once __DIR__ . '/Kundenzugang.php';
            return Kundenzugang::linkFuer($kundeId);
        }, '');

        $vorschau = (string) self::still(fn() => Db::wert(
            'SELECT preview_url FROM projects WHERE customer_id = ? AND preview_url IS NOT NULL
             ORDER BY id DESC LIMIT 1', [$kundeId], ''), '');

        $punkte = static fn(string $s): string => $s !== '' ? $s : '…';

        return [
            '{vorname}'  => $punkte(explode(' ', $name)[0] ?? ''),
            '{name}'     => $punkte($name),
            '{firma}'    => $punkte($firma !== '' ? $firma : $name),
            '{paket}'    => $punkte($paket),
            '{betrag}'   => $offen > 0 ? Fmt::geld($offen) : '…',
            '{seite}'    => $punkte($seite),
            '{vorschau}' => $punkte($vorschau),
        ];
    }

    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }
}
