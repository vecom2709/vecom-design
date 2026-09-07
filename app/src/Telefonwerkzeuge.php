<?php
declare(strict_types=1);

require_once __DIR__ . '/Telefon.php';
require_once __DIR__ . '/Baukasten.php';

/**
 * WAS MANUELA KANN — AN EINER STELLE, DIE AUCH DER SERVER LESEN KANN
 * ===========================================================================
 *
 * Diese vierzehn Beschreibungen standen bisher mitten in einer Ansicht. Das
 * ging, solange sie nur angezeigt wurden: kopieren, bei STRATO einfügen,
 * fertig. Es geht nicht mehr, seit die Verwaltung sie selbst hinüberschicken
 * soll — eine Ansicht lässt sich nicht aufrufen, ohne eine Seite zu bauen.
 *
 * WARUM DAS MEHR IST ALS EIN UMZUG
 *
 * Jede Änderung an einer Beschreibung musste bisher von Hand nach drüben:
 * vierzehn Blöcke kopieren, vierzehnmal einfügen. Wer das dreimal gemacht
 * hat, macht es beim vierten Mal nicht mehr — und dann steht bei STRATO eine
 * Fassung, die niemand mehr kennt, während hier eine andere gepflegt wird.
 * Genau das ist im September dreimal passiert.
 *
 * Der Rumpf wird als Zeichenkette gebaut und nicht durch json_encode gejagt:
 * Stratos Platzhalter {{ name }} müssen wörtlich stehen bleiben.
 */
final class Telefonwerkzeuge
{
    /**
     * Die Reihenfolge, in der sie bei STRATO stehen sollen.
     *
     * Nicht alphabetisch: Sie folgt dem Gespräch. Zuerst wissen, wer anruft,
     * dann was es gibt, dann beraten, dann ansehen, dann belegen, dann der
     * Preis — und ganz hinten das, was nur im Notfall gebraucht wird.
     */
    public const REIHE = ['kunde_nachschlagen', 'wissen', 'beratung', 'seite_ansehen', 'beleg',
                          'preis_auskunft', 'lage', 'angebot_link', 'termin', 'uebergabe',
                          'melde', 'zusammenfassung', 'wissensluecke', 'hilfe'];

    /**
     * Alle Beschreibungen, wie sie diese Sekunde gelten.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function alle(): array
    {
        /* Die Konfigurator-Antworten als Aufzaehlung. Sie muessen in Stratos
           Schema als enum stehen, damit das Modell aus einer Liste waehlt
           statt zu formulieren -- Freitext verwirft der Konfigurator ohnehin. */
        $fragen = [];
        foreach (Telefon::VORWEG as $f) {
            $frage = Baukasten::FRAGEN[$f] ?? null;
            if ($frage === null) { continue; }
            $fragen[$f] = ['art' => $frage['art'] ?? 'einfach',
                           'werte' => array_map('strval', array_keys($frage['optionen'] ?? []))];
        }

        $konfigs = [];

