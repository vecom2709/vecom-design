<?php
declare(strict_types=1);

require_once __DIR__ . '/Firma.php';
require_once __DIR__ . '/Pdf.php';

/**
 * Widerrufsbelehrung und Muster-Widerrufsformular.
 *
 * WARUM DIESE KLASSE EXISTIERT
 *
 * Bei einem Fernabsatzvertrag mit einem Verbraucher verlangt Art. 51 Abs. 7
 * des Codice del Consumo, dass der Unternehmer die Bestaetigung des
 * geschlossenen Vertrags auf einem DAUERHAFTEN DATENTRAEGER gibt —
 * spaetestens bevor die Leistung beginnt. Eine Webseite ist kein dauerhafter
 * Datentraeger; eine E-Mail und ein angehaengtes PDF sind es.
 *
 * Zu dieser Bestaetigung gehoeren die Angaben aus Art. 49 Abs. 1, darunter
 * die Bedingungen, Fristen und das Verfahren des Widerrufs (Buchstabe h) und
 * das Muster-Widerrufsformular aus Anhang I Teil B.
 *
 * Verlangt der Verbraucher, dass die Arbeit schon waehrend der Widerrufsfrist
 * beginnt, muss der Unternehmer diese ausdrueckliche Bitte einholen und sich
 * bestaetigen lassen, dass das Widerrufsrecht mit der vollstaendigen Leistung
 * erlischt (Art. 51 Abs. 8). Genau diese beiden Haken setzt der Kunde auf
 * buchen.php; hier steht der Wortlaut dazu.
 *
 * Der Wortlaut steht an EINER Stelle, nicht zweimal: Was auf der Buchungsseite
 * angezeigt wird und was spaeter bestaetigt wird, muss dasselbe sein — sonst
 * bestaetigt man etwas anderes, als der Kunde gelesen hat.
 *
 * Kein Rechtsrat. Den genauen Wortlaut bestaetigt ein Anwalt; hier steht, was
 * die Seite ausgibt, an einer Stelle und nachlesbar.
 */
final class Widerruf
{
    /** So lange laeuft die Frist, in Tagen. */
    public const FRIST_TAGE = 14;

    /* ---------- Texte ---------- */

