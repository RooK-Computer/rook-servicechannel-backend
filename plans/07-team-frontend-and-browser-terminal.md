# Teilplan 07 – Team-Frontend und Browser-Terminal

## Ziel

Die vom RooK-Team genutzte Browser-Oberflaeche im bestehenden Backend-Repository bereitstellen: Login-gebundene PIN-Eingabe, Session-Sicht und ein Browser-Terminal, das ueber einen separaten Gateway-Dienst mit der Konsole verbunden wird.

## Voraussetzungen

* Teilplan 04 ist umgesetzt: Web-API und Rolle `Service` sind vorhanden.
* Teilplan 05 ist umgesetzt: Terminal-Grants und Gateway-Validierung existieren backendseitig.
* Ein erster Gateway-MVP fuer Browser-WebSocket und Konsolenanbindung liegt vor oder wird parallel abgestimmt.

## Architekturelle Leitentscheidung

Das Team-Frontend wird **nicht** als separates Produkt ausserhalb dieses Repositories geplant.

Stattdessen gilt:

* Das UI lebt Drupal-nah in diesem Repository.
* Drupal bleibt fuer Login, Session, Routing und Auslieferung der Team-Oberflaeche zustaendig.
* Das Terminal-Gateway bleibt ein separater Dienst ausserhalb von Drupal.
* Das Browser-Terminal im Frontend spricht den Gateway per WebSocket auf Basis des vom Backend ausgestellten Terminal-Grants an.

## Relevante Konzeptquellen

* `spec/docs/architecture/servicechannel-concept.md`
* `spec/plans/03-api-plan-web-backend-rest.md`
* `spec/plans/04-api-plan-browser-gateway-websocket.md`
* `spec/openapi/03-web-backend-rest.openapi.yaml`
* `spec/openapi/04-browser-gateway-websocket.openapi.yaml`

## Umsetzungsumfang

* Drupal-nahe Team-Oberflaeche fuer authentisierte Service-Mitarbeiter
* PIN-Eingabe und Session-Kopplung ueber die bestehende Client-API
* Session-Ansicht mit relevanten Statusinformationen
* Browser-Terminal mit `xterm.js`
* WebSocket-Anbindung an den separaten Gateway-MVP
* Fehler- und Statusdarstellung fuer API- und Terminal-Verbindung

Nicht Teil dieses Arbeitspakets:

* die eigentliche Gateway-Implementierung selbst
* die SSH-Anbindung zur Konsole im Gateway
* tiefere Admin- oder Reporting-Oberflaechen ausserhalb des Support-Flows

## Konkrete Arbeitsschritte

1. Frontend-Zuschnitt im Drupal-Repo festlegen.
   * Entscheiden, ob die UI als Custom-Modul mit Asset-Build, als Theme-Anwendung oder als hybride Drupal/React-Einbettung umgesetzt wird.
   * Routing, Berechtigungen und Auslieferung fuer authentisierte Service-User definieren.

2. Team-Frontend fuer den Session-Flow aufbauen.
   * Login-gebundene Startseite fuer Support-Mitarbeiter bereitstellen.
   * PIN-Eingabe und Session-Kopplung ueber `pinlookup` integrieren.
   * Session-Status ueber `sessionstatus` sichtbar machen.

3. Terminal-Request-Flow in die UI integrieren.
   * `requestshell` anbinden.
   * den Grant nur im unmittelbaren Terminal-Startfluss verwenden.
   * Regeln fuer erneute Grant-Anforderung, Reconnect und Fehlerdarstellung mit dem Backend-Stand abstimmen.

4. Browser-Terminal integrieren.
   * `xterm.js` einbetten.
   * WebSocket-Handshake gegen den Gateway-MVP aufbauen.
   * Terminal-I/O, Resize und Verbindungsstatus abbilden.

5. Konfiguration und Entwicklungsmodus sauber kapseln.
   * Gateway-Basis-URL konfigurierbar halten.
   * keine feste lokale Entwicklungsadresse in Build, Tests oder Runtime voraussetzen.
   * sinnvolle Fallbacks fuer lokalen Mock- oder Stub-Betrieb nur dann einbauen, wenn sie den echten Flow nicht verfremden.

6. Tests und Dokumentation ergaenzen.
   * vorhandene Frontend- und Backend-Testwerkzeuge nutzen, keine unnoetige Parallel-Toolkette einfuehren.
   * UI-nahe Smoke- oder Integrationstests fuer PIN-Flow und Terminal-Startpfad vorsehen.
   * README, Teilplaene und Statusdokumente nachziehen.

## Erwartete Artefakte

* Drupal-nahes Frontend-Modul oder Theme mit Team-Oberflaeche
* UI fuer PIN-Eingabe, Session-Ansicht und Terminal-Start
* `xterm.js`-basierte Terminal-Komponente
* konfigurierbare Gateway-Anbindung
* Dokumentation fuer lokalen Start und Zusammenspiel mit Gateway-MVP

## Validierung

* authentisierte Service-User koennen die Team-Oberflaeche aufrufen
* eine gueltige PIN koppelt erfolgreich eine Session
* `requestshell` liefert einen Terminal-Grant und startet den Terminal-Flow
* das Browser-Terminal verbindet sich gegen einen kompatiblen Gateway-MVP
* Fehler bei Backend- oder Gateway-Kommunikation werden fuer den Nutzer klar sichtbar

## Risiken und offene Punkte

* finaler Frontend-Zuschnitt innerhalb von Drupal
* genauer Umfang des Gateway-MVP fuer den ersten durchgehenden Terminal-Flow
* Reconnect-, Fehler- und Close-Code-Semantik zwischen Frontend und Gateway
* moeglicher Build- und Tooling-Aufwand fuer eingebettete React-/xterm.js-Komponenten

## Empfohlene Reihenfolge fuer die Umsetzung

Die Umsetzung sollte **nicht** mit dem kompletten UI beginnen, bevor ein kleiner Gateway-MVP fuer den Terminal-Handshake feststeht.

Empfohlen ist daher:

1. zuerst ein Gateway-MVP mit minimalem WebSocket-Handshake und Grant-Validierung stabilisieren
2. danach unmittelbar das Drupal-nahe Team-Frontend gegen genau diesen MVP aufbauen
3. beide Komponenten anschliessend gemeinsam an Fehler-, Reconnect- und Terminaldetails schaerfen

## Uebergabe an Folgepakete

* finaler Frontend-Zuschnitt im Repo
* abgestimmte Gateway-URL- und Handshake-Konfiguration
* bekannte Restluecken bei Fehlerformat, Close-Codes und Reconnect
