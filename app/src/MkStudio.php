<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkMedium.php';

/* ==========================================================================
   MkStudio.php — das Werbestudio für die Partner-Galerie (03.10.2026).

   Uwe: „Es werden immer erst von mir in der Verwaltung Videos produziert,
   dann erst freigegeben, dass sie im Dashboard sind. Es soll immer ein Video
   nach einander produzierbar sein, nicht mehrere auf einmal. Jedes Video
   besonders stark … branchenabhängig, Vecom Design und Partner und 3D …
   kinoreif wie eine Art Trailer, hyperrealistisch … wo Stimme drin ist
   Kie.ai, die anderen mit passendem Sound; ohne Kie-Guthaben nur Blender und
   Unreal Engine.“

   Also:
   - Ein Katalog statt fester Pakete: je Branche (die zehn 3D-Szenen), Vecom
     Design (Trailer mit Stimme, Werbespot über alle Szenen) und Partner
     (Ansprechpartner vor Ort, kostenloser Website-Check).
   - Motor „Automatisch“: Kie.ai mit Stimme (Veo 3.1 Quality); meldet der PC
     zu wenig Kie-Guthaben, legt der Server von selbst denselben Eintrag mit
     dem Blender-Werbespot nach (Musik statt Stimme) — nie zwei Kie-Versuche.
   - Immer nur ein Studio-Auftrag gleichzeitig (wartet/läuft); der nächste
     geht erst, wenn der vorige fertig oder gescheitert ist.
   - Alles landet als „neu“ in der Galerie und ist für Partner erst nach
     Uwes „Für Partner freigeben“ sichtbar (MkMedium::galerieFuerPartner).
   - Die Stimme nennt keine Adresse: Den Link des Partners setzt sein
     Dashboard beim Teilen ans Ende (partner-3d.js) — so führt jedes Video auf
     die Landingpage dessen, der es teilt.
   ========================================================================== */

final class MkStudio
{
    public const GRUPPEN = ['branche' => 'Branchen', 'vecom' => 'Vecom Design', 'partner' => 'Partner'];

