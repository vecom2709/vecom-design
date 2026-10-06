<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkKampagne.php';
require_once __DIR__ . '/MkZielgruppe.php';
require_once __DIR__ . '/MkInhalt.php';

/**
 * Recherche per Knopf (Marketing-Studio, 01.10.2026, Uwe: „Recherche soll
 * automatisch starten, wenn in der Verwaltung geklickt wird — im Moment muss
 * man Claude im Chat schreiben“).
 *
 * Die Verwaltung kann den PC nicht anstoßen (Router dazwischen) und ruft
 * selbst keine KI auf (Uwe: über sein Claude-Abo). Deshalb:
 *
 *   Knopf  → Auftrag „wartet“
 *   PC     → fragt alle 5 Minuten nach (steuern), holt ihn ab → „läuft“,
 *            startet Claude Code mit Uwes Anmeldung, nur Websuche und Webseiten
 *   Claude → liefert Zielgruppen und Funde als ENTWÜRFE über die Worker-Tür
 *   PC     → meldet „fertig“ oder „Fehler“ mit Zahlen
 *
 * Freigegeben wird weiterhin nur in der Verwaltung.
 */
final class MkAuftrag
{
    public const STATUS = ['wartet' => 'wartet auf deinen PC', 'laeuft' => 'Claude recherchiert', 'fertig' => 'fertig',
                           'fehler' => 'nicht geklappt', 'abgebrochen' => 'abgebrochen'];
    /** Schutz fürs Claude-Abo: so viele Aufträge am Tag. */
    public const PRO_TAG = 8;
    /** Meldet der PC sich so lange nicht zurück, gilt der Auftrag als gescheitert. */
    public const HOECHSTENS_MIN = 75;
    /** 3D-Läufe (Blender/Unreal, Marketing-Studio 11): bis zu acht Stunden. */
    public const DREI_D_MIN = 480;
    /** So viele fehlende Zielgruppen nimmt ein Lauf „alle Branchen“ mit. */
    public const FEHLENDE_JE_LAUF = 2;

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /** Was der Auftrag tut — in einem Satz. */
    public static function beschreibung(array $a): string
    {
        if (($a['art'] ?? '') === 'ton') {   // Akquise-CRM D-2
            $p = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
            require_once __DIR__ . '/AkquiseWerkstatt.php';
            $fn = self::still(static fn() => (string) Db::wert('SELECT name FROM akq_firmen WHERE id = ?', [(int) ($p['firma_id'] ?? 0)], ''), '');
            return 'Umformulieren · ' . (AkquiseWerkstatt::TOENE[(string) ($p['ton'] ?? '')][0] ?? 'Text') . ' · ' . (($p['kanal'] ?? '') === 'whatsapp' ? 'WhatsApp' : 'E-Mail') . ($fn !== '' ? ' an ' . $fn : '');
        }
        if (($a['art'] ?? 'recherche') === 'uebersetzen') {
            $p = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
            if ((int) ($p['partnerseiten'] ?? 0) > 0 && (int) ($p['profile'] ?? 0) + (int) ($p['inhalte'] ?? 0) === 0) { return 'Übersetzung · Texte von ' . (int) $p['partnerseiten'] . ' Partnerseite(n)'; }
            return 'Deutsche Fassung · ' . (int) ($p['profile'] ?? 0) . ' Zielgruppen, ' . (int) ($p['inhalte'] ?? 0) . ' Inhalte auf Italienisch';
        }
        if (($a['art'] ?? 'recherche') === 'seite') {   // S6
            require_once __DIR__ . '/MkSeite.php';
            return MkSeite::beschreibung($a);
        }
        if (($a['art'] ?? 'recherche') === 'demo') {   // Marketing-Studio 10
            $p = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
            $f = self::still(static fn() => (string) Db::wert('SELECT f.name FROM mk_demos d JOIN akq_firmen f ON f.id = d.akq_firma_id WHERE d.id = ?', [(int) ($p['demo_id'] ?? 0)], ''), '');
            return 'Demo-Vorschau · ' . ($f !== '' ? $f : 'Interessent');
        }
        if (($a['art'] ?? 'recherche') === 'medien') {
            $p = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
            require_once __DIR__ . '/MkMedium.php';
            $art = (string) ($p['medium'] ?? 'bild');
            return ($art === 'video' ? 'Video' : 'Bild') . ' · ' . (string) ($p['titel'] ?? '') . ' · ' . (string) ($p['format'] ?? '')
                . ' · ' . (MkMedium::MODELLE[$art][$p['modell'] ?? ''][0] ?? (string) ($p['modell'] ?? '')) . (!empty($p['startbild']) ? ' · aus dem gewählten Bild' : '');
        }
        if (($a['art'] ?? 'recherche') === 'inhalte') {
            $p = json_decode((string) ($a['parameter'] ?? ''), true) ?: [];
            $pl = implode(', ', array_map(static fn($x) => MkKampagne::PLATTFORMEN[$x] ?? $x, (array) ($p['plattformen'] ?? [])));
            return (!empty($p['kanalplan']) ? 'Kanal-Plan Telegram · ' : (!empty($p['autopilot']) ? 'Autopilot · ' : (!empty($p['paket']) ? 'Kampagne · ' : 'Inhalte · '))) . (string) ($p['zielgruppe_titel'] ?? 'Zielgruppe') . ' — ' . (int) ($p['anzahl'] ?? 0) . ' Stück'
                . (!empty($p['mit_bildern']) ? ' mit Bildern' : '')
                . ($pl !== '' ? ' für ' . $pl : '') . (($p['umfang'] ?? 'beides') !== 'beides' ? ' (' . (MkInhalt::ARTEN[$p['umfang']] ?? $p['umfang']) . ')' : '');
        }
        $land = MkZielgruppe::LAENDER[$a['land']] ?? $a['land'];
        if ($a['branche'] === '') { return 'Alle Branchen · ' . $land . ' — neue Funde und fehlende Zielgruppen'; }
        return (MkKampagne::branchen()[$a['branche']] ?? $a['branche']) . ' · ' . $land . ' — Zielgruppe und Funde';
    }

