# Teilplan 05 – Gateway-Validierung und Laufzeitjobs

## Ziel

Die Gateway-seitige Token-Validierung sowie die noetigen Laufzeitmechanismen fuer Reconnect, Cleanup und Revocation bereitstellen.

## Voraussetzungen

* Teilplan 02 ist abgeschlossen.
* Teilplan 04 liefert Terminal-Grants.

## Relevante Verträge

* `spec/openapi/06-backend-gateway-terminal-grant.openapi.yaml`

## Umsetzungsumfang

* `POST /api/gateway/1/validateToken`
* Laufzeitlogik fuer Grant-Einloesung, Reconnect und Cleanup

## Konkrete Arbeitsschritte

1. Gateway-Endpunkt fuer Grant-Pruefung und Einloesung implementieren.
2. Grant-Bindung an Benutzer, Session und Konsolen-IP serverseitig validieren.
3. Reconnect-Fenster von 30 Sekunden fachlich und technisch abbilden.
4. Regeln fuer einmalige Nutzung ausserhalb des Reconnect-Fensters implementieren.
5. Revocation-Verhalten festlegen und in Services kapseln.
6. Cleanup fuer abgelaufene Sessions, PINs und Grants planen und umsetzen.
7. Falls notwendig geplante Jobs, Cron-Integration oder alternative Trigger definieren.

## Erwartete Artefakte

* Gateway-REST-Route
* Validierungsservice fuer Terminal-Grants
* Laufzeitjobs oder Trigger fuer Ablauf und Cleanup
* Tests fuer Grant-Einloesung und Reconnect-Verhalten

## Validierung

* Gueltige Tokens liefern mindestens die erwartete Konsolen-IP.
* Ungueltige oder bereits eingelöste Tokens werden korrekt abgewiesen.
* Reconnect innerhalb des erlaubten Fensters funktioniert.
* Cleanup entfernt oder sperrt abgelaufene Artefakte verlässlich.

## Risiken und offene Punkte

* genauer Mechanismus fuer Job-Ausfuehrung in Drupal
* endgueltige Revocation-Semantik
* Fehlerklassifikation bei Gateway- oder Backend-Ausfaellen

## Umgesetztes Initialergebnis

Folgende Artefakte wurden fuer die erste Gateway- und Runtime-Umsetzung angelegt:

* `docroot/modules/custom/rook_servicechannel_gateway_api/`
* Gateway-Route `POST /api/gateway/1/validateToken`
* Validierungsservice fuer Token-Pruefung, Redeem und Reconnect innerhalb des Grace-Fensters
* Runtime-Maintenance fuer Grant-Expiry und das Aufraeumen alter geschlossener Sessions
* Kernel- und OpenAPI-Contract-Tests fuer Gateway-Validierung und Maintenance

Wichtige Umsetzungsentscheidung:

* Ein erstmalig validierter Grant wird beim ersten erfolgreichen `validateToken` direkt auf `redeemed` gesetzt.
* Ein bereits eingelöster Grant darf innerhalb des bestehenden `reconnect_valid_until`-Fensters erneut validiert werden.
* Abgelaufene Grants werden auf `expired` gesetzt, bereits eingelöste Grants ausserhalb des Reconnect-Fensters werden abgewiesen und widerrufen.
* Geschlossene Sessions werden nach einer Stunde geloescht; zugehoerige Teilnehmer- und Grant-Daten werden dabei technisch mit aufgeraeumt.

## Uebergabe an Folgepakete

* dokumentierte Reconnect- und Revocation-Regeln
* bekannte Grenzen der Job-Ausfuehrung im lokalen Setup
* Liste aller Laufzeitprozesse mit Trigger und Nebenwirkungen
