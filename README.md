# vielbunt 2.0

WordPress Block-Child-Theme auf Basis von Twenty Twenty-Five, gebaut für
vielbunt e.V. (Queere Community Darmstadt). Wir übernehmen die Markenfarben,
den eckigen Poster-Look, Cera Pro als Schrift und bauen die Startseite
automatisch aus unseren bestehenden Beiträgen zusammen.

## Installation

1. Zuerst **Twenty Twenty-Five** im WordPress-Backend installieren
   (Design → Themes → Theme hinzufügen). Es muss nur vorhanden sein,
   nicht aktiviert.
2. Auf dem Server liegt das Theme im Ordner `vielbuntzweinull`. Nur die
   allererste Installation ist ein Upload von Hand (Design → Themes →
   Theme hochladen, das ZIP muss genau diesen Ordnernamen enthalten).
   Danach kommen Updates automatisch, siehe „Updates und Deployment".
3. **Cera Pro** wird automatisch aus unserer Mediathek geladen
   (`/wp-content/uploads/2021/01/`). Wir liefern keine Font-Dateien
   mit, das ist so gewollt (Lizenz, siehe unten).
4. Im Site-Editor (Design → Editor) den Navigationsblock im Header
   aufmachen und unser bestehendes Menü auswählen. Im Footer genauso.
5. Unter Einstellungen → Lesen „Eine statische Seite" wählen und unsere
   Homepage dort eintragen. Das Template `front-page` greift dann
   automatisch.

Bitte zuerst lokal (LocalWP, DDEV) oder auf einer Staging-Subdomain
testen bevor wir das auf der Live-Seite machen. Inhalte bleiben erhalten,
wir ändern nur die Darstellung.

## Updates und Deployment

Jeder Push auf `main` geht von selbst live, meistens nach zwei bis drei
Minuten:

1. Die GitHub Action (`.github/workflows/deploy.yml`) prüft PHP-Syntax,
   `theme.json` und das JavaScript und fährt dann ein Wegwerf-WordPress mit
   dem Theme hoch (WordPress Playground), in dem ein paar Seiten geladen
   werden. Jeder PHP-Fehler und jede Warnung stoppt alles, auf dem Server
   kommt dann nichts an.
2. Sie baut `theme.zip` (Ordner `vielbuntzweinull`, Version = die aus der
   `style.css` plus Laufnummer, z. B. `2.2.0.17`) und legt damit ein
   GitHub-Release an, zusammen mit einer kleinen `release.json`.
3. Sie ruft `POST https://www.vielbunt.org/wp-json/vielbunt/v1/deploy` mit
   dem Secret `DEPLOY_TOKEN` auf. WordPress lädt das Release und installiert
   es über den ganz normalen Theme-Updater in den bestehenden Ordner.
   Inhalte, Menüs und Startseiten-Einstellungen bleiben unangetastet.
4. Zum Schluss lädt sie die Live-Startseite und schaut, ob Hero und Kacheln
   da sind.

Klappt der Webhook mal nicht, sieht WordPress das Update trotzdem unter
Dashboard → Aktualisierungen und spielt es mit dem automatischen
Hintergrund-Update ein (für dieses Theme fest eingeschaltet).

Status, letzte Läufe, „Jetzt aktualisieren" und das Token gibt es unter
**Design → Theme-Updates** im Backend.

**Einmalig für den Webhook:** Im Backend Design → Theme-Updates öffnen,
„Token erzeugen" klicken und das Token in GitHub unter Settings → Secrets
and variables → Actions als `DEPLOY_TOKEN` eintragen. Alternativ
`VIELBUNT_DEPLOY_TOKEN` in der `wp-config.php` setzen.

Für größere Änderungen einfach `Version:` in der `style.css` hochsetzen,
die Laufnummer hängt die Action selbst dran.

## Wie die Startseite aufgebaut ist

Vier eigene Blöcke machen die Startseite aus, alle server-seitig
gerendert und im Site-Editor unter „vielbunt:" einfügbar: Hero,
Schnellzugriff, Aktuelle Termine und News-Feed. Keine Shortcodes,
keine rohen HTML-Blöcke, das hat beim ersten Versuch alles kaputtgemacht.

