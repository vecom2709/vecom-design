/* ==========================================================================
   legal-i18n.js — Rechtstexte fuer legal.html, English

   Nur auf legal.html geladen, damit die Startseite nicht mit Text belastet
   wird, den fast niemand aufruft. Und dort nur in EINER Sprache: Wer die
   Seite auf Italienisch oeffnet, laedt die deutschen und englischen
   Rechtstexte erst, wenn er oben umschaltet. Vorher waren es 39 KB fuer
   drei Fassungen, von denen eine gelesen wurde.

   Grundlage: die vom Betreiber gelieferte Fassung des Impressums.
   Datenschutz und AGB sind daraus und aus dem tatsaechlichen Verhalten der
   Seite abgeleitet (keine Cookies, keine fremden Schriften, Formular per
   E-Mail-Programm, Hosting bei All-Inkl).

   WICHTIG: Diese Texte ersetzen keine Rechtsberatung. Sie sollten vor der
   Veroeffentlichung von einem Anwalt oder Commercialista geprueft werden —
   besonders die AGB und die Angaben zur steuerlichen Behandlung.
   ========================================================================== */
(function () {
  const L = {
    legal: {
      title: "Legal notice, privacy & terms — Vecom Design",
      head: "Legal notice, privacy & terms",
      lead: "Three sections: provider details and liability, handling of personal data, general terms of service.",
      nav1: "Legal notice", nav2: "Privacy", nav3: "Terms", nav4: "Withdrawal",
      updated: "Last updated: August 2026",
      check: "These texts are not legal advice. A review by a lawyer or tax adviser is recommended before final publication.",

      i0: "Legal notice & liability",
      i1h: "Provider details",
      i1: "Vecom Design — web design · logo design · web services\nOperator: Uwe Vetter\nVia d’Ascoli 25\n92021 Aragona (AG)\nItaly\nWebsite: vecom-design.it\nEmail: kontakt@vecom-design.it",
      i2h: "Activity",
      i2: "Vecom Design provides web design, website production, logo design and related digital services. The activity is currently carried out on a small scale.",
      i3h: "Tax details",
      i3: "Italian tax code (Codice Fiscale): VTTWUE66P27Z112D",
      i4h: "Responsible for content",
      i4: "Uwe Vetter, Via d’Ascoli 25, 92021 Aragona (AG), Italy — kontakt@vecom-design.it",
      i5h: "Liability for content",
      i5: "The content of this website has been prepared with the greatest possible care. Despite careful checking, no guarantee can be given for the completeness, accuracy and currency of all information provided. Statutory liability remains unaffected.",
      i6h: "Liability for external links",
      i6: "This website may contain links to third-party websites over whose content Vecom Design has no direct influence. The respective operator is responsible for the content of external sites. At the time of linking, no clearly unlawful content was apparent. Should unlawful or problematic content become known, the relevant links will be removed after review.",
      i7h: "Copyright",
      i7: "The content, graphics, logos, designs, images and texts created for this website are — where applicable — protected by copyright. Reproduction, modification, distribution or other use beyond the limits of the law requires prior consent from Vecom Design or the respective rights holder. Third-party content and works are marked as such where necessary.",
      i8h: "Client-supplied material",
      i8: "When producing websites, logos or other designs, texts, images, trademarks or other content supplied by the client may be used. The client is responsible for holding the necessary usage and publication rights to the material they supply. Vecom Design accepts no responsibility for infringements arising solely from material supplied without such rights, as far as legally permissible.",
      i9h: "Availability",
      i9: "Vecom Design endeavours to keep this website and the online services offered reliably available. Continuous, entirely fault-free availability cannot be guaranteed. Maintenance, technical faults, hosting provider outages or events outside the control of Vecom Design may lead to temporary restrictions.",
      i10h: "Scope",
      i10: "This legal notice applies to https://vecom-design.it",

      p0: "Privacy policy",
      p1h: "Controller",
      p1: "Uwe Vetter, Via d’Ascoli 25, 92021 Aragona (AG), Italy — kontakt@vecom-design.it",
      p2h: "In short",
      p2: "For ordinary visits this website uses no tracking cookies, loads no fonts from third-party servers and builds no user profiles; that is why there is no cookie banner. Visits through a partner’s link or one of our campaign links (vecom-design.it/k/…) are the exception — see the section “Partner programme”. For statistics, the site counts visits on our own server: date, hour, which website you came from (its domain only), which page, the device type (phone or computer) and — if the link carries one — a campaign label such as “instagram”; likewise how often buttons such as “Call” or “WhatsApp” are pressed. No IP address, no cookie, no sharing with third parties; individual visits cannot be traced to anyone. The legal basis is our legitimate interest in improving the site (Art. 6(1)(f) GDPR). Personal data is only transmitted when you send the contact form — see below.",
      p3h: "Data collected when visiting",
      p3: "The site is hosted with ALL-INKL.COM — Neue Medien Münnich, Inh. René Münnich, Hauptstraße 68, 02742 Friedersdorf, Germany, on servers located in Germany. For security and operational reasons the provider records technical access data including IP address, date and time of the request, page requested, browser type and operating system. Legal basis: legitimate interest in secure operation (Art. 6(1)(f) GDPR). The data stays within the European Union; no transfer to third countries takes place.",
      p4h: "Contact form",
      p4a: "Anyone who enters their email address on the website to receive their personal dashboard is sent a link to that address. Until the link is opened we store only the address, the chosen language and the time; if it is not opened within seven days, these details are deleted. Opening it creates the customer record, and the following applies. Data entered in the form is transmitted to our own server and delivered from there by email. For sending we use Brevo (Sendinblue GmbH, Köpenicker Straße 126, 10179 Berlin, Germany). Name, email address, phone number (if given) and the content of the enquiry are processed. Legal basis: taking steps at your request prior to entering into a contract (Art. 6(1)(b) GDPR). Processing takes place within the EU; a data processing agreement with Brevo is in place. The enquiry is additionally stored in our project administration on the same server in Germany, so that it can be followed up and turned into a project without re-entering the details. Data is kept as long as needed to reply and handle any resulting project, then within statutory retention periods.",
      p4: "You can also write to us directly by email; the same retention rules apply. If you request a call back, we store your name, phone number, the time slot you chose and — if given — your topic, solely to call you back; the legal basis is pre-contractual steps at your request (Art. 6(1)(b) GDPR).",
      p5h: "Fonts and external content",
      p5: "The fonts used are hosted on our own server. No connection is made to Google Fonts or comparable services. No third-party maps, videos or social buttons are embedded. The phone assistant on the home page is the one exception; see its own section below.",
      p6h: "Local browser storage",
      p6: "The site stores your chosen language in your browser so the same version appears on your next visit. This is technically necessary local storage with no transfer to third parties; you can delete it at any time in your browser settings. The phone assistant adds entries of its own — see the section about it.",
      p8h: "Phone assistant (voice and chat window)",
      p8: "The home page and your personal customer page include a voice and chat assistant. We run it with the “AI Frontdesk” service of STRATO GmbH, Otto-Ostrowski-Straße 7, 10249 Berlin, Germany. When the page loads, the window loads a script from strato.ai-voicereceptionist.com. Your IP address and technical details about your browser and device are transmitted to that provider, and the service stores cookies and entries in your browser’s local storage to manage the session. If you speak or write to the assistant, your input is processed to answer your question and to send us a summary of the conversation; if the assistant matches you to an existing case, that is recorded in our project administration on our server in Germany. If you open the assistant on your customer page, we note there for five minutes that you are using it — that is the only way it can match you to your case, because no phone number is transmitted over the website. The legal basis is the performance of pre-contractual steps at your request (Art. 6(1)(b) GDPR) and our legitimate interest in being reachable (Art. 6(1)(f) GDPR). If you would rather not use it, write to kontakt@vecom-design.it.",
      p10h: "Telegram bot",
      p10: "With our bot on Telegram you can calculate a price estimate, send a request or ask for personal advice. Telegram is operated by Telegram Messenger Inc. (EU representative: European Data Protection Office, Avenue Huart Hamoir 71, 1030 Brussels, Belgium); according to Telegram, data of EU accounts is stored in data centres in the Netherlands. Telegram’s own privacy policy applies to the use of Telegram itself (telegram.org/privacy). From Telegram we receive the messages you send to the bot, your chat number and the language of your Telegram interface. Of these we only store the chat number, the language you chose, the state of the conversation and your answers to the questions — no username and no profile picture. If you send a request, we additionally store your name, email address and your request in our project management system on our server in Germany, just like a request made through the website; the bot tells you beforehand. If you have a website checked in the bot, our server loads that page once; we keep the address with the chat so it is included in a request you may send afterwards, and otherwise delete it after 30 days. The legal basis is taking steps at your request prior to entering into a contract (Art. 6(1)(b) GDPR). If you connect the bot to your client account from your personal area (one-time link), we store this connection: the bot then shows your project status, forwards your messages and files to your project and sends you a short notice with the subject line for every email we send you. Notices can be switched off in the bot, and the connection can be removed at any time in the bot or in your personal area; connected chats are kept until then. Unconnected chats without a sent request are deleted after 90 days of inactivity, unsent contact details after 30 days. You can delete the chat’s data yourself at any time with the /delete command. Our public channel t.me/vecomdesign is a news-only channel: as its operator we can see in Telegram the number of subscribers and the list with their public Telegram names; we do not store this list or use it for advertising. If you tap a button under a post, the bot opens; we only note that you came via the channel. If you start the bot or join the channel via a link from one of our campaigns (for example from a post, a flyer or a partner), we note that campaign: in the bot together with the chat, so we can see whether an advert leads to requests and orders; in the channel only as the number of joins per link — we do not store who joined. We also count each day how many people used the bot for the first time or again, as a plain number with no link to any person. The legal basis is our legitimate interest in measuring the success of our advertising (Art. 6(1)(f) GDPR). The legal basis for the channel is our legitimate interest in informing about our work (Art. 6(1)(f) GDPR); you can unsubscribe at any time in Telegram. The buttons “Calculate the price”, “New website” and “Improve my website” in the channel open our price calculator as a mini app, i.e. as a window inside Telegram; the page comes from vecom-design.it, and Telegram loads a script from telegram.org for it. Telegram passes start data to the page on your device (including your Telegram ID, first name and language); it is not sent to our server and we do not evaluate it. So that you can continue after closing, your device keeps a key for the calculator until you submit. What you enter there we treat like a request via the website.",
      p9h: "Partner programme",
      p9: "If you arrive through one of our partners’ links (vecom-design.it/p/…), we count the click and record this visit anonymously so that we can see what a recommendation achieves: a random visit code (e.g. “VIS-8F31A92C”), the time, the pages of our website you view, whether you use the price calculator, the questionnaire or the contact form, the domain of the site you came from, a campaign label in the link (utm), device type, browser, operating system and language, as well as the country and — for Italy and Germany — the region. We determine country and region on our own server from the IP address (DB-IP database, CC BY 4.0); we do not store the IP address itself, only a non-reversible check value that changes daily, to recognise repeated clicks. No city, no precise location, no device fingerprint, no disclosure to third parties. For this we set three small cookies for the duration of your visit (the partner’s code, the visit code and a marker that this is a partner visit); they are deleted when you close your browser. Only if you agree in the notice window does your browser remember the partner and the visit code for the stated number of days (currently 30), so that the recommendation still counts if you come back later (Art. 6(1)(a) GDPR); we store your choice in a cookie so that we do not ask again. You can withdraw your consent at any time with the link below. If you later enter your email address yourself or send an enquiry, we link the visit in which this happened to your enquiry; from then on we also see whether it led to an offer, an order and a payment. We delete the details of individual visits after 90 days; only daily counts per partner remain, with no link to any person. If you become a customer, we record which partner you came through so that we can pay their commission. The partner learns neither your name nor any other data about you, only that a purchase took place and for what amount. The legal basis for the anonymous recording of the visit and for the settlement is our legitimate interest in correct settlement with our partners and in evaluating our partner programme (Art. 6(1)(f) GDPR). Anyone applying as a partner sends us their name, email and the optional details in the form; they are used to assess the application and for settlement. Payouts are made via Stripe Payments Europe Ltd. (Ireland); the partner enters their bank details there themselves, and we do not see them.",
      p9k: "The same applies to our own campaign links (vecom-design.it/k/…), which we use in posts, ads, newsletters or on flyers: we count the click and record the visit anonymously exactly as described above — with the campaign and, where applicable, the individual piece (e.g. “Reel 3”) instead of a partner, so that we can see which advertising brings visitors and what comes of it. The same cookies apply for the duration of the visit (here with the campaign’s code instead of the partner’s), the same question in the notice window (remembered only with your consent, currently 30 days), the same withdrawal link below and the same deletion periods; afterwards only daily counts per campaign remain, with no link to any person. The legal basis is our legitimate interest in evaluating our advertising (Art. 6(1)(f) GDPR); for remembering beyond the visit, your consent (Art. 6(1)(a) GDPR).",
      p9w: "Withdraw consent and delete the remembered partner or campaign",
      p9ok: "Done: your browser no longer remembers any partner, campaign or visit code.",
      p7h: "Your rights",
      p7: "You have the right to access, rectification, erasure, restriction of processing, data portability and objection. Please write to kontakt@vecom-design.it. You also have the right to lodge a complaint with the competent supervisory authority — in Italy the Garante per la protezione dei dati personali.",

      t0: "General terms of service",
      t1h: "1. Scope",
      t1: "These terms apply to all services provided by Vecom Design (Uwe Vetter) to companies, self-employed people and consumers. Differing terms of the client apply only if confirmed in writing.",
      t2h: "2. Quote and conclusion of contract",
      t2: "Quotes are free and non-binding for the client. The contract comes into effect upon written acceptance of the quote, including by email. The approved quote defines the scope conclusively; additional work requested later is quoted separately before it is carried out.",
      t3h: "3. Prices and payment",
      t3: "The prices shown with the packages are entry prices. Unless agreed otherwise: 50% on commissioning, 50% on handover. Monthly care starts in the month after publication. The applicable tax treatment is stated on the invoice. Each instalment is due within 7 days: the deposit from the order confirmation, the balance from the handover notice.",
      t3bh: "3a. Late payment",
      t3b: "If the deadline passes without payment, the client is in default. Vecom Design sends a written reminder and sets a new deadline. Until payment in full, work may be suspended, the website is not published, and the rights of use remain with Vecom Design (clause 5). Statutory interest applies to outstanding amounts: towards businesses under D.Lgs. 231/2002 plus the fixed recovery cost of 40 euro, towards consumers the statutory interest under art. 1284 Codice civile. If the client withdraws from the commission, the deposit is not forfeited in full: the work actually done is invoiced and the remainder refunded.",
      t4h: "4. Client’s cooperation",
      t4: "Texts, images, logos and required access details are supplied by the client in good time and in usable form. Stated timeframes begin once the material is complete. Delays in supplying material extend the deadlines accordingly.",
      t5h: "5. Rights of use",
      t5: "On full payment the client receives unlimited rights of use, in time and territory, to the results created for them; code, project files and graphics are handed over. Third-party components (fonts, stock images, extensions) remain subject to their own licences, which are pointed out to the client. Vecom Design may name the project as a reference unless the client objects.",
      t6h: "6. Monthly care",
      t6: "Care covers the services listed in the chosen package. Term and notice period are agreed at commissioning and stated on the invoice. Either party may terminate the care with the agreed notice; the website remains the client’s property.",
      t7h: "7. Warranty",
      t7: "Vecom Design remedies defects attributable to its own work free of charge, provided they are reported within a reasonable period after handover. Not defects: changes in the behaviour of browsers, systems or third-party services after handover, and modifications made to the site by the client or third parties.",
      t8h: "8. Liability",
      t8: "Vecom Design is liable without limitation for intent and gross negligence and in cases where liability is mandatory by law. For slight negligence, liability is limited to foreseeable damage typical of the contract. Liability is excluded for content supplied by the client and for outages attributable to third-party providers.",
      t9h: "9. Consumers' right of withdrawal",
      t9: "If the client is a consumer, a 14-day right of withdrawal applies to contracts concluded at a distance. The right lapses where the service has been fully performed before the period expires at the client’s express request. The full withdrawal instructions, including the model form, are set out in the “Right of withdrawal” section on this page and are also provided before the contract is concluded.",
      t10h: "10. Applicable law and jurisdiction",
      t10: "Italian law applies. For consumers, jurisdiction at their place of residence remains unaffected. For companies, the place of jurisdiction is Agrigento.",
      t11h: "11. Severability",
      t11: "The invalidity of individual provisions does not affect the validity of the remaining ones.",

      w0: "Right of withdrawal",
      wlead: "As a consumer you may withdraw from the contract within fourteen days without giving a reason. If you ask for the work to start before that period ends, the right lapses at the moment the service has been fully performed; if you withdraw earlier, the part already carried out is charged. A clear message to kontakt@vecom-design.it is enough to withdraw.",
      w1h: "Fourteen-day period",
      w1: "The period is fourteen days from the conclusion of the contract. A clear statement is enough — an e-mail will do. You may use the form below, but you do not have to.",
      w2h: "Effects of withdrawal",
      w2: "Vecom Design refunds everything received within fourteen days of the statement, using the same means of payment the client used. If work has already begun at the client\\u2019s express request, the part carried out up to that point is charged, in proportion to the agreed total.",
      w3h: "Model withdrawal form",
      w3: "Complete and return this form only if you wish to withdraw from the contract.",
      wform: "To: Vecom Design, Uwe Vetter, kontakt@vecom-design.it\n\nI/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract for\nthe following service (*):\n\nOrdered on (*) / received on (*): ______________________\nName of consumer(s): ______________________\nAddress of consumer(s): ______________________\nDate: ______________________\nSignature of consumer(s) (only if this form is notified on paper): ______________________\n\n(*) Delete as appropriate.",

      back: "Back to the home page"
    }
  };

  /* Die kurze Fassung aus den Sprachdaten wird ersetzt, alles andere bleibt
     stehen. Reihenfolge der Skripte ist damit egal. */
  window.VECOM_I18N = window.VECOM_I18N || {};
  const b = window.VECOM_I18N;
  b.en = b.en || {};
  b.en.legal = L.legal;
})();
