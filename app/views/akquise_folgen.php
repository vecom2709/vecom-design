<?php
/** @var array $vorlagen @var array $folgen @var bool $an @var bool $test @var bool $versandAn @var array $waHand */
/* Folge-Mails (27.09.2026). Oben: läuft es, und wenn nicht, warum nicht --
   in einem Satz. Darunter die laufenden Folgen, dann die fünf Texte je
   Sprache. Freigegeben wird je Text; jede Änderung macht ihn wieder zum
   Entwurf. */
$akqTeil = 'folgen';
$sprachen = ['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'];
$frei = 0; $gesamt = 0;
foreach ($vorlagen as $je) { foreach ($je as $v) { $gesamt++; $frei += $v['status'] === 'freigegeben' ? 1 : 0; } }
$laufend = count(array_filter($folgen, static fn($f) => $f['status'] === 'laeuft'));
[$ampel, $satz] = match (true) {
    !$an => ['', 'Aus. Einschalten unter Regeln & Versand → Schalter („Folge-Mails“, dazu der Hauptschalter „Automatik“).'],
    $frei === 0 => ['gelb', 'An, aber noch kein Text freigegeben — es geht nichts raus.'],
    $test => ['gelb', 'An im Testbetrieb: Fällige Mails werden nur simuliert und im Protokoll vermerkt.'],
    !$versandAn => ['gelb', 'An, aber der E-Mail-Versand ist ausgeschaltet (Regeln & Versand → E-Mails verschicken).'],
    default => ['gruen', 'Läuft: Freigegebene Texte gehen automatisch raus, sobald ein Schritt fällig ist.'],
};
$statusWort = ['laeuft' => ['gut', 'läuft'], 'pausiert' => ['warnung', 'pausiert'], 'beendet' => ['', 'beendet']];
?>
<style>
  .fo-erkl{color:var(--dim);font-size:14px;line-height:1.55;max-width:72ch;margin:6px 0 0}
  .fo-stand{display:flex;gap:10px;align-items:flex-start;padding:14px 16px;border:1px solid var(--linie);border-radius:14px;background:var(--flaeche);margin:0 0 16px;font-size:14.5px}
  .fo-stand .akq-ampel{white-space:normal;align-items:flex-start}.fo-stand .akq-ampel i{margin-top:5px}
  .fo-zahlen{display:flex;gap:16px;flex-wrap:wrap;margin-top:8px;font-size:var(--fs-klein);color:var(--leise)}
  .fo-schritt{border:1px solid var(--linie);border-radius:14px;margin:0 0 12px;background:var(--flaeche)}
  .fo-schritt>summary{cursor:pointer;padding:14px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-size:14.5px}
  .fo-schritt>summary b{min-width:64px}
  .fo-schritt[open]>summary{border-bottom:1px solid var(--linie)}
  .fo-sprachen{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px;padding:14px 16px}
  .fo-sprachen form{margin:0}
  .fo-sprachen textarea{min-height:230px;font-size:13.5px;line-height:1.5}
  .fo-kopf{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px}
  .fo-platz{font-size:var(--fs-klein);color:var(--leise);margin:0 0 14px}
  .fo-platz code{font-size:var(--fs-klein);background:var(--flaeche2);padding:1px 5px;border-radius:5px;margin-right:2px}
  .fo-knoepfe{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
</style>

<div class="kopf"><div><h1>Folge-Mails</h1>
  <p class="fo-erkl">Nur an Betriebe, die per Bestätigungsmail eingewilligt haben. Fünf Mails in 30 Tagen (Tag 0, 3, 7, 14, 30).
    Die Folge hält an, sobald eine Antwort kommt, und endet bei Abmeldung, Sperre oder Auftrag. Vor jeder Mail prüft das Gate neu.</p></div></div>

<?php require __DIR__ . '/akquise_reiter.php'; ?>

<div class="fo-stand"><span class="akq-ampel <?= $ampel ?>"><i aria-hidden="true"></i><span><?= Fmt::h($satz) ?>
  <span class="fo-zahlen"><span><?= $frei ?> von <?= $gesamt ?> Texten freigegeben</span><span><?= $laufend ?> Folgen laufen</span>
    <a href="<?= Fmt::h(url('akquise/regeln#schalter')) ?>" style="text-decoration:underline">Schalter</a></span></span></span></div>

<style>
  .wh-liste{display:grid;gap:12px;margin-top:12px}
  .wh-karte{border:1px solid var(--linie);border-radius:14px;padding:14px 16px;background:var(--flaeche);display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px 18px;align-items:start}
  .wh-text{grid-column:1/-1;white-space:pre-line;font-size:13.5px;line-height:1.5;color:var(--dim);overflow-wrap:anywhere;max-width:72ch}
  .wh-knoepfe{display:flex;gap:8px;flex-wrap:wrap;align-items:center;grid-column:1/-1}
  .wh-knoepfe form{margin:0}
  @media (min-width:800px){.wh-text{grid-column:1}.wh-knoepfe{grid-column:2;grid-row:1/span 2;flex-direction:column;align-items:stretch}}
</style>
<div class="block" id="whatsapp">
  <h2>WhatsApp von Hand<?= $waHand ? ' — ' . count($waHand) . ' bereit' : '' ?></h2>
  <?php if (!$waHand): ?>
  <p class="akq-klein" style="max-width:72ch">Gerade liegt nichts bereit. Sobald bei einem Betrieb, der WhatsApp ausdrücklich erlaubt hat, ein Folge-Schritt fällig ist, steht er hier mit fertigem Text und dem Knopf „In WhatsApp öffnen“ — und du bekommst eine Meldung.</p>
  <?php else: ?>
  <p class="akq-klein" style="max-width:72ch">Diese Betriebe haben WhatsApp ausdrücklich erlaubt. „In WhatsApp öffnen“ öffnet deine App mit dem fertigen Text — du drückst nur noch auf Senden; der Schritt gilt damit als verschickt.
    Antwort im Handy? „Antwort kam“ hält die Folge an, „STOP bekommen“ sperrt den Betrieb. Nach zwei Tagen ohne Tipp geht die Mail (wenn eine Adresse da ist).</p>
  <div class="wh-liste">
  <?php foreach ($waHand as $wh): $whText = AkquiseFolge::handText($wh, (int) $wh['wa_hand_schritt'], (string) $wh['sprache'], true); ?>
    <div class="wh-karte">
      <div><a href="<?= Fmt::h(url('akquise/' . (int) $wh['firma_id'])) ?>" style="color:var(--cyan);font-weight:600"><?= Fmt::h((string) $wh['name']) ?></a>
        <div class="akq-klein"><?= (int) $wh['wa_hand_schritt'] ?>. <?= Fmt::h(AkquiseFolge::SCHRITT_NAME[(int) $wh['wa_hand_schritt']] ?? '') ?> · <?= Fmt::h(strtoupper((string) $wh['sprache'])) ?><?= $wh['stadt'] ? ' · ' . Fmt::h((string) $wh['stadt']) : '' ?></div></div>
      <div class="wh-text"><?= Fmt::h($whText) ?></div>
      <div class="wh-knoepfe">
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>" target="_blank"><?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="akq_folge_wa_hand"><input type="hidden" name="folge" value="<?= (int) $wh['id'] ?>">
          <button class="knopf haupt" style="width:100%">In WhatsApp öffnen</button></form>
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="akq_folge_pausieren"><input type="hidden" name="folge" value="<?= (int) $wh['id'] ?>">
          <button class="knopf klein" style="width:100%">Antwort kam</button></form>
        <form method="post" action="<?= Fmt::h(url('akquise')) ?>" data-frage="Diesen Betrieb dauerhaft sperren? Er bekommt dann auf keinem Weg mehr etwas." data-ja="Ja, sperren"><?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="akq_folge_wa_stop"><input type="hidden" name="folge" value="<?= (int) $wh['id'] ?>">
          <button class="knopf klein" style="width:100%">STOP bekommen</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="block" id="laufend">
  <h2>Betriebe in der Folge</h2>
  <?php if (!$folgen): ?>
    <p class="akq-klein">Noch keiner. Eine Folge beginnt automatisch, wenn ein Betrieb seine Einwilligung per Mail bestätigt (Website-Check, Analyse-Seite oder Einwilligungs-Link).</p>
  <?php else: ?>
  <div class="tabellenrahmen"><table><thead><tr><th>Betrieb</th><th>Stand</th><th>Nächster Schritt</th><th></th></tr></thead><tbody>
    <?php foreach ($folgen as $fo): $sw = $statusWort[$fo['status']] ?? ['', $fo['status']]; $naechst = (int) $fo['schritt'] + 1; ?>
      <tr><td><a href="<?= Fmt::h(url('akquise/' . (int) $fo['firma_id'])) ?>" style="color:var(--cyan)"><?= Fmt::h((string) $fo['name']) ?></a>
            <div class="akq-klein"><?= Fmt::h((string) ($fo['stadt'] ?? '')) ?> · <?= Fmt::h(strtoupper((string) $fo['sprache'])) ?></div></td>
          <td><span class="marke2 <?= $sw[0] ?>"><?= Fmt::h($sw[1]) ?></span> <span class="akq-klein"><?= (int) $fo['schritt'] ?> von 5 verschickt</span>
            <?php if ($fo['grund']): ?><div class="akq-klein" style="margin-top:4px"><?= Fmt::h((string) $fo['grund']) ?></div><?php endif; ?></td>
          <td class="akq-klein"><?php if ($fo['status'] === 'laeuft' && isset(AkquiseFolge::SCHRITT_NAME[$naechst])): ?>
              <?= $naechst ?>. <?= Fmt::h(AkquiseFolge::SCHRITT_NAME[$naechst]) ?><br><?= $fo['naechst_am'] ? Fmt::h(date('d.m.Y H:i', strtotime((string) $fo['naechst_am']))) : '' ?>
            <?php else: ?>—<?php endif; ?></td>
          <td style="text-align:right;white-space:nowrap">
            <?php foreach (['laeuft' => [['akq_folge_pausieren', 'Pausieren']], 'pausiert' => [['akq_folge_fortsetzen', 'Fortsetzen'], ['akq_folge_beenden', 'Beenden']]][$fo['status']] ?? [] as [$t, $w]): ?>
              <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="display:inline"<?= $t === 'akq_folge_beenden' ? ' data-frage="Folge-Mails für diesen Betrieb endgültig beenden?" data-ja="Ja, beenden"' : '' ?>><?= Csrf::feld() ?>
                <input type="hidden" name="tat" value="<?= $t ?>"><input type="hidden" name="folge" value="<?= (int) $fo['id'] ?>">
                <button class="knopf klein"><?= $w ?></button></form>
            <?php endforeach; ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<h2 class="akq-kat" style="margin-top:22px">Die fünf Texte</h2>
<p class="fo-platz">Platzhalter werden beim Versand gefüllt:
  <?php foreach (['{anrede}' => '„Guten Tag Maria Rossi,“ (ohne Namen nur „Guten Tag,“)', '{firma}' => 'Name des Betriebs', '{website}' => 'seine Domain', '{analyse}' => 'Analyse-Seite oder Website-Check', '{bedarf}' => 'Bedarf & Preis', '{beispiele}' => 'Arbeiten auf der Startseite', '{termin}' => 'Satz mit Buchungslink (leer, solange keine freien Zeiten)', '{inhaber}' => 'dein Name', '{absender}' => 'Vecom Design', '{telefon}' => 'deine Nummer'] as $p => $w): ?>
    <code><?= Fmt::h($p) ?></code><?= Fmt::h($w) ?> ·
  <?php endforeach; ?> Der Abmeldelink kommt automatisch darunter.</p>
<?php foreach (AkquiseFolge::TAGE as $schritt => $tag): $je = $vorlagen[$schritt] ?? [];
  $f = count(array_filter($je, static fn($v) => $v['status'] === 'freigegeben')); ?>
  <details class="fo-schritt"<?= $f < count($je) ? ' open' : '' ?>>
    <summary><b>Tag <?= $tag ?></b> <?= $schritt ?>. <?= Fmt::h(AkquiseFolge::SCHRITT_NAME[$schritt]) ?>
      <span class="marke2 <?= $f === count($je) && $f > 0 ? 'gut' : ($f ? 'warnung' : '') ?>"><?= $f ?> von <?= count($je) ?> freigegeben</span></summary>
    <div class="fo-sprachen">
      <?php foreach ($sprachen as $sp => $spName): $v = $je[$sp] ?? null; if (!$v) { continue; } ?>
        <div id="v<?= (int) $v['id'] ?>">
          <div class="fo-kopf"><b><?= $spName ?></b>
            <span class="marke2 <?= $v['status'] === 'freigegeben' ? 'gut' : '' ?>"><?= $v['status'] === 'freigegeben' ? 'freigegeben' : 'Entwurf' ?> · Fassung <?= (int) $v['fassung'] ?></span></div>
          <form method="post" action="<?= Fmt::h(url('akquise')) ?>"><?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="akq_folge_vorlage_speichern"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
            <div class="feld"><label>Betreff</label><input name="betreff" maxlength="190" value="<?= Fmt::h((string) $v['betreff']) ?>"></div>
            <div class="feld"><label>Text</label><textarea name="text"><?= Fmt::h((string) $v['text']) ?></textarea></div>
            <div class="fo-knoepfe"><button class="knopf">Speichern</button></div>
          </form>
          <?php if ($v['status'] !== 'freigegeben'): ?>
            <form method="post" action="<?= Fmt::h(url('akquise')) ?>" style="margin-top:8px"
                  data-frage="Diesen Text freigeben? Er geht dann automatisch an jeden Betrieb in der Folge, sobald Schritt <?= $schritt ?> fällig ist." data-ja="Ja, freigeben"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="akq_folge_freigeben"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
              <button class="knopf akq-los">Text freigeben</button></form>
          <?php else: ?>
            <p class="akq-klein" style="margin-top:8px">Freigegeben von <?= Fmt::h((string) $v['freigegeben_von']) ?> am <?= Fmt::h(date('d.m.Y', strtotime((string) $v['freigegeben_am']))) ?>.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
<?php endforeach; ?>
