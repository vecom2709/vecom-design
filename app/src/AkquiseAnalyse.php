<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseScore.php';
require_once __DIR__ . '/AkquiseText.php';

/**
 * Die persoenliche Analyse-Seite: vecom-design.it/analyse.php?t=…
 *
 * WAS DORT STEHT -- UND WAS NIE
 *
 * Firmenname, Website, das mobile Bildschirmfoto, hoechstens drei BELEGTE
 * Befunde mit Wirkung und Loesung, eine Experience-Idee (wenn es eine
 * gibt) und der Weg zu Vecom. Nie: Score, Stufe, unbelegte Vermutungen,
 * Notizen, Compliance, Kontaktdaten, andere Firmen.
 *
 * Die Seite ist aus, bis Uwe sie einschaltet; sie verfaellt nach 60 Tagen;
 * sie ist nie im Index (noindex im Kopf und in der Antwort); und sie
 * verschwindet sofort, wenn die Firma gesperrt wird.
 */
final class AkquiseAnalyse
{
    public const GUELTIG_TAGE = 60;

    public static function anlegen(int $firmaId): array
    {
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [$firmaId]);
        if (!$f) { throw new RuntimeException('Firma nicht gefunden.'); }
        if ((int) $f['gesperrt'] === 1) { throw new RuntimeException('Die Firma ist gesperrt.'); }
        $a = Akquise::letzterAudit($firmaId);
        if (!$a || $a['status'] !== 'fertig') { throw new RuntimeException('Ohne fertiges Audit keine Analyse-Seite.'); }
        $alt = Db::one('SELECT * FROM akq_analysen WHERE firma_id = ? AND audit_id = ?', [$firmaId, (int) $a['id']]);
        if ($alt) { return $alt; }
        $id = Db::insert('akq_analysen', [
            'firma_id' => $firmaId, 'audit_id' => (int) $a['id'], 'token' => bin2hex(random_bytes(20)),
            'sprache' => AkquiseText::spracheFuer($f), 'aktiv' => 0,
            'gueltig_bis' => date('Y-m-d', strtotime('+' . self::GUELTIG_TAGE . ' days')),
        ]);
        Akquise::protokoll($firmaId, 'analyse', 'Analyse-Seite vorbereitet (noch nicht sichtbar)');
        /* Den ausführlichen Bericht gleich mit prüfen (28.09.2026) -- dann muss der Betrieb beim Öffnen nicht warten. */
        try { require_once __DIR__ . '/WebBericht.php'; WebBericht::sicherFuerFirma($firmaId, 'analyse'); } catch (Throwable $e) { }
        return Db::one('SELECT * FROM akq_analysen WHERE id = ?', [$id]) ?? [];
    }

    public static function umschalten(int $analyseId, bool $an): void
    {
        $x = Db::one('SELECT * FROM akq_analysen WHERE id = ?', [$analyseId]);
        if (!$x) { throw new RuntimeException('Analyse-Seite nicht gefunden.'); }
        if ($an) { try { require_once __DIR__ . '/WebBericht.php'; WebBericht::sicherFuerFirma((int) $x['firma_id'], 'analyse'); } catch (Throwable $e) { } }
        Db::update('akq_analysen', $analyseId, ['aktiv' => $an ? 1 : 0,
            'gueltig_bis' => $an ? date('Y-m-d', strtotime('+' . self::GUELTIG_TAGE . ' days')) : $x['gueltig_bis']]);
        Akquise::protokoll((int) $x['firma_id'], 'analyse', $an ? 'Analyse-Seite eingeschaltet' : 'Analyse-Seite ausgeschaltet');
        Events::pruefspur($an ? 'akquise_analyse_an' : 'akquise_analyse_aus', 'akq_analysen', $analyseId);
    }

    /**
     * Branche aus der Recherche → Skizze der Startseite (vorschau.js). Ohne
     * passendes Bild keine Skizze: Ein Restaurantfoto über einer Kanzlei wäre
     * schlechter als gar keins. Eine Stelle für Analyse-Seite und Text B.
     */
    public const SKIZZE = [
        'restaurant' => 'restaurant', 'bar_cafe' => 'restaurant', 'baeckerei' => 'restaurant',
        'hotel' => 'beherbergung', 'ferienwohnung' => 'beherbergung', 'agriturismo' => 'beherbergung', 'tourismus' => 'beherbergung',
        'bau' => 'immobilien', 'immobilien' => 'immobilien', 'autohaus' => 'autohaus', 'werkstatt' => 'autohaus',
        'friseur' => 'friseur', 'beauty' => 'friseur', 'einzelhandel' => 'mode',
    ];

    public static function skizze(?string $branche): ?string
    {
        return self::SKIZZE[(string) $branche] ?? null;
    }

    public static function adresse(array $x): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/analyse.php?t=' . $x['token'];
    }

    /**
     * Liefert alles fuer die oeffentliche Seite -- oder null. Zaehlt den
     * Aufruf, ausser $zaehlen = false (die Einwilligung vom Kasten der Seite
     * ist kein zweiter Besuch).
     */
    public static function oeffentlich(string $token, bool $zaehlen = true): ?array
    {
        if (!preg_match('~^[a-f0-9]{40}$~', $token)) { return null; }
        $x = Db::one('SELECT * FROM akq_analysen WHERE token = ?', [$token]);
        if (!$x || (int) $x['aktiv'] !== 1) { return null; }
        if ($x['gueltig_bis'] !== null && strtotime((string) $x['gueltig_bis'] . ' 23:59:59') < time()) { return null; }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $x['firma_id']]);
        if (!$f || (int) $f['gesperrt'] === 1) { return null; }
        $a = Db::one('SELECT * FROM akq_audits WHERE id = ?', [(int) $x['audit_id']]);
        if (!$a) { return null; }
        $befunde = array_values(array_filter(AkquiseScore::topBefunde(Akquise::befunde((int) $a['id'])),
            static fn($b) => $b['status'] === 'VERIFIED'));
        if (!$zaehlen) { return ['analyse' => $x, 'firma' => $f, 'audit' => $a, 'befunde' => array_slice($befunde, 0, 3)]; }
        Db::run('UPDATE akq_analysen SET aufrufe = aufrufe + 1, zuletzt_am = NOW() WHERE id = ?', [(int) $x['id']]);
        if ((int) $x['aufrufe'] === 0) {
            Akquise::protokoll((int) $f['id'], 'analyse', 'Analyse-Seite zum ersten Mal geöffnet');
            try { Events::melden('akquise_analyse', 'Akquise: Analyse-Seite geöffnet — ' . $f['name'], 'info', null, 'akquise/' . $f['id']); }
            catch (Throwable $e) { }
        }
        return ['analyse' => $x, 'firma' => $f, 'audit' => $a, 'befunde' => array_slice($befunde, 0, 3)];
    }
}
