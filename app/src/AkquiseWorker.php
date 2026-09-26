<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseVersand.php';

/**
 * Die Tuer fuer den Worker (tools/akquise) -- aufgerufen ueber akquise.php.
 *
 * WAS DER WORKER DARF -- UND WAS NICHT
 *
 * Er darf melden, was er gefunden und geprueft hat, und Textvorschlaege
 * abliefern. Er darf NICHT freigeben, nicht senden, nicht sperren und
 * nichts loeschen. Eine Aktion "senden" gibt es an dieser Tuer nicht, und
 * das ist die eigentliche Trennung von Research und Versand: Selbst ein
 * gestohlener Worker-Schluessel kann keine einzige E-Mail ausloesen.
 */
final class AkquiseWorker
{
    public const AKTIONEN = ['hallo', 'lauf_holen', 'lauf_melden', 'firmen_melden', 'audits_holen',
                             'audit_melden', 'texte_holen', 'deutung_melden', 'vorlage_melden'];

    private const SCHLUESSEL = 'akq_worker_schluessel';
    private const DROSSEL_PRO_MINUTE = 240;
    private const BILD_GRENZE = 1_500_000;

    public static function schluessel(): string
    {
        return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [self::SCHLUESSEL], '');
    }

    public static function neuerSchluessel(): string
    {
        $neu = bin2hex(random_bytes(24));
        AkquiseGate::setzen(self::SCHLUESSEL, $neu);
        Events::pruefspur('akquise_schluessel', 'settings', null);
        return $neu;
    }

    public static function schluesselStimmt(string $eingabe): bool
    {
        $soll = self::schluessel();
        if ($soll === '' || $eingabe === '') { return false; }
        return hash_equals($soll, $eingabe);
    }

    /** Gegen eine Schleife im Worker, nicht gegen Angreifer. */
    public static function darfNoch(): bool
    {
        $takt = 'akq_takt_' . date('YmdHi');
        try {
            Db::run("INSERT INTO settings (skey, svalue) VALUES (?, '1') ON DUPLICATE KEY UPDATE svalue = CAST(svalue AS UNSIGNED) + 1", [$takt]);
            $stand = (int) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$takt], 0);
            if ($stand === 1) {
                Db::run("DELETE FROM settings WHERE skey LIKE 'akq_takt_%' AND skey < ?", ['akq_takt_' . date('YmdHi', time() - 3600)]);
            }
            return $stand <= self::DROSSEL_PRO_MINUTE;
        } catch (Throwable $e) {
            return true;
        }
    }

    /** @param array<string,mixed> $d */
    public static function ausfuehren(string $aktion, array $d): array
    {
        return match ($aktion) {
            'hallo'          => self::hallo(),
            'lauf_holen'     => self::laufHolen(),
            'lauf_melden'    => self::laufMelden($d),
            'firmen_melden'  => self::firmenMelden($d),
            'audits_holen'   => ['ok' => true, 'firmen' => Akquise::naechsteAudits((int) ($d['anzahl'] ?? 10))],
            'audit_melden'   => self::auditMelden($d),
            'texte_holen'    => self::texteHolen($d),
            'deutung_melden' => self::deutungMelden($d),
            'vorlage_melden' => self::vorlageMelden($d),
            default          => throw new InvalidArgumentException('Unbekannte Aktion.'),
        };
    }

    private static function hallo(): array
    {
        $g = AkquiseGate::grenzen();
        return [
            'ok' => true,
            'zeit' => date('c'),
            'stop' => $g['stop'],
            'branchen_stand' => substr(hash_file('sha256', __DIR__ . '/akquise_branchen.json') ?: '', 0, 12),
            'kennzahlen' => Akquise::kennzahlen(),
        ];
    }

    private static function laufHolen(): array
    {
        if (AkquiseGate::grenzen()['stop']) { return ['ok' => true, 'lauf' => null, 'hinweis' => 'Notbremse gezogen.']; }
        // Haengengebliebene Laeufe (Worker abgestuerzt) nach sechs Stunden freigeben.
        Db::run("UPDATE akq_laeufe SET status = 'wartet' WHERE status = 'laeuft' AND gestartet_am < DATE_SUB(NOW(), INTERVAL 6 HOUR)");
        $l = Db::one("SELECT * FROM akq_laeufe WHERE status = 'wartet' ORDER BY id LIMIT 1");
        if (!$l) { return ['ok' => true, 'lauf' => null]; }
        Db::update('akq_laeufe', (int) $l['id'], ['status' => 'laeuft', 'gestartet_am' => date('Y-m-d H:i:s')]);
        Akquise::protokoll(null, 'lauf', 'Recherche gestartet: ' . $l['land'] . ' / ' . $l['ebene'] . ' ' . $l['gebiet'], [], (int) $l['id']);
        $l['branchen'] = $l['branchen'] ? (json_decode((string) $l['branchen'], true) ?: []) : [];
        return ['ok' => true, 'lauf' => $l];
    }

    private static function laufMelden(array $d): array
    {
        $id = (int) ($d['lauf_id'] ?? 0);
        $l = Db::one('SELECT * FROM akq_laeufe WHERE id = ?', [$id]);
        if (!$l) { throw new RuntimeException('Lauf nicht gefunden.'); }
        $status = in_array($d['status'] ?? '', ['fertig', 'fehler', 'laeuft'], true) ? (string) $d['status'] : 'fertig';
        Db::update('akq_laeufe', $id, [
            'status' => $status,
            'fehler' => isset($d['fehler']) ? mb_substr((string) $d['fehler'], 0, 2000) : null,
            'beendet_am' => $status === 'laeuft' ? null : date('Y-m-d H:i:s'),
        ]);
        if ($status !== 'laeuft') {
            Akquise::protokoll(null, 'lauf', 'Recherche ' . ($status === 'fertig' ? 'abgeschlossen' : 'gescheitert')
                . ': ' . $l['gebiet'] . ' — ' . (int) $l['gefunden'] . ' gefunden, ' . (int) $l['neu'] . ' neu, '
                . (int) $l['dubletten'] . ' Dubletten', [], $id);
        }
        return ['ok' => true];
    }

    private static function firmenMelden(array $d): array
    {
        $laufId = isset($d['lauf_id']) ? (int) $d['lauf_id'] : null;
        $liste = array_slice((array) ($d['firmen'] ?? []), 0, 200);
        $neu = $dubletten = $fehler = 0;
        $ergebnisse = [];
        foreach ($liste as $roh) {
            if (!is_array($roh)) { $fehler++; continue; }
            try {
                $r = Akquise::firmaMelden($roh, $laufId);
                if (str_starts_with((string) ($roh['quelle'] ?? ''), 'lead-scout:')) { Akquise::altbestand((int) $r['id'], $roh); }
                $r['neu'] ? $neu++ : $dubletten++;
                $ergebnisse[] = ['quelle' => $roh['quelle'] ?? null, 'id' => $r['id'], 'neu' => $r['neu']];
            } catch (Throwable $e) {
                $fehler++;
                $ergebnisse[] = ['quelle' => $roh['quelle'] ?? null, 'fehler' => $e->getMessage()];
            }
        }
        if ($laufId) {
            Db::run('UPDATE akq_laeufe SET gefunden = gefunden + ?, neu = neu + ?, dubletten = dubletten + ? WHERE id = ?',
                [count($liste), $neu, $dubletten, $laufId]);
        }
        return ['ok' => true, 'neu' => $neu, 'dubletten' => $dubletten, 'fehler' => $fehler, 'ergebnisse' => $ergebnisse];
    }

    private static function auditMelden(array $d): array
    {
        $firmaId = (int) ($d['firma_id'] ?? 0);
        $f = Db::one('SELECT id, kennung FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        // Bilder zuerst ablegen, damit der Audit ihre Dateinamen traegt.
        foreach (['mobil', 'desktop'] as $art) {
            $b64 = (string) ($d['bilder'][$art] ?? '');
            if ($b64 === '') { continue; }
            $name = self::bildAblegen((string) $f['kennung'], $art, $b64);
            if ($name !== null) { $d['screenshot_' . $art] = $name; }
        }
        unset($d['bilder']);
        $r = Akquise::auditMelden($firmaId, $d);
        return ['ok' => true] + $r;
    }

    /** Legt ein JPEG/PNG geschuetzt unter uploads/akquise/ ab. */
    public static function bildAblegen(string $kennung, string $art, string $b64): ?string
    {
        $roh = base64_decode($b64, true);
        if ($roh === false || strlen($roh) > self::BILD_GRENZE || strlen($roh) < 100) { return null; }
        $endung = str_starts_with($roh, "\xFF\xD8\xFF") ? 'jpg' : (str_starts_with($roh, "\x89PNG") ? 'png' : null);
        if ($endung === null) { return null; }
        require_once __DIR__ . '/Ablage.php';
        $ordner = Ablage::ordner() . '/akquise';
        if (!is_dir($ordner)) { @mkdir($ordner, 0755, true); }
        $name = preg_replace('~[^A-Z0-9-]~', '', $kennung) . '-' . date('YmdHis') . '-' . $art . '.' . $endung;
        return file_put_contents($ordner . '/' . $name, $roh) === false ? null : $name;
    }

    /**
     * Firmen, fuer die Claude einen Text schreiben soll.
     *
     * Kostenkontrolle: nur fertig gepruefte Firmen ab Score 51, die nicht
     * gesperrt sind, noch keine offene Vorlage haben und nie kontaktiert
     * wurden. Alles darunter bekommt keinen einzigen Token.
     */
    private static function texteHolen(array $d): array
    {
        $min = max(0, min(100, (int) ($d['score_min'] ?? 51)));
        $anzahl = max(1, min(20, (int) ($d['anzahl'] ?? 5)));
        $firmen = Db::all("SELECT f.* FROM akq_firmen f
                            WHERE f.gesperrt = 0 AND f.audit_status = 'fertig' AND f.score >= ?
                              AND f.kontakt_status IN ('neu','qualifiziert')
                              AND NOT EXISTS (SELECT 1 FROM akq_vorlagen v WHERE v.firma_id = f.id AND v.status IN ('entwurf','freigegeben','gesendet'))
                              AND NOT EXISTS (SELECT 1 FROM akq_versand s WHERE s.firma_id = f.id AND s.status IN ('gesendet','von_hand'))
                            ORDER BY f.score DESC LIMIT $anzahl", [$min]);
        $out = [];
        foreach ($firmen as $f) {
            $a = Akquise::letzterAudit((int) $f['id']);
            if (!$a) { continue; }
            $out[] = [
                'firma' => array_intersect_key($f, array_flip(['id', 'kennung', 'name', 'domain', 'url', 'land', 'region', 'stadt',
                    'branche', 'sprache', 'tourismus', 'ansprechpartner'])),
                'sprache' => AkquiseText::spracheFuer($f),
                'audit' => ['id' => (int) $a['id'], 'loesung' => $a['loesung'], 'experience' => $a['experience']],
                'befunde' => array_map(static fn($b) => array_intersect_key($b, array_flip(['kategorie', 'code', 'schwere', 'titel',
                    'beschreibung', 'wirkung', 'url', 'messwert', 'beleg', 'status'])), Akquise::befunde((int) $a['id'])),
                'absender' => AkquiseText::absender(),
            ];
        }
        return ['ok' => true, 'firmen' => $out];
    }

    /**
     * Claudes Deutung eines Audits: Loesung und Experience-Idee (deutsch, fuer
     * Uwe), die Experience-Idee in der Sprache der Firma (fuer den Text) und
     * zusaetzliche Befunde aus dem Bildschirmfoto.
     *
     * Befunde von Claude kommen IMMER als UNVERIFIED herein, egal was der
     * Worker schickt: Eine Einschaetzung vom Bild ist eine Einschaetzung,
     * keine Messung. Sie zaehlt im Score mit 40 % und erscheint in keinem
     * Regeltext.
     */
    private static function deutungMelden(array $d): array
    {
        $auditId = (int) ($d['audit_id'] ?? 0);
        $a = Db::one('SELECT * FROM akq_audits WHERE id = ?', [$auditId]);
        if (!$a) { throw new RuntimeException('Audit nicht gefunden.'); }
        $ki = is_array($d['ki'] ?? null) ? $d['ki'] : [];
        Db::update('akq_audits', $auditId, [
            'loesung'    => mb_substr(trim((string) ($d['loesung'] ?? '')), 0, 4000) ?: $a['loesung'],
            'experience' => mb_substr(trim((string) ($d['experience'] ?? '')), 0, 4000) ?: $a['experience'],
            'ki'         => json_encode($ki, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ki_modell'  => mb_substr((string) ($d['ki_modell'] ?? ''), 0, 60) ?: null,
        ]);
        $neu = 0;
        $jetzt = date('Y-m-d H:i:s');
        foreach (array_slice((array) ($d['befunde'] ?? []), 0, 12) as $b) {
            if (!is_array($b)) { continue; }
            $kat = (string) ($b['kategorie'] ?? '');
            if (!in_array($kat, ['ux', 'design', 'conversion', 'vertrauen', 'experience'], true)) { continue; }
            $titel = trim((string) ($b['titel'] ?? ''));
            if ($titel === '') { continue; }
            Db::insert('akq_befunde', [
                'audit_id' => $auditId, 'firma_id' => (int) $a['firma_id'], 'kategorie' => $kat,
                'code' => 'ki_' . (preg_replace('~[^a-z0-9_]~', '', strtolower((string) ($b['code'] ?? 'einschaetzung'))) ?: 'einschaetzung'),
                'schwere' => max(1, min(4, (int) ($b['schwere'] ?? 2))),
                'titel' => mb_substr($titel, 0, 255),
                'beschreibung' => mb_substr(trim((string) ($b['beschreibung'] ?? '')), 0, 2000) ?: null,
                'wirkung' => mb_substr(trim((string) ($b['wirkung'] ?? '')), 0, 1000) ?: null,
                'beleg' => 'Einschätzung von Claude anhand des Bildschirmfotos — nicht gemessen.',
                'status' => 'UNVERIFIED', 'erkannt_am' => $jetzt,
            ]);
            $neu++;
        }
        $r = Akquise::neuBewerten($auditId);
        Akquise::protokoll((int) $a['firma_id'], 'ki', 'Deutung durch Claude' . ($neu ? " (+$neu Einschätzungen)" : ''), ['modell' => $d['ki_modell'] ?? null]);
        return ['ok' => true, 'score' => $r['score'], 'neu' => $neu];
    }

    private static function vorlageMelden(array $d): array
    {
        /* Nachbessern: Der Worker darf einen eigenen, noch nicht
           freigegebenen Claude-Entwurf ersetzen -- nie einen, den Uwe
           geschrieben, bearbeitet oder freigegeben hat. */
        $ersetzt = null;
        if (!empty($d['ersetzt'])) {
            $alt = Db::one("SELECT id FROM akq_vorlagen WHERE id = ? AND firma_id = ? AND erzeugt_von = 'claude' AND status = 'entwurf'",
                [(int) $d['ersetzt'], (int) ($d['firma_id'] ?? 0)]);
            $ersetzt = $alt ? (int) $alt['id'] : null;
        }
        $id = AkquiseVersand::vorlageSpeichern(
            (int) ($d['firma_id'] ?? 0), isset($d['audit_id']) ? (int) $d['audit_id'] : null,
            (string) ($d['sprache'] ?? ''), (string) ($d['kanal'] ?? 'email'),
            (string) ($d['betreff'] ?? ''), (string) ($d['text'] ?? ''), 'claude', $ersetzt);
        $v = Db::one('SELECT status, pruefhinweise FROM akq_vorlagen WHERE id = ?', [$id]);
        return ['ok' => true, 'vorlage_id' => $id,
                'beanstandungen' => $v && $v['pruefhinweise'] ? json_decode((string) $v['pruefhinweise'], true) : []];
    }
}
