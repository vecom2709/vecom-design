<?php
/**
 * Kunden, die sich selbst melden (01.10.2026, Uwe: Ja zu S1 und S4).
 * Steht oben unter „Beiträge & Kampagnen › Kampagnen“.
 *  S1  „Kommentiere STICHWORT“ → automatische private Nachricht mit dem Check-Link
 *  S4  Anzeige mit Sofortformular (Meta Lead Ads) → Bestätigung und Analyse automatisch
 * Beides eingehend: Der Betrieb gibt selbst den Anstoß. Anzeigen schaltet Uwe von Hand.
 */
require_once dirname(__DIR__) . '/src/MkKommentar.php';
$ekMeta = MetaSeite::einstellungen();
$ekBereit = MetaSeite::bereit();
$ekAn = MkKommentar::an();
$ekZ = MkKommentar::zahlen();
$ekW = MkKommentar::stichworte();
try { $ekLeads = (int) Db::wert("SELECT COUNT(*) FROM akq_meta_leads WHERE created_at >= NOW() - INTERVAL 30 DAY AND status = 'ok'", [], 0); } catch (Throwable $e) { $ekLeads = 0; } ?>
<section class="block" id="kommentar" aria-labelledby="ek-titel" style="margin-bottom:16px">
  <h2 id="ek-titel">Kunden, die sich selbst melden <span class="mehr">eingehend — der Betrieb gibt den Anstoß, die Antwort kommt automatisch</span></h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px">
    <div style="border:1px solid var(--linie2);border-radius:12px;padding:12px 14px;display:grid;gap:8px;align-content:start">
      <h3 style="margin:0;font-size:15.5px">Kommentar → Nachricht <?= $ekAn ? '<span class="marke2 gut">an</span>' : '<span class="marke2">aus</span>' ?></h3>
      <p class="mk-fein" style="margin:0;line-height:1.55">Reels und Beiträge sagen „Kommentiere CHECK“ (Italien: „Commenta SITO“). Wer das Stichwort kommentiert, bekommt genau eine private Nachricht mit dem Link zum kostenlosen Website-Check — Instagram und Facebook. Kein Nachfassen.</p>
      <p style="margin:0;font-size:13.5px">Stichwörter: <?= Fmt::h(implode(', ', array_keys($ekW))) ?> <span class="mk-fein">(dazu die aus den Zielgruppen)</span></p>
      <p style="margin:0;font-size:13.5px">Letzte 30 Tage: <b><?= (int) $ekZ['beantwortet'] ?></b> beantwortet<?= $ekZ['fehler'] > 0 ? ' · <span class="marke2 warnung">' . (int) $ekZ['fehler'] . ' gescheitert</span>' : '' ?></p>
      <?php if (!$ekBereit): ?>
        <p class="mk-fein" style="margin:0">Zuerst die Facebook-Seite verbinden: <a href="<?= Fmt::h(url('akquise/regeln') . '#wege') ?>">Akquise › Regeln › Facebook-Seite und Instagram</a>.</p>
      <?php else: ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <form method="post" action="<?= Fmt::h(url('kampagnen')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="kommentar_schalten"><input type="hidden" name="an" value="<?= $ekAn ? '0' : '1' ?>">
            <button class="knopf<?= $ekAn ? '' : ' haupt' ?>"><?= $ekAn ? 'Ausschalten' : 'Einschalten' ?></button></form>
          <form method="post" action="<?= Fmt::h(url('kampagnen')) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= Fmt::h(Csrf::token()) ?>"><input type="hidden" name="tat" value="kommentar_abo">
            <button class="knopf">Kommentar-Meldungen der Seite einschalten</button></form>
        </div>
        <details><summary class="mk-fein" style="cursor:pointer">Einmal in der Meta-App einrichten (Instagram)</summary>
          <ol class="mk-fein" style="margin:6px 0 0;padding-left:20px;line-height:1.6">
            <li>developers.facebook.com › deine App › Webhooks: Objekt „Instagram“ wählen, dieselbe Adresse wie für WhatsApp eintragen (…/wa-webhook.php, gleiches Prüfwort), Feld „comments“ abonnieren.</li>
            <li>Der Seiten-Schlüssel braucht die Rechte pages_messaging, pages_read_engagement, instagram_manage_comments und instagram_manage_messages — beim Erzeugen anhaken.</li>
            <li>Instagram-Konto ist ein Business-Konto und mit der Facebook-Seite verbunden; in der Instagram-App unter Einstellungen › Nachrichten „Zugriff auf Nachrichten erlauben“ einschalten.</li>
          </ol></details>
      <?php endif; ?>
      <?php if (!$ekAn): ?><p class="mk-fein" style="margin:0">Solange es aus ist, schreiben die Beiträge einen Link-Aufruf statt „Kommentiere …“.</p><?php endif; ?>
    </div>
    <div style="border:1px solid var(--linie2);border-radius:12px;padding:12px 14px;display:grid;gap:8px;align-content:start">
      <h3 style="margin:0;font-size:15.5px">Anzeige mit Sofortformular <?= $ekBereit ? '<span class="marke2 gut">verbunden</span>' : '<span class="marke2">nicht verbunden</span>' ?></h3>
      <p class="mk-fein" style="margin:0;line-height:1.55">Meta-Anzeige ortsgenau mit Formular (Website, E-Mail, Häkchen zur Einwilligung). Jede Meldung löst sofort die Bestätigungsmail aus, danach kommt die Analyse — und sie steht in der Verwaltung. Die Anzeige selbst schaltest du im Werbeanzeigenmanager; die Texte schreibt das Content-Studio (Format „Meta-Anzeige“).</p>
      <p style="margin:0;font-size:13.5px">Letzte 30 Tage: <b><?= $ekLeads ?></b> Formulare mit Einwilligung</p>
      <p style="margin:0"><a class="knopf" href="<?= Fmt::h(url('akquise/regeln') . '#wege') ?>">Anleitung und Verbindung</a></p>
    </div>
  </div>
</section>
