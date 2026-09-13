# IT-Kayali Translate – Entwicklungsagenda

**Aktueller Entwicklungsstand: v0.12.10**  
**Letzte Aktualisierung: 13.09.2026**

Diese Datei ist die zentrale Übergabe- und Arbeitsagenda des Projekts. Sie muss bei jeder neuen Plugin-Version aktualisiert werden, damit die Entwicklung auch in einem neuen Chat ohne Informationsverlust fortgesetzt werden kann.

## Arbeitsregel für jede neue Version

Wenn eine neue Version von IT-Kayali Translate erstellt wird, gehören immer dieselben Schritte dazu:

1. Plugin-Code aktualisieren.
2. Versionsnummer in allen relevanten Plugin-Dateien synchronisieren.
3. PHP-Syntaxprüfung und relevante Funktionstests durchführen.
4. Installierbare WordPress-ZIP erstellen und im Chat bereitstellen.
5. Den identischen Quellstand auf GitHub aktualisieren.
6. `README.md` auf den aktuellen Funktionsstand bringen.
7. `CHANGELOG.md` um die neue Version ergänzen.
8. Diese `AGENDA.md` aktualisieren: erledigte Punkte, bestätigte Funktionen, offene Fehler und nächste Schritte.
9. Erst danach gilt die Version als abgeschlossen.

## Übergabe in einen neuen Chat

Bei Fortsetzung in einem neuen Chat zuerst diese Dateien im GitHub-Repository lesen:

- `README.md` – Produkt, Architektur, Funktionen und aktueller Versionsstand.
- `CHANGELOG.md` – technische Änderungen pro Version.
- `AGENDA.md` – aktueller Arbeitsstand, offene Fehler und nächste Schritte.
- Aktueller Plugin-Quellcode – tatsächlicher technischer Stand.

Der neueste Stand auf GitHub und die zuletzt bereitgestellte Plugin-ZIP sollen dieselbe Versionsnummer besitzen.

## Repository-Status

Der `main`-Branch ist seit 13.09.2026 bereinigt und enthält den vollständigen, installierbaren Quellcode von **v0.12.10 direkt im Repository-Root**.

Die früher nur für den technischen Import benötigten Archive und Rekonstruktionsdateien gehören nicht mehr zum kanonischen `main`-Stand:

- kein `source/`
- kein `.repo-import/`
- kein `.repo-fix/`
- keine einmaligen Import-/Finalisierungs-Workflows

Der veröffentlichte Quellstand wurde aus der bereits funktionierenden v0.12.10-Installations-ZIP rekonstruiert. Vor der Veröffentlichung wurden ZIP-Prüfsumme und ZIP-Integrität kontrolliert sowie PHP- und Frontend-JavaScript-Syntaxtests ausgeführt.

Für die normale Nutzung kann der aktuelle `main`-Branch über **GitHub → Code → Download ZIP** heruntergeladen werden. Für jede neue Entwicklungsversion wird zusätzlich weiterhin eine fertige WordPress-ZIP im Chat bereitgestellt.

## Aktueller Funktionsstatus

### P0 – WooCommerce „Mein Konto“

**Erledigt in v0.12.10.**

Bestätigter Stand auf der realen Staging-Seite:

- Deutsch / Standardsprache: WooCommerce-Account-Endpunkte funktionieren.
- Nicht-Standardsprache: Kontoübersicht und getestete Account-Bereiche öffnen wieder korrekt.
- Der bisherige Rückfall auf das Dashboard nach Klick auf Orders, Downloads, Adressen oder Kontodetails wurde beseitigt.
- Der reale Test von v0.12.10 war erfolgreich.

### Technische Lösung in v0.12.10

- Unabhängiger Rescue-Marker `itkt_wc_endpoint` trägt den kanonischen WooCommerce-Endpoint zusätzlich zur Pretty-URL.
- Der Server liest den validierten Marker vor bzw. ergänzend zur Rewrite-Auswertung und stellt den exakten WooCommerce-Endpoint wieder her.
- Der Marker wird gegen die echte WooCommerce-Endpoint-Map validiert.
- Die normale `frontend.js` enthält die Account-Navigation-Reparatur.
- Account-Klicks werden in der Capture-Phase abgefangen, bevor WoodMart oder andere Scripts sie auf das Dashboard umleiten können.
- Nach erfolgreichem Laden wird der temporäre Marker wieder aus der sichtbaren URL entfernt.
- Der direkte WooCommerce-Content-Dispatcher bleibt als letzte serverseitige Absicherung aktiv.

### Validierung v0.12.10

- PHP-Syntaxprüfung erfolgreich.
- JavaScript-Syntaxprüfung für `public/assets/frontend.js` erfolgreich.
- Lokaler Marker-/Dispatcher-Test erfolgreich.
- Installations-ZIP integer und reproduzierbar geprüft.
- Realer Staging-Test erfolgreich.

## Nächster konkreter Schwerpunkt

Als Nächstes folgt die systematische WooCommerce-End-to-End-Prüfung für jede aktive Sprache. Nicht nur einzelne Seiten testen, sondern den vollständigen Nutzerweg:

1. Shop
2. Suche
3. Produktseite
4. Kategorien
5. Filter / Attribute
6. Mini-Cart
7. Warenkorb
8. Checkout
9. Mein Konto
10. Wishlist

Während dieser Tests sollen alle nicht übersetzten oder falsch gerouteten Texte/Links gesammelt werden. Besonders wichtig sind dynamische WoodMart-/WooCommerce-Ausgaben, AJAX-Inhalte und Blocks.

