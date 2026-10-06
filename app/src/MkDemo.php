<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';

/* ==========================================================================
   MkDemo.php — kostenlose Demo-Vorschau der neuen Startseite
   (Marketing-Studio 10, 01.10.2026, Uwe: „ja“ zu S1 — „Demo-Vorschau“).

   WAS GESCHIEHT
     1. Ein Interessent aus dem Website-Check (persönlicher Bereich, Mail
        bestätigt) drückt „Ja, Vorschau erstellen“. Das ist seine
        ausdrückliche Bitte — der Wortlaut steht in zustimmungen (Art demo).
     2. Ein Auftrag für Uwes PC: Claude Code liest die bisherige Website
        (WebFetch, Uwes Abo) und baut daraus EINE Startseite — nur belegte
        Angaben, keine erfundenen Preise oder Bewertungen, kein JavaScript.
     3. Uwe sieht sie unter „Freigeben“ an: Freigeben (dann geht der Link per
        Mail an den Interessenten und steht in seinem Bereich), Nochmal bauen
        (mit Hinweis) oder Verwerfen.
     4. Der Link (demo.php?t=…) gilt 30 Tage, ohne Skripte (CSP sandbox),
        nicht in Suchmaschinen, mit Band „Vorschau von Vecom Design“.

   WAS NICHT GESCHIEHT
     Keine Vorschau ohne Bitte des Interessenten, keine Mail ohne Uwes
     Freigabe, keine zweite Vorschau je Kunde, höchstens JE_TAG am Tag
     (schützt Uwes Abo vor Missbrauch).
   ========================================================================== */

final class MkDemo
{
    public const GUELTIG_TAGE = 30;
    public const JE_TAG = 8;
    public const MAX_HTML = 160_000;
    public const STUFEN = ['anfrage', 'vorhaben', 'angaben', 'angebot'];
    public const FASSUNG = 'demo-2026-10-01';
    public const STATUS = ['wartet' => 'in Arbeit', 'fertig' => 'wartet auf dich', 'freigegeben' => 'verschickt', 'verworfen' => 'verworfen', 'fehler' => 'nicht geklappt', 'geloescht' => 'gelöscht'];

    public const ZUSTIMMUNG = [
        'it' => 'Desidero un’anteprima gratuita della nuova home page della mia attività. Vecom Design può leggere a questo scopo il mio sito attuale e mandarmi il link per e-mail. Senza impegno.',
        'de' => 'Ich möchte eine kostenlose Vorschau der neuen Startseite meines Betriebs. Vecom Design darf dafür meine bisherige Website lesen und mir den Link per E-Mail schicken. Unverbindlich.',
        'en' => 'I would like a free preview of the new home page for my business. Vecom Design may read my current website for this and send me the link by email. No obligation.',
    ];

    public const TEXTE = [
        'titel'   => ['it' => 'Gratis: come potrebbe essere il suo nuovo sito', 'de' => 'Kostenlos: So könnte Ihre neue Startseite aussehen', 'en' => 'Free: what your new home page could look like'],
        'text'    => ['it' => 'Costruiamo un’anteprima della sua nuova home page con le informazioni del suo sito attuale — da guardare sul telefono, senza impegno.',
                      'de' => 'Wir bauen eine Vorschau Ihrer neuen Startseite aus den Angaben Ihrer bisherigen Website — zum Ansehen auf dem Handy, unverbindlich.',
                      'en' => 'We build a preview of your new home page from the information on your current website — to view on your phone, no obligation.'],
        'knopf'   => ['it' => 'Sì, voglio l’anteprima', 'de' => 'Ja, Vorschau erstellen', 'en' => 'Yes, create the preview'],
        'wartet'  => ['it' => 'La sua anteprima è in preparazione. Le scriviamo appena è pronta, di solito entro 1–2 giorni lavorativi.',
                      'de' => 'Ihre Vorschau ist in Arbeit. Sie bekommen eine E-Mail, sobald sie fertig ist — meist innerhalb von 1–2 Werktagen.',
                      'en' => 'Your preview is being prepared. We will email you as soon as it is ready, usually within 1–2 working days.'],
        'fertig'  => ['it' => 'La sua anteprima è pronta (valida fino al {datum}).', 'de' => 'Ihre Vorschau ist fertig (gültig bis {datum}).', 'en' => 'Your preview is ready (valid until {datum}).'],
        'ansehen' => ['it' => 'Vedere l’anteprima', 'de' => 'Vorschau ansehen', 'en' => 'View the preview'],
        'danke'   => ['it' => 'Grazie! Ci mettiamo al lavoro.', 'de' => 'Danke! Wir machen uns an die Arbeit.', 'en' => 'Thank you! We are on it.'],
        'band'    => ['it' => 'Anteprima gratuita di Vecom Design · non ancora online · valida fino al {datum}', 'de' => 'Kostenlose Vorschau von Vecom Design · noch nicht online · gültig bis {datum}',
                      'en' => 'Free preview by Vecom Design · not online yet · valid until {datum}'],
        'weg'     => ['it' => 'Questa anteprima non è più disponibile.', 'de' => 'Diese Vorschau ist nicht mehr verfügbar.', 'en' => 'This preview is no longer available.'],
    ];

