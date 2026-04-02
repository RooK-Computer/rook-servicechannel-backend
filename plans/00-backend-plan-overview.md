# Backend-Implementierungsplan – Gesamtuebersicht

## Ziel

Dieses Dokument zerlegt die Backend-Umsetzung in fortsetzbare Teilplaene. Es dient als Einstiegspunkt fuer andere Agenten und beschreibt Reihenfolge, Abhaengigkeiten und Uebergaben.

## Verbindliche Repo- und Infrastrukturentscheidungen

* `composer.json` liegt im Repo-Root.
* Drupal-Docroot ist `docroot/`.
* Drupal-Config-Sync liegt im Repo-Root unter `configurations/`.
* Die lokale Entwicklung verwendet `docker-compose.yml`.
* PHP und Datenbank laufen lokal in Docker.
* Die Datenbank speichert ihre Daten in ein gemountetes Host-Verzeichnis fuer Persistenz.

## Arbeitspakete

1. Bootstrap und lokale Entwicklungsumgebung
   * siehe `01-backend-bootstrap-and-dev-environment.md`

2. Domain-Modell und Drupal-Architektur
   * siehe `02-backend-domain-model-and-drupal-architecture.md`

3. Agent-API und Session-Lifecycle
   * siehe `03-agent-api-and-session-lifecycle.md`

4. Web-API und Zugriffskontrolle
   * siehe `04-web-api-and-access-control.md`
   * einschliesslich Anlage der Drupal-Rolle `Service`

5. Gateway-Validierung und Laufzeitjobs
   * siehe `05-gateway-validation-and-runtime-jobs.md`

6. Tests, Delivery und Statuspflege
   * siehe `06-testing-delivery-and-status-maintenance.md`

7. Team-Frontend und Browser-Terminal
   * siehe `07-team-frontend-and-browser-terminal.md`

8. Folgearbeiten aus Integrationsbefunden
   * siehe `08-backend-follow-up-from-integration-findings.md`

## Empfohlene Reihenfolge

1. Bootstrap abschliessen
2. Domain-Modell festziehen
3. Agent-API umsetzen
4. Web-API umsetzen
5. Gateway-Validierung und Laufzeitjobs ergaenzen
6. Test- und Statuspflege vervollstaendigen
7. Team-Frontend zusammen mit einem Gateway-MVP integrieren
8. Folgearbeiten aus Integrationsbefunden entlang von Lifecycle, Navigation und Team-UI umsetzen

## Agenten-Uebergaben

* Nach jedem Arbeitspaket sind betroffene Dateien, offene Entscheidungen und nicht geloesste Risiken direkt im jeweiligen Plan nachzuziehen.
* Ein Agent darf ein Folgepaket nur starten, wenn die unter `Voraussetzungen` im Teilplan genannten Ergebnisse vorliegen.
* Wenn bei der Umsetzung Spezifikationsluecken sichtbar werden, muessen sie sowohl im Teilplan als auch in `spec/implementation/04-rook-backend-status.md` gespiegelt werden.

## Globale offene Punkte

* Fehlercode-Katalog fuer alle Backend-APIs
* technische Umsetzung des VPN-basierten Vertrauensmodells
* konkrete Session-, Grant- und Audit-Felder jenseits des Minimalmodells
* konkreter Zuschnitt des Drupal-nahen Team-Frontends und seiner Gateway-Anbindung

## Erwartete Hauptartefakte

* Drupal-/Composer-Projekt im Repo-Root
* `docker-compose.yml`
* `docroot/`
* `configurations/`
* Custom-Module fuer die Backend-Domaene
* API-Endpunkte fuer Agent, Client und Gateway
* Drupal-nahes Team-Frontend inklusive Browser-Terminal-Integration
* Tests und aktualisierte Statusdokumente
