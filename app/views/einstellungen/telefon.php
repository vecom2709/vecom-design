<?php
/**
 * DIE TELEFONASSISTENTIN — ALLES, WAS EINGESTELLT WIRD
 * ===========================================================================
 *
 * Hier steht, was Manuela IST. Was sie GETAN hat, steht unter
 * „Telefonassistent" — das ist Auswertung und gehört nicht zwischen
 * Eingabefelder. Wer eine Zahl nachsehen will, soll nicht an einem Schlüssel
 * vorbeikommen, den er versehentlich neu erzeugt.
 *
 * Drei Dinge in dieser Reihenfolge, weil sie aufeinander aufbauen:
 * der Betriebsmodus (was sie heute sagen darf), der Schlüssel (womit sie
 * überhaupt hereinkommt), der Zugang zu STRATO (womit wir die Gespräche
 * zurückholen) und zuletzt die fünfzehn Konfigurationen (was sie kann).
 */
require_once dirname(__DIR__, 2) . '/src/Telefonwerkzeuge.php';
?>

<div class="block">
  <h2>Betrieb</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">
    Was sie am Telefon über deine Lage sagen darf. „Urlaub" und „ausgelastet" ändern
    ihren Ton und die Termine, die sie anbietet — sie erfindet dazu nichts.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;align-items:flex-end">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_modus">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <div class="feld" style="margin:0;min-width:220px"><label>Modus</label>
      <select name="modus">
        <?php foreach (Telefon::MODI as $wert => $wort): ?>
          <option value="<?= Fmt::h($wert) ?>" <?= $modus === $wert ? 'selected' : '' ?>><?= Fmt::h($wort) ?></option>
        <?php endforeach; ?>
      </select></div>
    <button class="knopf haupt">Übernehmen</button>
  </form>
</div>

<div class="block">
  <h2>Der Schlüssel</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
    Er öffnet nur, was am Telefon gebraucht wird: nachschlagen, Preis schätzen, Tag und
    Uhrzeit erfahren, Konfigurator-Link schicken, Anliegen melden, Zusammenfassung senden,
    eine offene Frage notieren, jemandem Schritt für Schritt weiterhelfen.
    Nicht deinen Zugang, nicht Stripe, nicht die Zahlungen.
    Wird er bekannt, kann jemand Anfragen anlegen und Links an <b>hinterlegte</b> Adressen
    schicken — lästig, nicht gefährlich. Und oben ist er in zehn Sekunden neu.</p>
  <div class="feld"><label>Adresse (bei STRATO als URL)</label>
    <input readonly value="<?= Fmt::h((string) $adresse) ?>" onclick="this.select()"></div>
  <?php /* VERDECKT, WEIL EIN BILDSCHIRMFOTO SCHNELLER GEMACHT IST ALS EIN
           NEUER SCHLÜSSEL. Am 6.9. stand er offen auf dieser Seite und lag
           damit in einem Screenshot — danach musste er gewechselt werden.
           Kopieren geht trotzdem: dafür braucht ihn niemand zu sehen. */ ?>
  <div class="feld"><label>Schlüssel (Kopfzeile <code>X-Vecom-Telefon</code>)</label>
    <div style="display:flex;gap:8px;align-items:center">
      <input id="telefon_schluessel" type="password" readonly
             value="<?= Fmt::h((string) $schluessel) ?>" style="flex:1">
      <button class="knopf" type="button" data-kopieren="telefon_schluessel">Kopieren</button>
      <button class="knopf" type="button" onclick="var f=document.getElementById('telefon_schluessel');
              f.type = f.type === 'password' ? 'text' : 'password';
              this.textContent = f.type === 'password' ? 'Zeigen' : 'Verbergen';">Zeigen</button>
    </div></div>
  <p style="color:var(--leise);font-size:12.5px">
    Er steht verdeckt da, damit er nicht in ein Bildschirmfoto gerät. Er gehört nicht in eine
    E-Mail und nicht in einen Chat. Kopieren, drüben einfügen, fertig.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"
        data-frage="Der alte Schlüssel wird damit ungültig — STRATO ruft danach ins Leere, bis du den neuen dort einträgst. Fortfahren?"
        data-ja="Ja, neuen Schlüssel erzeugen">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="telefon_schluessel_neu">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <button class="knopf">Neuen Schlüssel erzeugen</button></form>