    public const MAIL = [
        'it' => ['L’anteprima del nuovo sito per {firma}', "Buongiorno,\n\ncome richiesto, ecco l’anteprima gratuita della nuova home page per {firma}:\n\n{link}\n\nÈ una bozza costruita con le informazioni pubbliche del suo sito attuale: foto, testi e colori li definiamo insieme prima di qualsiasi lavoro. Il link è valido fino al {datum}. Nessun impegno.\n\nSe le piace, risponda semplicemente a questa e-mail. Il suo spazio personale: {bereich}\n\n{inhaber} · Vecom Design"],
        'de' => ['Die Vorschau der neuen Website für {firma}', "Guten Tag,\n\nwie gewünscht hier die kostenlose Vorschau der neuen Startseite für {firma}:\n\n{link}\n\nEs ist ein Entwurf aus den öffentlichen Angaben Ihrer bisherigen Website: Fotos, Texte und Farben legen wir vor jeder Arbeit gemeinsam mit Ihnen fest. Der Link gilt bis {datum}. Unverbindlich.\n\nWenn sie Ihnen gefällt, antworten Sie einfach auf diese Mail. Ihr persönlicher Bereich: {bereich}\n\n{inhaber} · Vecom Design"],
        'en' => ['The preview of the new website for {firma}', "Hello,\n\nas requested, here is the free preview of the new home page for {firma}:\n\n{link}\n\nIt is a draft built from the public information on your current website: photos, texts and colours are decided together with you before any work. The link is valid until {datum}. No obligation.\n\nIf you like it, simply reply to this email. Your personal area: {bereich}\n\n{inhaber} · Vecom Design"],
    ];

    public static function t(string $k, string $sprache): string
    {
        return (string) (self::TEXTE[$k][$sprache] ?? self::TEXTE[$k]['it'] ?? '');
    }

    private static function spr(string $s): string
    {
        return in_array($s, ['it', 'de', 'en'], true) ? $s : 'it';
    }

    public static function laden(int $id): ?array
    {
        return Db::one('SELECT * FROM mk_demos WHERE id = ?', [$id]) ?: null;
    }

