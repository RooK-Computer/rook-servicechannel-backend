# Teilplan 09 - Abgeschlossenes Team-UI-Finetuning fuer Terminal-Layout und Files-Fallback

## Status

Abgeschlossen.

## Ziel

Die nach der ersten Umsetzung der Team-UI im laufenden Integrationsabgleich sichtbar gewordenen Feinjustierungen fuer Browser-Terminal und Drupal-`files`-Fallback als abgeschlossene Repo-Entscheidung dokumentieren.

## Anlass

Nach Teilplan 08 war die Team-UI bereits funktional auf dem gewuenschten Zielbild, im praktischen Einsatz zeigten sich aber noch konkrete Detailprobleme:

* das Terminal blieb trotz 4:3-Regel zu klein oder zu gross,
* der Terminalbereich brach zunaechst zu aggressiv gegen Theme-Chrome und Sidebar aus,
* die Hoehenberechnung reagierte auf Scrollposition statt auf den stabil verfuegbaren Drupal-Bereich,
* die Wiedererzeugung aggregierter CSS/JS-Dateien unter `sites/default/files/` wurde durch die lokale Apache-Konfiguration weiterhin blockiert.

## Umgesetzte Entscheidungen

### 1. Terminalbreite nur innerhalb des real verfuegbaren Drupal-Bereichs

Die Team-UI nutzt fuer den Terminalblock keinen blinden Full-Bleed bis an den Browserrand mehr.

Stattdessen gilt:

* der Bedienblock bleibt in der normalen Content-Spalte,
* der Terminalblock darf nach rechts aus der engen Content-Spalte ausbrechen,
* seine Breite wird aber nur bis in den real verfuegbaren Bereich des Drupal-Layouts erweitert,
* ein visueller Seitenabstand bleibt bewusst erhalten, damit Theme-Sidebar und Admin-Chrome nicht ueberfahren werden.

### 2. Terminalhoehe statisch gegen den verfuegbaren Drupal-Bereich

Die Terminalhoehe wird nicht mehr aus der aktuellen Scrollposition abgeleitet.

Stattdessen gilt:

* Hoehenlimit bleibt weiterhin `min(4:3-Hoehe, verfuegbare Hoehe)`,
* als verfuegbare Hoehe zaehlt der sichtbare Bereich unter der oberen Drupal-Toolbar,
* die schwarze Admin-Leiste und weitere obere Toolbar-Elemente werden explizit beruecksichtigt,
* Scrollen veraendert die Terminalhoehe nicht mehr,
* das Terminal soll so gross sein, dass es immer vollstaendig in den verfuegbaren Drupal-Bereich gescrollt werden kann.

### 3. Reflow-Trigger fuer die Team-UI

Die Layout-Neuberechnung wurde von reinem `window.resize` geloest.

Aktuell reagiert die Terminal-UI auf:

* echte Groessenaenderungen des Terminal-Containers,
* Groessenaenderungen des Visual Viewports,
* Groessenaenderungen relevanter Drupal-Toolbar-Elemente,
* einen kurzen nachgelagerten Reflow nach Initial-Render.

Nicht mehr gewollt ist:

* Groessenaenderung nur durch Scrollposition,
* implizite Korrektur erst nach Ein- oder Ausblenden von Browser-UI-Chrome.

### 4. Apache-Fallback fuer fehlende Aggregate im `files`-Ordner

Die `.htaccess` unter `docroot/sites/default/files/` wurde so nachgeschaerft, dass fehlende Aggregatdateien unter `css/` und `js/` wieder an Drupal zurueckfallen koennen.

Wesentliche Korrektur:

* der vorher ergaenzte Rewrite allein reichte nicht,
* fuer die betroffenen Pfade muss der directory-weite `Drupal_Security`-Handler zuvor auf normales Dateihandling zurueckgesetzt werden,
* danach kann der Rewrite-Fallback fuer fehlende Aggregatdateien auf `/index.php` greifen.

## Betroffene Dateien

* `docroot/modules/custom/rook_servicechannel_team_ui/src/team-ui.tsx`
* `docroot/modules/custom/rook_servicechannel_team_ui/css/team-ui.css`
* `docroot/modules/custom/rook_servicechannel_team_ui/js/team-ui.js`
* `docroot/sites/default/files/.htaccess`

## Validierter Repo-Stand

Der Folgeabgleich wurde im Backend-Repo umgesetzt und gegen die vorhandenen Checks nachgezogen:

* Team-UI-Typecheck und Bundle-Neuaufbau ueber das modul-lokale Frontend-Tooling
* vorhandener Kernel-Test `TeamUiKernelTest`
* Apache-Syntaxpruefung fuer die geaenderte `files/.htaccess`

## Nicht Ziel dieses Nachtrags

Dieser Nachtrag fuehrt keine neue Spezifikationslinie ein.

Nicht Bestandteil:

* neue Backend-API-Flaechen,
* Gateway-interne Layout- oder Timeout-Regeln,
* weitergehende Theme-Anpassungen ausserhalb der Team-UI,
* vollstaendige Ende-zu-Ende-Bewertung gegen einen laufenden Gateway im echten Zielsystem.

## Uebergabehinweis

Fuer nachfolgende Teams gilt:

* Die uebergeordnete Spec in `spec/` bleibt der fachliche Zielrahmen.
* Dieser Teilplan dokumentiert zusaetzlich die konkret im Backend-Repo getroffenen UI- und Infrastrukturentscheidungen aus dem Integrationsfeintuning.
* Weitere UI-Arbeit sollte auf diesem erreichten Verhalten aufsetzen, statt die Terminalgroesse erneut direkt an Scrollposition oder Browser-Vollbreite zu koppeln.
