<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseAnsprechen.php';
require_once __DIR__ . '/PartnerRecherche.php';
require_once __DIR__ . '/Partner.php';
require_once __DIR__ . '/PartnerPost.php';
require_once __DIR__ . '/Texte.php';

/**
 * Anrufliste (29.09.2026, Uwe: Ja zu T1–T4).
 *
 * Uwe hakt in „Neue Kunden finden“ Betriebe an und übergibt sie einem
 * Partner zum Abtelefonieren. Der Partner sieht sie in seinem Dashboard mit
 * Anruf-Knopf und dem Text zum Sagen in der Sprache des Betriebs, und trägt
 * danach ein: Zugestimmt (DE: E-Mail, IT: E-Mail und/oder WhatsApp), Kein
 * Interesse oder Nicht erreicht.
 *
 * Nach „Zugestimmt“ läuft alles automatisch (Dashboard-Mail, Folge-Mails,
 * Meldung an Uwe), und E-Mail/Nummer sind für den Partner vorgemerkt:
 * Kauft der Betrieb, gehört er zum Partner -- mit mindestens 15 %
 * (Partner::STANDARD partner_anruf_bp, Quelle „anruf“).
 *
 * Werbeanrufe: Italien -- nie an Nummern im Registro Pubblico delle
 * Opposizioni; Deutschland -- nur bei mutmaßlichem Interesse. Deshalb prüft
 * das Gate jede Nummer, und Uwe bestätigt beim Übergeben seinen Prüfvermerk.
 * Keine Rechtsberatung.
 */
final class PartnerAnrufliste
{
    public const TAGE = 60;
    public const JE_UEBERGABE = 50;
    /** Nach so vielen „Nicht erreicht“ fällt der Betrieb aus der Liste (Uwe, 29.09.2026). */
    public const MAX_VERSUCHE = 3;
    /** So alt darf die Prüfung im Registro delle Opposizioni höchstens sein (Prüfung 07.10.2026, Punkt 21). */
    public const RPO_TAGE = 15;

    /** Italienische Nummer ohne gültige RPO-Prüfung? Dann darf sie nicht angerufen werden. */
    public static function rpoFehlt(array $f, ?int $heute = null): bool
    {
        if (strtoupper((string) ($f['land'] ?? '')) !== 'IT') { return false; }
        $am = (string) ($f['rpo_frei_am'] ?? '');
        if ($am === '') { return true; }
        return strtotime($am) < strtotime('-' . self::RPO_TAGE . ' days', strtotime(date('Y-m-d', $heute ?? time())));
    }