        $konfigs['kunde_nachschlagen'] = [
          'zweck' => 'Wer ruft an? Schlägt über Rufnummer, Kundennummer oder Namen nach. '
                   . 'Gibt Name, Betrieb, Sprache und Projektstand zurück — nie Beträge. '
                   . 'IMMER zuerst aufrufen, bevor du „hilfe“, „angebot_link“, „uebergabe“, „melde“ oder '
                   . '„zusammenfassung“ benutzt. Die zurückgegebene kunde_id gibst du danach bei jedem '
                   . 'weiteren Werkzeug im selben Gespräch mit — ohne sie bekommt niemand einen Stand '
                   . 'und keinen Link. Das ist keine Formsache: Zweimal hast du jemanden erkannt und '
                   . 'ihn danach als Unbekannten behandelt, weil die kunde_id im nächsten Aufruf fehlte. '
                   . 'Kommt kein Treffer, fragst du nach Rufnummer und Erreichbarkeit '
                   . 'und rufst „melde“ auf. '
                   . 'Kommt „schon_einmal“ zurück, sag den Satz aus „satz“ früh im Gespräch — '
                   . 'einmal, nicht mehrmals. Widerspricht er, glaub ihm und frag neu. '
                   . 'Kommt „website“ zurück, ist seine Internetadresse hinterlegt: Frag ihn dann NIE '
                   . 'danach, sondern ruf „seite_ansehen“ mit der kunde_id auf. '
                   . 'Kommt „website_achtung“, sag das früh — es ist meist der Grund seines Anrufs. '
                   . 'Kommt „kurz_halten“, sagst du GENAU den Satz aus „satz“ und sonst nichts: keine '
                   . 'Beratung, keine Preise, kein Link, kein Termin, keine Website-Prüfung. Fragt er '
                   . 'weiter, sag denselben Satz noch einmal und verabschiede dich. Bleib dabei '
                   . 'höflich — auch wenn er drängt. Werde nie unfreundlich, urteile nicht über ihn '
                   . 'und behaupte nichts über seine Lage.',
          'eig' => [
            'telefon'      => ['type' => 'string', 'description' => 'Rufnummer des Anrufers, wie sie hereinkommt'],
            'kundennummer' => ['type' => 'string', 'description' => 'Kunden-, Bestell- oder Angebotsnummer, falls genannt'],
            'name'         => ['type' => 'string', 'description' => 'Vor- und Nachname oder Betrieb, falls genannt'],
          ],
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"kunde_nachschlagen","telefon":"{{ telefon }}","kundennummer":"{{ kundennummer }}","name":"{{ name }}"}',
        ];

        $eigA = [
          'sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en'], 'description' => 'Sprache des Gesprächs'],
          'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher nachgeschlagen und gefunden'],
          'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Nur bei Neukunden, buchstabieren lassen'],
          'name' => ['type' => 'string', 'description' => 'Name für die Anrede'],
        ];
        foreach ($fragen as $f => $inf) {
            $eigA[$f] = $inf['art'] === 'mehrfach'
                ? ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $inf['werte']],
                   'description' => 'Mehrfach möglich. Nur eintragen, was der Anrufer wirklich gesagt hat.']
                : ['type' => 'string', 'enum' => $inf['werte'],
                   'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.'];
        }
        $rumpfA = '{"aktion":"angebot_link","sprache":"{{ sprache }}","kunde_id":"{{ kunde_id }}",'
                . '"email":"{{ email }}","name":"{{ name }}"';
        foreach (array_keys($fragen) as $f) { $rumpfA .= ',"' . $f . '":"{{ ' . $f . ' }}"'; }
        $rumpfA .= '}';

        $konfigs['angebot_link'] = [
          'zweck' => 'Schickt den Konfigurator-Link. Was am Telefon schon gesagt wurde, steht beim '
                   . 'Öffnen drin. Bei Bestandskunden geht der Link nur an die hinterlegte Adresse. '
                   . 'Sag erst „ist raus“, NACHDEM dieses Werkzeug „ok“ zurückgegeben hat — eine Zusage, '
                   . 'die du nicht eingelöst hast, ist schlimmer als gar keine.',
          'eig' => $eigA, 'pflicht' => ['sprache'], 'rumpf' => $rumpfA,
        ];

        $konfigs['preis_auskunft'] = [
          'zweck' => 'Was kostet das? Rechnet mit derselben Maschine wie der Konfigurator und das '
                   . 'Angebot — die Zahlen sind immer die aktuellen. Antwort ist eine Spanne, nie ein '
                   . 'Festpreis. Wenn der Anrufer schon etwas gesagt hat, seine Spanne; sonst die übliche. '
                   . 'Kommt „ausserhalb“ zurück, nennst du KEINE Zahl: Das Vorhaben passt nicht in den '
                   . 'Baukasten. Dann liest du den mitgegebenen Satz vor und bietest einen Termin an.',
          'eig' => (static function () use ($fragen) {
              $e = [];
              foreach ($fragen as $f => $inf) {
                  $e[$f] = $inf['art'] === 'mehrfach'
                      ? ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $inf['werte']],
                         'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.']
                      : ['type' => 'string', 'enum' => $inf['werte'],
                         'description' => 'Nur eintragen, was der Anrufer wirklich gesagt hat.'];
              }
              $e['vorhaben'] = ['type' => 'string', 'maxLength' => 300,
                  'description' => 'Was er sich wünscht, in seinen eigenen Worten — daran wird erkannt, '
                                 . 'ob es überhaupt in den Baukasten passt'];
              return $e;
          })(),
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"preis_auskunft","vorhaben":"{{ vorhaben }}"' . (static function () use ($fragen) {
              $r = ''; foreach (array_keys($fragen) as $f) { $r .= ',"' . $f . '":"{{ ' . $f . ' }}"'; }
              return $r;
          })() . '}',
        ];

        $konfigs['lage'] = [
          'zweck' => 'Wie spät ist es, welcher Tag, und ist ein Rückruf heute realistisch? '
                   . 'Immer aufrufen, bevor du einen Zeitpunkt zusagst — die Uhrzeit nie selbst schätzen. '
                   . '„ton“ sagt dir, wie du zu dieser Tageszeit klingen solltest: morgens frisch und knapp, '
                   . 'abends ruhiger und ohne lange Fragebögen, nachts nur noch aufnehmen. '
                   . 'Am Wochenende tust du nicht so, als säße jemand im Büro.',
          'eig' => [], 'pflicht' => [], 'rumpf' => '{"aktion":"lage"}',
        ];

        $konfigs['melde'] = [
          'zweck' => 'Trägt ein Anliegen in die Verwaltung ein: Rückruf, Nachricht, Beschwerde '
                   . 'oder „Link noch einmal schicken“. Beschwerden gelten immer als dringend. '
                   . 'Das ist der Rettungsanker: Endet ein Gespräch, ohne dass etwas rausgegangen ist, '
                   . 'rufst du wenigstens das hier auf — mit Rufnummer und Erreichbarkeit. '
                   . 'Ein Anrufer, von dem nichts in der Verwaltung steht, ist verloren.',
          'eig' => [
            'art' => ['type' => 'string', 'enum' => array_keys(Telefon::ARTEN),
                      'description' => 'Um welche Art Anliegen es geht'],
            'prioritaet' => ['type' => 'string', 'enum' => ['normal', 'dringend'],
                             'default' => 'normal', 'description' => 'Dringend nur, wenn es wirklich eilt'],
            'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
            'name' => ['type' => 'string', 'description' => 'Name des Anrufers'],
            'telefon' => ['type' => 'string', 'description' => 'Rückrufnummer'],
            'erreichbar' => ['type' => 'string', 'maxLength' => 160,
                             'description' => 'Wann er am besten erreichbar ist, in seinen Worten '
                                            . '(„ab 14 Uhr“, „nur vormittags“, „nicht Dienstag“)'],
            'text' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 4000,
                       'description' => 'Das Anliegen in eigenen Worten des Anrufers'],
          ],
          'pflicht' => ['art', 'text'],
          'rumpf' => '{"aktion":"melde","art":"{{ art }}","prioritaet":"{{ prioritaet }}",'
                   . '"kunde_id":"{{ kunde_id }}","name":"{{ name }}","telefon":"{{ telefon }}",'
                   . '"erreichbar":"{{ erreichbar }}","text":"{{ text }}"}',
        ];

        $konfigs['wissensluecke'] = [
          'zweck' => 'Wenn du eine Frage nicht sicher beantworten kannst: hier melden, statt zu raten. '
                   . 'Danach sagen „das schaue ich nach und melde mich“ — und nichts erfinden.',
          'eig' => [
            'frage' => ['type' => 'string', 'minLength' => 5, 'maxLength' => 500,
                        'description' => 'Die Frage des Anrufers, möglichst wörtlich'],
            'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
          ],
          'pflicht' => ['frage'],
          'rumpf' => '{"aktion":"wissensluecke","frage":"{{ frage }}","kunde_id":"{{ kunde_id }}"}',
        ];

        $konfigs['hilfe'] = [
          'zweck' => 'Wenn jemand nicht weiterkommt: Fragebogen, Bezahlung, Link weg, Entwurf, Zugang. '
                   . 'Sieht nach, wo DIESER Kunde steht, und gibt die Schritte in seiner Sprache zurück. '
                   . 'Die Sätze aus „schritte“ vorlesen, einen nach dem anderen — nicht zusammenfassen, '
                   . 'nichts dazuerfinden. Keine Beträge und nicht sagen, ob etwas offen ist: '
                   . 'Das steht auf seiner Seite, und der Link dorthin geht nur an die hinterlegte Adresse. '
                   . 'Klappt es beim zweiten Mal nicht, „versuch“ auf 2 setzen — dann übernimmt ein Mensch. '
                   . 'Kommt „bekannt“: false zurück, gibst du keinen Stand und keinen Link heraus, sondern '
                   . 'fragst nach Rufnummer und Erreichbarkeit und rufst „melde“ auf. '
                   . 'WÄHLE EINES DIESER FÜNF: „fragebogen“, wenn er beim Ausfüllen hängt; „bezahlung“ '
                   . 'bei allem um Rechnung, Überweisung, Karte; „link_weg“, wenn eine Mail oder ein '
                   . 'Link nicht ankam; „vorschau“, wenn er den Entwurf nicht sieht; „zugang“ bei '
                   . 'Anmelden, Passwort, Einloggen. „sonstiges“ NUR, wenn wirklich keines passt — '
                   . 'vier von fünf Meldungen unter „sonstiges“ machen die Liste „Woran es hakt“ '
                   . 'wertlos, und dann kann niemand mehr beheben, woran es wirklich hakt. '
                   . 'Schreib IMMER in „text“, was er in seinen eigenen Worten gesagt hat: Daran wird '
                   . 'nachgeprüft, ob die Kategorie stimmt.',
          'eig' => [
            'problem' => ['type' => 'string',
                          'enum' => ['fragebogen', 'bezahlung', 'link_weg', 'vorschau', 'zugang', 'sonstiges'],
                          'description' => 'Woran es hakt. Nimm eines der fünf, wenn es passt — '
                                         . '„sonstiges“ nur, wenn wirklich keines passt. Eine falsche '
                                         . 'Kategorie führt zu einer Anleitung für ein Problem, das er '
                                         . 'nicht hat; „sonstiges“ für alles führt zu einer Liste, aus '
                                         . 'der niemand mehr etwas lernt'],
            'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
            'telefon'  => ['type' => 'string', 'description' => 'Rufnummer, falls noch nicht nachgeschlagen'],
            'versuch'  => ['type' => 'integer',
                           'description' => '0 beim ersten Anlauf, 1 beim zweiten, 2 wenn es wieder nicht ging'],
            'text'     => ['type' => 'string', 'maxLength' => 500,
                           'description' => 'Was genau nicht geht, IN SEINEN WORTEN — immer mitgeben. '
                                          . 'Daran wird geprüft, ob die Kategorie stimmt'],
          ],
          'pflicht' => ['problem'],
          'rumpf' => '{"aktion":"hilfe","problem":"{{ problem }}","kunde_id":"{{ kunde_id }}",'
                   . '"telefon":"{{ telefon }}","versuch":"{{ versuch }}","text":"{{ text }}"}',
        ];

        $konfigs['zusammenfassung'] = [
          'zweck' => 'Schickt dem Anrufer, was besprochen wurde. Nur nach ausdrücklicher Zustimmung.',
          'eig' => [
            'zustimmung' => ['type' => 'boolean', 'description' => 'Hat der Anrufer ausdrücklich zugestimmt?'],
            'sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
            'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
            'email' => ['type' => 'string', 'format' => 'email', 'description' => 'Nur bei Neukunden'],
            'text' => ['type' => 'string', 'minLength' => 20, 'maxLength' => 6000,
                       'description' => 'Die Zusammenfassung in der Sprache des Gesprächs'],
          ],
          'pflicht' => ['zustimmung', 'text'],
          'rumpf' => '{"aktion":"zusammenfassung","zustimmung":"{{ zustimmung }}","sprache":"{{ sprache }}",'
                   . '"kunde_id":"{{ kunde_id }}","email":"{{ email }}","text":"{{ text }}"}',
        ];

        /* ---------- Die sechs neuen: Beratung statt Auskunft ---------- */

        $konfigs['seite_ansehen'] = [
          'zweck' => 'Sieh dir die Website wirklich an, während er redet — es gibt zwei Wege, und du '
                   . 'wählst nicht, sondern gibst mit, was du hast. '
                   . 'IST ER BESTANDSKUNDE (du hast eine „kunde_id“ aus „kunde_nachschlagen“): gib sie mit '
                   . 'und frag NICHT nach der Adresse — sie steht in seiner Akte und wird von dort '
                   . 'genommen. Kommt „quelle“: „verwaltung“ zurück, sag ihm die Adresse zur Bestätigung '
                   . '(„Ihre Seite … , richtig?“), statt sie dir buchstabieren zu lassen. Steht in '
                   . '„aus_verwaltung“ ein Satz, ist das das Wichtigste im ganzen Gespräch — sag ihn früh. '
                   . 'IST ER KEIN KUNDE: gib die Adresse mit, so wie er sie genannt hat, auch ohne '
                   . '„www“ und auch ohne Endung. Sie wird wirklich recherchiert — mit und ohne www, über '
                   . 'https und http, mit anderen Endungen und anderen Schreibweisen —, und die wichtigsten '
                   . 'Unterseiten werden mitgelesen. „Finde ich nicht“ kommt nur, wenn es die Adresse '
                   . 'wirklich nirgends gibt. '
                   . 'Sprich dann GENAU in dieser Reihenfolge, was in „gespraech“ steht: auftakt, befund, '
                   . 'folge, frage — und sei danach still. Einen zweiten Befund nur, wenn er nachfragt. '
                   . 'Lies nie die ganze Liste vor: Eine Mängelliste am Telefon macht keinen Kunden, '
                   . 'sie macht jemanden, der sich schlecht fühlt. '
                   . 'Sage nie etwas über Aussehen oder Gestaltung — geprüft wird nur, was messbar ist —, '
                   . 'und erfinde keine Zahlen, keine Mitbewerber und keine Eile. '
                   . 'Kommt „nichts_gefunden“, sag das ehrlich und verkaufe nichts. '
                   . 'Kommt „andere_adresse“, sag zuerst, unter welcher Adresse du sie gefunden hast. '
                   . 'RATE NIE. Wird sie nicht gefunden, lass buchstabieren und versuche es genau noch '
                   . 'einmal — danach nicht mehr, sondern „melde“.',
          'eig' => [
            'adresse'  => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200,
                           'description' => 'Die Internetadresse, wie er sie nennt — auch ohne Endung. '
                                          . 'Bei einem Bestandskunden mit „kunde_id“ leer lassen'],
            'sprache'  => ['type' => 'string', 'enum' => ['it', 'de', 'en'], 'description' => 'Sprache des Gesprächs'],
            'branche'  => ['type' => 'string',
                           'enum' => array_map('strval', array_keys(Baukasten::FRAGEN['branche']['optionen'] ?? [])),
                           'description' => 'Betriebsart, falls genannt — dann wird auch geprüft, was gerade '
                                          . 'diese Branche braucht (Speisekarte, Buchung, Arbeitsproben)'],
            'kunde_id' => ['type' => 'integer', 'description' => 'Aus „kunde_nachschlagen“. Immer mitgeben, '
                                          . 'wenn vorhanden — dann kommt die Adresse aus der Verwaltung'],
            'telefon'  => ['type' => 'string', 'maxLength' => 40,
                           'description' => 'Die Rufnummer des Anrufers, dieselbe wie bei '
                                          . '„kunde_nachschlagen“ — daran wird erkannt, dass mehrere '
                                          . 'Versuche zum selben Gespräch gehören'],
          ],
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"seite_ansehen","adresse":"{{ adresse }}","sprache":"{{ sprache }}",'
                   . '"branche":"{{ branche }}","kunde_id":"{{ kunde_id }}","telefon":"{{ telefon }}"}',
        ];

        $konfigs['beratung'] = [
          'zweck' => 'Der Konfigurator als Gespräch. Ruf ihn auf, sobald jemand eine neue Website will. '
                   . 'Er gibt dir in „satz“ die nächste Frage — stelle genau diese. Die Antwort trägst du '
                   . 'beim nächsten Aufruf als „antwort“ ein, zusammen mit „antwort_auf“ und dem '
                   . '„gespraech“ aus der letzten Antwort. Nimm als Antwort nur einen Schlüssel aus '
                   . '„optionen“; bei Mehrfachfragen mehrere mit Komma. Sobald „von_euro“ kommt, darfst du '
                   . 'die Spanne nennen — immer als Spanne, nie als Festpreis. '
                   . 'Kommt „ausserhalb“ zurück, nennst du KEINE Zahl: Das Vorhaben passt nicht in '
                   . 'den Baukasten, du liest den Satz vor und bietest einen Termin an. '
                   . 'Höchstens ein Werkzeug pro Gesprächszug — zwei hintereinander machen eine Pause, '
                   . 'die der Anrufer als Stille hört. '
                   . 'Kommt „abbrechen“ zurück, hörst du auf zu fragen: Er antwortet nicht mehr richtig. '
                   . 'Dann sagst du, dass der Rest schriftlich schneller geht, fragst nach der '
                   . 'E-Mail-Adresse und rufst „uebergabe“ auf — kein „nur noch eine Frage“.',
          'eig' => [
            'gespraech'   => ['type' => 'string', 'maxLength' => 48,
                              'description' => 'Der Wert aus der letzten Antwort. Beim ersten Aufruf leer lassen'],
            'sprache'     => ['type' => 'string', 'enum' => ['it', 'de', 'en'], 'description' => 'Sprache des Gesprächs'],
            'antwort_auf' => ['type' => 'string', 'enum' => Telefon::BERATUNG_REIHE,
                              'description' => 'Auf welche Frage sich die Antwort bezieht — das Feld aus „frage_zu“'],
            'antwort'     => ['type' => 'string', 'maxLength' => 200,
                              'description' => 'Ein Schlüssel aus „optionen“. Mehrere mit Komma, wenn die Frage '
                                             . 'mehrfach ist. Nichts anderes — Freitext wird verworfen'],
            'vorhaben'    => ['type' => 'string', 'maxLength' => 300,
                              'description' => 'Was er sich wünscht, in seinen eigenen Worten — beim ERSTEN '
                                             . 'Aufruf mitgeben. Daran wird erkannt, ob es überhaupt in den '
                                             . 'Baukasten passt'],
            'kunde_id'    => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
          ],
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"beratung","gespraech":"{{ gespraech }}","sprache":"{{ sprache }}",'
                   . '"antwort_auf":"{{ antwort_auf }}","antwort":"{{ antwort }}",'
                   . '"vorhaben":"{{ vorhaben }}","kunde_id":"{{ kunde_id }}"}',
        ];

        $konfigs['beleg'] = [
          'zweck' => 'Eine echte Kundenstimme statt eines Werbesatzes. Nenne höchstens eine, sinngemäß, '
                   . 'mit dem Betrieb dazu. Kommt keine zurück, erfinde keine — sag stattdessen, '
                   . 'dass Uwe Beispiele schickt.',
          'eig' => [
            'branche' => ['type' => 'string',
                          'enum' => array_map('strval', array_keys(Baukasten::FRAGEN['branche']['optionen'] ?? [])),
                          'description' => 'Betriebsart des Anrufers, falls genannt'],
            'sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
          ],
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"beleg","branche":"{{ branche }}","sprache":"{{ sprache }}"}',
        ];

        $konfigs['uebergabe'] = [
          'zweck' => 'Nach dem Gespräch: schickt schriftlich, worüber gesprochen wurde, die Spanne, '
                   . 'einen Befund von seiner Seite und den halb ausgefüllten Fragebogen. Ruf sie auf, '
                   . 'wenn die Beratung durch ist oder das Gespräch endet. Nenne danach keine Frist '
                   . 'und sag nicht „melden Sie sich bald“. '
                   . 'WICHTIG: Sag erst „ist raus“, NACHDEM dieses Werkzeug „ok“ zurückgegeben hat. '
                   . 'Eine Zusage, die du nicht eingelöst hast, ist schlimmer als gar keine.',
          'eig' => [
            'gespraech' => ['type' => 'string', 'maxLength' => 48,
                            'description' => 'Der Wert aus „beratung“, damit der Fragebogen vorausgefüllt ist'],
            'sprache'   => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
            'kunde_id'  => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
            'email'     => ['type' => 'string', 'format' => 'email',
                            'description' => 'Nur bei Neukunden, buchstabieren lassen'],
            'name'      => ['type' => 'string', 'description' => 'Name für die Anrede'],
            'von_euro'  => ['type' => 'integer', 'description' => 'Untere Grenze aus „beratung“, falls genannt'],
            'bis_euro'  => ['type' => 'integer', 'description' => 'Obere Grenze aus „beratung“, falls genannt'],
            'befund'    => ['type' => 'string', 'maxLength' => 400,
                            'description' => 'Ein Satz aus „seite_ansehen“, wörtlich — sonst leer lassen'],
          ],
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"uebergabe","gespraech":"{{ gespraech }}","sprache":"{{ sprache }}",'
                   . '"kunde_id":"{{ kunde_id }}","email":"{{ email }}","name":"{{ name }}",'
                   . '"von_euro":"{{ von_euro }}","bis_euro":"{{ bis_euro }}","befund":"{{ befund }}"}',
        ];

        $konfigs['wissen'] = [
          'zweck' => 'Pakete, Bausteine und Preise, wie sie in dieser Sekunde in der Verwaltung stehen. '
                   . 'Immer hier nachsehen, statt eine Zahl aus dem Gedächtnis zu nennen. '
                   . 'Diese Zahlen sind Einzelpreise — der Preis eines Projekts ist eine Spanne '
                   . 'und kommt aus „beratung“.',
          'eig' => ['sprache' => ['type' => 'string', 'enum' => ['it', 'de', 'en']]],
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"wissen","sprache":"{{ sprache }}"}',
        ];

        $konfigs['termin'] = [
          'zweck' => 'Ein fester Termin statt „er meldet sich“. Ohne „wann“ bekommst du freie Plätze — '
                   . 'nenne höchstens drei davon, nicht die ganze Liste. Sagt er einen zu, ruf noch '
                   . 'einmal auf und gib ihn in „wann“ genau so mit, wie er in der Liste stand.',
          'eig' => [
            'wann'     => ['type' => 'string', 'maxLength' => 16,
                           'description' => 'Ein Platz aus „frei“, wörtlich, z. B. 2026-09-08 15:00. '
                                          . 'Leer lassen, um die freien Plätze zu erfragen'],
            'kunde_id' => ['type' => 'integer', 'description' => 'Nur wenn vorher gefunden'],
            'name'     => ['type' => 'string', 'description' => 'Name des Anrufers'],
            'telefon'  => ['type' => 'string', 'description' => 'Rufnummer für den Anruf'],
            'anliegen' => ['type' => 'string', 'maxLength' => 500,
                           'description' => 'Worum es gehen soll, in seinen Worten'],
            'sprache'  => ['type' => 'string', 'enum' => ['it', 'de', 'en']],
          ],
          'pflicht' => ['sprache'],
          'rumpf' => '{"aktion":"termin","wann":"{{ wann }}","kunde_id":"{{ kunde_id }}",'
                   . '"name":"{{ name }}","telefon":"{{ telefon }}","anliegen":"{{ anliegen }}",'
                   . '"sprache":"{{ sprache }}"}',
        ];
        /* In der Reihenfolge des Gesprächs, und nur was es wirklich gibt. */
        $sortiert = [];
        foreach (self::REIHE as $name) {
            if (isset($konfigs[$name])) { $sortiert[$name] = $konfigs[$name]; }
        }
        foreach ($konfigs as $name => $k) {          // was in REIHE fehlt, geht nicht verloren
            if (!isset($sortiert[$name])) { $sortiert[$name] = $k; }
        }
        return $sortiert;
    }

    /**
     * Die fertigen JSON-Blöcke, wie STRATO sie erwartet.
     *
     * @return array<string,string>
     */
    public static function json(): array
    {
        $adresse    = Telefon::adresse();
        $schluessel = Telefon::schluessel();
        $aus = [];
        foreach (self::alle() as $name => $k) {
            $aus[$name] = Telefon::konfigJson($name, $k, $adresse, $schluessel);
        }
        return $aus;
    }

    /**
     * Dasselbe als Objekte -- so liegen sie in Stratos config.
     *
     * WARUM HIER KEIN ASSOZIATIVES ARRAY HERAUSKOMMT
     * ---------------------------------------------------------------------
     * Der erste Entwurf dekodierte mit json_decode($text, true). Damit wird
     * aus einem leeren JSON-Objekt {} ein leeres PHP-Array [], und beim
     * Zurückschreiben steht "properties": [] statt {}. Genau davor warnt der
     * Kommentar in Telefon::konfigJson() seit dem ersten Tag -- und genau
     * das habe ich am 7. September wieder eingebaut.
     *
     * Betroffen war „lage": Es hat als einziges Werkzeug keine Eigenschaften.
     * Stratos Schemaprüfung lehnte es ab, und der Assistent war nicht mehr
     * erreichbar. Ein Fehler in einem von vierzehn Werkzeugen legt alle
     * vierzehn still.
     *
     * Ohne das zweite Argument kommen stdClass-Objekte heraus, und ein
     * leeres Objekt bleibt ein leeres Objekt -- durch beliebig viele
     * Runden aus Dekodieren und Kodieren.
     *
     * @return list<object>
     */
    public static function objekte(): array
    {
        $aus = [];
        foreach (self::json() as $text) {
            $d = json_decode($text);          // NICHT true — siehe oben
            if ($d instanceof stdClass) { $aus[] = $d; }
        }
        return $aus;
    }
}
