<?php
declare(strict_types=1);

/**
 * Was Claude selbst eintragen darf (AI Office Stufe 3, 07.10.2026).
 *
 * Uwe: „Notizen und Aufgaben“, „Wiedervorlagen“, „Meldungen als gelesen“,
 * „Vorschläge in AI Freigaben“ — und dafür eine eigene Erlaubnis
 * (Umfang verwaltung.eintragen), um die Claude einmal neu fragt.
 *
 * DIE GRENZE
 * Alles hier bleibt im Haus: Nichts erreicht einen Kunden, einen Partner
 * oder die Öffentlichkeit, nichts berührt Geld, nichts löscht. Was nach
 * draußen soll, kann Claude nur VORSCHLAGEN (Freigabe::vorschlagen) — raus
 * geht es erst mit Uwes Ja in AI Freigaben, mit derselben Rückfrage wie sein
 * eigener Knopf. Die Prüfung (kette.php) sucht hier nach den Versandklassen
 * und nach Löschbefehlen; findet sie etwas, reißt sie.
 *
 * Jeder Eintrag steht in der Prüfspur mit „Claude“ als Urheber, und wo die
 * Verwaltung einen Verlauf zeigt (Kunde, Projekt, Betrieb), steht er dort auch.
 */
final class ClaudeEintragen
{
    /** Mehr offene Vorschläge von Claude auf einmal liest niemand. */
    public const OFFENE_VORSCHLAEGE = 25;

