"""Wandkalender A3 hoch (Gelato) für Partner — Marketingcenter, 04.10.2026.

Gelato-Vorgaben (support.gelato.com, Artikel 8996274, geprüft 04.10.2026):
297 × 420 mm, 4 mm Beschnitt, ~300 dpi, PDF; 14 Seiten = Titel, 12 Monate,
eine leere Seite; Wire-O oben, Sicherheitsabstand 4 mm, an der Bindung 12 mm.

Hier entstehen je Sprache die Seiten OHNE Partnerdaten und OHNE Feiertage:
Titel + 12 Monate, dazu layout.json mit der Lage jedes Tages im Kalendarium,
der Fußplatte für Name/Link/Kontakt und dem QR-Feld. PHP (WmKalender) legt
darüber: Fußplatte des Partners (ein Bild, auf allen Seiten dasselbe), den
Code als Vektor und die Feiertage seines Landes als kleine Zellenbilder.

Konzept „12 Monate, 12 Ideen“: Jeder Monat ein praktischer Tipp für den
Betrieb — nützlich, keine Versprechen. Einheit: 1 mm = 10 CSS-px.

Aufruf (im Ordner tools/visitenkarten):  python3 ../werbemittel/kalender.py <ausgabe> [jahr]
"""
import calendar, json, os, sys
sys.path.insert(0, os.getcwd())
import gen as K
from playwright.sync_api import sync_playwright

B_MM, W_MM, H_MM, DPI = 4, 297, 420, 300
B, W, H = B_MM * 10, (W_MM + 2 * B_MM) * 10, (H_MM + 2 * B_MM) * 10
def Y(mm): return B + mm * 10
def X(mm): return B + mm * 10

GOLD, GOLD_DUNKEL, CREME, TINTE = '#e6b85c', '#9a6f25', '#f6f1e6', '#1f1a13'
PAPIER, LINIE = '#f7f2e8', '#d9ccb4'
RAND = 18                         # mm Seitenrand links/rechts (innerhalb des Endformats)
ZONE_GRID, ZONE_FUSS = 196, 384   # mm von oben: Beginn Kalendarium, Beginn Fußplatte

MONATE = {
    'de': ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
    'it': ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'],
    'en': ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
}
TAGE = {'de': ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'], 'it': ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'],
        'en': ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']}
