<?php
declare(strict_types=1);
/* ==========================================================================
   Akquise — Verteiler fuer /app/akquise/…

   Aus index.php eingebunden, NACH Auth::nurAdmin() und der selbsttaetigen
   Einrichtung. Eigene Datei statt weiterer 400 Zeilen in index.php: Das
   Lead-System ist ein eigenes Modul und soll sich als Ganzes lesen, pruefen
   und notfalls abschalten lassen.

   Formulare schicken an url('akquise') mit tat=akq_…; jede Tat endet mit
   einer Weiterleitung (POST → Redirect → GET), nie mit einer Seite.
   ========================================================================== */

foreach (['Akquise', 'AkquiseScore', 'AkquiseGate', 'AkquiseText', 'AkquiseVersand', 'AkquiseWorker', 'AkquiseAnalyse', 'Ablauf'] as $k) {
    require_once __DIR__ . "/src/$k.php";
}

/** Die Tabellen fehlen noch (Migration nicht gelaufen) → freundlich statt weiss. */
$akqBereit = sicher(static fn() => Db::wert('SELECT COUNT(*) FROM akq_regeln') !== null, false);

if ($post) {
    Csrf::pruefen();
    $tat = (string) ($_POST['tat'] ?? '');
    $fid = (int) ($_POST['firma'] ?? 0);
    $zu = static fn(string $wohin) => weiter('akquise' . ($wohin !== '' ? '/' . $wohin : ''));
    try {
        switch ($tat) {
            case 'akq_lauf_anlegen':
                $land = strtoupper((string) ($_POST['land'] ?? ''));
                $ebene = (string) ($_POST['ebene'] ?? '');
                $gebiet = trim((string) ($_POST['gebiet'] ?? ''));
                if (!in_array($land, ['DE', 'IT'], true)) { throw new RuntimeException('Bitte Deutschland oder Italien wählen.'); }
                if (!in_array($ebene, ['region', 'kreis', 'stadt', 'plz'], true)) { throw new RuntimeException('Unbekannte Ebene.'); }
                if ($gebiet === '' || mb_strlen($gebiet) > 120) { throw new RuntimeException('Bitte ein Gebiet angeben.'); }
                $branchen = array_values(array_intersect((array) ($_POST['branchen'] ?? []), array_keys(Akquise::branchen())));
                $doppelt = Db::wert("SELECT id FROM akq_laeufe WHERE land = ? AND ebene = ? AND gebiet = ? AND status IN ('wartet','laeuft')",
                    [$land, $ebene, $gebiet], null);
                if ($doppelt !== null) { throw new RuntimeException('Für dieses Gebiet wartet schon ein Auftrag.'); }
                $lid = Db::insert('akq_laeufe', ['land' => $land, 'ebene' => $ebene, 'gebiet' => $gebiet,
                    'branchen' => $branchen ? json_encode($branchen) : null, 'angelegt_von' => Auth::name()]);
                Akquise::protokoll(null, 'lauf', 'Rechercheauftrag angelegt: ' . $land . ' / ' . $gebiet, ['branchen' => $branchen], $lid);
                $_SESSION['gut'] = 'Auftrag angelegt. Der Worker holt ihn beim nächsten Lauf ab.';
                $zu('recherche');

            case 'akq_lauf_abbrechen':
                Db::run("UPDATE akq_laeufe SET status = 'gestoppt', beendet_am = NOW() WHERE id = ? AND status IN ('wartet','laeuft')", [(int) $_POST['id']]);
                $zu('recherche');

            case 'akq_vorlage_regel':
                $vid = AkquiseVersand::regelVorlage($fid, (string) ($_POST['sprache'] ?? '') ?: null, (string) ($_POST['kanal'] ?? 'email'));
                $_SESSION['gut'] = 'Vorlage geschrieben — bitte lesen, anpassen und erst dann freigeben.';
                weiter('akquise/' . $fid . '#vorlage-' . $vid);

            case 'akq_vorlage_speichern':
                $vid = (int) ($_POST['vorlage'] ?? 0);
                $vid = AkquiseVersand::vorlageSpeichern($fid, null, (string) ($_POST['sprache'] ?? ''), (string) ($_POST['kanal'] ?? 'email'),
                    (string) ($_POST['betreff'] ?? ''), (string) ($_POST['text'] ?? ''), 'hand', $vid ?: null);
                $_SESSION['gut'] = 'Vorlage gespeichert.';
                weiter('akquise/' . $fid . '#vorlage-' . $vid);

            case 'akq_vorlage_freigeben':
                AkquiseVersand::freigeben((int) $_POST['vorlage']);
                $_SESSION['gut'] = 'Freigegeben. Verschickt wird erst mit dem nächsten Klick — und nur, wenn das Gate es erlaubt.';
                weiter('akquise/' . $fid);

            case 'akq_vorlage_verwerfen':
                AkquiseVersand::verwerfen((int) $_POST['vorlage']);
                weiter('akquise/' . $fid);

            case 'akq_senden':
                AkquiseVersand::senden((int) $_POST['vorlage'], (string) ($_POST['pruefvermerk'] ?? ''));
                $_SESSION['gut'] = 'Die E-Mail ist raus.';
                weiter('akquise/' . $fid);

            case 'akq_von_hand':
                AkquiseVersand::vonHand($fid, (string) ($_POST['kanal'] ?? ''), (string) ($_POST['begruendung'] ?? ''),
                    ((int) ($_POST['vorlage'] ?? 0)) ?: null);
                $_SESSION['gut'] = 'Vermerkt. Eine zweite Ansprache ist damit gesperrt.';
                weiter('akquise/' . $fid);

            case 'akq_antwort':
                $r = AkquiseVersand::antwortEintragen($fid, (string) ($_POST['von'] ?? ''), (string) ($_POST['betreff'] ?? ''),
                    (string) ($_POST['text'] ?? ''), (string) ($_POST['klasse'] ?? ''));
                $_SESSION['gut'] = 'Antwort eingetragen: ' . AkquiseText::ANTWORT_KLASSEN[$r['klasse']] . '.';
                weiter('akquise/' . $fid);

            case 'akq_sperren':
                AkquiseGate::sperren($fid, (string) ($_POST['grund'] ?? ''));
                $_SESSION['gut'] = 'Dauerhaft gesperrt. Diese Firma wird auf keinem Weg mehr angesprochen.';
                weiter('akquise/' . $fid);

            case 'akq_firma_speichern':
                $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$fid]);
                if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
                $neu = [
                    'notiz' => trim((string) ($_POST['notiz'] ?? '')) ?: null,
                    'ansprechpartner' => mb_substr(trim((string) ($_POST['ansprechpartner'] ?? '')), 0, 120) ?: null,
                    'email' => Akquise::normEmail($_POST['email'] ?? null),
                    'telefon' => Akquise::normTelefon($_POST['telefon'] ?? null, (string) $f['land']),
                    'einwilligung' => mb_substr(trim((string) ($_POST['einwilligung'] ?? '')), 0, 255) ?: null,
                    'bestandskunde' => !empty($_POST['bestandskunde']) ? 1 : 0,
                ];
                $b = (string) ($_POST['branche'] ?? '');
                if ($b !== '' && isset(Akquise::branchen()[$b])) { $neu['branche'] = $b; }
                $sp = (string) ($_POST['sprache'] ?? '');
                if (isset(AkquiseText::SPRACHEN[$sp])) { $neu['sprache'] = $sp; }
                $ks = (string) ($_POST['kontakt_status'] ?? '');
                if (isset(Akquise::KONTAKT_STATUS[$ks]) && $ks !== 'gesperrt' && (int) $f['gesperrt'] === 0) { $neu['kontakt_status'] = $ks; }
                Db::update('akq_firmen', $fid, $neu);
                // Einwilligung und Bestandskunde veraendern die Rechtslage -- das gehoert in die Pruefspur.
                if (($f['einwilligung'] ?? null) !== $neu['einwilligung'] || (int) $f['bestandskunde'] !== $neu['bestandskunde']) {
                    Events::pruefspur('akquise_rechtsgrundlage', 'akq_firmen', $fid,
                        ['einwilligung' => $f['einwilligung'], 'bestandskunde' => (int) $f['bestandskunde']],
                        ['einwilligung' => $neu['einwilligung'], 'bestandskunde' => $neu['bestandskunde']]);
                }
                Akquise::protokoll($fid, 'bearbeitet', 'Stammdaten bearbeitet');
                AkquiseGate::statusSpeichern($fid);
                $_SESSION['gut'] = 'Gespeichert.';
                weiter('akquise/' . $fid);

            case 'akq_befund_verwerfen':
                $b = Db::one('SELECT * FROM akq_befunde WHERE id = ? AND firma_id = ?', [(int) $_POST['befund'], $fid]);
                if (!$b) { throw new RuntimeException('Befund nicht gefunden.'); }
                Db::update('akq_befunde', (int) $b['id'], ['status' => 'VERWORFEN']);
                Akquise::protokoll($fid, 'befund', 'Befund verworfen: ' . $b['titel']);
                Akquise::neuBewerten((int) $b['audit_id']);
                weiter('akquise/' . $fid);

            case 'akq_neu_pruefen':
                Db::run("UPDATE akq_firmen SET audit_status = 'offen' WHERE id = ? AND url IS NOT NULL AND gesperrt = 0", [$fid]);
                Akquise::protokoll($fid, 'audit', 'Neue Prüfung angefordert');
                $_SESSION['gut'] = 'Vorgemerkt. Der Worker prüft die Seite beim nächsten Lauf.';
                weiter('akquise/' . $fid);

            case 'akq_analyse_anlegen':
                AkquiseAnalyse::anlegen($fid);
                weiter('akquise/' . $fid . '#analyse');

            case 'akq_analyse_umschalten':
                AkquiseAnalyse::umschalten((int) $_POST['analyse'], !empty($_POST['an']));
                weiter('akquise/' . $fid . '#analyse');

            case 'akq_regel_speichern':
                $rid = (int) ($_POST['regel'] ?? 0);
                $alt = Db::one('SELECT * FROM akq_regeln WHERE id = ?', [$rid]);
                if (!$alt) { throw new RuntimeException('Regel nicht gefunden.'); }
                $erg = (string) ($_POST['ergebnis'] ?? '');
                if (!isset(AkquiseGate::STATUS[$erg])) { throw new RuntimeException('Unbekanntes Ergebnis.'); }
                $neu = ['ergebnis' => $erg, 'begruendung' => trim((string) ($_POST['begruendung'] ?? '')) ?: $alt['begruendung'],
                        'quelle' => mb_substr(trim((string) ($_POST['quelle'] ?? '')), 0, 500) ?: null,
                        'geprueft_am' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($_POST['geprueft_am'] ?? '')) ? $_POST['geprueft_am'] : $alt['geprueft_am'],
                        'aktiv' => !empty($_POST['aktiv']) ? 1 : 0];
                Db::update('akq_regeln', $rid, $neu);
                Events::pruefspur('akquise_regel', 'akq_regeln', $rid, $alt, $neu);
                Akquise::protokoll(null, 'regel', 'Compliance-Regel geändert: ' . $alt['land'] . ' / ' . $alt['kanal'] . ' / ' . $alt['bedingung'] . ' → ' . $erg);
                // Alle Firmen dieses Landes neu einstufen -- eine Regel ohne Wirkung waere keine.
                foreach (Db::all('SELECT id FROM akq_firmen WHERE land = ? OR ? = \'*\'', [$alt['land'], $alt['land']]) as $z) {
                    AkquiseGate::statusSpeichern((int) $z['id']);
                }
                $_SESSION['gut'] = 'Regel gespeichert, Firmen neu eingestuft.';
                $zu('regeln');

            case 'akq_grenzen_speichern':
                $vorher = AkquiseGate::grenzen();
                foreach (['akq_limit_tag' => [0, 200], 'akq_limit_stunde' => [0, 50], 'akq_limit_domain_tage' => [1, 3650],
                          'akq_pause_sekunden' => [0, 86400], 'akq_fehler_grenze' => [1, 50], 'akq_bounce_grenze' => [1, 50]] as $k => [$min, $max]) {
                    if (isset($_POST[$k]) && is_numeric($_POST[$k])) { AkquiseGate::setzen($k, (string) max($min, min($max, (int) $_POST[$k]))); }
                }
                AkquiseGate::setzen('akq_versand_an', !empty($_POST['akq_versand_an']) ? '1' : '0');
                AkquiseGate::setzen('akq_konfigurator_link', !empty($_POST['akq_konfigurator_link']) ? '1' : '0');
                AkquiseGate::setzen('akq_absender_telefon', mb_substr(trim((string) ($_POST['akq_absender_telefon'] ?? '')), 0, 40));
                Events::pruefspur('akquise_grenzen', 'settings', null, $vorher, AkquiseGate::grenzen());
                $_SESSION['gut'] = 'Versandregeln gespeichert.';
                $zu('regeln');

            case 'akq_notbremse':
                AkquiseGate::notbremse(!empty($_POST['ziehen']));
                $_SESSION['gut'] = !empty($_POST['ziehen']) ? 'Notbremse gezogen. Es geht nichts mehr raus.' : 'Notbremse gelöst.';
                weiter((string) ($_POST['zurueck'] ?? 'akquise'));

            case 'akq_schluessel_neu':
                $_SESSION['akq_schluessel_einmal'] = AkquiseWorker::neuerSchluessel();
                $_SESSION['gut'] = 'Neuer Worker-Schlüssel erzeugt. Er wird genau einmal angezeigt — der alte gilt nicht mehr.';
                $zu('regeln');

            case 'akq_sperre_eintragen':
                AkquiseGate::eintragen((string) ($_POST['art'] ?? ''), (string) ($_POST['wert'] ?? ''), (string) ($_POST['grund'] ?? ''));
                $_SESSION['gut'] = 'In die Sperrliste eingetragen.';
                $zu('regeln#sperrliste');

            case 'akq_sperre_loeschen':
                $z = Db::one('SELECT * FROM akq_sperrliste WHERE id = ?', [(int) $_POST['id']]);
                if (!$z) { throw new RuntimeException('Eintrag nicht gefunden.'); }
                if (in_array($z['quelle'], ['abmeldung', 'antwort'], true)) {
                    throw new RuntimeException('Ein Widerspruch der Firma selbst lässt sich nicht löschen.');
                }
                Db::run('DELETE FROM akq_sperrliste WHERE id = ?', [(int) $z['id']]);
                Events::pruefspur('akquise_sperre_geloescht', 'akq_sperrliste', (int) $z['id'], $z, []);
                Akquise::protokoll($z['firma_id'] ? (int) $z['firma_id'] : null, 'sperrliste', 'Sperrlisten-Eintrag gelöscht: ' . $z['art'] . ' ' . $z['wert']);
                $_SESSION['gut'] = 'Eintrag gelöscht. Die Firma selbst bleibt gesperrt, bis du es dort änderst — mit Absicht.';
                $zu('regeln#sperrliste');

            default:
                throw new RuntimeException('Unbekannte Aktion.');
        }
    } catch (RuntimeException | InvalidArgumentException $e) {
        $_SESSION['fehler'] = $e->getMessage();
        weiter((string) ($_POST['zurueck'] ?? ('akquise' . ($fid ? '/' . $fid : ''))));
    }
}

