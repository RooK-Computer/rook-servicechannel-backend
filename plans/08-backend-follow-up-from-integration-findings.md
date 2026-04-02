# Teilplan 08 - Folgearbeiten aus Integrationsbefunden

## Ziel

Die aus den ersten interaktiven Integrationstests abgeleiteten Backend-Folgearbeiten fuer Session-Lifecycle, Team-UI und Navigation als repo-lokalen Umsetzungsschnitt festziehen.

## Anlass und Quellen

Fuehrendes Quellartefakt ist:

* `spec/implementation/11-integrationsbefunde-und-folgearbeiten.md`

Zusammen mit:

* `spec/implementation/04-rook-backend-status.md`
* `spec/docs/architecture/servicechannel-concept.md`
* `spec/schemas/backend/02-agent-backend-session-catalog.md`
* `spec/openapi/02-agent-backend-rest.openapi.yaml`
* `spec/openapi/04-browser-gateway-websocket.openapi.yaml`

## Ausgangslage im Backend-Repository

Bereits vorhanden:

* Domain-Modul `rook_servicechannel_core`
* Console-, Client- und Gateway-API-Module
* erste Drupal-native Team-UI unter `/servicechannel/team`
* Kernel- und Contract-Tests fuer Domain- und API-Grundpfade

Der Integrationsstand zeigt aber weitere Arbeiten:

* Session-Regeln muessen gegen die neue Heartbeat-/Idle-Semantik nachgeschaerft werden.
* Die Team-UI ist noch Plain JavaScript statt React/TypeScript.
* Die Team-UI hat noch das alte Layout und noch keine Menueeinbindung.

## Fachliche Leitlinien aus der aktuellen Spec-Lage

Fuer dieses Backend-Repo sind aktuell insbesondere folgende Regeln verbindlich:

* Agent-Heartbeats halten die uebergeordnete Support-Session offen.
* Eine fehlende aktive Service-Bedienung beendet die Support-Session nicht.
* Browser-Idle beendet nicht automatisch die Browser-Terminal-Sitzung.
* Ein Browser-Disconnect beendet nicht automatisch die uebergeordnete Support-Session.
* Die Team-UI soll in React mit TypeScript weitergefuehrt werden.
* Die Team-UI soll in einem vertikalen Zwei-Block-Aufbau erscheinen:
  * Bedienblock oben
  * Terminal darunter vollbreit
  * Debug-Informationen hinter einem Info-Symbol
  * 4:3-Terminalbereich mit Hoehenbegrenzung gegen die verfuegbare Viewport-Hoehe unter Einbezug der Drupal-Oberflaeche

## Nicht Ziel dieses Arbeitspakets

Dieses Arbeitspaket plant nur die backendseitigen Konsequenzen.

Nicht Bestandteil dieses Plans:

* Root-Cause-Analyse im Browser-Terminal-Gateway fuer Idle-/Keepalive-Abbrueche
* Umsetzung gateway-interner Laufzeitregeln ausserhalb des Backend-Repositories
* allgemeine Spec-Pflege im `spec`-Repository ohne konkrete Backend-Folge

## Arbeitspakete

### 1. Lokale Planungs- und Statusartefakte nachziehen

Die Backend-Planserie und spaetere Statuspflege muessen die neue Spec-Lage sauber spiegeln.

Konkrete Schritte:

1. backend-lokale Plaene auf die neuen Integrationsbefunde abbilden
2. festhalten, dass derzeit kein neuer REST-Pfadbruch sichtbar ist
3. Lifecycle-, UI- und Navigationsfolgen als eigene Umsetzungslinien trennen

### 2. Session-Lifecycle serverseitig nachschaerfen

Die bestehende Session-Lebensdauer ist derzeit mehrfach ueber `expires_at` und 30-Sekunden-Logik im Code verteilt. Das muss gegen die aktuelle Spec-Lage konsistent gemacht werden.

Konkrete Schritte:

1. Heartbeat- und Grace-Period-Regeln zentralisieren statt in mehreren Services zu duplizieren
2. Rueckfall von `active` nach `open` als normalen Zustand abbilden
3. Cleanup- und Timeout-Pfade pruefen gegen:
   * Heartbeat-Ausfall schliesst die Session
   * fehlende Service-Aktivitaet schliesst die Session nicht
   * Browser-Disconnect schliesst die Support-Session nicht automatisch
4. Tests fuer Heartbeat-, Timeout- und ruhende Session-Zustaende erweitern