    /** Die (eine) Vorschau eines Kunden — oder null. */
    public static function fuerKunde(int $kundeId): ?array
    {
        try { return Db::one('SELECT * FROM mk_demos WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$kundeId]) ?: null; } catch (Throwable $e) { return null; }
    }

    /** Der Betrieb aus der Akquise, wenn er eine Website hat und noch keine Vorschau angefragt wurde. */
    public static function moeglich(int $kundeId): ?array
    {
        try {
            if (self::fuerKunde($kundeId) !== null) { return null; }
            $af = Db::one("SELECT * FROM akq_firmen WHERE customer_id = ? AND url IS NOT NULL AND url <> '' ORDER BY id DESC LIMIT 1", [$kundeId]);
            return $af && preg_match('~^https?://~i', (string) $af['url']) ? $af : null;
        } catch (Throwable $e) { return null; }
    }

    /** Der Interessent bittet um die Vorschau. @return int|string  Vorschau-ID oder Hinweis */
    public static function anfordern(int $kundeId, string $sprache): int|string
    {
        require_once __DIR__ . '/Zustimmung.php';
        require_once __DIR__ . '/MkAuftrag.php';
        $sp = self::spr($sprache);
        $af = self::moeglich($kundeId);
        if ($af === null) { return 'nicht_moeglich'; }
        if ((int) Db::wert('SELECT COUNT(*) FROM mk_demos WHERE created_at >= CURDATE()', [], 0) >= self::JE_TAG) { return 'zuviel'; }
        Zustimmung::festhalten('demo', $kundeId, self::ZUSTIMMUNG[$sp], $sp, self::FASSUNG);
        $id = (int) Db::insert('mk_demos', ['customer_id' => $kundeId, 'akq_firma_id' => (int) $af['id'], 'token' => bin2hex(random_bytes(16)),
            'sprache' => $sp, 'url' => mb_substr((string) $af['url'], 0, 300)]);
        self::auftrag($id, $sp, (string) ($af['branche'] ?? ''));
        try { require_once __DIR__ . '/Akquise.php'; Akquise::protokoll((int) $af['id'], 'demo', 'Demo-Vorschau angefragt (Kundenbereich, Wortlaut in den Zustimmungen)'); } catch (Throwable $e) { }
        try { Events::melden('demo', 'Demo-Vorschau angefragt: ' . (string) $af['name'], 'gut', 'Dein PC baut sie in den nächsten Minuten (Claude über dein Abo). Danach unter „Freigeben“ ansehen und freigeben.', self::freigabeLink($sp)); } catch (Throwable $e) { }
        return $id;
    }

    private static function auftrag(int $demoId, string $sp, string $branche): int
    {
        $a = (int) Db::insert('mk_auftraege', ['art' => 'demo', 'branche' => mb_substr($branche, 0, 40), 'land' => $sp === 'de' ? 'DE' : 'IT',
            'parameter' => json_encode(['demo_id' => $demoId], JSON_UNESCAPED_SLASHES)]);
        Db::run('UPDATE mk_demos SET auftrag_id = ? WHERE id = ?', [$a, $demoId]);
        return $a;
    }

    public static function freigabeLink(string $sp): string
    {
        return 'freigabe?land=' . ($sp === 'de' ? 'DE' : 'IT') . '#demos';
    }

    /** Was der PC für Claude braucht: belegte Angaben aus der Akquise, nichts Geheimes. */
    public static function fuerPc(array $auftrag): ?array
    {
        $p = json_decode((string) $auftrag['parameter'], true) ?: [];
        $d = self::laden((int) ($p['demo_id'] ?? 0));
        if ($d === null) { return null; }
        $af = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $d['akq_firma_id']]) ?: [];
        $verbessern = [];
        try {
            foreach (['Akquise', 'AkquiseScore', 'AkquiseText'] as $k) { require_once __DIR__ . "/$k.php"; }
            $au = Akquise::letzterAudit((int) ($af['id'] ?? 0));
            foreach (array_slice(AkquiseScore::topBefunde($au ? Akquise::befunde((int) $au['id']) : []), 0, 5) as $b) {
                $s = AkquiseText::saetze($b, $af, 'de');
                if ($s !== null) { $verbessern[] = trim($s[0] . ' → ' . $s[2]); }
            }
        } catch (Throwable $e) { }
        return ['demo_id' => (int) $d['id'], 'betrieb' => (string) ($af['name'] ?? ''), 'url' => (string) $d['url'], 'stadt' => (string) ($af['stadt'] ?? ''),
                'adresse' => (string) ($af['adresse'] ?? ''), 'telefon' => (string) ($af['telefon'] ?? ''), 'branche' => (string) ($af['branche'] ?? ''),
                'sprache' => (string) $d['sprache'], 'hinweis' => (string) ($d['hinweis'] ?? ''), 'verbessern' => $verbessern, 'max_bytes' => self::MAX_HTML];
    }

