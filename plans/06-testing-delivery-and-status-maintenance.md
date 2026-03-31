# Teilplan 06 – Tests, Delivery und Statuspflege

## Ziel

Die Backend-Umsetzung absichern, gegen die Spezifikation pruefen und die zugehoerigen Statusartefakte aktuell halten.

## Voraussetzungen

* Teilplan 01 bis 05 sind zumindest in einem ersten Slice umgesetzt.

## Konkrete Arbeitsschritte

1. Vorhandene Testwerkzeuge des gewaehren Drupal-/PHP-Stacks identifizieren und verbindlich verwenden.
2. Tests fuer Domain-Services, REST-Endpunkte und zentrale Lifecycle-Szenarien aufbauen.
3. OpenAPI-Drafts mit der realen Implementierung abgleichen und Abweichungen dokumentieren.
4. Lokale Developer-Workflows fuer Installation, Tests und Reset dokumentieren.
5. `spec/implementation/04-rook-backend-status.md` fortlaufend aktualisieren:
   * Status
   * aktueller Stand
   * naechste Schritte
   * erkennbare Risiken oder Blocker
6. Pruefen, ob weitere Spezifikationsdokumente bei belastbaren Implementierungserkenntnissen angepasst werden muessen.

## Erwartete Artefakte

* Test-Suites
* dokumentierte lokale Workflows
* aktualisierte Statusdokumente
* Nachweis ueber umgesetzte oder bewusst offene API-Vertragsstellen

## Validierung

* Kern-Workflows sind automatisiert pruefbar.
* Die wichtigsten API-Endpunkte sind gegen Regression abgesichert.
* Status- und Planungsdokumente spiegeln den realen Stand.

## Risiken und offene Punkte

* moeglicher Aufwand fuer realistische Integrationsumgebungen
* Spezifikationsabweichungen, die erst waehrend der Implementierung sichtbar werden

## Umgesetztes Initialergebnis

Teilplan 06 wurde als Konsolidierungsschritt fuer den bisherigen Backend-Stand initial umgesetzt.

Ergaenzt wurden:

* ein eigener Kernel-Regressionstest fuer gemeinsam genutzte Core-Domain-Services in `rook_servicechannel_core`
* ein dokumentierter, funktionierender Drupal-PHPUnit-Entrypoint ueber `docroot/core/phpunit.xml.dist` inklusive benoetigter `SIMPLETEST_*`-Umgebungsvariablen
* README-Workflows fuer:
  * vollstaendige Custom-Module-Testlaeufe
  * gezielte Kernel-Testausfuehrung
  * manuelle API-Pruefung mit konfigurierbarer Basis-URL
  * lokalen Reset aus dem Config-Export
* aktualisierte Statusartefakte zum realen Test- und Delivery-Stand

Bekannter Stand der Absicherung:

* Kernel- und OpenAPI-Contract-Tests decken die Console-, Client- und Gateway-Endpunkte ab.
* Der Domain-Kern prueft zentrale Session-, Teilnehmer- und Grant-Helfer jetzt auch direkt.
* Der lokale Testweg ist dokumentiert, ohne eine feste Entwicklungs-URL im Repository vorauszusetzen.

Bewusst offen bleiben:

* Fehlercode-Katalog und Revocation-Feinschliff ueber alle APIs
* echte Ende-zu-Ende-Validierung gegen eine laufende Gateway-Komponente
* weitergehende Integrationsumgebungen jenseits des lokalen Docker-/Drupal-Stacks

## Uebergabe

* offene Spezifikationsfragen sauber benennen
* letzte bekannte Testabdeckung und Luecken dokumentieren
* alle Statusdateien auf den aktuellen Projektstand bringen
