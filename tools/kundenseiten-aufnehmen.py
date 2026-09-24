"""Bahn-Aufnahmen der Kundenseiten, Fassung 3 (24.09.2026).

ref.py:  sofort nach dem Laden, full_page -> graue Kästen (Nachlader) und
         halbe Einblendungen.
ref2.py: vorher durchgescrollt, aber full_page -> Chromium vergrößert dafür
         das Fenster; Mensaena malte dabei schwarze Rechtecke, alles unter
         dem Aufmacher blieb dunkel (die Einblender sahen nie ein Fenster).
Jetzt: genau so, wie ein Besucher scrollt -- Fenster in Bildschirmgröße des
Geräts im Foto (1440 x 824 bzw. 390 x 727), drei Aufnahmen untereinander,
feste Kopfleisten ab der zweiten ausgeblendet, Cookie-Hinweise per Stil
verborgen (nichts wird angeklickt)."""
import asyncio, os, sys
from playwright.async_api import async_playwright
from PIL import Image
SEITEN = {'cavaleri':'https://cavaleri-trasporti.netlify.app/', 'jonika':'https://jonika-venturis.com/', 'mensaena':'https://mensaena.de/', 'trendonix':'https://www.trendonix-buecher.de/'}
WAHL = sys.argv[1:] or list(SEITEN)
BANNER_WEG = """() => {
  for (const el of document.querySelectorAll('body *')) {
    const s = getComputedStyle(el);
    if ((s.position === 'fixed' || s.position === 'sticky') && /cookie|zählen|datenschutz|consent/i.test(el.textContent || '') && el.textContent.length < 800
        && el.getBoundingClientRect().top > innerHeight * 0.35) el.style.setProperty('display', 'none', 'important');
  }
}"""
# Nur kleine feste Elemente (Kopfleiste, Knöpfe, Hinweise) -- nicht große:
# Seiten mit eigenem Scrollen legen den ganzen Inhalt in einen festen
# Behälter; ihn auszublenden machte bei Mensaena alles schwarz.
FEST_WEG = """() => { for (const el of document.querySelectorAll('body *')) { const s = getComputedStyle(el);
  if (s.position !== 'fixed' && s.position !== 'sticky') continue;
  const r = el.getBoundingClientRect();
  if (r.height < innerHeight * 0.3 && r.width * r.height < innerWidth * innerHeight * 0.35) el.style.setProperty('visibility', 'hidden', 'important'); } }"""
async def main():
    prox = os.environ.get('HTTPS_PROXY') or os.environ.get('https_proxy')
    async with async_playwright() as p:
        b = await p.chromium.launch(proxy={'server': prox} if prox else None)
        for k in WAHL:
            for name, vp, dsf in (('laptop', {'width':1440,'height':824}, 2), ('handy', {'width':390,'height':727}, 3)):
                ctx = await b.new_context(viewport=vp, device_scale_factor=dsf, ignore_https_errors=True, is_mobile=(name=='handy'), has_touch=(name=='handy'))
                pg = await ctx.new_page()
                await pg.goto(SEITEN[k], wait_until='load', timeout=60000)
                await pg.wait_for_timeout(3500)
                # Das erste Bild sofort: Es muss zum Bildschirm im Fotorender passen,
                # und Cavaleri wechselt im Aufmacher die Fotos -- nach dem
                # Vorscrollen stand dort schon ein anderes (Sprung am Filmende).
                await pg.evaluate(BANNER_WEG)
                await pg.screenshot(path=f'/tmp/claude-0/ref3/{k}-{name}-0.png')
                # einmal durch die ersten Bildschirme und zurück: Nachlader laden vor
                for y in range(0, vp['height'] * 4, vp['height'] // 3):
                    await pg.mouse.wheel(0, vp['height'] // 3); await pg.wait_for_timeout(180)
                await pg.mouse.wheel(0, -100000); await pg.wait_for_timeout(1500); await pg.evaluate("window.scrollTo(0,0)"); await pg.wait_for_timeout(4500)
                await pg.evaluate(BANNER_WEG)
                # Ab dem zweiten Bild an Abschnittsanfänge springen statt um feste
                # Bildschirmhöhen: Mensaena heftet den Aufmacher an und blendet
                # darin um -- dort zeigte jede weitere Bildschirmhöhe nur Schwarz.
                ziele = await pg.evaluate("""(H) => { const out = []; let grenze = H * 0.85;
                  const secs = [...document.querySelectorAll('section, main > div, footer')].map(e => Math.round(e.getBoundingClientRect().top + scrollY)).sort((a, b) => a - b);
                  for (const t of secs) { if (t >= grenze && out.length < 2) { out.push(t); grenze = t + H * 0.85; } }
                  while (out.length < 2) out.push((out.length + 1) * H); return out; }""", vp['height'])
                if k == 'mensaena':
                    # Aufmacher mit Scroll-Erzählung (vier Überschriften übereinander
                    # im selben angehefteten Bereich): ein Bild mitten in der Erzählung,
                    # dann der erste feste Abschnitt danach („Sieben Welten").
                    ziele = await pg.evaluate("""(H) => { const hs = [...document.querySelectorAll('h2')].map(h => Math.round(h.getBoundingClientRect().top + scrollY));
                      const erst = hs[0]; const fest = hs.find(t => t > erst + 2 * H) || erst + 3 * H;
                      return [Math.round(erst + H * 0.35), fest - Math.round(H * 0.12)]; }""", vp['height'])
                teile = []
                for i in range(3):
                    if i:
                        # echte Radbewegung statt scrollTo: Seiten mit eigener
                        # Scroll-Steuerung (Lenis/GSAP) reagieren nur darauf
                        await pg.mouse.move(vp['width'] // 2, vp['height'] // 2)
                        ist = await pg.evaluate("scrollY")
                        while ist < ziele[i-1] - 4:
                            await pg.mouse.wheel(0, min(120, ziele[i-1] - ist)); await pg.wait_for_timeout(60)
                            neu = await pg.evaluate("scrollY")
                            if neu == ist: await pg.wait_for_timeout(300); neu = await pg.evaluate("scrollY")
                            if neu == ist: break
                            ist = neu
                        await pg.wait_for_timeout(2200)
                        await pg.evaluate(FEST_WEG); await pg.evaluate(BANNER_WEG)
                    pfad = f'/tmp/claude-0/ref3/{k}-{name}-{i}.png'
                    if i: await pg.screenshot(path=pfad)
                    teile.append(Image.open(pfad))
                w = teile[0].width; ges = Image.new('RGB', (w, sum(t.height for t in teile)))
                y = 0
                for t in teile: ges.paste(t.convert('RGB'), (0, y)); y += t.height
                ges.save(f'/tmp/claude-0/ref3/{k}-{name}-ganz.png')
                print(k, name, ges.size, ziele)
                await ctx.close()
        await b.close()
asyncio.run(main())