    /** @return int|string  Auftragsnummer oder Hinweis */
    public static function anlegen(string $branche, string $land): int|string
    {
        $land = strtoupper($land);
        if ($branche !== '' && !isset(MkKampagne::branchen()[$branche])) { return 'Unbekannte Branche.'; }
        if (!isset(MkZielgruppe::LAENDER[$land])) { return 'Land muss Italien oder Deutschland sein.'; }
        self::aufraeumen();
        $offen = Db::one("SELECT id, status FROM mk_auftraege WHERE art = 'recherche' AND branche = ? AND land = ? AND status IN ('wartet','laeuft') LIMIT 1", [$branche, $land]);
        if ($offen) { return $offen['status'] === 'laeuft' ? 'Diese Recherche läuft gerade schon.' : 'Diese Recherche wartet schon auf deinen PC.'; }
        if (self::heute('recherche') >= self::PRO_TAG) { return 'Heute sind schon ' . self::PRO_TAG . ' Recherchen gelaufen — das schont dein Claude-Abo. Morgen geht es weiter.'; }
        $id = (int) Db::insert('mk_auftraege', ['art' => 'recherche', 'branche' => $branche, 'land' => $land]);
        Events::protokoll('recherche_auftrag', 'Recherche angestoßen: ' . self::beschreibung(['branche' => $branche, 'land' => $land]), null, null, null, ['auftrag_id' => $id]);
        return $id;
    }

    /**
     * Deutsche Fassung nachholen (Marketing-Studio 5, Uwe: „für die
     * Verwaltung auf Deutsch anzeigen, dass wir es lesen können“): ein
     * Auftrag für alles Italienische ohne Übersetzung. Neue Recherchen und
     * Inhalte bringen die deutsche Fassung gleich mit — das hier holt nur
     * nach, was vorher entstand.
     * @return int|string
     */
    public static function anlegenUebersetzen(): int|string
    {
        self::aufraeumen();
        if (Db::one("SELECT id FROM mk_auftraege WHERE art = 'uebersetzen' AND status IN ('wartet','laeuft') LIMIT 1")) { return 'Die Übersetzung läuft schon — oder wartet auf deinen PC.'; }
        $o = MkZielgruppe::ohneUebersetzung();
        if ($o['profile'] === [] && $o['inhalte'] === []) { return 'Es ist schon alles auf Deutsch zu lesen.'; }
        if (self::heute('uebersetzen') >= self::PRO_TAG) { return 'Heute sind schon ' . self::PRO_TAG . ' Übersetzungen gelaufen. Morgen geht es weiter.'; }
        $param = ['profile' => count($o['profile']), 'inhalte' => count($o['inhalte'])];
        $id = (int) Db::insert('mk_auftraege', ['art' => 'uebersetzen', 'branche' => '', 'land' => 'IT', 'parameter' => json_encode($param)]);
        Events::protokoll('uebersetzen_auftrag', 'Übersetzung angestoßen: ' . self::beschreibung(['art' => 'uebersetzen', 'parameter' => json_encode($param)]), null, null, null, ['auftrag_id' => $id]);
        return $id;
    }

