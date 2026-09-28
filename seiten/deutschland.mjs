/* ==========================================================================
   Branchen-Seiten für Deutschland (28.09.2026, Uwe: Ja zu D4).

   Nur auf Deutsch, für Betriebe in Deutschland (die Landeseiten daneben
   sprechen deutschsprachige Betriebe in Sizilien an). build.mjs setzt jede
   Seite in das Gerüst der deutschen Preisseite und trägt sie in die Sitemap
   ein. Erster Knopf: die kostenlose Analyse (analisi.php?lang=de) -- wer eine
   Website hat, sieht sofort die Ampel und kann darunter selbst Ja sagen.

   REGELN (VECOM-STANDARD: „niemals erfunden“)
   - Keine Kunden, Städte, Bewertungen oder Zahlen, die nicht belegt sind.
   - Keine Preise im Text: Sie stehen live auf der Preisseite.
   - Keine Versprechen über Google-Plätze oder Umsatz.
   - Stimme der Website: erste Person, „Sie“.
   ========================================================================== */

export const DEUTSCHLAND_STAND = '2026-09-28';

export const DEUTSCHLAND_SEITEN = [
  {
    schluessel: 'friseur', ziel: 'de/website-friseur.html', kurz: 'Friseure & Kosmetik',
    titel: 'Website für Friseure und Kosmetikstudios | Vecom Design',
    desc: 'Eine Website für Ihren Salon: Leistungen, Öffnungszeiten, Anfahrt und Termin-Anfrage auf dem Handy. Fester Preis vor dem Start, ein Ansprechpartner, alles online.',
    kicker: 'Friseure & Kosmetikstudios', h1: 'Eine Website, die Ihren Salon zeigt — und Termine bringt',
    lead: 'Wer einen neuen Friseur sucht, schaut zuerst auf dem Handy: Wie sieht es dort aus, was kostet ein Schnitt, wann ist offen, wie bekomme ich einen Termin? Eine eigene Seite beantwortet das in Sekunden — mit echten Fotos aus Ihrem Salon statt Bildern aus dem Katalog.',
    blocchi: [
      ['Was drauf gehört', null, ['Ihre Leistungen mit Preisrahmen, so wie Sie sie nennen möchten', 'Öffnungszeiten, Adresse, Anfahrt und ein Knopf zum Anrufen', 'Eine Termin-Anfrage per Formular oder WhatsApp — oder der Link zu Ihrem Buchungsprogramm', 'Fotos von Ihnen, Ihrem Team und Ihren Arbeiten']],
      ['Warum nicht nur Instagram', 'Instagram zeigt, was Sie können. Die Fragen, die vor dem ersten Besuch kommen — wo, wann, was kostet es —, beantwortet eine Website schneller, und Google findet sie, wenn jemand „Friseur“ und Ihren Ort sucht. Beides zusammen funktioniert am besten.'],
      ['So läuft es', null, ['Sie tragen Ihre E-Mail ein und bekommen den Link zu Ihrem persönlichen Bereich — kein Konto, kein Passwort.', 'Acht kurze Fragen, dann sehen Sie sofort Ihre Preisspanne.', 'Sie bekommen ein Angebot zum festen Preis. Erst nach Ihrem Ja geht es los.', 'Fotos, Logo und Texte laden Sie in Ihrem Bereich hoch. Online geht die Seite erst, wenn Sie sagen: So passt es.']],
    ],
    faq: [
      ['Ich habe schon eine Website. Lohnt sich eine neue?', 'Das sehen Sie selbst: Die kostenlose Analyse zeigt in wenigen Sekunden zwölf Punkte als Ampel — Ladezeit, Sicherheit, Handy, Google, Aktualität und Vorschau beim Teilen. Oft reicht es, einzelne Punkte zu verbessern.'],
      ['Kann ich mein Buchungsprogramm behalten?', 'Ja. Die Seite verlinkt darauf oder bindet es ein, wenn das Programm das anbietet. Sie müssen nichts umstellen.'],
      ['Sie sitzen in Sizilien — geht das?', 'Ja. Ich bin Deutscher, und alles läuft über Ihren persönlichen Bereich, per Telefon, Video oder WhatsApp. Einen Termin vor Ort braucht es nicht.'],
    ],
  },
  {
    schluessel: 'handwerk', ziel: 'de/website-handwerker.html', kurz: 'Handwerk',
    titel: 'Website für Handwerksbetriebe | Vecom Design',
    desc: 'Eine Website für Ihren Handwerksbetrieb: Leistungen, Einsatzgebiet, Referenzfotos und Anfrage mit Foto vom Handy. Fester Preis vor dem Start, alles online.',
    kicker: 'Handwerk', h1: 'Eine Website für Ihren Betrieb — damit die passenden Aufträge anfragen',
    lead: 'Viele Handwerker haben mehr Anfragen, als sie annehmen können — aber nicht immer die richtigen. Eine gute Seite zeigt klar, was Sie machen, wo Sie arbeiten und wie ein Auftrag abläuft. Dann fragen die an, die zu Ihnen passen.',
    blocchi: [
      ['Was drauf gehört', null, ['Ihre Leistungen, klar getrennt — so wie Kunden danach suchen', 'Ihr Einsatzgebiet', 'Fotos von fertigen Arbeiten, mit Ihrer Erlaubnis und der Ihrer Kunden', 'Eine Anfrage, bei der der Kunde gleich ein Foto vom Handy mitschickt', 'Kontakt, Anfahrt, Öffnungszeiten, Impressum und Datenschutz']],
      ['Weniger Telefon, bessere Anfragen', 'Eine Anfrage mit Foto, Adresse und kurzer Beschreibung erspart den ersten Rückruf. Sie sehen gleich, ob sich der Weg lohnt — und der Kunde fühlt sich ernst genommen.'],
      ['So läuft es', null, ['Sie tragen Ihre E-Mail ein und bekommen den Link zu Ihrem persönlichen Bereich — kein Konto, kein Passwort.', 'Acht kurze Fragen, dann sehen Sie sofort Ihre Preisspanne.', 'Sie bekommen ein Angebot zum festen Preis. Erst nach Ihrem Ja geht es los.', 'Fotos und Texte laden Sie in Ihrem Bereich hoch, wann es Ihnen passt. Online geht die Seite erst mit Ihrem Okay.']],
    ],
    faq: [
      ['Ich habe keine Zeit für Texte.', 'Müssen Sie auch nicht. Ich schreibe die Texte aus Ihren Stichworten und aus einem kurzen Gespräch; Sie lesen nur noch gegen.'],
      ['Ich habe schon eine Seite, die aber alt ist.', 'Die kostenlose Analyse zeigt in wenigen Sekunden, wo sie steht. Danach entscheiden Sie, ob eine Überarbeitung reicht.'],
      ['Wer pflegt die Seite danach?', 'Wenn Sie möchten, ich — mit der monatlichen Betreuung als eigenem, freiwilligem Vertrag. Ohne sie läuft die Seite trotzdem und gehört Ihnen.'],
    ],
  },
  {
    schluessel: 'gastro', ziel: 'de/website-restaurant-cafe.html', kurz: 'Restaurants & Cafés',
    titel: 'Website für Restaurants, Cafés und Bars | Vecom Design',
    desc: 'Eine Website für Ihr Restaurant oder Café: Speisekarte, die Sie selbst ändern, Öffnungszeiten, Reservierung und Anfahrt. Fester Preis vor dem Start, alles online.',
    kicker: 'Restaurants, Cafés & Bars', h1: 'Eine Website, die Appetit macht — und Tische füllt',
    lead: 'Bevor jemand reserviert, will er drei Dinge wissen: Was gibt es, hat es heute offen, wie komme ich hin? Eine eigene Seite zeigt das auf dem Handy auf einen Blick — mit Ihrer echten Karte und Fotos aus Ihrer Küche.',
    blocchi: [
      ['Was drauf gehört', null, ['Die Speisekarte als Text statt als PDF — lesbar auf dem Handy und für Google', 'Öffnungszeiten, Ruhetag, Adresse und Anfahrt', 'Reservierung per Formular, Telefon oder WhatsApp', 'Mittagstisch, Aktionen und Veranstaltungen, die Sie selbst eintragen']],
      ['Die Karte ändern Sie selbst', 'Neue Preise, ein Wochengericht, der Sommer auf der Terrasse: Das tragen Sie in Ihrem Bereich ein, ohne Programmierer und ohne Wartezeit.'],
      ['So läuft es', null, ['Sie tragen Ihre E-Mail ein und bekommen den Link zu Ihrem persönlichen Bereich — kein Konto, kein Passwort.', 'Acht kurze Fragen, dann sehen Sie sofort Ihre Preisspanne.', 'Sie bekommen ein Angebot zum festen Preis. Erst nach Ihrem Ja geht es los.', 'Karte, Fotos und Logo laden Sie hoch. Online geht die Seite erst, wenn Sie sagen: So passt es.']],
    ],
    faq: [
      ['Brauche ich ein Reservierungssystem?', 'Nicht unbedingt. Für viele reicht eine Anfrage mit Datum, Uhrzeit und Personenzahl, die Sie selbst bestätigen. Haben Sie schon ein System, wird es verlinkt.'],
      ['Mehrere Sprachen?', 'Wenn viele Gäste aus dem Ausland kommen, ja — mit echten Texten, nicht maschinell übersetzt.'],
      ['Was kostet das?', 'Das hängt von Seiten, Sprachen und Funktionen ab. Die Zahlen stehen offen auf der Preisseite; im Rechner sehen Sie Ihre Spanne in anderthalb Minuten.'],
    ],
  },
  {
    schluessel: 'pension', ziel: 'de/website-pension-ferienwohnung.html', kurz: 'Pensionen & Ferienwohnungen',
    titel: 'Website für Pensionen und Ferienwohnungen | Vecom Design',
    desc: 'Eine eigene Website für Ihre Pension oder Ferienwohnung: Zimmer, Fotos, Lage und Buchungsanfrage — mehr direkte Gäste neben den Portalen. Fester Preis, alles online.',
    kicker: 'Pensionen & Ferienwohnungen', h1: 'Direkte Gäste neben den Portalen — mit Ihrer eigenen Seite',
    lead: 'Portale bringen Gäste, nehmen dafür aber eine Provision. Eine eigene Website ist der Ort, an dem Stammgäste und Weiterempfohlene direkt bei Ihnen anfragen — mit Ihren Fotos, Ihren Worten und Ihren Bedingungen.',
    blocchi: [
      ['Was drauf gehört', null, ['Zimmer oder Wohnungen mit Fotos, Ausstattung und Preisrahmen', 'Lage, Anfahrt und was es in der Umgebung gibt', 'Eine Buchungsanfrage mit Anreise, Abreise und Personenzahl', 'Hausregeln, Stornobedingungen, Impressum und Datenschutz']],
      ['Der einfache Weg', 'Für die meisten Häuser reicht eine Anfrage mit Daten, die Sie selbst beantworten: kein Kalender, der mit den Portalen abgeglichen werden muss, keine Doppelbuchung.'],
      ['So läuft es', null, ['Sie tragen Ihre E-Mail ein und bekommen den Link zu Ihrem persönlichen Bereich — kein Konto, kein Passwort.', 'Acht kurze Fragen, dann sehen Sie sofort Ihre Preisspanne.', 'Sie bekommen ein Angebot zum festen Preis. Erst nach Ihrem Ja geht es los.', 'Fotos und Texte laden Sie in Ihrem Bereich hoch. Online geht die Seite erst mit Ihrem Okay.']],
    ],
    faq: [
      ['Darf ich neben den Portalen direkt vermieten?', 'In der Regel ja; manche Verträge verlangen, dass Sie online nicht günstiger sind als auf dem Portal. Die Bedingungen ändern sich, deshalb lohnt ein Blick in Ihren Vertrag.'],
      ['Mehrere Sprachen?', 'Wenn Ihre Gäste aus dem Ausland kommen, ja — mit echten Texten, nicht maschinell übersetzt.'],
      ['Ich habe schon eine Seite.', 'Die kostenlose Analyse zeigt in wenigen Sekunden, wo sie steht — danach entscheiden Sie.'],
    ],
  },
  {
    schluessel: 'kfz', ziel: 'de/website-kfz-werkstatt.html', kurz: 'Kfz-Werkstätten',
    titel: 'Website für Kfz-Werkstätten und Autohäuser | Vecom Design',
    desc: 'Eine Website für Ihre Werkstatt: Leistungen, Öffnungszeiten, Terminanfrage mit Kennzeichen und Anfahrt. Fester Preis vor dem Start, alles online.',
    kicker: 'Kfz-Werkstätten & Autohäuser', h1: 'Eine Website für Ihre Werkstatt — mit Terminanfrage in einer Minute',
    lead: 'Wer eine Werkstatt sucht, hat meist ein konkretes Problem: Inspektion, Reifen, TÜV, ein Geräusch. Eine klare Seite zeigt, was Sie machen, wann Sie offen haben und wie man schnell einen Termin anfragt.',
    blocchi: [
      ['Was drauf gehört', null, ['Ihre Leistungen — Inspektion, Reifen, HU/AU-Vorbereitung, Klima, Karosserie, was Sie anbieten', 'Eine Terminanfrage mit Fahrzeug, Kennzeichen und Wunschtermin', 'Öffnungszeiten, Adresse, Anfahrt und ein Knopf zum Anrufen', 'Beim Autohaus: Fahrzeuge, die Sie selbst eintragen']],
      ['Anfragen mit allen Angaben', 'Kommt eine Anfrage schon mit Fahrzeug und Anliegen, sparen Sie sich das erste Telefonat und können direkt einen Termin vorschlagen.'],
      ['So läuft es', null, ['Sie tragen Ihre E-Mail ein und bekommen den Link zu Ihrem persönlichen Bereich — kein Konto, kein Passwort.', 'Acht kurze Fragen, dann sehen Sie sofort Ihre Preisspanne.', 'Sie bekommen ein Angebot zum festen Preis. Erst nach Ihrem Ja geht es los.', 'Fotos, Logo und Texte laden Sie in Ihrem Bereich hoch. Online geht die Seite erst mit Ihrem Okay.']],
    ],
    faq: [
      ['Ich bin Partner einer Werkstattkette.', 'Dann klären wir vorab, was die Kette vorgibt — Logo, Farben, Pflichtangaben — und bauen die Seite so, dass sie dazu passt.'],
      ['Ich habe schon eine Seite.', 'Die kostenlose Analyse zeigt in wenigen Sekunden zwölf Punkte als Ampel. Oft reicht es, einzelne davon zu verbessern.'],
      ['Wer pflegt die Seite danach?', 'Wenn Sie möchten, ich — mit der monatlichen Betreuung als eigenem, freiwilligem Vertrag. Ohne sie läuft die Seite trotzdem und gehört Ihnen.'],
    ],
  },
];

/* Wörter rund um die Seiten. */
export const DEUTSCHLAND_WORTE = {
  analyse_titel: 'Wie steht Ihre Website heute da?',
  analyse_text: 'Adresse eingeben: In wenigen Sekunden sehen Sie zwölf Punkte als Ampel. Kostenlos, ohne Anmeldung. Darunter können Sie die ausführliche Analyse anfordern.',
  analyse_knopf: 'Kostenlose Analyse',
  cta_titel: 'Noch keine Website — oder eine neue?',
  cta_text: 'E-Mail eintragen: Sie bekommen den Link zu Ihrem persönlichen Bereich und sehen in anderthalb Minuten Ihre Preisspanne. Kein Konto, keine Verpflichtung.',
  cta_knopf: 'Loslegen', preise: 'Alle Preise ansehen', faq: 'Häufige Fragen', auch: 'Websites auch für',
};
