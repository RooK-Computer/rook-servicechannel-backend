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
4. Status-Endpunkt auf bestehende Session und PIN abbilden.
5. Heartbeat-Logik umsetzen:
   * Frequenzannahme 10 Sekunden
   * Grace Period 3 Heartbeats
   * Timeout 30 Sekunden
   * IP-Abgleich und moegliche IP-Aktualisierung
6. Session-Ende inklusive PIN-Ungueltigmachung implementieren.
7. Audit-relevante Minimaldaten mitschreiben.
8. Fehlerfaelle dokumentieren, auch wenn der Fehlercode-Katalog noch unvollstaendig ist.

## Erwartete Artefakte

* Agent-REST-Routen
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

## Uebergabe an Folgepakete

* dokumentierter Session-Lifecycle
* bekannte Limitierungen des Agent-Vertrauensmodells
* alle Request-/Response-Abweichungen zur Spezifikation