    /** @return array<string,string> */
    public static function texte(string $sprache): array
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        return self::ALLE[$s];
    }

    public static function t(string $schluessel, string $sprache): string
    {
        return self::texte($sprache)[$schluessel] ?? '';
    }

    private const ALLE = [
        'it' => [
            'agb'      => 'Ho letto e accetto le <a href="/legal.html#agb" target="_blank" rel="noopener">condizioni</a> e l’<a href="/legal.html#privacy" target="_blank" rel="noopener">informativa privacy</a>.',
            'wid'      => 'Chiedo espressamente che il lavoro inizi subito e prendo atto che, a servizio interamente prestato, perderò il diritto di recesso.',
            'widTitel' => 'Informazioni sul diritto di recesso',
            'widText'  => 'Come consumatore hai quattordici giorni per recedere dal contratto, senza motivazione. Se chiedi che il lavoro inizi prima della scadenza di questo termine, il diritto si estingue nel momento in cui il servizio è stato interamente prestato; se receti prima, ti verrà addebitata la parte già svolta. Per recedere basta una comunicazione chiara a kontakt@vecom-design.it.',
            'formTitel'=> 'Modulo di recesso tipo',
            'formVor'  => 'Compilare e rispedire il presente modulo solo se si desidera recedere dal contratto.',
            'formAn'   => 'Destinatario',
            'formText' => "Con la presente io/noi (*) notifichiamo il recesso dal mio/nostro (*) contratto di vendita\ndei seguenti beni/servizi (*):",
            'formOrd'  => 'Ordinato il (*) / ricevuto il (*)',
            'formName' => 'Nome del/dei consumatore(i)',
            'formAdr'  => 'Indirizzo del/dei consumatore(i)',
            'formUnt'  => 'Firma del/dei consumatore(i) (solo se il presente modulo è notificato in versione cartacea)',
            'formDat'  => 'Data',
            'formFuss' => '(*) Cancellare la dicitura inutile.',
        ],
        'de' => [
            'agb'      => 'Ich habe die <a href="/legal.html#agb" target="_blank" rel="noopener">AGB</a> und die <a href="/legal.html#privacy" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen und akzeptiere sie.',
            'wid'      => 'Ich verlange ausdrücklich, dass die Arbeit sofort beginnt, und weiß, dass mein Widerrufsrecht mit der vollständigen Leistung erlischt.',
            'widTitel' => 'Hinweise zum Widerrufsrecht',
            'widText'  => 'Als Verbraucher kannst du den Vertrag innerhalb von vierzehn Tagen ohne Angabe von Gründen widerrufen. Verlangst du, dass die Arbeit schon vor Ablauf dieser Frist beginnt, erlischt das Recht in dem Moment, in dem die Leistung vollständig erbracht ist; widerrufst du vorher, wird der bis dahin geleistete Teil berechnet. Für den Widerruf genügt eine eindeutige Nachricht an kontakt@vecom-design.it.',
            'formTitel'=> 'Muster-Widerrufsformular',
            'formVor'  => 'Dieses Formular nur ausfüllen und zurücksenden, wenn du den Vertrag widerrufen willst.',
            'formAn'   => 'An',
            'formText' => "Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über\ndie folgende Dienstleistung (*):",
            'formOrd'  => 'Bestellt am (*) / erhalten am (*)',
            'formName' => 'Name des/der Verbraucher(s)',
            'formAdr'  => 'Anschrift des/der Verbraucher(s)',
            'formUnt'  => 'Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)',
            'formDat'  => 'Datum',
            'formFuss' => '(*) Unzutreffendes streichen.',
        ],
        'en' => [
            'agb'      => 'I have read and accept the <a href="/legal.html#agb" target="_blank" rel="noopener">terms</a> and the <a href="/legal.html#privacy" target="_blank" rel="noopener">privacy notice</a>.',
            'wid'      => 'I expressly ask for the work to begin at once and acknowledge that my right of withdrawal ends once the service has been fully performed.',
            'widTitel' => 'About the right of withdrawal',
            'widText'  => 'As a consumer you may withdraw from the contract within fourteen days without giving a reason. If you ask for the work to start before that period ends, the right lapses at the moment the service has been fully performed; if you withdraw earlier, the part already carried out is charged. A clear message to kontakt@vecom-design.it is enough to withdraw.',
            'formTitel'=> 'Model withdrawal form',
            'formVor'  => 'Complete and return this form only if you wish to withdraw from the contract.',
            'formAn'   => 'To',
            'formText' => "I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract for\nthe following service (*):",
            'formOrd'  => 'Ordered on (*) / received on (*)',
            'formName' => 'Name of consumer(s)',
            'formAdr'  => 'Address of consumer(s)',
            'formUnt'  => 'Signature of consumer(s) (only if this form is notified on paper)',
            'formDat'  => 'Date',
            'formFuss' => '(*) Delete as appropriate.',
        ],
    ];

    /* ---------- Das Formular als PDF ---------- */

    /**
     * Muster-Widerrufsformular nach Anhang I Teil B — als ausfuellbares
     * Blatt mit Linien, so wie man es ausdruckt oder am Bildschirm liest.
     */
    public static function formularPdf(string $sprache, array $bestellung = []): string
    {
        $t = self::texte($sprache);

        $tinte = [0.051, 0.106, 0.165];
        $grau  = [0.42, 0.46, 0.53];
        $leise = [0.62, 0.66, 0.72];
        $blau  = [0.024, 0.282, 0.910];
        $cyan  = [0.122, 0.910, 1.0];
        $linie = [0.80, 0.83, 0.87];

        $p = new Pdf();
        $rand   = 56.0;
        $rechts = Pdf::A4_BREIT - $rand;

        /* Briefkopf wie auf dem Beleg — es ist dasselbe Haus. */
        require_once __DIR__ . '/Rechnung.php';
        $logo = Rechnung::logo();
        if ($logo === null || !$p->bild($logo, $rand, 44, 98, 67)) {
            $bv = $p->text($rand, 62, 'VECOM', 17, true, 'links', $blau);
            $p->text($rand + $bv + 5, 62, 'DESIGN', 17, true, 'links', $tinte);
        }
        $y = 46;
        foreach (Firma::anschrift() as $i => $zeile) {
            $p->text($rechts, $y, $zeile, 8.5, $i === 0, 'rechts', $i === 0 ? $tinte : $grau);
            $y += 11.5;
        }
        $p->flaeche($rand, 124, ($rechts - $rand) * 0.38, 1.6, $blau);
        $p->flaeche($rand + ($rechts - $rand) * 0.38, 124, ($rechts - $rand) * 0.12, 1.6, $cyan);

        /* Titel und Vorbemerkung */
        $p->text($rand, 168, $t['formTitel'], 20, true, 'links', $tinte);
        $y = 192;
        foreach ($p->umbrechen($t['formVor'], $rechts - $rand, 10) as $zeile) {
            $p->text($rand, $y, $zeile, 10, false, 'links', $grau);
            $y += 14;
        }

        /* Empfaenger — das sind wir */
        $y += 16;
        $p->text($rand, $y, strtoupper($t['formAn']), 7.5, true, 'links', $leise);
        $y += 16;
        foreach (Firma::anschrift() as $i => $zeile) {
            $p->text($rand, $y, $zeile, 10.5, $i === 0, 'links', $tinte);
            $y += 14;
        }
        $post = array_filter([Firma::get('email'), Firma::get('telefon')]);
        if ($post) {
            $p->text($rand, $y, implode('  ·  ', $post), 10, false, 'links', $grau);
            $y += 14;
        }

        /* Der Erklaerungstext */
        $y += 18;
        foreach (explode("\n", $t['formText']) as $zeile) {
            $p->text($rand, $y, $zeile, 10.5, false, 'links', $tinte);
            $y += 15;
        }

        /* Wenn die Bestellung bekannt ist, steht sie gleich drin —
           der Kunde soll nichts abschreiben muessen. */
        $y += 6;
        $vorbelegt = trim((string) ($bestellung['leistung'] ?? ''));
        if ($vorbelegt !== '') {
            $p->text($rand + 6, $y, $vorbelegt, 10.5, false, 'links', $tinte);
        }
        $p->linie($rand, $y + 8, $rechts, $y + 8, 0.6, $linie);
        $y += 34;

        /* Felder zum Ausfuellen */
        $felder = [
            [$t['formOrd'],  trim((string) ($bestellung['datum'] ?? ''))],
            [$t['formName'], trim((string) ($bestellung['name'] ?? ''))],
            [$t['formAdr'],  trim((string) ($bestellung['anschrift'] ?? ''))],
            [$t['formDat'],  ''],
            [$t['formUnt'],  ''],
        ];
        foreach ($felder as [$was, $wert]) {
            $p->text($rand, $y, $was, 8.5, false, 'links', $leise);
            if ($wert !== '') {
                $p->text($rand + 6, $y + 18, $wert, 10.5, false, 'links', $tinte);
            }
            $p->linie($rand, $y + 24, $rechts, $y + 24, 0.6, $linie);
            $y += 46;
        }

        $p->text($rand, $y + 4, $t['formFuss'], 8.5, false, 'links', $leise);

        /* Fuss */
        $fuss = Pdf::A4_HOCH - 82;
        $p->flaeche($rand, $fuss, ($rechts - $rand) * 0.10, 1.2, $blau);
        $p->linie($rand + ($rechts - $rand) * 0.10, $fuss + 0.6, $rechts, $fuss + 0.6, 0.5, $linie);
        $fy = $fuss + 18;
        foreach (Firma::fusszeilen() as $zeile) {
            $p->text($rand, $fy, $zeile, 8, false, 'links', $leise);
            $fy += 11;
        }

        return $p->fertig();
    }

    /** Dateiname des Formulars, je Sprache. */
    public static function dateiname(string $sprache): string
    {
        return match (in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it') {
            'de' => 'Widerrufsformular.pdf',
            'en' => 'Withdrawal-form.pdf',
            default => 'Modulo-di-recesso.pdf',
        };
    }
}
