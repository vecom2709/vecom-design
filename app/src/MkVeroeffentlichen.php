<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkInhalt.php';
require_once __DIR__ . '/MkMedium.php';

/**
 * Veröffentlichen nach Freigabe (Marketing-Studio Schritt 4, 01.10.2026,
 * Uwe: „Ja, so“ — automatisch auf Facebook, Instagram und Telegram, Pakete
 * für alles andere).
 *
 *   Facebook   Beitrag mit gewähltem Bild (/photos) oder als Text mit Link (/feed),
 *              Reel mit gewähltem Video (/videos) — über die eingerichtete Seite (MetaSeite)
 *   Instagram  Beitrag mit gewähltem Bild, Reel mit gewähltem Video (Container →
 *              veröffentlichen; ein Video, das Meta noch verarbeitet, macht der Cronlauf fertig)
 *   Telegram   Telegram-Beitrag in den hinterlegten Kanal, mit Bild/Video und Knopf auf den eigenen Link
 *   alles andere (TikTok, LinkedIn, Google-Profil, Meta- und Google-Anzeigen, Story, Karussell):
 *              Paket als ZIP — Text mit Link, Bild/Video, bei Google-Anzeigen eine
 *              Datei für den Google Ads Editor, eine Anleitung
 *
 * Gepostet wird nur Freigegebenes, sofort per Knopf oder geplant (Cronlauf).
 */
final class MkVeroeffentlichen
{
    public const AUTO = ['facebook', 'instagram', 'telegram'];
    public const JE_LAUF = 5;

    /** Öffentliche Adresse eines gewählten Mediums (für Meta und Telegram). */
    public static function oeffentlich(array $m): string
    {
        $t = (string) ($m['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{32}$/', $t)) {
            $t = bin2hex(random_bytes(16));
            Db::run('UPDATE mk_medien SET token = ? WHERE id = ?', [$t, (int) $m['id']]);
        }
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/m.php?t=' . $t;
    }

