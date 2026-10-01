<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkInhalt.php';
require_once __DIR__ . '/MkMedium.php';
require_once __DIR__ . '/MkAuftrag.php';

/* ==========================================================================
   MkVorherNachher.php — Vorher/Nachher-Beiträge aus fertigen Projekten
   (Marketing-Studio 9, 01.10.2026, Uwe: „ja“ zu S4).

   WAS GESCHIEHT
     1. Der Kunde stimmt im Kundenbereich zu (nach der Übergabe), dass Vecom
        seine neue Website mit dem Namen des Betriebs zeigen darf. Wortlaut
        in zustimmungen (Art referenz), Zeitpunkt in customers.referenz_am.
     2. In der Verwaltung (Inhalte) steht er dann unter „Vorher/Nachher“ mit
        einem Knopf. Der legt drei Entwürfe an — Instagram, Facebook,
        Telegram — in der Sprache des Kunden, mit deutscher Fassung, und
        einen Bild-Auftrag für den PC.
     3. Der PC fotografiert die neue Website (Handy), holt das Bildschirmfoto
        der alten aus dem Website-Check der Akquise (falls es eines gibt),
        setzt beides als Vorher/Nachher-Bild zusammen und lädt es hoch. Das
        Bild steht dann bei allen drei Entwürfen.
     4. Freigegeben wird wie immer — im Reiter „Freigeben“ oder per Telegram.

   WAS NICHT GESCHIEHT
     Kein Beitrag ohne Zustimmung, keine Zahlen, die nicht gemessen sind
     („x von 12 Punkten“ nur aus dem Check der neuen Seite), keine Aussagen
     über Umsatz oder Gäste.
   ========================================================================== */

final class MkVorherNachher
{
    public const FASSUNG = 'referenz-2026-10-01';

    public const ZUSTIMMUNG = [
        'it' => 'Vecom Design può mostrare il mio nuovo sito come esempio (prima e dopo, con il nome della mia attività) su Instagram, Facebook, Telegram e sul sito vecom-design.it. Posso ritirare il consenso in qualsiasi momento qui; i post già pubblicati vengono tolti su mia richiesta.',
        'de' => 'Vecom Design darf meine neue Website als Beispiel zeigen (Vorher/Nachher, mit dem Namen meines Betriebs) auf Instagram, Facebook, Telegram und auf vecom-design.it. Ich kann die Zustimmung hier jederzeit zurückziehen; bereits veröffentlichte Beiträge werden auf meinen Wunsch entfernt.',
        'en' => 'Vecom Design may show my new website as an example (before and after, with the name of my business) on Instagram, Facebook, Telegram and on vecom-design.it. I can withdraw this consent here at any time; posts already published will be removed on my request.',
    ];

    /** Zustimmung geben oder zurückziehen (aus dem Kundenbereich). */
    public static function zustimmen(int $kundeId, bool $ja, string $sprache): void
    {
        require_once __DIR__ . '/Zustimmung.php';
        $sp = isset(self::ZUSTIMMUNG[$sprache]) ? $sprache : 'it';
        if ($ja) {
            Zustimmung::festhalten('referenz', $kundeId, self::ZUSTIMMUNG[$sp], $sp, self::FASSUNG);
            Db::run('UPDATE customers SET referenz_am = NOW() WHERE id = ?', [$kundeId]);
        } else {
            Db::run('UPDATE customers SET referenz_am = NULL WHERE id = ?', [$kundeId]);
        }
        Events::pruefspur($ja ? 'referenz_ja' : 'referenz_zurueck', 'customers', $kundeId, [], ['referenz' => $ja ? 1 : 0]);
        if (!$ja) {
            try { Events::melden('referenz', 'Zustimmung zum Vorher/Nachher zurückgezogen', 'info', 'Bereits veröffentlichte Beiträge zu diesem Kunden bitte entfernen.', 'kunden/' . $kundeId); } catch (Throwable $e) { }
        }
    }

