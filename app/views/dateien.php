<div class="kopf"><div><h1>Dateien</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Alles, was hochgeladen wurde — von dir oder vom Kunden. Hochladen und löschen im jeweiligen Projekt.</p></div></div>

<?php if (!$bereit): ?>
  <div class="hinweis schlecht">Der Ordner <code>app/uploads/</code> lässt sich nicht beschreiben.
    Im KAS unter Dateiverwaltung die Schreibrechte für <code>app/</code> prüfen.</div>
<?php endif; ?>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Noch keine Dateien.</div>
  <?php else: ?>
    <table><thead><tr><th>Datei</th><th>Kunde</th><th>Projekt</th><th>Größe</th><th>Von</th><th>Wann</th></tr></thead><tbody>
    <?php foreach ($liste as $d): ?>
      <tr>
        <td><a href="<?= Fmt::h(url('dateien/' . (int) $d['id'])) ?>"><?= Fmt::h($d['orig_name']) ?></a>
          <br><small style="color:var(--leise)"><?= Fmt::h((string) $d['mime']) ?></small></td>
        <td><?= $d['customer_id']
              ? '<a href="' . Fmt::h(url('kunden/' . (int) $d['customer_id'])) . '">' . Fmt::h((string) ($d['firma'] ?: $d['kunde'])) . '</a>'
              : '—' ?></td>
        <td><?= $d['project_id']
              ? '<a href="' . Fmt::h(url('projekte/' . (int) $d['project_id'])) . '">' . Fmt::h((string) $d['projekt']) . '</a>'
              : '—' ?></td>
        <td style="white-space:nowrap"><?= Fmt::h(Fmt::bytes((int) $d['size_bytes'])) ?></td>
        <td><?= $d['uploaded_by'] === 'kunde' ? 'Kunde' : 'du' ?></td>
        <td style="white-space:nowrap;color:var(--leise)"><?= Fmt::h(Fmt::seit($d['created_at'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