    /**
     * Betriebe einem Partner übergeben.
     * @param list<int> $firmaIds
     * @return array{ok:int,weg:array<string,string>}
     */
    public static function uebergeben(array $firmaIds, int $partnerId, string $vermerk, string $wer = 'Uwe'): array
    {
        $p = Partner::laden($partnerId);
        if (!$p || $p['status'] !== 'aktiv') { throw new RuntimeException('Bitte einen aktiven Partner wählen.'); }
        $vermerk = trim(mb_substr($vermerk, 0, 200));
        if (mb_strlen($vermerk) < 15) { throw new RuntimeException('Bitte den Prüfvermerk bestätigen (Registro delle Opposizioni / mutmaßliches Interesse).'); }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $firmaIds), static fn($i) => $i > 0))), 0, self::JE_UEBERGABE);
        if (!$ids) { throw new RuntimeException('Bitte in der Liste mindestens einen Betrieb anhaken.'); }
        $ok = 0; $weg = [];
        foreach ($ids as $fid) {
            $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$fid]);
            if (!$f) { continue; }
            $name = (string) $f['name'];
            if (strlen((string) preg_replace('~\D~', '', (string) ($f['telefon'] ?? ''))) < 6) { $weg[$name] = 'keine Telefonnummer'; continue; }
            if ((int) $f['gesperrt'] === 1 || (int) $f['bestandskunde'] === 1 || in_array((string) $f['kontakt_status'], ['abgelehnt', 'gesperrt', 'kunde'], true)) { $weg[$name] = 'gesperrt oder schon Kunde'; continue; }
            if (trim((string) ($f['einwilligung'] ?? '')) !== '') { $weg[$name] = 'hat schon zugestimmt'; continue; }
            $r = Db::one('SELECT partner_id, bis FROM partner_reservierungen WHERE firma_id = ?', [$fid]);
            if ($r && (int) $r['partner_id'] !== $partnerId && strtotime((string) $r['bis']) >= strtotime('today')) { $weg[$name] = 'reserviert von einem anderen Partner'; continue; }
            $g = AkquiseGate::pruefen(['id' => null] + $f, 'telefon');   // ohne id: die eigene Reservierung zählt hier nicht
            if (!in_array($g['status'], [AkquiseGate::ERLAUBT, AkquiseGate::PRUEFEN], true)) { $weg[$name] = 'Anruf nicht erlaubt: ' . ($g['gruende'][0] ?? ''); continue; }
            /* Der Haken beim Übergeben ist Uwes Bestätigung von HEUTE — sie wird je Nummer mit
               Datum gespeichert und läuft nach 15 Tagen ab (dann verschwindet der Betrieb aus der
               Anrufliste, bis Uwe neu prüft und neu übergibt). */
            if (strtoupper((string) ($f['land'] ?? '')) === 'IT') {
                try { Db::run('UPDATE akq_firmen SET rpo_frei_am = CURDATE() WHERE id = ?', [$fid]); }
                catch (Throwable $e) { $weg[$name] = 'RPO-Prüfung lässt sich nicht speichern (Migration 214 fehlt)'; continue; }
            }
            Db::run('INSERT INTO partner_reservierungen (firma_id, partner_id, bis, herkunft, anruf_status, versuche, vermerk)
                     VALUES (?, ?, DATE_ADD(CURDATE(), INTERVAL ' . self::TAGE . " DAY), 'vecom', 'offen', 0, ?)
                     ON DUPLICATE KEY UPDATE partner_id = VALUES(partner_id), bis = VALUES(bis), herkunft = 'vecom', anruf_status = 'offen',
                                             versuche = 0, anruf_am = NULL, naechster_versuch = NULL, vermerk = VALUES(vermerk), created_at = NOW()",
                [$fid, $partnerId, mb_substr($vermerk . ' (' . $wer . ', ' . date('d.m.Y') . ')', 0, 255)]);
            Akquise::protokoll($fid, 'anrufliste', 'An Partner ' . $p['name'] . ' zum Abtelefonieren übergeben — Prüfvermerk: ' . $vermerk);
            $ok++;
        }
        if ($ok > 0) {
            try {
                $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
                $T = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sp);
                PartnerPost::push($partnerId, $T('al_push_titel'), strtr($T('al_push_text'), ['{n}' => (string) $ok]), Partner::portalLink($p) . '#anrufliste');
            } catch (Throwable $e) { }
        }
        return ['ok' => $ok, 'weg' => $weg];
    }

    /** Die offenen Anrufe des Partners (zuerst die noch nicht versuchten). @return list<array<string,mixed>> */
    public static function liste(int $partnerId): array
    {
        /* Italienische Nummern nur mit RPO-Prüfung, die höchstens 15 Tage alt ist. */
        try {
            return array_values(array_filter(self::listeRoh($partnerId), static fn(array $f): bool => !self::rpoFehlt($f)));
        } catch (Throwable $e) { return []; }
    }

    /** @return list<array<string,mixed>> */
    private static function listeRoh(int $partnerId): array
    {
        try {
            return Db::all("SELECT f.*, r.anruf_status, r.versuche, r.anruf_am, r.bis
                              FROM partner_reservierungen r JOIN akq_firmen f ON f.id = r.firma_id
                             WHERE r.partner_id = ? AND r.herkunft = 'vecom' AND r.bis >= CURDATE() AND f.gesperrt = 0
                               AND r.anruf_status IN ('offen','nicht_erreicht') AND (r.naechster_versuch IS NULL OR r.naechster_versuch <= CURDATE())
                          ORDER BY r.naechster_versuch IS NULL, r.naechster_versuch, r.created_at, f.id LIMIT 200", [$partnerId]);
        } catch (Throwable $e) { return []; }
    }

    /** Wiedervorlage: wie viele warten noch, und wann ist der nächste dran? @return array{n:int,naechster:?string} */
    public static function wiedervorlage(int $partnerId): array
    {
        try {
            $z = Db::one("SELECT COUNT(*) AS n, MIN(naechster_versuch) AS naechster FROM partner_reservierungen
                           WHERE partner_id = ? AND herkunft = 'vecom' AND anruf_status = 'nicht_erreicht' AND naechster_versuch > CURDATE() AND bis >= CURDATE()", [$partnerId]);
            return ['n' => (int) ($z['n'] ?? 0), 'naechster' => $z['naechster'] ?? null];
        } catch (Throwable $e) { return ['n' => 0, 'naechster' => null]; }
    }

    /** Nächster Versuch in 2–3 Tagen, nie an einem Sonntag. */
    public static function naechsterVersuch(int $versuche, ?int $heute = null): string
    {
        $t = strtotime('+' . ($versuche % 2 === 1 ? 2 : 3) . ' days', $heute ?? time());
        if ((int) date('N', $t) === 7) { $t = strtotime('+1 day', $t); }
        return date('Y-m-d', $t);
    }

    /** Morgens (8–11 Uhr) einmal: Partnern mit fälligen Rückrufen Bescheid geben. */
    public static function morgen(?int $jetzt = null): int
    {
        $jetzt ??= time();
        $h = (int) date('G', $jetzt);
        if ($h < 8 || $h > 11) { return 0; }
        $n = 0;
        try {
            foreach (Db::all("SELECT r.partner_id, COUNT(*) AS n FROM partner_reservierungen r
                               WHERE r.herkunft = 'vecom' AND r.anruf_status = 'nicht_erreicht' AND r.naechster_versuch = ? AND r.bis >= ?
                            GROUP BY r.partner_id", [date('Y-m-d', $jetzt), date('Y-m-d', $jetzt)]) as $z) {
                $k = 'al_rueckruf_' . (int) $z['partner_id'];
                if ((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], '') === date('Y-m-d', $jetzt)) { continue; }
                Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, date('Y-m-d', $jetzt)]);
                $p = Partner::laden((int) $z['partner_id']);
                require_once __DIR__ . '/PartnerSchutz.php';
                if (!$p || $p['status'] !== 'aktiv' || !PartnerSchutz::freigeschaltet($p)) { continue; }
                $sp = in_array((string) $p['sprache'], ['it', 'de', 'en'], true) ? (string) $p['sprache'] : 'it';
                $T = static fn(string $x): string => Texte::h(Texte::PARTNER[$x] ?? [], $sp);
                try { PartnerPost::push((int) $p['id'], $T('al_rr_titel'), strtr($T('al_rr_text'), ['{n}' => (string) (int) $z['n']]), Partner::portalLink($p) . '#anrufliste'); } catch (Throwable $e) { }
                $n++;
            }
        } catch (Throwable $e) { }
        return $n;
    }

    /** @return array{zugestimmt:int,kein_interesse:int} */
    public static function erledigt(int $partnerId): array
    {
        $n = ['zugestimmt' => 0, 'kein_interesse' => 0];
        try {
            foreach (Db::all("SELECT anruf_status, COUNT(*) AS n FROM partner_reservierungen WHERE partner_id = ? AND herkunft = 'vecom'
                               AND anruf_status IN ('zugestimmt','kein_interesse') GROUP BY anruf_status", [$partnerId]) as $z) {
                $n[(string) $z['anruf_status']] = (int) $z['n'];
            }
        } catch (Throwable $e) { }
        return $n;
    }

    /** Der Satz für Käufe aus der Anrufliste (für die Anzeige beim Partner). */
    public static function satz(array $p): array
    {
        $s = Partner::satzFuer($p);
        if ($s['art'] === 'prozent') { $s['wert'] = max((int) $s['wert'], Partner::zahl('partner_anruf_bp')); }
        return $s;
    }

    /** Welche Wege der Partner eintragen darf: DE nur E-Mail, IT E-Mail und WhatsApp. @return list<string> */
    public static function wege(array $f): array
    {
        return strtoupper((string) ($f['land'] ?? '')) === 'DE' ? ['email'] : ['email', 'whatsapp'];
    }

    /**
     * Ergebnis des Anrufs.
     * @param array{email?:string,whatsapp?:string,person?:string,vorgelesen?:bool} $d
     * @return string ok | al_weg | al_mail | al_wa | al_eins | al_person | al_haken | al_fehler
     */
    public static function ergebnis(array $p, int $firmaId, string $ergebnis, array $d = []): string
    {
        $pid = (int) $p['id'];
        $r = Db::one("SELECT * FROM partner_reservierungen WHERE firma_id = ? AND partner_id = ? AND herkunft = 'vecom' AND bis >= CURDATE()", [$firmaId, $pid]);
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$r || !$f || (int) $f['gesperrt'] === 1 || !in_array((string) $r['anruf_status'], ['offen', 'nicht_erreicht'], true)) { return 'al_weg'; }
        $wer = 'Partner ' . $p['name'];

        if ($ergebnis === 'nicht_erreicht') {
            /* Wiedervorlage in 2–3 Tagen; nach dem dritten Mal fällt der Betrieb heraus
               (die Reservierung endet, er ist in der Verwaltung wieder frei). */
            $v = (int) $r['versuche'] + 1;
            if ($v >= self::MAX_VERSUCHE) {
                Db::run("UPDATE partner_reservierungen SET anruf_status = 'nicht_erreichbar', versuche = ?, anruf_am = NOW(), naechster_versuch = NULL,
                          bis = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE firma_id = ?", [$v, $firmaId]);
                Akquise::protokoll($firmaId, 'anrufliste', $wer . ': ' . $v . '× nicht erreicht — aus der Anrufliste genommen');
                return 'al_raus';
            }
            Db::run("UPDATE partner_reservierungen SET anruf_status = 'nicht_erreicht', versuche = ?, anruf_am = NOW(), naechster_versuch = ? WHERE firma_id = ?",
                [$v, self::naechsterVersuch($v), $firmaId]);
            Akquise::protokoll($firmaId, 'anrufliste', $wer . ': angerufen, nicht erreicht (' . $v . '. Versuch) — Wiedervorlage');
            return 'ok';
        }
        if ($ergebnis === 'kein_interesse') {
            Db::run("UPDATE partner_reservierungen SET anruf_status = 'kein_interesse', anruf_am = NOW() WHERE firma_id = ?", [$firmaId]);
            require_once __DIR__ . '/AkquiseVersand.php';
            AkquiseVersand::antwortEintragen($firmaId, $wer, 'Anruf', 'Am Telefon (' . $wer . '): kein Interesse.', 'NOT_INTERESTED');
            return 'ok';
        }
        if ($ergebnis !== 'zugestimmt') { return 'al_fehler'; }

        $wege = self::wege($f);
        $mail = trim((string) ($d['email'] ?? ''));
        $wa = in_array('whatsapp', $wege, true) ? trim((string) ($d['whatsapp'] ?? '')) : '';
        $person = trim((string) ($d['person'] ?? ''));
        if ($mail !== '' && Akquise::normEmail($mail) === null) { return 'al_mail'; }
        if ($wa !== '' && strlen((string) preg_replace('~\D~', '', $wa)) < 8) { return 'al_wa'; }
        /* Die E-Mail ist Pflicht (Uwe, 29.09.2026): nur so geht der persönliche Bereich sofort automatisch raus
           und der Betrieb bleibt über seine Adresse dem Partner zugeordnet. WhatsApp (nur IT) zusätzlich. */
        if ($mail === '') { return 'al_mail'; }
        if (mb_strlen($person) < 2) { return 'al_person'; }
        if (empty($d['vorgelesen'])) { return 'al_haken'; }

        /* Zuerst vormerken: Wird aus dem Betrieb ein Kunde (auch gleich durch die Dashboard-Mail), gehört er zum Partner. */
        Partner::vormerken($pid, $mail !== '' ? $mail : null, $wa !== '' ? $wa : (string) $f['telefon'], 'anruf', 'anruf');
        Db::run("UPDATE partner_reservierungen SET anruf_status = 'zugestimmt', anruf_am = NOW() WHERE firma_id = ?", [$firmaId]);
        try {
            require_once __DIR__ . '/AkquiseEinwilligung.php';
            AkquiseEinwilligung::muendlich($firmaId, 'anruf', $person, $mail, $wa, $mail !== '', $wa !== '', true, true, $pid, $wer);
        } catch (Throwable $e) {
            Db::run("UPDATE partner_reservierungen SET anruf_status = ?, anruf_am = ? WHERE firma_id = ?", [(string) $r['anruf_status'], $r['anruf_am'], $firmaId]);
            return 'al_fehler';
        }
        return 'ok';
    }

    /** Für die Verwaltung: was liegt bei welchem Partner? @return list<array<string,mixed>> */
    public static function ueberblick(): array
    {
        try {
            return Db::all("SELECT p.id, p.name,
                                   SUM(r.anruf_status IN ('offen','nicht_erreicht') AND r.bis >= CURDATE()) AS offen,
                                   SUM(r.anruf_status = 'zugestimmt') AS zugestimmt,
                                   SUM(r.anruf_status = 'kein_interesse') AS kein_interesse,
                                   SUM(r.anruf_status = 'nicht_erreichbar') AS nicht_erreichbar
                              FROM partner_reservierungen r JOIN partner p ON p.id = r.partner_id
                             WHERE r.herkunft = 'vecom' GROUP BY p.id, p.name ORDER BY offen DESC, p.name");
        } catch (Throwable $e) { return []; }
    }
}
