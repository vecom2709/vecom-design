<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/**
 * Bausteine, die schon irgendwo laufen.
 *
 * WARUM NICHT "BAUSTEINE"
 *
 * Der Name war vergeben: bausteine ist der Preisbaukasten, aus dem ein
 * Angebot entsteht. Hier geht es um etwas anderes — um fertige Abschnitte
 * einer Website, die sich beim naechsten Kunden wiederverwenden lassen.
 *
 * WARUM "LAEUFT BEI" DAS WICHTIGSTE FELD IST
 *
 * Eine Sammlung von Schnipseln, die jemand einmal fuer gut hielt, ist eine
 * Sammlung von Absichten. Was zaehlt, ist der Beweis: Dieser Abschnitt steht
 * bei Boulevard und bei Charme Color und tut dort seit Monaten seinen
 * Dienst. Deshalb steht das Feld nicht am Rand, sondern gleich hinter dem
 * Namen — und ein Baustein ohne diese Angabe ist noch keiner.
 *
 * WARUM ES VON SELBST WAECHST
 *
 * Nichts hier wird im voraus angelegt. Was zum dritten Mal gebaut wird,
 * wandert hinein — und taucht ab dann im Briefing des naechsten passenden
 * Kunden von allein auf.
 */
final class Muster
{
    private static function still(callable $fn, mixed $ersatz = null): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /** @return list<array<string,mixed>> */
    public static function alle(bool $nurAktive = false): array
    {
        $wo = $nurAktive ? 'WHERE aktiv = 1' : '';
        return (array) self::still(static fn() => Db::all(
            "SELECT * FROM muster $wo ORDER BY aktiv DESC, sortierung, name"), []);
    }

    public static function eines(int $id): ?array
    {
        return self::still(static fn() => Db::one('SELECT * FROM muster WHERE id = ?', [$id]));
    }

    /**
     * Welche Bausteine zu diesem Kunden passen koennten.
     *
     * Verglichen wird gegen zwei Quellen: die angekreuzten Funktionen im
     * Fragebogen und die Branche. Beides sind Woerter, keine Schluessel —
     * deshalb wird stumpf nach Vorkommen gesucht. Ein Vorschlag zu viel
     * kostet eine Zeile im Briefing; ein Vorschlag zu wenig kostet einen
     * halben Tag Nachbauen.
     *
     * @return list<array<string,mixed>>
     */
    public static function passend(array $antworten, string $branche = ''): array
    {
        $alle = self::alle(true);
        if (!$alle) { return []; }

        $heu = mb_strtolower(trim(implode(' ', array_filter([
            (string) ($antworten['funktionen_wahl'] ?? ''),
            (string) ($antworten['funktionen'] ?? ''),
            (string) ($antworten['seiten'] ?? ''),
            (string) ($antworten['branche'] ?? ''),
            $branche,
        ]))));

        $aus = [];
        foreach ($alle as $m) {
            $marken = array_filter(array_map(
                static fn(string $s): string => mb_strtolower(trim($s)),
                explode(',', (string) ($m['passt_zu'] ?? ''))));
            /* Ohne Marken passt der Baustein ueberall. Das ist Absicht: Ein
               Rechtsfuss oder eine Sprachumschaltung gehoert auf jede Seite,
               und niemand soll dafuer erst Stichwoerter pflegen muessen. */
            if (!$marken) { $aus[] = $m; continue; }
            foreach ($marken as $marke) {
                if ($marke !== '' && $heu !== '' && str_contains($heu, $marke)) { $aus[] = $m; break; }
            }
        }
        return $aus;
    }

    /** Anlegen oder aendern. Gibt die Nummer zurueck. */
    public static function speichern(?int $id, array $eingabe): int
    {
        $name = trim((string) ($eingabe['name'] ?? ''));
        if ($name === '') { throw new RuntimeException('Der Baustein braucht einen Namen.'); }

        $daten = [
            'name'       => mb_substr($name, 0, 160),
            'zweck'      => mb_substr(trim((string) ($eingabe['zweck'] ?? '')), 0, 400) ?: null,
            'laeuft_bei' => mb_substr(trim((string) ($eingabe['laeuft_bei'] ?? '')), 0, 400) ?: null,
            'passt_zu'   => mb_substr(trim((string) ($eingabe['passt_zu'] ?? '')), 0, 200) ?: null,
            'inhalt'     => trim((string) ($eingabe['inhalt'] ?? '')) ?: null,
            'aktiv'      => !empty($eingabe['aktiv']) ? 1 : 0,
            'sortierung' => (int) ($eingabe['sortierung'] ?? 0),
        ];

        if ($id !== null && $id > 0) {
            Db::update('muster', $id, $daten);
            return $id;
        }
        $daten['slug'] = self::freierSlug($name);
        return Db::insert('muster', $daten);
    }

    public static function loeschen(int $id): void
    {
        Db::run('DELETE FROM muster WHERE id = ?', [$id]);
    }

    /**
     * Ein Kuerzel, das es noch nicht gibt.
     *
     * Der Name darf sich spaeter aendern, das Kuerzel nicht — es ist der
     * Name, unter dem der Baustein in einem Briefing von vor einem Jahr
     * steht.
     */
    private static function freierSlug(string $name): string
    {
        $basis = trim(preg_replace('~[^a-z0-9]+~', '-', mb_strtolower(strtr($name, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]))) ?? '', '-');
        $basis = $basis !== '' ? mb_substr($basis, 0, 50) : 'baustein';

        $slug = $basis;
        for ($i = 2; $i < 60; $i++) {
            $da = (int) self::still(static fn() => Db::wert(
                'SELECT COUNT(*) FROM muster WHERE slug = ?', [$slug], 0), 0);
            if ($da === 0) { return $slug; }
            $slug = $basis . '-' . $i;
        }
        return $basis . '-' . substr((string) time(), -4);
    }
}
