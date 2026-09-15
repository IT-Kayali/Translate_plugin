# IT-Kayali Translate – Entwicklungsagenda

**Aktueller Entwicklungsstand: v0.12.11**  
**Letzte Aktualisierung: 15.09.2026**

Diese Datei ist die zentrale Übergabe- und Arbeitsagenda. Der aktuelle Fokus ist ein **fertiges, stabiles Produktions-Plugin** für den eigenen Einsatz. Verkauf, Lizenzierung, Marketplace und Kunden-Updater werden bewusst auf später verschoben.

## Arbeitsregel für jede neue Version

1. Plugin-Code aktualisieren.
2. Versionsnummer überall synchronisieren.
3. PHP-/JavaScript-Prüfungen und relevante Funktionstests durchführen.
4. Installierbare WordPress-ZIP im Chat bereitstellen.
5. Identischen Quellstand auf GitHub aktualisieren.
6. `README.md`, `CHANGELOG.md` und `AGENDA.md` aktualisieren.
7. Realtest dokumentieren.
8. Erst dann gilt eine Version/Funktion als abgeschlossen.

## Bestätigter stabiler Stand

### WooCommerce Mein Konto – P0 erledigt

v0.12.10 wurde auf der echten Staging-Seite erfolgreich bestätigt:

- Standardsprache funktioniert.
- Nicht-Standardsprachen funktionieren im getesteten Account-Ablauf.
- Orders, Downloads, Adressen und Kontodetails fallen nicht mehr auf Dashboard zurück.
- Der My-Account-Rescue-Mechanismus bleibt Bestandteil des aktuellen Codes.

## v0.12.11 – aktuell implementiert

### Frontend Texte – Backend-Zentrale

Neu ist der Bereich **IT-Kayali Translate → Frontend Texte**.

Wenn ein Administrator die konfigurierte Standardsprache im Frontend durchklickt, sammelt das Plugin unterstützte sichtbare Texte automatisch und ordnet sie Bereichen zu:

- Shop
- Suche
- Produktseite
- Kategorie/Archiv
- Filter
- Mini-Cart
- Warenkorb
- Checkout
- Mein Konto
- Wishlist
- Popups/Offcanvas
- Header
- Footer
- Menü
- Sonstige

Die gefundenen Texte können in einer Tabelle direkt für alle aktiven Zielsprachen übersetzt werden.

### Dynamische Erfassung in v0.12.11

- MutationObserver für nachgeladene DOM-Inhalte.
- WooCommerce Cart-/Checkout-/Fragment-Events werden erneut erfasst.
- Popup-, Drawer- und Offcanvas-Bereiche werden berücksichtigt.
- Erfassung läuft nur für angemeldete Administratoren.
- Erfassung läuft nur in der Standardsprache.
- Typische Preise, Mengen und technische Produkt-/Bestellwerte werden nicht als Übersetzungsstrings gesammelt.
- Gespeicherte Werte nutzen die vorhandene globale Runtime-Übersetzung und verändern keine Theme-/Plugin-Dateien.

### Status v0.12.11

**Implementiert und lokal validiert, aber noch nicht auf der realen Staging-Seite bestätigt.**

Lokale Prüfungen:
- PHP-Syntaxprüfung erfolgreich.
- JavaScript-Syntaxprüfung erfolgreich.
- Installations-ZIP geprüft.

Nächster Realtest nach Installation:
1. Als Administrator die deutsche Standardsprache öffnen.
2. Shop, Produktseite, Filter, Mini-Cart, Warenkorb, Checkout, Mein Konto, Wishlist und mindestens ein Popup/Offcanvas öffnen.
3. Backend → IT-Kayali Translate → Frontend Texte öffnen.
4. Prüfen, ob die Texte nach Bereich erscheinen.
5. Testweise einen Text für EN/AR übersetzen und speichern.
6. EN/AR öffnen und prüfen, ob die Runtime-Übersetzung greift.

## Ziel bis „Plugin fertig“

### P1 – WooCommerce komplett End-to-End

