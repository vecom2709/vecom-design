<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';

/**
 * Der Assistent der Akquise -- ohne KI-Kosten (27.09.2026, Uwe: „Assistent
 * ohne KI-Kosten“).
 *
 * Feste Fragen über den vorhandenen Daten, dazu ein Eingabefeld, das Sätze
 * wie „Zeig mir die besten Leads aus Sizilien“ auf eine dieser Fragen und
 * ihre Filter abbildet (Schlüsselwörter, Branchen- und Ortsnamen). Keine
 * Antwort erfindet etwas: Jede Zeile ist ein Datensatz mit Link.
 *
 * DIE EINWILLIGUNG ZÄHLT IMMER
 * „Wer darf per Mail?“ ist die einzige Frage, die E-Mail-Adressen zeigt, und
 * sie zeigt nur Betriebe, bei denen das Gate „Ja, erlaubt“ sagt. Alle anderen
 * Antworten zeigen die Ampel des Gates in jeder Zeile -- damit aus „gute
 * Chance“ nie stillschweigend „darf angeschrieben werden“ wird.
 */
final class AkquiseAssistent
{
    public const FRAGEN = [
        'beste'      => ['Die besten Betriebe', 'Höchste Chance, noch nicht angesprochen.'],
        'probleme'   => ['Websites mit starken Problemen', 'Chance 71 oder mehr — viele belegte Befunde.'],
        'mail'       => ['Wer darf per E-Mail angeschrieben werden?', 'Nur Betriebe mit bestätigter Einwilligung (Gate: Ja, erlaubt).'],
        'warten'     => ['Wer wartet auf eine Antwort von mir?', 'Betriebe, die geantwortet haben.'],
        'still'      => ['Wen habe ich angeschrieben, ohne Antwort?', 'Kontaktiert, noch keine Antwort — die ältesten zuerst.'],
        'dreid'      => ['Wer passt wahrscheinlich zu 3D und Animation?', 'Branche mit hoher Eignung oder ein belegter Befund „Experience-Potenzial“.'],
        'ohnetext'   => ['Starke Betriebe ohne Textvorschlag', 'Chance 71 oder mehr, noch keine Vorlage — die nimmt sich der PC als Nächstes vor.'],
        'checks'     => ['Neue Anfragen über den Website-Check', 'Betriebe, die ihre Seite selbst geprüft haben.'],
        'folgen'     => ['Folge-Mails, die warten', 'Laufende Folgen mit einem Grund, warum gerade nichts rausgeht.'],
    ];

    /** Deutsche und englische Namen italienischer Regionen → wie sie in den Daten stehen. */
    private const REGIONEN = [
        'sizilien' => 'Sicilia', 'sicily' => 'Sicilia', 'sardinien' => 'Sardegna', 'sardinia' => 'Sardegna', 'kalabrien' => 'Calabria',
        'apulien' => 'Puglia', 'kampanien' => 'Campania', 'toskana' => 'Toscana', 'tuscany' => 'Toscana', 'lombardei' => 'Lombardia',
        'venetien' => 'Veneto', 'latium' => 'Lazio', 'piemont' => 'Piemonte', 'ligurien' => 'Liguria', 'umbrien' => 'Umbria',
        'südtirol' => 'Trentino-Alto Adige/Südtirol', 'bavaria' => 'Bayern',
    ];

    /** Reihenfolge zählt: das Genauere zuerst („nicht geantwortet“ vor „geantwortet“). */
    private const WOERTER = [
        'mail' => ['mail', 'e-mail', 'email', 'dürfen', 'darf', 'erlaubt', 'rechtlich', 'einwilligung', 'marketing'],
        'still' => ['keine antwort', 'ohne antwort', 'nachfassen', 'nicht geantwortet', 'noch nicht geantwortet'],
        'warten' => ['warten auf eine antwort', 'wartet auf eine antwort', 'antwort von mir', 'geantwortet', 'antworten', 'antwort'],
        'dreid' => ['3d', 'animation', 'webgl', 'erlebnis', 'rundgang'],
        'probleme' => ['problem', 'schlecht', 'fehler', 'veraltet', 'schwäche'],
        'ohnetext' => ['audit', 'vorbereiten', 'textvorschlag', 'vorlage'],
        'checks' => ['website-check', 'check', 'anfrage'],
        'folgen' => ['folge', 'follow'],
        'beste' => ['beste', 'besten', 'top', 'potenzial', 'höchste', 'interessant'],
    ];

