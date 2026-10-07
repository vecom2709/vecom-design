<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseMail.php';

/**
 * „Einmal an alle“ — der PROBELAUF (07.10.2026, Uwe: „in kunden finden soll automatisch jetzt alle
 * emails versenden einmalig nur“, „in verwaltung wichtig“; auf den Hinweis zu Art. 130 Codice Privacy,
 * § 7 UWG und Brevo: „Erst Probelauf“).
 *
 * Hier wird NICHTS gesendet und nichts geschrieben. Gerechnet wird, was ein einmaliger Versand an jeden
 * Betrieb mit E-Mail-Adresse träfe: wie viele, je Land, mit und ohne dokumentierte Zustimmung, wer aus
 * welchem Grund draußen bliebe, wie viele schon einen fertigen Text haben, wie lange es bei den
 * eingestellten Grenzen dauerte — und fünf Beispieltexte, wie sie hinausgingen. Mit diesen Zahlen
 * entscheidet Uwe, ob und wie gesendet wird.
 *
 * Die Ausschlüsse sind dieselben Regeln wie beim echten Versand (AkquiseMail::kann, Sperrliste,
 * Partner-Reservierung) — keine zweite Wahrheit.
 */
final class AkquiseEinmal
{
    /** Obergrenze je Rechnung — mehr Betriebe hat die Akquise heute nicht; darüber wird ehrlich „mehr als“ gesagt. */
    public const HOECHSTENS = 6000;

    public const GRUENDE = [
        'nicht'   => 'Nicht kontaktieren (Widerspruch, Abmeldung, Sperre)',
        'sperre'  => 'Auf der Sperrliste',
        'partner' => 'Ein Partner kümmert sich gerade darum',
        'schon'   => 'Schon angeschrieben (E-Mail, eigenes Programm oder Brief)',
        'bounce'  => 'Adresse kam schon einmal unzustellbar zurück',
        'domain'  => 'An dieselbe Domain ging in der Sperrfrist schon etwas',
    ];

    /**
     * @return array{am:string, gesamt:int, mit_adresse:int, gekappt:bool, bekaemen:int, mit_zustimmung:int, ohne_zustimmung:int,
     *               je_land:array<string,array{mit:int,ohne:int}>, aus:array<string,int>, mit_text:int, ohne_text:int,
     *               tage:int, grenzen:array, beispiele:list<array>}
     */
    public static function probelauf(int $beispiele = 5): array
    {
        $grenzen = AkquiseGate::grenzen();
        $gesamt = (int) Db::wert('SELECT COUNT(*) FROM akq_firmen', [], 0);
        $l = self::lage($grenzen);
        $firmen = $l['firmen']; $gekappt = $l['gekappt']; $texte = $l['texte'];
        $aus = array_fill_keys(array_keys(self::GRUENDE), 0);
        $jeLand = [];
        $mit = 0; $ohne = 0; $mitText = 0; $ohneText = 0; $adresse = 0;
        $kandidaten = [];
        foreach ($firmen as $f) {
            if (Akquise::normEmail((string) $f['email']) === null) { continue; }
            $adresse++;
            $id = (int) $f['id'];
            $kann = AkquiseMail::kann($f);
            $grund = self::grund($f, $kann, $l);
            if ($grund !== null) { $aus[$grund]++; continue; }
            $zustimmung = $kann['werbung'] || AkquiseGate::einwilligungDeckt($f, 'email');
            $land = strtoupper((string) ($f['land'] ?? '')) ?: '?';
            $jeLand[$land] ??= ['mit' => 0, 'ohne' => 0];
            $jeLand[$land][$zustimmung ? 'mit' : 'ohne']++;
            $zustimmung ? $mit++ : $ohne++;
            isset($texte[$id]) ? $mitText++ : $ohneText++;
            if (isset($texte[$id]) && count($kandidaten) < 200) { $kandidaten[] = ['f' => $f, 'vorlage' => $texte[$id], 'zustimmung' => $zustimmung]; }
        }
        ksort($jeLand);
        $bekaemen = $mit + $ohne;

        // Beispiele: gemischt über Länder und mit/ohne Zustimmung, damit das Bild nicht schief ist
        $beispielListe = [];
        usort($kandidaten, static fn($a, $b) => [$a['zustimmung'] ? 0 : 1, (string) $a['f']['land']] <=> [$b['zustimmung'] ? 0 : 1, (string) $b['f']['land']]);
        $gesehenLand = [];
        foreach ($kandidaten as $k) {
            if (count($beispielListe) >= $beispiele) { break; }
            $schluessel = (string) $k['f']['land'] . ($k['zustimmung'] ? 'm' : 'o');
            if (isset($gesehenLand[$schluessel]) && count($gesehenLand) < 4) { continue; }
            $gesehenLand[$schluessel] = true;
            $v = Db::one('SELECT betreff, text, sprache, status FROM akq_vorlagen WHERE id = ?', [$k['vorlage']]);
            if (!$v) { continue; }
            $beispielListe[] = ['firma_id' => (int) $k['f']['id'], 'name' => (string) $k['f']['name'], 'land' => (string) $k['f']['land'],
                'ort' => (string) ($k['f']['stadt'] ?? ''), 'zustimmung' => $k['zustimmung'], 'betreff' => (string) $v['betreff'],
                'text' => mb_substr((string) $v['text'], 0, 2500), 'sprache' => (string) $v['sprache'], 'freigegeben' => $v['status'] === 'freigegeben'];
        }
        if (count($beispielListe) < $beispiele) {
            foreach ($kandidaten as $k) {
                if (count($beispielListe) >= $beispiele) { break; }
                if (in_array((int) $k['f']['id'], array_column($beispielListe, 'firma_id'), true)) { continue; }
                $v = Db::one('SELECT betreff, text, sprache, status FROM akq_vorlagen WHERE id = ?', [$k['vorlage']]);
                if ($v) {
                    $beispielListe[] = ['firma_id' => (int) $k['f']['id'], 'name' => (string) $k['f']['name'], 'land' => (string) $k['f']['land'],
                        'ort' => (string) ($k['f']['stadt'] ?? ''), 'zustimmung' => $k['zustimmung'], 'betreff' => (string) $v['betreff'],
                        'text' => mb_substr((string) $v['text'], 0, 2500), 'sprache' => (string) $v['sprache'], 'freigegeben' => $v['status'] === 'freigegeben'];
                }
            }
        }
        $proTag = max(1, (int) $grenzen['tag']);
        return [
            'am' => date('Y-m-d H:i:s'), 'gesamt' => $gesamt, 'mit_adresse' => $adresse, 'gekappt' => $gekappt,
            'bekaemen' => $bekaemen, 'mit_zustimmung' => $mit, 'ohne_zustimmung' => $ohne, 'je_land' => $jeLand,
            'aus' => $aus, 'mit_text' => $mitText, 'ohne_text' => $ohneText,
            'tage' => $bekaemen > 0 ? (int) ceil($bekaemen / $proTag) : 0, 'grenzen' => $grenzen, 'beispiele' => $beispielListe,
        ];
    }

