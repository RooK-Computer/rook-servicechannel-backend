# Feature-Spezifikation 01 – Dateiverwaltung und Konsolen-Downloads

## Ziel

Service-Mitarbeiter brauchen eine einfache Moeglichkeit, Dateien entweder dauerhaft fuer ihre Arbeit vorzuhalten oder waehrend einer laufenden Support-Session kurzlebig hochzuladen und anschliessend auf die verbundene Konsole zu uebertragen.

Das Feature kombiniert deshalb:

* eine allgemeine Dateiverwaltung fuer eingeloggte Service-User
* eine dateibezogene Seitenleiste in der bestehenden Service-UI
* einen einfachen Download-Flow auf die Konsole ueber eingefuegte `curl`-Befehle

## Beteiligte Nutzer und Systeme

### Nutzer

* eingeloggte Backend-Benutzer mit der Rolle `Service`

### Bestehende Bezugssysteme

* Drupal-basiertes Backend
* bestehende Service-UI unter `/servicechannel/team`
* bestehende `support_session`-Logik
* bestehende Gateway-Basis-URL-Konfiguration der Team-UI
* bestehendes Audit-Modell des Backends

## Fachlicher Geltungsbereich

Das Feature umfasst zwei Oberflaechen:

1. eine eigene Dateiverwaltungsseite im Backend
2. eine Dateien-Seitenleiste in der Service-UI

Nicht Teil des Features ist:

* eine anonyme oeffentliche Dateifreigabe
* eine Dateifreigabe ausserhalb des `Service`-Rollenmodells
* ein separates Download-Token- oder Signed-URL-System fuer Konsolen-Downloads
* ein Papierkorb- oder Restore-Mechanismus

## Dateiarten

### 1. Dauerhafte Dateien

Dauerhafte Dateien sind benutzerbezogene Dateien, die langfristig in der Dateiverwaltung gepflegt werden.

Eigenschaften:

* gehoeren genau einem Owner
* haben einen Pflicht-Titel
* koennen eine optionale Kurzbeschreibung haben
* koennen privat oder global freigegeben sein
* koennen vom Owner bearbeitet, ersetzt und geloescht werden

### 2. Kurzlebige Session-Dateien

Kurzlebige Session-Dateien sind Dateien, die waehrend einer aktiven Support-Session hochgeladen werden.

Eigenschaften:

* gehoeren dem hochladenden Service-User
* sind an genau eine `support_session` gebunden
* verwenden den Dateinamen als Titel
* verwenden keine fachlich relevante Kurzbeschreibung
* koennen nicht global freigegeben werden
* koennen manuell geloescht, aber nicht direkt bearbeitet oder ersetzt werden
* werden nach dem backendseitigen Ende der zugehoerigen Session zeitnah automatisch geloescht

## Fachliche Kernregeln

### Zugriff und Sichtbarkeit

* Nur eingeloggte Benutzer mit Rolle `Service` haben Zugriff auf die Dateiverwaltung.
* Private dauerhafte Dateien sind nur fuer ihren Owner sichtbar und nutzbar.
* Global freigegebene dauerhafte Dateien sind fuer alle Service-User sichtbar:
  * in der Dateiverwaltung read-only fuer Nicht-Owner
  * in der Service-UI fuer den Konsolen-Download verwendbar
* Der Owner kann eine globale Freigabe spaeter wieder entziehen.
* Kurzlebige Session-Dateien sind ausschliesslich fuer ihren hochladenden User sichtbar und nutzbar.

### Bearbeitung und Loeschung

* Der Owner darf bei dauerhaften Dateien sowohl Metadaten als auch die eigentliche Datei ersetzen.
* Beim Ersetzen einer dauerhaften Datei bleibt ihre Download-URL stabil und zeigt auf die aktuelle Version.
* Dauerhafte Dateien werden beim Loeschen sofort endgueltig entfernt.
* Kurzlebige Dateien koennen vor Session-Ende manuell geloescht werden.
* Wenn sich eine kurzlebige Datei aendern soll, wird sie geloescht und neu angelegt.

### Suche und Sortierung

* Die Suche laeuft ueber Dateiname und Titel.
* Ohne aktive Suche oder alternative Sortierung erscheinen Dateien mit den neuesten zuerst.