    /**
     * Geht dieses Stück automatisch raus — und wenn nicht, warum nicht?
     * @return array{auto:bool, grund:string, medium:?array}
     */
    public static function moeglich(array $x): array
    {
        $bild = MkMedium::gewaehlt((int) $x['id'], 'bild');
        $video = MkMedium::gewaehlt((int) $x['id'], 'video');
        $nein = static fn(string $g) => ['auto' => false, 'grund' => $g, 'medium' => null];
        if ($x['art'] === 'bezahlt') {
            return $nein($x['format'] === 'google_anzeige' ? 'Google-Anzeigen schaltest du in Google Ads — das Paket enthält alle Felder und eine Datei für den Google Ads Editor.'
                                                          : 'Anzeigen schaltest du im Werbeanzeigenmanager — das Paket enthält alle Felder.');
        }
        if (!in_array($x['plattform'], self::AUTO, true)) {
            return $nein('Für ' . trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform'])) ?? '') . ' gibt es ein Paket zum Hochladen.');
        }
        if ($x['plattform'] === 'telegram') {
            require_once __DIR__ . '/Telegram.php';
            if (Telegram::kanal()['id'] === '' || !Telegram::bereit()) { return $nein('Der Telegram-Kanal ist noch nicht verbunden (Marketing › Kanäle › Verbinden).'); }
            return ['auto' => true, 'grund' => '', 'medium' => $video ?? $bild];
        }
        require_once __DIR__ . '/MetaSeite.php';
        if (!MetaSeite::bereit()) { return $nein('Die Facebook-Seite ist noch nicht verbunden (Marketing › Kanäle › Verbinden).'); }
        if ($x['plattform'] === 'instagram') {
            if (MetaSeite::einstellungen()['ig_id'] === '') { return $nein('Instagram ist an der Facebook-Seite noch nicht verbunden.'); }
            if ($x['format'] === 'reel') { return $video ? ['auto' => true, 'grund' => '', 'medium' => $video] : $nein('Für ein Instagram-Reel erst ein Video erzeugen und wählen.'); }
            if ($x['format'] === 'beitrag') { return $bild ? ['auto' => true, 'grund' => '', 'medium' => $bild] : $nein('Instagram braucht ein Bild — erst eines erzeugen und wählen.'); }
            return $nein((MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) . ' auf Instagram lädst du mit dem Paket hoch.');
        }
        /* Facebook */
        if ($x['format'] === 'reel') { return $video ? ['auto' => true, 'grund' => '', 'medium' => $video] : $nein('Für ein Facebook-Reel erst ein Video erzeugen und wählen.'); }
        if (in_array($x['format'], ['beitrag', 'karussell'], true)) { return ['auto' => true, 'grund' => '', 'medium' => $bild]; }
        return $nein((MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) . ' auf Facebook lädst du mit dem Paket hoch.');
    }

    private static function ids(array $x): array
    {
        return json_decode((string) ($x['post_ids'] ?? ''), true) ?: [];
    }

    /** Text fürs Posten: der fertige Text mit Link, wo er klickbar ist. */
    private static function text(array $x, int $max): string
    {
        $t = MkInhalt::kopiertext($x);
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
    }

    /**
     * Jetzt veröffentlichen. @return array{ok:bool, grund:?string}
     */
    /* ------------------------------------------------------------------ */
    /* Freigabe-Stapel (Marketing-Studio 6, Uwe: „ja“ zu U3)               */
    /* ------------------------------------------------------------------ */

    /** Feste Sendezeit je Plattform und Tag — abends, wenn Inhaber nach Feierabend aufs Handy schauen. */
    public const SENDEZEIT = '18:30';

    /**
     * Der nächste freie Sendeplatz einer Plattform: heute um 18:30 (wenn noch
     * mindestens eine halbe Stunde Zeit ist), sonst morgen — und nie zwei am
     * selben Tag auf derselben Plattform (geplant oder heute schon gepostet).
     */
    public static function naechsterSlot(string $plattform, ?int $jetzt = null): string
    {
        $jetzt ??= time();
        $tag = strtotime(date('Y-m-d', $jetzt) . ' ' . self::SENDEZEIT);
        if ($tag < $jetzt + 1800) { $tag = strtotime(date('Y-m-d', $jetzt + 86400) . ' ' . self::SENDEZEIT); }
        $belegt = [];
        foreach (Db::all("SELECT DATE(geplant_am) AS d FROM mk_inhalte WHERE status = 'freigegeben' AND plattform = ? AND geplant_am IS NOT NULL
                          UNION SELECT DATE(veroeffentlicht_am) FROM mk_inhalte WHERE status = 'veroeffentlicht' AND plattform = ? AND veroeffentlicht_am >= CURDATE()", [$plattform, $plattform]) as $r) {
            $belegt[(string) $r['d']] = true;
        }
        for ($i = 0; $i < 60 && isset($belegt[date('Y-m-d', $tag)]); $i++) { $tag = strtotime(date('Y-m-d', $tag + 86400) . ' ' . self::SENDEZEIT); }
        return date('Y-m-d H:i', $tag);
    }

    /** Was „Ja“ im Stapel mit diesem Stück tut — in einem Satz, bevor Uwe klickt. */
    public static function wasPassiert(array $x): string
    {
        $hatBild = (int) Db::wert("SELECT COUNT(*) FROM mk_medien WHERE inhalt_id = ? AND art = 'bild' AND status <> 'verworfen'", [(int) $x['id']], 0) > 0;
        $m = self::moeglich($x);
        /* Fehlt nur die Wahl des Bildes: „Ja“ wählt das vorhandene — dann geht es. */
        if (!$m['auto'] && $hatBild && str_contains($m['grund'], 'Bild — erst eines erzeugen und wählen')) { $m = ['auto' => true, 'grund' => '']; }
        if ($m['auto']) {
            $t = strtotime(self::naechsterSlot((string) $x['plattform']));
            return 'Ja gibt frei und veröffentlicht es am ' . ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) date('w', $t)] . ' ' . date('d.m.', $t) . ' um ' . date('H:i', $t)
                . ' auf ' . trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform'])) ?? '') . '.';
        }
        require_once __DIR__ . '/MkHandy.php';
        if (MkHandy::istHandy($x) && MkHandy::stand()['bereit']) {
            $t = strtotime(self::naechsterSlot((string) $x['plattform']));
            return 'Ja gibt frei und schickt es dir am ' . ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int) date('w', $t)] . ' ' . date('d.m.', $t) . ' um ' . date('H:i', $t) . ' per Telegram aufs Handy zum Posten.';
        }
        return 'Ja gibt frei. ' . rtrim($m['grund'], '.') . '. Der eigene Link steht dann am Stück.';
    }

    /**
     * „Ja“ im Stapel: ein vorhandenes Bild wählen (wenn noch keines gewählt
     * ist), freigeben (eigener Link), und wo es geht auf den nächsten freien
     * Sendeplatz legen. Nichts geht ohne diesen Klick raus.
     * @return array{ok:bool, text:string}
     */
    public static function stapelJa(int $id): array
    {
        $x = MkInhalt::laden($id);
        if ($x === null || $x['status'] !== 'entwurf') { return ['ok' => false, 'text' => 'Dieses Stück ist kein Entwurf mehr.']; }
        foreach (['bild', 'video'] as $art) {
            if (MkMedium::gewaehlt($id, $art) === null) {
                $neu = Db::wert("SELECT id FROM mk_medien WHERE inhalt_id = ? AND art = ? AND status = 'neu' ORDER BY id DESC LIMIT 1", [$id, $art], null);
                if ($neu !== null) { MkMedium::status((int) $neu, 'gewaehlt'); }
            }
        }
        $f = MkInhalt::freigeben($id);
        if ($f !== null) { return ['ok' => false, 'text' => $f]; }
        return ['ok' => true, 'text' => self::nachFreigabe($id)];
    }

    public static function jetzt(int $id): array
    {
        $x = MkInhalt::laden($id);
        if ($x === null) { return ['ok' => false, 'grund' => 'Inhalt nicht gefunden.']; }
        if ($x['status'] !== 'freigegeben') { return ['ok' => false, 'grund' => 'Nur freigegebene Inhalte gehen raus.']; }
        $m = self::moeglich($x);
        if (!$m['auto']) { return ['ok' => false, 'grund' => $m['grund']]; }
        $ids = self::ids($x);
        try {
            $erg = match ($x['plattform']) {
                'telegram' => self::telegram($x, $m['medium']),
                'instagram' => self::instagram($x, $m['medium'], $ids),
                default => self::facebook($x, $m['medium']),
            };
        } catch (Throwable $e) {
            $erg = ['ok' => false, 'grund' => mb_substr($e->getMessage(), 0, 200), 'ids' => []];
        }
        $ids = array_merge($ids, $erg['ids'] ?? []);
        if (!empty($erg['wartet'])) {   // Instagram verarbeitet das Video noch: der Cronlauf macht es fertig
            Db::update('mk_inhalte', $id, ['post_ids' => json_encode($ids), 'geplant_am' => date('Y-m-d H:i:s'), 'post_fehler' => null]);
            return ['ok' => true, 'grund' => null, 'wartet' => true];
        }
        if ($erg['ok']) {
            unset($ids['ig_container'], $ids['versuche']);
            Db::update('mk_inhalte', $id, ['status' => 'veroeffentlicht', 'veroeffentlicht_am' => date('Y-m-d H:i:s'), 'geplant_am' => null,
                                           'post_ids' => json_encode($ids), 'post_fehler' => null]);
            Events::pruefspur('inhalt_gepostet', 'mk_inhalte', $id, ['status' => 'freigegeben'], ['status' => 'veroeffentlicht', 'plattform' => $x['plattform']] + $ids);
            return ['ok' => true, 'grund' => null];
        }
        Db::update('mk_inhalte', $id, ['post_ids' => $ids ? json_encode($ids) : null, 'post_fehler' => mb_substr((string) $erg['grund'], 0, 300)]);
        return ['ok' => false, 'grund' => (string) $erg['grund']];
    }

    private static function facebook(array $x, ?array $medium): array
    {
        require_once __DIR__ . '/MetaSeite.php';
        $seite = MetaSeite::einstellungen()['seite_id'];
        $text = self::text($x, 5000);
        if ($medium && $medium['art'] === 'video') {
            $r = MetaSeite::graph('POST', $seite . '/videos', ['file_url' => self::oeffentlich($medium), 'description' => $text, 'published' => true]);
        } elseif ($medium) {
            $r = MetaSeite::graph('POST', $seite . '/photos', ['url' => self::oeffentlich($medium) . '&f=jpg', 'caption' => $text, 'published' => true]);
        } else {
            $link = MkInhalt::link($x);
            $r = MetaSeite::graph('POST', $seite . '/feed', ['message' => $text] + ($link ? ['link' => $link] : []));
        }
        $pid = (string) ($r['json']['post_id'] ?? $r['json']['id'] ?? '');
        return $r['status'] === 200 && $pid !== '' ? ['ok' => true, 'ids' => ['fb' => $pid]] : ['ok' => false, 'grund' => 'Facebook: ' . MetaSeite::fehler($r)];
    }

    private static function instagram(array $x, ?array $medium, array $ids): array
    {
        require_once __DIR__ . '/MetaSeite.php';
        $ig = MetaSeite::einstellungen()['ig_id'];
        $container = (string) ($ids['ig_container'] ?? '');
        if ($container === '') {
            $text = self::text($x, 2200);
            $body = $medium['art'] === 'video'
                ? ['media_type' => 'REELS', 'video_url' => self::oeffentlich($medium), 'caption' => $text, 'share_to_feed' => true]
                : ['image_url' => self::oeffentlich($medium) . '&f=jpg', 'caption' => $text];
            $r = MetaSeite::graph('POST', $ig . '/media', $body);
            $container = (string) ($r['json']['id'] ?? '');
            if ($r['status'] !== 200 || $container === '') { return ['ok' => false, 'grund' => 'Instagram: ' . MetaSeite::fehler($r)]; }
        }
        if ($medium['art'] === 'video') {
            $st = MetaSeite::graph('GET', $container . '?fields=status_code');
            $code = (string) ($st['json']['status_code'] ?? '');
            if ($code === 'ERROR' || $code === 'EXPIRED') { return ['ok' => false, 'grund' => 'Instagram konnte das Video nicht verarbeiten (' . $code . ').', 'ids' => ['ig_container' => null]]; }
            if ($code !== 'FINISHED') { return ['ok' => false, 'wartet' => true, 'ids' => ['ig_container' => $container]]; }
        }
        $p = MetaSeite::graph('POST', $ig . '/media_publish', ['creation_id' => $container]);
        $pid = (string) ($p['json']['id'] ?? '');
        return $p['status'] === 200 && $pid !== '' ? ['ok' => true, 'ids' => ['ig' => $pid]] : ['ok' => false, 'grund' => 'Instagram: ' . MetaSeite::fehler($p), 'ids' => ['ig_container' => $container]];
    }

    private static function telegram(array $x, ?array $medium): array
    {
        require_once __DIR__ . '/Telegram.php';
        $k = Telegram::kanal();
        $link = MkInhalt::link($x);
        $knopf = trim((string) ($x['f']['knopf'] ?? '')) ?: 'vecom-design.it';
        $mark = $link ? ['inline_keyboard' => [[['text' => mb_substr($knopf, 0, 40), 'url' => $link]]]] : null;
        $text = trim((string) ($x['f']['text'] ?? ''));
        if ($medium) {
            $methode = $medium['art'] === 'video' ? 'sendVideo' : 'sendPhoto';
            $daten = ['chat_id' => $k['id'], $medium['art'] === 'video' ? 'video' : 'photo' => self::oeffentlich($medium) . ($medium['art'] === 'video' ? '' : '&f=jpg'),
                      'caption' => mb_substr($text, 0, 1024)];
        } else {
            $methode = 'sendMessage';
            $daten = ['chat_id' => $k['id'], 'text' => mb_substr($text, 0, Telegram::KANAL_MAX)];
        }
        if ($mark) { $daten['reply_markup'] = $mark; }
        $r = Telegram::rufen($methode, $daten);
        $mid = (string) ($r['result']['message_id'] ?? '');
        return $r['ok'] && $mid !== '' ? ['ok' => true, 'ids' => ['tg' => $mid]] : ['ok' => false, 'grund' => 'Telegram: ' . ($r['beschreibung'] ?: 'nicht angenommen')];
    }

    /** Planen: nur Freigegebenes, das automatisch rausgehen kann; 5 Minuten bis 60 Tage voraus. */
    public static function planen(int $id, string $wann): ?string
    {
        $x = MkInhalt::laden($id);
        if ($x === null) { return 'Inhalt nicht gefunden.'; }
        if ($x['status'] !== 'freigegeben') { return 'Nur freigegebene Inhalte lassen sich planen.'; }
        if ($wann === '') { Db::update('mk_inhalte', $id, ['geplant_am' => null]); return null; }
        $t = strtotime(str_replace('T', ' ', $wann));
        if ($t === false) { return 'Bitte Datum und Uhrzeit wählen.'; }
        if ($t < time() + 240 || $t > time() + 60 * 86400) { return 'Bitte einen Zeitpunkt zwischen jetzt und 60 Tagen.'; }
        $m = self::moeglich($x);
        if (!$m['auto']) { return $m['grund']; }
        Db::update('mk_inhalte', $id, ['geplant_am' => date('Y-m-d H:i:00', $t), 'post_fehler' => null]);
        return null;
    }

    /** P2: So lange wartet der zweite Versuch, wenn der erste scheitert (Meta/Telegram kurz nicht erreichbar). */
    public const WIEDER_MIN = 60;

    /** Cronlauf: Fälliges posten, wartende Instagram-Videos fertig machen. Scheitert ein Stück, versucht es der Lauf
        nach einer Stunde noch einmal; erst wenn auch das scheitert, kommt die Meldung (Startseite und Handy). */
    public static function faellige(): array
    {
        $aus = ['gepostet' => 0, 'fehler' => 0, 'wieder' => 0];
        foreach (Db::all("SELECT id FROM mk_inhalte WHERE status = 'freigegeben' AND geplant_am IS NOT NULL AND geplant_am <= NOW() ORDER BY geplant_am LIMIT " . self::JE_LAUF) as $r) {
            /* P3: Was nicht automatisch geht, kommt zur Sendezeit aufs Handy. */
            $vorab = MkInhalt::laden((int) $r['id']);
            if ($vorab !== null) {
                require_once __DIR__ . '/MkHandy.php';
                if (MkHandy::istHandy($vorab)) {
                    $e = MkHandy::senden($vorab);
                    $e['ok'] ? $aus['gepostet']++ : $aus['fehler']++;
                    if (!$e['ok']) { Db::update('mk_inhalte', (int) $r['id'], ['geplant_am' => null, 'post_fehler' => mb_substr((string) $e['grund'], 0, 300)]); }
                    continue;
                }
            }
            $e = self::jetzt((int) $r['id']);
            if (!empty($e['wartet'])) { continue; }
            if ($e['ok']) { $aus['gepostet']++; continue; }
            $x = MkInhalt::laden((int) $r['id']);
            $ids = $x ? self::ids($x) : [];
            $versuche = (int) ($ids['versuche'] ?? 0) + 1;
            if ($versuche < 2) {
                $ids['versuche'] = $versuche;
                Db::update('mk_inhalte', (int) $r['id'], ['geplant_am' => date('Y-m-d H:i:s', time() + self::WIEDER_MIN * 60), 'post_ids' => json_encode($ids)]);
                $aus['wieder']++;
                continue;
            }
            unset($ids['versuche']);
            $aus['fehler']++;
            Db::update('mk_inhalte', (int) $r['id'], ['geplant_am' => null, 'post_ids' => $ids ? json_encode($ids) : null]);
            $pl = trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$x['plattform'] ?? ''] ?? ($x['plattform'] ?? ''))) ?? '');
            try { Events::melden('inhalt_post_fehler', 'Beitrag nicht veröffentlicht (' . $pl . ')', 'warnung', mb_substr((string) $e['grund'], 0, 300) . ' — zweimal versucht. Unter Kanäle verbinden steht, woran es liegt.', 'kanaele'); } catch (Throwable $y) { }
        }
        return $aus;
    }

    /**
     * G3: Nach jeder Freigabe dasselbe — geht der Kanal automatisch, kommt das Stück auf den nächsten
     * Sendeplatz; sonst sagt der Satz, was zu tun ist (Paket bzw. per Handy).
     */
    public static function nachFreigabe(int $id): string
    {
        $x = MkInhalt::laden($id);
        if ($x === null) { return 'Freigegeben.'; }
        $m = self::moeglich($x);
        if ($m['auto']) {
            $slot = self::naechsterSlot((string) $x['plattform']);
            if (self::planen($id, $slot) === null) {
                return '„' . $x['titel'] . '“ freigegeben — geht am ' . date('d.m. \u\m H:i', strtotime($slot)) . ' automatisch raus.';
            }
        }
        require_once __DIR__ . '/MkHandy.php';
        $handy = MkHandy::planen($id);
        if ($handy !== null) {
            return '„' . $x['titel'] . '“ freigegeben — kommt am ' . date('d.m. \\u\\m H:i', strtotime($handy)) . ' per Telegram aufs Handy zum Posten.';
        }
        return '„' . $x['titel'] . '“ freigegeben — ' . rtrim($m['grund'] ?: 'Link und Paket stehen am Stück', '.') . '.';
    }

    /* ------------------------------------------------------------------ */
    /* Paket                                                               */
    /* ------------------------------------------------------------------ */

    /** Was im Paket steht — auch für die Kette prüfbar. @return array<string,string> Dateiname => Inhalt (Medien als Pfad mit Präfix „@“) */
    public static function paketInhalt(array $x): array
    {
        $f = $x['f'];
        $link = MkInhalt::link($x);
        $pl = MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform'];
        $dateien = ['text.txt' => MkInhalt::kopiertext($x) . "\n"];
        if ($link) { $dateien['link.txt'] = $link . "\n"; }
        $schritte = match ($x['format']) {
            'google_anzeige' => "1. Google Ads Editor öffnen → Konto → Kampagne und Anzeigengruppe wählen.\n2. Konto > Importieren > aus Datei: google-ads-editor.csv.\n3. Prüfen, „Änderungen veröffentlichen“.\nOder im Google-Ads-Konto: Neue Anzeige → Responsive Suchanzeige → Felder aus text.txt kopieren, finale URL aus link.txt.",
            'meta_anzeige' => "1. Werbeanzeigenmanager öffnen → Kampagne „Leads“ oder „Traffic“.\n2. Anzeige: Primärtexte, Überschriften, Beschreibung und Knopf aus text.txt, Bild/Video aus diesem Paket.\n3. Website-URL: der Link aus link.txt — nur so zählt der Klick bis zum Kunden.\n4. Zielgruppe wie im Zielgruppen-Profil (Abschnitt „Bezahlte Werbung“).",
            'profil' => "1. Google Unternehmensprofil öffnen (business.google.com oder in der Google-Suche „Mein Unternehmen“).\n2. „Beitrag hinzufügen“ → „Neuigkeiten“, Text aus text.txt, Bild aus diesem Paket.\n3. Schaltfläche „Weitere Informationen“, Link aus link.txt.",
            'story' => "1. Story anlegen, je Folie Titel und Text aus text.txt.\n2. Link-Sticker mit dem Link aus link.txt.",
            'reel' => "1. Video aus diesem Paket hochladen (oder nach dem Drehbuch in text.txt selbst drehen).\n2. Beschreibung aus text.txt; den Link in die Bio bzw. den ersten Kommentar.",
            'karussell' => "1. Für jede Folie aus text.txt ein Bild anlegen (Titel groß, Text klein).\n2. Als Karussell hochladen, Begleittext aus text.txt; Link in die Bio.",
            default => "1. Bild aus diesem Paket und Text aus text.txt auf $pl veröffentlichen.\n2. Den Link aus link.txt dorthin setzen, wo er klickbar ist (Bio, Kommentar, Knopf).",
        };
        $dateien['liesmich.txt'] = "Vecom Design · Inhalt #{$x['id']} · {$x['titel']}\n$pl · " . (MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) . "\n\n$schritte\n\nDanach in der Verwaltung „Als veröffentlicht markieren“ drücken.\n";
        if ($x['format'] === 'google_anzeige') {
            $kopf = ['Campaign', 'Ad group'];
            $zeile = [(string) ($x['kampagne_name'] ?? 'Vecom'), (string) ($x['titel'])];
            for ($i = 1; $i <= 15; $i++) { $kopf[] = "Headline $i"; $zeile[] = (string) ($f['ueberschriften'][$i - 1] ?? ''); }
            for ($i = 1; $i <= 4; $i++) { $kopf[] = "Description $i"; $zeile[] = (string) ($f['beschreibungen'][$i - 1] ?? ''); }
            array_push($kopf, 'Path 1', 'Path 2', 'Final URL'); array_push($zeile, (string) ($f['pfad1'] ?? ''), (string) ($f['pfad2'] ?? ''), (string) ($link ?? ''));
            $csv = fopen('php://temp', 'w+');
            fputcsv($csv, $kopf, ',', '"', '\\'); fputcsv($csv, $zeile, ',', '"', '\\');
            rewind($csv); $dateien['google-ads-editor.csv'] = (string) stream_get_contents($csv); fclose($csv);
            if (!empty($f['keywords'])) { $dateien['keywords.txt'] = implode("\n", $f['keywords']) . "\n"; }
            /* Marketing-Studio 6 (S5): Suchbegriffe als Wortgruppe und genau, dazu die Ausschlüsse — fertig für den Google Ads Editor. */
            if (!empty($f['keywords']) || !empty($f['ausschluesse'])) {
                $csv = fopen('php://temp', 'w+');
                fputcsv($csv, ['Campaign', 'Ad group', 'Keyword', 'Criterion Type'], ',', '"', '\\');
                foreach ((array) ($f['keywords'] ?? []) as $kw) {
                    foreach (['Phrase', 'Exact'] as $typ) { fputcsv($csv, [$zeile[0], $zeile[1], (string) $kw, $typ], ',', '"', '\\'); }
                }
                foreach ((array) ($f['ausschluesse'] ?? []) as $kw) { fputcsv($csv, [$zeile[0], '', (string) $kw, 'Campaign Negative Phrase'], ',', '"', '\\'); }
                rewind($csv); $dateien['google-keywords.csv'] = (string) stream_get_contents($csv); fclose($csv);
            }
            $istDe = ($x['land'] ?? 'IT') === 'DE';
            $dateien['liesmich.txt'] .= "\nEINSTELLUNGEN DER KAMPAGNE (Vorschlag)\n"
                . '- Standort: ' . ($istDe ? 'Deutschland (oder die Bundesländer, in denen du Kunden willst)' : 'Sizilien — für den Anfang die Provinz Agrigent und Nachbarprovinzen') . ", Option „Präsenz: Personen an diesem Ort“.\n"
                . '- Sprache: ' . ($istDe ? 'Deutsch' : 'Italienisch') . ".\n"
                . "- Budget: 5–10 € am Tag zum Start, Gebotsstrategie „Klicks maximieren“ mit Höchstgebot, nach 2–3 Wochen auf „Conversions“ umstellen.\n"
                . "- google-keywords.csv: Suchbegriffe als Wortgruppe und genau, dazu ausschließende Keywords auf Kampagnenebene (Konto > Importieren).\n"
                . "- Erfolg zählt die Verwaltung: Website-Checks und Leads über den Link — nicht die Klicks.\n";
        }
        foreach (MkMedium::zuInhalt((int) $x['id']) as $m) {
            if ($m['status'] === 'neu' && MkMedium::gewaehlt((int) $x['id'], (string) $m['art'])) { continue; }   // gewählte gehen vor
            $dateien[($m['art'] === 'video' ? 'video-' : 'bild-') . (int) $m['id'] . '.' . (MkMedium::MIME[$m['mime']] ?? 'bin')] = '@' . MkMedium::ordner() . '/' . basename((string) $m['datei']);
        }
        return $dateien;
    }

    /** ZIP bauen und ausliefern. */
    public static function paketSenden(array $x): never
    {
        [$k] = MkInhalt::kampagne($x);
        $x['kampagne_name'] = $k['name'] ?? 'Vecom';
        $pfad = tempnam(sys_get_temp_dir(), 'mkpaket');
        $zip = new ZipArchive();
        if ($pfad === false || $zip->open($pfad, ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('ZIP nicht anlegbar.'); }
        foreach (self::paketInhalt($x) as $name => $inhalt) {
            if (str_starts_with($inhalt, '@')) { if (is_file(substr($inhalt, 1))) { $zip->addFile(substr($inhalt, 1), $name); } }
            else { $zip->addFromString($name, $inhalt); }
        }
        $zip->close();
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($pfad));
        header('Content-Disposition: attachment; filename="vecom-inhalt-' . (int) $x['id'] . '-' . $x['plattform'] . '.zip"');
        header('X-Content-Type-Options: nosniff');
        readfile($pfad);
        @unlink($pfad);
        exit;
    }
}