    /** Was jede Rechnung braucht — einmal geladen statt je Betrieb gefragt. */
    public static function lage(array $grenzen): array
    {
        $firmen = Db::all("SELECT * FROM akq_firmen WHERE email IS NOT NULL AND email <> '' ORDER BY id LIMIT " . (self::HOECHSTENS + 1));
        $gekappt = count($firmen) > self::HOECHSTENS;
        $schon = array_flip(array_map('intval', array_column(Db::all("SELECT DISTINCT firma_id FROM akq_versand WHERE status IN ('gesendet','von_hand')"), 'firma_id')));
        $bounce = array_flip(array_map('intval', array_column(Db::all("SELECT DISTINCT firma_id FROM akq_versand WHERE status = 'bounce'"), 'firma_id')));
        try {
            foreach (Db::all("SELECT DISTINCT firma_id FROM akq_antworten WHERE klasse = 'INVALID_ADDRESS'") as $r) { $bounce[(int) $r['firma_id']] = 0; }
        } catch (Throwable $e) { }
        $domainFrist = [];
        foreach (Db::all("SELECT DISTINCT f.domain FROM akq_versand v JOIN akq_firmen f ON f.id = v.firma_id
                           WHERE f.domain IS NOT NULL AND f.domain <> '' AND v.status IN ('gesendet','von_hand')
                             AND v.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [(int) $grenzen['domain_tage']]) as $r) { $domainFrist[mb_strtolower((string) $r['domain'])] = true; }
        $texte = [];
        foreach (Db::all("SELECT firma_id, MAX(id) AS id FROM akq_vorlagen WHERE kanal = 'email' AND status IN ('entwurf','freigegeben') GROUP BY firma_id") as $r) {
            $texte[(int) $r['firma_id']] = (int) $r['id'];
        }
        return ['firmen' => array_slice($firmen, 0, self::HOECHSTENS), 'gekappt' => $gekappt, 'schon' => $schon, 'bounce' => $bounce, 'domain' => $domainFrist, 'texte' => $texte];
    }

    /** Warum ein Betrieb draußen bleibt — null heißt: er bekäme die Mail. Dieselben Regeln wie beim echten Versand. */
    public static function grund(array $f, array $kann, array $l): ?string
    {
        $id = (int) $f['id'];
        if (AkquiseMail::nichtKontaktieren($f) || $kann['status'] === AkquiseMail::NICHT) { return 'nicht'; }
        if (AkquiseGate::trifftSperrliste($f) !== null) { return 'sperre'; }
        if (isset($l['schon'][$id])) { return 'schon'; }
        if (isset($l['bounce'][$id])) { return 'bounce'; }
        if (!empty($f['domain']) && isset($l['domain'][mb_strtolower((string) $f['domain'])])) { return 'domain'; }
        if (!$kann['senden']) { return 'partner'; }
        return null;
    }