## Spendenkampagne (optional, Donorbox)

Unter dem Hero kann optional ein Kampagnen-Bereich mit Donorbox-Zielmesser
und Spenden-Button erscheinen. Er ist **standardmäßig aus**, die Startseite
bleibt also wie bisher. Steuerung über **Design → Customizer →
„Spendenkampagne"**: Häkchen setzen, Überschrift und optionalen Text
eintragen und die Einbettungscodes aus Donorbox (Kampagne → „Ziel-Messer"
bzw. „Spenden-Button") einfügen. Solange die Kampagne deaktiviert ist oder
kein Code hinterlegt wurde, wird nichts gerendert – ohne Layout-Lücke.

Technisch wird der Bereich nicht als platzierbarer Block ins Template
geschrieben, sondern per `render_block`-Filter
(`vielbunt_render_campaign_after_hero`) direkt hinter den Hero gehängt. Das
ist nötig, weil ein im Site-Editor angepasstes `front-page`-Template in der
Datenbank liegt und Änderungen an der Theme-Datei dann ignoriert würden.
(Gleiche Mechanik wie auf csd-darmstadt.de, dort aber für die CSD-2026-
Kampagne aktiv vorbelegt.)

## Die Aktuelles-Logik

Wir lesen die nächsten 8 Termine direkt aus unseren Beiträgen. Entscheident
ist ein führendes Datum im Beitragstitel:

- `06.06.: Museumsbesuch ...` → Termin am 6. Juni, Titel „Museumsbesuch"
- `28.05. · 19:00 treffbunt Nr. 182` → Termin am 28. Mai
- `01.06.-05.06.2026 Wochenprogramm` → Startet am 1. Juni
- `vielbunt zum IDAHOBITA*` → kein Datum erkannt, landet im News-Feed

Das Datum oben links auf der Kachel kommt aus genau diesem Titel, wir
pflegen also nichts doppelt. Der Datumspräfix wird für die Anzeige
automatisch abgeschnitten.

Vergangene Termine fallen automatisch raus. Wenn es zu wenige zukünftige
gibt füllen wir mit kürzlich vergangenen (max. 14 Tage) auf, immer so
dass volle Reihen rauskommen (8 oder 4 Kacheln, nie 7 oder 5).

Da die Titel meist nur Tag/Monat tragen leiten wir das Jahr selbst ab,
ausgehend vom Veröffentlichungsdatum des Beitrags. Ein explizit
genanntes Jahr (z.B. `2026`) hat natürlich Vorrang.

Hat der Beitrag ein Bild (Beitragsbild, sonst das erste Bild im Inhalt),
zeigen wir das als Sharepic unverändert. Ohne Bild: farbige Kachel mit
Datum und Titel. Alle Kacheln haben das selbe Seitenverhältnis (819:1024,
passend zu unseren Sharepic-Vorlagen).

Der News-Feed unten zeigt das Gegenteil: alle Beiträge ohne führendes
Datum, neuste zuerst.

## Was man im Editor ändern kann

Wir haben die wichtigsten Inhalte direkt im Site-Editor bearbeitbar
gemacht, ohne in den Code zu müssen:

**Hero-Block** (rechte Seitenleiste):
- Hintergrundbild aus unserer Mediathek wählen
- Kicker, Titel und Leadtext überschreiben
- Beschriftung und URL beider Buttons ändern

Alles leer lassen und es greift der Standard-Wert.

**Schnellzugriff-Block**:
- Für jede der 8 Kacheln: Beschriftung und URL ändern
- Hintergrundbild pro Kachel wählen (der Farbschleier kommt automatisch)
- Überschrift „Schnellzugriff" ändern

Alles wird sofort in der Vorschau angezeigt und mit dem normalen
**Speichern** oben rechts übernommen. Leere Felder zeigen den grauen
Standardtext.

**Hero-Text als Filter:** Wer lieber in `functions.php` arbeitet,
kann Kicker/Titel/Lead auch über `vielbunt_hero_title` etc. setzen.
Ein im Editor eingetragener Text hat dann Vorrang.

### Warum die Kachelbilder nicht mehr verschwinden

Bis 2.1.x lagen Hero-Texte und Kachelbilder als Block-Attribute im
`front-page`-Template. WordPress serialisiert Templates bei jedem
Speichern in PHP neu und macht dabei aus der Bilder-Map `{"0":…,"1":…}`
eine Liste `[…]`. Der Editor verwirft diese Liste beim nächsten Öffnen,
und alle Kachelbilder sind weg. Theme-Neu-Upload oder Template-Reset
haben die Attribute zusätzlich jedes Mal mitgenommen. Die alte
Spiegelung nach `vielbunt_block_settings` hat das nicht aufgefangen, weil
sie denselben Fehler mitgeschleppt hat.

Seit 2.2 liegt alles in einer Option, `vielbunt_frontpage`
(siehe `inc/frontpage.php`):

- mit strengem REST-Schema registriert und über die WordPress-eigene
  „site"-Entity bearbeitet, wird also mit „Speichern" wie alles andere
  gespeichert,
- unabhängig von Theme-Ordner, Template und Theme-Updates,
- Bilder werden mit Anhang-ID gespeichert (URL als Rückfallebene).

Beim ersten Seitenaufruf nach dem Update werden die Inhalte einmalig aus
dem angepassten Template und aus `vielbunt_block_settings` übernommen
(die alte Option bleibt als Backup liegen). Dieselbe Migration nimmt auch
ein paar alte HTML-Kommentare aus dem front-page-Template, wegen denen der
Editor die `<main>`-Gruppe als „ungültiger Inhalt" markiert hat.

## Beschreibung und Link-Vorschau

`inc/meta.php` setzt eine Meta-Beschreibung (Startseite: Leadtext aus dem
Hero, Beiträge: Auszug oder Textanfang) und Open-Graph-Tags für die
Vorschau in WhatsApp, Signal, Instagram & Co. Als Bild nehmen wir das
Beitragsbild, sonst das erste Bild im Beitrag, sonst `assets/share.png`.
Kommt ein SEO- oder OG-Plugin dazu, hält sich das Theme automatisch raus.

## Lightbox, Icons, einmalige Schritte

- **Lightbox:** `assets/lightbox.js` (ohne jQuery) öffnet jeden Link auf eine
  Bilddatei, mit Pfeilen, Tasten und Wischen durch Galerie bzw. Beitrag.
  Bildblöcke ohne Link nutzen die Lightbox von WordPress (in `theme.json`
  eingeschaltet). Das alte FancyBox-Plugin braucht es nicht mehr.
- **Icons:** Favicon und App-Icons liegen in `assets/icons` und ersetzen das
  Website-Icon aus dem Customizer.
- **Einmalige Schritte:** `inc/once.php` enthält Dinge, die nach einem Update
  genau einmal auf dem Server passieren sollen (z. B. FancyBox-Plugin
  abschalten, Autoptimize lässt Google Fonts in Ruhe). Ergebnis unter
  Design → Theme-Updates.

## Farben und Schrift

Unter Design → Editor → Stile liegt die vielbunt-Palette mit allen sechs
Regenbogenfarben plus Anthrazit, Creme und Weiß. Schrift ist Cera Pro
(per @font-face aus unserer Mediathek, nicht im Theme enthalten).

## Lizenzhinweis Cera Pro

Die Schriftart ist lizensiert und darf laut vielbunt-Richtlinie nicht
weitergegeben und nicht außerhalb von vielbunt-Kontexten verwendet werden.
Wir liefern deshalb keine Schriftdateien mit sondern laden sie per
@font-face aus unserer eigenen Mediathek. So bleibt das Theme weitergebbar
und trotzdem alles lizenzkonform.

## Die atmenden Balken im Hero

Das sind ein dekoratives Motiv das die Bildmarke zittiert, nicht das
echte Logo. Wir verändern die Geometrie des Logos nie (vielbunt-Richtlinie).
Die Animation respektiert `prefers-reduced-motion`.
