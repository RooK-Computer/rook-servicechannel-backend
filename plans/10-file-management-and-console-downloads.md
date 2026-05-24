# Teilplan 10 – Dateiverwaltung und Konsolen-Downloads

## Ziel

Die fachlich bereits beschriebene Dateiverwaltung in konkrete Umsetzungsarbeit fuer Backend, Drupal-Modell, Service-UI, Audit und Cleanup zerlegen.

## Status

Umsetzungsnaher Teilplan auf Basis der allgemeinen Feature-Spezifikation.

## Fachliche Grundlage

Die allgemeine Featurebeschreibung und die verbindlichen Produktregeln liegen in:

* `feature-specs/01-file-management-and-console-downloads.md`

Dieser Teilplan wiederholt die Fachspezifikation nicht vollstaendig, sondern leitet daraus die benoetigten Umsetzungsflaechen ab.

## Voraussetzungen

* Teilplan 04 ist umgesetzt: Web-API und Rolle `Service` existieren bereits.
* Teilplan 07 ist umgesetzt: die Team-UI mit Terminal und konfigurierbarer Gateway-Basis-URL existiert bereits.
* Die Kernobjekte `support_session`, `terminal_grant`, Teilnehmer-Kopplung und Audit-Log sind vorhanden.

## Architekturelle Leitplanken fuer die Umsetzung

Aus der Fachspezifikation folgen fuer die technische Ausarbeitung mindestens diese nicht mehr offenen Leitplanken:

* es wird ein neuer Node-Bundle eingefuehrt
* dauerhafte und kurzlebige Dateien verwenden denselben Bundle
* die Rolle `Service` bleibt die fachliche Zugangsschwelle
* Konsolen-Downloads folgen dem VPN-Vertrauensmodell ohne zusaetzliche Signed-URL- oder Token-Mechanik
* Browser-Downloads bleiben login-pflichtig
* die Service-UI verwendet eine gemeinsame Dateiliste mit Kennzeichnung statt getrennter Bereiche
* kurzlebige Dateien sind owner-exklusiv, nicht freigebbar und an `support_session` gebunden

## Backend-Flaechen, die spaeter aus dieser Spezifikation abgeleitet werden muessen

1. **Node-Bundle und Storage**
   * Bundle-Definition
   * Feldmodell fuer Datei, Titel, Beschreibung, Lifetime, Ownership, Sharing und Session-Referenz
   * Loesch- und Cleanup-Verhalten fuer kurzlebige Dateien

2. **Zugriffslogik**
   * Owner-Rechte auf eigene dauerhafte Dateien
   * read-only-Zugriff auf global freigegebene Dateien anderer User
   * exklusive Sichtbarkeit kurzlebiger Session-Dateien fuer ihren Uploader
   * eingeloggter Browser-Download versus VPN-basierter Konsolen-Download

3. **Service-UI**
   * gemeinsame Dateiliste mit Suchschlitz, Badges und Upload fuer kurzlebige Dateien
   * Erzeugung des nicht-destruktiven `curl`-Befehls
   * Nutzung der vorhandenen konfigurierbaren Basis-URL

4. **Eigene Dateiverwaltungsseite**
   * Listen-, Upload-, Bearbeitungs-, Ersetzungs- und Loesch-Flows
   * read-only-Darstellung global freigegebener Dateien anderer User

5. **Auditierung**
   * Upload, Bearbeitung, Ersetzung, Freigabe-Aenderung, Loeschung und Download muessen auditiert werden
   * sowohl erfolgreiche als auch fehlgeschlagene Dateiaktionen sollen auditierbar sein

## Konkrete Arbeitsschritte

1. Node-Bundle und Feldmodell festlegen.
   * Bundle-Namen, Felder, Feldtypen und Referenzen technisch definieren.
   * Abbilden, wie Lifetime, Sharing und Session-Bezug im Bundle ausgedrueckt werden.

2. Access- und Downloadlogik zuschneiden.
   * Owner-Rechte, read-only-Sicht auf freigegebene Dateien und owner-exklusive Session-Dateien in Drupal-Access-Regeln ueberfuehren.
   * Browser-Download und VPN-basierten Konsolen-Download auf reproduzierbare Routen und Controller abbilden.

3. Dateiverwaltungsseite im Backend planen.
   * Route, Navigation, Listenansicht sowie Upload-, Edit-, Replace- und Delete-Flows ableiten.
   * Read-only-Darstellung global freigegebener Dateien anderer User konkretisieren.

4. Service-UI-Seitenleiste erweitern.
   * Dateiliste, Suche, Session-Upload und Kennzeichnung der Dateiart in die bestehende Team-UI einhaengen.
   * `curl`-Kommando fuer nicht-destruktiven Download in das bestehende Terminal-Paste-Verhalten integrieren.

5. Cleanup- und Audit-Erweiterungen ausarbeiten.
   * Loeschpfad fuer kurzlebige Dateien nach Session-Ende technisch zuschneiden.
   * Erfolgreiche und fehlgeschlagene Dateiaktionen auf das vorhandene Audit-Modell abbilden.

6. Vertrags- und Statusdokumente nachziehen.
   * bei Bedarf API- oder Schema-Dokumente fuer Listen-, Upload- und Download-Schnittstellen anlegen
   * Statusdokumente nach der Umsetzung fortschreiben

## Erwartete Artefakte

* neuer Node-Bundle fuer Dateien
* Feld- und Access-Modell fuer dauerhafte und kurzlebige Dateien
* Backend-Seite fuer die allgemeine Dateiverwaltung
* Erweiterung der Team-UI um Dateien-Seitenleiste und Session-Upload
* Download-Endpunkte fuer Browser und Konsole
* Audit- und Cleanup-Erweiterungen

## Noch offene Punkte fuer die spaetere Schaerfung

Die folgenden Punkte sind noch nicht belastbar genug festgelegt und muessen vor API- oder UI-Feinspezifikation weiter geklaert werden:

* exakter URL-Zuschnitt der Download-Endpunkte im Verhaeltnis zu Backend-Origin und Gateway-Origin
* konkrete API-Schnitte fuer Liste, Upload, Bearbeitung, Loeschung und Browser-Download
* genaue UI-Felder in Dateiverwaltung und Seitenleiste, z. B. Groesse, Owner-Name oder Zeitstempel
* konkrete Audit-Ereignistypen und Payload-Struktur fuer erfolgreiche und fehlgeschlagene Dateiaktionen
* genaue technische Cleanup-Ausloesung fuer kurzlebige Dateien nach Session-Ende

## Validierung des Teilplans

Dieser Teilplan ist ausreichend vorbereitet, wenn daraus ohne weitere Grundsatzentscheidungen mindestens folgende Umsetzungspakete gestartet werden koennen:

* Bundle- und Feldmodell
* Zugriffs- und Downloadregeln
* Dateiverwaltungsseite
* Dateien-Seitenleiste in der Service-UI
* Audit- und Cleanup-Erweiterungen

## Uebergabe an Folgepakete

Folge-Agenten muessen aus diesem Dokument mitnehmen:

* dass die fachliche Featurebeschreibung in `feature-specs/01-file-management-and-console-downloads.md` liegt
* welche technischen Arbeitspakete daraus folgen
* welche Architekturgrenzen bereits feststehen und nicht erneut offen diskutiert werden muessen
