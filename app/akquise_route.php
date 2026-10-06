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

foreach (['Akquise', 'AkquiseScore', 'AkquiseGate', 'AkquiseText', 'AkquiseVersand', 'AkquiseWorker', 'AkquiseAnalyse', 'AkquiseEinwilligung', 'Ablauf'] as $k) {
    require_once __DIR__ . "/src/$k.php";
}

/** Die Tabellen fehlen noch (Migration nicht gelaufen) → freundlich statt weiss. */
$akqBereit = sicher(static fn() => Db::wert('SELECT COUNT(*) FROM akq_regeln') !== null, false);

if ($post) {
    Csrf::pruefen();
    $tat = (string) ($_POST['tat'] ?? '');
    $fid = (int) ($_POST['firma'] ?? 0);
    $zu = static fn(string $wohin) => weiter('akquise' . ($wohin !== '' ? '/' . $wohin : ''));
    /* Zurueck dorthin, wo der Knopf stand (Reiter „Alle Befunde", „Verlauf") --
       aber nur innerhalb der Akquise. */
    $zurueck = static function (string $sonst): never {
        $z = (string) ($_POST['zurueck'] ?? '');
        weiter(preg_match('~^akquise(/[0-9a-z_/-]*)?(\?ansicht=[a-z]+)?(#[a-z0-9_-]+)?$~', $z) ? $z : $sonst);
    };
    try {
        switch ($tat) {
            case 'akq_lauf_anlegen':
                $land = strtoupper((string) ($_POST['land'] ?? ''));
                $ebene = (string) ($_POST['ebene'] ?? '');
                $gebiet = trim((string) ($_POST['gebiet'] ?? ''));
                if (!in_array($land, ['DE', 'IT'], true)) { throw new RuntimeException('Bitte Deutschland oder Italien wählen.'); }
                if (!in_array($ebene, ['auto', 'region', 'kreis', 'stadt', 'plz'], true)) { throw new RuntimeException('Unbekannte Ebene.'); }
                if ($gebiet === '' || mb_strlen($gebiet) > 120) { throw new RuntimeException('Bitte ein Gebiet angeben.'); }
                $branchen = array_values(array_intersect((array) ($_POST['branchen'] ?? []), array_keys(Akquise::branchen())));
                $quelle = ($_POST['quelle'] ?? 'osm') === 'overture' ? 'overture' : 'osm';
                if ($quelle === 'overture' && in_array($ebene, ['auto', 'plz'], true)) { $ebene = 'kreis'; }   // Overture lädt Flächen: Gemeinde, Provinz, Region
                $doppelt = Db::wert("SELECT id FROM akq_laeufe WHERE land = ? AND ebene = ? AND gebiet = ? AND quelle = ? AND status IN ('wartet','laeuft')",
                    [$land, $ebene, $gebiet, $quelle], null);
                if ($doppelt !== null) { throw new RuntimeException('Für dieses Gebiet wartet schon ein Auftrag.'); }
                $lid = Db::insert('akq_laeufe', ['land' => $land, 'ebene' => $ebene, 'gebiet' => $gebiet, 'quelle' => $quelle,
                    'branchen' => $branchen ? json_encode($branchen) : null, 'angelegt_von' => Auth::name()]);
                Akquise::protokoll(null, 'lauf', 'Rechercheauftrag angelegt: ' . $land . ' / ' . $gebiet, ['branchen' => $branchen], $lid);
                $_SESSION['gut'] = 'Auftrag angelegt. Der Worker holt ihn beim nächsten Lauf ab.';
                $zu('recherche');

            case 'akq_lauf_abbrechen':
                Db::run("UPDATE akq_laeufe SET status = 'gestoppt', beendet_am = NOW() WHERE id = ? AND status IN ('wartet','laeuft')", [(int) $_POST['id']]);
                $zu('recherche');

            case 'akq_vorlage_regel':
                $vid = AkquiseVersand::regelVorlage($fid, (string) ($_POST['sprache'] ?? '') ?: null, ((string) ($_POST['kanal'] ?? '')) ?: null);
                $_SESSION['gut'] = 'Text geschrieben — bitte lesen, bei Bedarf anpassen und dann freigeben.';
                weiter('akquise/' . $fid . '#kontakt');

            case 'akq_vorlage_speichern':
                $vid = (int) ($_POST['vorlage'] ?? 0);
                $vid = AkquiseVersand::vorlageSpeichern($fid, null, (string) ($_POST['sprache'] ?? ''), (string) ($_POST['kanal'] ?? 'email'),
                    (string) ($_POST['betreff'] ?? ''), (string) ($_POST['text'] ?? ''), 'hand', $vid ?: null);
                $_SESSION['gut'] = 'Gespeichert und geprüft.';
                weiter('akquise/' . $fid . '#kontakt');

            case 'akq_vorlage_freigeben':
                AkquiseVersand::freigeben((int) $_POST['vorlage']);
                $fk = (string) Db::wert('SELECT kanal FROM akq_vorlagen WHERE id = ?', [(int) $_POST['vorlage']], '');
                if ($fk === 'brief') {
                    /* Der Brief traegt einen QR-Code zur Analyse-Seite. Die
                       Freigabe des Briefs ist deshalb auch ihre Freigabe --
                       so steht es in der Rueckfrage vor dem Knopf. */
                    $an = AkquiseAnalyse::anlegen($fid);
                    if ((int) ($an['aktiv'] ?? 0) !== 1) { AkquiseAnalyse::umschalten((int) $an['id'], true); }
                    $_SESSION['gut'] = 'Freigegeben. Jetzt „Brief drucken“ — der QR-Code führt zur persönlichen Analyse-Seite.';
                } else {
                    $_SESSION['gut'] = 'Freigegeben. Verschickt wird erst mit dem nächsten Klick — und nur, wenn es erlaubt ist.';
                }
                weiter('akquise/' . $fid . '#kontakt');

            case 'akq_brief_verschickt':
                AkquiseVersand::briefVerschickt($fid, (int) $_POST['vorlage'], !empty($_POST['bestaetigt']));
                $_SESSION['gut'] = 'Vermerkt: Brief ist unterwegs. Meldet sich jemand oder öffnet die Analyse-Seite, siehst du es hier.';
                weiter('akquise/' . $fid);

            case 'akq_anruf':
                /* Der Pruefvermerk fuer den Anruf (B2B, mutmassliche Einwilligung):
                   Uwe bestaetigt ihn mit einem Haken, die Notiz kommt dazu. */
                $notiz = trim((string) ($_POST['notiz'] ?? ''));
                $ergebnis = (string) ($_POST['ergebnis'] ?? '');
                $vermerk = $ergebnis === 'nicht_erreicht' ? $notiz : (!empty($_POST['anlass'])
                    ? 'Anruf: Geschäftsnummer öffentlich, konkreter Anlass (Befunde zur eigenen Website), kein Widerspruch bekannt.'
                        . ($notiz !== '' ? ' Notiz: ' . $notiz : '')
                    : $notiz);
                $_SESSION['gut'] = AkquiseVersand::anrufErgebnis($fid, $ergebnis, $vermerk, (string) ($_POST['email'] ?? ''));
                weiter('akquise/' . $fid);

            case 'akq_anruf_nicht_erreicht':
                $_SESSION['gut'] = AkquiseVersand::anrufErgebnis($fid, 'nicht_erreicht', trim((string) ($_POST['notiz'] ?? '')));
                weiter('akquise/' . $fid);

            case 'akq_suchen':
                /* „Jetzt suchen": ein Ort, die Ebene sucht sich der Worker selbst
                   (Gemeinde, sonst Kreis/Provinz, sonst Region). */
                $land = strtoupper((string) ($_POST['land'] ?? 'IT'));
                $gebiet = trim((string) ($_POST['gebiet'] ?? ''));
                if (!in_array($land, ['DE', 'IT'], true) || $gebiet === '' || mb_strlen($gebiet) > 120) {
                    throw new RuntimeException('Bitte einen Ort eintragen, zum Beispiel „Sciacca“.');
                }
                $branchen = array_values(array_intersect((array) ($_POST['branchen'] ?? []), array_keys(Akquise::branchen())));
                $doppelt = Db::wert("SELECT id FROM akq_laeufe WHERE land = ? AND gebiet = ? AND status IN ('wartet','laeuft')", [$land, $gebiet], null);
                if ($doppelt !== null) { throw new RuntimeException('„' . $gebiet . '“ steht schon auf der Liste für heute Nacht.'); }
                $lid = Db::insert('akq_laeufe', ['land' => $land, 'ebene' => 'auto', 'gebiet' => $gebiet,
                    'branchen' => $branchen ? json_encode($branchen) : null, 'angelegt_von' => Auth::name()]);
                Akquise::protokoll(null, 'lauf', 'Suche angelegt: ' . $gebiet . ' (' . $land . ')', ['branchen' => $branchen], $lid);
                $_SESSION['gut'] = '„' . $gebiet . '“ ist vorgemerkt. Dein PC fängt in den nächsten fünf Minuten an zu suchen (wenn er an ist), sonst beim nächsten Start.';
                weiter('akquise');

            case 'akq_vorlage_verwerfen':
                AkquiseVersand::verwerfen((int) $_POST['vorlage']);
                weiter('akquise/' . $fid);

            case 'akq_senden':
                AkquiseVersand::senden((int) $_POST['vorlage'], (string) ($_POST['pruefvermerk'] ?? ''));
                $_SESSION['gut'] = AkquiseGate::testbetrieb() ? 'Testbetrieb: Die E-Mail wurde nur simuliert — nichts ging raus. Umschalten unter Regeln & Versand.' : 'Die E-Mail ist raus.';
                weiter('akquise/' . $fid);

            case 'akq_von_hand':
                AkquiseVersand::vonHand($fid, (string) ($_POST['kanal'] ?? ''), (string) ($_POST['begruendung'] ?? ''),
                    ((int) ($_POST['vorlage'] ?? 0)) ?: null);
                $_SESSION['gut'] = 'Vermerkt. Eine zweite Ansprache ist damit gesperrt.';
                weiter('akquise/' . $fid);

            /* Ansprechen von Hand (29.09.2026, K2/K3) */
            case 'akq_zugestimmt':
                $r = AkquiseEinwilligung::muendlich($fid, (string) ($_POST['weg'] ?? ''), (string) ($_POST['person'] ?? ''),
                    (string) ($_POST['email'] ?? ''), (string) ($_POST['whatsapp'] ?? ''), !empty($_POST['per_email']), !empty($_POST['per_whatsapp']),
                    !empty($_POST['vorgelesen']), !empty($_POST['bereich']));
                $_SESSION['gut'] = 'Zustimmung gespeichert. ' . implode(' und ', $r['wege']) . (count($r['wege']) > 1 ? ' sind' : ' ist') . ' jetzt frei, die Folge-Mails laufen automatisch'
                    . ($r['bereich'] !== null ? ', und der persönliche Bereich ist per Mail unterwegs.' : '.');
                weiter('akquise/' . $fid . '#ansprechen');

            /* Starten und Stoppen (29.09.2026): der PC holt sich das binnen fünf Minuten ab */
            case 'akq_pruefung_start':
                require_once __DIR__ . '/src/AkquiseSteuerung.php';
                AkquiseSteuerung::pruefungStarten();
                $_SESSION['gut'] = 'Prüfung gestartet — dein PC fängt in den nächsten fünf Minuten an (wenn er an ist).';
                weiter('akquise#steuerung');

            case 'akq_pruefung_stop':
                require_once __DIR__ . '/src/AkquiseSteuerung.php';
                AkquiseSteuerung::pruefungStoppen();
                $_SESSION['gut'] = 'Prüfung gestoppt — der PC hört nach der Website auf, die er gerade prüft, und prüft auch nachts nicht, bis du wieder startest.';
                weiter('akquise#steuerung');

            case 'akq_suche_stop':
                require_once __DIR__ . '/src/AkquiseSteuerung.php';
                $n = AkquiseSteuerung::sucheStoppen();
                $_SESSION['gut'] = $n > 0 ? 'Suche gestoppt — was schon gefunden wurde, bleibt in der Liste.' : 'Es lief keine Suche.';
                weiter('akquise#steuerung');

            case 'akq_suche_an':
                require_once __DIR__ . '/src/AkquiseSteuerung.php';
                AkquiseSteuerung::sucheEinschalten();
                $_SESSION['gut'] = 'Betriebe suchen ist eingeschaltet — wartende Aufträge startet dein PC in den nächsten fünf Minuten.';
                weiter('akquise#steuerung');

            /* Aussortieren (06.10.2026, Uwe: „die Betriebe, die keine E-Mail haben und kein WhatsApp, lösche raus“). */
            case 'akq_aussortieren':
                $n = Akquise::aussortieren();
                Events::pruefspur('aussortieren', 'akq_firmen', 0, [], ['geloescht' => $n]);
                $_SESSION['gut'] = $n > 0 ? $n . ' Betrieb' . ($n === 1 ? '' : 'e') . ' ohne E-Mail und ohne WhatsApp gelöscht. Neue kommen gar nicht erst in die Liste.'
                                          : 'Nichts zu löschen — jeder Betrieb in der Liste hat E-Mail oder WhatsApp, oder wartet noch auf die Prüfung seiner Website.';
                weiter('akquise');

            /* Kommunikationsstatus E-Mail (06.10.2026, Uwe): dokumentieren statt pauschal sperren. */
            case 'akq_mail_grund':
                require_once __DIR__ . '/src/AkquiseMail.php';
                $r = AkquiseMail::grundDokumentieren($fid, $_POST);
                $_SESSION['gut'] = 'Versandgrund dokumentiert. Status: ' . AkquiseMail::STATUS[$r['status']][2]
                    . ($r['freigabe'] ? ($r['werbung'] ? ' — einzelner Versand von Hand möglich, auch Werbung.' : ' — einzelne Nachricht von Hand möglich, keine Werbung.') : ' — eine Freigabe braucht noch eine Prüfung.');
                weiter('akquise/' . $fid . '#mailstatus');
            case 'akq_mail_pruefung':
                require_once __DIR__ . '/src/AkquiseMail.php';
                AkquiseMail::pruefungAnfordern($fid, (string) ($_POST['notiz'] ?? ''));
                $_SESSION['gut'] = 'Zur Prüfung markiert.';
                weiter('akquise/' . $fid . '#mailstatus');
            case 'akq_mail_zurueck':
                require_once __DIR__ . '/src/AkquiseMail.php';
                AkquiseMail::freigabeZuruecknehmen($fid, (string) ($_POST['notiz'] ?? ''));
                $_SESSION['gut'] = 'Versandfreigabe zurückgenommen.';
                weiter('akquise/' . $fid . '#mailstatus');
            case 'akq_mail_geprueft':
                require_once __DIR__ . '/src/AkquiseMail.php';
                AkquiseMail::adresseGeprueft($fid, !empty($_POST['ja']));
                $_SESSION['gut'] = !empty($_POST['ja']) ? 'Adresse als bestätigt markiert.' : 'Bestätigung entfernt.';
                weiter('akquise/' . $fid . '#mailstatus');
            case 'akq_mail_nicht':
                require_once __DIR__ . '/src/AkquiseMail.php';
                AkquiseMail::nichtKontaktierenSetzen($fid, (string) ($_POST['grund'] ?? ''), (string) ($_POST['notiz'] ?? ''));
                $_SESSION['gut'] = '„Nicht kontaktieren“ gesetzt. Werbeversand bleibt auf allen Wegen blockiert.';
                weiter('akquise/' . $fid . '#mailstatus');
            case 'akq_mail_nicht_aufheben':
                require_once __DIR__ . '/src/AkquiseMail.php';
                AkquiseMail::nichtKontaktierenAufheben($fid, (string) ($_POST['begruendung'] ?? ''));
                $_SESSION['gut'] = '„Nicht kontaktieren“ aufgehoben und protokolliert.';
                weiter('akquise/' . $fid . '#mailstatus');

            case 'akq_an_partner':
                require_once __DIR__ . '/src/PartnerAnrufliste.php';
                $r = PartnerAnrufliste::uebergeben((array) ($_POST['firmen'] ?? []), (int) ($_POST['partner'] ?? 0), (string) ($_POST['vermerk'] ?? ''), Auth::name() ?: 'Uwe');
                $_SESSION['gut'] = $r['ok'] . ' Betrieb' . ($r['ok'] === 1 ? '' : 'e') . ' übergeben — der Partner sieht sie in seiner Anrufliste.'
                    . ($r['weg'] ? ' Nicht übergeben: ' . implode('; ', array_map(static fn($n, $g) => $n . ' (' . $g . ')', array_keys(array_slice($r['weg'], 0, 5, true)), array_slice($r['weg'], 0, 5, true))) . (count($r['weg']) > 5 ? ' …' : '') . '.' : '');
                weiter('akquise#anrufliste');

            case 'akq_manuell':
                require_once __DIR__ . '/src/AkquiseAnsprechen.php';
                $ok = AkquiseAnsprechen::vermerken($fid, (string) ($_POST['kanal'] ?? ''));
                if (!empty($_POST['still'])) { http_response_code($ok ? 204 : 200); exit; }
                $_SESSION['gut'] = $ok ? 'Vermerkt.' : 'Schon vermerkt.';
                weiter('akquise/' . $fid . '#ansprechen');

            case 'akq_kein_interesse':
                AkquiseVersand::antwortEintragen($fid, trim((string) ($_POST['person'] ?? '')) ?: 'Betrieb', 'Ansprechen', 'Kein Interesse (' . (($_POST['weg'] ?? '') === 'besuch' ? 'Besuch' : 'Anruf') . ').', 'NOT_INTERESTED');
                $_SESSION['gut'] = 'Vermerkt und gesperrt — dieser Betrieb wird nie mehr angesprochen.';
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
                if ($neu['email'] !== null && $neu['email'] !== ($f['email'] ?? null)) {   // Herkunft der Adresse (06.10.2026)
                    $neu['email_source'] = 'Von Hand eingetragen von ' . (Auth::name() ?: 'Verwaltung') . ' am ' . date('d.m.Y');
                    $neu['email_verified'] = 0;
                }
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
                $zurueck('akquise/' . $fid);

            case 'akq_neu_pruefen':
                Db::run("UPDATE akq_firmen SET audit_status = 'offen' WHERE id = ? AND url IS NOT NULL AND gesperrt = 0", [$fid]);
                Akquise::protokoll($fid, 'audit', 'Neue Prüfung angefordert');
                $_SESSION['gut'] = 'Vorgemerkt. Der Worker prüft die Seite beim nächsten Lauf.';
                weiter('akquise/' . $fid);

            case 'akq_briefdienst_speichern':
                require_once __DIR__ . '/src/AkquiseBriefdienst.php';
                if (!empty($_POST['loeschen'])) { AkquiseBriefdienst::tokenSetzen(''); }
                elseif (trim((string) ($_POST['token'] ?? '')) !== '') { AkquiseBriefdienst::tokenSetzen((string) $_POST['token']); }
                AkquiseBriefdienst::testSetzen(!empty($_POST['test']));
                if (isset(AkquiseBriefdienst::PRODUKTE[(string) ($_POST['produkt'] ?? '')])) { AkquiseBriefdienst::produktSetzen((string) $_POST['produkt']); }
                Events::pruefspur('akquise_briefdienst', 'settings', 0, [], ['test' => !empty($_POST['test']), 'token_neu' => trim((string) ($_POST['token'] ?? '')) !== '', 'geloescht' => !empty($_POST['loeschen'])]);
                $_SESSION['gut'] = 'Briefdienst gespeichert' . (!empty($_POST['test']) ? ' (Testbetrieb).' : ' — ECHTBETRIEB.');
                $zu('regeln#briefdienst');

            case 'akq_brief_vorschau':
                require_once __DIR__ . '/src/AkquiseBriefdienst.php';
                if (empty($_POST['bestaetigt'])) { throw new RuntimeException('Bitte bestätigen, dass kein Werbewiderspruch bekannt ist.'); }
                AkquiseBriefdienst::vorschau($fid);
                $_SESSION['gut'] = 'Vorschau vom Briefdienst ist da — Preis und Blatt prüfen, dann verschicken.';
                weiter('akquise/' . $fid . '#kontakt');

            case 'akq_brief_senden':
                require_once __DIR__ . '/src/AkquiseBriefdienst.php';
                AkquiseBriefdienst::senden((int) ($_POST['brief'] ?? 0), 'kein Werbewiderspruch bekannt, Widerspruchshinweis im Brief');
                $_SESSION['gut'] = AkquiseBriefdienst::test() ? 'Test-Brief bestätigt (Sandbox — nichts verschickt).' : 'Brief ist beauftragt — Poste Italiane druckt und verschickt ihn.';
                weiter('akquise/' . $fid . '#kontakt');

            case 'akq_brief_verwerfen':
                require_once __DIR__ . '/src/AkquiseBriefdienst.php';
                AkquiseBriefdienst::verwerfen((int) ($_POST['brief'] ?? 0));
                weiter('akquise/' . $fid . '#kontakt');

            case 'akq_briefserie_vorbereiten':
                require_once __DIR__ . '/src/AkquiseBriefserie.php';
                if (empty($_POST['bestaetigt'])) { throw new RuntimeException('Bitte bestätigen, dass bei keinem der Betriebe ein Werbewiderspruch bekannt ist.'); }
                $bsR = AkquiseBriefserie::vorbereiten(array_map('intval', (array) ($_POST['firmen'] ?? [])));
                $_SESSION['gut'] = $bsR['ok'] . ' Vorschau' . ($bsR['ok'] === 1 ? '' : 'en') . ' vom Briefdienst da — Preise und Blätter prüfen, dann alle zusammen verschicken.';
                if ($bsR['fehler']) { $_SESSION['fehler'] = count($bsR['fehler']) . ' nicht vorbereitet: ' . mb_substr(implode(' · ', array_map(static fn($id, $g) => '#' . $id . ' ' . $g, array_keys($bsR['fehler']), $bsR['fehler'])), 0, 600); }
                $zu('briefe');

            case 'akq_briefserie_senden':
                require_once __DIR__ . '/src/AkquiseBriefserie.php';
                $bsS = AkquiseBriefserie::senden(array_map('intval', (array) ($_POST['briefe'] ?? [])), 'Serie: kein Werbewiderspruch bekannt, Widerspruchshinweis im Brief');
                $_SESSION['gut'] = $bsS['verschickt'] . ' Brief' . ($bsS['verschickt'] === 1 ? '' : 'e') . (AkquiseBriefdienst::test() ? ' bestätigt (TEST — nichts verschickt)' : ' beauftragt')
                    . ' · zusammen ' . number_format($bsS['summe'] / 100, 2, ',', '.') . ' €.';
                if ($bsS['fehler']) { $_SESSION['fehler'] = count($bsS['fehler']) . ' nicht verschickt: ' . mb_substr(implode(' · ', $bsS['fehler']), 0, 600); }
                $zu('briefe');

            case 'akq_briefserie_verwerfen':
                require_once __DIR__ . '/src/AkquiseBriefserie.php';
                AkquiseBriefserie::verwerfen(array_map('intval', (array) ($_POST['briefe'] ?? [])));
                $_SESSION['gut'] = 'Vorschauen verworfen — nichts wurde verschickt.';
                $zu('briefe');

            case 'akq_postfach_speichern':
                require_once __DIR__ . '/src/AkquisePostfach.php';
                AkquisePostfach::zugangSetzen((string) ($_POST['host'] ?? ''), (int) ($_POST['port'] ?? 993), (string) ($_POST['nutzer'] ?? ''),
                    (string) ($_POST['passwort'] ?? ''), (string) ($_POST['ordner'] ?? 'INBOX'));
                Events::pruefspur('akquise_postfach', 'settings', 0, [], ['host' => (string) ($_POST['host'] ?? ''), 'passwort_neu' => (string) ($_POST['passwort'] ?? '') !== '']);
                $_SESSION['gut'] = trim((string) ($_POST['host'] ?? '')) === '' ? 'Postfach-Zugang entfernt.' : 'Postfach gespeichert. Mit „Jetzt prüfen“ siehst du sofort, ob die Anmeldung klappt.';
                $zu('regeln#postfach');

            case 'akq_postfach_jetzt':
                require_once __DIR__ . '/src/AkquisePostfach.php';
                $pfR = AkquisePostfach::lauf(true);
                if (isset($pfR['fehler'])) { $_SESSION['fehler'] = 'Postfach: ' . $pfR['fehler']; }
                else { $_SESSION['gut'] = 'Postfach gelesen: ' . (int) ($pfR['gelesen'] ?? 0) . ' neue Mails, davon ' . (int) ($pfR['zugeordnet'] ?? 0) . ' Antworten von angeschriebenen Betrieben.'; }
                $zu('regeln#postfach');

            case 'akq_wochenziel':
                require_once __DIR__ . '/src/AkquiseAuswertung.php';
                AkquiseAuswertung::wochenzielSetzen((int) ($_POST['ziel'] ?? 0));
                $_SESSION['gut'] = 'Wochenziel gespeichert.';
                weiter('akquise/auswertung');

            case 'akq_schalter_speichern':
                $vorher = ['testbetrieb' => AkquiseGate::testbetrieb()];
                foreach (array_keys(AkquiseGate::SCHALTER) as $k) {
                    $vorher[$k] = AkquiseGate::schalterSelbst($k);
                    AkquiseGate::schalterSetzen($k, !empty($_POST['schalter'][$k]));
                }
                $test = ($_POST['testbetrieb'] ?? '1') !== '0';
                if ($test !== $vorher['testbetrieb']) { AkquiseGate::testbetriebSetzen($test); }
                Events::pruefspur('akquise_schalter', 'settings', null, $vorher, ['testbetrieb' => $test] + array_map(static fn($k) => !empty($_POST['schalter'][$k]), array_combine(array_keys(AkquiseGate::SCHALTER), array_keys(AkquiseGate::SCHALTER))));
                $_SESSION['gut'] = 'Schalter gespeichert.' . ($test ? ' Testbetrieb: Akquise-Mails werden nur simuliert.' : ' Echtbetrieb: freigegebene Akquise-Mails gehen wirklich raus.');
                $zu('regeln#schalter');

            case 'akq_folge_vorlage_speichern':
                require_once __DIR__ . '/src/AkquiseFolge.php';
                AkquiseFolge::vorlageSpeichern((int) ($_POST['id'] ?? 0), (string) ($_POST['betreff'] ?? ''), (string) ($_POST['text'] ?? ''));
                $_SESSION['gut'] = 'Text gespeichert — er ist wieder Entwurf, bis du ihn freigibst.';
                weiter('akquise/folgen#v' . (int) ($_POST['id'] ?? 0));

            case 'akq_folge_freigeben':
                require_once __DIR__ . '/src/AkquiseFolge.php';
                AkquiseFolge::freigeben((int) ($_POST['id'] ?? 0));
                $_SESSION['gut'] = 'Text freigegeben — er geht ab jetzt automatisch raus, wenn der Schritt fällig ist.';
                weiter('akquise/folgen#v' . (int) ($_POST['id'] ?? 0));

            case 'akq_folge_pausieren':
            case 'akq_folge_fortsetzen':
            case 'akq_folge_beenden':
                require_once __DIR__ . '/src/AkquiseFolge.php';
                $foId = (int) ($_POST['folge'] ?? 0);
                match ($tat) {
                    'akq_folge_pausieren' => AkquiseFolge::pausieren($foId),
                    'akq_folge_fortsetzen' => AkquiseFolge::fortsetzen($foId),
                    default => AkquiseFolge::beenden($foId),
                };
                weiter('akquise/folgen#laufend');

            /* WhatsApp von Hand (02.10.2026): ein Tipp vermerkt den Schritt und öffnet WhatsApp mit dem Text. */
            case 'akq_folge_wa_hand':
                require_once __DIR__ . '/src/AkquiseFolge.php';
                try { $waAdr = AkquiseFolge::handGesendet((int) ($_POST['folge'] ?? 0)); }
                catch (Throwable $e) { $_SESSION['fehler'] = $e->getMessage(); weiter('akquise/folgen#whatsapp'); }
                header('Location: ' . $waAdr);
                exit;

            case 'akq_folge_wa_stop':
                require_once __DIR__ . '/src/AkquiseFolge.php';
                $foS = Db::one('SELECT firma_id FROM akq_folgen WHERE id = ?', [(int) ($_POST['folge'] ?? 0)]);
                if ($foS) {
                    AkquiseGate::sperren((int) $foS['firma_id'], 'Per WhatsApp mit STOP geantwortet', 'whatsapp');
                    AkquiseFolge::beenden((int) ($_POST['folge'] ?? 0), 'STOP per WhatsApp');
                    $_SESSION['gut'] = 'Gesperrt — dieser Betrieb bekommt nichts mehr, auf keinem Weg.';
                }
                weiter('akquise/folgen#whatsapp');

            case 'akq_termin_einstellungen':
                require_once __DIR__ . '/src/AkquiseTermin.php';
                AkquiseTermin::einstellungenSetzen((array) ($_POST['plan'] ?? []), (int) ($_POST['dauer'] ?? 30), (int) ($_POST['vorlauf'] ?? 18),
                    (int) ($_POST['tage'] ?? 21), (string) ($_POST['gesperrt'] ?? ''), !empty($_POST['an']));
                $_SESSION['gut'] = 'Sprechzeiten gespeichert — ' . array_sum(array_map('count', AkquiseTermin::freie())) . ' freie Zeiten sind jetzt buchbar.';
                weiter('akquise/termine#zeiten');

            case 'akq_termin_absagen':
            case 'akq_termin_erledigt':
                require_once __DIR__ . '/src/AkquiseTermin.php';
                if ($tat === 'akq_termin_absagen') { AkquiseTermin::absagen((int) ($_POST['termin'] ?? 0), 'vecom'); $_SESSION['gut'] = 'Termin abgesagt — die Nachricht ist raus.'; }
                else { AkquiseTermin::erledigt((int) ($_POST['termin'] ?? 0)); }
                weiter('akquise/termine#kommend');

            case 'akq_pipeline':
                $pfId = (int) ($_POST['firma'] ?? 0);
                Akquise::pipelineSetzen($pfId, (string) ($_POST['wert'] ?? ''));
                $_SESSION['gut'] = ($_POST['wert'] ?? '') !== '' ? 'Stand gesetzt: ' . Akquise::PIPELINE_HAND[(string) $_POST['wert']] . '.' : 'Stand zurückgesetzt.';
                weiter('akquise/' . $pfId);

            case 'akq_brief_schalten':
                AkquiseGate::briefSchalten(!empty($_POST['an']));
                Events::pruefspur('akquise_brief_schalter', 'settings', null, [], ['akq_brief_an' => !empty($_POST['an']) ? '1' : '0']);
                $_SESSION['gut'] = !empty($_POST['an']) ? 'Briefe sind wieder eingeschaltet.' : 'Briefe sind ausgeschaltet — es bleibt bei E-Mail mit Einwilligung.';
                $zu('regeln#briefdienst');

            case 'akq_check_erledigt':
                require_once __DIR__ . '/src/AkquiseCheck.php';
                AkquiseCheck::erledigen((int) ($_POST['check'] ?? 0));
                weiter('akquise#checks');

            case 'akq_check_schalten':
                require_once __DIR__ . '/src/AkquiseCheck.php';
                AkquiseCheck::schalten(!empty($_POST['an']));
                Events::pruefspur('akquise_check_schalter', 'settings', null, [], ['akq_check_an' => !empty($_POST['an']) ? '1' : '0']);
                $_SESSION['gut'] = !empty($_POST['an']) ? 'Website-Check ist eingeschaltet.' : 'Website-Check ist ausgeschaltet — die Seite nimmt keine Anfragen an.';
                $zu('regeln#check');

            case 'akq_plattform_erledigt':
                require_once __DIR__ . '/src/AkquisePlattform.php';
                AkquisePlattform::erledigen((int) ($_POST['anfrage'] ?? 0));
                weiter('akquise#plattform');

            case 'akq_signal_erledigt':
                require_once __DIR__ . '/src/AkquiseSignal.php';
                AkquiseSignal::erledigen((int) ($_POST['signal'] ?? 0));
                weiter('akquise#signale');

            case 'akq_vorort':
                /* Vor-Ort-Modus (28.09.2026, V1): Der Inhaber tippt selbst auf Uwes Handy. */
                $sp = in_array($_POST['sprache'] ?? '', ['it', 'de', 'en'], true) ? (string) $_POST['sprache'] : 'it';
                $e = AkquiseEinwilligung::link($fid, 'vorort');
                $r = AkquiseEinwilligung::anfragen((string) $e['link_token'], (string) ($_POST['email'] ?? ''), !empty($_POST['ja']), $sp,
                    (string) ($_SERVER['REMOTE_ADDR'] ?? ''), !empty($_POST['wa']) ? (string) ($_POST['whatsapp'] ?? '') : null);
                weiter('akquise/' . $fid . '/vorort?sprache=' . $sp . ($r === 'ok' ? '' : '&f=' . rawurlencode($r)));

            case 'akq_wa_speichern':
                require_once __DIR__ . '/src/WhatsAppCloud.php';
                WhatsAppCloud::speichern($_POST);
                Events::pruefspur('whatsapp_einstellungen', 'settings', null, [], ['nummer_id' => WhatsAppCloud::einstellungen()['nummer_id'], 'konto_id' => WhatsAppCloud::einstellungen()['konto_id']]);
                $_SESSION['gut'] = 'WhatsApp-Einstellungen gespeichert.';
                $zu('regeln#whatsapp');

            case 'akq_meta_speichern':
                require_once __DIR__ . '/src/MetaSeite.php';
                MetaSeite::speichern($_POST);
                Events::pruefspur('meta_einstellungen', 'settings', null, [], ['seite_id' => MetaSeite::einstellungen()['seite_id'], 'ig_id' => MetaSeite::einstellungen()['ig_id']]);
                $_SESSION['gut'] = 'Facebook/Instagram gespeichert.';
                $zu('regeln#wege');

            case 'akq_google_schluessel':
                require_once __DIR__ . '/src/GoogleLead.php';
                GoogleLead::schluesselNeu();
                Events::pruefspur('google_lead_schluessel', 'settings', null, [], ['neu' => true]);
                $_SESSION['gut'] = 'Neuer Schlüssel für Google Ads erzeugt — jetzt in Google Ads eintragen.';
                $zu('regeln#google');

            case 'akq_meta_abo':
                require_once __DIR__ . '/src/MetaSeite.php';
                $r = MetaSeite::formulareAbonnieren();
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['ok'] ? 'Die Seite meldet ausgefüllte Werbeformulare jetzt automatisch.' : 'Anmelden bei Meta gescheitert: ' . $r['grund'];
                $zu('regeln#wege');

            case 'akq_beitrag_neu':
                require_once __DIR__ . '/src/MetaSeite.php';
                MetaSeite::entwurf((string) ($_POST['code'] ?? '') ?: null, (string) ($_POST['sprache'] ?? '') ?: null);
                $_SESSION['gut'] = 'Neuer Entwurf liegt oben.';
                $zu('beitraege');

            case 'akq_beitrag_posten':
                require_once __DIR__ . '/src/MetaSeite.php';
                $r = MetaSeite::posten((int) ($_POST['beitrag'] ?? 0), (string) ($_POST['text'] ?? ''));
                $_SESSION[$r['ok'] ? 'gut' : 'fehler'] = $r['ok'] ? 'Gepostet.' : 'Nicht gepostet: ' . $r['grund'];
                $zu('beitraege');

            case 'akq_anzeige_erledigt':
                Db::run("UPDATE akq_anzeigen SET status = IF(status = 'erledigt', 'entwurf', 'erledigt') WHERE id = ?", [(int) ($_POST['anzeige'] ?? 0)]);
                $zu('anzeigen');

            case 'akq_anzeigen_jetzt':
                require_once __DIR__ . '/src/BranchenStatistik.php';
                $nSeiten = BranchenStatistik::rechnen();
                Db::run('DELETE FROM akq_anzeigen WHERE woche = ? AND status = ?', [BranchenStatistik::woche(), 'entwurf']);
                $nAnz = BranchenStatistik::anzeigenPlanen();
                $_SESSION['gut'] = "Neu gerechnet: $nSeiten Branchen-Seiten, $nAnz Anzeigen-Entwürfe.";
                $zu('anzeigen');

            case 'akq_beitrag_verwerfen':
                Db::run("UPDATE akq_beitraege SET status = 'verworfen' WHERE id = ? AND status IN ('entwurf','fehler')", [(int) ($_POST['beitrag'] ?? 0)]);
                $zu('beitraege');

            case 'akq_wa_anmelden':
                require_once __DIR__ . '/src/WhatsAppCloud.php';
                $r = WhatsAppCloud::anmelden();
                $_SESSION[$r['fehler'] ? 'fehler' : 'gut'] = $r['eingereicht'] . ' Vorlage(n) bei Meta eingereicht.' . ($r['fehler'] ? ' Probleme: ' . implode(' · ', array_slice($r['fehler'], 0, 3)) : ' Die Genehmigung dauert meist Minuten bis wenige Stunden.');
                $zu('regeln#whatsapp');

            case 'akq_wa_stand':
                require_once __DIR__ . '/src/WhatsAppCloud.php';
                $_SESSION['gut'] = WhatsAppCloud::standAbrufen() . ' Vorlage(n) mit neuem Stand.';
                $zu('regeln#whatsapp');

            case 'akq_einwilligung_link':
                $e = AkquiseEinwilligung::link($fid, 'link');
                $_SESSION['akq_einw_link'][$fid] = AkquiseEinwilligung::adresse($e);
                Akquise::protokoll($fid, 'einwilligung', 'Einwilligungs-Link erzeugt');
                weiter('akquise/' . $fid . '#einwilligung');

            case 'akq_karte':
                /* Karte drucken: Analyse-Seite anlegen und einschalten (Uwes Klick). */
                try {
                    $an = AkquiseAnalyse::anlegen($fid);
                    if ((int) ($an['aktiv'] ?? 0) !== 1 || ($an['gueltig_bis'] !== null && strtotime((string) $an['gueltig_bis']) < strtotime('today'))) { AkquiseAnalyse::umschalten((int) $an['id'], true); }
                } catch (RuntimeException $e) { $_SESSION['fehler'] = $e->getMessage(); weiter('akquise/' . $fid . '#einwilligung'); }
                weiter('akquise/' . $fid . '/qrkarte');

            case 'akq_analyse_anlegen':
                AkquiseAnalyse::anlegen($fid);
                weiter('akquise/' . $fid . '?ansicht=verlauf#analyse');

            case 'akq_analyse_umschalten':
                AkquiseAnalyse::umschalten((int) $_POST['analyse'], !empty($_POST['an']));
                $zurueck('akquise/' . $fid . '?ansicht=verlauf#analyse');

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
                $_SESSION['gut'] = 'Regel gespeichert, Betriebe neu eingestuft.';
                $zu('regeln#regeln');

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
                $_SESSION['gut'] = 'Versand-Einstellungen gespeichert.';
                $zu('regeln#versand');

            case 'akq_notbremse':
                AkquiseGate::notbremse(!empty($_POST['ziehen']));
                $_SESSION['gut'] = !empty($_POST['ziehen']) ? 'Notbremse gezogen. Es geht nichts mehr raus.' : 'Notbremse gelöst.';
                weiter((string) ($_POST['zurueck'] ?? 'akquise'));

            case 'akq_schluessel_neu':
                $_SESSION['akq_schluessel_einmal'] = AkquiseWorker::neuerSchluessel();
                $_SESSION['gut'] = 'Neuer Schlüssel für die PC-Verbindung erzeugt. Er wird genau einmal angezeigt — ein alter gilt nicht mehr.';
                $zu('regeln#rechner');

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

if ($teil === 'steuerung') {
    /* Nur der Block „Dein PC“ (29.09.2026) -- für die Aktualisierung alle 30 Sekunden. */
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    require __DIR__ . '/views/akquise_steuerung.php';
    exit;
}

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

if ($teil === 'beitraege') {
    require_once __DIR__ . '/src/MetaSeite.php';
    ansicht('akquise_beitraege', [
        'beitraege' => sicher(static fn() => Db::all("SELECT * FROM akq_beitraege WHERE status <> 'verworfen' OR created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) ORDER BY FIELD(status,'entwurf','fehler','gepostet','verworfen'), id DESC LIMIT 40"), []),
        'leads' => sicher(static fn() => Db::all('SELECT * FROM akq_meta_leads ORDER BY id DESC LIMIT 20'), []),
        'meta' => ['bereit' => MetaSeite::bereit()],
    ]);
    exit;
}

if ($teil === 'anzeigen') {
    /* W1/W3 (28.09.2026): Branchen-Seiten und Anzeigen-Entwürfe mit echten Zahlen. */
    require_once __DIR__ . '/src/BranchenStatistik.php';
    ansicht('akquise_anzeigen', [
        'anzeigen' => sicher(static fn() => Db::all("SELECT * FROM akq_anzeigen WHERE woche >= ? ORDER BY woche DESC, FIELD(status,'entwurf','erledigt'), slug, kanal LIMIT 60",
            [BranchenStatistik::woche(strtotime('-21 days'))]), []),
        'seiten' => sicher(static fn() => BranchenStatistik::liste(null, 200), []),
    ]);
    exit;
}

if ($teil === 'qrkarte') {
    /* Allgemeine QR-Karte (28.09.2026, Z3): führt auf analisi.php. */
    require_once __DIR__ . '/src/QrBild.php';
    require_once __DIR__ . '/src/AkquiseAnalyse.php';
    $f = null; $analyse = null;
    $sp = in_array($_GET['sprache'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['sprache'] : 'it';
    $ziel = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/analisi.php?lang=' . $sp;
    require __DIR__ . '/views/akquise_qrkarte.php';
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

if ($teil === 'auswertung' || $teil === 'karte') {
    require_once __DIR__ . '/src/AkquiseAuswertung.php';
    if ($teil === 'karte') { ansicht('akquise_karte', ['punkte' => AkquiseAuswertung::kartenpunkte()]); exit; }
    $nach = in_array($_GET['nach'] ?? '', ['branche', 'kanal', 'variante'], true) ? (string) $_GET['nach'] : 'branche';
    $tage = in_array((int) ($_GET['tage'] ?? 90), [30, 90, 365], true) ? (int) ($_GET['tage'] ?? 90) : 90;
    $wegDash = null; try { $wegDash = AkquiseAuswertung::wegZumDashboard($tage); } catch (Throwable $e) { $wegDash = null; }
    ansicht('akquise_auswertung', ['trichter' => AkquiseAuswertung::trichter($nach, $tage), 'nach' => $nach, 'tage' => $tage, 'woche' => AkquiseAuswertung::woche(), 'wegDash' => $wegDash]);
    exit;
}

if ($teil === 'briefe' && !AkquiseGate::briefAn()) {
    $_SESSION['fehler'] = 'Briefe sind ausgeschaltet — einschalten unter Regeln & Versand.';
    weiter('akquise/regeln#briefdienst');
}
if ($teil === 'briefe') {
    require_once __DIR__ . '/src/AkquiseBriefserie.php';
    ansicht('akquise_briefe', ['kandidaten' => AkquiseBriefserie::kandidaten(), 'offen' => AkquiseBriefserie::offen(), 'bereit' => AkquiseBriefdienst::bereit()]);
    exit;
}

if ($teil === 'assistent') {
    require_once __DIR__ . '/src/AkquiseAssistent.php';
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 200);
    $verstanden = [];
    $filter = array_filter([
        'land' => in_array($_GET['land'] ?? '', ['DE', 'IT'], true) ? (string) $_GET['land'] : '',
        'region' => mb_substr(trim((string) ($_GET['region'] ?? '')), 0, 120),
        'stadt' => mb_substr(trim((string) ($_GET['stadt'] ?? '')), 0, 120),
        'branche' => isset(Akquise::branchen()[(string) ($_GET['branche'] ?? '')]) ? (string) $_GET['branche'] : '',
    ], static fn($v) => $v !== '');
    $frage = (string) ($_GET['f'] ?? 'beste');
    if ($q !== '') {
        $v = AkquiseAssistent::verstehen($q);
        $frage = $v['frage']; $filter = $v['filter'] + $filter; $verstanden = $v['erkannt'];
    }
    ansicht('akquise_assistent', ['antwort' => AkquiseAssistent::antwort($frage, $filter), 'verstanden' => $verstanden, 'q' => $q,
        'filter' => array_diff_key($filter, ['anzahl' => 1]), 'werte' => Akquise::filterWerte()]);
    exit;
}

if ($teil === 'termine') {
    require_once __DIR__ . '/src/AkquiseTermin.php';
    ansicht('akquise_termine', ['kommend' => AkquiseTermin::liste(true), 'vorbei' => AkquiseTermin::liste(false, 30), 'plan' => AkquiseTermin::planText(),
        'e' => AkquiseTermin::einstellungen(), 'an' => AkquiseTermin::an(), 'freiZahl' => array_sum(array_map('count', AkquiseTermin::freie()))]);
    exit;
}

if ($teil === 'folgen') {
    require_once __DIR__ . '/src/AkquiseFolge.php';
    ansicht('akquise_folgen', [
        'vorlagen' => AkquiseFolge::vorlagen(),
        'folgen' => AkquiseFolge::liste(),
        'waHand' => AkquiseFolge::handOffen(),
        'an' => AkquiseGate::schalter('folge'),
        'test' => AkquiseGate::testbetrieb(),
        'versandAn' => AkquiseGate::grenzen()['versand_an'],
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
    $zusatz = (string) ($teile[2] ?? '');
    if ($zusatz === 'brief' && !AkquiseGate::briefAn()) { $_SESSION['fehler'] = 'Briefe sind ausgeschaltet.'; weiter('akquise/' . $fid); }
    if ($zusatz === 'brief') {
        /* Druckblatt: eigenes A4 ohne Menue. */
        $audit = Akquise::letzterAudit($fid);
        $befunde = $audit ? Akquise::befunde((int) $audit['id']) : [];
        require __DIR__ . '/views/akquise_brief.php';
        exit;
    }
    if ($zusatz === 'vorort') {
        /* Vor-Ort-Modus (28.09.2026, V1): eigene Seite ohne Menü, für den Kunden lesbar. */
        require_once __DIR__ . '/src/AkquiseScore.php';
        $audit = Akquise::letzterAudit($fid);
        $befunde = $audit ? Akquise::befunde((int) $audit['id']) : [];
        require __DIR__ . '/views/akquise_vorort.php';
        exit;
    }
    if ($zusatz === 'qrkarte') {
        /* QR-Karte zum Hinlegen (28.09.2026, Z3): führt auf die eigene Analyse-Seite. */
        foreach (['AkquiseAnalyse', 'QrBild'] as $k) { require_once __DIR__ . "/src/$k.php"; }
        $analyse = Db::one('SELECT * FROM akq_analysen WHERE firma_id = ? AND aktiv = 1 AND (gueltig_bis IS NULL OR gueltig_bis >= CURDATE()) ORDER BY id DESC LIMIT 1', [$fid]);
        if (!$analyse) { $_SESSION['fehler'] = 'Für die Karte braucht es eine eingeschaltete Analyse-Seite — Knopf „Karte drucken“ schaltet sie ein.'; weiter('akquise/' . $fid . '#einwilligung'); }
        $sp = in_array($_GET['sprache'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['sprache'] : AkquiseText::spracheFuer($f);
        $ziel = AkquiseAnalyse::adresse($analyse);
        require __DIR__ . '/views/akquise_qrkarte.php';
        exit;
    }
    if ($zusatz === 'anruf') {
        /* Anrufzettel: im Rahmen der Verwaltung, weil die Ergebnis-Knoepfe
           die Rueckfrage aus Ablauf::TRAGWEITE brauchen (Kein Interesse sperrt). */
        $audit = Akquise::letzterAudit($fid);
        ansicht('akquise_anruf', [
            'f' => $f, 'audit' => $audit,
            'befunde' => $audit ? Akquise::befunde((int) $audit['id']) : [],
            'gate' => AkquiseGate::pruefen($f, 'telefon'),
        ]);
        exit;
    }
    $audit = Akquise::letzterAudit($fid);
    $befunde = $audit ? Akquise::befunde((int) $audit['id']) : [];
    $gates = [];
    foreach (array_keys(AkquiseGate::KANAELE) as $k) { $gates[$k] = AkquiseGate::pruefen($f, $k); }
    $vorlagen = Db::all('SELECT * FROM akq_vorlagen WHERE firma_id = ? ORDER BY id DESC', [$fid]);
    foreach ($vorlagen as $v) {
        if ($v['status'] !== 'verworfen') { $f['vorlage_status'] = $v['status']; $f['vorlage_kanal'] = $v['kanal']; break; }
    }
    $ansicht = in_array($_GET['ansicht'] ?? '', ['befunde', 'verlauf'], true) ? (string) $_GET['ansicht'] : 'ueberblick';
    ansicht('akquise_firma', [
        'ansicht' => $ansicht,
        'ampel' => AkquiseGate::ampel($f),
        'schritt' => Akquise::naechsterSchritt($f),
        'f' => $f,
        'audit' => $audit,
        'befunde' => $befunde,
        'top' => array_slice(AkquiseScore::topBefunde($befunde), 0, 3),
        'teile' => $audit && $audit['teilwerte'] ? (json_decode((string) $audit['teilwerte'], true) ?: []) : [],
        'messwerte' => $audit && $audit['messwerte'] ? (json_decode((string) $audit['messwerte'], true) ?: []) : [],
        'ki' => $audit && $audit['ki'] ? (json_decode((string) $audit['ki'], true) ?: []) : [],
        'vorlagen' => $vorlagen,
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
                                                  'stufe', 'score_min', 'von', 'bis', 'q', 'sort', 'gesperrte', 'stark', 'darf', 'ohne_web', 'partner']));
ansicht('akquise', [
    'liste' => Akquise::liste($filter, max(1, (int) ($_GET['seite'] ?? 1))),
    'filter' => $filter,
    'werte' => Akquise::filterWerte(),
    'kz' => Akquise::kennzahlen(),
    'grenzen' => AkquiseGate::grenzen(),
    'wartend' => (int) Db::wert("SELECT COUNT(*) FROM akq_laeufe WHERE status IN ('wartet','laeuft')"),
    'suchen' => Db::all("SELECT gebiet, status FROM akq_laeufe WHERE status IN ('wartet','laeuft') ORDER BY id LIMIT 5"),
    'branchen' => Akquise::branchen(),
    'signale' => sicher(static function () { require_once __DIR__ . '/src/AkquiseSignal.php'; return AkquiseSignal::offen(); }, []),
    'checks' => sicher(static function () { require_once __DIR__ . '/src/AkquiseCheck.php'; return AkquiseCheck::liste(10); }, []),
    'plattform' => sicher(static function () { require_once __DIR__ . '/src/AkquisePlattform.php'; return AkquisePlattform::offen(20); }, []),
]);
exit;