    private static function heute(string $art): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = ? AND created_at >= CURDATE() AND status <> 'abgebrochen'", [$art], 0);
    }

    /**
     * Content-Studio: Claude schreibt Inhalte für eine FREIGEGEBENE Zielgruppe.
     * $p: zielgruppe (id), plattformen[], umfang organisch|bezahlt|beides, anzahl 3–12, thema, kampagne (id, optional)
     * @return int|string
     */
    public static function anlegenInhalte(array $p): int|string
    {
        $zgId = (int) ($p['zielgruppe'] ?? 0);
        $zg = Db::one('SELECT id, branche, land, titel, status, vorher FROM mk_zielgruppen WHERE id = ?', [$zgId]);
        if (!$zg) { return 'Bitte eine Zielgruppe wählen.'; }
        if ($zg['status'] !== 'freigegeben' && $zg['vorher'] === null) { return 'Diese Zielgruppe ist noch ein Entwurf — erst freigeben, dann schreibt Claude dafür.'; }
        $pl = array_values(array_intersect(MkInhalt::PLATTFORMEN, array_map('strval', (array) ($p['plattformen'] ?? []))));
        if ($pl === []) { return 'Bitte mindestens eine Plattform wählen.'; }
        $umfang = (string) ($p['umfang'] ?? 'beides');
        if (!in_array($umfang, ['organisch', 'bezahlt', 'beides'], true)) { $umfang = 'beides'; }
        if (MkInhalt::formateFuerClaude($pl, $umfang) === []) { return 'Für diese Plattformen gibt es kein ' . (MkInhalt::ARTEN[$umfang] ?? '') . ' Format — z. B. Anzeigen nur auf Facebook, Instagram und Google.'; }
        $anzahl = max(3, min(12, (int) ($p['anzahl'] ?? 6)));
        $kampagne = (int) ($p['kampagne'] ?? 0);
        if ($kampagne > 0 && MkKampagne::laden($kampagne) === null) { return 'Kampagne nicht gefunden.'; }
        self::aufraeumen();
        if (Db::one("SELECT id FROM mk_auftraege WHERE art = 'inhalte' AND status IN ('wartet','laeuft') AND parameter LIKE ? LIMIT 1", ['%"zielgruppe_id":' . $zgId . ',%'])) {
            return 'Für diese Zielgruppe schreibt Claude gerade schon — oder der Auftrag wartet auf deinen PC.';
        }
        if (self::heute('inhalte') >= self::PRO_TAG) { return 'Heute sind schon ' . self::PRO_TAG . ' Schreibaufträge gelaufen — das schont dein Claude-Abo. Morgen geht es weiter.'; }
        $param = ['zielgruppe_id' => $zgId, 'zielgruppe_titel' => mb_substr((string) $zg['titel'], 0, 80), 'plattformen' => $pl, 'umfang' => $umfang,
                  'anzahl' => $anzahl, 'thema' => mb_substr(trim(strip_tags((string) ($p['thema'] ?? ''))), 0, 200), 'kampagne_id' => $kampagne > 0 ? $kampagne : null,
                  /* Marketing-Studio 6: Kampagnen-Paket (feste Mischung) und Bilder gleich mit (Kie.ai, nach der Lieferung). */
                  'paket' => !empty($p['paket']), 'mit_bildern' => !empty($p['mit_bildern']), 'autopilot' => !empty($p['autopilot']),
                  /* Redaktionsplan des Telegram-Kanals (01.10.2026, TelegramKanalPlan): meldet sich wie ein Paket, wenn die Entwürfe da sind. */
                  'kanalplan' => !empty($p['kanalplan']),
                  /* TikTok täglich (03.10.2026, MkTiktokTakt): Stimme und Werbespot im Wechsel, Meldung wie ein Paket. */
                  'tiktok_takt' => !empty($p['tiktok_takt'])];
        $id = (int) Db::insert('mk_auftraege', ['art' => 'inhalte', 'branche' => (string) $zg['branche'], 'land' => (string) $zg['land'],
                                                'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        Events::protokoll('inhalte_auftrag', 'Inhalte angestoßen: ' . self::beschreibung(['art' => 'inhalte', 'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE)]), null, null, null, ['auftrag_id' => $id]);
        return $id;
    }

    /** Was ein Kampagnen-Paket enthält — je nach Wahl organisch, Anzeigen oder beides. @return array{0:list<string>,1:string,2:int} [Plattformen, Umfang, Anzahl] */
    public static function paketMischung(bool $organisch, bool $anzeigen, ?bool $tiktok = null): array
    {
        /* TikTok (02.10.2026, Uwe: „mache in Marketing auch alles fertig automatisch wegen TikTok“):
           Sobald es das Konto gibt, gehören zwei Kurzvideos je Paket dazu — mit Video statt Bild. */
        if ($tiktok === null) { require_once __DIR__ . '/MkAnmeldungen.php'; $tiktok = MkAnmeldungen::kontoStand('tiktok') !== 'offen'; }
        $tt = $tiktok ? ['tiktok'] : [];
        $mehr = $tiktok ? 2 : 0;
        if ($organisch && $anzeigen) { return [array_merge(['instagram', 'facebook', 'telegram'], $tt, ['google']), 'beides', 8 + $mehr]; }
        if ($anzeigen) { return [['instagram', 'facebook', 'google'], 'bezahlt', 4]; }
        return [array_merge(['instagram', 'facebook', 'telegram'], $tt), 'organisch', 6 + $mehr];
    }

    /**
     * Ein-Klick-Kampagne (Marketing-Studio 6, Uwe: „ja“ zu U3): aus einer
     * freigegebenen Zielgruppe ein fertiges Paket — Claude schreibt die feste
     * Mischung, Kie.ai bebildert (wenn gewünscht), Uwe geht alles im
     * Freigabe-Stapel durch. Jeder Link führt auf den Website-Check.
     * @return int|string
     */
    public static function anlegenKampagne(int $zgId, array $p): int|string
    {
        $organisch = !empty($p['organisch']); $anzeigen = !empty($p['anzeigen']);
        if (!$organisch && !$anzeigen) { return 'Bitte Beiträge, Anzeigen oder beides wählen.'; }
        [$pl, $umfang, $anzahl] = self::paketMischung($organisch, $anzeigen);
        return self::anlegenInhalte(['zielgruppe' => $zgId, 'plattformen' => $pl, 'umfang' => $umfang, 'anzahl' => $anzahl,
            'thema' => (string) ($p['thema'] ?? ''), 'paket' => true, 'mit_bildern' => !empty($p['bilder']), 'autopilot' => !empty($p['autopilot'])]);
    }

    public static function abbrechen(int $id): ?string
    {
        $n = Db::run("UPDATE mk_auftraege SET status = 'abgebrochen', fertig_am = NOW() WHERE id = ? AND status = 'wartet'", [$id])->rowCount();
        return $n > 0 ? null : 'Nur wartende Aufträge lassen sich abbrechen.';
    }

    /** Hängengebliebene Läufe beenden (PC aus, Claude abgestürzt …). */
    public static function aufraeumen(): void
    {
        /* 3D-Aufträge (Marketing-Studio 11) rechnen länger — ein Film mit 192 Bildern dauert auf der RTX 5070 eine gute Stunde. */
        Db::run("UPDATE mk_auftraege SET status = 'fehler', fertig_am = NOW(),
                        ergebnis = 'Keine Rückmeldung vom PC — vermutlich ausgeschaltet oder abgebrochen. Einfach neu starten.'
                  WHERE status = 'laeuft' AND gestartet_am < NOW() - INTERVAL " . self::HOECHSTENS_MIN . " MINUTE AND (parameter IS NULL OR parameter NOT LIKE '%\"drei_d\":true%')");
        Db::run("UPDATE mk_auftraege SET status = 'fehler', fertig_am = NOW(),
                        ergebnis = 'Keine Rückmeldung vom PC nach " . intdiv(self::DREI_D_MIN, 60) . " Stunden — 3D-Lauf abgebrochen? Einfach neu starten.'
                  WHERE status = 'laeuft' AND gestartet_am < NOW() - INTERVAL " . self::DREI_D_MIN . " MINUTE AND parameter LIKE '%\"drei_d\":true%'");
    }

    /** Für befehl_holen: wartet etwas? (vor der Migration still: nein) */
    public static function wartet(): bool
    {
        return (bool) self::still(static fn() => (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE status = 'wartet'", [], 0) > 0, false);
    }

    /** Die letzten Aufträge einer oder mehrerer Arten — mit $land nur die dieses Landes. */
    public static function liste(int $max = 6, string|array $art = 'recherche', ?string $land = null): array
    {
        self::aufraeumen();
        $arten = array_values((array) $art);
        $w = 'art IN (' . implode(',', array_fill(0, count($arten), '?')) . ')';
        if ($land !== null) { $w .= ' AND land = ?'; $arten[] = $land; }
        return Db::all('SELECT * FROM mk_auftraege WHERE ' . $w . ' ORDER BY id DESC LIMIT ' . max(1, min(50, $max)), $arten);
    }

    public static function offen(string $art = 'recherche'): bool
    {
        return (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege WHERE art = ? AND status IN ('wartet','laeuft')", [$art], 0) > 0;
    }

    /**
     * Für den PC: den ältesten wartenden Auftrag übernehmen, samt allem, was
     * Claude dafür braucht (ohne Personen).
     * @return array{ok:bool, auftrag:?array}
     */
    public static function holen(): array
    {
        self::aufraeumen();
        for ($versuch = 0; $versuch < 3; $versuch++) {
            /* Nachtschicht (Marketing-Studio 11): 3D-Aufträge nur im Fenster oder mit „Jetzt rechnen“ — alles andere wie bisher der Reihe nach. */
            $a = null;
            $fenster = null;
            foreach (Db::all("SELECT * FROM mk_auftraege WHERE status = 'wartet' ORDER BY id LIMIT 40") as $kand) {
                if (($kand['art'] ?? '') === 'medien' && str_contains((string) $kand['parameter'], '"drei_d":true')) {
                    $kp = json_decode((string) $kand['parameter'], true) ?: [];
                    if (empty($kp['sofort'])) {
                        require_once __DIR__ . '/MkMedium.php';
                        $fenster ??= MkMedium::imFenster();
                        if (!$fenster) { continue; }
                    }
                }
                $a = $kand; break;
            }
            if (!$a) { return ['ok' => true, 'auftrag' => null]; }
            $n = Db::run("UPDATE mk_auftraege SET status = 'laeuft', gestartet_am = NOW() WHERE id = ? AND status = 'wartet'", [(int) $a['id']])->rowCount();
            if ($n === 0) { continue; }   // ein anderer Abruf war schneller
            if (($a['art'] ?? 'recherche') === 'inhalte') { return ['ok' => true, 'auftrag' => self::inhalteAuftrag($a)]; }
            if (($a['art'] ?? '') === 'ton') {
                /* Akquise-CRM D-2: einen Text umformulieren — ohne Werkzeuge, nur mit dem, was im Text steht. */
                require_once __DIR__ . '/AkquiseWerkstatt.php';
                $tn = AkquiseWerkstatt::fuerPc($a);
                if ($tn === null) { Db::update('mk_auftraege', (int) $a['id'], ['status' => 'abgebrochen', 'ergebnis' => 'Vorschlag nicht mehr da.']); continue; }
                return ['ok' => true, 'auftrag' => ['id' => (int) $a['id'], 'art' => 'ton', 'beschreibung' => self::beschreibung($a)] + $tn];
            }
            if (($a['art'] ?? 'recherche') === 'uebersetzen') {
                require_once __DIR__ . '/PartnerSeite.php';   // E4 (03.10.2026): Texte der Partnerseiten fahren mit
                return ['ok' => true, 'auftrag' => ['id' => (int) $a['id'], 'art' => 'uebersetzen', 'beschreibung' => self::beschreibung($a)] + MkZielgruppe::ohneUebersetzung()
                    + ['partnerseiten' => self::still(static fn() => PartnerSeite::ohneUebersetzung(), [])]];
            }
            if (($a['art'] ?? 'recherche') === 'seite') {
                /* S6: Landingpage — Claude schreibt aus dem freigegebenen Profil. */
                require_once __DIR__ . '/MkSeite.php';
                $sp = MkSeite::fuerPc($a);
                if ($sp === null) { Db::update('mk_auftraege', (int) $a['id'], ['status' => 'abgebrochen', 'ergebnis' => 'Zielgruppe nicht mehr freigegeben.']); continue; }
                return ['ok' => true, 'auftrag' => ['id' => (int) $a['id'], 'art' => 'seite', 'beschreibung' => self::beschreibung($a)] + $sp];
            }
            if (($a['art'] ?? 'recherche') === 'demo') {
                /* Marketing-Studio 10: Demo-Vorschau — Claude baut eine Startseite aus der bisherigen Website. */
                require_once __DIR__ . '/MkDemo.php';
                $dm = MkDemo::fuerPc($a);
                if ($dm === null) { Db::update('mk_auftraege', (int) $a['id'], ['status' => 'abgebrochen', 'ergebnis' => 'Vorschau nicht mehr da.']); continue; }
                return ['ok' => true, 'auftrag' => ['id' => (int) $a['id'], 'art' => 'demo', 'beschreibung' => self::beschreibung($a)] + $dm];
            }
            if (($a['art'] ?? 'recherche') === 'medien') {
                require_once __DIR__ . '/MkMedium.php';
                $p = json_decode((string) $a['parameter'], true) ?: [];
                return ['ok' => true, 'auftrag' => ['id' => (int) $a['id'], 'art' => 'medien', 'beschreibung' => self::beschreibung($a),
                    'medium' => (string) ($p['medium'] ?? 'bild'), 'modell' => (string) ($p['modell'] ?? ''), 'format' => (string) ($p['format'] ?? ''),
                    'prompt' => (string) ($p['prompt'] ?? ''), 'startbild' => $p['startbild'] ?? null, 'credits_ca' => (int) ($p['credits_ca'] ?? 0),
                    'teil_bytes' => MkMedium::TEIL_BYTES, 'max_bytes' => MkMedium::MAX_BYTES,
                    /* Marketing-Studio 9: Vorher/Nachher — der PC fotografiert statt Kie.ai. */
                    'vn' => $p['vn'] ?? null,
                    /* Marketing-Studio 11: 3D auf dem PC — Szene, Blickwinkel, Filmtexte. */
                    'drei_d' => !empty($p['drei_d']) ? ['studio' => $p['studio'] ?? null, 'generativ' => !empty($p['generativ']), 'seed' => (int) ($p['seed'] ?? 0),
                        'sprache' => (string) ($p['sprache'] ?? 'it'), 'film_titel' => (string) ($p['film_titel'] ?? ''), 'abspann' => (string) ($p['abspann'] ?? ''),
                        /* Partner-Wunsch (W1–W3): Feinwahl für Branchen-Szenen; bei eigener Idee ist prompt der Wunschtext. */
                        'wunsch' => isset($p['wunsch']) && is_array($p['wunsch']) ? $p['wunsch'] : null, 'partner_wunsch' => isset($p['wunschtext']) && $p['wunschtext'] !== '',
                        /* Werbespot (01.10.2026): Abspann, beim Vecom-Spot die Szenenfolge und das goldene V */
                        'spot' => isset($p['spot']) && is_array($p['spot']) ? $p['spot'] : null,
                        /* Titelbild einer Partnerseite (03.10.2026, B1/B3/B4): Schleife, Jahreszeit, Länge, Pixel */
                        'kopf' => isset($p['kopf']) && is_array($p['kopf']) ? ['schleife' => !empty($p['kopf']['schleife']), 'saison' => (string) ($p['kopf']['saison'] ?? ''),
                            'sekunden' => (int) ($p['kopf']['sekunden'] ?? 0), 'px' => (string) ($p['kopf']['px'] ?? '')] : null] : null]];
            }
            $branche = (string) $a['branche'];
            $land = (string) $a['land'];
            $daten = MkZielgruppe::datenFuerClaude($branche !== '' ? $branche : null, $land);
            $ziele = [];
            if ($branche !== '') {
                $ziele[] = $branche;
            } else {
                foreach (MkZielgruppe::fehlend(20) as $f) {
                    if ($f['land'] === $land) { $ziele[] = $f['branche']; }
                    if (count($ziele) >= self::FEHLENDE_JE_LAUF) { break; }
                }
            }
            $vorhanden = [];
            foreach ($ziele as $b) {
                $p = Db::one('SELECT profil FROM mk_zielgruppen WHERE branche = ? AND land = ?', [$b, $land]);
                if ($p) { $vorhanden[$b] = json_decode((string) $p['profil'], true) ?: null; }
            }
            return ['ok' => true, 'auftrag' => [
                'id' => (int) $a['id'], 'art' => 'recherche', 'branche' => $branche, 'land' => $land,
                'beschreibung' => self::beschreibung($a),
                'zielgruppen_fuer' => array_map(static fn($b) => ['branche' => $b, 'name' => MkKampagne::branchen()[$b] ?? $b], $ziele),
                'vorhandene_profile' => $vorhanden,
                'daten' => $daten,
            ]];
        }
        return ['ok' => true, 'auftrag' => null];
    }

    /** Was Claude zum Schreiben bekommt: freigegebenes Profil, Funde der Branche, Formate, bisherige Titel (gegen Wiederholung). */
    private static function inhalteAuftrag(array $a): array
    {
        $p = json_decode((string) $a['parameter'], true) ?: [];
        $zg = Db::one('SELECT * FROM mk_zielgruppen WHERE id = ?', [(int) ($p['zielgruppe_id'] ?? 0)]);
        $profil = $zg ? MkZielgruppe::freigegeben((string) $zg['branche'], (string) $zg['land']) : null;
        $funde = [];
        foreach (array_merge(MkZielgruppe::recherche(['branche' => (string) $a['branche']], 30), MkZielgruppe::recherche([], 30)) as $f) {
            if (isset($funde[$f['id']]) || ($f['branche'] !== '' && $f['branche'] !== $a['branche']) || ($f['land'] !== '' && $f['land'] !== $a['land'])) { continue; }
            $funde[$f['id']] = ['id' => (int) $f['id'], 'art' => $f['art'], 'titel' => $f['titel'], 'text' => $f['text'], 'relevanz' => (int) $f['relevanz'],
                                'gemerkt' => $f['status'] === 'gemerkt', 'quellen' => array_map(static fn($q) => $q['url'], $f['q'])];
            if (count($funde) >= 20) { break; }
        }
        $titel = array_map(static fn($r) => (string) $r['titel'], Db::all('SELECT titel FROM mk_inhalte WHERE zielgruppe_id = ? ORDER BY id DESC LIMIT 40', [(int) ($p['zielgruppe_id'] ?? 0)]));
        $k = (int) ($p['kampagne_id'] ?? 0) > 0 ? MkKampagne::laden((int) $p['kampagne_id']) : null;
        return [
            'id' => (int) $a['id'], 'art' => 'inhalte', 'branche' => (string) $a['branche'], 'land' => (string) $a['land'],
            'beschreibung' => self::beschreibung($a),
            'zielgruppe' => ['id' => (int) ($zg['id'] ?? 0), 'name' => MkKampagne::branchen()[$a['branche']] ?? $a['branche'], 'profil' => $profil],
            'plattformen' => (array) ($p['plattformen'] ?? []), 'umfang' => (string) ($p['umfang'] ?? 'beides'), 'anzahl' => (int) ($p['anzahl'] ?? 6),
            'thema' => (string) ($p['thema'] ?? ''),
            'formate' => MkInhalt::formateFuerClaude((array) ($p['plattformen'] ?? []), (string) ($p['umfang'] ?? 'beides')),
            'grenzen' => MkInhalt::G, 'meta_cta' => MkInhalt::META_CTA,
            /* S6: Hat die Zielgruppe eine eigene Landingpage online, führen die Beiträge dorthin. */
            'zielseite' => $k ? (string) $k['ziel'] : (self::still(static function () use ($p): ?string { require_once __DIR__ . '/MkSeite.php'; return MkSeite::pfadFuerZielgruppe((int) ($p['zielgruppe_id'] ?? 0)); }, null)
                           ?? MkInhalt::CHECK . ($a['land'] === 'DE' ? '?lang=de' : '')),
            'paket' => !empty($p['paket']),
            /* S1: Ist die automatische Antwort auf Kommentare eingeschaltet, dürfen Beiträge „Kommentiere STICHWORT“ sagen. */
            'kommentar_automatik' => (string) self::still(static fn() => Db::wert("SELECT svalue FROM settings WHERE skey = 'mk_kommentar_an'", [], ''), '') === '1',
            'funde' => array_values($funde), 'bisherige_titel' => $titel,
        ];
    }

    /** Für den PC: fertig oder gescheitert. */
    public static function melden(array $d): array
    {
        $id = (int) ($d['id'] ?? 0);
        $ok = !empty($d['ok']);
        $text = mb_substr(trim(strip_tags((string) ($d['text'] ?? ''))), 0, 1000);
        $zg = max(0, min(999, (int) ($d['zielgruppen'] ?? 0)));
        $fu = max(0, min(999, (int) ($d['funde'] ?? 0)));
        $a = Db::one('SELECT * FROM mk_auftraege WHERE id = ?', [$id]);
        if (!$a) { return ['ok' => false, 'hinweis' => 'Auftrag unbekannt.']; }
        $in = max(0, min(999, (int) ($d['inhalte'] ?? 0)));
        $istInhalt = ($a['art'] ?? 'recherche') === 'inhalte';
        if (($a['art'] ?? '') === 'ton') {   // D-2: der Text selbst kommt über akquise_ton_melden; hier nur „nicht geklappt“
            if (!in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => true]; }
            if (!$ok) {
                Db::update('mk_auftraege', $id, ['status' => 'fehler', 'ergebnis' => $text !== '' ? $text : 'Nicht geklappt.', 'fertig_am' => date('Y-m-d H:i:s')]);
                Db::run("UPDATE akq_textvorschlaege SET status = 'abgelehnt', grund = ?, fertig_am = NOW() WHERE auftrag_id = ? AND status = 'wartet'", [mb_substr($text !== '' ? $text : 'Nicht geklappt.', 0, 500), $id]);
            }
            return ['ok' => true];
        }
        if (in_array($a['art'] ?? '', ['uebersetzen', 'seite'], true)) {   // S6: die Seite selbst kommt über marketing_seite_melden
            if (!in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
            Db::update('mk_auftraege', $id, ['status' => $ok ? 'fertig' : 'fehler', 'ergebnis' => $text !== '' ? $text : null, 'zielgruppen' => $zg, 'inhalte' => $in, 'fertig_am' => date('Y-m-d H:i:s')]);
            return ['ok' => true];
        }
        if (($a['art'] ?? '') === 'demo') {   // Marketing-Studio 10: die Seite selbst kommt über marketing_demo_melden
            if (!in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
            Db::update('mk_auftraege', $id, ['status' => $ok ? 'fertig' : 'fehler', 'ergebnis' => $text !== '' ? $text : null, 'fertig_am' => date('Y-m-d H:i:s')]);
            if (!$ok) { require_once __DIR__ . '/MkDemo.php'; self::still(static fn() => MkDemo::gescheitert($a, $text), null); }
            return ['ok' => true];
        }
        if (($a['art'] ?? '') === 'medien') {
            if (!in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
            $p = json_decode((string) $a['parameter'], true) ?: [];
            Db::update('mk_auftraege', $id, ['status' => $ok ? 'fertig' : 'fehler', 'ergebnis' => $text !== '' ? $text : null, 'fertig_am' => date('Y-m-d H:i:s')]);
            if (!$ok) { self::still(static fn() => Events::melden('medien_fertig', 'Bild/Video nicht geklappt: ' . (string) ($p['titel'] ?? ''), 'info', $text,
                (int) ($p['inhalt_id'] ?? 0) > 0 ? 'inhalte/' . (int) $p['inhalt_id'] : 'freigabe#partner3d'), null); }
            if ($ok && !empty($p['vn'])) { require_once __DIR__ . '/MkVorherNachher.php'; self::still(static fn() => MkVorherNachher::verteilen($a), null); }
            /* Studio (03.10.2026): Kie-Guthaben zu knapp → derselbe Eintrag als Blender-Fassung. */
            if (!$ok && !empty($p['studio_katalog'])) { require_once __DIR__ . '/MkStudio.php'; self::still(static fn() => MkStudio::nachKieFehler($a, $text), null); }
            /* Marketing-Studio 7: War das das letzte Bild einer Kampagne, kommt jetzt der Stapel per Telegram. */
            $elternId = (int) Db::wert('SELECT auftrag_id FROM mk_inhalte WHERE id = ?', [(int) ($p['inhalt_id'] ?? 0)], 0);
            if ($elternId > 0) { require_once __DIR__ . '/TelegramMarketing.php'; TelegramMarketing::vielleichtMelden($elternId); }
            return ['ok' => true];
        }
        if (!in_array($a['status'], ['laeuft', 'fehler'], true)) { return ['ok' => false, 'hinweis' => 'Auftrag läuft nicht.']; }
        /* Kampagnen-Paket mit Bildern: je geliefertem Stück (außer Google-Suchanzeigen) ein Bild über Kie.ai —
           der PC prüft vor jedem Bild das Guthaben. */
        $bilder = 0;
        if ($ok && $istInhalt && !empty((json_decode((string) $a['parameter'], true) ?: [])['mit_bildern'])) {
            require_once __DIR__ . '/MkMedium.php';
            $takt = !empty((json_decode((string) $a['parameter'], true) ?: [])['tiktok_takt']);
            $nTt = 0;
            foreach (Db::all("SELECT id, plattform FROM mk_inhalte WHERE auftrag_id = ? AND status = 'entwurf' AND format <> 'google_anzeige' ORDER BY id", [$id]) as $r) {
                /* TikTok nimmt nur Videos: dort gleich ein Hochkant-Video (Motor wie eingestellt: Werbespot oder Kie.ai). */
                $mArt = $r['plattform'] === 'tiktok' ? 'video' : 'bild';
                if ($takt && $r['plattform'] === 'tiktok') {
                    /* TikTok täglich: Stimme (Kie.ai) und Werbespot (Blender) im Wechsel; ohne 3D-Szene für die Branche wird es Kie.ai. */
                    require_once __DIR__ . '/MkTiktokTakt.php';
                    $mod = MkTiktokTakt::motor($nTt++);
                    $erg = self::still(static fn() => MkMedium::anlegen((int) $r['id'], 'video', $mod, '9:16'), 'x');
                    if (!is_int($erg) && $mod !== 'veo3') { $erg = self::still(static fn() => MkMedium::anlegen((int) $r['id'], 'video', 'veo3', '9:16'), 'x'); }
                    if (is_int($erg)) { $bilder++; }
                    continue;
                }
                if (is_int(self::still(static fn() => MkMedium::anlegen((int) $r['id'], $mArt), 'x'))) { $bilder++; }
            }
            if ($bilder > 0) { $text = trim($text . "
" . $bilder . ' Bilder entstehen jetzt über Kie.ai.'); }
        }
        Db::update('mk_auftraege', $id, ['status' => $ok ? 'fertig' : 'fehler', 'ergebnis' => $text !== '' ? $text : null,
                                         'zielgruppen' => $zg, 'funde' => $fu, 'fertig_am' => date('Y-m-d H:i:s')] + ($istInhalt ? ['inhalte' => $in] : []));
        $wort = $istInhalt ? 'Inhalte' : 'Recherche';
        self::still(static fn() => Events::melden($istInhalt ? 'inhalte_fertig' : 'recherche_fertig',
            $ok ? $wort . ' fertig: ' . self::beschreibung($a) : $wort . ' nicht geklappt: ' . self::beschreibung($a),
            $ok ? 'gut' : 'info',   // kein „warnung“: das klingelte als Störung auf dem Handy
            $ok ? ($istInhalt ? $in . ' Entwürfe' . ($bilder > 0 ? ' (' . $bilder . ' Bilder entstehen)' : '') . ' — unter „Freigeben“ mit Ja oder Nein durchgehen.' : $zg . ' Zielgruppen-Entwürfe, ' . $fu . ' neue Funde — bitte prüfen und freigeben.') : $text,
            $istInhalt ? 'freigabe?land=' . $a['land'] : 'zielgruppen?land=' . $a['land']), null);
        /* Marketing-Studio 7: Kampagne ohne Bilder ist jetzt fertig — Stapel per Telegram (mit Bildern: nach dem letzten Bild). */
        if ($ok && $istInhalt) { require_once __DIR__ . '/TelegramMarketing.php'; TelegramMarketing::vielleichtMelden($id); }
        return ['ok' => true];
    }
}
