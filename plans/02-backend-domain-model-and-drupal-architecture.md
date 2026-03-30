# Teilplan 02 – Domain-Modell und Drupal-Architektur

## Ziel

Die interne Backend-Architektur und das fachliche Modell so festlegen, dass Session-Lifecycle, PIN-Verwaltung, Grants, Audit und API-Logik konsistent umgesetzt werden koennen.

## Voraussetzungen

* Teilplan 01 ist abgeschlossen.
* Lokale Drupal-Entwicklungsumgebung laeuft.

## Fachliche Kernobjekte

* Support-Session
* PIN-Zuordnung
* Terminal-Grant
* Audit-Ereignis
* optional zusaetzliche interne Hilfsobjekte fuer Timeout- und Kopplungslogik

## Konkrete Arbeitsschritte

1. Entscheiden, welche Teile als Drupal-Entities, Konfiguration oder reiner Service modelliert werden.
2. Session-Zustandsmodell festziehen:
   * `open`
   * `active`
   * `closed`
3. Minimale Pflichtfelder technisch definieren:
   * `status`
   * `pin`
   * `ipAddress`
4. Weitere benoetigte Felder fuer Lifecycle, Team-Kopplung, Grant-Validierung und Audit identifizieren.
5. Modulgrenzen festlegen, z. B.:
   * Domaenenmodul fuer Sessions und Grants
   * API-Modul fuer REST-Routen
   * Infrastrukturmodul fuer Cleanup oder Scheduled Jobs
6. Regeln fuer Heartbeat, Timeout, PIN-Gueltigkeit, Cleanup und Revocation in Services abbilden.
7. Spezifikationsluecken dokumentieren, statt sie stillschweigend zu ueberspielen.

## Erwartete Artefakte

* Architekturentscheidung fuer Custom-Module
* Entity-/Schema-Definitionen
* Service-Klassen fuer Session- und Grant-Logik
* dokumentierte Zustandsuebergaenge

## Validierung

* Das Modell deckt alle drei externen APIs ab.
* Jeder API-Endpunkt kann eindeutig auf Domain-Services und persistente Daten abgebildet werden.
* Timeout- und Cleanup-Regeln lassen sich mit dem Modell ausdruecken.

## Risiken und offene Punkte

* fehlende Detailfestlegungen bei Fehlercodes und Teilen der Request-/Response-Modelle
* technische Auspraegung des VPN-Vertrauensmodells
* moegliche Konflikte zwischen Drupal-Konventionen und sehr transaktionaler Session-Logik

## Uebergabe an Folgepakete

Nach Abschluss muessen andere Agenten wissen:

* welche Module es gibt
* welche Datenobjekte persistiert werden
* ueber welche Services Session-, Grant- und Audit-Logik laufen
* welche offenen Spezifikationsfragen noch nicht entschieden sind