    /**
     * Vom PC: die fertige Seite (Worker-Aktion marketing_demo_melden).
     * @return array{ok:bool, hinweis?:string, bytes?:int}
     */
    public static function melden(array $d): array
    {
        $a = Db::one("SELECT * FROM mk_auftraege WHERE id = ? AND art = 'demo' AND status = 'laeuft'", [(int) ($d['auftrag_id'] ?? 0)]);
        if (!$a) { return ['ok' => false, 'hinweis' => 'Kein laufender Demo-Auftrag.']; }
        $p = json_decode((string) $a['parameter'], true) ?: [];
        $demo = self::laden((int) ($p['demo_id'] ?? 0));
        if ($demo === null) { return ['ok' => false, 'hinweis' => 'Vorschau unbekannt.']; }
        $html = (string) ($d['html'] ?? '');
        if (strlen($html) < 400 || stripos($html, '<body') === false) { return ['ok' => false, 'hinweis' => 'Keine vollständige Seite.']; }
        if (strlen($html) > self::MAX_HTML) { return ['ok' => false, 'hinweis' => 'Seite zu groß (' . strlen($html) . ' Bytes, höchstens ' . self::MAX_HTML . ').']; }
        $sauber = self::bereinigen($html);
        $quellen = array_values(array_filter(array_map(static fn($u) => mb_substr(trim((string) $u), 0, 300), (array) ($d['quellen'] ?? [])), static fn($u) => (bool) preg_match('~^https?://\S+$~', $u)));
        Db::update('mk_demos', (int) $demo['id'], ['html' => $sauber, 'zusammenfassung' => mb_substr(trim(strip_tags((string) ($d['zusammenfassung'] ?? ''))), 0, 1500),
            'quellen' => json_encode(array_slice($quellen, 0, 12), JSON_UNESCAPED_SLASHES), 'status' => 'fertig', 'fehler' => null]);
        $firma = (string) Db::wert('SELECT name FROM akq_firmen WHERE id = ?', [(int) $demo['akq_firma_id']], '');
        try { Events::melden('demo', 'Demo-Vorschau fertig: ' . $firma, 'gut', 'Ansehen, dann freigeben — erst dann geht der Link an den Interessenten.', self::freigabeLink((string) $demo['sprache'])); } catch (Throwable $e) { }
        return ['ok' => true, 'bytes' => strlen($sauber)];
    }

    /** Auftrag gescheitert (aus MkAuftrag::melden). */
    public static function gescheitert(array $auftrag, string $text): void
    {
        $p = json_decode((string) $auftrag['parameter'], true) ?: [];
        $demo = self::laden((int) ($p['demo_id'] ?? 0));
        if ($demo === null || $demo['status'] !== 'wartet') { return; }
        Db::update('mk_demos', (int) $demo['id'], ['status' => 'fehler', 'fehler' => mb_substr($text, 0, 900) ?: null]);
        try { Events::melden('demo', 'Demo-Vorschau nicht geklappt', 'info', $text !== '' ? $text : 'Unter „Freigeben“ nochmal bauen lassen.', self::freigabeLink((string) $demo['sprache'])); } catch (Throwable $e) { }
    }

