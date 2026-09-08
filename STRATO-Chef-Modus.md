# Chef-Modus für Manuela — was in STRATO eingetragen wird

Das ändert **nichts** an dem, was Kunden hören. Es kommt nur ein Zweig dazu, der
sich erst öffnet, wenn du das Codewort sagst. Zwei Bausteine: ein Absatz im Feld
**„Verhalten im Telefonat"** und **vier neue API-Integrationen**.

Die Endpunkt-Seite läuft schon live: `https://vecom-design.it/chef.php`
(antwortet ohne Schlüssel mit „Nicht gefunden" — genau richtig).

---

## 1) So läuft der Chef-Modus (die Idee)

- Du sagst im Gespräch **„Chef-Modus"**. Das ist nur der Auslöser — kein Geheimnis.
- Manuela bittet **einmal** um dein **Codewort**. Das Codewort kennt sie **nicht** —
  sie reicht nur weiter, was du sagst. Ob es stimmt, entscheidet der Server.
- Stimmt es, ist sie auf deiner Seite: sagt, was heute dran ist, erklärt einen
  Kunden, legt auf dein „ja" einen Kunden an, nimmt eine Notiz auf.
- Stimmt es nicht, bleibt alles Normalbetrieb — sie erwähnt den Modus gar nicht.

Das Codewort setzt du selbst in der Verwaltung: **Einstellungen →
Telefonassistentin → Chef-Modus**. Es steht nirgends im Code und in keinem Chat.

---

## 2) Zusatz für „Verhalten im Telefonat" (ANHÄNGEN, nichts löschen)

> **Chef-Modus (nur für Uwe).** Sagt der Anrufer „Chef-Modus" (oder „ich bin Uwe,
> Chef-Modus"), bittest du **einmal** höflich um das Codewort. Was er daraufhin
> sagt, gibst du bei den chef-Werkzeugen im Feld `codewort` unverändert mit — du
> prüfst es nicht selbst, das macht das System, und du wiederholst es nie laut.
> Antwortet ein chef-Werkzeug mit `gesperrt` oder `aus`, sag freundlich, das habe
> nicht gepasst, und bleib im normalen Betrieb. Antwortet es normal, bist du bis
> zum Gesprächsende **Chef-Modus für Uwe** — du verkaufst dann nicht, du hilfst:
> - Fragt er, was ansteht / was heute dran ist: `chef_lage`, dann den Satz vorlesen.
> - Fragt er nach einem Kunden: `chef_kunde` mit Name oder E-Mail.
> - Will er einen Kunden anlegen: `chef_kunde_anlegen` mit Name und E-Mail. Kommt
>   `bestaetigung_noetig` zurück, lies den Vorschlag vor und frag nach. Erst wenn er
>   „ja" sagt, rufe es **erneut** auf, diesmal mit `bestaetigt` = `ja`.
> - Will er sich etwas notieren/merken: `chef_notiz` mit dem Text — genauso erst
>   nach „ja" mit `bestaetigt` = `ja`.
> - Gib das `codewort` bei **jedem** dieser Aufrufe mit. Erfinde nie Kundendaten;
>   was ein Werkzeug nicht liefert, sagst du ehrlich.

---

## 3) Vier neue API-Integrationen

Alle: **Methode POST**, **URL** `https://vecom-design.it/chef.php`,
**Header** genau wie bei deinen bestehenden Werkzeugen:
`X-Vecom-Telefon: <dein STRATO-Schlüssel>` (derselbe Schlüssel, der schon bei den
15 Kunden-Werkzeugen steht). **Body: JSON.**

### 3.1 `chef_lage` — Was ist heute dran?
- Beschreibung: „Tagesüberblick für den Chef: neue Anfragen, offene Fragebögen,
  fällige Raten, E-Mail-Probleme."
- Body:
  ```json
  { "aktion": "chef_lage", "codewort": "{{codewort}}" }
  ```

### 3.2 `chef_kunde` — Stand eines Kunden
- Beschreibung: „Zeigt dem Chef den Stand eines Kunden: Projekt, Vertrag, offene Zahlungen."
- Body (Name ODER E-Mail):
  ```json
  { "aktion": "chef_kunde", "codewort": "{{codewort}}", "name": "{{name}}" }
  ```

### 3.3 `chef_kunde_anlegen` — Kunde anlegen (nach „ja")
- Beschreibung: „Legt einen neuen Kunden an. Erst Vorschlag, dann auf Bestätigung."
- Body:
  ```json
  { "aktion": "chef_kunde_anlegen", "codewort": "{{codewort}}",
    "name": "{{name}}", "email": "{{email}}", "firma": "{{firma}}",
    "bestaetigt": "{{bestaetigt}}" }
  ```
  (`firma` und `bestaetigt` optional; `bestaetigt` erst „ja", wenn Uwe zustimmt.)

### 3.4 `chef_notiz` — Notiz für Uwe (nach „ja")
- Beschreibung: „Legt eine Notiz/Aufgabe in Uwes Meldungen ab. Erst Vorschlag, dann Bestätigung."
- Body:
  ```json
  { "aktion": "chef_notiz", "codewort": "{{codewort}}",
    "text": "{{text}}", "bestaetigt": "{{bestaetigt}}" }
  ```

---

## 4) Danach

1. In der Verwaltung das **Codewort** setzen (Einstellungen → Telefonassistentin).
2. Manuela anrufen, „Chef-Modus" sagen, Codewort nennen, „was ist heute dran?"
   fragen — sie sollte `chef_lage` aufrufen und dir den Satz vorlesen.
3. Ein falsches Wort testen — sie darf dann nichts vom Chef-Modus verraten.

Alles, was schreibt (Kunde anlegen, Notiz), fragt vorher und wartet auf dein „ja".
