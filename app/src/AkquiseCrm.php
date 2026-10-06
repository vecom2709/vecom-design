<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquisePrio.php';

/* ==========================================================================
   AkquiseCrm.php — der Betrieb als vollständiges Profil (Akquise-CRM Modul A
   und C, 06.10.2026, Uwe: „A+B+C: Profil, Score, Arbeitsplatz“).

   Baut auf akq_firmen auf — kein zweiter Datensatz je Betrieb. Neu sind die
   Profilfelder (Migration 193), weitere Ansprechpartner, Notizen mit
   Sichtbarkeit, Kanäle und Sperrarten; dazu der Arbeitsplatz „Heute“ und
   „Nächster bester Kontakt“.

   SPERRARTEN
   hart  → AkquiseGate::sperren (Sperrliste, dauerhaft, nur über die Regeln
           zu lösen): Nicht kontaktieren, Beschwerde, Rechtsfall, Kein Interesse.
   weich → nur gesperrt = 1 + Art; lässt sich hier wieder lösen: bestehender
           Kunde, ehemaliger Kunde, Partner, Wettbewerber, intern gesperrt.
   Beide: Versand aus (das Gate sieht gesperrt = 1), Priorität „nie“.

   QUELLE IST PFLICHT ZU ZEIGEN
   Woher die Daten stammen (Art, Link, gefunden am/von), steht immer sichtbar
   im Profil — erfunden wird nichts, und was niemand eingetragen hat, steht
   als „—“ da.
   ========================================================================== */
final class AkquiseCrm
{
    public const SPERR_ARTEN = [
        'nicht_kontaktieren' => 'Nicht kontaktieren', 'beschwerde' => 'Beschwerde', 'rechtsfall' => 'Rechtsfall', 'kein_interesse' => 'Kein Interesse',
        'bestandskunde' => 'Bestehender Kunde', 'ehemaliger_kunde' => 'Ehemaliger Kunde', 'partner' => 'Partner', 'wettbewerber' => 'Wettbewerber', 'intern' => 'Intern gesperrt',
    ];
    /** Diese Arten gehen auf die Sperrliste und lassen sich hier nicht lösen. */
    public const SPERR_HART = ['nicht_kontaktieren', 'beschwerde', 'rechtsfall', 'kein_interesse'];

    public const QUELLEN = ['google' => 'Google', 'webseite' => 'Firmenwebseite', 'branchenbuch' => 'Branchenbuch', 'linkedin' => 'LinkedIn', 'xing' => 'Xing',
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'empfehlung' => 'Empfehlung', 'manuell' => 'Manuell', 'osm' => 'OpenStreetMap', 'overture' => 'Overture Maps', 'sonstige' => 'Sonstige Quelle'];

    public const KANAELE = ['email' => 'E-Mail', 'whatsapp' => 'WhatsApp', 'telefon' => 'Telefon', 'linkedin' => 'LinkedIn', 'xing' => 'Xing',
        'formular' => 'Kontaktformular', 'brief' => 'Brief', 'besuch' => 'Besuch'];

    /** Felder, die das Profil-Formular schreibt — alles andere bleibt unberührt. Wert: Höchstlänge. */
    public const PROFIL = [
        'name' => 190, 'rechtsform' => 40, 'branche' => 40, 'unterbranche' => 80, 'adresse' => 255, 'plz' => 10, 'stadt' => 120, 'kreis' => 120, 'region' => 120,
        'ansprechpartner' => 120, 'position' => 80, 'email' => 190, 'telefon' => 40, 'mobil' => 40, 'whatsapp' => 40, 'url' => 500,
        'google_profil' => 500, 'facebook' => 500, 'instagram' => 500, 'linkedin' => 500, 'xing' => 500,
        'quelle_art' => 20, 'quelle_url' => 500, 'gefunden_von' => 80, 'naechster_schritt' => 160, 'naechster_am' => 10, 'filiale_von' => 11,
    ];
    private const LINKS = ['url', 'google_profil', 'facebook', 'instagram', 'linkedin', 'xing', 'quelle_url'];

    /* ------------------------------------------------------------------ Profil */

