<?php
declare(strict_types=1);

require_once __DIR__ . '/Seitenblick.php';
require_once __DIR__ . '/Seiteninhalt.php';
require_once __DIR__ . '/Vies.php';
require_once __DIR__ . '/Domainpruefung.php';

/**
 * Was wir schon wissen koennten, bevor der Kunde tippt (A1 + A3, 25.09.2026).
 *
 * Zwei Quellen, beide oeffentlich und beide vom Kunden selbst: seine alte
 * Website und seine P. IVA im EU-Register. Was dort steht, landet im
 * Fragebogen -- aber nur in LEEREN Feldern und nur, solange er noch nicht
 * abgeschickt ist. Was der Kunde selbst geschrieben hat, wird nie
 * ueberschrieben; was wir eingetragen haben, steht mit dem Hinweis "von
 * Ihrer Website uebernommen" da, damit er prueft statt ungelesen bestaetigt.
 *
 * WANN
 *
 * Nie waehrend jemand wartet: Eine fremde Seite kann zehn Sekunden brauchen.
 * Der Fragebogen stoesst es nach dem Ausliefern an (die Antwort ist dann
 * schon beim Kunden), und der Cron holt nach, was dabei nicht lief --
 * je Lauf nur wenige, weil jeder Abruf Zeit auf einem fremden Server ist.
 */
final class Vorwissen
{
    public const JE_LAUF = 3;

    /** Nur Felder, fuer die die Quelle wirklich taugt. Reihenfolge = Vorrang. */
    public const FELDER = ['firmenname', 'ort', 'beschreibung', 'altseite', 'social',
                           'telefon', 'email_web', 'domain_name', 'impressum'];

