<?php
$töne = ['verbunden' => 'gut', 'fehler' => 'schlecht', 'deaktiviert' => 'neutral', 'nicht_verbunden' => 'neutral'];
$zeichen = ['verarbeitet' => '🟢', 'empfangen' => '🟡', 'fehler' => '🔴'];
?>
<div class="kopf"><h1>Integrationen</h1></div>

<div class="block">
  <h2>Stripe <span class="marke2 <?= $stripe->bereit() ? 'gut' : '' ?>"><?= $stripe->bereit() ? 'Schlüssel hinterlegt' : 'kein Schlüssel' ?></span>
    <span class="marke2 <?= $stripe->modus() === 'live' ? 'warnung' : '' ?>"><?= $stripe->modus() === 'live' ? 'Livemodus' : 'Testmodus' ?></span></h2>

  <table><tbody>
    <tr><td>Geheimer Schlüssel</td><td><?= Fmt::h($stripe->schluesselHinweis()) ?></td></tr>
    <tr><td>Webhook-Geheimnis</td><td><?= $stripe->webhookBereit() ? 'hinterlegt' : '— fehlt' ?></td></tr>
    <tr><td>Adresse für Stripe</td><td><code>https://vecom-design.it/stripe-webhook.php</code></td></tr>
    <tr><td>Fehlgeschlagene Ereignisse</td><td><?= $offen ?></td></tr>
  </tbody></table>

  <?php if (!$stripe->bereit() || !$stripe->webhookBereit()): ?>
    <div class="hinweis" style="margin-top:14px;background:rgba(31,232,255,.08);border-color:rgba(31,232,255,.32);color:var(--text)">
      <strong>So wird Stripe verbunden</strong><br>
      Trage in <code>app/config.local.php</code> auf dem Webspace einen Abschnitt <code>'stripe'</code> ein:
      <code>modus</code> (<em>test</em> oder <em>live</em>), <code>geheim</code> (der geheime Schlüssel aus dem
      Stripe-Konto) und <code>webhook_geheim</code> (das Signaturgeheimnis, das Stripe beim Anlegen des Webhooks zeigt).
      Die Schlüssel gehören ausschließlich dorthin — nie ins Repository, nie in den Browser.
      Die Vorlage steht in <code>app/config.local.example.php</code>.
    </div>
  <?php endif; ?>

  <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
    Kartendaten erreichen diesen Server nie — bezahlt wird auf einer Seite, die Stripe selbst ausliefert.
    Eingehende Meldungen werden auf ihre Unterschrift geprüft und können durch den Eindeutigkeitsschlüssel
    auf Anbieter&nbsp;+&nbsp;Ereignis-Nummer nicht doppelt verarbeitet werden.
  </p>
</div>

<div class="zwei"><div>
  <div class="block"><h2>Letzte Ereignisse von außen</h2>
    <?php if (!$ereignisse): ?><div class="leer">Noch nichts angekommen.</div><?php else: ?>
    <div class="tabellenrahmen"><table>
      <thead><tr><th></th><th>Ereignis</th><th>Anbieter</th><th>Zeitpunkt</th><th>Hinweis</th></tr></thead><tbody>
      <?php foreach ($ereignisse as $e): ?><tr>
        <td><?= $zeichen[$e['status']] ?? '⚪' ?></td>
        <td><?= Fmt::h($e['event_type']) ?></td>
        <td><?= Fmt::h($e['provider']) ?></td>
        <td style="white-space:nowrap;color:var(--dim)"><?= Fmt::h(Fmt::seit($e['received_at'])) ?></td>
        <td style="color:var(--rot);font-size:12.5px"><?= Fmt::h($e['error'] ?? '') ?></td>
      </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div>
</div><div>
  <div class="block"><h2>Alle Dienste</h2><table><tbody>
    <?php foreach ($liste as $i): ?><tr>
      <td><?= Fmt::h($i['name']) ?><br><small style="color:var(--leise)"><?= Fmt::h($i['category']) ?></small></td>
      <td><span class="marke2 <?= $töne[$i['status']] ?? '' ?>"><?= Fmt::h(Status::label(Status::INTEGRATION, $i['status'])) ?></span>
        <?php if ($i['last_error']): ?><br><small style="color:var(--rot)"><?= Fmt::h(mb_substr((string) $i['last_error'], 0, 90)) ?></small><?php endif; ?>
        <?php if ($i['last_sync_at']): ?><br><small style="color:var(--leise)">zuletzt <?= Fmt::h(Fmt::seit($i['last_sync_at'])) ?></small><?php endif; ?>
      </td></tr><?php endforeach; ?>
  </tbody></table></div>
</div></div>
