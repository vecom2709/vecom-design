<div class="kopf"><div><h1>Einstellungen</h1></div></div>

<div class="block">
  <h2>Beispieldaten</h2>
  <p style="color:var(--dim);font-size:13.5px;margin:8px 0 16px;line-height:1.6">
    Drei vollständige Vorgänge in drei Sprachen — eine Bäckerei aus Aragona auf Italienisch,
    eine Ferienvermietung auf Deutsch, ein Keramikstudio auf Englisch. Damit füllt sich jede
    Ansicht und du siehst, wie die Verwaltung aussieht, wenn Kunden da sind.
  </p>

  <?php if ($beispiele > 0): ?>
    <div class="hinweis" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb)">
      Es sind gerade <b><?= (int) $beispiele ?></b> Beispielkunden geladen. Alle Zahlen im Dashboard
      rechnen darüber mit — sie sind also noch nicht deine echten Zahlen.
    </div>
    <p style="color:var(--dim);font-size:13.5px;margin-bottom:14px">
      Die Beispiele verschwinden von allein, sobald die erste echte Bestellung entsteht.
      Du kannst sie aber jederzeit selbst löschen. Gelöscht wird ausschließlich, was als
      Beispiel gekennzeichnet ist — an echte Daten kommt die Löschung gar nicht heran.
    </p>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="beispiel_loeschen">
      <input type="hidden" name="zurueck" value="einstellungen">
      <button class="knopf haupt">Beispieldaten jetzt löschen</button></form>

  <?php else: ?>
    <?php if ($echteDaten): ?>
      <div class="hinweis gut">Keine Beispieldaten geladen — die Verwaltung zeigt ausschließlich echte Vorgänge.</div>
      <p style="color:var(--dim);font-size:13.5px;margin-bottom:14px">
        Du hast schon echte Daten. Wenn du trotzdem Beispiele dazulegst, rechnen Umsatz und
        Anzahl im Dashboard beides zusammen — oben steht dann auf jeder Seite ein Hinweis,
        und mit einem Klick sind die Beispiele wieder weg.
      </p>
    <?php else: ?>
      <div class="hinweis">Noch keine Daten. Mit Beispieldaten siehst du sofort, wie alles zusammenspielt.</div>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="beispiel_anlegen">
      <input type="hidden" name="zurueck" value="einstellungen">
      <button class="knopf haupt">Beispieldaten anlegen</button></form>
  <?php endif; ?>
</div>

<div class="block">
  <h2>Was noch kommt</h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.7">
    Website-Monitoring per Cronjob, Nachrichten und Dateien je Projekt, automatische
    Rechnungen und die monatliche Betreuung als Stripe-Abo. Die Tabellen dafür stehen schon.
  </p>
</div>