    /**
     * Gürtel zur CSP: Skripte, Rahmen, Objekte, Formulare-Ziele, Ereignis-
     * Attribute und javascript:-Adressen raus. Die Seite läuft ohnehin in
     * einer Sandbox ohne Skripte.
     */
    public static function bereinigen(string $html): string
    {
        $alt = libxml_use_internal_errors(true);
        $d = new DOMDocument();
        $d->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($alt);
        $weg = [];
        foreach (['script', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'base', 'form', 'noscript', 'template', 'portal'] as $tag) {
            foreach ($d->getElementsByTagName($tag) as $el) { $weg[] = $el; }
        }
        foreach ($d->getElementsByTagName('meta') as $el) { if ($el->hasAttribute('http-equiv')) { $weg[] = $el; } }
        foreach ($d->getElementsByTagName('link') as $el) {
            $rel = strtolower((string) $el->getAttribute('rel'));
            $href = (string) $el->getAttribute('href');
            if (!($rel === 'stylesheet' && str_starts_with($href, 'https://fonts.googleapis.com/')) && !in_array($rel, ['preconnect'], true)) { $weg[] = $el; }
        }
        foreach ($weg as $el) { $el->parentNode?->removeChild($el); }
        foreach ((new DOMXPath($d))->query('//@*') ?: [] as $at) {
            $n = strtolower($at->nodeName);
            $v = strtolower(preg_replace('/[\s\x00-\x1f]+/', '', (string) $at->nodeValue) ?? '');
            if (str_starts_with($n, 'on') || in_array($n, ['srcdoc', 'formaction', 'action', 'ping'], true)
                || (in_array($n, ['href', 'src', 'xlink:href', 'poster', 'background', 'data'], true) && preg_match('~^(javascript|vbscript|data:text|data:application)~', $v))) {
                $at->ownerElement?->removeAttribute($at->nodeName);
            }
        }
        /* saveHTML() mit Knoten: Umlaute bleiben Zeichen (ohne Knoten würden sie zu &ugrave; usw.). */
        $wurzel = $d->documentElement;
        $aus = $wurzel !== null ? (string) $d->saveHTML($wurzel) : '';
        return "<!doctype html>\n" . trim($aus);
    }

    /** Uwe gibt frei: Link per Mail an den Interessenten, 30 Tage gültig. */
    public static function freigeben(int $id): ?string
    {
        $demo = self::laden($id);
        if ($demo === null) { return 'Vorschau nicht gefunden.'; }
        if ($demo['status'] !== 'fertig' || trim((string) $demo['html']) === '') { return 'Nur eine fertige Vorschau lässt sich freigeben.'; }
        $k = Db::one('SELECT id, email, name, company FROM customers WHERE id = ?', [(int) $demo['customer_id']]);
        if (!$k || !filter_var((string) $k['email'], FILTER_VALIDATE_EMAIL)) { return 'Der Interessent hat keine gültige E-Mail-Adresse.'; }
        $bis = date('Y-m-d', strtotime('+' . self::GUELTIG_TAGE . ' days'));
        Db::update('mk_demos', $id, ['status' => 'freigegeben', 'freigegeben_am' => date('Y-m-d H:i:s'), 'gueltig_bis' => $bis]);
        $sp = self::spr((string) $demo['sprache']);
        $firma = (string) Db::wert('SELECT name FROM akq_firmen WHERE id = ?', [(int) $demo['akq_firma_id']], '') ?: (string) ($k['company'] ?: $k['name']);
        require_once __DIR__ . '/Mail.php';
        require_once __DIR__ . '/Firma.php';
        require_once __DIR__ . '/Kundenzugang.php';
        [$b, $t] = self::MAIL[$sp];
        $w = ['{firma}' => $firma, '{link}' => self::adresse($demo), '{datum}' => date('d.m.Y', strtotime($bis)),
              '{bereich}' => Kundenzugang::linkFuer((int) $k['id'], $sp), '{inhaber}' => Firma::get('inhaber', 'Uwe Vetter')];
        Mail::senden('demo', (string) $k['email'], strtr($b, $w), strtr($t, $w), ['customer_id' => (int) $k['id'], 'nurText' => true, 'sprache' => $sp]);
        Events::pruefspur('demo_freigegeben', 'mk_demos', $id, ['status' => 'fertig'], ['status' => 'freigegeben', 'gueltig_bis' => $bis]);
        try { require_once __DIR__ . '/Akquise.php'; Akquise::protokoll((int) $demo['akq_firma_id'], 'demo', 'Demo-Vorschau freigegeben, Link an ' . (string) $k['email']); } catch (Throwable $e) { }
        return null;
    }

    public static function verwerfen(int $id): ?string
    {
        $demo = self::laden($id);
        if ($demo === null) { return 'Vorschau nicht gefunden.'; }
        if ($demo['status'] === 'freigegeben') { return 'Schon verschickt — der Link läuft am ' . date('d.m.Y', strtotime((string) $demo['gueltig_bis'])) . ' von selbst ab.'; }
        Db::update('mk_demos', $id, ['status' => 'verworfen']);
        if ((int) $demo['auftrag_id'] > 0) { Db::run("UPDATE mk_auftraege SET status = 'abgebrochen' WHERE id = ? AND status IN ('wartet','laeuft')", [(int) $demo['auftrag_id']]); }
        Events::pruefspur('demo_verworfen', 'mk_demos', $id, ['status' => $demo['status']], ['status' => 'verworfen']);
        return null;
    }

