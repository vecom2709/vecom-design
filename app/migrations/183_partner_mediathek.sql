-- ===========================================================================
-- 183 — Mediathek des Partners (Phase 3, 05.10.2026, Uwe: „Gleich mit Tabelle
-- und Verwaltung“).
--
-- Bis hierher lagen fertige Inhalte für Partner in 15 Blöcken des Reiters
-- „Werben“, und Uwe konnte nur einen Teil davon selbst ändern. Jetzt gibt es
-- EINE Mediathek: jede Karte hat einen Zweck (neue Kunden, Vertrauen, Angebot,
-- Anlass, Vorstellen, Referenzen), Branchen (die zwölf aus PartnerBranche) und
-- Kanäle. Uwe legt neue Karten in der Verwaltung an (Text, Bild, Verweis).
--
-- Branchen und Kanäle als Liste mit Kommas an beiden Enden („,gastronomie,beauty,“),
-- leer = für alle. So sucht ein LIKE '%,beauty,%' ohne Zwischentabelle.
--
-- Bilder liegen als WebP in der Zeile (wie das Partnerfoto): GD rechnet sie
-- neu, damit kein Standort aus dem EXIF mitreist; ausgeliefert über p.php?mt=,
-- nur aktive Karten.
--
-- Grundbestand: elf Verweise auf die vorhandenen Bereiche (3D-Galerie,
-- Bild-Baukasten, Kundenstimmen …), damit nichts hinter der neuen Ordnung
-- verschwindet. Wiederholbar über den eindeutigen Schlüssel.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_mediathek (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zweck       VARCHAR(16)   NOT NULL,                       -- PartnerMediathek::ZWECKE
  titel_it    VARCHAR(120)  NOT NULL DEFAULT '',
  titel_de    VARCHAR(120)  NOT NULL DEFAULT '',
  titel_en    VARCHAR(120)  NOT NULL DEFAULT '',
  text_it     TEXT          NULL,                           -- zum Teilen, {link} und {name} werden ersetzt
  text_de     TEXT          NULL,
  text_en     TEXT          NULL,
  branchen    VARCHAR(255)  NOT NULL DEFAULT '',            -- ,gastronomie,beauty, (leer = alle)
  kanaele     VARCHAR(255)  NOT NULL DEFAULT '',            -- ,whatsapp,instagram, (leer = alle)
  anker       VARCHAR(40)   NOT NULL DEFAULT '',            -- Verweis auf einen Bereich im Partnerbereich
  bild        MEDIUMBLOB    NULL,                           -- WebP, neu gerechnet
  bild_am     DATETIME      NULL,
  status      VARCHAR(8)    NOT NULL DEFAULT 'entwurf',     -- entwurf | aktiv | archiv
  sort        SMALLINT      NOT NULL DEFAULT 100,
  schluessel  VARCHAR(40)   NULL,                           -- nur Grundbestand (Wiederholbarkeit)
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pmt_schluessel (schluessel),
  KEY ix_pmt_status (status, zweck, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO partner_mediathek (schluessel, zweck, kanaele, anker, status, sort, titel_it, titel_de, titel_en, text_it, text_de, text_en) VALUES
('anker:werbung', 'neukunden', '', 'werbung', 'aktiv', 10,
 'Tutti i modelli per canale', 'Alle Vorlagen je Kanal', 'All templates by channel',
 'WhatsApp, Telegram, Instagram, Facebook, TikTok, e-mail, LinkedIn, SMS — con la Sua firma.',
 'WhatsApp, Telegram, Instagram, Facebook, TikTok, E-Mail, LinkedIn, SMS — mit deiner Signatur.',
 'WhatsApp, Telegram, Instagram, Facebook, TikTok, e-mail, LinkedIn, SMS — with your signature.'),
('anker:branchen', 'neukunden', ',whatsapp,instagram,persoenlich,', 'branchen', 'aktiv', 20,
 'Argomenti per settore', 'Argumente je Branche', 'Arguments by industry',
 'Perché serve un sito, tre argomenti e una domanda per il colloquio.',
 'Warum eine Website, drei Argumente und eine Frage fürs Gespräch.',
 'Why a website, three arguments and a question for the conversation.'),
('anker:medien', 'neukunden', ',instagram,facebook,whatsapp,druck,', 'medien', 'aktiv', 30,
 'Creare un’immagine o un video', 'Bild oder Video selbst gestalten', 'Make your own image or video',
 '6 motivi, 7 formati, il Suo link incluso.', '6 Motive, 7 Formate, dein Link ist dabei.', '6 designs, 7 formats, your link included.'),
('anker:galerie3d', 'neukunden', ',instagram,facebook,tiktok,', 'galerie3d', 'aktiv', 40,
 'Immagini e video 3D con QR', '3D-Bilder und -Videos mit QR-Code', '3D images and videos with QR code',
 'In tre formati: post, storia, stampa.', 'In drei Formaten: Beitrag, Story, Druck.', 'In three formats: post, story, print.'),
('anker:beitraege', 'vorstellung', ',instagram,facebook,whatsapp,', 'beitraege', 'aktiv', 50,
 'Post di Vecom pronti con il Suo link', 'Fertige Vecom-Beiträge mit deinem Link', 'Ready Vecom posts with your link',
 'Immagine e testo, il link è già dentro.', 'Bild und Text, dein Link steckt schon drin.', 'Image and text, your link is already in.'),
('anker:gutschein', 'angebot', ',whatsapp,instagram,druck,', 'gutschein', 'aktiv', 60,
 'Buono regalo', 'Gutschein', 'Voucher',
 'Un buono da regalare, con testo di accompagnamento.', 'Ein Gutschein zum Verschenken, mit Begleittext.', 'A voucher to give away, with a short text.'),
('anker:stimmen-teilen', 'vertrauen', ',instagram,facebook,whatsapp,', 'stimmen-teilen', 'aktiv', 70,
 'Condividere recensioni', 'Kundenstimmen teilen', 'Share reviews',
 'Come testo o come immagine.', 'Als Text oder als Bild.', 'As text or as an image.'),
('anker:stimme-sammeln', 'vertrauen', ',whatsapp,email,', 'stimme-sammeln', 'aktiv', 80,
 'Raccogliere recensioni', 'Kundenstimmen sammeln', 'Collect reviews',
 'Un link con cui i clienti soddisfatti lasciano il loro parere.', 'Ein Link, mit dem zufriedene Kunden ihre Meinung schreiben.', 'A link for happy customers to leave their opinion.'),
('anker:antworten', 'vertrauen', ',instagram,facebook,tiktok,', 'antworten', 'aktiv', 90,
 'Rispondere ai commenti', 'Auf Kommentare antworten', 'Reply to comments',
 'Risposte pronte a like, commenti e domande.', 'Fertige Antworten auf Likes, Kommentare und Fragen.', 'Ready replies to likes, comments and questions.'),
('anker:arbeiten-teilen', 'referenzen', ',instagram,facebook,whatsapp,', 'arbeiten-teilen', 'aktiv', 100,
 'Mostrare lavori di Vecom', 'Arbeiten von Vecom zeigen', 'Show Vecom’s work',
 'Tre siti d’esempio come post.', 'Drei Beispielseiten als Beitrag.', 'Three sample sites as a post.'),
('anker:erfolge', 'referenzen', ',instagram,facebook,linkedin,', 'erfolge', 'aktiv', 110,
 'Progetti online', 'Projekte, die online sind', 'Projects that are live',
 'Post sui progetti online — solo con il consenso del cliente.', 'Beiträge zu Projekten, die online sind — nur mit Zustimmung des Kunden.', 'Posts about live projects — only with the customer’s consent.');