    /**
     * Einen Satz verstehen. Was nicht erkannt wird, landet bei „beste“ -- mit
     * dem Hinweis, was erkannt wurde, damit niemand einer Vermutung glaubt.
     * @return array{frage:string, filter:array{land?:string,region?:string,stadt?:string,branche?:string,anzahl?:int}, erkannt:list<string>}
     */
    public static function verstehen(string $satz): array
    {
        $s = ' ' . mb_strtolower(trim($satz)) . ' ';
        $frage = 'beste'; $erkannt = [];
        foreach (self::WOERTER as $f => $woerter) {
            foreach ($woerter as $w) { if (str_contains($s, $w)) { $frage = $f; $erkannt[] = '„' . $w . '“ → ' . self::FRAGEN[$f][0]; break 2; } }
        }
        $filter = [];
        if (preg_match('~\b(\d{1,3})\b~', $s, $m) && (int) $m[1] > 0 && (int) $m[1] <= 200 && !str_contains($s, '3d')) { $filter['anzahl'] = (int) $m[1]; }
        if (preg_match('~\b(italien|italia|italy)\b~u', $s)) { $filter['land'] = 'IT'; $erkannt[] = 'Land: Italien'; }
        if (preg_match('~\b(deutschland|germania|germany)\b~u', $s)) { $filter['land'] = 'DE'; $erkannt[] = 'Land: Deutschland'; }
        foreach (Akquise::branchen() as $k => $b) {
            foreach (array_filter([(string) ($b['de'] ?? ''), (string) ($b['it'] ?? ''), (string) ($b['en'] ?? '')]) as $name) {
                $n = mb_strtolower($name);
                if (mb_strlen($n) >= 4 && (str_contains($s, $n) || str_contains($s, rtrim($n, 'e') . 's') || str_contains($s, $n . 's'))) {
                    $filter['branche'] = (string) $k; $erkannt[] = 'Branche: ' . ($b['de'] ?? $k); break 2;
                }
            }
        }
        foreach (self::REGIONEN as $wort => $region) {
            if (str_contains($s, $wort)) { $filter['region'] = $region; $erkannt[] = 'Region: ' . $region; break; }
        }
        if (!isset($filter['region'])) {
            $w = Akquise::filterWerte();
            foreach (['region' => $w['region'], 'stadt' => array_merge($w['kreis'], $w['stadt'])] as $feld => $werte) {
                foreach ($werte as $wert) {
                    $x = mb_strtolower((string) $wert);
                    if (mb_strlen($x) >= 4 && preg_match('~(?<![\p{L}])' . preg_quote($x, '~') . '(?![\p{L}])~u', $s)) {
                        $filter[$feld === 'region' ? 'region' : 'stadt'] = (string) $wert; $erkannt[] = ($feld === 'region' ? 'Region: ' : 'Ort: ') . $wert; break 2;
                    }
                }
            }
        }
        return ['frage' => $frage, 'filter' => $filter, 'erkannt' => $erkannt];
    }