    /** Nochmal bauen lassen — mit Uwes Hinweis für Claude. */
    public static function nochmal(int $id, string $hinweis): ?string
    {
        $demo = self::laden($id);
        if ($demo === null) { return 'Vorschau nicht gefunden.'; }
        $haengt = $demo['status'] === 'wartet' && !in_array((string) Db::wert('SELECT status FROM mk_auftraege WHERE id = ?', [(int) $demo['auftrag_id']], ''), ['wartet', 'laeuft'], true);
        /* 06.10.2026, Uwe: Änderungen auch an einer verschickten Vorschau — der Link ruht, bis du die neue Fassung freigibst. */
        if (!in_array($demo['status'], ['fertig', 'fehler', 'freigegeben'], true) && !$haengt) { return 'Geht nur bei einer fertigen, verschickten oder gescheiterten Vorschau.'; }
        Db::update('mk_demos', $id, ['status' => 'wartet', 'hinweis' => mb_substr(trim(strip_tags($hinweis)), 0, 600) ?: null, 'fehler' => null]);
        self::auftrag($id, (string) $demo['sprache'], (string) Db::wert('SELECT branche FROM akq_firmen WHERE id = ?', [(int) $demo['akq_firma_id']], ''));
        return null;
    }

    /** Alle Vorschauen eines Kunden für seine Akte (06.10.2026), neueste zuerst, ohne die Seite selbst. */
    public static function fuerKundeAlle(int $kundeId): array
    {
        try {
            return Db::all("SELECT d.id, d.status, d.sprache, d.url, d.hinweis, d.zusammenfassung, d.fehler, d.aufrufe, d.freigegeben_am, d.gueltig_bis, d.created_at, d.updated_at,
                                   d.token, (d.html IS NOT NULL AND d.html <> '') AS hat_seite, f.name AS firma
                              FROM mk_demos d LEFT JOIN akq_firmen f ON f.id = d.akq_firma_id
                             WHERE d.customer_id = ? AND d.status <> 'geloescht' ORDER BY d.id DESC LIMIT 20", [$kundeId]);
        } catch (Throwable $e) { return []; }
    }

    /**
     * Löschen (06.10.2026, Uwe: „… wieder löschen können“): auch eine verschickte Vorschau. Der Link ist sofort tot
     * (neuer Schlüssel, Seite gelöscht), ein laufender Bau wird abgebrochen. Die Zeile bleibt für die Prüfspur.
     */
    public static function loeschen(int $id): ?string
    {
        $demo = self::laden($id);
        if ($demo === null || $demo['status'] === 'geloescht') { return 'Vorschau nicht gefunden.'; }
        Db::update('mk_demos', $id, ['status' => 'geloescht', 'html' => null, 'token' => bin2hex(random_bytes(16)), 'gueltig_bis' => null]);
        if ((int) $demo['auftrag_id'] > 0) { Db::run("UPDATE mk_auftraege SET status = 'abgebrochen' WHERE id = ? AND status IN ('wartet','laeuft')", [(int) $demo['auftrag_id']]); }
        Events::pruefspur('demo_geloescht', 'mk_demos', $id, ['status' => $demo['status'], 'gueltig_bis' => $demo['gueltig_bis']], ['status' => 'geloescht']);
        try { require_once __DIR__ . '/Akquise.php'; if ((int) $demo['akq_firma_id'] > 0) { Akquise::protokoll((int) $demo['akq_firma_id'], 'demo', 'Demo-Vorschau gelöscht — der Link ist nicht mehr erreichbar'); } } catch (Throwable $e) { }
        return null;
    }

    /** Verlängern: eine verschickte Vorschau gilt GUELTIG_TAGE ab heute bzw. ab ihrem bisherigen Ende länger. Ohne neue Mail. */
    public static function verlaengern(int $id): ?string
    {
        $demo = self::laden($id);
        if ($demo === null) { return 'Vorschau nicht gefunden.'; }
        if ($demo['status'] !== 'freigegeben') { return 'Verlängern geht nur bei einer verschickten Vorschau.'; }
        $ab = max(strtotime('today'), strtotime((string) ($demo['gueltig_bis'] ?: 'today')));
        $bis = date('Y-m-d', strtotime('+' . self::GUELTIG_TAGE . ' days', $ab));
        Db::update('mk_demos', $id, ['gueltig_bis' => $bis]);
        Events::pruefspur('demo_verlaengert', 'mk_demos', $id, ['gueltig_bis' => $demo['gueltig_bis']], ['gueltig_bis' => $bis]);
        return null;
    }

    public static function adresse(array $demo): string
    {
        require_once __DIR__ . '/Config.php';
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/demo.php?t=' . (string) $demo['token'];
    }

    /** Für die öffentliche Seite: nur freigegeben und gültig. Zählt den Aufruf. */
    public static function zeigen(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) { return null; }
        $demo = Db::one("SELECT * FROM mk_demos WHERE token = ? AND status = 'freigegeben' AND gueltig_bis >= CURDATE()", [$token]);
        if (!$demo) { return null; }
        Db::run('UPDATE mk_demos SET aufrufe = aufrufe + 1 WHERE id = ?', [(int) $demo['id']]);
        return $demo;
    }