## Dateiverwaltungsseite

Die allgemeine Dateiverwaltung muss mindestens folgende Nutzerfaelle abdecken:

* dauerhafte Datei hochladen
* Titel und optionale Kurzbeschreibung erfassen oder aendern
* globale Freigabe setzen oder entziehen
* eigene dauerhafte Datei ersetzen
* eigene dauerhafte Datei loeschen
* global freigegebene Dateien anderer User read-only einsehen
* Dateien im eingeloggten Browser herunterladen

## Dateien-Seitenleiste in der Service-UI

Die Seitenleiste zeigt in einer gemeinsamen Liste:

* eigene dauerhafte Dateien
* global freigegebene dauerhafte Dateien
* eigene kurzlebige Dateien der aktuellen Support-Session

Dabei gilt:

* Herkunft oder Art der Datei muss sichtbar gekennzeichnet sein
* die Liste ist durchsuchbar
* fuer kurzlebige Dateien gibt es in der Seitenleiste einen Upload-Einstieg

## Konsolen-Download

Wenn in der Service-UI auf eine Datei geklickt wird:

* wird ein passender `curl`-Befehl in das verbundene Terminal eingefuegt
* der Befehl wird nicht automatisch ausgefuehrt
* die Datei wird in das aktuelle Arbeitsverzeichnis geladen
* der Originaldateiname wird verwendet
* vorhandene Dateien gleichen Namens sollen nicht automatisch ueberschrieben werden

## Autorisierungsmodell fuer Downloads

### Konsolen-Downloads

* Fuer Konsolen-Downloads reicht es aus, dass der Request ueber die VPN-Verbindung eingeht.
* Eine aktuell gekoppelte Support-Session des Owners ist fuer diesen Download nicht erforderlich.
* Normale, vorhersagbare und wiederverwendbare Download-URLs reichen aus.
* Kurzlebige Tokens oder signierte Einmal-URLs sind nicht erforderlich.

### Browser-Downloads

* Browser-Downloads sind fuer eingeloggte Benutzer erlaubt.
* Ein Download ueber die normale oeffentliche IP des Servers ohne Login ist nicht zulaessig.

## Modellierungsentscheidung

Fuer dieses Feature wird ein neuer Node-Bundle eingefuehrt.

Wichtige Regel:

* Dauerhafte und kurzlebige Dateien werden beide ueber denselben Node-Bundle modelliert.

Das ist eine bewusste Ausnahme zur Architekturentscheidung, keine Node-Bundles fuer den transaktionalen Backend-Kern wie `support_session` oder `terminal_grant` einzusetzen.

## Minimale fachliche Felder

Der neue Node-Bundle muss fachlich mindestens folgende Informationen tragen:

* Datei-Lebensdauer: `persistent` oder `session`
* Dateireferenz
* Titel
* optionale Kurzbeschreibung fuer dauerhafte Dateien
* Owner-User
* Freigabestatus fuer dauerhafte Dateien
* Referenz auf die `support_session` fuer kurzlebige Dateien
* Erstellungs- und Aenderungszeitpunkt

## Audit-Anforderungen

Dateiaktionen sollen im Backend auditierbar sein.

Das betrifft mindestens:

* Upload
* Bearbeitung
* Ersetzung
* Freigabe-Aenderung
* Loeschung
* Download

Dabei sollen sowohl erfolgreiche als auch fehlgeschlagene Dateiaktionen auditierbar sein.

## Noch offene fachnahe Punkte fuer die nachgelagerte Ausarbeitung

Noch nicht festgezogen und spaeter zu klaeren sind insbesondere:

* exakter Zuschnitt der Download-Endpunkte relativ zu Backend- und Gateway-Origin
* konkrete API-Schnitte fuer Liste, Upload, Bearbeitung, Loeschung und Download
* finale sichtbare UI-Felder wie Groesse, Owner-Anzeige oder Zeitstempel
* konkrete Audit-Ereignistypen und Payload-Strukturen
* technischer Ausloeser des automatischen Cleanups nach Session-Ende

## Verweis auf Umsetzung

Die Ableitung in konkrete Backend-, UI-, Audit- und Cleanup-Arbeitspakete erfolgt in:

* `plans/10-file-management-and-console-downloads.md`