T = {
    'tipp': {'de': 'Idee des Monats', 'it': 'Idea del mese', 'en': 'Idea of the month'},
    'ansprech': {'de': 'Ihr Ansprechpartner', 'it': 'Il suo referente', 'en': 'Your contact'},
    'jetzt': {'de': 'Jetzt scannen', 'it': 'Scansiona ora', 'en': 'Scan now'},
    'titel1': {'de': '12 Monate.', 'it': '12 mesi.', 'en': '12 months.'},
    'titel2': {'de': '12 Ideen für Ihren digitalen Auftritt.', 'it': '12 idee per la sua presenza digitale.', 'en': '12 ideas for your digital presence.'},
    'art': {'de': 'Wandkalender', 'it': 'Calendario da parete', 'en': 'Wall calendar'},
    'unter': {'de': 'Webdesign · Logo Design · Branding', 'it': 'Webdesign · Logo Design · Branding', 'en': 'Web design · Logo design · Branding'},
}
# Die zwölf Ideen: (Überschrift, Satz). Nur Belegbares, keine Zahlenversprechen.
IDEEN = {
    'de': [
        ('Ist Ihr Google-Profil aktuell?', 'Öffnungszeiten, Telefonnummer, Fotos: Viele Kunden sehen Ihr Google-Profil, bevor sie Ihre Website sehen. Prüfen Sie es zum Jahresbeginn.'),
        ('Testen Sie Ihre Website auf dem Handy.', 'Rufen Sie Ihre Seite auf dem Smartphone auf: Ist alles lesbar? Lässt sich die Telefonnummer antippen?'),
        ('Zeigen Sie echte Fotos.', 'Ihr Team, Ihre Räume, Ihre Arbeit: Echte Bilder schaffen mehr Vertrauen als Bilder aus dem Katalog.'),
        ('Bitten Sie um Bewertungen.', 'Fragen Sie zufriedene Kunden nach einer Google-Bewertung — und beantworten Sie jede Bewertung, auch die kritischen.'),
        ('Machen Sie Kontakt einfach.', 'Telefon, WhatsApp, E-Mail: Ein Tipp sollte genügen, um Sie zu erreichen — auf jeder Seite Ihrer Website.'),
        ('Wie schnell lädt Ihre Website?', 'Wer warten muss, geht. Große Bilder und alte Technik bremsen — oft lässt sich das einfach beheben.'),
        ('Kündigen Sie Ihre Urlaubszeiten an.', 'Sommerpause oder geänderte Zeiten? Tragen Sie sie rechtzeitig auf Website, Google-Profil und Social Media ein.'),
        ('Sind Ihre Pflichtangaben vollständig?', 'Impressum, Datenschutzerklärung, Cookie-Hinweis: Prüfen Sie, ob alles vorhanden und aktuell ist.'),
        ('Lieber regelmäßig als viel.', 'Ein fester Tag pro Woche für einen Beitrag bringt mehr als zehn Beiträge auf einmal und danach Funkstille.'),
        ('Planen Sie Ihr Jahresende online.', 'Gutscheine, Weihnachtsangebote, Öffnungszeiten an Feiertagen: Stellen Sie sie früh online, nicht erst im Dezember.'),
        ('Woher kommen Ihre Kunden?', 'Fragen Sie neue Kunden, wie sie Sie gefunden haben. So wissen Sie, was wirkt — und was nicht.'),
        ('Was soll Ihr Auftritt 2028 können?', 'Ein neues Jahr ist ein guter Moment für einen besseren Auftritt. Ihr Ansprechpartner unten hilft Ihnen gern.'),
    ],
    'it': [
        ('Il suo profilo Google è aggiornato?', 'Orari, telefono, foto: molti clienti vedono il suo profilo Google prima del suo sito. Lo controlli a inizio anno.'),
        ('Provi il suo sito sullo smartphone.', 'Apra il suo sito sul telefono: si legge tutto? Il numero di telefono si può toccare per chiamare?'),
        ('Mostri foto vere.', 'Il suo team, i suoi locali, il suo lavoro: le immagini vere creano più fiducia di quelle da catalogo.'),
        ('Chieda recensioni.', 'Chieda ai clienti soddisfatti una recensione su Google — e risponda a ogni recensione, anche a quelle critiche.'),
        ('Renda facile contattarla.', 'Telefono, WhatsApp, e-mail: un tocco deve bastare per raggiungerla — su ogni pagina del suo sito.'),
        ('Quanto è veloce il suo sito?', 'Chi deve aspettare se ne va. Immagini pesanti e tecnologia datata rallentano — spesso si risolve facilmente.'),
        ('Annunci le sue ferie.', 'Pausa estiva o orari diversi? Li inserisca per tempo sul sito, sul profilo Google e sui social.'),
        ('I dati obbligatori sono completi?', 'Partita IVA, informativa privacy, banner cookie: verifichi che tutto sia presente e aggiornato.'),
        ('Meglio con regolarità che tanto.', 'Un giorno fisso alla settimana per un post rende più di dieci post in una volta e poi silenzio.'),
        ('Prepari online la fine dell’anno.', 'Buoni regalo, offerte di Natale, orari dei giorni festivi: li pubblichi presto, non solo a dicembre.'),
        ('Da dove arrivano i suoi clienti?', 'Chieda ai nuovi clienti come l’hanno trovata. Così saprà cosa funziona — e cosa no.'),
        ('Cosa deve offrire il suo sito nel 2028?', 'Un nuovo anno è il momento giusto per un’immagine migliore. Il suo referente qui sotto la aiuta volentieri.'),
    ],
    'en': [
        ('Is your Google profile up to date?', 'Opening hours, phone number, photos: many customers see your Google profile before your website. Check it at the start of the year.'),
        ('Test your website on a phone.', 'Open your site on a smartphone: is everything readable? Can the phone number be tapped to call?'),
        ('Show real photos.', 'Your team, your premises, your work: real pictures build more trust than stock images.'),
        ('Ask for reviews.', 'Ask happy customers for a Google review — and reply to every review, including critical ones.'),
        ('Make contact easy.', 'Phone, WhatsApp, email: one tap should be enough to reach you — on every page of your website.'),
        ('How fast does your website load?', 'People who have to wait leave. Large images and old technology slow things down — often an easy fix.'),
        ('Announce your holidays.', 'Summer break or changed hours? Add them in good time to your website, Google profile and social media.'),
        ('Is your legal information complete?', 'Imprint, privacy policy, cookie notice: check that everything is there and up to date.'),
        ('Regular beats a lot.', 'One fixed day a week for a post achieves more than ten posts at once followed by silence.'),
        ('Plan your year-end online.', 'Gift vouchers, Christmas offers, holiday opening hours: publish them early, not just in December.'),
        ('Where do your customers come from?', 'Ask new customers how they found you. Then you know what works — and what doesn’t.'),
        ('What should your presence do in 2028?', 'A new year is a good moment for a better presence. Your contact below will be happy to help.'),
    ],
}