    /**
     * DIE REIHE IM EIGENEN MAILPROGRAMM (07.10.2026, Uwe auf die Frage nach dem Versand:
     * „sendn im mailprogramm öffnen mach es darüber“).
     *
     * Der Server verschickt hier nichts. Er sucht den nächsten Betrieb, der eine Mail bekäme, legt den Text an,
     * falls noch keiner da ist, und lässt ihn durch dieselbe Prüfung wie jede andere Mail (Werkstatt).
     * Geöffnet wird dann über die bestehende Tat akq_mail_mailto — sie schreibt akq_versand „von_hand“
     * samt Abmeldelink. Damit ist der Betrieb „schon angeschrieben“ und kommt nie ein zweites Mal dran:
     * Das „einmalig“ steht in der Datenbank, nicht im Browser.
     *
     * Ohne dokumentierte Zustimmung nur, wenn $auchOhne — das kreuzt Uwe einmal ausdrücklich an, und jede
     * einzelne Mail zeigt den Hinweis noch einmal. Erst die mit Zustimmung, dann die ohne.
     *
     * @param list<int> $ueberspringen in dieser Reihe schon übersprungen (Text ließ sich nicht anlegen, oder Uwe wollte nicht)
     * @return array{naechste:?array, offen:int, offen_mit:int, offen_ohne:int, heute:int, uebersprungen:list<array{id:int,name:string,grund:string}>}
     */
    public static function naechste(bool $auchOhne, array $ueberspringen = []): array
    {
        require_once __DIR__ . '/AkquiseVersand.php';
        require_once __DIR__ . '/AkquiseWerkstatt.php';
        $grenzen = AkquiseGate::grenzen();
        $l = self::lage($grenzen);
        $weg = array_flip(array_map('intval', $ueberspringen));
        $mit = []; $ohne = []; $mitIds = [];
        foreach ($l['firmen'] as $f) {
            if (Akquise::normEmail((string) $f['email']) === null || isset($weg[(int) $f['id']])) { continue; }
            $kann = AkquiseMail::kann($f);
            if (self::grund($f, $kann, $l) !== null) { continue; }
            if ($kann['werbung'] || AkquiseGate::einwilligungDeckt($f, 'email')) { $mit[] = $f; $mitIds[(int) $f['id']] = true; } else { $ohne[] = $f; }
        }
        $reihe = $auchOhne ? array_merge($mit, $ohne) : $mit;
        $neuWeg = [];
        $naechste = null;
        foreach (array_slice($reihe, 0, 25) as $f) {   // höchstens 25 Versuche je Klick — ein Betrieb ohne Audit hält die Reihe nicht auf
            $id = (int) $f['id'];
            try {
                $vid = $l['texte'][$id] ?? AkquiseVersand::regelVorlage($id, null, 'email');
                $v = Db::one("SELECT id, betreff, text, status FROM akq_vorlagen WHERE id = ? AND firma_id = ? AND kanal = 'email'", [$vid, $id]);
                if (!$v || trim((string) $v['betreff']) === '') { throw new RuntimeException('Kein Text mit Betreff.'); }
                $liste = AkquiseWerkstatt::pruefliste($f, 'email', (string) $v['betreff'], (string) $v['text'], true);
                $stopp = array_column(array_filter($liste, static fn($x) => $x['stufe'] === AkquiseWerkstatt::STOPP), 'text');
                if ($stopp) { throw new RuntimeException('Prüfung: ' . implode(' ', $stopp)); }
            } catch (Throwable $e) {
                $neuWeg[] = ['id' => $id, 'name' => (string) $f['name'], 'grund' => mb_substr($e->getMessage(), 0, 200)];
                continue;
            }
            $zustimmung = isset($mitIds[$id]);
            $naechste = [
                'firma_id' => $id, 'name' => (string) $f['name'], 'ort' => trim((string) ($f['stadt'] ?? '') . ' ' . (string) ($f['land'] ?? '')),
                'an' => (string) Akquise::normEmail((string) $f['email']), 'zustimmung' => $zustimmung,
                'vorlage' => (int) $v['id'], 'betreff' => (string) $v['betreff'], 'text' => (string) $v['text'],
                'hinweise' => array_values(array_column(array_filter($liste, static fn($x) => $x['stufe'] === AkquiseWerkstatt::HINWEIS), 'text')),
            ];
            break;
        }
        $wegMit = count(array_filter($neuWeg, static fn($w) => isset($mitIds[$w['id']])));   // gerade Übersprungene zählen nicht als offen
        $wegOhne = count($neuWeg) - $wegMit;
        return [
            'naechste' => $naechste, 'offen' => count($reihe) - count($neuWeg), 'offen_mit' => count($mit) - $wegMit, 'offen_ohne' => count($ohne) - $wegOhne,
            'heute' => (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE status = 'von_hand' AND created_at >= CURDATE()", [], 0),
            'tag' => (int) $grenzen['tag'], 'uebersprungen' => $neuWeg,
        ];
    }
}