### 3. Backend-Grenze zum Gateway explizit halten

Ein Teil der Befunde betrifft die Schnittstelle zum Gateway, nicht zwingend die Backend-Implementierung selbst.

Konkrete Schritte:

1. bestehende Grant- und Session-Pruefungen gegen Disconnect-/Reconnect-Regeln querlesen
2. nur dort Backend-Code anpassen, wo die aktuelle Implementierung der neuen Spec-Semantik widerspricht
3. offene Folgearbeiten klar als gateway-abhaengig markieren

### 4. Menue- und Konfigurationsintegration fuer die Team-UI nachziehen

Die vorhandenen Routen sollen in Drupal sichtbar und reproduzierbar auffindbar werden.

Konkrete Schritte:

1. Team-UI in die Hauptnavigation einhaengen
2. Eintrag dort moeglichst weit oben platzieren
3. Team-UI-Konfiguration unter `Configuration/System` einhaengen
4. Rollen- und Sichtbarkeitsregeln in Tests abbilden

### 5. Team-UI auf React und TypeScript umstellen

Die bestehende Drupal-nahe UI soll technisch modernisiert werden, ohne den lauffaehigen Repo-Stand aufzugeben.

Konkrete Schritte:

1. React-/TypeScript-Zuschnitt fuer das vorhandene Modul festlegen
2. Build- und Commit-Strategie so waehlen, dass benoetigte Runtime-Artefakte im Repo verfuegbar bleiben
3. bestehende Plain-JS-Logik fuer PIN-Flow, Session-Status, Grant-Anforderung und Terminal-Flow migrieren
4. `drupalSettings`, Routen und Permissions beibehalten

### 6. Integrationslayout der Team-UI nachziehen

Die UI soll dem aus dem Integrationslauf abgeleiteten Zielbild folgen.

Konkrete Schritte:

1. Zweispalten-Layout auf vertikalen Zwei-Block-Aufbau umstellen
2. Debug-Informationen hinter ein klickbares Info-Symbol legen
3. Terminal vollbreit in einem 4:3-Rahmen anordnen
4. Terminalhoehe an verfuegbaren Viewport und Drupal-Chrome koppeln
5. sichtbare Fehler- und Statusfuehrung beim API- und Gateway-Flow beibehalten

### 7. README, Status und Tests synchronisieren

Nach der Umsetzung muessen Dokumentation und Testabdeckung den realen Stand widerspiegeln.

Konkrete Schritte:

1. Kernel-Tests fuer Menueeinbindung und Lifecycle-Nachschaerfung erweitern
2. README bei geaenderter Frontend-Build- oder Bedienlogik aktualisieren
3. Status- und Teilplandokumente auf den tatsaechlich erreichten Stand bringen

## Empfohlene Reihenfolge

1. Session-Lifecycle zuerst nachschaerfen
2. Gateway-Abgrenzung parallel sauber festhalten
3. Menueintegration als kleiner, isolierter UI-Schritt vorziehen
4. Danach React-/TypeScript-Migration und Layout-Refresh gemeinsam angehen
5. Zum Schluss Tests, README und Statuspflege abschliessen

## Erwartete Artefakte

* aktualisierte Backend-Teilplaene
* nachgeschaerfte Session-Lifecycle-Logik in Core-/API-nahen Services
* zusaetzliche Kernel-Tests fuer Lifecycle und Menueintegration
* Menue-Links fuer Team-UI und Team-UI-Konfiguration
* React-/TypeScript-basierte Team-UI mit aktualisiertem Integrationslayout
* fortgeschriebene README- und Statusdokumente

## Risiken und offene Punkte

* Die bestehende `expires_at`-Modellierung koennte fuer die endgueltige Lifecycle-Semantik zu grob sein.
* Die genaue Build-Strategie fuer React/TypeScript muss mit dem Repo-Grundsatz eines eingecheckten lauffaehigen Zustands vereinbar bleiben.
* Weitere Gateway- oder Spec-Nachschaerfungen koennen Folgeanpassungen an Grant- oder Sessionregeln ausloesen.

## Uebergabe an Folgepakete

Nach diesem Plan sollen Folgeagenten klar sehen:

* welche Arbeiten direkt im Backend-Repo liegen
* welche Punkte gateway-abhaengig bleiben
* welche Reihenfolge fuer Lifecycle, Navigation und Team-UI derzeit empfohlen ist
