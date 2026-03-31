# Teilplan 03 – Agent-API und Session-Lifecycle

## Ziel

Die Agent-seitigen REST-Endpunkte und den serverseitigen Session-Lifecycle gemaess Spezifikation implementieren.

## Voraussetzungen

* Teilplan 01 und 02 sind abgeschlossen.
* Session- und PIN-Modell ist im Backend vorhanden.

## Relevante Verträge

* `spec/openapi/02-agent-backend-rest.openapi.yaml`
* `spec/schemas/backend/02-agent-backend-session-catalog.md`

## Umsetzungsumfang

* `POST /api/console/1/beginsession`
* `POST /api/console/1/status`
* `POST /api/console/1/ping`
* `POST /api/console/1/endsession`

## Konkrete Arbeitsschritte

1. REST-Routen und Controller/Responder fuer die vier Agent-Endpunkte anlegen.
2. Session-Start so umsetzen, dass ein kurzlebiger 4-stelliger PIN erzeugt und zurueckgegeben wird.
3. Vertrauensmodell fuer Agent-Requests gegen die aktive VPN-Verbindung technisch anbinden oder vorlaeufig sauber kapseln.
4. Eine produktive IP-Zugriffsbeschraenkung in ein separates, optional installierbares Modul auslagern, damit die Agent-API lokal ohne diese Schranke testbar bleibt.
5. Status-Endpunkt auf bestehende Session und PIN abbilden.
6. Heartbeat-Logik umsetzen:
   * Frequenzannahme 10 Sekunden
   * Grace Period 3 Heartbeats
   * Timeout 30 Sekunden
   * IP-Abgleich und moegliche IP-Aktualisierung
7. Session-Ende inklusive PIN-Ungueltigmachung implementieren.
8. Audit-relevante Minimaldaten mitschreiben.
9. Fehlerfaelle dokumentieren, auch wenn der Fehlercode-Katalog noch unvollstaendig ist.

## Erwartete Artefakte

* Agent-REST-Routen
* optional installierbares Modul fuer produktive Quell-IP-Beschraenkung
* Request-/Response-Mapping
* Session-Service-Methoden fuer Start, Status, Ping und Ende
* Tests fuer Kernpfade und Timeout-Verhalten

## Validierung

* Agent kann Session starten und PIN erhalten.
* Heartbeats halten die Session offen.
* Ausbleibende Heartbeats fuehren zur Schliessung nach den definierten Regeln.
* Endsession invalidiert die Session verlässlich.

## Risiken und offene Punkte

* echte Durchsetzung des VPN-Kontexts im lokalen Development
* Verhalten bei doppelten `beginsession`-Requests
* finaler Fehlercode-Katalog

## Umgesetztes Initialergebnis

Folgende Artefakte wurden fuer die erste Agent-API-Umsetzung angelegt:

* `docroot/modules/custom/rook_servicechannel_console_api/`
* `docroot/modules/custom/rook_servicechannel_console_ip_guard/`
* versionierte Routen fuer `beginsession`, `status`, `ping` und `endsession`
* Lifecycle-Service fuer PIN-Vergabe, Heartbeat-Timeout und "latest start wins"
* Kernel-Tests fuer Kernpfade, das optionale Guard-Modul und die OpenAPI-basierte Contract-Pruefung

Wichtige Zuschnittsentscheidung:

* Die Agent-API lebt in `rook_servicechannel_console_api`.
* Die produktive Einschraenkung auf erlaubte Quell-IP-Adressen lebt separat in `rook_servicechannel_console_ip_guard`.
* Das Guard-Modul kann fuer lokale Entwicklung deaktiviert oder deinstalliert werden, ohne die API-Endpunkte selbst zu entfernen.

## Uebergabe an Folgepakete

* dokumentierter Session-Lifecycle
* bekannte Limitierungen des Agent-Vertrauensmodells
* alle Request-/Response-Abweichungen zur Spezifikation
