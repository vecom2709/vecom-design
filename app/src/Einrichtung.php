<?php
declare(strict_types=1);

/** Die Einrichtungsschritte. Wird vom Einrichter und von der Kommandozeile benutzt. */
final class Einrichtung
{
    /** Welche Migrationen sind noch nicht eingespielt? Faellt nie um. */
    public static function offene(): array
    {
        try {
            $dateien = array_map('basename', glob(dirname(__DIR__) . '/migrations/*.sql') ?: []);
            $erledigt = array_column(Db::all('SELECT datei FROM migrations'), 'datei');
            return array_values(array_diff($dateien, $erledigt));
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Spielt alle noch nicht angewandten Migrationen ein. Gibt die Namen zurueck. */
    public static function migrieren(): array
    {
        Db::run('CREATE TABLE IF NOT EXISTS migrations (
            datei VARCHAR(190) NOT NULL PRIMARY KEY,
            angewandt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $erledigt = array_column(Db::all('SELECT datei FROM migrations'), 'datei');
        $dateien = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
        sort($dateien);
        $neu = [];
        foreach ($dateien as $pfad) {
            $name = basename($pfad);
            if (in_array($name, $erledigt, true)) { continue; }
            $sql = (string) file_get_contents($pfad);
            $sql = preg_replace('~^\s*--.*$~m', '', $sql) ?? $sql;
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $anweisung) {
                Db::pdo()->exec($anweisung);
            }
            Db::insert('migrations', ['datei' => $name]);
            $neu[] = $name;
        }
        return $neu;
    }

    /** Legt den Admin an oder setzt sein Passwort neu. */
    public static function admin(string $name, string $email, string $passwort): string
    {
        $email = mb_strtolower(trim($email));
        $hash  = password_hash($passwort, PASSWORD_DEFAULT);
        $da = Db::one('SELECT id FROM users WHERE email = ?', [$email]);
        if ($da) {
            Db::update('users', (int) $da['id'], ['password_hash' => $hash, 'name' => $name, 'role' => 'admin', 'active' => 1]);
            return 'Passwort neu gesetzt';
        }
        Db::insert('users', ['email' => $email, 'password_hash' => $hash, 'name' => $name, 'role' => 'admin', 'active' => 1]);
        return 'Zugang angelegt';
    }

    /** Uebernimmt die drei Pakete der Website. Mehrfach aufrufbar. */
    public static function pakete(): array
    {
        $ergebnis = [];
        foreach (require __DIR__ . '/Standardpakete.php' as $p) {
            $daten = [
                'slug' => $p['slug'], 'name' => $p['name'], 'description' => $p['description'],
                'sub' => $p['sub'] ?? null, 'ideal' => $p['ideal'] ?? null,
                'price_cents' => $p['price_cents'], 'monthly_cents' => $p['monthly_cents'], 'currency' => 'EUR',
                'features' => json_encode($p['features'], JSON_UNESCAPED_UNICODE),
                'texte' => isset($p['texte']) ? json_encode($p['texte'], JSON_UNESCAPED_UNICODE) : null,
                'detail_url' => $p['detail_url'] ?? null,
                'active' => 1, 'oeffentlich' => 1, 'popular' => $p['popular'], 'sort' => $p['sort'],
            ];
            $da = Db::one('SELECT id FROM packages WHERE slug = ?', [$p['slug']]);
            if ($da) { Db::update('packages', (int) $da['id'], $daten); $ergebnis[] = $p['name'] . ' (aktualisiert)'; }
            else     { Db::insert('packages', $daten); $ergebnis[] = $p['name'] . ' (angelegt)'; }
        }
        return $ergebnis;
    }

    /**
     * Traegt fehlende Website-Texte bei den bekannten Paketen nach — aber nur
     * dort, wo noch nichts steht. Was Uwe selbst eingetragen hat, bleibt.
     * Wird nach einer Aktualisierung aufgerufen, damit die drei Pakete auf der
     * Website nicht ploetzlich ohne Untertitel und in einer Sprache dastehen.
     */
    public static function texteNachtragen(): int
    {
        $ergaenzt = 0;
        foreach (require __DIR__ . '/Standardpakete.php' as $p) {
            $da = Db::one('SELECT id, sub, ideal, texte, detail_url FROM packages WHERE slug = ?', [$p['slug']]);
            if (!$da) { continue; }
            $neu = [];
            if (trim((string) $da['texte']) === '' && isset($p['texte'])) {
                $neu['texte'] = json_encode($p['texte'], JSON_UNESCAPED_UNICODE);
            }
            if (trim((string) $da['sub']) === '' && ($p['sub'] ?? '') !== '')       { $neu['sub'] = $p['sub']; }
            if (trim((string) $da['ideal']) === '' && ($p['ideal'] ?? '') !== '')   { $neu['ideal'] = $p['ideal']; }
            if (trim((string) $da['detail_url']) === '' && ($p['detail_url'] ?? '') !== '') { $neu['detail_url'] = $p['detail_url']; }
            if ($neu) { Db::update('packages', (int) $da['id'], $neu); $ergaenzt++; }
        }
        return $ergaenzt;
    }

    /** Schreibt die Konfigurationsdatei. Werte werden nie in den Text eingesetzt,
        sondern ueber var_export ausgegeben — so kann kein Eingabefeld Code einschleusen. */
    public static function konfigSchreiben(string $pfad, array $daten): bool
    {
        $inhalt = "<?php\n/* Von app/einrichten.php erzeugt. Enthaelt Zugangsdaten —\n"
                . "   gehoert nicht ins Repository und wird vom Deploy nie ueberschrieben. */\n"
                . 'return ' . var_export($daten, true) . ";\n";
        $ok = @file_put_contents($pfad, $inhalt, LOCK_EX) !== false;
        if ($ok) { @chmod($pfad, 0600); }
        return $ok;
    }

    public static function konfigText(array $daten): string
    {
        return "<?php\nreturn " . var_export($daten, true) . ";\n";
    }
}
