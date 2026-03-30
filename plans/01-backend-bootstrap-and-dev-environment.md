# Teilplan 01 – Bootstrap und lokale Entwicklungsumgebung

## Ziel

Eine lauffaehige lokale Drupal-Entwicklungsumgebung im bestehenden Repo aufbauen, die den festgelegten Strukturvorgaben folgt und als Basis fuer alle weiteren Backend-Arbeiten dient.

## Voraussetzungen

* Architektur- und API-Spezifikation aus `spec/` ist bekannt.
* Dieser Teilplan darf ohne weitere Vorarbeiten begonnen werden.

## Verbindliche Strukturentscheidungen

* `composer.json` im Repo-Root
* `docroot/` als oeffentlicher Webroot
* `configurations/` als Config-Sync-Verzeichnis im Repo-Root
* `docker-compose.yml` fuer lokale Dienste
* persistenter Datenordner fuer die Datenbank als Host-Mount
* der ausfuehrbare Projektstand wird eingecheckt; dazu gehoeren bei diesem Projekt auch installierte Drupal-Artefakte und kuenftig kompilierte Frontend-Artefakte

## Konkrete Arbeitsschritte

1. Composer-basiertes Drupal-Projekt im Repo-Root anlegen oder aufsetzen.
2. Composer-Konfiguration so einstellen, dass Drupal nach `docroot/` installiert wird.
3. Config-Sync-Pfad auf `../configurations` bzw. repo-root-nah passend zur Drupal-Struktur ausrichten.
4. `docker-compose.yml` fuer mindestens folgende Services anlegen:
   * PHP/Drupal-App
   * Datenbank
5. Verzeichnis fuer persistente Datenbankdaten festlegen, z. B. `.docker/db-data/`, und in Compose mounten.
6. Basisdateien fuer lokale Umgebungsvariablen, Datenbankzugang und Bootstrapping anlegen.
7. Dokumentieren, wie ein Entwickler lokal installiert, startet und erste Drupal-Initialisierung ausfuehrt.
8. Git-Policy so ausrichten, dass das Repository nach dem Klonen einen ausfuehrbaren Stand enthaelt und Build-Artefakte, die fuer den Betrieb notwendig sind, nicht versehentlich ignoriert werden.

## Erwartete Artefakte

* `composer.json`
* `composer.lock`
* `vendor/`
* `docroot/`
* `configurations/`
* `docker-compose.yml`
* eventuell Dockerfile oder PHP-Service-Konfiguration
* lokale Beispielkonfigurationen wie `.env.example`, falls passend

## Validierung

* Composer-Installation laeuft lokal reproduzierbar.
* Drupal ist unter der lokalen Compose-Umgebung erreichbar.
* Datenbankdaten bleiben nach Container-Neustart erhalten.
* Config-Sync zeigt auf `configurations/`.
* Ein frischer Klon enthaelt bereits alle fuer die Ausfuehrung benoetigten eingecheckten Artefakte ausser bewusst lokalen Daten wie `.env` oder Datenbankinhalten.

## Risiken und offene Punkte

* genaue Wahl des PHP-Container-Setups
* Abgrenzung zwischen Commit-pflichtigen Dateien und lokalen Overrides
* Umgang mit Drupal-Dateirechten in gemounteten Verzeichnissen

## Uebergabe an Folgepakete

Nach Abschluss muessen folgende Informationen fuer andere Agenten dokumentiert sein:

* finale lokale Startbefehle
* genaue Verzeichnisstruktur
* relevante Composer-Packages und Drupal-Basisversion
* alle erzeugten Konfigurationsdateien
