/* partner-medien.js — Bilder und Kurzvideos der Partner (26.09.2026).

   Alles wird im Browser des Partners gezeichnet: Kein Bild und kein Video
   geht über einen fremden Server, und wir müssen nichts rendern oder
   speichern. Jedes Ergebnis trägt den QR-Code und die Kurzadresse des
   Partners -- der Kanal steht im Link (/bild, /video), damit „Was wirkt“
   sieht, woher die Kunden kamen.

   Braucht qrcode.js (window.qrcode) und <script type="application/json"
   id="medien_daten"> mit Namen, Links und Texten. */
(function () {
  'use strict';
  var datenEl = document.getElementById('medien_daten');
  if (!datenEl || typeof qrcode !== 'function') { return; }
  var D = JSON.parse(datenEl.textContent);

  var FARBE = { grund: '#0a0908', grund2: '#15120d', text: '#f7f3ea', leise: '#b4ada2', gold: '#f1d38b' };
  var FORMATE = {
    quadrat: [1080, 1080], hoch: [1080, 1350], story: [1080, 1920], quer: [1200, 630], banner: [1200, 400], qr: [1000, 1000]
  };

  /* ---------- Bausteine ---------- */
  var logo = new Image(); logo.src = '/assets/img/logo-mark.webp?v=gold2609';
  var foto = null;
  if (D.foto) { foto = new Image(); foto.src = D.foto; }
  function bereit(bild) {
    return new Promise(function (ok) { if (!bild || bild.complete) { ok(); return; } bild.onload = bild.onerror = function () { ok(); }; });
  }
  var schriften = (document.fonts && document.fonts.load)
    ? Promise.all(['800 80px Archivo', '700 80px Archivo', '400 40px Inter', '600 40px Inter'].map(function (f) { return document.fonts.load(f).catch(function () {}); }))
    : Promise.resolve();
  var alles = Promise.all([schriften, bereit(logo), bereit(foto)]);

  var qrCache = {};
  function qrMatrix(t) { if (!qrCache[t]) { var q = qrcode(0, 'M'); q.addData(t); q.make(); qrCache[t] = q; } return qrCache[t]; }
  function gold(x, x0, x1) {
    var g = x.createLinearGradient(x0, 0, x1, 0);
    g.addColorStop(0, '#b98a31'); g.addColorStop(0.45, '#f7e6ae'); g.addColorStop(1, '#c49438'); return g;
  }
  function rund(x, px, py, b, h, r) {
    x.beginPath(); x.moveTo(px + r, py); x.arcTo(px + b, py, px + b, py + h, r); x.arcTo(px + b, py + h, px, py + h, r);
    x.arcTo(px, py + h, px, py, r); x.arcTo(px, py, px + b, py, r); x.closePath();
  }
  /* Hintergrund: fast schwarz, ein warmes Licht oben rechts, eine feine Goldlinie.
     t (0..1) verschiebt das Licht -- im Video atmet es, im Bild steht es. */
  function grund(x, b, h, t) {
    var g = x.createLinearGradient(0, 0, 0, h); g.addColorStop(0, FARBE.grund2); g.addColorStop(1, FARBE.grund);
    x.fillStyle = g; x.fillRect(0, 0, b, h);
    var lx = b * (0.82 - 0.1 * (t || 0)), ly = h * (0.08 + 0.05 * (t || 0)), r = Math.max(b, h) * 0.7;
    var l = x.createRadialGradient(lx, ly, 0, lx, ly, r);
    l.addColorStop(0, 'rgba(241,211,139,0.20)'); l.addColorStop(0.45, 'rgba(241,211,139,0.05)'); l.addColorStop(1, 'rgba(241,211,139,0)');
    x.fillStyle = l; x.fillRect(0, 0, b, h);
  }
  function zeilen(x, text, breite) {
    var w = String(text).split(/\s+/), aus = [], z = '';
    w.forEach(function (t) { var p = z ? z + ' ' + t : t; if (x.measureText(p).width > breite && z) { aus.push(z); z = t; } else { z = p; } });
    if (z) { aus.push(z); } return aus;
  }
  /* Schrift so groß wie möglich, höchstens maxZeilen Zeilen. */
  function passend(x, text, breite, gross, klein, maxZeilen, gewicht, familie) {
    for (var s = gross; s >= klein; s -= 2) {
      x.font = gewicht + ' ' + s + 'px ' + familie; var z = zeilen(x, text, breite);
      if (z.length <= maxZeilen) { return { groesse: s, zeilen: z }; }
    }
    x.font = gewicht + ' ' + klein + 'px ' + familie; return { groesse: klein, zeilen: zeilen(x, text, breite) };
  }
  function schreibe(x, satz, px, py, zeilenhoehe) { satz.zeilen.forEach(function (z, i) { x.fillText(z, px, py + i * zeilenhoehe); }); return py + satz.zeilen.length * zeilenhoehe; }
  function marke(x, px, py, groesse, mittig) {
    var lb = groesse * 1.26, lh = groesse;
    x.font = '800 ' + Math.round(groesse * 0.46) + 'px Archivo, sans-serif';
    var wort1 = 'VECOM', wort2 = ' DESIGN', b1 = x.measureText(wort1).width, b2 = x.measureText(wort2).width;
    var gesamt = lb + groesse * 0.3 + b1 + b2, sx = mittig ? px - gesamt / 2 : px;
    if (logo.complete && logo.naturalWidth) { x.drawImage(logo, sx, py, lb, lh); }
    x.textAlign = 'left'; x.textBaseline = 'middle';
    x.fillStyle = gold(x, sx + lb, sx + lb + b1 + groesse); x.fillText(wort1, sx + lb + groesse * 0.3, py + lh / 2);
    x.fillStyle = FARBE.text; x.fillText(wort2, sx + lb + groesse * 0.3 + b1, py + lh / 2);
    x.textBaseline = 'alphabetic';
  }
  function qrFeld(x, link, px, py, groesse) {
    var q = qrMatrix(link), n = q.getModuleCount(), rand = groesse * 0.07, z = (groesse - 2 * rand) / n;
    x.fillStyle = '#fff'; rund(x, px, py, groesse, groesse, groesse * 0.06); x.fill();
    x.fillStyle = '#0a0908';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) { x.fillRect(px + rand + c * z, py + rand + r * z, Math.ceil(z), Math.ceil(z)); }
  }
  function empfehlung(x, px, py, groesse, mittig, farbe) {
    var text = D.empf, r = groesse * 0.9, mitFoto = foto && foto.complete && foto.naturalWidth;
    x.font = '600 ' + groesse + 'px Inter, sans-serif';
    var tb = x.measureText(text).width, gesamt = (mitFoto ? r * 2 + groesse * 0.6 : 0) + tb, sx = mittig ? px - gesamt / 2 : px;
    if (mitFoto) {
      x.save(); x.beginPath(); x.arc(sx + r, py, r, 0, Math.PI * 2); x.clip(); x.drawImage(foto, sx, py - r, r * 2, r * 2); x.restore();
      x.strokeStyle = 'rgba(241,211,139,.7)'; x.lineWidth = Math.max(2, groesse * 0.08); x.beginPath(); x.arc(sx + r, py, r, 0, Math.PI * 2); x.stroke();
      sx += r * 2 + groesse * 0.6;
    }
    x.textAlign = 'left'; x.textBaseline = 'middle'; x.fillStyle = farbe || FARBE.text; x.fillText(text, sx, py); x.textBaseline = 'alphabetic';
  }
  function hinweis(x, b, groesse) {
    x.font = '400 ' + groesse + 'px Inter, sans-serif'; x.textAlign = 'right'; x.fillStyle = 'rgba(180,173,162,.75)';
    x.fillText(D.werbung, b - groesse * 1.4, groesse * 2.2);
  }

  /* ---------- Bilder ---------- */
  function zeichne(format, motiv, canvas) {
    var m = D.motive[motiv] || D.motive.allgemein, g = FORMATE[format], b = g[0], h = g[1], x = canvas.getContext('2d');
    canvas.width = b; canvas.height = h;
    var link = D.links.bild;
    if (format === 'qr') {
      x.fillStyle = '#fff'; x.fillRect(0, 0, b, h); qrFeld(x, D.links.karte, 60, 60, 880); return;
    }
    grund(x, b, h, 0);
    if (format === 'story') {
      marke(x, b / 2, 190, 74, true); hinweis(x, b, 24);
      x.textAlign = 'center'; x.fillStyle = FARBE.text;
      var t = passend(x, m.titel, 900, 104, 64, 3, '800', 'Archivo, sans-serif');
      var y = schreibe(x, t, b / 2, 470, t.groesse * 1.1);
      x.fillStyle = FARBE.leise; x.font = '400 42px Inter, sans-serif'; y = schreibe(x, { zeilen: zeilen(x, m.unter, 880) }, b / 2, y + 40, 56);
      qrFeld(x, link, (b - 470) / 2, 1030, 470);
      x.textAlign = 'center'; x.font = '700 46px Inter, sans-serif'; var sb = x.measureText(D.scan).width;
      x.fillStyle = gold(x, b / 2 - sb / 2, b / 2 + sb / 2); x.fillText(D.scan, b / 2, 1582);
      x.fillStyle = FARBE.text; x.font = '600 38px Inter, sans-serif'; x.fillText(D.kurz, b / 2, 1642);
      empfehlung(x, b / 2, 1740, 38, true);
    } else if (format === 'quadrat' || format === 'hoch') {
      var hoch = format === 'hoch', rand = 84;
      marke(x, rand, rand, 64, false); hinweis(x, b, 22);
      x.textAlign = 'left'; x.fillStyle = FARBE.text;
      var t2 = passend(x, m.titel, b - 2 * rand, hoch ? 100 : 92, 60, 3, '800', 'Archivo, sans-serif');
      var y2 = schreibe(x, t2, rand, hoch ? 340 : 290, t2.groesse * 1.08);
      x.fillStyle = FARBE.leise; x.font = '400 38px Inter, sans-serif'; schreibe(x, { zeilen: zeilen(x, m.unter, b - 2 * rand) }, rand, y2 + 34, 50);
      var qg = hoch ? 360 : 300, qy = h - rand - qg - 70;
      qrFeld(x, link, b - rand - qg, qy, qg);
      x.textAlign = 'center'; x.fillStyle = gold(x, b - rand - qg, b - rand); x.font = '700 30px Inter, sans-serif'; x.fillText(D.scan, b - rand - qg / 2, qy + qg + 48);
      x.textAlign = 'left'; x.fillStyle = FARBE.text; x.font = '600 32px Inter, sans-serif'; x.fillText(D.kurz, rand, h - rand - 14);
      empfehlung(x, rand, h - rand - 100, 32, false, FARBE.leise);
    } else if (format === 'quer') {
      var r3 = 64, qg3 = 330;
      marke(x, r3, r3, 52, false); hinweis(x, b, 18);
      x.textAlign = 'left'; x.fillStyle = FARBE.text;
      var t3 = passend(x, m.titel, b - qg3 - 3 * r3, 64, 40, 3, '800', 'Archivo, sans-serif');
      var y3 = schreibe(x, t3, r3, 220, t3.groesse * 1.1);
      x.fillStyle = FARBE.leise; x.font = '400 28px Inter, sans-serif'; schreibe(x, { zeilen: zeilen(x, m.unter, b - qg3 - 3 * r3) }, r3, y3 + 20, 38);
      empfehlung(x, r3, h - r3 - 12, 26, false, FARBE.leise);
      qrFeld(x, link, b - r3 - qg3, 110, qg3);
      x.textAlign = 'center'; x.fillStyle = FARBE.text; x.font = '600 24px Inter, sans-serif'; x.fillText(D.kurz, b - r3 - qg3 / 2, 110 + qg3 + 44);
    } else if (format === 'banner') {
      var r4 = 44, qg4 = 300;
      marke(x, r4, r4, 44, false);
      x.textAlign = 'left'; x.fillStyle = FARBE.text;
      var t4 = passend(x, m.titel, b - qg4 - 3 * r4, 56, 34, 2, '800', 'Archivo, sans-serif');
      var y4 = schreibe(x, t4, r4, 170, t4.groesse * 1.1);
      x.fillStyle = gold(x, r4, r4 + 400); x.font = '600 26px Inter, sans-serif'; x.fillText(D.kurz, r4, Math.min(y4 + 34, h - r4 - 40));
      empfehlung(x, r4, h - r4 - 4, 22, false, FARBE.leise);
      qrFeld(x, link, b - r4 - qg4, (h - qg4) / 2, qg4);
    }
  }

  /* ---------- Kurzvideo ---------- */
  var DAUER = 10.5;
  function sanft(t) { t = Math.max(0, Math.min(1, t)); return 1 - Math.pow(1 - t, 3); }
  function bild(motiv, x, b, h, s) {
    var m = D.motive[motiv] || D.motive.allgemein, k = b / 1080;
    x.setTransform(1, 0, 0, 1, 0, 0);
    grund(x, b, h, 0.5 + 0.5 * Math.sin(s / DAUER * Math.PI * 2));
    x.save(); x.scale(k, k);
    var B = 1080;
    marke(x, B / 2, 170, 60, true);
    // Szene 1: Frage, Wort für Wort (0–3 s)
    var a1 = s < 3 ? 1 : Math.max(0, 1 - (s - 3) / 0.4);
    if (a1 > 0) {
      x.globalAlpha = a1; x.textAlign = 'center'; x.fillStyle = FARBE.text;
      var f = passend(x, D.hook, 900, 112, 70, 4, '800', 'Archivo, sans-serif'), zh = f.groesse * 1.1, y0 = 900 - (f.zeilen.length * zh) / 2, wortNr = 0;
      f.zeilen.forEach(function (z, i) {
        var w = z.split(' '), breite = x.measureText(z).width, px = B / 2 - breite / 2;
        w.forEach(function (wort) {
          var p = sanft((s - 0.25 * wortNr) / 0.5); wortNr++;
          x.save(); x.globalAlpha = a1 * p; x.textAlign = 'left'; x.translate(0, (1 - p) * 30);
          x.fillText(wort, px, y0 + i * zh); x.restore(); px += x.measureText(wort + ' ').width;
        });
      });
    }
    // Szene 2: Motiv und drei Schritte (3–7 s)
    if (s > 3 && s < 7.4) {
      var a2 = sanft((s - 3) / 0.5) * (s < 7 ? 1 : Math.max(0, 1 - (s - 7) / 0.4));
      x.globalAlpha = a2; x.textAlign = 'center'; x.fillStyle = FARBE.text;
      var t = passend(x, m.titel, 900, 84, 56, 3, '800', 'Archivo, sans-serif');
      var y = schreibe(x, t, B / 2, 600, t.groesse * 1.1);
      D.punkte.forEach(function (pkt, i) {
        var p = sanft((s - 3.6 - i * 0.9) / 0.5); if (p <= 0) { return; }
        var py = y + 170 + i * 190;
        x.save(); x.globalAlpha = a2 * p; x.translate((1 - p) * -40, 0);
        x.fillStyle = gold(x, 150, 230); x.beginPath(); x.arc(180, py - 18, 32, 0, Math.PI * 2); x.fill();
        x.fillStyle = '#16120b'; x.font = '800 36px Archivo, sans-serif'; x.textAlign = 'center'; x.fillText(String(i + 1), 180, py - 5);
        x.fillStyle = FARBE.text; x.font = '600 54px Inter, sans-serif'; x.textAlign = 'left'; x.fillText(pkt, 250, py);
        x.restore();
      });
    }
    // Szene 3: QR, Adresse, Empfehlung (ab 7 s)
    if (s > 7) {
      var p3 = sanft((s - 7) / 0.7), g = 520 * (0.85 + 0.15 * p3);
      x.globalAlpha = p3;
      qrFeld(x, D.links.video, (B - g) / 2, 560 + (520 - g) / 2, g);
      x.textAlign = 'center'; x.font = '700 50px Inter, sans-serif'; var bb = x.measureText(D.bio).width;
      x.fillStyle = gold(x, B / 2 - bb / 2, B / 2 + bb / 2); x.fillText(D.bio, B / 2, 1200);
      x.fillStyle = FARBE.text; x.font = '600 40px Inter, sans-serif'; x.fillText(D.kurz, B / 2, 1266);
      empfehlung(x, B / 2, 1400, 40, true);
      x.font = '400 26px Inter, sans-serif'; x.fillStyle = 'rgba(180,173,162,.75)'; x.fillText(D.werbung, B / 2, 1760);
    }
    x.restore(); x.globalAlpha = 1;
  }
  function videoArt() {
    if (!window.MediaRecorder || !HTMLCanvasElement.prototype.captureStream) { return null; }
    var arten = ['video/mp4;codecs=avc1.42E01E', 'video/mp4;codecs=avc1', 'video/mp4', 'video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];
    for (var i = 0; i < arten.length; i++) { if (MediaRecorder.isTypeSupported(arten[i])) { return arten[i]; } }
    return null;
  }

  /* ---------- Oberfläche ---------- */
  var wahl = { motiv: 'allgemein', format: 'quadrat' };
  var vorschau = document.getElementById('bild_vorschau');
  function neu() { alles.then(function () { zeichne(wahl.format, wahl.motiv, vorschau); }); }
  function chips(sel, schluessel) {
    [].forEach.call(document.querySelectorAll(sel), function (b) {
      b.addEventListener('click', function () {
        wahl[schluessel] = b.dataset.wert;
        [].forEach.call(document.querySelectorAll(sel), function (a) { a.setAttribute('aria-pressed', a === b ? 'true' : 'false'); });
        neu(); vidZuruck();
      });
    });
  }
  chips('[data-motiv]', 'motiv'); chips('[data-format]', 'format');
  function dateiname(endung) { return 'vecom-' + D.code.toLowerCase() + '-' + wahl.motiv + '-' + (endung === 'png' ? wahl.format : 'video') + '.' + endung; }
  function alsDatei(canvas) {
    return new Promise(function (ok) { canvas.toBlob(function (bl) { ok(new File([bl], dateiname('png'), { type: 'image/png' })); }, 'image/png'); });
  }
  function laden(blob, name) {
    var a = document.createElement('a'), u = URL.createObjectURL(blob); a.href = u; a.download = name;
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(u); }, 4000);
  }
  function teilenMoeglich(datei) { try { return !!(navigator.canShare && navigator.canShare({ files: [datei] })); } catch (e) { return false; } }
  var bl = document.getElementById('bild_laden'), bt = document.getElementById('bild_teilen');
  if (bl) { bl.addEventListener('click', function () { alles.then(function () { zeichne(wahl.format, wahl.motiv, vorschau); return alsDatei(vorschau); }).then(function (d) { laden(d, d.name); }); }); }
  if (bt) {
    // Probedatei: Ob das Handy Bilder teilen kann, weiß man erst mit einer Datei in der Hand.
    if (teilenMoeglich(new File([new Blob(['x'], { type: 'image/png' })], 'probe.png', { type: 'image/png' }))) { bt.hidden = false; }
    bt.addEventListener('click', function () {
      alsDatei(vorschau).then(function (d) { return navigator.share({ files: [d], text: D.kurz }); }).catch(function () {});
    });
  }
  neu();

  // Video
  var vk = document.getElementById('video_erzeugen'), vs = document.getElementById('video_stand'), vv = document.getElementById('video_vorschau');
  var vl = document.getElementById('video_laden'), vt = document.getElementById('video_teilen'), vDatei = null;
  function vidZuruck() { vDatei = null; if (vv) { vv.hidden = true; vv.removeAttribute('src'); } if (vl) { vl.hidden = true; } if (vt) { vt.hidden = true; } }
  var art = videoArt();
  if (vk && !art) { vk.disabled = true; vs.textContent = D.t.video_nein; }
  if (vk && art) {
    vk.addEventListener('click', function () {
      vk.disabled = true; vidZuruck();
      alles.then(function () {
        // Auf dem Handy 720p: 1080p in Echtzeit zu zeichnen und zu kodieren schafft nicht jedes Gerät ohne Ruckler.
        var klein = matchMedia('(max-width: 820px)').matches || (navigator.deviceMemory && navigator.deviceMemory < 4);
        var b = klein ? 720 : 1080, h = klein ? 1280 : 1920, c = document.createElement('canvas'); c.width = b; c.height = h;
        var x = c.getContext('2d'), strom = c.captureStream(30), teile = [];
        var rec = new MediaRecorder(strom, { mimeType: art, videoBitsPerSecond: klein ? 5e6 : 9e6 });
        rec.ondataavailable = function (e) { if (e.data && e.data.size) { teile.push(e.data); } };
        rec.onstop = function () {
          var typ = art.split(';')[0], endung = typ === 'video/mp4' ? 'mp4' : 'webm';
          var blob = new Blob(teile, { type: typ }); vDatei = new File([blob], dateiname(endung), { type: typ });
          vv.src = URL.createObjectURL(blob); vv.hidden = false; vl.hidden = false;
          if (teilenMoeglich(vDatei)) { vt.hidden = false; }
          vs.textContent = endung === 'webm' ? D.t.video_webm : D.t.video_fertig; vk.disabled = false;
        };
        var start = performance.now(); bild(wahl.motiv, x, b, h, 0); rec.start(250);
        (function schritt() {
          var s = (performance.now() - start) / 1000;
          bild(wahl.motiv, x, b, h, Math.min(s, DAUER));
          vs.textContent = D.t.video_laeuft.replace('{s}', String(Math.max(0, Math.ceil(DAUER - s))));
          if (s < DAUER) { requestAnimationFrame(schritt); } else { setTimeout(function () { rec.stop(); }, 150); }
        })();
      });
    });
    vl.addEventListener('click', function () { if (vDatei) { laden(vDatei, vDatei.name); } });
    vt.addEventListener('click', function () { if (vDatei) { navigator.share({ files: [vDatei] }).catch(function () {}); } });
  }
  window.__vecomMedien = { zeichne: zeichne, bild: bild, FORMATE: FORMATE, bereit: alles };
})();
