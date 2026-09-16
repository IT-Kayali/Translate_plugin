# IT-Kayali Translate – Entwicklungsagenda

**Aktueller Entwicklungsstand: v0.12.12**  
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

## v0.12.11 / v0.12.12 – aktuelle Produktionsphase

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

Neu in v0.12.12:

- separates Modul `ITKT_Dynamic_Runtime`
- `@wordpress/i18n` Plural-Bridge für `ngettext` und `ngettext_with_context`
- erneutes Anwenden der Übersetzungen nach WooCommerce Blocks Cart-/Checkout-Updates
- erneutes Anwenden nach klassischen WooCommerce Fragment-, Coupon-, Versand- und Checkout-Events
- Beobachtung dynamisch geänderter `placeholder`, `title`, `aria-label` und Button-Werte

### Status

**v0.12.12 ist implementiert und lokal validiert, aber noch nicht auf der realen Staging-Seite bestätigt.**

Lokale Prüfungen:
- alle PHP-Dateien: Syntaxprüfung erfolgreich
- alle JavaScript-Dateien: Syntaxprüfung erfolgreich
- Installations-ZIP: Integrität erfolgreich geprüft

Nächster Realtest:
1. v0.12.12 installieren.
2. Als Administrator DE öffnen.
3. Shop, Produktseite, Filter, Mini-Cart, Warenkorb, Checkout, Mein Konto, Wishlist und Popups öffnen.
4. Mindestens eine Fehlermeldung/Notice auslösen, z. B. Checkout-Validierung oder Warenkorb-Hinweis.
5. Backend → Frontend Texte prüfen: richtige Bereichszuordnung, insbesondere Mini-Cart und Hinweise/Fehler.
6. Einen neu erkannten Text für EN/AR übersetzen.
7. EN/AR prüfen und WooCommerce AJAX/Cart/Checkout-Aktionen auslösen.
8. Prüfen, dass Übersetzungen nach Fragment-/Blocks-Refresh erhalten bleiben.

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

- automatische Erfassung weiter verfeinern
- doppelte/falsche technische Texte reduzieren
- Bereichserkennung verbessern
- direkte Übersetzung je aktiver Sprache
- Suche/Filter/Status vollständig nutzbar

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
- visuelle/globale Übersetzungen
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