DE/EN/AR zuerst vollständig prüfen und reparieren:

- Shop
- Suche
- Produktseite
- Kategorien
- Filter / Attribute
- Mini-Cart
- Warenkorb
- Checkout
- Mein Konto
- Wishlist
- Popups / Offcanvas

Wichtig: Der Sprachwechsel muss immer auf derselben logischen Zielseite bleiben. Kein Rückfall auf Startseite, Dashboard oder falsche Produkt-/Kategorie-URL.

### P2 – Frontend-Texte im Backend vollständig

- Automatische Erfassung weiter verfeinern.
- Doppelte/falsche technische Texte reduzieren.
- Bereichserkennung verbessern.
- Direkte Übersetzung je aktiver Sprache.
- Suche/Filter/Status vollständig nutzbar.
- Später optional Sammelaktionen/Bulk-Übersetzung.

### P3 – Dynamische Übersetzungen vervollständigen

Systematisch prüfen:

- WooCommerce Classic
- WooCommerce Blocks
- WoodMart
- AJAX-Fragmente
- Zahlungsarten
- Versandtexte
- Fehlermeldungen
- Validierung
- Checkout-Hinweise
- Notices
- dynamische Popup-/Offcanvas-Inhalte

### P4 – Live-Übersetzung erweitern

Frontend-Live-Modus weiter ausbauen für:

- Kategorien
- Menüs
- globale Strings
- Header
- Footer
- WoodMart-Elemente
- Popups
- Offcanvas/Drawer
- weitere dynamische Widgets

### P5 – JavaScript-i18n fertigstellen

- dynamisch nachgeladene JS-Texte
- WooCommerce Blocks
- sichere Pluralformen
- AJAX-Neurendering
- keine verlorenen Übersetzungen nach Fragment-Refresh

### P6 – Backup / Restore

Einbauen für:

- Plugin-Einstellungen
- Sprachkonfiguration
- String-Übersetzungen
- visuelle/globalen Übersetzungen
- Produktübersetzungen
- Taxonomieübersetzungen
- relevante Plugin-Metadaten

Restore muss vorhandene Daten kontrolliert wiederherstellen können, ohne Produkte oder Layout zu duplizieren.

### P7 – Performance & Cache

Abschließend testen:

- viele Produkte
- viele Strings
- WooCommerce AJAX
- WoodMart AJAX
- WooCommerce Blocks
- WP Fastest Cache
- IONOS Cache
- Sprachwechsel nach Full-Page-Cache
- keine falsche Sprache aus Cache
- keine unnötigen Datenbankabfragen bei jedem Besucher

## Wichtige Produktregeln

- WooCommerce-Produkte werden nicht je Sprache dupliziert.
- Theme-/Plugin-Quelldateien werden nicht verändert.
- Preise, SKU, Bestand, Bilder und technische Daten bleiben gemeinsam.
- Layout, CSS, IDs, Bilder, technische URLs und Code dürfen durch Textübersetzung nicht beschädigt werden.
- Frontend-Sprache und Adminsprache bleiben unabhängig.
- RTL kann nur den Content/Text betreffen; Header/Footer müssen nicht gespiegelt werden.
- Rechnungen, Lieferscheine und transaktionale E-Mails sollen in der konfigurierten Standardsprache bleiben.
- Verkaufs-/Lizenzfunktionen erst später, nachdem diese Produktionspunkte abgeschlossen sind.

## Repository-Regel

`main` soll nur den echten Plugin-Quellcode plus `README.md`, `CHANGELOG.md`, `AGENDA.md` und `.gitignore` enthalten. Temporäre Import-/Patch-Dateien und einmalige Update-Workflows gehören nicht in den finalen Stand.

## Neue-Chat-Übergabe

Bei Fortsetzung in einem neuen Chat zuerst lesen:

1. `README.md`
2. `CHANGELOG.md`
3. `AGENDA.md`
4. aktuellen Plugin-Quellcode

Danach immer vom neuesten GitHub-Stand weiterarbeiten.
