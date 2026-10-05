<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   Pruefspur.php — die Ansicht der Prüfspur (Phase 9, 06.10.2026).

   audit_log wird an über 120 Stellen geschrieben (wer, Tat, Objekt, vorher,
   nachher, IP) und war bis hierher unsichtbar. Diese Klasse LIEST nur.

   SCHWÄRZEN
   Was in vorher/nachher landet, hat niemand für eine Anzeige geschrieben.
   Steht dort ein Schlüssel, ein Passwort oder ein Token, darf es auch hier
   nicht erscheinen — weder im Browser noch in einem Bildschirmfoto. Geschwärzt
   wird nach dem Namen des Felds UND nach dem Aussehen des Werts.
   ========================================================================== */
final class Pruefspur
{
    public const JE_SEITE = 100;
    /** Feldnamen, deren Wert nie gezeigt wird. */
    private const GEHEIM = '~pass|kennwort|token|secret|geheim|schluessel|schlüssel|api[_-]?key|^key$|_key$|iban|pin$|signatur|cookie|session|csrf|hash~i';

    /**
     * @param array{wer?:string, tat?:string, objekt?:string, objekt_id?:int|string, von?:string, bis?:string, vor?:int} $f
     * @return array{zeilen:list<array<string,mixed>>, weiter:?int}
     */
    public static function lesen(array $f): array
    {
        $wo = []; $par = [];
        if (($f['wer'] ?? '') !== '') { $wo[] = 'actor = ?'; $par[] = (string) $f['wer']; }
        if (($f['tat'] ?? '') !== '') { $wo[] = 'action LIKE ?'; $par[] = str_replace(['%', '_'], ['\\%', '\\_'], (string) $f['tat']) . '%'; }
        if (($f['objekt'] ?? '') !== '') { $wo[] = 'entity = ?'; $par[] = (string) $f['objekt']; }
        if ((int) ($f['objekt_id'] ?? 0) > 0) { $wo[] = 'entity_id = ?'; $par[] = (int) $f['objekt_id']; }
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($f['von'] ?? ''))) { $wo[] = 'created_at >= ?'; $par[] = $f['von'] . ' 00:00:00'; }
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($f['bis'] ?? ''))) { $wo[] = 'created_at <= ?'; $par[] = $f['bis'] . ' 23:59:59'; }
        if ((int) ($f['vor'] ?? 0) > 0) { $wo[] = 'id < ?'; $par[] = (int) $f['vor']; }
        $z = Db::all('SELECT * FROM audit_log' . ($wo ? ' WHERE ' . implode(' AND ', $wo) : '') . ' ORDER BY id DESC LIMIT ' . (self::JE_SEITE + 1), $par);
        $weiter = null;
        if (count($z) > self::JE_SEITE) { array_pop($z); $weiter = (int) end($z)['id']; }
        /* Die rohen Spalten verlassen diese Klasse nicht — nur die geschwärzte Änderung. */
        return ['zeilen' => array_map(static function (array $r): array {
            $r['aenderung'] = self::aenderung($r['before_json'], $r['after_json']);
            unset($r['before_json'], $r['after_json']);
            return $r;
        }, $z), 'weiter' => $weiter];
    }

    /** Für die Auswahlfelder: wer und welche Objekte überhaupt vorkommen. */
    public static function auswahl(): array
    {
        return ['wer' => array_column(Db::all('SELECT actor, COUNT(*) n FROM audit_log GROUP BY actor ORDER BY n DESC LIMIT 40'), 'actor'),
                'objekt' => array_column(Db::all('SELECT entity, COUNT(*) n FROM audit_log GROUP BY entity ORDER BY entity LIMIT 80'), 'entity')];
    }

    /**
     * Vorher/nachher als Liste von Feldern — nur, was sich unterscheidet, geschwärzt.
     * @return list<array{feld:string, vorher:?string, nachher:?string}>
     */
    public static function aenderung(?string $vorher, ?string $nachher): array
    {
        $v = self::flach(json_decode((string) $vorher, true));
        $n = self::flach(json_decode((string) $nachher, true));
        $aus = [];
        foreach (array_unique(array_merge(array_keys($v), array_keys($n))) as $k) {
            $a = $v[$k] ?? null; $b = $n[$k] ?? null;
            if ($a === $b) { continue; }
            $aus[] = ['feld' => (string) $k, 'vorher' => self::zeigen((string) $k, $a), 'nachher' => self::zeigen((string) $k, $b)];
            if (count($aus) >= 40) { break; }
        }
        return $aus;
    }

    /** Ein Wert für die Anzeige: geschwärzt, gekürzt. */
    public static function zeigen(string $feld, ?string $wert): ?string
    {
        if ($wert === null) { return null; }
        if (preg_match(self::GEHEIM, $feld) || self::siehtGeheimAus($wert)) { return '••• (geschwärzt)'; }
        return mb_strlen($wert) > 300 ? mb_substr($wert, 0, 300) . '…' : $wert;
    }

    /** Lange Zeichenketten ohne Leerzeichen, die nach Schlüssel aussehen (sk_live_…, xkeysib-…, JWT, 32+ Hex/Base64). */
    public static function siehtGeheimAus(string $w): bool
    {
        return (bool) preg_match('~^(sk|rk|pk|whsec|xkeysib|xsmtpsib|ghp|gho|github_pat|eyJ)[A-Za-z0-9_\-.]{12,}|^[A-Za-z0-9+/_\-]{32,}={0,2}$~', trim($w));
    }

    /** Verschachteltes JSON zu „a.b“ => Text. */
    private static function flach(mixed $j, string $vor = ''): array
    {
        if (!is_array($j)) { return $j === null ? [] : [($vor !== '' ? $vor : 'wert') => is_scalar($j) ? (string) $j : json_encode($j)]; }
        $aus = [];
        foreach ($j as $k => $w) {
            $schl = $vor === '' ? (string) $k : $vor . '.' . $k;
            if (is_array($w) && $w !== [] && count($aus) < 60) { $aus += self::flach($w, $schl); continue; }
            $aus[$schl] = is_bool($w) ? ($w ? 'ja' : 'nein') : (is_scalar($w) || $w === null ? ($w === null ? null : (string) $w) : json_encode($w, JSON_UNESCAPED_UNICODE));
        }
        return $aus;
    }
}