    /** Die Seite samt Band „Vorschau von Vecom Design“ (bzw. „nur für Uwe“). */
    public static function seite(array $demo, bool $verwaltung = false): string
    {
        $sp = self::spr((string) $demo['sprache']);
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $text = $verwaltung ? 'Vorschau nur für dich — ' . (self::STATUS[(string) $demo['status']] ?? (string) $demo['status'])
            : strtr(self::t('band', $sp), ['{datum}' => date('d.m.Y', strtotime((string) ($demo['gueltig_bis'] ?: 'today')))]);
        $band = '<div style="position:fixed;left:12px;right:12px;bottom:12px;z-index:2147483647;display:flex;gap:12px;align-items:center;justify-content:space-between;'
            . 'background:#0d0c0a;color:#f3ece0;border:1px solid #c79a43;border-radius:14px;padding:10px 14px;font:500 13.5px/1.4 system-ui,-apple-system,sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.35)">'
            . '<span>' . $h($text) . '</span><a href="https://vecom-design.it/?lang=' . $sp . '" style="color:#f5e2a6;white-space:nowrap;text-decoration:underline">Vecom Design</a></div>';
        $html = (string) $demo['html'];
        $mit = preg_replace('~(<body\b[^>]*>)~i', '$1' . $band, $html, 1, $n);
        return $n > 0 && is_string($mit) ? $mit : $band . $html;
    }

    /** Kopfzeilen: keine Skripte, keine Formulare, nicht einbettbar außer bei uns, nicht in Suchmaschinen. */
    public static function kopfzeilen(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; img-src https: data:; style-src 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation");
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
    }

    /** Für den Freigabe-Reiter: was auf Uwe wartet und was läuft. */
    public static function liste(string $land): array
    {
        try {
            return Db::all("SELECT d.id, d.status, d.sprache, d.url, d.hinweis, d.zusammenfassung, d.fehler, d.aufrufe, d.gueltig_bis, d.created_at, f.name AS firma, f.stadt,
                                   a.status AS auftrag_status, a.ergebnis AS auftrag_ergebnis
                              FROM mk_demos d LEFT JOIN akq_firmen f ON f.id = d.akq_firma_id LEFT JOIN mk_auftraege a ON a.id = d.auftrag_id
                             WHERE (d.sprache = 'de') = (? = 'DE') AND (d.status IN ('wartet','fertig','fehler') OR (d.status = 'freigegeben' AND d.gueltig_bis >= CURDATE()))
                          ORDER BY d.status = 'fertig' DESC, d.id DESC LIMIT 20", [$land]);
        } catch (Throwable $e) { return []; }
    }
}
