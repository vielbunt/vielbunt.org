# vielbunt 2.0

WordPress Block-Child-Theme auf Basis von Twenty Twenty-Five, gebaut für
vielbunt e.V. (Queere Community Darmstadt). Wir übernehmen die Markenfarben,
den eckigen Poster-Look, Cera Pro als Schrift und bauen die Startseite
automatisch aus unseren bestehenden Beiträgen zusammen.

## Installation

1. Zuerst **Twenty Twenty-Five** im WordPress-Backend installieren
   (Design → Themes → Theme hinzufügen). Es muss nur vorhanden sein,
   nicht aktiviert.
2. Die ZIP-Datei `vielbunt.zip` unter Design → Themes → Theme hochladen
   einspielen und aktivieren.
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

## Wie die Startseite aufgebaut ist

Vier eigene Blöcke machen die Startseite aus, alle server-seitig
gerendert und im Site-Editor unter „vielbunt:" einfügbar: Hero,
Schnellzugriff, Aktuelle Termine und News-Feed. Keine Shortcodes,
keine rohen HTML-Blöcke, das hat beim ersten Versuch alles kaputtgemacht.

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

**Hero-Text als Filter:** Wer lieber in `functions.php` arbeitet,
kann Kicker/Titel/Lead auch über `vielbunt_hero_title` etc. setzen.
Block-Attribut hat dann Vorrang.

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