    /** Kunden mit Zustimmung und veröffentlichter Website, für die es noch keinen Vorher/Nachher-Beitrag gibt. */
    public static function kandidaten(): array
    {
        try {
            return Db::all("SELECT c.id, c.name, c.company, c.city, c.sprache, c.referenz_am, p.veroeffentlicht_domain AS domain, p.veroeffentlicht_am
                              FROM customers c JOIN projects p ON p.customer_id = c.id
                             WHERE c.referenz_am IS NOT NULL AND p.veroeffentlicht_am IS NOT NULL AND p.veroeffentlicht_domain IS NOT NULL AND p.veroeffentlicht_domain <> ''
                               AND NOT EXISTS (SELECT 1 FROM mk_inhalte i WHERE i.kunde_id = c.id AND i.status <> 'verworfen')
                          GROUP BY c.id ORDER BY MAX(p.veroeffentlicht_am) DESC LIMIT 20");
        } catch (Throwable $e) { return []; }
    }

    /** Das Bildschirmfoto der alten Website aus dem Website-Check der Akquise (Dateipfad oder null). */
    public static function vorherBild(int $kundeId): ?string
    {
        try {
            $n = (string) Db::wert("SELECT a.screenshot_mobil FROM akq_audits a JOIN akq_firmen f ON f.id = a.firma_id
                                     WHERE f.customer_id = ? AND a.screenshot_mobil IS NOT NULL AND a.screenshot_mobil <> '' ORDER BY a.id DESC LIMIT 1", [$kundeId], '');
            if ($n === '') { return null; }
            require_once __DIR__ . '/Ablage.php';
            $pfad = Ablage::ordner() . '/akquise/' . basename($n);
            return is_file($pfad) ? $pfad : null;
        } catch (Throwable $e) { return null; }
    }

    /** Die drei Texte (Instagram, Facebook, Telegram) in einer Sprache. @return array<string,array{titel:string,felder:array}> */
    public static function texte(string $sp, string $betrieb, string $ort, ?int $punkte): array
    {
        $ortTeil = $ort !== '' ? ($sp === 'de' ? ' in ' . $ort : ' a ' . $ort) : '';
        $mess = $punkte !== null ? ($sp === 'de' ? " Im Website-Check von Vecom: $punkte von 12 Punkten in Ordnung." : " Nel nostro check: $punkte punti su 12 a posto.") : '';
        if ($sp === 'de') {
            $text = "Vorher – nachher: Die neue Website von $betrieb$ortTeil ist online.\n\nGebaut fürs Handy, mit allem, was Kunden suchen — und die Domain gehört dem Betrieb.$mess\n\nWie steht Ihre Website da?";
            return [
                'instagram' => ['titel' => "Vorher/Nachher: $betrieb", 'felder' => ['hook' => "Vorher – nachher: $betrieb", 'text' => $text, 'hashtags' => ['#vorhernachher', '#webdesign', '#website'], 'cta' => 'Kostenlos prüfen — Link in der Bio']],
                'facebook'  => ['titel' => "Vorher/Nachher: $betrieb (Facebook)", 'felder' => ['hook' => "Vorher – nachher: $betrieb", 'text' => $text, 'hashtags' => ['#vorhernachher', '#webdesign'], 'cta' => 'Website kostenlos prüfen']],
                'telegram'  => ['titel' => "Vorher/Nachher: $betrieb (Telegram)", 'felder' => ['text' => "Vorher – nachher: die neue Website von $betrieb$ortTeil.\n\n" . ($mess !== '' ? trim($mess) . "\n\n" : '') . 'Wie steht Ihre Website da?', 'knopf' => 'Kostenlos prüfen']],
            ];
        }
        $text = "Prima e dopo: il nuovo sito di $betrieb$ortTeil è online.\n\nPensato per il telefono, con tutto quello che i clienti cercano — e il dominio resta all’attività.$mess\n\nCom’è messo il suo sito?";
        return [
            'instagram' => ['titel' => "Prima/dopo: $betrieb", 'felder' => ['hook' => "Prima e dopo: $betrieb", 'text' => $text, 'hashtags' => ['#primaedopo', '#sitoweb', '#webdesign'], 'cta' => 'Analisi gratuita — link in bio']],
            'facebook'  => ['titel' => "Prima/dopo: $betrieb (Facebook)", 'felder' => ['hook' => "Prima e dopo: $betrieb", 'text' => $text, 'hashtags' => ['#primaedopo', '#sitoweb'], 'cta' => 'Analisi gratuita del sito']],
            'telegram'  => ['titel' => "Prima/dopo: $betrieb (Telegram)", 'felder' => ['text' => "Prima e dopo: il nuovo sito di $betrieb$ortTeil.\n\n" . ($mess !== '' ? trim($mess) . "\n\n" : '') . 'Com’è messo il suo sito?', 'knopf' => 'Analisi gratuita']],
        ];
    }

    /**
     * Drei Entwürfe und den Bild-Auftrag anlegen.
     * @return int|string  Bild-Auftrag oder Hinweis
     */
    public static function erstellen(int $kundeId, ?int $punkte = null): int|string
    {
        $k = null;
        foreach (self::kandidaten() as $c) { if ((int) $c['id'] === $kundeId) { $k = $c; break; } }
        if ($k === null) { return 'Für diesen Kunden geht es nicht: Zustimmung fehlt, die Website ist noch nicht veröffentlicht oder es gibt schon einen Beitrag.'; }
        $sp = ((string) $k['sprache']) === 'de' ? 'de' : 'it';
        $land = $sp === 'de' ? 'DE' : 'IT';
        $betrieb = trim((string) ($k['company'] ?: $k['name']));
        if ($betrieb === '') { return 'Der Kunde hat keinen Namen — erst in der Kundenakte eintragen.'; }
        $branche = (string) Db::wert('SELECT branche FROM akq_firmen WHERE customer_id = ? AND branche IS NOT NULL ORDER BY id DESC LIMIT 1', [$kundeId], '');
        $url = 'https://' . preg_replace('~^https?://~', '', rtrim((string) $k['domain'], '/'));
        $original = self::texte($sp, $betrieb, (string) ($k['city'] ?? ''), $punkte);
        $deutsch = $sp === 'it' ? self::texte('de', $betrieb, (string) ($k['city'] ?? ''), $punkte) : null;
        $ids = [];
        foreach ($original as $plattform => $t) {
            $format = $plattform === 'telegram' ? 'telegram' : 'beitrag';
            $x = MkInhalt::pruefen(['format' => $format, 'plattform' => $plattform, 'sprache' => $sp, 'titel' => $t['titel'], 'felder' => $t['felder'],
                'begruendung' => 'Vorher/Nachher aus einem fertigen Projekt — der Kunde hat zugestimmt (' . date('d.m.Y', strtotime((string) $k['referenz_am'])) . ').',
                'uebersetzung' => $deutsch ? trim(implode("\n\n", array_filter([(string) ($deutsch[$plattform]['felder']['hook'] ?? ''), (string) ($deutsch[$plattform]['felder']['text'] ?? ''), (string) ($deutsch[$plattform]['felder']['cta'] ?? $deutsch[$plattform]['felder']['knopf'] ?? '')]))) : null], $land);
            if (is_string($x)) { return $x; }
            $x['felder'] = json_encode($x['felder'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $ids[] = (int) Db::insert('mk_inhalte', $x + ['kunde_id' => $kundeId, 'branche' => $branche, 'land' => $land]);
        }
        $param = ['inhalt_id' => $ids[0], 'medium' => 'bild', 'modell' => 'vorher-nachher', 'format' => '4:5', 'prompt' => '', 'credits_ca' => 0,
                  'titel' => mb_substr('Vorher/Nachher ' . $betrieb, 0, 80), 'vn' => ['url' => $url, 'betrieb' => mb_substr($betrieb, 0, 80), 'sprache' => $sp,
                  'kunde_id' => $kundeId, 'vorher' => self::vorherBild($kundeId) !== null, 'geschwister' => array_slice($ids, 1)]];
        $id = (int) Db::insert('mk_auftraege', ['art' => 'medien', 'branche' => $branche, 'land' => $land, 'parameter' => json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        Events::protokoll('vorher_nachher', 'Vorher/Nachher für „' . $betrieb . '“ angelegt', null, null, null, ['auftrag_id' => $id]);
        return $id;
    }

    /** Für den PC: das alte Bildschirmfoto zu einem laufenden Vorher/Nachher-Auftrag (base64) — sonst nichts. */
    public static function vorherFuerAuftrag(int $auftragId): array
    {
        $a = Db::one("SELECT * FROM mk_auftraege WHERE id = ? AND art = 'medien' AND status = 'laeuft'", [$auftragId]);
        $p = $a ? (json_decode((string) $a['parameter'], true) ?: []) : [];
        if (!$a || empty($p['vn']['kunde_id'])) { return ['ok' => false, 'hinweis' => 'Kein laufender Vorher/Nachher-Auftrag.']; }
        $kunde = (int) $p['vn']['kunde_id'];
        if (Db::wert('SELECT referenz_am FROM customers WHERE id = ?', [$kunde], null) === null) { return ['ok' => false, 'hinweis' => 'Die Zustimmung des Kunden fehlt (zurückgezogen?).']; }
        $pfad = self::vorherBild($kunde);
        return ['ok' => true, 'bild' => $pfad !== null ? base64_encode((string) file_get_contents($pfad)) : null];
    }

    /** Nach dem Hochladen: dasselbe Bild auch bei Facebook- und Telegram-Entwurf, überall gewählt. */
    public static function verteilen(array $auftrag): void
    {
        $p = json_decode((string) $auftrag['parameter'], true) ?: [];
        if (empty($p['vn'])) { return; }
        $m = Db::one("SELECT * FROM mk_medien WHERE auftrag_id = ? ORDER BY id DESC LIMIT 1", [(int) $auftrag['id']]);
        if (!$m) { return; }
        MkMedium::status((int) $m['id'], 'gewaehlt');
        foreach ((array) ($p['vn']['geschwister'] ?? []) as $iid) {
            $neu = $m; unset($neu['id'], $neu['token'], $neu['created_at'], $neu['updated_at']);
            $neu['inhalt_id'] = (int) $iid; $neu['status'] = 'gewaehlt';
            Db::insert('mk_medien', array_intersect_key($neu, array_flip(['inhalt_id', 'auftrag_id', 'art', 'datei', 'mime', 'bytes', 'sha256', 'format', 'modell', 'credits', 'prompt', 'quelle_url', 'status'])));
        }
        try { Events::melden('vorher_nachher', 'Vorher/Nachher fertig: ' . (string) ($p['vn']['betrieb'] ?? ''), 'gut', 'Drei Entwürfe mit Bild — unter „Freigeben“ mit Ja oder Nein durchgehen.', 'freigabe?land=' . (((string) ($p['vn']['sprache'] ?? 'it')) === 'de' ? 'DE' : 'IT')); } catch (Throwable $e) { }
    }
}
