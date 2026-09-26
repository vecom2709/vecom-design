<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/PartnerCheck.php';

/**
 * Signal-Wecker und Wiedervorlage der Akquise (26.09.2026, Uwe: Ja).
 *
 * SIGNAL-WECKER: Einmal die Woche je Betrieb nachsehen, ob seine Website
 * noch antwortet und wie lange das Zertifikat hält. Gemeldet wird nur eine
 * VERÄNDERUNG (war erreichbar, ist es nicht mehr) oder ein Termin (Zertifikat
 * läuft in 14 Tagen ab) -- eine Seite, die schon beim ersten Blick tot war,
 * kennt das Audit längst („domain_tot“); sie jede Woche neu zu melden, wäre
 * Lärm. Abgerufen wird über PartnerCheck::holen: dieselben Sicherungen
 * (nur öffentliche Adressen, feste IP, begrenzte Zeit).
 *
 * WIEDERVORLAGE: Nach einem Brief (5 Tage) oder einer E-Mail (3 Tage) meldet
 * sich der Betrieb in der Verwaltung zurück -- mit dem, was seitdem passiert
 * ist (Analyse-Seite geöffnet? Antwort?). Eine zweite Ansprache löst sie NICHT
 * aus; das Gate bleibt, wie es ist.
 */
final class AkquiseSignal
{
    public const JE_LAUF = 3;
    public const ABSTAND_TAGE = 7;
    public const SSL_WARNUNG_TAGE = 14;
    public const WIEDERVORLAGE = ['brief' => 5, 'email' => 3];

    /** @var null|callable(string):array Austauschbar für die Kette. */
    public static $holer = null;