</div>

<?php
/* ---------- Der Chef-Modus ---------- */
require_once dirname(__DIR__, 2) . '/src/Chef.php';
$chefAn = Chef::eingerichtet();
?>
<div class="block">
  <h2>Chef-Modus
    <?php if ($chefAn): ?><span class="marke2 gut" style="margin-left:8px">eingerichtet</span>
    <?php else: ?><span class="marke2" style="margin-left:8px">aus</span><?php endif; ?>
  </h2>
  <p style="color:var(--leise);font-size:13px;line-height:1.6;margin-bottom:10px">
    Ein geheimes Wort, mit dem du am Telefon Manuelas Chef-Modus öffnest — nur für dich.
    Sagst du es im Gespräch, hilft sie dir statt zu verkaufen: sagt, was heute dran ist,
    erklärt den Stand eines Kunden, legt auf dein „ja" einen Kunden an, nimmt eine Notiz auf.
    Für alle anderen Anrufer bleibt sie unverändert. Ohne Wort ist der Chef-Modus aus.
    Wähl ein Wort, das leicht zu sprechen und schwer zu erraten ist — es steht nirgends im
    Chat und in keiner E-Mail.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="chef_codewort">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <div class="feld" style="flex:1;min-width:220px">
      <label>Codewort<?= $chefAn ? ' (leer lassen = unverändert)' : '' ?></label>
      <input type="password" name="codewort" autocomplete="off"
             placeholder="<?= $chefAn ? 'ist gesetzt' : 'z. B. ein Wort, das nur du kennst' ?>">
    </div>
    <button class="knopf haupt">Speichern</button>
  </form>
  <?php if ($chefAn):
    $gesperrtBis = Chef::gesperrtBis();
    $fehlHeute = (int) Db::wert("SELECT COUNT(*) FROM chef_versuche WHERE erfolg = 0 AND created_at >= NOW() - INTERVAL 1 DAY", [], 0);
  ?>
    <p style="font-size:12.5px;color:var(--leise);margin:12px 0 0;line-height:1.6">
      Gespeichert ist nur ein Hash — das Wort selbst steht nirgends, auch nicht in der Datenbank.
      Nach <?= Chef::SPERRE_VERSUCHE ?> falschen Wörtern in <?= Chef::SPERRE_FENSTER ?> Minuten ist der Modus
      <?= Chef::SPERRE_DAUER ?> Minuten zu, auch für das richtige.
      Falsche Versuche in den letzten 24 Stunden: <b><?= $fehlHeute ?></b>.</p>
    <?php if ($gesperrtBis !== null): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="chef_sperre_weg">
        <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
        <span class="marke2 schlecht">gesperrt bis <?= Fmt::h(substr($gesperrtBis, 11, 5)) ?> Uhr</span>
        <button class="knopf" style="margin-left:8px">Sperre aufheben</button>
      </form>
    <?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-top:12px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="chef_pin">
      <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
      <div class="feld" style="flex:1;min-width:180px">
        <label>PIN (4–8 Ziffern, optional<?= Chef::mitPin() ? ' — ist gesetzt; leer speichern entfernt sie' : '' ?>)</label>
        <input type="password" name="pin" inputmode="numeric" autocomplete="off" pattern="[0-9]{4,8}|">
      </div>
      <button class="knopf">PIN speichern</button>
    </form>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px"
          data-frage="Das Codewort wird entfernt — der Chef-Modus ist danach aus, bis du ein neues setzt. Fortfahren?"
          data-ja="Ja, Codewort entfernen">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="chef_codewort_weg">
      <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
      <button class="knopf">Codewort entfernen</button>
    </form>
  <?php endif; ?>
</div>

<?php
/* ---------- Was am Telefon vorbereitet wurde, und was Uwe sich gemerkt hat ---------- */
$freigaben = $chefAn ? Chef::gedaechtnis(['WAITING_FOR_APPROVAL']) : [];
$merk = $chefAn ? array_values(array_filter(Chef::gedaechtnis(),
    static fn($g) => $g['kategorie'] !== 'WAITING_FOR_APPROVAL')) : [];