    /**
     * Schlüssel => [Gruppe, Name in der Verwaltung, 3D-Szene (oder null), Bildidee für Kie (englisch),
     *               gesprochener Satz je Sprache].
     * Der Satz ist der Einstieg eines Trailers: kurz (8 s), konkret, ohne Adresse.
     */
    public const KATALOG = [
        'b_gastro' => ['branche', 'Restaurant: der gedeckte Tisch', 'gastro',
            'A family trattoria at golden hour, a waiter sets the last table, steam rises from a fresh plate of pasta, guests arrive in the doorway',
            ['de' => 'Ihr Essen ist großartig. Sieht man das auch online?', 'it' => 'La sua cucina è straordinaria. Online si vede?', 'en' => 'Your food is amazing. Does it show online?']],
        'b_wein' => ['branche', 'Weingut: Wein im Gewölbekeller', 'wein',
            'A vaulted wine cellar in Sicily, candlelight on old barrels, a winemaker pours red wine into a glass, slow push-in',
            ['de' => 'Jahrzehnte Arbeit in jeder Flasche. Erzählen Sie es der Welt.', 'it' => 'Anni di lavoro in ogni bottiglia. Lo racconti al mondo.', 'en' => 'Decades of work in every bottle. Tell the world.']],
        'b_salon' => ['branche', 'Friseur: der Platz im Salon', 'salon',
            'A stylish hair salon in the morning light, a stylist prepares the chair, mirrors and warm lamps, a client walks in smiling',
            ['de' => 'Ihre Kundinnen buchen nachts. Ist Ihr Salon dann offen?', 'it' => 'Le sue clienti prenotano di notte. Il suo salone è aperto?', 'en' => 'Your clients book at night. Is your salon open then?']],
        'b_schmuck' => ['branche', 'Juwelier: die Uhr im Licht', 'schmuck',
            'A luxury watch rotating slowly on a velvet stand in a jewellery shop, sparkling highlights, deep shadows, macro lens',
            ['de' => 'Wer Qualität verkauft, braucht einen Auftritt, der glänzt.', 'it' => 'Chi vende qualità merita una vetrina che brilli.', 'en' => 'Selling quality deserves a showcase that shines.']],
        'b_schuh' => ['branche', 'Einzelhandel: Schuh im Schaufenster', 'schuh',
            'A handmade leather shoe in a shop window at dusk, passers-by reflected in the glass, the shop lights switch on',
            ['de' => 'Ihr Schaufenster schließt um 19 Uhr. Ihre Website nie.', 'it' => 'La sua vetrina chiude alle 19. Il suo sito mai.', 'en' => 'Your shop window closes at 7 pm. Your website never.']],
        'b_kueche' => ['branche', 'Handwerk: die Küche mit Kochinsel', 'kueche',
            'A carpenter runs his hand over a finished kitchen island in a bright new kitchen, wood shavings in the light, satisfied nod',
            ['de' => 'Ihre Arbeit ist Maßarbeit. Ihre Website sollte es auch sein.', 'it' => 'Il suo lavoro è su misura. Anche il suo sito dovrebbe esserlo.', 'en' => 'Your work is made to measure. Your website should be too.']],
        'b_lkw' => ['branche', 'Transport: der Sattelzug', 'lkw',
            'A semi-truck drives along a Sicilian coastal highway at sunrise, aerial tracking shot, the sea glowing, cinematic',
            ['de' => 'Sie liefern pünktlich. Finden neue Kunden Sie genauso schnell?', 'it' => 'Lei consegna in orario. I nuovi clienti la trovano altrettanto in fretta?', 'en' => 'You deliver on time. Do new customers find you as fast?']],
        'b_auto' => ['branche', 'Autohaus: das Auto auf der Piazza', 'mittelklasse',
            'A polished car parked on a Baroque piazza in Sicily at blue hour, reflections of the church facade on the paint, slow orbit',
            ['de' => 'Ihre Autos glänzen. Ihr Online-Auftritt auch?', 'it' => 'Le sue auto brillano. E la sua presenza online?', 'en' => 'Your cars shine. Does your online presence?']],
        'b_werkstatt' => ['branche', 'Werkstatt: der Kleinwagen', 'kleinwagen',
            'A small car on a lift in a clean family garage, a mechanic wipes his hands, sunlight through the open door',
            ['de' => 'Ehrliche Arbeit spricht sich herum. Online noch schneller.', 'it' => 'Il lavoro onesto fa parlare di sé. Online ancora di più.', 'en' => 'Honest work gets talked about. Online even faster.']],
        'b_sport' => ['branche', 'Premium: Sportwagen an der Küste', 'auto',
            'A sports car on a winding cliff road above the Mediterranean, golden hour, drone shot following the curve',
            ['de' => 'Erster Eindruck. Zweite Chance gibt es nicht.', 'it' => 'Prima impressione. Una seconda non c’è.', 'en' => 'First impression. There is no second one.']],
        'v_trailer' => ['vecom', 'Vecom Design: Trailer mit Stimme', null,
            'A montage feel in one shot: dawn over a Sicilian town, shop shutters rolling up, a phone screen lights up in a hand, a business owner smiles',
            ['de' => 'Jeder Betrieb verdient es, gesehen zu werden. Vecom Design.', 'it' => 'Ogni attività merita di essere vista. Vecom Design.', 'en' => 'Every business deserves to be seen. Vecom Design.']],
        'v_spot' => ['vecom', 'Vecom Design: Werbespot über alle Branchen (Blender)', 'vecom', '', []],
        'p_vorort' => ['partner', 'Partner: Ihr Ansprechpartner vor Ort', null,
            'A friendly local consultant walks into a small family shop, shakes hands with the owner across the counter, warm daylight, handheld documentary feel',
            ['de' => 'Kein Callcenter. Ein Mensch aus Ihrer Nähe, der Ihre Website begleitet.', 'it' => 'Nessun call center. Una persona vicina a lei che segue il suo sito.', 'en' => 'No call centre. Someone nearby who looks after your website.']],
        'p_check' => ['partner', 'Partner: kostenloser Website-Check', null,
            'Close-up of a business owner checking a phone at the counter of a café, the screen glow on the face, a moment of realisation, cinematic shallow focus',
            ['de' => 'Wie gut ist Ihre Website wirklich? Prüfen Sie es kostenlos.', 'it' => 'Quanto è buono davvero il suo sito? Lo verifichi gratis.', 'en' => 'How good is your website really? Check it for free.']],
    ];

    public const MOTOREN = ['auto' => 'Automatisch — Kie.ai mit Stimme, ohne Guthaben Blender', 'kie' => 'Kie.ai mit Stimme (Veo 3.1 Quality, ~400 Credits)',
                            'spot' => 'Blender-Werbespot (20 s, Musik, keine Credits)', 'blender' => 'Blender (eine Fahrt bzw. ein Bild, keine Credits)',
                            'unreal' => 'Unreal Engine (Path Tracer, wenn freigeschaltet)'];