/* ---------------------------- Ansichten --------------------------------- */

if (!$akqBereit) {
    ansicht('akquise_leer', []);
    exit;
}

$teil = $teile[1] ?? '';

if ($teil === 'bild') {
    /* Bildschirmfoto eines Audits -- aus dem gesperrten Ordner, nur angemeldet. */
    $aid = (int) ($teile[2] ?? 0);
    $art = ($teile[3] ?? '') === 'desktop' ? 'desktop' : 'mobil';
    $a = Db::one('SELECT * FROM akq_audits WHERE id = ?', [$aid]);
    $name = (string) ($a['screenshot_' . $art] ?? '');
    require_once __DIR__ . '/src/Ablage.php';
    $pfad = Ablage::ordner() . '/akquise/' . basename($name);
    if ($name === '' || !is_file($pfad)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg'));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($pfad);
    exit;
}

if ($teil === 'recherche') {
    ansicht('akquise_recherche', [
        'laeufe' => Db::all('SELECT * FROM akq_laeufe ORDER BY id DESC LIMIT 100'),
        'branchen' => Akquise::branchen(),
    ]);
    exit;
}

if ($teil === 'regeln') {
    $einmal = $_SESSION['akq_schluessel_einmal'] ?? null;
    unset($_SESSION['akq_schluessel_einmal']);
    ansicht('akquise_regeln', [
        'regeln' => Db::all("SELECT * FROM akq_regeln ORDER BY land, FIELD(kanal,'email','whatsapp','kontaktformular','brief','telefon'), bedingung"),
        'grenzen' => AkquiseGate::grenzen(),
        'konfigurator' => AkquiseGate::einstellung('akq_konfigurator_link', '1') === '1',
        'telefon' => AkquiseGate::einstellung('akq_absender_telefon', ''),
        'sperrliste' => Db::all('SELECT * FROM akq_sperrliste ORDER BY id DESC LIMIT 500'),
        'schluesselDa' => AkquiseWorker::schluessel() !== '',
        'schluesselEinmal' => $einmal,
        'heute' => (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE status = 'gesendet' AND created_at >= CURDATE()"),
        'blockiert' => (int) Db::wert("SELECT COUNT(*) FROM akq_versand WHERE status = 'blockiert' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
    ]);
    exit;
}

if ($teil === 'protokoll') {
    ansicht('akquise_protokoll', [
        'eintraege' => Db::all('SELECT p.*, f.name AS firma FROM akq_protokoll p LEFT JOIN akq_firmen f ON f.id = p.firma_id ORDER BY p.id DESC LIMIT 300'),
    ]);
    exit;
}

if ($teil !== '' && ctype_digit($teil)) {
    $fid = (int) $teil;
    $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$fid]);
    if (!$f) { $_SESSION['fehler'] = 'Diese Firma gibt es nicht.'; weiter('akquise'); }
    $audit = Akquise::letzterAudit($fid);
    $befunde = $audit ? Akquise::befunde((int) $audit['id']) : [];
    $gates = [];
    foreach (array_keys(AkquiseGate::KANAELE) as $k) { $gates[$k] = AkquiseGate::pruefen($f, $k); }
    ansicht('akquise_firma', [
        'f' => $f,
        'audit' => $audit,
        'befunde' => $befunde,
        'top' => array_slice(AkquiseScore::topBefunde($befunde), 0, 3),
        'teile' => $audit && $audit['teilwerte'] ? (json_decode((string) $audit['teilwerte'], true) ?: []) : [],
        'messwerte' => $audit && $audit['messwerte'] ? (json_decode((string) $audit['messwerte'], true) ?: []) : [],
        'ki' => $audit && $audit['ki'] ? (json_decode((string) $audit['ki'], true) ?: []) : [],
        'vorlagen' => Db::all('SELECT * FROM akq_vorlagen WHERE firma_id = ? ORDER BY id DESC', [$fid]),
        'versand' => Db::all('SELECT * FROM akq_versand WHERE firma_id = ? ORDER BY id DESC', [$fid]),
        'antworten' => Db::all('SELECT * FROM akq_antworten WHERE firma_id = ? ORDER BY id DESC', [$fid]),
        'protokoll' => Db::all('SELECT * FROM akq_protokoll WHERE firma_id = ? ORDER BY id DESC LIMIT 100', [$fid]),
        'gates' => $gates,
        'sperre' => AkquiseGate::versandSperre($f),
        'analysen' => Db::all('SELECT * FROM akq_analysen WHERE firma_id = ? ORDER BY id DESC', [$fid]),
        'audits' => Db::all('SELECT id, beendet_am, score, status FROM akq_audits WHERE firma_id = ? ORDER BY id DESC LIMIT 10', [$fid]),
    ]);
    exit;
}

$filter = array_intersect_key($_GET, array_flip(['land', 'region', 'kreis', 'stadt', 'branche', 'kontakt', 'compliance', 'audit',
                                                  'stufe', 'score_min', 'von', 'bis', 'q', 'sort', 'gesperrte']));
ansicht('akquise', [
    'liste' => Akquise::liste($filter, max(1, (int) ($_GET['seite'] ?? 1))),
    'filter' => $filter,
    'werte' => Akquise::filterWerte(),
    'kz' => Akquise::kennzahlen(),
    'grenzen' => AkquiseGate::grenzen(),
    'wartend' => (int) Db::wert("SELECT COUNT(*) FROM akq_laeufe WHERE status IN ('wartet','laeuft')"),
]);
exit;