def text(x, y, s, gr, gew, farbe, anker='start', extra=''):
    return f'<text x="{x}" y="{y}" text-anchor="{anker}" font-family="MS" font-size="{gr}" font-weight="{gew}" fill="{farbe}" {extra}>{s}</text>'


def ecke():
    """Goldbänder nur oben rechts (wie die Flyer: nie durch Text)."""
    g = ''
    for x0, br, fill, op in [(W * 0.86, W * 0.10, 'url(#dunkelgold)', .95), (W * 0.97, W * 0.035, 'url(#gold)', .95)]:
        g += K.band(x0, br, k=0.62, fill=fill, op=op, filt='gebuerstet')
    return g + K.linie(W * 0.97, k=0.62)


def fuss(lang):
    """Dunkle Fußplatte mit Goldkante; Name/Link/Kontakt setzt PHP, der Code kommt als Vektor."""
    g = f'<rect x="0" y="{Y(ZONE_FUSS)}" width="{W}" height="{H - Y(ZONE_FUSS)}" fill="#0d0c0a"/>'
    g += f'<rect x="0" y="{Y(ZONE_FUSS)}" width="{W}" height="6" fill="url(#goldH)"/>'
    qs = 240
    qx, qy = X(W_MM - RAND) - qs, Y(ZONE_FUSS + 7)
    box, modul = K.qr_box(qx, qy, qs, 'A')
    g += box
    g += text(qx - 40, qy + qs / 2 + 22, T['jetzt'][lang].upper(), 46, 700, GOLD, 'end', 'letter-spacing="5"')
    g += text(X(RAND), Y(ZONE_FUSS + 8.5), T['ansprech'][lang], 42, 500, CREME, extra='opacity=".8"')
    # Fläche für Name/Link/Kontakt (PHP, Grundlinien bei 70/128/178) — endet vor dem 4-mm-Sicherheitsrand unten.
    platte = [X(RAND), Y(ZONE_FUSS + 10.5), round(qx - 600 - X(RAND)), 200]
    return g, modul, platte