if ($freigaben || $merk): ?>
<div class="block" id="freigaben">
  <h2>Aus dem Chef-Modus
    <?php if ($freigaben): ?><span class="marke2 warnung" style="margin-left:8px"><?= count($freigaben) ?> wartet auf Freigabe</span><?php endif; ?>
  </h2>
  <?php foreach ($freigaben as $g):
    $v = json_decode((string) $g['vorhaben'], true) ?: []; ?>
    <div style="border:1px solid var(--linie);border-radius:10px;padding:12px 14px;margin:10px 0">
      <b><?= Fmt::h((string) $g['text']) ?></b>
      <div style="font-size:12.5px;color:var(--leise);margin-top:4px">
        Am Telefon vorbereitet am <?= Fmt::h(Fmt::datum((string) $g['created_at'])) ?>.
        <?php if (($v['art'] ?? '') === 'hosting_speicher'): ?>
          Folgen: Der vereinbarte Speicher ändert sich in VECOM; im KAS erst nach „KAS auf Vecom-Wert setzen“.
          Stimmt der alte Wert beim Freigeben nicht mehr, wird nichts geändert.
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <form method="post" action="<?= Fmt::h(url('')) ?>">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="chef_freigeben">
          <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <button class="knopf haupt">Freigeben</button></form>
        <form method="post" action="<?= Fmt::h(url('')) ?>">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="chef_verwerfen">
          <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
          <button class="knopf">Verwerfen</button></form>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($merk): ?>
    <table style="margin-top:10px"><thead><tr><th>Art</th><th>Kunde</th><th>Vermerk</th><th>Seit</th></tr></thead><tbody>
    <?php foreach (array_slice($merk, 0, 20) as $g):
      $kn = $g['customer_id'] ? (string) Db::wert('SELECT name FROM customers WHERE id = ?', [(int) $g['customer_id']], '') : '';
      $ueberholt = $g['kategorie'] === 'WAITING_FOR_CUSTOMER' ? Chef::eingetroffen($g) : null; ?>
      <tr><td><span class="marke2"><?= Fmt::h(['FACT' => 'Fakt', 'CHEF_DECISION' => 'Entscheidung', 'OPEN_TASK' => 'Aufgabe',
                 'WAITING_FOR_CUSTOMER' => 'wartet auf Kunde'][$g['kategorie']] ?? $g['kategorie']) ?></span></td>
          <td><?= $kn !== '' ? '<a href="' . Fmt::h(url('kunden/' . (int) $g['customer_id'])) . '">' . Fmt::h($kn) . '</a>' : '—' ?></td>
          <td><?= Fmt::h((string) $g['text']) ?><?php if ($ueberholt): ?><br><span class="marke2 warnung">überholt: <?= Fmt::h($ueberholt) ?></span><?php endif; ?></td>
          <td style="white-space:nowrap"><?= Fmt::h(Fmt::datum((string) $g['created_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php
/* ---------- Der Verhaltenstext ---------- */
require_once dirname(__DIR__, 2) . '/src/Telefonverhalten.php';
$vStand = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'strato_verhalten'", [], '');
[$vWie, $vVer] = array_pad(explode(':', $vStand), 2, '');
?>
<div class="block" id="verhalten">
  <h2>Verhaltenstext <span class="marke2" style="margin-left:8px">v<?= Telefonverhalten::VERSION ?></span>
    <?php if ($vWie === 'aktuell'): ?><span class="marke2 gut" style="margin-left:6px">drüben aktuell</span>
    <?php elseif ($vWie === 'aelter'): ?><span class="marke2 warnung" style="margin-left:6px">drüben v<?= Fmt::h($vVer) ?></span>
    <?php elseif ($vWie === 'fehlt'): ?><span class="marke2 warnung" style="margin-left:6px">drüben nicht gefunden</span><?php endif; ?>
  </h2>
  <p style="color:var(--leise);font-size:13px;line-height:1.6;margin-bottom:10px">
    Die Regeln, nach denen Manuela spricht: Sprache halten, nichts erfinden, nachfragen, zurücklesen,
    sauber abschließen, Chef-Modus nur mit Codewort. Er gehört bei STRATO <b>unter</b> Stimme, Begrüßung und
    Persönlichkeit ins Verhaltensfeld — die Verwaltung schreibt ihn dort nicht selbst hinein, damit nichts
    überschrieben wird, was du drüben eingestellt hast. Sie prüft nur lesend, ob die Fassung angekommen ist.</p>
  <div style="display:flex;gap:10px;margin-bottom:8px;flex-wrap:wrap">
    <button class="knopf haupt" type="button" data-kopieren="verhalten_text">Kopieren</button>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_verhalten">
      <button class="knopf">Mit STRATO vergleichen</button></form>
  </div>
  <textarea id="verhalten_text" readonly rows="14"
    style="width:100%;font-family:ui-monospace,monospace;font-size:11.5px;line-height:1.45"
  ><?= Fmt::h(Telefonverhalten::text()) ?></textarea>
</div>

<?php /* ---------- Die Merkliste ---------- */ ?>
<div class="block">
  <h2>Merkliste
    <?php if ($merkliste): ?><span class="marke2"><?= count($merkliste) ?></span><?php endif; ?>
  </h2>
  <p style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:8px 0 14px">
    Für Anrufer, die regelmäßig anrufen und nie kaufen. Steht eine Nummer hier, gibt Manuela
    <b>keine Beratung, keinen Seitenblick, keine Preise, keinen Link, kein Angebot und keinen
    Termin</b>. Sie bleibt höflich, sagt in zwei Sätzen, dass Anfragen schriftlich laufen,
    und lässt das Gespräch enden.
  </p>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.65;margin:0 0 16px">
    Der Anruf steht trotzdem in der Verwaltung — niemand wird heimlich weggeblendet, und du
    siehst, wer angerufen hat. Unhöflich wird sie nicht: Sie urteilt nicht über den Anrufer,
    behauptet nichts über ihn und legt nicht auf. Das wäre in Italien
    <i>diffamazione</i>, es steht aufgezeichnet bei der Telefonplattform, und in einer Provinz,
    in der man sich kennt, kostet es mehr als jeder verlorene Auftrag.
  </p>

  <?php if ($merkliste): ?>
    <table style="margin-bottom:16px"><thead><tr>
      <th>Nummer</th><th>Notiz</th><th>Angerufen</th><th></th>
    </tr></thead><tbody>
    <?php foreach ($merkliste as $m): ?>
      <tr>
        <td><b><?= Fmt::h((string) $m['nummer']) ?></b></td>
        <td style="color:var(--dim)"><?= Fmt::h((string) $m['notiz']) ?: '—' ?></td>
        <td style="color:var(--leise);font-size:12.5px">
          <?php if ((int) $m['getroffen'] > 0): ?>
            <?= (int) $m['getroffen'] ?>× seither<?php if ($m['zuletzt_am']): ?>,
              zuletzt <?= Fmt::h(Fmt::seit((string) $m['zuletzt_am'])) ?><?php endif; ?>
          <?php else: ?>noch nicht<?php endif; ?>
        </td>
        <td style="text-align:right">
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="merkliste_weg">
            <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
            <input type="hidden" name="ende" value="<?= Fmt::h((string) $m['nummer_ende']) ?>">
            <button class="knopf">Wieder normal</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>

  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="merkliste_setzen">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <div class="feld" style="margin:0;min-width:200px"><label>Rufnummer</label>
      <input name="nummer" placeholder="+39 380 111 2233" required></div>
    <div class="feld" style="margin:0;flex:1;min-width:220px"><label>Notiz (nur für dich)</label>
      <input name="notiz" placeholder="ruft alle zwei Wochen an, nie ein Auftrag"></div>
    <button class="knopf haupt">Auf die Merkliste</button>
  </form>
  <p style="color:var(--leise);font-size:12px;margin-top:10px">
    Verglichen werden die letzten neun Ziffern — dieselbe Nummer kommt mal mit +39,
    mal mit 0039, mal ohne Vorwahl an.
  </p>
</div>

<?php /* ---------- Der Rückweg: die Gespräche von STRATO ---------- */ ?>
<div class="block">
  <h2>Gespräche von STRATO holen
    <?php if ($strato['eingerichtet'] && $strato['fehler'] === ''): ?>
      <span class="marke2 gut">verbunden</span>
    <?php elseif ($strato['eingerichtet']): ?>
      <span class="marke2 schlecht">antwortet nicht</span>
    <?php else: ?>
      <span class="marke2">nicht eingerichtet</span>
    <?php endif; ?>
  </h2>

  <p style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:8px 0 14px">
    Bei STRATO liegt zu jedem Anruf mehr, als die Oberfläche dort zeigt: Betreff,
    Zusammenfassung <b>und eine maschinelle Auswertung</b> — wie das Gespräch ausging, wie
    beteiligt der Anrufer war, welche Probleme auffielen und ob Manuela gegen ihre eigenen
    Anweisungen gehandelt hat. Mit einem Zugang holt die Verwaltung das stündlich herüber
    und legt es dauerhaft neben unsere eigene Spur. Ohne Zugang bleibt die Telefonseite bei
    dem, was wir selbst protokolliert haben.
  </p>

  <?php if ($strato['fehler'] !== ''): ?>
    <div class="hinweis schlecht"><?= Fmt::h($strato['fehler']) ?><br>
      <span style="font-size:12.5px">
      <?php if (str_contains($strato['fehler'], 'Already Used')): ?>
        Der Token wurde zweimal gleichzeitig benutzt — dann widerruft Supabase die ganze
        Sitzung. Seit dem 7.9. verhindert eine Sperre das; ein einmal widerrufener Zugang
        lässt sich aber nicht wiederbeleben. Bitte hier neu hinterlegen.
      <?php else: ?>
        STRATO hat die Sitzung beendet. Neu hinterlegen wie unten beschrieben — privates
        Fenster, Cookie kopieren, einfügen, Fenster schließen (nicht abmelden).
      <?php endif; ?>
      </span></div>
  <?php elseif ($strato['eingerichtet']): ?>
    <div class="hinweis gut">
      <?= (int) $strato['anzahl'] ?> Gespräche liegen hier.
      <?= $strato['zuletzt'] !== '' ? 'Zuletzt geholt ' . Fmt::h(Fmt::seit($strato['zuletzt'])) . '.' : 'Noch nicht geholt.' ?>
    </div>
  <?php endif; ?>

  <?php /* DIE WICHTIGSTE ZEILE AUF DIESER SEITE
           Am 7. September starb der Zugang nach zwei Stunden: „Invalid
           Refresh Token: Already Used". Der Grund war nicht ein Fehler im
           Code, sondern eine geteilte Sitzung. Wer den Token aus dem
           normalen Browserfenster nimmt, gibt dem Server dieselbe Sitzung,
           die der Browser benutzt — und beide tauschen ihn aus. Wer zweiter
           ist, ist draußen. */ ?>
  <div style="background:rgba(251,191,36,.10);border:1px solid rgba(251,191,36,.35);
              border-radius:8px;padding:12px 14px;margin:0 0 14px">
    <p style="color:var(--dim);font-size:13px;line-height:1.7;margin:0">
      <b>Hol den Token aus einem privaten Fenster.</b> Nimmst du ihn aus deinem normalen
      Chrome, teilen sich Browser und Server <b>dieselbe</b> Sitzung — und weil beide den
      Token bei jeder Benutzung austauschen, sperrt der eine irgendwann den anderen aus.
      Genau daran ist der Zugang schon einmal gestorben.
      <br>Im privaten Fenster anmelden, Token holen, Fenster einfach
      <b>schließen</b> — <u>nicht abmelden</u>: Abmelden würde die Sitzung beenden, die der
      Server danach braucht.
    </p>
  </div>

  <?php /* KEIN CODE IN DER KONSOLE
           Chrome warnt bei jedem Einfügen in die Entwicklerkonsole — zu
           Recht. Wer seinen Nutzern beibringt, diese Warnung wegzuklicken,
           bringt ihnen bei, sie immer wegzuklicken. Deshalb nimmt das Feld
           unten auch den rohen Cookie-Wert: mit der Maus kopieren, hier
           einfügen, der Server packt ihn aus. */ ?>
  <div style="background:rgba(93,188,252,.07);border:1px solid var(--linie);
              border-radius:8px;padding:12px 14px;margin:0 0 14px">
    <p style="color:var(--dim);font-size:13px;line-height:1.7;margin:0">
      <b>Du brauchst dafür keinen Code in der Konsole.</b> Kopiere unten einfach den
      <b>ganzen Wert</b> des Cookies <code>sb-…-auth-token</code> — so wie er dasteht, auch
      wenn er mit <code>base64-</code> beginnt und wie Kauderwelsch aussieht. Auspacken macht
      der Server. Chrome warnt zu Recht vor fremdem Code in der Konsole; diese Warnung sollst
      du nie wegklicken.
    </p>
  </div>

  <details style="margin-bottom:14px">
    <summary style="cursor:pointer;font-size:13px;color:var(--cyan)">Wo die beiden Angaben stehen</summary>
    <ol style="color:var(--dim);font-size:13px;line-height:1.9;padding-left:20px;margin:10px 0 0">
      <li><b>Privates Fenster</b> öffnen (⇧⌘N) und dort bei STRATO anmelden.</li>
      <li>Mit <b>F12</b> die Entwicklerwerkzeuge öffnen, Reiter <b>Application</b> (Firefox: <b>Speicher</b>).</li>
      <li>Links unter <b>Cookies</b> die Adresse von STRATO wählen.</li>
      <li>Auf den Eintrag <code>sb-…-auth-token</code> klicken. Unten erscheint der Wert —
          mit der Maus markieren und kopieren, <b>ganz</b>, auch das <code>base64-</code>
          am Anfang. Kein Code, keine Konsole.</li>
      <li>Hier unten in das Feld <b>Auffrischungs-Token</b> einfügen. Der Server holt sich
          daraus, was er braucht, und wirft den Rest weg. Das obere Feld bleibt leer —
          der öffentliche Schlüssel steht schon gespeichert.</li>
      <li>Hier eintragen, dann das private Fenster <b>schließen</b> — nicht abmelden.</li>
    </ol>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">
      Kein Passwort, nirgends. Der Auffrischungs-Token gilt nur für dieses eine Konto bei
      diesem einen Dienst, wird bei jeder Benutzung ausgetauscht und wird wertlos, sobald sich
      jemand aus dieser Sitzung abmeldet. Er gehört trotzdem nicht in eine E-Mail und nicht in
      einen Chat.
    </p>
  </details>

  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_zugang">
    <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
    <div class="feld"><label>Öffentlicher Schlüssel<?= $strato['eingerichtet'] ? ' (leer lassen = unverändert)' : '' ?></label>
      <input type="password" name="anon" autocomplete="off" placeholder="eyJhbGciOi…"></div>
    <div class="feld"><label>Auffrischungs-Token<?= $strato['eingerichtet'] ? ' (leer lassen = unverändert)' : '' ?></label>
      <input type="password" name="refresh" autocomplete="off"
             placeholder="den ganzen Cookie-Wert einfügen — base64-… ist richtig so">
      <small style="color:var(--leise);font-size:12px">Der ganze Cookie-Wert genügt; der
        Server packt ihn aus und behält nur den Auffrischungs-Token.</small></div>
    <button class="knopf haupt">Zugang hinterlegen und prüfen</button>
  </form>

  <?php
  /* WIE LANGE DIE SITZUNGEN HIELTEN (Uwe, 26.09.2026: „c“)
     Das Feld „Automatisch neu anmelden“ stand bis heute hier. Es kann bei
     Uwe nicht gehen: Er meldet sich bei STRATO mit Kundennummer an, und das
     läuft über STRATOs eigenes Login, nicht über E-Mail und Passwort. Die
     Anmeldeseite von STRATO von hier aus zu bedienen wäre Screen Scraping —
     bricht bei jeder Änderung und bei jedem Bestätigungscode. Die Technik
     dahinter (Strato::anmeldungSetzen) bleibt für Konten mit E-Mail-Login. */
  $sitzungen = Strato::sitzungen();
  $seit = Strato::sitzungSeit();
  ?>
  <div style="border-top:1px solid var(--linie);margin-top:16px;padding-top:14px">
    <h3 style="font-size:15px;margin:0 0 6px">Wenn der Zugang abläuft</h3>
    <p style="color:var(--dim);font-size:13px;line-height:1.7;margin:0 0 8px">
      Du bekommst sofort eine Meldung — hier und, wenn der Zuruf eingerichtet ist, aufs Handy.
      Neu hinterlegen: STRATO in einem <b>privaten Fenster</b> öffnen, mit Kundennummer anmelden,
      den Cookie <code>sb-…-auth-token</code> kopieren, oben einfügen, Fenster <b>schließen, nicht abmelden</b>.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
      <input id="strato_adresse" readonly value="https://strato.ai-voicereceptionist.com/" style="max-width:320px">
      <button class="knopf" type="button" data-kopieren="strato_adresse">Adresse kopieren</button>
      <span style="color:var(--leise);font-size:12px">… und im privaten Fenster einfügen (⇧⌘N / Strg+⇧+N)</span>
    </div>
    <?php if ($seit !== '' || $sitzungen): ?>
      <p style="font-size:13px;margin:0 0 4px"><b>Wie lange die Sitzungen hielten</b></p>
      <ul style="font-size:12.5px;color:var(--dim);line-height:1.8;margin:0;padding-left:18px">
        <?php if ($seit !== ''): ?><li>jetzige: seit <?= Fmt::h(Fmt::datum($seit)) ?> (<?= (int) round((time() - strtotime($seit)) / 3600) ?> Std.)</li><?php endif; ?>
        <?php foreach ($sitzungen as $x): $t = intdiv($x['stunden'], 24); ?>
          <li><?= Fmt::h(Fmt::datum($x['von'])) ?> – <?= Fmt::h(Fmt::datum($x['bis'])) ?>:
            <b><?= $t > 0 ? $t . ' Tg. ' : '' ?><?= $x['stunden'] % 24 ?> Std.</b>
            <span style="color:var(--leise)">(<?= Fmt::h($x['grund']) ?>)</span></li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($sitzungen) >= 3):
        $h = array_column($sitzungen, 'stunden'); $spanne = max($h) - min($h); ?>
        <p style="font-size:12.5px;color:var(--leise);margin:6px 0 0"><?= $spanne <= 6
          ? 'Die Sitzungen halten fast gleich lang (' . Fmt::h((string) round(array_sum($h) / count($h) / 24, 1)) . ' Tage) — STRATO schaltet nach fester Zeit ab.'
          : 'Die Sitzungen halten verschieden lang — eher ein Abmelden oder eine zweite Sitzung im Browser als eine feste Frist.' ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($strato['eingerichtet']): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_holen">
      <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
      <button class="knopf">Jetzt holen</button></form>
  <?php endif; ?>

  <?php /* DIE SPERRLISTE GEHÖRT SICHTBAR
           Was hier gelöscht wurde, liegt bei STRATO noch. Ohne diese Zeile
           wäre die Sperrliste eine Falle: Man löscht ein Gespräch, es kommt
           nicht wieder — und niemand weiß, warum ein Anruf, den man drüben
           sieht, hier fehlt. */ ?>
  <?php if (($strato['gesperrt'] ?? 0) > 0): ?>
    <div style="border-top:1px solid var(--linie);margin-top:16px;padding-top:14px">
      <p style="color:var(--dim);font-size:13px;line-height:1.65;margin:0 0 10px">
        <b><?= (int) $strato['gesperrt'] ?></b>
        <?= (int) $strato['gesperrt'] === 1 ? 'Gespräch wurde' : 'Gespräche wurden' ?>
        hier gelöscht und
        <?= (int) $strato['gesperrt'] === 1 ? 'wird' : 'werden' ?> beim Abgleich übersprungen.
        Bei STRATO
        <?= (int) $strato['gesperrt'] === 1 ? 'liegt es' : 'liegen sie' ?> noch — daran kommen
        wir nicht heran. Hebst du die Sperre auf,
        <?= (int) $strato['gesperrt'] === 1 ? 'kommt es' : 'kommen sie' ?> beim nächsten Lauf zurück.
      </p>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"
            data-frage="Die gelöschten Gespräche kommen beim nächsten Abgleich zurück, sofern STRATO sie noch hat. Fortfahren?"
            data-ja="Ja, Sperre aufheben">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="gespraeche_sperre_loesen">
        <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
        <button class="knopf">Sperre aufheben</button></form>
    </div>
  <?php endif; ?>

  <?php if ($strato['eingerichtet']): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"
          data-frage="Der Zugang wird gelöscht. Die schon geholten Gespräche bleiben — es kommen nur keine neuen mehr dazu. Fortfahren?"
          data-ja="Ja, Zugang löschen">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_loeschen">
      <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
      <button class="knopf">Zugang löschen</button></form>
  <?php endif; ?>
</div>

<?php
/* ---------- Die fertigen Konfigurationen ----------
   Sie standen bis September mitten in dieser Ansicht. Jetzt stehen sie in
   Telefonwerkzeuge -- damit der Server sie selbst zu STRATO schicken kann,
   ohne dass jemand fünfzehn Blöcke von Hand kopiert. Wer das dreimal gemacht
   hat, macht es beim vierten Mal nicht mehr, und dann steht drüben eine
   Fassung, die niemand mehr kennt. */
$konfigs = Telefonwerkzeuge::json();
?>
<div class="block">
  <h2>Die <?= count($konfigs) ?> Konfigurationen für STRATO</h2>

  <?php /* DER KNOPF, DER DAS KOPIEREN ERSETZT
           Fünfzehn Blöcke von Hand hinüberzutragen macht niemand viermal —
           und dann steht drüben eine Fassung, die niemand mehr kennt, während
           hier eine andere gepflegt wird. Genau das ist im September dreimal
           passiert. */ ?>
  <?php if ($strato['eingerichtet']): ?>
    <div style="background:rgba(93,188,252,.07);border:1px solid var(--linie);
                border-radius:8px;padding:14px 16px;margin:10px 0 18px">
      <p style="color:var(--dim);font-size:13.5px;line-height:1.7;margin:0 0 12px">
        <b>Du musst hier nichts kopieren.</b> Der Zugang zu STRATO liegt hinterlegt — ein Klick
        schreibt alle <?= count($konfigs) ?> hinüber. Angefasst werden <b>nur die Werkzeuge</b>:
        Stimme, Tempo, Begrüßung, Aussprache und der Verhaltenstext drüben bleiben Zeichen für
        Zeichen, wie du sie eingestellt hast. Danach wird nachgelesen, ob es wirklich ankam.
        <?php if (($strato['werkzeuge_am'] ?? '') !== ''): ?>
          <br><span style="color:var(--leise);font-size:12.5px">Zuletzt übertragen
          <?= Fmt::h(Fmt::seit((string) $strato['werkzeuge_am'])) ?>.</span>
        <?php endif; ?>
      </p>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="strato_werkzeuge">
        <input type="hidden" name="zurueck" value="einstellungen?b=telefon">
        <button class="knopf haupt">Jetzt zu STRATO übertragen</button></form>
    </div>
  <?php endif; ?>

  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">
    Von Hand geht es auch: Bei STRATO unter <b>API-Integration → neu anlegen</b>, für jede
    einzelne den Block kopieren und einfügen. Der Schlüssel steht schon drin.
    <br>Die Antwortmöglichkeiten (<code>enum</code>) sind kein Beiwerk: Der Konfigurator nimmt
    nur seine eigenen Schlüsselwörter an. Was Manuela frei formuliert, wird verworfen — die
    Frage bleibt dann offen, statt falsch beantwortet zu werden.</p>

  <?php $roh = Telefonwerkzeuge::alle(); ?>
  <?php foreach ($konfigs as $name => $text):
    $pflicht = count($roh[$name]['pflicht'] ?? []);
  ?>
    <div style="margin-bottom:18px">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <b style="font-size:14px"><?= Fmt::h($name) ?></b>
        <span class="marke2"><?= $pflicht ?> Pflichtfeld<?= $pflicht === 1 ? '' : 'er' ?></span>
        <button class="knopf" type="button" data-kopieren="k_<?= Fmt::h($name) ?>">Kopieren</button>
      </div>
      <textarea id="k_<?= Fmt::h($name) ?>" readonly rows="8"
        style="width:100%;font-family:ui-monospace,monospace;font-size:11.5px;line-height:1.45"
      ><?= Fmt::h($text) ?></textarea>
    </div>
  <?php endforeach; ?>
</div>
