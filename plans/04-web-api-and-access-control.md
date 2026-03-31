# Teilplan 04 – Web-API und Zugriffskontrolle

## Ziel

Die vom RooK-Team verwendeten REST-Endpunkte samt Rollenpruefung, Session-Kopplung und Grant-Erzeugung implementieren.

## Voraussetzungen

* Teilplan 01 und 02 sind abgeschlossen.
* Teilplan 03 sollte mindestens so weit stehen, dass aktive Sessions und PINs vorliegen.

## Relevante Verträge

* `spec/openapi/03-web-backend-rest.openapi.yaml`
* `spec/schemas/backend/03-web-backend-session-catalog.md`

## Umsetzungsumfang

* `POST /api/client/1/pinlookup`
* `POST /api/client/1/sessionstatus`
* `POST /api/client/1/requestshell`
* Drupal-Rolle `Service` fuer berechtigte Servicemitarbeiter

## Konkrete Arbeitsschritte

1. Drupal-Rolle `Service` als Teil des Backends explizit anlegen.
2. Initiale Permissions fuer diese Rolle definieren und ueber Install-/Update-Pfade des Projekts reproduzierbar machen.
3. Zugriff auf die Endpunkte auf authentisierte Drupal-Benutzer mit Rolle `Service` begrenzen.
4. `pinlookup` so umsetzen, dass eine Session per PIN gefunden und fuer den Mitarbeiter gekoppelt oder reserviert werden kann.
5. `sessionstatus` auf die aus dem Frontend sichtbare Session-Sicht abbilden.
6. `requestshell` so umsetzen, dass ein opaques Terminal-Token erzeugt wird, das Benutzer und Session bindet.
7. Regeln fuer parallele Sessions pro Mitarbeiter und mehrere Mitarbeiter pro Session sauber abbilden.
8. Die Request-Modelle fuer `pinlookup`, `sessionstatus` und `requestshell` auf explizite Session-/PIN-Daten festziehen.
9. Unklare Request-/Response-Details explizit dokumentieren und im Code kapseln.
10. Bestehende HTTP-Checks und lokale Validierungshinweise auf konfigurierbare Basis-URLs umstellen.

## Erwartete Artefakte

* Client-REST-Routen
* Installations- oder Update-Code fuer die Rolle `Service`
* Zugriffskontrolllogik auf Drupal-Rollenbasis
* Services fuer Session-Kopplung und Grant-Erzeugung
* Tests fuer Rollenpruefung, PIN-Kopplung, Token-Ausgabe und Contract-Validierung

## Validierung

* Die Rolle `Service` wird im System reproduzierbar angelegt.
* Nutzer ohne Rolle `Service` erhalten keinen Zugriff.
* Ein gueltiger PIN koppelt eine Session erfolgreich.
* `requestshell` liefert ein Token fuer eine passende Session und einen passenden Benutzer.
* HTTP-Checks arbeiten ohne fest verdrahteten lokalen Port.

## Risiken und offene Punkte

* unvollstaendige Request-/Response-Details in der Spezifikation
* Abgrenzung zwischen impliziter Kopplung und expliziter Persistenz dieser Kopplung
* Verhalten bei mehrfacher Token-Anforderung pro Benutzer und Session

## Umgesetztes Initialergebnis

Folgende Artefakte wurden fuer die erste Web-API-Umsetzung angelegt:

* `docroot/modules/custom/rook_servicechannel_client_api/`
* reproduzierbare Rolle `Service` inklusive Permission `access rook client api`
* Routen fuer `pinlookup`, `sessionstatus` und `requestshell`
* Service-Schicht fuer PIN-Kopplung, Frontend-Status und Grant-Ausgabe
* Kernel- und OpenAPI-Contract-Tests fuer die Client-API
* umgestellte HTTP-Validierungshinweise ohne festen lokalen Port

Wichtige Umsetzungsentscheidung:

* Alle drei Client-Endpunkte arbeiten in der ersten technischen Umsetzung mit expliziten `pin`-Feldern im Request.
* Die Session-Kopplung wird serverseitig in `rook_support_session_participant` persistiert.
* `requestshell` bindet den aktuellen Service-User und die explizit adressierte Session an einen neuen Terminal-Grant.

## Uebergabe an Folgepakete

* dokumentiert, wo und wie die Rolle `Service` angelegt wird
* dokumentiertes Mapping zwischen Drupal-Usern, Sessions und Grants
* bekannte offene API-Detailfragen
* sichtbare Unterschiede zwischen Frontend-Sicht und internem Domain-Modell
* dokumentiert, wie lokale HTTP-Pruefungen konfigurierbar bleiben