    public static function lauf(): array
    {
        $n = ['geprueft' => 0, 'signale' => 0];
        $firmen = Db::all("SELECT * FROM akq_firmen
                            WHERE url IS NOT NULL AND url <> '' AND gesperrt = 0 AND bestandskunde = 0
                              AND kontakt_status NOT IN ('abgelehnt','gesperrt','kunde')
                              AND (signal_geprueft_am IS NULL OR signal_geprueft_am < DATE_SUB(NOW(), INTERVAL " . self::ABSTAND_TAGE . " DAY))
                         ORDER BY signal_geprueft_am IS NOT NULL, signal_geprueft_am, COALESCE(score, 0) DESC
                            LIMIT " . self::JE_LAUF);
        foreach ($firmen as $f) {
            // Erst vermerken: Eine Seite, die den Abruf hängen lässt, soll nicht jeden Lauf blockieren.
            Db::run('UPDATE akq_firmen SET signal_geprueft_am = NOW() WHERE id = ?', [(int) $f['id']]);
            $n['signale'] += self::pruefen($f);
            $n['geprueft']++;
        }
        return $n;
    }

    /** @return int Zahl der neuen Signale */
    public static function pruefen(array $f): int
    {
        $url = PartnerCheck::adresse((string) $f['url']);
        if ($url === null) { return 0; }
        $a = self::$holer ? (self::$holer)($url) : PartnerCheck::holen($url);
        $status = $a['ok'] ? (int) $a['status'] : ($a['fehler'] === 'zertifikat' ? -1 : (int) $a['status']);
        $vorher = $f['web_status'] === null ? null : (int) $f['web_status'];
        $gut = static fn(?int $s): bool => $s !== null && $s >= 200 && $s < 400;
        $neu = 0;
        if ($vorher !== null && $gut($vorher) && $status === -1) {
            $neu += self::signal($f, 'ssl_abgelaufen', 'Zertifikat ungültig — Besucher sehen eine Sicherheitswarnung statt der Seite.');
        } elseif ($vorher !== null && $gut($vorher) && !$gut($status)) {
            $neu += self::signal($f, 'offline', 'Website antwortet nicht mehr' . ($status > 0 ? ' (HTTP ' . $status . ')' : '') . '.');
        } elseif ($vorher !== null && !$gut($vorher) && $gut($status)) {
            self::signal($f, 'wieder_online', 'Website ist wieder erreichbar.', false);
        }
        $tage = $a['ssl_tage'] ?? null;
        if ($gut($status) && $tage !== null && $tage >= 0 && $tage <= self::SSL_WARNUNG_TAGE) {
            $neu += self::signal($f, 'ssl_bald', 'Zertifikat läuft in ' . $tage . ' Tag' . ($tage === 1 ? '' : 'en') . ' ab — danach warnt jeder Browser vor der Seite.');
        }
        Db::run('UPDATE akq_firmen SET web_status = ?, ssl_bis = ? WHERE id = ?',
            [$status, $tage !== null ? date('Y-m-d', strtotime('+' . (int) $tage . ' days')) : null, (int) $f['id']]);
        return $neu;
    }

    /** Ein Signal je Art und Firma höchstens alle 30 Tage. @return int 1 = neu */
    private static function signal(array $f, string $art, string $text, bool $melden = true): int
    {
        $schon = (int) Db::wert('SELECT COUNT(*) FROM akq_signale WHERE firma_id = ? AND art = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)',
            [(int) $f['id'], $art], 0);
        if ($schon > 0) { return 0; }
        Db::insert('akq_signale', ['firma_id' => (int) $f['id'], 'art' => $art, 'text' => mb_substr($text, 0, 255), 'erledigt' => $melden ? 0 : 1]);
        Akquise::protokoll((int) $f['id'], 'signal', $text);
        if ($melden) {
            try { Events::melden('akquise_signal', 'Akquise-Signal: ' . $f['name'], 'info', $text, 'akquise/' . $f['id']); } catch (Throwable $e) { }
        }
        return $melden ? 1 : 0;
    }

    /** @return list<array<string,mixed>> Offene Signale mit Firmenname, neueste zuerst. */
    public static function offen(int $n = 20): array
    {
        return Db::all('SELECT s.*, f.name, f.stadt, f.kontakt_status FROM akq_signale s JOIN akq_firmen f ON f.id = s.firma_id
                         WHERE s.erledigt = 0 ORDER BY s.id DESC LIMIT ' . max(1, $n));
    }

    public static function erledigen(int $signalId): void
    {
        Db::run('UPDATE akq_signale SET erledigt = 1 WHERE id = ?', [$signalId]);
    }

    /* ==================================================================== */
    /*  Wiedervorlage                                                       */
    /* ==================================================================== */

    public static function vormerken(int $firmaId, string $kanal): void
    {
        $tage = self::WIEDERVORLAGE[$kanal] ?? null;
        if ($tage === null) { return; }
        // Datum aus PHP (Europe/Rome), nicht CURDATE(): Die Datenbank läuft
        // womöglich in UTC -- kurz nach Mitternacht lägen beide einen Tag auseinander.
        Db::run('UPDATE akq_firmen SET wiedervorlage_am = ? WHERE id = ?', [date('Y-m-d', strtotime('+' . (int) $tage . ' days')), $firmaId]);
    }

    /** Fällige Wiedervorlagen melden (eine Meldung je Firma) und abhaken. @return int */
    public static function wiedervorlagen(): int
    {
        $n = 0;
        foreach (Db::all('SELECT * FROM akq_firmen WHERE wiedervorlage_am IS NOT NULL AND wiedervorlage_am <= ? LIMIT 20', [date('Y-m-d')]) as $f) {
            Db::run('UPDATE akq_firmen SET wiedervorlage_am = NULL WHERE id = ?', [(int) $f['id']]);
            $text = self::stand($f);
            Akquise::protokoll((int) $f['id'], 'wiedervorlage', 'Wiedervorlage: ' . $text);
            try { Events::melden('akquise_wiedervorlage', 'Wiedervorlage: ' . $f['name'], 'info', $text, 'akquise/' . $f['id']); } catch (Throwable $e) { }
            $n++;
        }
        return $n;
    }

    /** Was seit der Ansprache passiert ist -- in einem Satz für die Meldung. */
    public static function stand(array $f): string
    {
        $v = Db::one("SELECT kanal, created_at FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand') ORDER BY id DESC LIMIT 1", [(int) $f['id']]);
        $an = Db::one('SELECT aufrufe, zuletzt_am FROM akq_analysen WHERE firma_id = ? ORDER BY id DESC LIMIT 1', [(int) $f['id']]);
        $antwort = (int) Db::wert('SELECT COUNT(*) FROM akq_antworten WHERE firma_id = ?', [(int) $f['id']], 0);
        $teile = [];
        if ($v) { $teile[] = ($v['kanal'] === 'brief' ? 'Brief' : ucfirst((string) $v['kanal'])) . ' vom ' . date('d.m.', strtotime((string) $v['created_at'])); }
        $teile[] = $an && (int) $an['aufrufe'] > 0 ? 'Analyse-Seite ' . (int) $an['aufrufe'] . '× geöffnet' : 'Analyse-Seite noch nicht geöffnet';
        $teile[] = $antwort > 0 ? 'Antwort liegt vor' : 'keine Antwort';
        if (trim((string) ($f['einwilligung'] ?? '')) !== '') { $teile[] = 'E-Mail erlaubt (Einwilligung)'; }
        return implode(' · ', $teile);
    }
}
