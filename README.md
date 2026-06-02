# vielbunt 2.0 – WordPress Block-Child-Theme

Block-Child-Theme auf Basis von **Twenty Twenty-Five** für vielbunt e.V.
(Queere Community Darmstadt). Übernimmt die Markenfarben, den eckigen
Poster-Look, Cera Pro als Schrift und erzeugt die Startseite automatisch
aus euren bestehenden Beiträgen.

## Installation

1. **Eltern-Theme installieren:** In WordPress unter *Design → Themes →
   Theme hinzufügen* nach „Twenty Twenty-Five" suchen und installieren
   (muss vorhanden, aber nicht aktiviert sein).
2. **Dieses Theme hochladen:** Die ZIP-Datei `vielbunt.zip` unter
   *Design → Themes → Theme hinzufügen → Theme hochladen* einspielen und
   aktivieren.
3. **Cera Pro:** Die Schrift wird automatisch aus eurer Mediathek geladen
   (`/wp-content/uploads/2021/01/`). Es werden **keine** Font-Dateien
   mitgeliefert – das ist aus Lizenzgründen so gewollt (siehe unten).
4. **Menü zuweisen:** Im Site-Editor (*Design → Editor*) den Navigations-
   block im Header öffnen und euer bestehendes Menü auswählen bzw.
   importieren. Gleiches im Footer.
5. **Startseite setzen:** Unter *Einstellungen → Lesen* „Eine statische
   Seite" wählen und als Startseite eure Homepage setzen – das Template
   `front-page` greift dann automatisch.

> **Wichtig:** Zuerst lokal (LocalWP/DDEV) oder auf einer Staging-
> Subdomain testen, nicht direkt auf der Live-Seite. Inhalte (Beiträge,
> Seiten, Menüs) bleiben erhalten – das Theme verändert nur die Darstellung.

## Aufbau der Startseite

Die Startseite besteht aus vier eigenen Blöcken (server-seitig gerendert,
im Site-Editor unter „vielbunt:" einfügbar): Hero, Schnellzugriff,
Aktuelle Termine und News-Feed. Es werden **keine Shortcodes und keine
rohen HTML-Blöcke** verwendet.

## Die automatische „Aktuelles"-Logik

Das Theme liest die **nächsten 8 Termine** automatisch aus euren Beiträgen.
Entscheidend ist ein **führendes Datum im Beitragstitel**:

| Beitragstitel                         | Ergebnis                          |
|---------------------------------------|-----------------------------------|
| `06.06.: Museumsbesuch …`             | Termin am 6. Juni, Titel „Museumsbesuch …" |
| `28.05. · 19:00 treffbunt Nr. 182`    | Termin am 28. Mai                 |
| `01.06.-05.06.2026 Wochenprogramm`    | Termin ab 1. Juni (villaQ)        |
| `vielbunt zum IDAHOBITA*`             | **kein** Termin → News-Feed unten |

- Der Datums-Block oben links auf der Kachel wird aus genau diesem Titel-
  Datum erzeugt – ihr pflegt also **nichts doppelt**.
- Der Datumspräfix wird für die Anzeige automatisch vom Titel abgeschnitten.
- **Vergangene** Termine fallen automatisch raus; sortiert wird aufsteigend.
- Da die Titel nur Tag/Monat tragen, leitet das Theme das Jahr selbst ab
  (nächstes Auftreten). Ein explizit genanntes Jahr hat Vorrang.
- Hat der Beitrag ein Bild (Beitragsbild, sonst erstes Bild im Inhalt),
  wird dieses **Sharepic unverändert** als Kachel gezeigt – alle im selben
  Format (4:5). Alt-Text für Screenreader = Datum + Titel. Ohne Bild:
  farbige Kachel mit Datum + Titel.

Der **News-Feed „Aus dem Verein"** (zwischen CTA und Footer) zeigt das
Gegenteil: alle Beiträge **ohne** führendes Datum, neueste zuerst.

### Wochenprogramme als Termine behandeln
Aktuell erscheinen die villaQ-Wochenprogramme (führendes Datum) wie
gewünscht **oben** in den Terminen. Sollen sie später doch in den Feed,
lässt sich das in `functions.php` über eine Kategorie-Abfrage trennen.

## Anpassen

- **Farben & Schrift:** *Design → Editor → Stile* – die vielbunt-Palette
  ist hinterlegt.
- **Hero-Hintergrund:** Im Site-Editor (*Design → Editor*) die Startseite
  öffnen, den Hero-Block anklicken; rechts in der Seitenleiste unter
  „Hintergrundbild" ein Bild aus der Mediathek wählen. Die pinke
  Überlagerung und der weiße Text bleiben automatisch lesbar. Ohne Bild
  zeigt der Hero einen Marken-Verlauf als Platzhalter.
- **Schnellzugriff-Hintergrund:** Schnellzugriff-Block anklicken,
  Hintergrundbild ebenso in der Seitenleiste wählen. Der weiße Schleier
  dimmt das Bild automatisch auf ~20 %, die farbigen Kacheln liegen
  deckend darüber.
- **Hero-Texte:** aktuell per Filter (`vielbunt_hero_title`,
  `vielbunt_hero_kicker`, `vielbunt_hero_lead`) in der `functions.php`
  anpassbar.
- **Logo:** Header und Footer nutzen zwei Varianten desselben Logos –
  Header die **farbige** Variante (Bildmarke + Wortmarke), Footer die
  **weiße** Variante mit Claim auf dunklem Grund (entspricht der
  Richtlinie für dunkle Hintergründe). Beides über den Block
  „vielbunt: Logo" (Attribut `variant`: `color` bzw. `white-claim`). Die
  Wortmarke ist Live-Text in Cera Pro (vielbunt-Kontext = lizenzkonform).

## Lizenzhinweis Cera Pro

Die Schriftart der Wortmarke ist lizenziert. Laut vielbunt-Richtlinie darf
die Font **nicht weitergegeben** und **nicht außerhalb von vielbunt-
Kontexten** verwendet werden. Deshalb enthält dieses Theme **keine**
Schriftdateien, sondern lädt sie per `@font-face` aus eurer eigenen
Mediathek. Damit bleibt das Theme weitergebbar und lizenzkonform.

## Hinweis zur Logo-Animation

Die langsam „atmenden" Balken im Hero sind ein **dekoratives Motiv**, das
die Bildmarke zitiert – **nicht** das echte Logo. Die Geometrie des Logos
wird nie verändert (vielbunt-Richtlinie, Folie 5). Die Animation
respektiert zudem `prefers-reduced-motion`.
