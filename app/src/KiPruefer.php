<?php
declare(strict_types=1);

require_once __DIR__ . '/AkquiseText.php';

/**
 * Der Prüfer für jeden Satz, den die KI schreibt (Vorschlag 11, 07.10.2026).
 *
 * Ein KI-Text darf nur sagen, was in der Akte steht. Deshalb:
 *  - jede Zahl muss in den Fakten vorkommen (kein „in 3 Tagen online“, keine erfundene Frist)
 *  - kein Preis, außer genau dieser Betrag steht in den Fakten
 *  - keine Links, Mailadressen oder Telefonnummern — die stehen im festen Teil (außer ausdrücklich erlaubt)
 *  - gesiezt, immer (kein du/dein, kein „ciao“/„tu“)
 *  - keine Versprechen (garantiert, kostenlos, sofort …), keine Angstmache, keine internen Wörter
 *  - keine Anrede und kein Gruß — die hat der feste Teil schon
 * Fällt ein Text durch, geht die feste Vorlage raus. Ein halber KI-Text geht nie raus.
 */
final class KiPruefer
{
    private const VERSPRECHEN = [
        '~\bkostenlos~u', '~\bgratis\b~u', '~\bfree of charge\b~u', '~\bfor free\b~u', '~\bsofort online\b~u', '~\bsubito online\b~u',
        '~\b100\s?%~u', '~\bversprech~u', '~\bprometto\b~u', '~\bi promise\b~u', '~\brabatt~u', '~\bsconto\b~u', '~\bdiscount\b~u',
    ];
    private const INTERN = ['ki', 'claude', 'prompt', 'künstliche intelligenz', 'intelligenza artificiale', 'artificial intelligence', 'chatbot', 'sprachmodell'];
    private const ANREDE = '~^\s*(buongiorno|buonasera|gentile|salve|ciao|guten tag|guten morgen|hallo|liebe|lieber|sehr geehrte|hello|hi|dear)\b~iu';
    private const GRUSS = '~(cordiali saluti|distinti saluti|un saluto|a presto|freundliche grüße|herzliche grüße|viele grüße|mit freundlichen|best regards|kind regards)~iu';

    /**
     * @param string        $text       der KI-Text
     * @param string        $fakten     alles, was belegt ist (Akte, Befunde, Beträge) — Zahlen müssen hier vorkommen
     * @param string        $sprache    it|de|en
     * @param array{max?:int, links?:list<string>, anrede_ok?:bool} $o
     * @return list<string> Mängel; leer = darf raus
     */
    public static function pruefen(string $text, string $fakten, string $sprache, array $o = []): array
    {
        $m = [];
        $t = trim($text);
        if ($t === '') { return ['leer']; }
        $max = (int) ($o['max'] ?? 600);
        if (mb_strlen($t) > $max) { $m[] = 'zu lang (' . mb_strlen($t) . ' Zeichen, höchstens ' . $max . ')'; }
        $klein = mb_strtolower($t);
        if (preg_match('~[{}\[\]<>]~u', $t)) { $m[] = 'Platzhalter oder Klammern im Text'; }

        /* Links nur, wenn genau dieser erlaubt ist. */
        $ohneErlaubte = $t;
        foreach ((array) ($o['links'] ?? []) as $l) { if ($l !== '') { $ohneErlaubte = str_replace($l, ' ', $ohneErlaubte); } }
        $f = mb_strtolower($fakten);
        if (preg_match('~[\w.+-]+@[\w-]+\.[\w.]+~u', $ohneErlaubte)) { $m[] = 'Mailadresse im KI-Teil'; }
        if (preg_match('~https?://\S+~iu', $ohneErlaubte, $x)) { $m[] = 'Link im KI-Teil: „' . $x[0] . '“'; }
        /* Ein Domainname (etwa die Website des Betriebs) ist erlaubt, wenn er in der Akte steht. */
        if (preg_match_all('~\b(?:www\.)?[\w-]+(?:\.[\w-]+)*\.(?:it|de|com|net|eu|org|info|biz)\b~iu', $ohneErlaubte, $doms)) {
            foreach ($doms[0] as $d) {
                $dk = mb_strtolower(preg_replace('~^www\.~i', '', $d) ?? $d);
                if ($dk !== 'vecom-design.it' && !str_contains($f, $dk)) { $m[] = 'Adresse ohne Beleg: „' . $d . '“'; }
            }
        }

        /* Zahlen: jede belegt. Die Fakten werden genauso normalisiert. */
        preg_match_all('~\d+(?:[.,]\d+)*~u', $ohneErlaubte, $z);
        foreach (array_unique($z[0]) as $zahl) {
            $varianten = [$zahl, str_replace(',', '.', $zahl), str_replace('.', ',', $zahl), str_replace(['.', ','], '', $zahl)];
            $belegt = false;
            foreach ($varianten as $v) { if ($v !== '' && str_contains($f, $v)) { $belegt = true; break; } }
            if (!$belegt) { $m[] = 'Zahl ohne Beleg in der Akte: „' . $zahl . '“'; }
        }
        if (preg_match('~(€|\beur\b|\beuro\b)~u', $klein) && !preg_match('~(€|\beur\b|\beuro\b)~u', $f)) { $m[] = 'Preisangabe ohne Beleg'; }

        /* Gesiezt. */
        $du = ['de' => '~\b(du|dich|dir|dein|deine|deinen|deinem|deiner|euch|euer)\b~u', 'it' => '~\b(ciao|tu|ti|tuo|tua|tuoi|tue|te lo|ti prego)\b~u'][$sprache] ?? null;
        if ($du !== null && preg_match($du, $klein, $x)) { $m[] = 'nicht gesiezt („' . $x[0] . '“)'; }

        foreach (self::VERSPRECHEN as $v) { if (preg_match($v, $klein, $x)) { $m[] = 'Versprechen: „' . $x[0] . '“'; } }
        foreach (AkquiseText::VERBOTEN as $v) { if (preg_match($v, $klein, $x)) { $m[] = 'Angst- oder Übertreibungsformulierung: „' . $x[0] . '“'; } }
        foreach (array_merge(self::INTERN, ['score', 'lead', 'opportunity', 'unverified', 'kaltakquise']) as $w) {
            if (preg_match('~\b' . preg_quote($w, '~') . '\b~u', $klein) && !preg_match('~\b' . preg_quote($w, '~') . '\b~u', $f)) { $m[] = 'internes Wort: „' . $w . '“'; }   // steht es im Namen des Betriebs, ist es keins
        }
        if (empty($o['anrede_ok']) && preg_match(self::ANREDE, $t)) { $m[] = 'Anrede doppelt (die steht schon im festen Teil)'; }
        if (empty($o['anrede_ok']) && preg_match(self::GRUSS, $t)) { $m[] = 'Grußformel doppelt'; }
        return array_values(array_unique($m));
    }
}
