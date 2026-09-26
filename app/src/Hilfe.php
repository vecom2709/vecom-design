<?php
declare(strict_types=1);

/**
 * Hilfe — ein Satz je Seite und die Einführung beim ersten Anmelden
 * (26.09.2026, Uwe: „es muss einfach in der Einführung sein“).
 *
 * Wer eine Seite öffnet, soll im ersten Satz lesen, wozu sie da ist -- nicht
 * aus den Knöpfen erraten. Die Sätze stehen hier gesammelt und nicht in den
 * Ansichten, damit eine neue Seite ihren Satz an genau einer Stelle bekommt
 * und die Kette prüfen kann, dass keine Menüseite ohne Satz bleibt.
 *
 * Duzen, kurz, was man HIER tut -- nicht, was die Seite technisch ist.
 */
final class Hilfe
{
    public const SAETZE = [
        'heute'              => 'Hier siehst du, was jetzt zu tun ist — das Wichtigste oben, mit dem Knopf, der es erledigt.',
        'benachrichtigungen' => 'Hier steht, was nicht von allein läuft: Fehler, Fristen, Dinge, die du prüfen solltest.',
        'aktivitaeten'       => 'Hier steht, was passiert ist — wer wann bestellt, bezahlt, geschrieben oder freigegeben hat.',
        'vorgaenge'          => 'Hier stehen alle Kunden mit laufendem Auftrag, jeweils mit dem nächsten Schritt.',
        'kunden'             => 'Hier findest du jeden Kunden, auch ohne laufenden Auftrag. Ein Klick öffnet seine Akte.',
        'nachrichten'        => 'Hier liest und beantwortest du, was Kunden dir über ihre Seite schreiben.',
        'anfragen'           => 'Hier landen Anfragen von der Website. Antworte zuerst denen, die am längsten warten.',
        'bedarf'             => 'Hier siehst du, was Besucher im Preisrechner zusammengestellt haben, und machst daraus ein Angebot.',
        'empfehlungen'       => 'Hier siehst du, welcher Kunde wen empfohlen hat und welcher Rabatt dafür gilt.',
        'partner'            => 'Hier verwaltest du Partner, die für Provision Kunden bringen — und zahlst ihnen aus.',
        'stimmen'            => 'Hier gibst du Kundenstimmen für die Website frei. Ohne Erlaubnis des Kunden geht keine online.',
        'rechnungen'         => 'Hier stehen alle Belege. Offene Beträge sind oben.',
        'angebote'           => 'Hier schreibst und verschickst du Angebote — mit festem Preis, bevor gebaut wird.',
        'zahlungen'          => 'Hier siehst du jede Zahlung und trägst eine von Hand ein, wenn sie nicht über Stripe kam.',
        'ausgaben'           => 'Hier trägst du ein, was du ausgibst — für die Übersicht und fürs Finanzamt.',
        'abos'               => 'Hier stehen die Monatsverträge (Betreuung, Domain & Hosting) mit der nächsten Abbuchung.',
        'finanzamt'          => 'Hier holst du dir alles fürs Finanzamt: Belege und Ausgaben eines Jahres in einem Paket.',
        'dashboard'          => 'Hier stehen die Zahlen: Umsatz, offene Beträge, wie viele Anfragen zu Kunden wurden.',
        'werkstatt'          => 'Hier baust du: Jeder Auftrag mit Briefing, Material und dem Stand der Seite.',
        'onboarding'         => 'Hier siehst du die Fragebögen der Kunden — was sie ausgefüllt haben und was noch fehlt.',
        'standard'           => 'Hier steht der Vecom-Standard: die Regeln, nach denen jede Kundenseite gebaut wird.',
        'muster'             => 'Hier liegen Bausteine, die du in Kundenseiten wiederverwendest.',
        'dateien'            => 'Hier liegen alle Dateien, die Kunden hochgeladen haben: Logos, Fotos, Texte.',
        'monitoring'         => 'Hier siehst du, ob die Kundenseiten erreichbar sind und ihr Zertifikat gilt.',
        'einstellungen'      => 'Hier stellst du ein, was für alles gilt: Firma, Stripe, E-Mail, Zugänge.',
        'bereit'             => 'Hier steht, was noch eingerichtet werden muss, damit alles von allein läuft.',
        'pakete'             => 'Hier legst du die Pakete an, die Kunden kaufen können.',
        'baukasten'          => 'Hier stellst du die Preise ein, aus denen der Preisrechner den Preis ausrechnet.',
        'telefon'            => 'Hier siehst du Manuelas Gespräche, Rückrufwünsche und Termine — und stellst sie ein.',
        'suche'              => 'Hier findest du Kunden, Bestellungen und Angebote über Name, Nummer oder E-Mail.',
    ];

    /** Die Einführung: fünf Schritte, je ein Satz, je ein Ort. */
    public const EINFUEHRUNG = [
        ['heute',         'Heute',         'Jeden Tag hier anfangen. Oben steht, was jetzt dran ist — ein goldener Knopf erledigt es. Alles andere wartet unter „Später“.'],
        ['vorgaenge',     'Kunden',        'Jeder Kunde hat eine Akte: Nachrichten, Angebot, Zahlung und Stand der Seite an einem Ort. Oben links kannst du jeden suchen.'],
        ['rechnungen',    'Geld',          'Belege, Zahlungen und Verträge. Was über Stripe bezahlt wird, bucht sich von allein — du trägst nur Barzahlungen ein.'],
        ['werkstatt',     'Bauen',         'Hier entsteht die Seite: Briefing lesen, Material ansehen, Vorschau freigeben. Veröffentlicht wird nur nach deinem Klick.'],
        ['telefon',       'Telefon',       'Manuela nimmt Anrufe an und schreibt dir Rückrufwünsche hierher. Wenn etwas nicht klappt, steht es unter „Heute“.'],
    ];

    public static function satz(string $route): string
    {
        return self::SAETZE[$route] ?? '';
    }

    /** Hat dieser Benutzer die Einführung schon gesehen? */
    public static function gesehen(int $uid): bool
    {
        try {
            return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', ['einfuehrung_' . $uid], '') !== '';
        } catch (Throwable $e) {
            return true;   // Ohne Tabelle lieber keine Einführung als eine kaputte Seite
        }
    }

    public static function merken(int $uid): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
                ['einfuehrung_' . $uid, date('Y-m-d H:i')]);
    }
}
