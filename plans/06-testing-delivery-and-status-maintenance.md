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

## Uebergabe

* offene Spezifikationsfragen sauber benennen
* letzte bekannte Testabdeckung und Luecken dokumentieren
* alle Statusdateien auf den aktuellen Projektstand bringen
