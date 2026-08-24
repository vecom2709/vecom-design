import { chromium } from 'playwright';
const b = await chromium.launch({args:['--use-gl=swiftshader','--enable-unsafe-swiftshader']});
const sites = [
  ['mensaena', 'https://mensaena.de'],
  ['dragis',   'https://dragis-kitchen.de'],
  ['charme',   'https://manuelbrandner85.github.io/Friseursalon-Nadia/'],
];
for (const [name, url] of sites) {
  const ctx = await b.newContext({viewport:{width:1280,height:800}, locale:'de-DE'});
  const p = await ctx.newPage();
  try { await p.goto(url, {waitUntil:'networkidle', timeout:40000}); }
  catch(e) { await p.goto(url, {waitUntil:'load', timeout:30000}); }
  await p.waitForTimeout(3000);
  await p.addStyleTag({content:'::-webkit-scrollbar{width:0;height:0}html{scrollbar-width:none}'});
  // Cookie-/Consent-Schichten nur ausblenden, nicht anklicken — es wird nichts
  // bestätigt, das Bild soll nur die Seite zeigen.
  await p.evaluate(() => {
    const rx = /cookie|consent|datenschutz-hinweis|privacy/i;
    document.querySelectorAll('body *').forEach(el => {
      const cs = getComputedStyle(el);
      if ((cs.position === 'fixed' || cs.position === 'sticky') && el.offsetHeight > 40 &&
          rx.test(el.className + ' ' + el.id + ' ' + (el.innerText||'').slice(0,300))) el.style.display = 'none';
    });
  });
  await p.evaluate(async () => { const h=document.body.scrollHeight; for(let y=0;y<h;y+=600){window.scrollTo(0,y); await new Promise(r=>setTimeout(r,110));} window.scrollTo(0,0); });
  await p.waitForTimeout(1800);
  await p.screenshot({path:`/home/claude/caps/${name}.png`, fullPage:true, timeout:60000});
  console.log(name, 'ok');
  await ctx.close();
}
await b.close();
