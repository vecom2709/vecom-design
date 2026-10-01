(()=>{
const O='__ORIGIN__',A='__APP__';
const F=d=>{
const T=s=>String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ').trim();
const docs=[document];
for(const f of document.querySelectorAll('iframe')){try{if(f.contentDocument&&f.contentDocument.body)docs.push(f.contentDocument)}catch(x){}}
const felder=[];
for(const doc of docs){for(const el of doc.querySelectorAll('input,textarea,select')){
const t=(el.type||'').toLowerCase();
if(el.disabled||el.readOnly||['hidden','password','file','checkbox','radio','submit','button','image','reset','date','time','color','range'].includes(t))continue;
const r=el.getBoundingClientRect();if(r.width<2&&r.height<2)continue;
let l='';try{if(el.labels)for(const x of el.labels)l+=' '+x.textContent}catch(x){}
const lb=el.getAttribute('aria-labelledby');if(lb)for(const i of lb.split(/\s+/)){const x=doc.getElementById(i);if(x)l+=' '+x.textContent}
if(!l.trim()){const c=el.closest('label');if(c)l=c.textContent}
if(!l.trim()){let p=el.previousElementSibling;if(p&&p.textContent.length<80)l=p.textContent}
felder.push({el,t,doc,d:T([el.name,el.id,el.placeholder,el.getAttribute('aria-label'),el.title,el.autocomplete,l].join(' '))})}}
const hatNachname=felder.some(f=>/cognome|last ?name|surname|nachname|family name/.test(f.d));
const kurz=(v,m)=>{if(!m||m<1||v.length<=m)return v;const s=v.slice(0,m);const i=Math.max(s.lastIndexOf('. '),s.lastIndexOf(' '));return(i>m*0.6?s.slice(0,i+(s[i]==='.'?1:0)):s).trim()};
const R=[
['kanal',f=>d.kanal&&/telegram|t\.me|tg link|(link|url).{0,12}canale|canale.{0,12}(link|url)|(link|url).{0,12}channel|channel.{0,12}(link|url)|username/.test(f.d),f=>/username|@/.test(f.d)?d.kanal_name:d.kanal],
['email',f=>f.t==='email'||/e-?mail|posta elettronica/.test(f.d)&&!/pec/.test(f.d),()=>d.email],
['telefon',f=>f.t==='tel'||/telefon|phone|cellulare|\bcell\b|mobile|\btel\b|handy/.test(f.d),()=>d.telefon],
['piva',f=>/partita iva|p\.? ?iva|vat|ust-?id|umsatzsteuer/.test(f.d),()=>d.piva],
['plz',f=>/\bcap\b|zip|postal|postleitzahl|\bplz\b|postcode/.test(f.d),()=>d.plz],
['region',f=>/regione|\bregion\b|bundesland/.test(f.d),()=>d.region],
['provinz',f=>/provincia|province|\bprov\b/.test(f.d),f=>f.el.maxLength>0&&f.el.maxLength<4?d.provinz:d.provinz_name],
['ort',f=>/citta|comune|\bcity\b|town|\bort\b|localita|stadt|location/.test(f.d),()=>d.ort],
['land',f=>/paese|nazione|country|\bland\b|\bstato\b/.test(f.d),()=>d.land],
['web',f=>f.t==='url'||/sito|website|web ?site|homepage|webseite|\burl\b|indirizzo web/.test(f.d),()=>d.web],
['strasse',f=>/indirizzo|address|\bvia\b|strasse|street|anschrift/.test(f.d),()=>d.strasse],
['nachname',f=>/cognome|last ?name|surname|nachname|family name/.test(f.d),()=>d.nachname],
['kontakt',f=>/referente|contact ?person|your name|full name|nome e cognome|nome completo|ansprechpartner|dein name|ihr name/.test(f.d),()=>d.inhaber],
['name',f=>/ragione sociale|nome (dell'?)? ?(azienda|attivita|impresa|ditta|societa|canale|gruppo)|azienda|attivita|business|company|firma|unternehmen|channel name|titolo|title|organizza|organization|brand|insegna/.test(f.d),()=>d.name],
['stichworte',f=>/parole chiave|keyword|\btag|schlagw|schlusselw/.test(f.d),()=>d.stichworte],
['text',f=>f.el.tagName==='TEXTAREA'||/descrizione|description|beschreibung|about|chi siamo|presentazione|\bbio\b|testo/.test(f.d),f=>{const m=f.el.maxLength>0?f.el.maxLength:0;return kurz(d.art==='telegram'||(m&&m<400)?d.kurz:d.lang,m)}],
['vorname',f=>/first ?name|vorname|given name/.test(f.d)||hatNachname&&/\bnome\b/.test(f.d),()=>d.vorname],
['name',f=>/\bnome\b|\bname\b/.test(f.d),()=>d.name]
];
const W={kategorie:/categor|settore|rubrica|branche|category|industry|attivita|sector|topic|argomento/,sprache:/lingua|language|sprache|idioma/,land:/paese|nazione|country|\bland\b|\bstato\b/,provinz:/provincia|province|\bprov\b/,region:/regione|\bregion\b|bundesland/};
const setze=(f,v)=>{const w=f.doc.defaultView;const p=f.el.tagName==='TEXTAREA'?w.HTMLTextAreaElement.prototype:w.HTMLInputElement.prototype;const s=Object.getOwnPropertyDescriptor(p,'value').set;f.el.focus();s.call(f.el,v);f.el.dispatchEvent(new w.Event('input',{bubbles:true}));f.el.dispatchEvent(new w.Event('change',{bubbles:true}));f.el.blur();f.el.style.outline='2px solid #d9b46a';f.el.style.outlineOffset='1px'};
const waehle=(f,listen)=>{const alle=[...f.el.options].filter(o=>o.value!=='');const gut=alle.filter(o=>!(d.meiden||[]).some(m=>T(o.value+' '+o.textContent).includes(m)));const opts=gut.length?gut:alle;for(const k of listen){const o=opts.find(o=>T(o.textContent).replace(/&amp;/g,'&').includes(T(k)));if(o){f.el.value=o.value;f.el.dispatchEvent(new f.doc.defaultView.Event('change',{bubbles:true}));f.el.style.outline='2px solid #d9b46a';return o.textContent.trim()}}return ''};
const gefuellt=[],offen=[];
for(const f of felder){
if(f.el.tagName==='SELECT'){
if(f.el.selectedIndex>0)continue;
let n='';
if(W.sprache.test(f.d))n=waehle(f,d.sprache_namen);
else if(W.region.test(f.d))n=waehle(f,[d.region]);
else if(W.provinz.test(f.d))n=waehle(f,[d.provinz_name,d.provinz]);
else if(W.land.test(f.d))n=waehle(f,d.land_namen);
else if(W.kategorie.test(f.d)){n=waehle(f,d.kategorien);if(!n)offen.push('Kategorie')}
if(n)gefuellt.push(n);continue}
const jetzt=String(f.el.value||'').trim();
for(const [k,passt,wert] of R){if(!passt(f))continue;
const v=String(wert(f)||'');if(!v){break}
if(jetzt&&!v.startsWith(jetzt))break;
setze(f,v);gefuellt.push(k);break}}
const box=document.createElement('div');box.style.cssText='position:fixed;z-index:2147483647;top:14px;right:14px;max-width:330px';
const sh=box.attachShadow({mode:'closed'});const k=document.createElement('div');
k.style.cssText='font:14px/1.45 system-ui,sans-serif;background:#17140f;color:#efe6d2;border:1px solid #d9b46a;border-radius:12px;padding:12px 14px;box-shadow:0 8px 30px rgba(0,0,0,.35)';
const z=(t,b)=>{const p=document.createElement('div');p.textContent=t;if(b)p.style.fontWeight='700';k.appendChild(p)};
z('Vecom: '+gefuellt.length+' Felder ausgefüllt (gold umrandet)',1);
if(offen.length)z('Bitte selbst wählen: '+offen.join(', '));
z(gefuellt.length?'Bitte prüfen, ein Captcha lösen und absenden. Danach im kleinen Fenster „Eingereicht“.':'Auf dieser Seite kein passendes Feld gefunden — vielleicht erst zum Formular weiterklicken und noch einmal drücken.');
const x=document.createElement('button');x.textContent='Schließen';x.style.cssText='margin-top:8px;background:#d9b46a;color:#16120b;border:0;border-radius:8px;padding:5px 10px;cursor:pointer;font-weight:700';x.onclick=()=>box.remove();k.appendChild(x);
sh.appendChild(k);(document.body||document.documentElement).appendChild(box);setTimeout(()=>box.remove(),30000)};
if(!window.__vecomHelfer){window.__vecomHelfer=1;addEventListener('message',e=>{if(e.origin!==O||!e.data||typeof e.data!=='object'||!e.data.vecomAusfuellen)return;F(e.data.vecomAusfuellen)})}
window.open(A+'/ausfuellen?h='+encodeURIComponent(location.hostname)+'&o='+encodeURIComponent(location.origin),'vecomhelfer','width=470,height=640');
})();