    /** @return list<array<string,mixed>> */
    public static function liste(): array
    {
        $tut = static fn(string $titel): array => ['title' => $titel, 'readOnlyHint' => false, 'destructiveHint' => false,
                                                   'idempotentHint' => false, 'openWorldHint' => false];
        $zahl = static fn(string $was): array => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647, 'description' => $was];
        require_once __DIR__ . '/AkquiseAntwort.php';
        require_once __DIR__ . '/Freigabe.php';
        return [
            ['name' => 'notiz_anlegen', 'title' => 'Notiz anlegen',
             'description' => 'Eine interne Notiz an einen Kunden, ein Projekt oder einen Akquise-Betrieb heften. Steht im Verlauf mit „Claude“ als Verfasser. Nur intern — der Kunde sieht sie nie.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['ziel', 'id', 'text'], 'properties' => [
                 'ziel' => ['type' => 'string', 'enum' => ['kunde', 'projekt', 'betrieb']],
                 'id' => $zahl('ID des Kunden, Projekts oder Betriebs.'),
                 'text' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 2000]]],
             'annotations' => $tut('Notiz anlegen')],
            ['name' => 'aufgabe_anlegen', 'title' => 'Aufgabe anlegen',
             'description' => 'Eine Aufgabe an ein Projekt hängen, wahlweise mit Fälligkeit.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['projekt_id', 'titel'], 'properties' => [
                 'projekt_id' => $zahl('ID des Projekts.'),
                 'titel' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 200],
                 'faellig' => ['type' => 'string', 'pattern' => '^20[0-9]{2}-[0-9]{2}-[0-9]{2}$', 'description' => 'JJJJ-MM-TT']]],
             'annotations' => $tut('Aufgabe anlegen')],
            ['name' => 'aufgabe_erledigt', 'title' => 'Aufgabe abhaken',
             'description' => 'Eine Projektaufgabe als erledigt markieren (oder wieder öffnen).',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['aufgabe_id'], 'properties' => [
                 'aufgabe_id' => $zahl('ID der Aufgabe (aus projekt_akte).'),
                 'erledigt' => ['type' => 'boolean', 'description' => 'Standard: ja.']]],
             'annotations' => $tut('Aufgabe abhaken')],
            ['name' => 'wiedervorlage_setzen', 'title' => 'Wiedervorlage setzen',
             'description' => 'Einen Akquise-Betrieb auf ein Datum legen. Er steht dann an dem Tag unter „Heute“ und im Morgenbriefing.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['betrieb_id', 'datum', 'grund'], 'properties' => [
                 'betrieb_id' => $zahl('ID des Betriebs (firma_id aus akquise).'),
                 'datum' => ['type' => 'string', 'pattern' => '^20[0-9]{2}-[0-9]{2}-[0-9]{2}$', 'description' => 'JJJJ-MM-TT, ab heute'],
                 'grund' => ['type' => 'string', 'enum' => array_keys(AkquiseAntwort::WIEDERVORLAGE),
                             'description' => implode(' · ', array_map(static fn($k, $v) => "$k = $v", array_keys(AkquiseAntwort::WIEDERVORLAGE), AkquiseAntwort::WIEDERVORLAGE))],
                 'notiz' => ['type' => 'string', 'maxLength' => 300]]],
             'annotations' => $tut('Wiedervorlage setzen')],
            ['name' => 'meldungen_gelesen', 'title' => 'Meldungen als gelesen',
             'description' => 'Meldungen als gelesen markieren, deren Anlass erledigt ist (IDs aus meldungen). Gelöscht wird nichts.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['ids'], 'properties' => [
                 'ids' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => ['type' => 'integer', 'minimum' => 1]]]],
             'annotations' => $tut('Meldungen als gelesen')],
            ['name' => 'freigabe_vorschlagen', 'title' => 'Vorschlag in AI Freigaben',
             'description' => 'Etwas, das nach draußen gehen soll, Uwe zur Freigabe hinlegen: Nachricht an einen Kunden (kunde_nachricht: kunde_id, betreff, text), Nachricht zum Projekt (nachricht_senden: projekt_id, text), Nachricht an einen Partner (partner_nachricht: partner_id, text), ein fertiges Angebot senden (angebot_senden: angebot_id) oder eine Vorschau freischalten (vorschau_frei: projekt_id). Es geht erst raus, wenn Uwe genehmigt; er kann Betreff und Text vorher ändern. Texte in der Sprache des Kunden, Uwe siezt Kunden. Nichts erfinden — nur, was in der Verwaltung steht.',
             'inputSchema' => ['type' => 'object', 'additionalProperties' => false, 'required' => ['art', 'titel', 'grund'], 'properties' => [
                 'art' => ['type' => 'string', 'enum' => array_keys(Freigabe::ARTEN)],
                 'titel' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200, 'description' => 'Worum es geht, in einer Zeile.'],
                 'grund' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 1000, 'description' => 'Warum jetzt — mit der Quelle (z. B. „Angebot A-12 seit 9 Tagen ohne Antwort“).'],
                 'empfehlung' => ['type' => 'string', 'maxLength' => 1000],
                 'auswirkung' => ['type' => 'string', 'maxLength' => 1000],
                 'kunde_id' => $zahl('Bei kunde_nachricht.'),
                 'projekt_id' => $zahl('Bei nachricht_senden und vorschau_frei.'),
                 'partner_id' => $zahl('Bei partner_nachricht.'),
                 'angebot_id' => $zahl('Bei angebot_senden.'),
                 'betreff' => ['type' => 'string', 'maxLength' => 200],
                 'text' => ['type' => 'string', 'maxLength' => 8000]]],
             'annotations' => $tut('Vorschlag in AI Freigaben')],
        ];
    }

    /** @return list<string> */
    public static function namen(): array { return array_column(self::liste(), 'name'); }

    /** @return array{ok:bool, daten:mixed, text:string} */
    public static function rufen(string $name, array $a, int $verbindungId): array
    {
        try {
            $daten = match ($name) {
                'notiz_anlegen'        => self::notiz($a, $verbindungId),
                'aufgabe_anlegen'      => self::aufgabe($a, $verbindungId),
                'aufgabe_erledigt'     => self::abhaken($a, $verbindungId),
                'wiedervorlage_setzen' => self::wiedervorlage($a, $verbindungId),
                'meldungen_gelesen'    => self::gelesen($a, $verbindungId),
                'freigabe_vorschlagen' => self::vorschlag($a, $verbindungId),
                default                => throw new InvalidArgumentException('Unbekanntes Werkzeug: ' . $name),
            };
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'daten' => null, 'text' => $e->getMessage()];
        } catch (Throwable $e) {
            try { require_once __DIR__ . '/Events.php'; Events::protokoll('claude_werkzeug_fehler', $name . ': ' . mb_substr($e->getMessage(), 0, 300)); } catch (Throwable $e2) { }
            return ['ok' => false, 'daten' => null, 'text' => 'Das ließ sich gerade nicht eintragen. Später noch einmal versuchen.'];
        }
        return ['ok' => true, 'daten' => $daten, 'text' => (string) json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)];
    }

    /* ================================================================== */

    private static function notiz(array $a, int $vid): array
    {
        $ziel = (string) ($a['ziel'] ?? '');
        $id = self::id($a, 'id');
        $text = self::text($a, 'text', 2, 2000);
        if ($ziel === 'betrieb') {
            if (Db::one('SELECT id FROM akq_firmen WHERE id = ?', [$id]) === null) { throw new InvalidArgumentException('Kein Betrieb mit der ID ' . $id . '.'); }
            require_once __DIR__ . '/Akquise.php';
            require_once __DIR__ . '/AkquiseCrm.php';
            $r = AkquiseCrm::notizAnlegen($id, $text, 'admin', false, ['user_id' => null, 'autor' => 'Claude']);
            if (!$r['ok']) { throw new InvalidArgumentException($r['fehler']); }
            self::spur('claude_notiz', 'akq_firmen', $id, $vid, ['text' => mb_substr($text, 0, 300)]);
            return ['ok' => true, 'notiz_id' => $r['id'], 'link' => self::link('/akquise/' . $id)];
        }
        if ($ziel === 'kunde') {
            if (Db::one('SELECT id FROM customers WHERE id = ? AND demo = 0', [$id]) === null) { throw new InvalidArgumentException('Kein Kunde mit der ID ' . $id . '.'); }
            $nid = self::verlauf($text, $id, null);
            self::spur('claude_notiz', 'customers', $id, $vid, ['text' => mb_substr($text, 0, 300)]);
            return ['ok' => true, 'notiz_id' => $nid, 'link' => self::link('/kunden/' . $id)];
        }
        if ($ziel === 'projekt') {
            $kid = Db::wert('SELECT customer_id FROM projects WHERE id = ? AND demo = 0', [$id], null);
            if ($kid === null && Db::one('SELECT id FROM projects WHERE id = ? AND demo = 0', [$id]) === null) { throw new InvalidArgumentException('Kein Projekt mit der ID ' . $id . '.'); }
            $nid = self::verlauf($text, $kid !== null ? (int) $kid : null, $id);
            self::spur('claude_notiz', 'projects', $id, $vid, ['text' => mb_substr($text, 0, 300)]);
            return ['ok' => true, 'notiz_id' => $nid, 'link' => self::link('/projekte/' . $id)];
        }
        throw new InvalidArgumentException('ziel: kunde, projekt oder betrieb.');
    }

    /** Eine Notiz im Verlauf von Kunde und Projekt — dort, wo Uwe die Geschichte eines Kunden liest. */
    private static function verlauf(string $text, ?int $kunde, ?int $projekt): int
    {
        return (int) Db::insert('activities', [
            'type' => 'notiz_claude', 'title' => mb_substr('Notiz von Claude: ' . preg_replace('/\s+/u', ' ', $text), 0, 255),
            'customer_id' => $kunde, 'project_id' => $projekt, 'actor' => 'Claude',
            'meta' => json_encode(['text' => $text], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private static function aufgabe(array $a, int $vid): array
    {
        $pid = self::id($a, 'projekt_id');
        if (Db::one('SELECT id FROM projects WHERE id = ? AND demo = 0', [$pid]) === null) { throw new InvalidArgumentException('Kein Projekt mit der ID ' . $pid . '.'); }
        $titel = self::text($a, 'titel', 2, 200);
        $faellig = self::datum($a, 'faellig', false);
        $id = (int) Db::insert('tasks', ['project_id' => $pid, 'title' => $titel, 'due_date' => $faellig,
            'sort' => (int) Db::wert('SELECT COALESCE(MAX(sort),0)+1 FROM tasks WHERE project_id = ?', [$pid], 1)]);
        self::spur('claude_aufgabe', 'tasks', $id, $vid, ['projekt' => $pid, 'titel' => $titel, 'faellig' => $faellig]);
        return ['ok' => true, 'aufgabe_id' => $id, 'link' => self::link('/projekte/' . $pid)];
    }

    private static function abhaken(array $a, int $vid): array
    {
        $id = self::id($a, 'aufgabe_id');
        $t = Db::one('SELECT id, project_id, done FROM tasks WHERE id = ?', [$id]);
        if ($t === null) { throw new InvalidArgumentException('Keine Aufgabe mit der ID ' . $id . '.'); }
        $ja = !array_key_exists('erledigt', $a) || (bool) $a['erledigt'];
        Db::update('tasks', $id, ['done' => $ja ? 1 : 0]);
        self::spur('claude_aufgabe_erledigt', 'tasks', $id, $vid, ['vorher' => (int) $t['done'], 'nachher' => $ja ? 1 : 0]);
        return ['ok' => true, 'aufgabe_id' => $id, 'erledigt' => $ja, 'link' => self::link('/projekte/' . (int) $t['project_id'])];
    }

    private static function wiedervorlage(array $a, int $vid): array
    {
        $fid = self::id($a, 'betrieb_id');
        if (Db::one('SELECT id FROM akq_firmen WHERE id = ?', [$fid]) === null) { throw new InvalidArgumentException('Kein Betrieb mit der ID ' . $fid . '.'); }
        $datum = (string) self::datum($a, 'datum', true);
        require_once __DIR__ . '/Akquise.php';
        require_once __DIR__ . '/AkquiseAntwort.php';
        $r = AkquiseAntwort::wiedervorlage($fid, (string) ($a['grund'] ?? ''), mb_substr((string) ($a['notiz'] ?? ''), 0, 300) . ' (Claude)', $datum, 0);
        if (!$r['ok']) { throw new InvalidArgumentException($r['fehler']); }
        self::spur('claude_wiedervorlage', 'akq_firmen', $fid, $vid, ['am' => $r['am'], 'schritt' => $r['schritt']]);
        return ['ok' => true, 'am' => $r['am'], 'schritt' => $r['schritt'], 'link' => self::link('/akquise/' . $fid)];
    }

    private static function gelesen(array $a, int $vid): array
    {
        $ids = $a['ids'] ?? null;
        if (!is_array($ids) || $ids === [] || count($ids) > 50) { throw new InvalidArgumentException('ids: eine Liste mit 1 bis 50 Meldungen.'); }
        $ids = array_values(array_unique(array_map(static function ($i): int {
            if (!is_int($i) && !(is_string($i) && ctype_digit($i))) { throw new InvalidArgumentException('ids: nur ganze Zahlen.'); }
            return (int) $i;
        }, $ids)));
        $n = Db::run('UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids)->rowCount();
        self::spur('claude_meldungen_gelesen', 'notifications', null, $vid, ['ids' => $ids, 'gelesen' => $n]);
        return ['ok' => true, 'als_gelesen' => $n, 'schon_gelesen_oder_unbekannt' => count($ids) - $n];
    }

    private static function vorschlag(array $a, int $vid): array
    {
        require_once __DIR__ . '/Freigabe.php';
        $art = (string) ($a['art'] ?? '');
        if (!isset(Freigabe::ARTEN[$art])) { throw new InvalidArgumentException('art: ' . implode(', ', array_keys(Freigabe::ARTEN)) . '.'); }
        $offen = (int) Db::wert("SELECT COUNT(*) FROM ai_freigaben WHERE status IN ('offen','zurueckgestellt') AND system_name = 'Claude (Connector)'", [], 0);
        if ($offen >= self::OFFENE_VORSCHLAEGE) {
            throw new InvalidArgumentException('Schon ' . $offen . ' Vorschläge warten auf Uwe. Erst wenn er davon welche entschieden hat, kommen neue dazu.');
        }
        $daten = [];
        $braucht = static function (string $feld, string $tabelle, string $wort) use ($a): int {
            $id = self::id($a, $feld);
            if (Db::one("SELECT id FROM $tabelle WHERE id = ?", [$id]) === null) { throw new InvalidArgumentException('Kein ' . $wort . ' mit der ID ' . $id . '.'); }
            return $id;
        };
        switch ($art) {
            case 'kunde_nachricht':
                $daten = ['kunde' => $braucht('kunde_id', 'customers', 'Kunde'), 'betreff' => self::text($a, 'betreff', 3, 200), 'text' => self::text($a, 'text', 10, 8000)];
                break;
            case 'nachricht_senden':
                $daten = ['projekt' => $braucht('projekt_id', 'projects', 'Projekt'), 'text' => self::text($a, 'text', 10, 8000)];
                if (trim((string) ($a['betreff'] ?? '')) !== '') { $daten['betreff'] = self::text($a, 'betreff', 3, 200); }
                break;
            case 'partner_nachricht':
                $daten = ['partner' => $braucht('partner_id', 'partner', 'Partner'), 'text' => self::text($a, 'text', 10, 8000)];
                break;
            case 'angebot_senden':
                $daten = ['angebot' => $braucht('angebot_id', 'angebote', 'Angebot')];
                break;
            case 'vorschau_frei':
                $daten = ['projekt' => $braucht('projekt_id', 'projects', 'Projekt')];
                break;
        }
        $id = Freigabe::vorschlagen($art, $daten, [
            'titel' => self::text($a, 'titel', 3, 200), 'grund' => self::text($a, 'grund', 3, 1000),
            'empfehlung' => mb_substr(trim((string) ($a['empfehlung'] ?? '')), 0, 1000),
            'auswirkung' => mb_substr(trim((string) ($a['auswirkung'] ?? '')), 0, 1000),
            'von' => 'Claude', 'system' => 'Claude (Connector)',
        ]);
        self::spur('claude_vorschlag', 'ai_freigaben', $id, $vid, ['art' => $art]);
        return ['ok' => true, 'freigabe_id' => $id, 'wartet_auf' => 'Uwes Ja in AI Freigaben (und per Telegram)', 'link' => self::link('/ai-freigaben/' . $id)];
    }

    /* ================================================================== */

    private static function id(array $a, string $k): int
    {
        $v = $a[$k] ?? null;
        if (!(is_int($v) || (is_string($v) && ctype_digit($v)) || (is_float($v) && floor($v) === $v)) || (int) $v < 1) {
            throw new InvalidArgumentException($k . ': eine ID (ganze Zahl ab 1).');
        }
        return (int) $v;
    }

    private static function text(array $a, string $k, int $min, int $max): string
    {
        $t = trim(str_replace("\r\n", "\n", (string) ($a[$k] ?? '')));
        if (mb_strlen($t) < $min) { throw new InvalidArgumentException($k . ': mindestens ' . $min . ' Zeichen.'); }
        if (mb_strlen($t) > $max) { throw new InvalidArgumentException($k . ': höchstens ' . $max . ' Zeichen.'); }
        return $t;
    }

    private static function datum(array $a, string $k, bool $pflicht): ?string
    {
        $d = trim((string) ($a[$k] ?? ''));
        if ($d === '') {
            if ($pflicht) { throw new InvalidArgumentException($k . ' fehlt (JJJJ-MM-TT).'); }
            return null;
        }
        $t = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        if ($t === false || $t->format('Y-m-d') !== $d) { throw new InvalidArgumentException($k . ': JJJJ-MM-TT.'); }
        if ($d < date('Y-m-d') || $d > date('Y-m-d', strtotime('+2 years'))) { throw new InvalidArgumentException($k . ': ab heute, höchstens zwei Jahre voraus.'); }
        return $d;
    }

    /** Prüfspur mit „Claude“ als Urheber (Events::pruefspur kennt nur angemeldete Menschen). */
    private static function spur(string $aktion, string $was, ?int $id, int $vid, array $nachher): void
    {
        try {
            Db::insert('audit_log', ['user_id' => null, 'actor' => 'Claude', 'action' => $aktion, 'entity' => $was, 'entity_id' => $id,
                'before_json' => null, 'after_json' => json_encode($nachher + ['verbindung' => $vid], JSON_UNESCAPED_UNICODE), 'ip' => null]);
        } catch (Throwable $e) { }
    }

    private static function link(string $pfad): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . Config::basis() . $pfad;
    }
}