def monat(jahr, m, lang):
    g = K.schwarz_grund() + ecke()
    # Kein Wasserzeichen mehr (04.10.2026): die große Monatszahl lief bei langen Ideen in den Text — Lesbarkeit geht vor.
    g += K.logo_quer(X(RAND), Y(20), 120)
    g += text(X(W_MM - RAND - 70), Y(31), str(jahr), 60, 700, GOLD, 'end', 'letter-spacing="8"')
    g += text(X(RAND), Y(82), MONATE[lang][m - 1].upper(), 290, 700, GOLD, extra='letter-spacing="10"')
    g += f'<rect x="{X(RAND)}" y="{Y(94)}" width="420" height="5" fill="url(#goldH)"/>'
    g += text(X(RAND), Y(110), T['tipp'][lang].upper() + f'  ·  {m:02d}', 44, 600, GOLD, extra='letter-spacing="7"')
    titel, satz = IDEEN[lang][m - 1]
    g += (f'<foreignObject x="{X(RAND)}" y="{Y(115)}" width="2300" height="{(ZONE_GRID - 120) * 10}">'
          f'<div xmlns="http://www.w3.org/1999/xhtml" style="font-family:MS;color:{CREME}">'
          f'<div style="font-size:122px;font-weight:700;line-height:1.12;letter-spacing:-.5px">{titel}</div>'
          f'<div style="font-size:66px;font-weight:500;line-height:1.42;margin-top:34px;opacity:.88">{satz}</div></div></foreignObject>')
    # Kalendarium auf ruhigem Papierton (flach — PHP legt Feiertagszellen deckungsgleich darüber)
    g += f'<rect x="0" y="{Y(ZONE_GRID)}" width="{W}" height="{Y(ZONE_FUSS) - Y(ZONE_GRID)}" fill="{PAPIER}"/>'
    x0, x1 = X(RAND - 6), X(W_MM - RAND + 6)
    kb = (x1 - x0) / 7
    kopf = 110
    g += f'<rect x="{x0}" y="{Y(ZONE_GRID + 8)}" width="{x1 - x0}" height="{kopf}" fill="{TINTE}"/>'
    for i, t in enumerate(TAGE[lang]):
        g += text(x0 + kb * i + kb / 2, Y(ZONE_GRID + 8) + 74, t.upper(), 46, 700, GOLD if i == 6 else CREME, 'middle', 'letter-spacing="4"')
    wochen = calendar.Calendar(0).monthdayscalendar(jahr, m)
    gy0 = Y(ZONE_GRID + 8) + kopf
    zh = (Y(ZONE_FUSS - 6) - gy0) / len(wochen)     # so viele Zeilen, wie der Monat Wochen hat: volle Fläche zum Schreiben
    zellen = {}
    for r, woche in enumerate(wochen):
        for c, tag in enumerate(woche):
            x, y = x0 + kb * c, gy0 + zh * r
            g += f'<rect x="{x}" y="{y}" width="{kb}" height="{zh}" fill="none" stroke="{LINIE}" stroke-width="3"/>'
            if tag:
                g += text(x + 26, y + 92, str(tag), 72, 700, GOLD_DUNKEL if c == 6 else TINTE)
                zellen[str(tag)] = [round(x + 4, 1), round(y + 4, 1), round(kb - 8, 1), round(zh - 8, 1)]
    f, modul, platte = fuss(lang)
    return g + f, {'zellen': zellen, 'qr': modul, 'platte': platte}


