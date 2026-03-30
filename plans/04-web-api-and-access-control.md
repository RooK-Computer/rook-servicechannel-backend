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

## Konkrete Arbeitsschritte

1. Zugriff auf die Endpunkte auf authentisierte Drupal-Benutzer mit Rolle `Service` begrenzen.
2. `pinlookup` so umsetzen, dass eine Session per PIN gefunden und fuer den Mitarbeiter gekoppelt oder reserviert werden kann.
3. `sessionstatus` auf die aus dem Frontend sichtbare Session-Sicht abbilden.
4. `requestshell` so umsetzen, dass ein opaques Terminal-Token erzeugt wird, das Benutzer und Session bindet.
5. Regeln fuer parallele Sessions pro Mitarbeiter und mehrere Mitarbeiter pro Session sauber abbilden.
6. Unklare Request-/Response-Details explizit dokumentieren und im Code kapseln.

## Erwartete Artefakte

* Client-REST-Routen
* Zugriffskontrolllogik auf Drupal-Rollenbasis
* Services fuer Session-Kopplung und Grant-Erzeugung
* Tests fuer Rollenpruefung, PIN-Kopplung und Token-Ausgabe

## Validierung

* Nutzer ohne Rolle `Service` erhalten keinen Zugriff.
* Ein gueltiger PIN koppelt eine Session erfolgreich.
* `requestshell` liefert ein Token fuer eine passende Session und einen passenden Benutzer.

## Risiken und offene Punkte

* unvollstaendige Request-/Response-Details in der Spezifikation
* Abgrenzung zwischen impliziter Kopplung und expliziter Persistenz dieser Kopplung
* Verhalten bei mehrfacher Token-Anforderung pro Benutzer und Session

## Uebergabe an Folgepakete

* dokumentiertes Mapping zwischen Drupal-Usern, Sessions und Grants
* bekannte offene API-Detailfragen
* sichtbare Unterschiede zwischen Frontend-Sicht und internem Domain-Modell
