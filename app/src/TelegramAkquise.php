<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Events.php';
require_once __DIR__ . '/Ablauf.php';
require_once __DIR__ . '/Akquise.php';
require_once __DIR__ . '/AkquiseGate.php';
require_once __DIR__ . '/AkquiseText.php';
require_once __DIR__ . '/AkquiseVersand.php';

/* ==========================================================================
   TelegramAkquise.php — Chef-Zentrale Schritt 2: „✅ Freigaben“ (01.10.2026,
   Uwe: „fahre fort“).

   KEIN ZWEITER WEG

   Telegram ruft genau die Funktionen auf, die auch die Verwaltung aufruft:
   AkquiseVersand::freigeben / verwerfen und AkquiseGate::sperren. Deren
   Prüfungen (Textprüfung, gesperrte Firma, nur Entwürfe) gelten also auch
   hier — Telegram kann nichts, was die Verwaltung nicht auch könnte, und
   nichts an ihnen vorbei.

   Was hier NICHT geht, mit Absicht:
     - Senden. Freigeben heißt „gelesen und geprüft“; verschickt wird mit
       einem eigenen Klick (in der Verwaltung, später hier — Schritt 3).
     - Briefe freigeben. Die Freigabe eines Briefs schaltet zugleich die
       Analyse-Seite mit dem QR-Code ein und gehört zum Druck — das bleibt
       in der Verwaltung.

   WER HAT GEKLICKT

   Auth kennt nur die Sitzung der Verwaltung. Für die Dauer einer Aktion
   steht deshalb der verbundene Zugang als Handelnder da (mit „Telegram“
   im Namen) — so tragen Akquise-Protokoll, Prüfspur und „freigegeben von“
   den richtigen Namen, und niemand kann Telegram-Klicks von Verwaltungs-
   Klicks nicht unterscheiden.
   ========================================================================== */
final class TelegramAkquise
{
    /** So viele Entwürfe zeigt die Liste auf einmal. */
    public const LISTE = 8;

    /** Platz für den Mailtext in einer Telegram-Nachricht (4096 Zeichen insgesamt). */
    public const TEXT_MAX = 2400;

    /**
     * Offene Entwürfe, die eine Freigabe brauchen — die wichtigsten zuerst
     * (höchster Score, dann die ältesten).
     *
     * @return array{zahl:int, liste:list<array>}
     */
    public static function entwuerfe(): array
    {
        $bed = "v.status = 'entwurf' AND f.gesperrt = 0";
        $zahl = (int) Db::wert("SELECT COUNT(*) FROM akq_vorlagen v JOIN akq_firmen f ON f.id = v.firma_id WHERE $bed", [], 0);
        $liste = Db::all("SELECT v.id, v.kanal, v.sprache, v.created_at, f.name, f.stadt, f.score
                            FROM akq_vorlagen v JOIN akq_firmen f ON f.id = v.firma_id
                           WHERE $bed ORDER BY f.score DESC, v.id ASC LIMIT " . self::LISTE);
        return ['zahl' => $zahl, 'liste' => $liste];
    }

    /**
     * Ein Entwurf mit allem, was vor der Freigabe zu lesen ist.
     *
     * @return array|null  vorlage, firma, gate (Rechtsprüfung für den Kanal),
     *                     hinweise (Textprüfung), grund, darfFreigeben, warum
     */
    public static function entwurf(int $vorlageId): ?array
    {
        $v = Db::one('SELECT * FROM akq_vorlagen WHERE id = ?', [$vorlageId]);
        if (!$v) { return null; }
        $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $v['firma_id']]);
        if (!$f) { return null; }
        $gate = AkquiseGate::pruefen($f, (string) $v['kanal']);
        $befunde = $v['audit_id'] ? Akquise::befunde((int) $v['audit_id']) : [];
        $hinweise = AkquiseText::pruefen((string) $v['betreff'], (string) $v['text'], (string) $v['sprache'], $befunde, (string) $v['kanal'], $f);
        $top = json_decode((string) ($f['top_probleme'] ?? ''), true);
        $grund = is_array($top) && $top ? implode(' · ', array_map('strval', array_slice($top, 0, 3))) : '';