def titel(jahr, lang):
    g = K.schwarz_grund() + ecke()
    for x0, br, fill, op in [(-W * 0.80, W * 0.13, 'url(#dunkelgold)', .9), (-W * 0.66, W * 0.05, 'url(#gold)', 1)]:
        g += K.band(x0, br, k=0.62, fill=fill, op=op, filt='gebuerstet')
    l, unten = K.logo_gross(W / 2, Y(42), 820)
    g += l
    g += text(W / 2, Y(238), str(jahr), 560, 700, GOLD, 'middle', 'letter-spacing="24"')
    g += f'<rect x="{W / 2 - 220}" y="{Y(252)}" width="440" height="6" fill="url(#goldH)"/>'
    g += text(W / 2, Y(282), T['titel1'][lang], 140, 700, CREME, 'middle')
    g += (f'<foreignObject x="{X(RAND + 8)}" y="{Y(290)}" width="{(W_MM - 2 * RAND - 16) * 10}" height="330">'
          f'<div xmlns="http://www.w3.org/1999/xhtml" style="font-family:MS;color:{GOLD};font-size:112px;font-weight:700;line-height:1.16;text-align:center">{T["titel2"][lang]}</div></foreignObject>')
    g += text(W / 2, Y(ZONE_FUSS - 22), T['art'][lang].upper() + f' {jahr}', 50, 600, CREME, 'middle', 'letter-spacing="9" opacity=".85"')
    g += text(W / 2, Y(ZONE_FUSS - 11), T['unter'][lang], 44, 500, CREME, 'middle', 'letter-spacing="3" opacity=".7"')
    f, modul, platte = fuss(lang)
    return g + f, {'qr': modul, 'platte': platte}


def html(inhalt):
    K.W, K.H, K.B = W, H, B
    return (f'<!doctype html><html><head><meta charset="utf-8"><style>{K.FONTFACE}html,body{{margin:0;padding:0;background:#000}} svg{{display:block}}</style></head>'
            f'<body><svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}">{K.defs()}{inhalt}</svg></body></html>')


if __name__ == '__main__':
    out = sys.argv[1]
    jahr = int(sys.argv[2]) if len(sys.argv) > 2 else 2027
    os.makedirs(out, exist_ok=True)
    K.W, K.H, K.B = W, H, B
    layout = {'b': W_MM, 'h': H_MM, 'beschnitt': B_MM, 'jahr': jahr, 'seiten': 14, 'monate': {}}
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium' if os.path.exists('/opt/pw-browsers/chromium') else None,
                              args=['--disable-gpu', '--disable-gpu-rasterization'])
        for lang in os.environ.get('SPRACHEN', 'de,it,en').split(','):
            for nr in range(0, 13):
                inhalt, lay = titel(jahr, lang) if nr == 0 else monat(jahr, nr, lang)
                ziel = f'{out}/{lang}-{nr:02d}.png'
                # Wie das Roll-up in Streifen: große Seiten am Stück gab Chromium mit schwarzen Kacheln aus.
                from PIL import Image
                Image.MAX_IMAGE_PIXELS = None
                STREIFEN = 1000
                pg = b.new_page(viewport={'width': W, 'height': STREIFEN}, device_scale_factor=DPI / 254)
                pg.set_content(html(inhalt).replace('<body>', '<body style="overflow:hidden"><div id="wanne">', 1).replace('</body>', '</div></body>', 1))
                pg.wait_for_timeout(500)
                teile, y = [], 0
                while y < H:
                    h = min(STREIFEN, H - y)
                    pg.evaluate(f"document.getElementById('wanne').style.transform = 'translateY(-{y}px)'")
                    pg.wait_for_timeout(250)
                    pfad = f'{ziel}.{y}.png'
                    pg.screenshot(path=pfad, clip={'x': 0, 'y': 0, 'width': W, 'height': h})
                    teile.append(pfad); y += h
                pg.close()
                bilder = [Image.open(t).convert('RGB') for t in teile]
                ganz = Image.new('RGB', (bilder[0].width, sum(x.height for x in bilder)))
                yy = 0
                for x in bilder: ganz.paste(x, (0, yy)); yy += x.height
                ganz.crop((0, 0, ganz.width, min(ganz.height, round(H * DPI / 254)))).save(ziel)
                for t in teile: os.remove(t)
                if lang == 'de':
                    if nr == 0: layout['titel'] = lay
                    else: layout['monate'][str(nr)] = lay
                print(lang, nr, flush=True)
        b.close()
    json.dump(layout, open(f'{out}/layout.json', 'w'), indent=1)