## Bereits vorhandene Hauptfunktionen

### Translation Core

- Dynamische Sprachenverwaltung.
- Standardsprache frei wählbar.
- LTR/RTL-Unterstützung.
- Sprachabhängiges Routing und Fallbacks.
- Modulare Adapter-Architektur.
- Frontend-Sprache unabhängig von der WordPress-Adminsprache.

### WordPress / Elementor

- Seiten und Beiträge mit Sprachversionen.
- Verknüpfung vorhandener Sprachseiten.
- Elementor-Strukturkopie und -Bearbeitung.
- Layoutschutz für Container, CSS, IDs, Bilder und technische Einstellungen.
- Sprachabhängige Slugs und Routing.
- Strukturierte Übersetzung unterstützter Builder-Textfelder.

### WooCommerce

- Ein physisches Produkt pro Produkt-ID, keine Produktduplikate je Sprache.
- Produktname, Kurzbeschreibung und Langbeschreibung.
- Kategorien, Tags, Attribute und Attributwerte.
- Sprachabhängige Slugs.
- Produkt-Routing und Fallbacks.
- Cart-/Checkout-/My-Account-Sprachlogik.
- XLSX/CSV-Produktübersetzungen mit dynamischen Sprachspalten.
- Preise, SKU, Lagerbestand, Bilder und technische Produktdaten bleiben gemeinsam.

### String Translation

- PHP-Gettext-Scanner für Plugins und Themes.
- Eigene gespeicherte Übersetzungen ohne Änderung fremder Quelldateien.
- Suche und Statusverwaltung.
- Native WordPress-/WooCommerce-Sprachpakete als Fallback.
- IT-Kayali-Overrides mit höherer Priorität.
- JavaScript-i18n wurde erweitert, ist aber noch nicht vollständig abgeschlossen.

### Frontend Live Translation

- Admin-Modus auf der echten Website.
- Unterstützte Texte direkt anklickbar.
- Editor/Drawer für aktive Sprachen.
- Schutz dynamischer Variablen, HTML/Markup und Links wird weiter ausgebaut.

### SEO / Diagnose

- Canonical.
- hreflang.
- x-default.
- Sprachabhängige SEO-Felder.
- Sitemap.
- Routing-/Slug-Diagnose und Reparaturwerkzeuge.

## Nächste Aufgaben – Priorität

### P0 – erledigt

- WooCommerce „Mein Konto“-Endpoints für Nicht-Standardsprachen repariert.
- v0.12.10 auf der realen Staging-Seite bestätigt.
- Dashboard-Fallback nach Sprachwechsel im getesteten Ablauf behoben.
- GitHub-Repository auf vollständigen installierbaren Quellcode umgestellt.

### P1 – aktuell als Nächstes

- WooCommerce-End-to-End je Sprache systematisch prüfen.
- Nicht übersetzbare Frontend-Texte automatisch erkennen und zentral verfügbar machen.
- Frontend Live Translation für Kategorien, Menüs, globale Strings und weitere WoodMart-Inhalte erweitern.
- WooCommerce-/WoodMart-Strings in AJAX und Blocks systematisch testen.
- Checkout einschließlich dynamischer Hinweistexte, Zahlungs-/Versandtexte und Validierung prüfen.
- Sprachwechsel auf Produkt-, Kategorie-, Warenkorb-, Checkout- und Account-Seiten auf exakt gleiche Zielseite prüfen.

### P2 – Richtung v1.0

- JavaScript-i18n und sichere Pluralformen vervollständigen.
- Rollen/Capability wie `manage_translations`.
- Globale Kategorien/Tags/Attribute im Import/Export erweitern.
- Backup/Restore von Plugin-Konfiguration und Übersetzungsdaten.
- Datenbank-Migrationssystem für Updates.
- Performance-/Caching-Tests bei großen Shops.
- Weitere Theme-/Builder-Adapter abstrahieren.
- Lizenz-/Update-System erst nach stabiler v1.0-Basis.
- Optionale KI-Übersetzung später als separates Modul mit Glossar und Kostenkontrolle.

## Wichtige Produktregeln

- Das Plugin soll generisch und verkaufbar bleiben, nicht Allord-Sweets-spezifisch werden.
- WooCommerce-Produkte werden nicht je Sprache dupliziert.
- Theme-/Plugin-Quelldateien werden nicht verändert.
- Automatische Übersetzungen dürfen kein Layout, CSS, IDs, Bilder, technische URLs oder Code beschädigen.
- RTL soll auf Wunsch nur den Inhaltsbereich beeinflussen und nicht pauschal Header/Footer spiegeln.
- Rechnungen, Lieferscheine und transaktionale E-Mails sollen unabhängig von der Kundensprache in der konfigurierten Standardsprache bleiben.
- Neue Integrationen sollen über Module/Adapter ergänzt werden, nicht als projektspezifische Sonderlösung im Core.

## Versionsregel

Bei jedem funktionalen Release müssen mindestens folgende Stellen dieselbe Version zeigen:

- Plugin Header in `it-kayali-translate.php`
- `ITKT_VERSION`
- WordPress `readme.txt`
- `README.md`
- `CHANGELOG.md`
- `AGENDA.md`

Zusätzlich müssen GitHub-Quellstand und die im Chat bereitgestellte Installations-ZIP denselben funktionalen Stand repräsentieren.