        // Freigeben in Telegram nur, wenn nichts dagegen spricht — sonst sagt der Bot, warum nicht.
        $warum = null;
        if ($v['status'] !== 'entwurf') { $warum = 'Dieser Text ist kein Entwurf mehr (' . $v['status'] . ').'; }
        elseif ((int) $f['gesperrt'] === 1) { $warum = 'Der Betrieb ist gesperrt.'; }
        elseif ($v['kanal'] !== 'email') { $warum = 'Briefe und andere Wege werden in der Verwaltung freigegeben (Druck, QR-Code, Analyse-Seite).'; }
        elseif ($hinweise) { $warum = 'Der Text hat noch Beanstandungen — bitte in der Verwaltung überarbeiten.'; }
        elseif (in_array($gate['status'], [AkquiseGate::NICHT, AkquiseGate::UNKLAR], true)) { $warum = 'Rechtliche Prüfung: ' . AkquiseGate::STATUS[$gate['status']] . '.'; }

        return ['vorlage' => $v, 'firma' => $f, 'gate' => $gate, 'hinweise' => $hinweise, 'grund' => $grund,
                'darfFreigeben' => $warum === null, 'warum' => $warum];
    }

    /** Die drei Taten. Werfen RuntimeException mit einem Satz für den Bot. */
    public static function freigeben(int $userId, int $vorlageId): void
    {
        $e = self::entwurf($vorlageId);
        if (!$e) { throw new RuntimeException('Diesen Entwurf gibt es nicht mehr.'); }
        if (!$e['darfFreigeben']) { throw new RuntimeException((string) $e['warum']); }
        self::als($userId, static fn() => AkquiseVersand::freigeben($vorlageId));
    }

    public static function verwerfen(int $userId, int $vorlageId): void
    {
        self::als($userId, static fn() => AkquiseVersand::verwerfen($vorlageId));
    }

    /** „Nicht kontaktieren“: der ganze Betrieb auf die Sperrliste — wie „Sperren“ in der Verwaltung. */
    public static function sperren(int $userId, int $vorlageId): string
    {
        $v = Db::one('SELECT firma_id FROM akq_vorlagen WHERE id = ?', [$vorlageId]);
        if (!$v) { throw new RuntimeException('Diesen Entwurf gibt es nicht mehr.'); }
        $fid = (int) $v['firma_id'];
        self::als($userId, static fn() => AkquiseGate::sperren($fid, 'Nicht kontaktieren (über Telegram)'));
        return (string) Db::wert('SELECT name FROM akq_firmen WHERE id = ?', [$fid], '');
    }

    /**
     * Für die Dauer einer Tat handelt der verbundene Zugang — mit „(Telegram)“
     * im Namen. $_SESSION ist im Webhook kein Cookie, nur ein Feld dieser
     * Anfrage; danach steht es wieder wie vorher.
     */
    private static function als(int $userId, callable $tat): void
    {
        $u = Db::one("SELECT id, name, email FROM users WHERE id = ? AND active = 1 AND role = 'admin'", [$userId]);
        if (!$u) { throw new RuntimeException('Kein berechtigter Zugang.'); }
        $vorher = $_SESSION ?? null;
        $_SESSION = ['uid' => (int) $u['id'], 'name' => trim((string) ($u['name'] ?: $u['email'])) . ' (Telegram)', 'rolle' => 'admin'];
        try { $tat(); }
        finally { if ($vorher === null) { unset($_SESSION); } else { $_SESSION = $vorher; } }
    }

    /** Die Rückfrage aus Ablauf::TRAGWEITE — dieselbe wie vor dem Knopf in der Verwaltung. */
    public static function rueckfrage(string $tat, string $ersatz): string
    {
        return (string) (Ablauf::rueckfrage($tat)['frage'] ?? $ersatz);
    }
}