    /**
     * Profil speichern. Prüft jedes Feld auf dem Server; Links nur http(s), E-Mails gültig.
     * @return array{ok:bool, fehler?:string, geaendert?:array<string,mixed>}
     */
    public static function profilSpeichern(int $id, array $d): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { return ['ok' => false, 'fehler' => 'Betrieb nicht gefunden.']; }
        $neu = [];
        foreach (self::PROFIL as $k => $max) {
            if (!array_key_exists($k, $d)) { continue; }
            $w = trim(str_replace(["\r", "\n", "\t"], ' ', (string) $d[$k]));
            $w = mb_substr($w, 0, $max);
            if ($w === '') { $neu[$k] = $k === 'name' ? (string) $f['name'] : null; continue; }
            if (in_array($k, self::LINKS, true)) {
                if (!preg_match('~^https?://[^\s<>"]{3,}$~i', $w)) { return ['ok' => false, 'fehler' => 'Bitte einen vollständigen Link mit https:// eintragen (' . $k . ').']; }
            }
            if ($k === 'email' && !filter_var($w, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'fehler' => 'Die E-Mail-Adresse ist nicht gültig.']; }
            if ($k === 'naechster_am' && !preg_match('~^\d{4}-\d{2}-\d{2}$~', $w)) { return ['ok' => false, 'fehler' => 'Datum für den nächsten Schritt ungültig.']; }
            if ($k === 'quelle_art' && !isset(self::QUELLEN[$w])) { return ['ok' => false, 'fehler' => 'Unbekannte Quelle.']; }
            if ($k === 'branche' && !isset(Akquise::branchen()[$w])) { return ['ok' => false, 'fehler' => 'Unbekannte Branche.']; }
            if ($k === 'filiale_von') {
                $h = (int) $w;
                if ($h === $id || (int) Db::wert('SELECT COUNT(*) FROM akq_firmen WHERE id = ?', [$h], 0) !== 1) { return ['ok' => false, 'fehler' => 'Den Hauptsitz gibt es nicht.']; }
                $w = $h;
            }
            if ($k === 'email') { $w = mb_strtolower($w); }
            $neu[$k] = $w;
        }
        if (array_key_exists('emails_weitere', $d)) {
            $liste = array_values(array_unique(array_filter(array_map(static fn($e) => mb_strtolower(trim($e)), preg_split('~[\s,;]+~', (string) $d['emails_weitere']) ?: []))));
            foreach ($liste as $e) { if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'fehler' => '„' . $e . '“ ist keine gültige E-Mail-Adresse.']; } }
            $neu['emails_weitere'] = $liste ? json_encode(array_slice($liste, 0, 10)) : null;
        }
        if (isset($neu['url'])) { $neu['domain'] = Akquise::normDomain((string) $neu['url']); }
        $geaendert = array_filter($neu, static fn($v, $k) => (string) ($f[$k] ?? '') !== (string) ($v ?? ''), ARRAY_FILTER_USE_BOTH);
        if (!$geaendert) { return ['ok' => true, 'geaendert' => []]; }
        /* Neue Adresse = neue Herkunft, ungeprüft — dieselbe Regel wie unter „Bearbeiten“ (Kommunikationsstatus E-Mail, 06.10.2026). */
        $mailNeu = isset($geaendert['email']) && $geaendert['email'] !== null;
        try { Db::update('akq_firmen', $id, $geaendert); }
        catch (Throwable $e) {
            if (Db::doppelt($e)) { return ['ok' => false, 'fehler' => 'Diese Website gehört schon zu einem anderen Betrieb — wahrscheinlich eine Dublette.']; }
            throw $e;
        }
        if ($mailNeu) {
            try { Db::update('akq_firmen', $id, ['email_verified' => 0, 'email_source' => 'Von Hand eingetragen von ' . (Auth::name() ?: 'Verwaltung') . ' am ' . date('d.m.Y')]); }
            catch (Throwable $e) { }   // vor Migration 192 fehlen die Spalten
            require_once __DIR__ . '/AkquiseGate.php';
            AkquiseGate::statusSpeichern($id);
        }
        $vorher = array_intersect_key($f, $geaendert);
        Events::pruefspur('akquise_profil', 'akq_firmen', $id, $vorher, $geaendert);
        Akquise::protokoll($id, 'profil', 'Profil geändert: ' . implode(', ', array_keys($geaendert)));
        require_once __DIR__ . '/AkquisePrio.php';
        AkquisePrio::aktualisieren($id);
        return ['ok' => true, 'geaendert' => $geaendert];
    }

    /** Quelle in Klartext — aus quelle_art oder der technischen Quelle (osm:…, overture:…, lead-scout:…). */
    public static function quelle(array $f): string
    {
        $art = (string) ($f['quelle_art'] ?? '');
        if ($art === '') {
            $roh = (string) ($f['quelle'] ?? '');
            $art = match (true) {
                str_starts_with($roh, 'osm:') => 'osm', str_starts_with($roh, 'overture:') => 'overture',
                str_starts_with($roh, 'partner') => 'empfehlung', $roh === '' => '', default => 'sonstige',
            };
        }
        return $art !== '' ? (self::QUELLEN[$art] ?? $art) : '—';
    }

    /* ------------------------------------------------------------- Sperrarten */

    public static function sperrArtSetzen(int $id, string $art, string $grund): array
    {
        if (!isset(self::SPERR_ARTEN[$art])) { return ['ok' => false, 'fehler' => 'Unbekannte Sperrart.']; }
        $grund = mb_substr(trim($grund), 0, 255);
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { return ['ok' => false, 'fehler' => 'Betrieb nicht gefunden.']; }
        if (in_array($art, self::SPERR_HART, true)) {
            require_once __DIR__ . '/AkquiseGate.php';
            AkquiseGate::sperren($id, self::SPERR_ARTEN[$art] . ($grund !== '' ? ': ' . $grund : ''), 'hand');
            if ($art === 'kein_interesse') { Db::update('akq_firmen', $id, ['kontakt_status' => 'abgelehnt']); }
        } else {
            Db::update('akq_firmen', $id, ['gesperrt' => 1]);
        }
        Db::update('akq_firmen', $id, ['sperr_art' => $art, 'sperr_grund' => $grund !== '' ? $grund : null, 'sperr_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('akquise_sperrart', 'akq_firmen', $id, ['sperr_art' => $f['sperr_art'] ?? null, 'gesperrt' => $f['gesperrt']], ['sperr_art' => $art, 'grund' => $grund]);
        Akquise::protokoll($id, 'sperre', '🔴 ' . self::SPERR_ARTEN[$art] . ($grund !== '' ? ' — ' . $grund : ''));
        require_once __DIR__ . '/AkquisePrio.php';
        AkquisePrio::aktualisieren($id);
        return ['ok' => true];
    }

    /** Nur weiche Sperren lassen sich hier lösen — ein Widerspruch bleibt, wo er ist (Regeln & Versand › Sperrliste). */
    public static function sperrArtLoesen(int $id): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { return ['ok' => false, 'fehler' => 'Betrieb nicht gefunden.']; }
        $art = (string) ($f['sperr_art'] ?? '');
        if ($art === '' || in_array($art, self::SPERR_HART, true)) {
            return ['ok' => false, 'fehler' => 'Diese Sperre lässt sich hier nicht lösen — sie steht auf der Sperrliste (Regeln & Versand).'];
        }
        require_once __DIR__ . '/AkquiseGate.php';
        if (AkquiseGate::trifftSperrliste($f) !== null) { return ['ok' => false, 'fehler' => 'Der Betrieb steht zusätzlich auf der Sperrliste — dort zuerst lösen.']; }
        Db::update('akq_firmen', $id, ['gesperrt' => 0, 'sperr_art' => null, 'sperr_grund' => null, 'sperr_am' => null]);
        Events::pruefspur('akquise_sperrart_geloest', 'akq_firmen', $id, ['sperr_art' => $art], []);
        Akquise::protokoll($id, 'sperre', 'Sperre gelöst (' . self::SPERR_ARTEN[$art] . ')');
        require_once __DIR__ . '/AkquisePrio.php';
        AkquisePrio::aktualisieren($id);
        return ['ok' => true];
    }

    /* --------------------------------------------------------------- Kontakte */

    public static function kontaktAnlegen(int $firmaId, array $d): array
    {
        $name = mb_substr(trim((string) ($d['name'] ?? '')), 0, 120);
        if (mb_strlen($name) < 2) { return ['ok' => false, 'fehler' => 'Bitte einen Namen eintragen.']; }
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { return ['ok' => false, 'fehler' => 'Die E-Mail-Adresse ist nicht gültig.']; }
        if ((int) Db::wert('SELECT COUNT(*) FROM akq_kontakte WHERE firma_id = ?', [$firmaId], 0) >= 20) { return ['ok' => false, 'fehler' => 'Mehr als 20 Ansprechpartner je Betrieb gehen nicht.']; }
        $id = (int) Db::insert('akq_kontakte', ['firma_id' => $firmaId, 'name' => $name, 'position' => mb_substr(trim((string) ($d['position'] ?? '')), 0, 80) ?: null,
            'email' => $email ?: null, 'telefon' => mb_substr(trim((string) ($d['telefon'] ?? '')), 0, 40) ?: null, 'notiz' => mb_substr(trim((string) ($d['notiz'] ?? '')), 0, 255) ?: null]);
        Akquise::protokoll($firmaId, 'kontakt', 'Ansprechpartner ergänzt: ' . $name);
        return ['ok' => true, 'id' => $id];
    }

    public static function kontaktLoeschen(int $firmaId, int $kontaktId): bool
    {
        $k = Db::one('SELECT * FROM akq_kontakte WHERE id = ? AND firma_id = ?', [$kontaktId, $firmaId]);
        if (!$k) { return false; }
        Db::run('DELETE FROM akq_kontakte WHERE id = ?', [$kontaktId]);
        Events::pruefspur('akquise_kontakt_geloescht', 'akq_firmen', $firmaId, $k, []);
        Akquise::protokoll($firmaId, 'kontakt', 'Ansprechpartner entfernt: ' . $k['name']);
        return true;
    }

    /* ---------------------------------------------------------------- Notizen */

    /** @param array{user_id?:?int, partner_id?:?int, autor:string} $wer */
    public static function notizAnlegen(int $firmaId, string $text, string $sichtbar, bool $angeheftet, array $wer): array
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if (mb_strlen($text) < 2) { return ['ok' => false, 'fehler' => 'Die Notiz ist leer.']; }
        if (!in_array($sichtbar, ['ich', 'admin', 'team'], true)) { return ['ok' => false, 'fehler' => 'Unbekannte Sichtbarkeit.']; }
        if (!empty($wer['partner_id']) && $sichtbar === 'admin') { return ['ok' => false, 'fehler' => 'Partner schreiben „nur für mich“ oder „Team“.']; }
        $id = (int) Db::insert('akq_notizen', ['firma_id' => $firmaId, 'user_id' => $wer['user_id'] ?? null, 'partner_id' => $wer['partner_id'] ?? null,
            'autor' => mb_substr((string) $wer['autor'], 0, 80), 'sichtbar' => $sichtbar, 'angeheftet' => $angeheftet ? 1 : 0, 'text' => mb_substr($text, 0, 2000)]);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Notizen, die dieser Betrachter sehen darf, angeheftete zuerst.
     * Verwaltung: alles außer fremden „nur für mich“ (auch die eines Partners). Partner: eigene „ich“ und alle „team“.
     * @param array{user_id?:?int, partner_id?:?int} $wer
     */
    public static function notizen(int $firmaId, array $wer): array
    {
        if (!empty($wer['partner_id'])) {
            return Db::all("SELECT * FROM akq_notizen WHERE firma_id = ? AND (sichtbar = 'team' OR (sichtbar = 'ich' AND partner_id = ?))
                             ORDER BY angeheftet DESC, id DESC LIMIT 200", [$firmaId, (int) $wer['partner_id']]);
        }
        return Db::all("SELECT * FROM akq_notizen WHERE firma_id = ? AND (sichtbar <> 'ich' OR (user_id IS NOT NULL AND user_id = ?))
                         ORDER BY angeheftet DESC, id DESC LIMIT 200", [$firmaId, (int) ($wer['user_id'] ?? 0)]);
    }

    /** Anheften/lösen oder löschen — nur der Verfasser (Verwaltung darf Team-/Admin-Notizen aller Verwaltungsnutzer). */
    public static function notizAendern(int $firmaId, int $notizId, string $was, array $wer): bool
    {
        $n = Db::one('SELECT * FROM akq_notizen WHERE id = ? AND firma_id = ?', [$notizId, $firmaId]);
        if (!$n) { return false; }
        $darf = !empty($wer['partner_id']) ? (int) $n['partner_id'] === (int) $wer['partner_id']
                                           : ($n['partner_id'] === null && ($n['sichtbar'] !== 'ich' || (int) $n['user_id'] === (int) ($wer['user_id'] ?? 0)));
        if (!$darf) { return false; }
        if ($was === 'loeschen') {
            Db::run('DELETE FROM akq_notizen WHERE id = ?', [$notizId]);
            Events::pruefspur('akquise_notiz_geloescht', 'akq_firmen', $firmaId, ['notiz' => mb_substr((string) $n['text'], 0, 200)], []);
            return true;
        }
        Db::run('UPDATE akq_notizen SET angeheftet = 1 - angeheftet WHERE id = ?', [$notizId]);
        return true;
    }

    /* ---------------------------------------------------------------- Kanäle */

    /**
     * Je Kanal: Zustand (offen | benutzt | antwort | pausiert | gesperrt | kein_weg), letzter und nächster Kontakt.
     * Aus Versand, Antworten, Sperre und Gate gerechnet; nur „pausiert“, „von Hand benutzt“ und „nächster“ werden gespeichert.
     */
    public static function kanaele(array $f): array
    {
        $id = (int) $f['id'];
        $hand = [];
        foreach (Db::all('SELECT * FROM akq_kanaele WHERE firma_id = ?', [$id]) as $r) { $hand[(string) $r['kanal']] = $r; }
        $versand = [];
        foreach (Db::all("SELECT kanal, MAX(created_at) l FROM akq_versand WHERE firma_id = ? AND status IN ('gesendet','von_hand') GROUP BY kanal", [$id]) as $r) {
            $versand[$r['kanal'] === 'kontaktformular' ? 'formular' : (string) $r['kanal']] = $r['l'];
        }
        $antwort = (string) Db::wert('SELECT MAX(created_at) FROM akq_antworten WHERE firma_id = ?', [$id], '');
        $gesperrt = (int) ($f['gesperrt'] ?? 0) === 1;
        /* E-Mail hat seit dem 06.10.2026 einen eigenen Kommunikationsstatus (AkquiseMail) — der gilt hier, nicht eine zweite Rechnung. */
        $mail = null;
        try { require_once __DIR__ . '/AkquiseMail.php'; $mail = AkquiseMail::status($f); } catch (Throwable $e) { }
        $hat = ['email' => $f['email'] ?? '', 'whatsapp' => ($f['whatsapp'] ?? '') ?: ($f['mobil'] ?? ''), 'telefon' => ($f['telefon'] ?? '') ?: ($f['mobil'] ?? ''),
                'linkedin' => $f['linkedin'] ?? '', 'xing' => $f['xing'] ?? '', 'formular' => $f['url'] ?? '', 'brief' => $f['adresse'] ?? '', 'besuch' => $f['adresse'] ?? ''];
        $aus = [];
        foreach (self::KANAELE as $k => $wort) {
            $h = $hand[$k] ?? [];
            $letzter = max((string) ($versand[$k] ?? ''), (string) ($h['benutzt_am'] ?? ''));
            $zustand = match (true) {
                $gesperrt, $k === 'email' && $mail === 'nicht_kontaktieren' => 'gesperrt',
                !empty($h['pausiert']) => 'pausiert',
                $letzter !== '' && $antwort !== '' && $antwort >= $letzter && $k === 'email' => 'antwort',
                $letzter !== '' => 'benutzt',
                trim((string) $hat[$k]) === '' => 'kein_weg',
                default => 'offen',
            };
            $aus[$k] = ['wort' => $wort, 'zustand' => $zustand, 'letzter' => $letzter !== '' ? $letzter : null, 'naechster' => $h['naechster_am'] ?? null,
                        'mailstatus' => $k === 'email' && $mail !== null ? AkquiseMail::STATUS[$mail] : null];
        }
        return $aus;
    }

    /** Kanal pausieren/fortsetzen, von Hand als benutzt vermerken, nächsten Kontakt setzen. */
    public static function kanalSetzen(int $firmaId, string $kanal, string $was, ?string $datum = null): bool
    {
        if (!isset(self::KANAELE[$kanal])) { return false; }
        Db::run('INSERT IGNORE INTO akq_kanaele (firma_id, kanal) VALUES (?, ?)', [$firmaId, $kanal]);
        match ($was) {
            'pausieren' => Db::run('UPDATE akq_kanaele SET pausiert = 1, geaendert_am = NOW() WHERE firma_id = ? AND kanal = ?', [$firmaId, $kanal]),
            'fortsetzen' => Db::run('UPDATE akq_kanaele SET pausiert = 0, geaendert_am = NOW() WHERE firma_id = ? AND kanal = ?', [$firmaId, $kanal]),
            'benutzt' => Db::run('UPDATE akq_kanaele SET benutzt_am = NOW(), geaendert_am = NOW() WHERE firma_id = ? AND kanal = ?', [$firmaId, $kanal]),
            'naechster' => Db::run('UPDATE akq_kanaele SET naechster_am = ?, geaendert_am = NOW() WHERE firma_id = ? AND kanal = ?',
                [$datum !== null && preg_match('~^\d{4}-\d{2}-\d{2}$~', $datum) ? $datum : null, $firmaId, $kanal]),
            default => null,
        };
        if ($was === 'benutzt') { Db::run('UPDATE akq_firmen SET letzter_kontakt_am = NOW() WHERE id = ?', [$firmaId]); }
        Akquise::protokoll($firmaId, 'kanal', self::KANAELE[$kanal] . ': ' . ['pausieren' => 'pausiert', 'fortsetzen' => 'fortgesetzt', 'benutzt' => 'Kontakt vermerkt', 'naechster' => 'nächster Kontakt ' . ($datum ?: '—')][$was]);
        return true;
    }

    /* --------------------------------------------------------------- Pipeline
       Zwölf Stufen und vier Seitenstufen (Uwe: Drag & Drop). Eine Wahrheit:
       Was sich aus den Daten ergibt, wird gerechnet (gefunden, geprüft,
       reserviert, Text bereit, kontaktiert, Antwort, Interesse). Von Hand
       gesetzt wird nur, was die Daten nicht wissen können — Bedarf geklärt,
       Angebot erstellt, Später in crm_stufe; Angebot gesendet, Nachfassen,
       Gewonnen, Verloren wie bisher in pipeline (Akquise::pipelineSetzen).
       Gesperrt und Kein Interesse kommen nur über den Sperrstatus zustande. */
    public const SPALTEN = [
        'neu' => 'Neu gefunden', 'geprueft' => 'Geprüft', 'reserviert' => 'Reserviert', 'vorbereitet' => 'Erstkontakt vorbereitet',
        'kontaktiert' => 'Kontaktiert', 'antwort' => 'Antwort erhalten', 'interesse' => 'Interesse', 'bedarf' => 'Bedarf geklärt',
        'angebot_erstellt' => 'Angebot erstellt', 'angebot_gesendet' => 'Angebot gesendet', 'nachfassen' => 'Nachfassen', 'gewonnen' => 'Auftrag gewonnen',
        'spaeter' => 'Später', 'kein_interesse' => 'Kein Interesse', 'verloren' => 'Verloren', 'gesperrt' => 'Gesperrt',
    ];
    /** Wohin man ziehen darf, und was es schreibt: crm_stufe oder pipeline. */
    public const ZIEHBAR = ['bedarf' => ['crm', 'bedarf'], 'angebot_erstellt' => ['crm', 'angebot_erstellt'], 'spaeter' => ['crm', 'spaeter'],
        'angebot_gesendet' => ['pipeline', 'angebot'], 'nachfassen' => ['pipeline', 'verhandlung'], 'gewonnen' => ['pipeline', 'gewonnen'], 'verloren' => ['pipeline', 'verloren']];
    /** Gerechnete Stufen: Hierhin ziehen heißt „wieder aus den Daten rechnen“. */
    public const GERECHNET = ['neu', 'geprueft', 'reserviert', 'vorbereitet', 'kontaktiert', 'antwort', 'interesse'];

    /** Die Spalte als SQL-Ausdruck — damit zählt und filtert die Datenbank, auch bei vielen tausend Betrieben. */
    public static function spalteSql(): string
    {
        $pos = "'" . implode("','", ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST']) . "'";
        /* Modul G (06.10.2026, Uwe: „Pipeline folgt dem Angebot, aus den Daten“): Angebot, Bestellung und
           Preisrechner des verknüpften Kunden schieben den Betrieb weiter. Von Hand Gesetztes bleibt möglich;
           ein angenommenes Angebot oder eine Bestellung ist aber „Gewonnen“, egal was vorher gezogen wurde. */
        require_once __DIR__ . '/AkquiseKunde.php';
        $g = AkquiseKunde::spalteTeile();
        return "CASE
            WHEN f.kontakt_status = 'abgelehnt' OR f.sperr_art = 'kein_interesse' THEN 'kein_interesse'
            WHEN f.pipeline = 'gewonnen' OR f.kontakt_status = 'kunde' OR f.bestandskunde = 1 OR {$g['gewonnen']} THEN 'gewonnen'
            WHEN f.gesperrt = 1 THEN 'gesperrt'
            WHEN f.pipeline = 'verloren' THEN 'verloren'
            WHEN f.crm_stufe = 'spaeter' THEN 'spaeter'
            WHEN f.pipeline = 'verhandlung' OR {$g['abgelaufen']} THEN 'nachfassen'
            WHEN f.pipeline = 'angebot' OR {$g['gesendet']} THEN 'angebot_gesendet'
            WHEN {$g['verloren']} THEN 'verloren'
            WHEN f.crm_stufe = 'angebot_erstellt' OR {$g['entwurf']} THEN 'angebot_erstellt'
            WHEN f.crm_stufe = 'bedarf' OR {$g['bedarf']} THEN 'bedarf'
            WHEN EXISTS (SELECT 1 FROM akq_antworten a WHERE a.firma_id = f.id AND a.klasse IN ($pos))
              OR EXISTS (SELECT 1 FROM akq_termine t WHERE t.firma_id = f.id AND t.status IN ('gebucht','erledigt')) THEN 'interesse'
            WHEN f.kontakt_status = 'geantwortet' THEN 'antwort'
            WHEN f.kontakt_status = 'kontaktiert' OR EXISTS (SELECT 1 FROM akq_versand v WHERE v.firma_id = f.id AND v.status IN ('gesendet','von_hand')) THEN 'kontaktiert'
            WHEN f.kontakt_status IN ('vorlage','freigegeben') THEN 'vorbereitet'
            WHEN EXISTS (SELECT 1 FROM partner_reservierungen r WHERE r.firma_id = f.id AND r.bis >= CURDATE()) THEN 'reserviert'
            WHEN f.audit_status IN ('fertig','keine_website') THEN 'geprueft'
            ELSE 'neu' END";
    }

    public static function spalte(int $id): string
    {
        return (string) Db::wert('SELECT ' . self::spalteSql() . ' FROM akq_firmen f WHERE f.id = ?', [$id], 'neu');
    }

    /**
     * Die Tafel: je Spalte Zahl und die ersten Betriebe nach Priorität. Filter serverseitig.
     * @param array{branche?:string, stadt?:string, q?:string, prio?:string, partner?:string} $filter
     * @return array<string,array{n:int, zeilen:list<array<string,mixed>>}>
     */
    public static function pipeline(array $filter = [], int $je = 25): array
    {
        $wo = ['1=1']; $par = [];
        foreach (['branche' => 'f.branche', 'stadt' => 'f.stadt'] as $k => $sp) {
            if (trim((string) ($filter[$k] ?? '')) !== '') { $wo[] = "$sp = ?"; $par[] = trim((string) $filter[$k]); }
        }
        if (!empty($filter['prio']) && isset(AkquisePrio::STUFEN[$filter['prio']])) { $wo[] = 'f.prio_stufe = ?'; $par[] = (string) $filter['prio']; }
        if (trim((string) ($filter['q'] ?? '')) !== '') {
            $like = '%' . addcslashes(trim((string) $filter['q']), '%_\\') . '%';
            $wo[] = '(f.name LIKE ? OR f.stadt LIKE ? OR f.domain LIKE ?)'; array_push($par, $like, $like, $like);
        }
        if (ctype_digit((string) ($filter['partner'] ?? ''))) {
            $wo[] = 'EXISTS (SELECT 1 FROM partner_reservierungen r WHERE r.firma_id = f.id AND r.bis >= CURDATE() AND r.partner_id = ?)'; $par[] = (int) $filter['partner'];
        }
        $innen = 'SELECT f.id, f.name, f.stadt, f.branche, f.prio_score, f.prio_stufe, f.prio_gruende, f.score, f.naechster_am, f.letzter_kontakt_am, '
            . self::spalteSql() . ' AS spalte FROM akq_firmen f WHERE ' . implode(' AND ', $wo);
        $aus = array_fill_keys(array_keys(self::SPALTEN), ['n' => 0, 'zeilen' => []]);
        foreach (Db::all("SELECT spalte, COUNT(*) n FROM ($innen) t GROUP BY spalte", $par) as $r) { $aus[(string) $r['spalte']]['n'] = (int) $r['n']; }
        foreach (Db::all("SELECT * FROM (SELECT t.*, ROW_NUMBER() OVER (PARTITION BY spalte ORDER BY prio_score IS NULL, prio_score DESC, id DESC) AS nr FROM ($innen) t) u
                           WHERE nr <= " . max(1, min(100, $je)) . ' ORDER BY nr', $par) as $r) {
            $aus[(string) $r['spalte']]['zeilen'][] = $r;
        }
        return $aus;
    }

    /**
     * Ziehen auf eine Spalte. Gerechnete Spalten heben die Handsetzung auf; Gesperrt/Kein Interesse gehen nur über die Sperre;
     * aus „Gewonnen“ heraus zieht man nicht (der Kunde ist angelegt).
     * @return array{ok:bool, fehler?:string, spalte?:string}
     */
    public static function stufeSetzen(int $id, string $ziel): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$id]);
        if (!$f) { return ['ok' => false, 'fehler' => 'Betrieb nicht gefunden.']; }
        if (!isset(self::SPALTEN[$ziel])) { return ['ok' => false, 'fehler' => 'Unbekannte Stufe.']; }
        $jetzt = self::spalte($id);
        if ($jetzt === $ziel) { return ['ok' => true, 'spalte' => $jetzt]; }
        if (in_array($ziel, ['gesperrt', 'kein_interesse'], true)) { return ['ok' => false, 'fehler' => 'Sperren und „Kein Interesse“ gehen über den Sperrstatus im Profil — mit Grund.']; }
        if (in_array($jetzt, ['gewonnen', 'gesperrt', 'kein_interesse'], true)) { return ['ok' => false, 'fehler' => '„' . self::SPALTEN[$jetzt] . '“ ist abgeschlossen und wird hier nicht mehr verschoben.']; }
        if (isset(self::ZIEHBAR[$ziel])) {
            [$wo, $wert] = self::ZIEHBAR[$ziel];
            if ($wo === 'pipeline') {
                Akquise::pipelineSetzen($id, $wert);
                Db::run('UPDATE akq_firmen SET crm_stufe = NULL, crm_stufe_am = NULL WHERE id = ?', [$id]);
            } else {
                if (!empty($f['pipeline'])) { Akquise::pipelineSetzen($id, ''); }
                Db::run('UPDATE akq_firmen SET crm_stufe = ?, crm_stufe_am = NOW() WHERE id = ?', [$wert, $id]);
                Events::pruefspur('akquise_stufe', 'akq_firmen', $id, ['stufe' => $jetzt], ['stufe' => $ziel]);
                Akquise::protokoll($id, 'pipeline', 'Stufe gesetzt: ' . self::SPALTEN[$ziel]);
            }
        } else {
            if (!empty($f['pipeline'])) { Akquise::pipelineSetzen($id, ''); }
            Db::run('UPDATE akq_firmen SET crm_stufe = NULL, crm_stufe_am = NULL WHERE id = ?', [$id]);
            Events::pruefspur('akquise_stufe', 'akq_firmen', $id, ['stufe' => $jetzt], ['stufe' => 'gerechnet']);
            Akquise::protokoll($id, 'pipeline', 'Stufe wieder aus den Daten gerechnet');
        }
        require_once __DIR__ . '/AkquisePrio.php';
        AkquisePrio::aktualisieren($id);
        return ['ok' => true, 'spalte' => self::spalte($id)];
    }

    /* ------------------------------------------------- Arbeitsplatz „Heute“ */

    /** Grundbedingung für „ansprechbar“: nicht gesperrt, nicht erledigt, nicht bei einem Partner reserviert. */
    private const ANSPRECHBAR = "f.gesperrt = 0 AND f.kontakt_status NOT IN ('kunde','abgelehnt','gesperrt')
        AND NOT EXISTS (SELECT 1 FROM partner_reservierungen r WHERE r.firma_id = f.id AND r.bis >= CURDATE())";

    /**
     * Die Kacheln für „Heute“ — je Kachel Zahl und die ersten Betriebe.
     * @return array<string,array{titel:string, ton:string, n:int, zeilen:list<array<string,mixed>>}>
     */
    public static function heute(int $je = 6): array
    {
        $pos = "'" . implode("','", ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST']) . "'";
        $felder = 'f.id, f.name, f.stadt, f.branche, f.prio_score, f.prio_stufe, f.prio_gruende, f.score, f.naechster_am, f.wiedervorlage_am, f.letzter_kontakt_am, f.kontakt_status';
        $k = [];
        $k['heiss'] = ['titel' => 'Heiße Antworten — warten auf dich', 'ton' => 'schlecht', 'sql' => "SELECT $felder, MIN(a.created_at) AS seit FROM akq_firmen f JOIN akq_antworten a ON a.firma_id = f.id
            WHERE a.klasse IN ($pos) AND a.erledigt = 0 AND f.gesperrt = 0 GROUP BY f.id ORDER BY seit"];
        $k['antworten'] = ['titel' => 'Offene Antworten', 'ton' => 'warnung', 'sql' => "SELECT $felder, MIN(a.created_at) AS seit FROM akq_firmen f JOIN akq_antworten a ON a.firma_id = f.id
            WHERE a.klasse NOT IN ($pos) AND a.klasse NOT IN ('OUT_OF_OFFICE','INVALID_ADDRESS','DO_NOT_CONTACT','NOT_INTERESTED') AND a.erledigt = 0 AND f.gesperrt = 0 GROUP BY f.id ORDER BY seit"];
        $k['faellig'] = ['titel' => 'Wiedervorlagen & Follow-ups fällig', 'ton' => 'warnung', 'sql' => "SELECT $felder, LEAST(COALESCE(f.naechster_am, '9999-12-31'), COALESCE(f.wiedervorlage_am, '9999-12-31')) AS seit
            FROM akq_firmen f WHERE " . self::ANSPRECHBAR . " AND (f.naechster_am <= CURDATE() OR f.wiedervorlage_am <= CURDATE()) ORDER BY seit"];
        /* Modul E: Nachfassen Tag 3 / Tag 7 ohne Antwort — Erinnerung mit fertigem Entwurf, gesendet wird von Hand (AkquiseAntwort::nachfassen). */
        $k['nachfassen'] = ['titel' => 'Nachfassen fällig (Tag 3 / Tag 7)', 'ton' => 'warnung', 'sql' => "SELECT $felder, v.erste AS seit FROM akq_firmen f
            JOIN (SELECT firma_id, COUNT(*) AS n, MIN(created_at) AS erste, MAX(created_at) AS letzte FROM akq_versand WHERE kanal = 'email' AND status IN ('gesendet','von_hand') GROUP BY firma_id) v ON v.firma_id = f.id
            WHERE f.gesperrt = 0 AND f.kontakt_status NOT IN ('kunde','abgelehnt','gesperrt','geantwortet') AND v.letzte <= NOW() - INTERVAL 2 DAY
              AND ((v.n = 1 AND v.erste <= NOW() - INTERVAL 3 DAY) OR (v.n = 2 AND v.erste <= NOW() - INTERVAL 7 DAY))
              AND NOT EXISTS (SELECT 1 FROM akq_antworten a WHERE a.firma_id = f.id AND a.eingang_am >= v.erste)
              AND NOT EXISTS (SELECT 1 FROM akq_folgen fo WHERE fo.firma_id = f.id AND fo.status IN ('laeuft','pausiert'))
            ORDER BY v.erste"];
        $k['ohne_schritt'] = ['titel' => 'Interessenten ohne nächsten Schritt', 'ton' => 'warnung', 'sql' => "SELECT $felder, NULL AS seit FROM akq_firmen f
            WHERE f.gesperrt = 0 AND f.kontakt_status NOT IN ('kunde','abgelehnt','gesperrt') AND f.naechster_am IS NULL AND (f.wiedervorlage_am IS NULL OR f.wiedervorlage_am < CURDATE())
              AND (f.pipeline IN ('angebot','verhandlung') OR EXISTS (SELECT 1 FROM akq_antworten a WHERE a.firma_id = f.id AND a.klasse IN ($pos) AND a.erledigt = 1))
            ORDER BY f.prio_score DESC"];
        $k['jetzt'] = ['titel' => '🔥 Hohe Priorität, noch nicht angesprochen', 'ton' => 'gut', 'sql' => "SELECT $felder, NULL AS seit FROM akq_firmen f WHERE " . self::ANSPRECHBAR . "
            AND f.prio_stufe = 'jetzt' AND f.kontakt_status IN ('neu','qualifiziert','vorlage','freigegeben') ORDER BY f.prio_score DESC"];
        $k['reservierung'] = ['titel' => 'Partner-Reservierungen laufen ab (5 Tage)', 'ton' => '', 'sql' => "SELECT $felder, r.bis AS seit, p.name AS partner FROM akq_firmen f
            JOIN partner_reservierungen r ON r.firma_id = f.id JOIN partner p ON p.id = r.partner_id WHERE r.bis BETWEEN CURDATE() AND CURDATE() + INTERVAL 5 DAY ORDER BY r.bis"];
        $aus = [];
        foreach ($k as $schl => $x) {
            try {
                $n = (int) Db::wert('SELECT COUNT(*) FROM (' . $x['sql'] . ') t', [], 0);
                $aus[$schl] = ['titel' => $x['titel'], 'ton' => $x['ton'], 'n' => $n, 'zeilen' => $n > 0 ? Db::all($x['sql'] . ' LIMIT ' . max(1, $je)) : []];
            } catch (Throwable $e) { $aus[$schl] = ['titel' => $x['titel'], 'ton' => $x['ton'], 'n' => 0, 'zeilen' => []]; }
        }
        return $aus;
    }

    /** Wie lange wartet eine heiße Antwort? 24 h gelb, 48 h rot, 72 h Hinweis an Uwe (Modul F schickt ihn). */
    public static function warteStufe(?string $seit): string
    {
        if ($seit === null || $seit === '') { return ''; }
        $h = (time() - strtotime($seit)) / 3600;
        return $h >= 72 ? 'admin' : ($h >= 48 ? 'rot' : ($h >= 24 ? 'gelb' : ''));
    }

    /**
     * Der nächste beste Kontakt: zuerst wartende heiße Antworten (älteste zuerst), dann fällige Wiedervorlagen,
     * dann die höchste Priorität unter den ansprechbaren, noch nicht kontaktierten Betrieben.
     * @param list<int> $ueberspringen  in dieser Sitzung schon angesehene/übersprungene
     * @return array{id:int, grund:string}|null
     */
    public static function naechster(array $ueberspringen = []): ?array
    {
        $ohne = $ueberspringen ? ' AND f.id NOT IN (' . implode(',', array_map('intval', array_slice($ueberspringen, -200))) . ')' : '';
        $pos = "'" . implode("','", ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST']) . "'";
        $wege = [
            ['Antwort mit Interesse wartet', "SELECT f.id FROM akq_firmen f JOIN akq_antworten a ON a.firma_id = f.id WHERE a.klasse IN ($pos) AND a.erledigt = 0 AND f.gesperrt = 0$ohne ORDER BY a.created_at LIMIT 1"],
            ['Wiedervorlage fällig', 'SELECT f.id FROM akq_firmen f WHERE ' . self::ANSPRECHBAR . " AND (f.naechster_am <= CURDATE() OR f.wiedervorlage_am <= CURDATE())$ohne
                ORDER BY LEAST(COALESCE(f.naechster_am, '9999-12-31'), COALESCE(f.wiedervorlage_am, '9999-12-31')) LIMIT 1"],
            ['Höchste Priorität', 'SELECT f.id FROM akq_firmen f WHERE ' . self::ANSPRECHBAR . " AND f.prio_stufe IN ('jetzt','gut')
                AND f.kontakt_status IN ('neu','qualifiziert','vorlage','freigegeben')$ohne ORDER BY f.prio_score DESC, f.id LIMIT 1"],
        ];
        foreach ($wege as [$grund, $sql]) {
            $id = Db::wert($sql, [], null);
            if ($id !== null) { return ['id' => (int) $id, 'grund' => $grund]; }
        }
        return null;
    }
}