    /**
     * @param array{land?:string,region?:string,stadt?:string,branche?:string,anzahl?:int} $filter
     * @return array{titel:string, satz:string, zeilen:list<array>, spalte:string}
     */
    public static function antwort(string $frage, array $filter = []): array
    {
        if (!isset(self::FRAGEN[$frage])) { $frage = 'beste'; }
        $n = max(1, min(200, (int) ($filter['anzahl'] ?? 20)));
        $wo = ['1=1']; $p = [];
        if (!empty($filter['land']) && in_array($filter['land'], ['DE', 'IT'], true)) { $wo[] = 'f.land = ?'; $p[] = $filter['land']; }
        if (!empty($filter['region'])) { $wo[] = 'f.region = ?'; $p[] = $filter['region']; }
        if (!empty($filter['stadt'])) { $wo[] = '(f.stadt = ? OR f.kreis = ?)'; $p[] = $filter['stadt']; $p[] = $filter['stadt']; }
        if (!empty($filter['branche'])) { $wo[] = 'f.branche = ?'; $p[] = $filter['branche']; }
        $w = implode(' AND ', $wo);
        $spalte = 'Chance';
        $sql = match ($frage) {
            'beste' => "SELECT f.* FROM akq_firmen f WHERE $w AND f.gesperrt = 0 AND f.score IS NOT NULL AND f.kontakt_status IN ('neu','qualifiziert','vorlage','freigegeben') ORDER BY f.score DESC, f.id LIMIT $n",
            'probleme' => "SELECT f.*, (SELECT COUNT(*) FROM akq_befunde b WHERE b.firma_id = f.id AND b.status = 'VERIFIED' AND b.schwere >= 3) AS zusatz FROM akq_firmen f WHERE $w AND f.gesperrt = 0 AND f.score >= 71 ORDER BY zusatz DESC, f.score DESC LIMIT $n",
            'mail' => "SELECT f.* FROM akq_firmen f WHERE $w AND f.gesperrt = 0 AND f.compliance_status = 'CONTACT_ALLOWED' AND f.email IS NOT NULL AND COALESCE(f.einwilligung, '') <> '' ORDER BY f.score DESC, f.id LIMIT $n",
            'warten' => "SELECT f.*, (SELECT MAX(r.eingang_am) FROM akq_antworten r WHERE r.firma_id = f.id) AS zusatz FROM akq_firmen f WHERE $w AND f.kontakt_status = 'geantwortet' AND f.gesperrt = 0 ORDER BY zusatz DESC LIMIT $n",
            'still' => "SELECT f.*, (SELECT MAX(v.created_at) FROM akq_versand v WHERE v.firma_id = f.id AND v.status IN ('gesendet','von_hand')) AS zusatz FROM akq_firmen f WHERE $w AND f.kontakt_status = 'kontaktiert' AND f.gesperrt = 0 ORDER BY zusatz ASC LIMIT $n",
            'dreid' => "SELECT f.*, (SELECT COUNT(*) FROM akq_befunde b WHERE b.firma_id = f.id AND b.kategorie = 'experience' AND b.status = 'VERIFIED') AS zusatz FROM akq_firmen f WHERE $w AND f.gesperrt = 0 AND (f.branche IN ('" . implode("','", self::dreidBranchen()) . "') OR EXISTS (SELECT 1 FROM akq_befunde b WHERE b.firma_id = f.id AND b.kategorie = 'experience' AND b.status = 'VERIFIED')) ORDER BY zusatz DESC, f.score DESC LIMIT $n",
            'ohnetext' => "SELECT f.* FROM akq_firmen f WHERE $w AND f.gesperrt = 0 AND f.score >= 71 AND f.kontakt_status IN ('neu','qualifiziert') AND NOT EXISTS (SELECT 1 FROM akq_vorlagen v WHERE v.firma_id = f.id AND v.status IN ('entwurf','freigegeben','gesendet')) ORDER BY f.score DESC LIMIT $n",
            'checks' => "SELECT f.*, c.created_at AS zusatz FROM akq_checks c JOIN akq_firmen f ON f.id = c.firma_id WHERE $w AND c.status = 'neu' ORDER BY c.id DESC LIMIT $n",
            'folgen' => "SELECT f.*, fo.grund AS zusatz FROM akq_folgen fo JOIN akq_firmen f ON f.id = fo.firma_id WHERE $w AND fo.status = 'laeuft' AND fo.grund LIKE 'Wartet%' ORDER BY fo.naechst_am LIMIT $n",
        };
        $spalte = match ($frage) { 'probleme' => 'Belegte Befunde', 'warten' => 'Antwort vom', 'still' => 'Angeschrieben am', 'dreid' => 'Experience-Befunde', 'checks' => 'Anfrage vom', 'folgen' => 'Warum', default => 'Chance' };
        $zeilen = Db::all($sql, $p);
        $zahl = count($zeilen);
        $ort = trim(implode(' · ', array_filter([$filter['branche'] ?? null ? (Akquise::branchen()[$filter['branche']]['de'] ?? $filter['branche']) : null, $filter['region'] ?? null, $filter['stadt'] ?? null,
            ['IT' => 'Italien', 'DE' => 'Deutschland'][$filter['land'] ?? ''] ?? null])));
        return ['titel' => self::FRAGEN[$frage][0] . ($ort !== '' ? ' — ' . $ort : ''), 'satz' => $zahl === 0 ? 'Keine Treffer.' : $zahl . ' Treffer. ' . self::FRAGEN[$frage][1],
                'zeilen' => $zeilen, 'spalte' => $spalte, 'frage' => $frage];
    }

    /** Branchen mit hoher Grundeignung für 3D/Animation (Wert aus akquise_branchen.json). */
    public static function dreidBranchen(): array
    {
        return array_map('strval', array_keys(array_filter(Akquise::branchen(), static fn($b) => (int) ($b['experience'] ?? 0) >= 7)));
    }
}