    /** Cron: offene Frageboegen, bei denen noch nie nachgesehen wurde. */
    public static function nachholen(int $hoechstens = self::JE_LAUF): array
    {
        $ids = array_column(Db::all(
            "SELECT id FROM questionnaires
              WHERE seite_gelesen_am IS NULL AND status <> 'abgeschlossen'
                AND created_at > NOW() - INTERVAL 30 DAY
              ORDER BY id DESC LIMIT " . max(1, $hoechstens)), 'id');
        $gefuellt = 0;
        foreach ($ids as $id) {
            try { $gefuellt += count(self::fuerFragebogen((int) $id)); }
            catch (Throwable $e) {
                Db::run('UPDATE questionnaires SET seite_gelesen_am = NOW(), updated_at = updated_at WHERE id = ?', [(int) $id]);
            }
        }
        return ['frageboegen' => count($ids), 'felder' => $gefuellt];
    }

    /**
     * Einen Fragebogen vorbelegen. Die beiden Abrufe sind austauschbar,
     * damit die Pruefkette ohne Netz laeuft.
     *
     * @param callable(string):?array|null $seite  Adresse -> Seitenblick::abrufen()-Form
     * @param callable(string):?array|null $register P. IVA -> Vies::fragen()-Form
     * @return array<string,string> eingetragene Felder
     */
    public static function fuerFragebogen(int $id, ?callable $seite = null, ?callable $register = null): array
    {
        $seite    ??= static fn(string $a): ?array => Seitenblick::abrufen($a);
        $register ??= static fn(string $n): ?array => Vies::fragen($n);

        /* Sofort als gelesen markieren -- ein zweiter Aufruf (Cron und
           Fragebogen gleichzeitig) soll nicht zweimal fremde Server fragen.
           Wer die Zeile nicht selbst umstellt, hat verloren und geht. */
        /* updated_at bleibt stehen: Die Erinnerungen (C3) lesen daran ab, ob
           der KUNDE sich bewegt hat -- unser Eintragen ist keine Bewegung. */
        $geholt = Db::run("UPDATE questionnaires SET seite_gelesen_am = NOW(), updated_at = updated_at
                            WHERE id = ? AND seite_gelesen_am IS NULL AND status <> 'abgeschlossen'", [$id])->rowCount();
        if ($geholt === 0) { return []; }

        $q = Db::one('SELECT q.data, q.customer_id, c.email, c.vat_id
                        FROM questionnaires q JOIN customers c ON c.id = q.customer_id WHERE q.id = ?', [$id]);
        if (!$q) { return []; }
        $daten = json_decode((string) ($q['data'] ?? ''), true) ?: [];

        $funde = [];
        $adresse = self::adresseFuer((int) $q['customer_id'], $daten, (string) ($q['email'] ?? ''));
        if ($adresse !== null) {
            if (Seitenblick::istProfil($adresse)) {
                $funde['social'] = str_starts_with($adresse, 'http') ? $adresse : 'https://' . $adresse;
            } else {
                $r = $seite($adresse);
                if ($r) {
                    $funde = Seiteninhalt::lesen($r['html'], $r['url'], $r['unterseiten'] ?? []);
                    $funde['_host'] = preg_replace('~^www\.~', '', (string) parse_url($r['url'], PHP_URL_HOST)) ?? '';
                }
            }
        }

        /* A3: Die P. IVA aus der Akte, von der Seite oder aus dem, was der
           Kunde schon ins Impressum geschrieben hat -- in dieser Reihenfolge. */
        $piva = trim((string) ($q['vat_id'] ?? '')) ?: ($funde['piva'] ?? '');
        if ($piva === '' && preg_match('~\b(?:IT)?(\d{11})\b~', (string) ($daten['impressum'] ?? ''), $m)) { $piva = $m[1]; }
        $amtlich = $piva !== '' ? $register($piva) : null;

        $werte = self::zuFeldern($funde, $amtlich);
        $eingetragen = self::eintragen($id, $werte);
        if ($adresse !== null) {
            Db::run('UPDATE questionnaires SET seite_adresse = ?, updated_at = updated_at WHERE id = ?', [mb_substr($adresse, 0, 190), $id]);
        }
        return $eingetragen;
    }

    /**
     * Wo die alte Seite liegen koennte -- nur Adressen, die der Kunde selbst
     * genannt hat: im Fragebogen, in der Anfrage, in seiner E-Mail-Adresse.
     */
    public static function adresseFuer(int $kundeId, array $daten, string $email): ?string
    {
        if (($daten['altseite'] ?? '') === 'nein') { return null; }
        $kandidaten = [(string) ($daten['domain_name'] ?? '')];
        try {
            $kandidaten[] = (string) Db::wert(
                "SELECT website FROM anfragen WHERE customer_id = ? AND website IS NOT NULL AND website <> ''
                  ORDER BY id DESC LIMIT 1", [$kundeId], '');
        } catch (Throwable $e) { /* ohne Anfragen-Tabelle eben nicht */ }
        require_once __DIR__ . '/Bedarf.php';
        $kandidaten[] = (string) (Bedarf::domainAusMail($email) ?? '');
        foreach ($kandidaten as $k) {
            $k = trim($k);
            if ($k !== '' && (Domainpruefung::normalisieren($k) !== null)) { return $k; }
        }
        return null;
    }

    /**
     * Funde in Fragebogenfelder uebersetzen. Das Register geht beim
     * Impressum vor: Dort zaehlt die amtliche Firmierung, nicht, wie sich
     * der Betrieb auf seiner Startseite nennt.
     *
     * @param array<string,string> $f
     * @param array<string,mixed>|null $amtlich
     * @return array<string,string>
     */
    public static function zuFeldern(array $f, ?array $amtlich): array
    {
        $w = [];
        $name = $f['name'] ?? null;
        if ($name === null && $amtlich && !empty($amtlich['name'])) {
            $name = mb_convert_case(mb_strtolower((string) $amtlich['name']), MB_CASE_TITLE);
        }
        if ($name !== null)              { $w['firmenname'] = $name; }
        $ort = $f['ort'] ?? ($amtlich ? Vies::ortAus($amtlich['anschrift'] ?? null) : null);
        if ($ort !== null)               { $w['ort'] = $ort; }
        if (!empty($f['beschreibung']) && mb_strlen($f['beschreibung']) >= 40) { $w['beschreibung'] = $f['beschreibung']; }
        if (!empty($f['_host']))         { $w['altseite'] = 'ja'; $w['domain_name'] = $f['_host']; }
        foreach (['social' => 'social', 'telefon' => 'telefon', 'email' => 'email_web'] as $von => $nach) {
            if (!empty($f[$von])) { $w[$nach] = $f[$von]; }
        }

        $zeilen = [];
        if ($amtlich && !empty($amtlich['gueltig']) && !empty($amtlich['name'])) {
            $zeilen[] = (string) $amtlich['name'];
            if (!empty($amtlich['anschrift'])) { $zeilen[] = str_replace("\n", ', ', (string) $amtlich['anschrift']); }
            $zeilen[] = 'P. IVA ' . $amtlich['land'] . $amtlich['nummer'];
        } else {
            $anschrift = $f['anschrift'] ?? trim(implode(', ', array_filter([
                $f['strasse'] ?? null, trim(($f['plz'] ?? '') . ' ' . ($f['ort'] ?? '')) ?: null])));
            if (!empty($f['strasse']) || !empty($f['anschrift']) || !empty($f['piva'])) {
                $zeilen[] = $f['firmierung'] ?? ($name ?? '');
                if ($anschrift !== '') { $zeilen[] = $anschrift; }
                if (!empty($f['piva'])) { $zeilen[] = 'P. IVA ' . $f['piva']; }
            }
        }
        $zeilen = array_values(array_filter(array_map('trim', $zeilen)));
        if ($zeilen) { $w['impressum'] = implode("\n", $zeilen); }

        return array_intersect_key($w, array_flip(self::FELDER));
    }

    /**
     * In LEERE Felder eintragen -- unter Sperre, damit ein gleichzeitiges
     * Speichern des Kunden nicht zwischen Lesen und Schreiben faellt.
     *
     * @param array<string,string> $werte
     * @return array<string,string> was wirklich eingetragen wurde
     */
    public static function eintragen(int $id, array $werte): array
    {
        if (!$werte) { return []; }
        return (array) Db::transaktion(static function () use ($id, $werte): array {
            $q = Db::one('SELECT data, seite_felder, status FROM questionnaires WHERE id = ? FOR UPDATE', [$id]);
            if (!$q || $q['status'] === 'abgeschlossen') { return []; }
            $daten = json_decode((string) ($q['data'] ?? ''), true) ?: [];
            $schon = json_decode((string) ($q['seite_felder'] ?? ''), true) ?: [];
            $neu = [];
            foreach ($werte as $feld => $wert) {
                if (trim((string) ($daten[$feld] ?? '')) !== '') { continue; }
                $daten[$feld] = $wert;
                $neu[$feld] = $wert;
            }
            if ($neu) {
                Db::run('UPDATE questionnaires SET data = ?, seite_felder = ?, updated_at = updated_at WHERE id = ?', [
                    json_encode($daten, JSON_UNESCAPED_UNICODE),
                    json_encode($neu + $schon, JSON_UNESCAPED_UNICODE), $id]);
            }
            return $neu;
        }, 3);
    }
}