    /** Der laufende Studio-Auftrag, falls es einen gibt — dann ist kein zweiter erlaubt. */
    public static function laeuft(): ?array
    {
        try {
            return Db::one("SELECT * FROM mk_auftraege WHERE art = 'medien' AND status IN ('wartet', 'laeuft') AND parameter LIKE '%\"studio_katalog\":%' ORDER BY id LIMIT 1") ?: null;
        } catch (Throwable $e) { return null; }
    }

    /** Der Trailer-Prompt für Kie.ai: Bildidee, gesprochener Satz in der Sprache, Kino-Look, keine Schrift. */
    public static function prompt(string $schluessel, string $art, string $sprache): string
    {
        $k = self::KATALOG[$schluessel] ?? null;
        if ($k === null || $k[3] === '') { return ''; }
        $sp = in_array($sprache, ['de', 'it', 'en'], true) ? $sprache : 'de';
        $look = 'Cinematic movie-trailer look, hyperrealistic and photorealistic, anamorphic lens, shallow depth of field, motivated warm light, subtle film grain, '
              . 'tense build-up in the first two seconds. Absolutely no on-screen text, no numbers, no captions, no subtitles, no logos.';
        if ($art !== 'video') { return $k[3] . ".\n\n" . $look; }
        $sprachName = ['de' => 'German', 'it' => 'Italian', 'en' => 'English'][$sp];
        $satz = (string) ($k[4][$sp] ?? '');
        return $k[3] . ".\n\nVoiceover: a calm, confident native " . $sprachName . ' narrator says, clearly: "' . str_replace('"', "'", $satz) . "\"\n\n" . $look . ' Slow dolly or crane move, vertical framing safe.';
    }

