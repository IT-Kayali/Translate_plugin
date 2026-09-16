# IT-Kayali Translate – Entwicklungsagenda

**Aktueller Entwicklungsstand: v0.12.13**  
**Letzte Aktualisierung: 16.09.2026**

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

## v0.12.11 / v0.12.12 – Frontend- und Dynamic-Runtime-Phase

### Frontend Texte – Backend-Zentrale

Der Bereich **IT-Kayali Translate → Frontend Texte** sammelt unterstützte sichtbare Texte automatisch, wenn ein Administrator die Standardsprache im Frontend durchklickt.

Bereiche:

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
- Hinweise/Fehlermeldungen
- Header
- Footer
- Menü
- Sonstige

v0.12.12 erweitert die Erfassung um `placeholder`, `aria-label`, `title`, Button-Werte und Select-Optionen. Mini-Cart-Inhalte werden vor allgemeinen Popup-/Offcanvas-Bereichen erkannt.

### Dynamische Übersetzungen / JavaScript-i18n

In v0.12.12 implementiert:

- separates Modul `ITKT_Dynamic_Runtime`
- `@wordpress/i18n` Plural-Bridge für `ngettext` und `ngettext_with_context`
- erneutes Anwenden der Übersetzungen nach WooCommerce Blocks Cart-/Checkout-Updates
- erneutes Anwenden nach klassischen WooCommerce Fragment-, Coupon-, Versand- und Checkout-Events
- Beobachtung dynamisch geänderter `placeholder`, `title`, `aria-label` und Button-Werte

**Status:** lokal validiert, vollständiger Realtest auf der Staging-Seite noch offen.

## v0.12.13 – Backup / Restore

Neu ist **IT-Kayali Translate → Backup / Restore**.

Das JSON-Backup enthält:

- Plugin-Einstellungen
- Sprachkonfiguration
- ITKT String-/Source-/Translation-Tabellen
- globale und Frontend-Stringübersetzungen
- `_itkt_*` Post-Metadaten inkl. Produktübersetzungen
- `_itkt_*` Term-Metadaten inkl. Taxonomieübersetzungen
- Snapshot verknüpfter übersetzter WordPress-Inhalte

Restore-Regeln:

- Produkte, Seiten, Beiträge und Begriffe werden **nicht neu erstellt**.
- Damit entstehen durch Restore keine Duplikate.
- Vorhandene verknüpfte Inhalte werden nur über ihre existierende WordPress-ID aktualisiert.
- Nicht mehr vorhandene IDs werden übersprungen und gemeldet.
- ITKT-Stringtabellen und ITKT-Metadaten werden durch den Backup-Stand ersetzt.
- Rewrite-, Such- und Runtime-Zustände werden danach neu aufgebaut.
- Export/Restore nur für Administratoren mit Nonce-Schutz und expliziter Restore-Bestätigung.

**Status P6:** implementiert und lokal validiert, aber erst nach einem echten Export-/Änderungs-/Restore-Test auf Staging abgeschlossen.

## Was bis „Plugin fertig“ noch fehlt

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

### P2 – Frontend-Texte im Backend vollständig verifizieren/verfeinern

- automatische Erfassung real testen
- doppelte/falsche technische Texte reduzieren
- Bereichserkennung verbessern, falls im Realtest nötig
- direkte Übersetzung je aktiver Sprache prüfen
- Suche/Filter/Status auf realen Daten prüfen

### P3 – Dynamische Übersetzungen real vollständig prüfen

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

### P4 – Live-Übersetzung erweitern/verifizieren

- Kategorien
- Menüs
- globale Strings
- Header
- Footer
- WoodMart-Elemente
- Popups
- Offcanvas/Drawer
- weitere dynamische Widgets

### P5 – JavaScript-i18n fertig verifizieren

- dynamisch nachgeladene JS-Texte
- WooCommerce Blocks
- sichere Pluralformen
- AJAX-Neurendering
- keine verlorenen Übersetzungen nach Fragment-Refresh

### P6 – Backup / Restore Realtest

v0.12.13 ist implementiert. Noch durchführen:

1. Backup auf Staging exportieren.
2. Eine ungefährliche Testübersetzung ändern.
3. Backup wiederherstellen.
4. Prüfen, dass alter Wert zurückkommt.
5. Prüfen, dass keine Produkte/Seiten/Begriffe dupliziert wurden.
6. Frontend EN/AR nach Restore prüfen.

Danach P6 schließen.

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
