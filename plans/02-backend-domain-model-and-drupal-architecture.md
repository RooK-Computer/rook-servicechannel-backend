# Teilplan 02 – Domain-Modell und Drupal-Architektur

## Ziel

Die interne Backend-Architektur und das fachliche Modell so festlegen, dass Session-Lifecycle, PIN-Verwaltung, Grants, Audit und API-Logik konsistent umgesetzt werden koennen.

## Architekturentscheidung

Das Modell soll **nicht** auf Node-Bundles aufsetzen.

Begruendung:

* Die Backend-Objekte sind keine redaktionellen Inhalte.
* Session-, Grant- und Kopplungsdaten sind transaktional, kurzlebig und technisch statt editorial.
* Die benoetigten Lifecycle- und Ablaufregeln passen besser zu eigenen Domain-Objekten als zu Nodes.

Die Umsetzung soll deshalb aus einer Kombination aus **Custom Content Entities** und **dedizierten Schema-Tabellen** bestehen:

* Custom Content Entities fuer die fachlichen Hauptaggregate
* dedizierte Tabellen fuer hochfrequente, relationale oder append-only Daten

## Voraussetzungen

* Teilplan 01 ist abgeschlossen.
* Lokale Drupal-Entwicklungsumgebung laeuft.

## Fachliche Kernobjekte

* Support-Session
* PIN-Zuordnung
* Terminal-Grant
* Audit-Ereignis
* optional zusaetzliche interne Hilfsobjekte fuer Timeout- und Kopplungslogik

## Konkrete Modellentscheidung

### 1. Support-Session als eigene Content Entity

Entity-Typ:

* `support_session`

Warum als Entity:

* zentrale fachliche Hauptressource
* klarer Lifecycle
* wird von mehreren APIs referenziert
* braucht stabile Identifikatoren und saubere Storage-/Access-Integration

Pflichtfelder der Entity:

* `uuid`
* `status`
* `pin`
* `console_ip_address`
* `started_at`
* `last_heartbeat_at`
* `expires_at`
* `closed_at`

Voraussichtlich zusaetzliche Felder:

* `console_label` oder aehnlicher technischer Anzeigename, falls spaeter benoetigt
* `vpn_peer_ip_address`, falls vom effektiven Konsolen-IP-Wert getrennt zu behandeln
* `close_reason`
* `claimed_at`
* `active_terminal_count`

Zustandsmodell:

* `open` – Konsole hat Session begonnen, aber noch keine aktive Servicesitzung
* `active` – mindestens ein Servicemitarbeiter ist gekoppelt oder ein Terminal ist aktiv
* `closed` – Session wurde beendet, ist abgelaufen oder wurde explizit geschlossen

### 2. PIN nicht als eigene Entity

Die PIN wird **nicht** als eigene Entity und zunaechst auch nicht als eigene Haupttabelle modelliert.

Stattdessen:

* `pin` ist ein Feld auf `support_session`
* die PIN-Zuordnung ist damit direkt an den Session-Lifecycle gebunden

Begruendung:

* laut Spezifikation ist die PIN ein kurzlebiger Kopplungscode fuer genau eine aktive Session
* es gibt aktuell keinen belastbaren fachlichen Bedarf fuer ein eigenstaendiges PIN-Aggregat
* Invalidierung und Wiederverwendung haengen direkt an der Session

### 3. Terminal-Grant als eigene Content Entity

Entity-Typ:

* `terminal_grant`

Warum als Entity:

* ein Grant ist ein eigenstaendiges fachliches Objekt mit eigenem Lifecycle
* ein Grant bindet Benutzer, Session und Zugriffstoken
* Reconnect-, Redeem- und Revocation-Regeln muessen nachvollziehbar persistiert werden

Pflichtfelder der Entity:

* `uuid`
* `token_hash`
* `support_session_id`
* `user_id`
* `console_ip_address`
* `status`
* `issued_at`
* `redeemed_at`
* `last_used_at`
* `expires_at`
* `reconnect_valid_until`

Grant-Status:

* `issued`
* `redeemed`
* `expired`
* `revoked`

Wichtige Regel:

* das opaque Token selbst soll nur zur Ausgabe generiert werden; persistent gespeichert wird vorzugsweise ein Hash statt des Klartext-Tokens

### 4. Session-Kopplung als dedizierte Tabelle

Tabelle:

* `rook_support_session_participant`

Warum keine eigene Entity:

* die Kopplung zwischen Session und Drupal-User ist eine technische Many-to-Many-Beziehung
* sie ist leichtgewichtig und stark relationell
* sie braucht keine eigenstaendige redaktionelle oder feldreiche Entity-Verwaltung

Spalten:

* `id`
* `support_session_id`
* `user_id`
* `state`
* `coupled_at`
* `last_seen_at`
* `released_at`

Mindestzustand:

* `coupled`
* `released`

Diese Tabelle bildet den Zustand nach `pinlookup` ab, noch bevor ein Terminal-Grant eingelöst wurde.

### 5. Audit-Log als dedizierte Append-Only-Tabelle

Tabelle:

* `rook_support_audit_log`

Warum keine Entity:

* Audit-Ereignisse koennen schnell wachsen
* sie sind append-only
* sie muessen nicht als erstklassige Bearbeitungsobjekte in Drupal erscheinen

Spalten:

* `id`
* `support_session_id`
* `terminal_grant_id`
* `user_id`
* `event_type`
* `ip_address`
* `payload_json`
* `created_at`

Typische Events:

* `session_started`
* `session_heartbeat`
* `session_closed`
* `pin_lookup`
* `grant_issued`
* `grant_redeemed`
* `grant_revoked`

## Modulzuschnitt

Als naechster technischer Zuschnitt wird ein zentrales Domain-Modul vorgesehen:

* `rook_servicechannel_core`

Dieses Modul enthaelt:

* Entity-Definitionen fuer `support_session` und `terminal_grant`
* Schema-Definitionen fuer Teilnehmer- und Audit-Tabellen
* Domain-Services fuer Session-, Grant- und Audit-Logik
* gemeinsame Konstanten oder Enums fuer Statuswerte

Die spaeteren API-Endpunkte koennen danach entweder im selben Modul starten oder in Folgeschritten in separate API-Module ausgelagert werden:

* `rook_servicechannel_console_api`
* `rook_servicechannel_client_api`
* `rook_servicechannel_gateway_api`

Fuer den naechsten Schritt ist aber zunaechst nur `rook_servicechannel_core` erforderlich.

## Konkrete Arbeitsschritte

1. Custom-Modul `rook_servicechannel_core` anlegen.
2. Content Entity `support_session` definieren.
3. Content Entity `terminal_grant` definieren.
4. Schema-Tabellen fuer Teilnehmer- und Audit-Daten anlegen.
5. Session-Zustandsmodell festziehen:
   * `open`
   * `active`
   * `closed`
6. Minimale Pflichtfelder technisch definieren:
   * Session mit `status`, `pin`, `console_ip_address`
   * Grant mit Token-Hash, Session-Referenz, User-Referenz und Laufzeitfeldern
7. Weitere benoetigte Felder fuer Lifecycle, Team-Kopplung, Grant-Validierung und Audit identifizieren.
8. Domain-Services fuer Heartbeat, Timeout, PIN-Gueltigkeit, Cleanup und Revocation festlegen.
9. Spezifikationsluecken dokumentieren, statt sie stillschweigend zu ueberspielen.

## Erwartete Artefakte

* `docroot/modules/custom/rook_servicechannel_core/rook_servicechannel_core.info.yml`
* `docroot/modules/custom/rook_servicechannel_core/rook_servicechannel_core.install`
* `docroot/modules/custom/rook_servicechannel_core/src/Entity/SupportSession.php`
* `docroot/modules/custom/rook_servicechannel_core/src/Entity/TerminalGrant.php`
* `docroot/modules/custom/rook_servicechannel_core/src/Service/SupportSessionManager.php`
* `docroot/modules/custom/rook_servicechannel_core/src/Service/TerminalGrantManager.php`
* `docroot/modules/custom/rook_servicechannel_core/src/Service/AuditLogWriter.php`
* dokumentierte Zustandsuebergaenge und Tabellenstruktur

## Validierung

* Das Modell deckt alle drei externen APIs ab.
* Jeder API-Endpunkt kann eindeutig auf Domain-Services und persistente Daten abgebildet werden.
* Timeout- und Cleanup-Regeln lassen sich mit dem Modell ausdruecken.
* Es werden keine Node-Bundles fuer transaktionale Backend-Daten eingefuehrt.

## Risiken und offene Punkte

* fehlende Detailfestlegungen bei Fehlercodes und Teilen der Request-/Response-Modelle
* technische Auspraegung des VPN-Vertrauensmodells
* moegliche Konflikte zwischen Drupal-Konventionen und sehr transaktionaler Session-Logik

## Uebergabe an Folgepakete

Nach Abschluss muessen andere Agenten wissen:

* dass `support_session` und `terminal_grant` eigene Content Entities sind
* dass PINs als Session-Feld modelliert werden und nicht als eigene Entity
* dass Teilnehmer- und Audit-Daten in dedizierten Tabellen liegen
* dass Node-Bundles fuer diesen Backend-Kern explizit nicht verwendet werden
* ueber welche Services Session-, Grant- und Audit-Logik laufen
* welche offenen Spezifikationsfragen noch nicht entschieden sind

## Umgesetztes Initialergebnis

Folgende Artefakte wurden bereits angelegt und gegen die lokale Drupal-Instanz verifiziert:

* `docroot/modules/custom/rook_servicechannel_core/rook_servicechannel_core.info.yml`
* `docroot/modules/custom/rook_servicechannel_core/rook_servicechannel_core.permissions.yml`
* `docroot/modules/custom/rook_servicechannel_core/rook_servicechannel_core.services.yml`
* `docroot/modules/custom/rook_servicechannel_core/rook_servicechannel_core.install`
* `src/Entity/SupportSession.php`
* `src/Entity/TerminalGrant.php`
* `src/Service/SupportSessionManager.php`
* `src/Service/TerminalGrantManager.php`
* `src/Service/AuditLogWriter.php`

Verifiziert wurden:

* Aktivierung des Moduls `rook_servicechannel_core`
* Existenz der Tabellen `rook_support_session`, `rook_terminal_grant`, `rook_support_session_participant`, `rook_support_audit_log`
* Smoke-Test fuer Session-Erzeugung
* Smoke-Test fuer Grant-Ausgabe
* Smoke-Test fuer Audit- und Teilnehmer-Tabellen

Wichtige technische Notiz:

* Wegen eines unterbrochenen fruehen Installationszustands wurde zusaetzlich ein Reparaturpfad in `rook_servicechannel_core.install` hinterlegt, der fehlende Entity-Schemata und Hook-Schema-Tabellen nachziehen kann.