    /**
     * Einen Eintrag produzieren. @return int|string Auftrag-ID oder Fehler (deutsch, für die Verwaltung)
     */
    public static function produzieren(string $schluessel, string $art, string $motor, string $sprache, string $format = ''): int|string
    {
        require_once __DIR__ . '/MkAuftrag.php';
        $k = self::KATALOG[$schluessel] ?? null;
        if ($k === null) { return 'Diesen Eintrag gibt es nicht.'; }
        if (!isset(MkMedium::ARTEN[$art])) { return 'Bild oder Video?'; }
        if (!isset(self::MOTOREN[$motor])) { $motor = 'auto'; }
        $sp = in_array($sprache, ['de', 'it', 'en'], true) ? $sprache : 'de';
        if (!in_array($format, MkMedium::FORMATE[$art], true)) { $format = $art === 'video' ? '9:16' : '4:5'; }
        MkAuftrag::aufraeumen();
        if (($l = self::laeuft()) !== null) {
            $lp = json_decode((string) $l['parameter'], true) ?: [];
            return 'Es läuft schon ein Studio-Auftrag („' . (string) ($lp['titel'] ?? '') . '“). Der nächste geht, sobald er fertig ist.';
        }
        $szene = $k[2];
        $kieMoeglich = $k[3] !== '';
        if ($motor === 'auto') { $motor = $kieMoeglich ? 'kie' : ($art === 'video' ? 'spot' : 'blender'); }
        if ($motor === 'kie' && !$kieMoeglich) { return 'Für „' . $k[1] . '“ gibt es nur die 3D-Fassung.'; }
        if ($motor !== 'kie' && $szene === null) { return 'Für „' . $k[1] . '“ gibt es keine 3D-Szene — bitte Kie.ai wählen.'; }
        if ($motor === 'unreal' && !MkMedium::motor()['unreal_bereit']) { return 'Unreal ist unter „Motor“ noch nicht freigeschaltet.'; }
        if (in_array($motor, ['spot', 'unreal'], true) && $art !== 'video') { $motor = 'blender'; }
        $titel = mb_substr('Studio · ' . $k[1] . ' · ' . strtoupper($sp), 0, 80);
        if ($motor === 'kie') {
            $modell = $art === 'video' ? 'veo3' : (string) array_key_first(MkMedium::MODELLE['bild']);
            $param = ['inhalt_id' => 0, 'medium' => $art, 'modell' => $modell, 'format' => $format, 'prompt' => self::prompt($schluessel, $art, $sp),
                      'eigener_prompt' => false, 'startbild' => null, 'credits_ca' => MkMedium::MODELLE[$art][$modell][1], 'titel' => $titel, 'galerie' => 1, 'studio' => $szene ?? 'studio'];
        } elseif ($szene === 'vecom') {
            $r = MkMedium::anlegenVecomSpot($art === 'video' ? $format : '9:16', $sp);
            if (!is_int($r)) { return $r; }
            self::markieren($r, $schluessel, $sp, $motor);
            return $r;
        } else {
            $motiv = MkMedium::STUDIO_MOTIV[$szene] ?? 'allgemein';
            require_once __DIR__ . '/Texte.php';
            $filmTitel = (string) ($k[4][$sp] ?? Texte::h(Texte::PARTNER_MEDIEN['motive'][$motiv]['titel'] ?? ['de' => ''], $sp));
            $param = ['inhalt_id' => 0, 'medium' => $art, 'modell' => $motor, 'format' => $format, 'prompt' => '', 'startbild' => null, 'credits_ca' => 0, 'titel' => $titel,
                      'drei_d' => true, 'studio' => $szene, 'generativ' => false, 'seed' => random_int(1, 999999), 'sofort' => false, 'sprache' => $sp,
                      'film_titel' => mb_substr($filmTitel, 0, 70), 'abspann' => 'vecom-design.it', 'galerie' => 1];
            if ($motor === 'spot') { $param['spot'] = MkMedium::spotTexte('Vecom Design', (string) ($k[4][$sp] ?? ''), 'vecom-design.it', $sp); }
        }
        $param['ende'] = 1;
        $id = (int) Db::insert('mk_auftraege', ['art' => 'medien', 'branche' => '', 'land' => $sp === 'de' ? 'DE' : 'IT',
                                                'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        self::markieren($id, $schluessel, $sp, $motor);
        try { Events::protokoll('studio', 'Studio: „' . $k[1] . '“ (' . $art . ', ' . self::MOTOREN[$motor] . ', ' . $sp . ')', null, null, null, ['auftrag_id' => $id]); } catch (Throwable $e) { }
        return $id;
    }

    /** Den Auftrag als Studio-Auftrag kennzeichnen (Katalog, Sprache, Motor) — daran hängen „eins nach dem anderen“ und der Ersatz ohne Kie-Guthaben. */
    private static function markieren(int $id, string $schluessel, string $sp, string $motor): void
    {
        $p = json_decode((string) Db::wert('SELECT parameter FROM mk_auftraege WHERE id = ?', [$id], '{}'), true) ?: [];
        $p['studio_katalog'] = $schluessel; $p['studio_sprache'] = $sp; $p['studio_motor'] = $motor;
        Db::run('UPDATE mk_auftraege SET parameter = ? WHERE id = ?', [json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id]);
    }

    /**
     * Ist ein Kie-Auftrag des Studios am Guthaben gescheitert, kommt derselbe Eintrag als Blender-Fassung
     * (Video: Werbespot mit Musik). Wird aus MkAuftrag::melden gerufen. @return int|null neuer Auftrag
     */
    public static function nachKieFehler(array $auftrag, string $text): ?int
    {
        $p = json_decode((string) ($auftrag['parameter'] ?? ''), true) ?: [];
        if (empty($p['studio_katalog']) || ($p['studio_motor'] ?? '') !== 'kie' || stripos($text, 'Guthaben') === false) { return null; }
        $k = self::KATALOG[(string) $p['studio_katalog']] ?? null;
        if ($k === null || $k[2] === null) { return null; }
        $r = self::produzieren((string) $p['studio_katalog'], (string) ($p['medium'] ?? 'video'), ($p['medium'] ?? 'video') === 'video' ? 'spot' : 'blender',
                               (string) ($p['studio_sprache'] ?? 'de'), (string) ($p['format'] ?? ''));
        return is_int($r) ? $r : null;
    }

    /**
     * Alle bisherigen Galerie-Medien aus Dashboard und Verwaltung nehmen (Uwe, 03.10.2026: „lösche alle“).
     * Umkehrbar: Status „verworfen“, die Dateien bleiben bis zum endgültigen Löschen liegen.
     * Offene Galerie-Aufträge und Partner-Wünsche werden abgebrochen. @return array{medien:int, auftraege:int}
     */
    public static function galerieLeeren(): array
    {
        $m = Db::run("UPDATE mk_medien SET status = 'verworfen' WHERE inhalt_id = 0 AND (galerie = 1 OR partner_id IS NOT NULL) AND status <> 'verworfen'")->rowCount();
        $a = Db::run("UPDATE mk_auftraege SET status = 'abgebrochen', ergebnis = 'Galerie geleert' WHERE art = 'medien' AND status IN ('wartet', 'pruefen')
                      AND (parameter LIKE '%\"galerie\":1%' OR parameter LIKE '%\"partner_id\":%')")->rowCount();
        try { Events::pruefspur('galerie_leeren', 'mk_medien', 0, [], ['medien' => $m, 'auftraege' => $a]); } catch (Throwable $e) { }
        return ['medien' => $m, 'auftraege' => $a];
    }
}
