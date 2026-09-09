# IT-Kayali Translate – Entwicklungsagenda

**Aktueller Entwicklungsstand: v0.12.7**  
**Letzte Aktualisierung: 09.09.2026**

Diese Datei ist die zentrale Übergabe- und Arbeitsagenda des Projekts. Sie muss bei jeder Plugin-Version aktualisiert werden, damit die Entwicklung auch in einem neuen Chat ohne Informationsverlust fortgesetzt werden kann.

## Arbeitsregel für jede neue Version

Wenn eine neue Version von IT-Kayali Translate erstellt wird, gehören immer dieselben Schritte dazu:

1. Plugin-Code aktualisieren.
2. Versionsnummer in allen relevanten Plugin-Dateien synchronisieren.
3. PHP-Syntaxprüfung und relevante Funktionstests durchführen.
4. Installierbare WordPress-ZIP erstellen und im Chat bereitstellen.
5. Den identischen Quellstand auf GitHub aktualisieren.
6. `README.md` auf den aktuellen Funktionsstand bringen.
7. `CHANGELOG.md` um die neue Version ergänzen.
8. Diese `AGENDA.md` aktualisieren:
   - Was wurde erledigt?
   - Was funktioniert?
   - Welche Fehler sind noch offen?
   - Was ist als Nächstes geplant?
9. Erst danach gilt die Version als abgeschlossen.

## Übergabe in einen neuen Chat

Bei Fortsetzung in einem neuen Chat zuerst diese Dateien aus dem GitHub-Repository lesen:

- `README.md` – Produkt, Architektur, Funktionen und aktueller Versionsstand.
- `CHANGELOG.md` – technische Änderungen pro Version.
- `AGENDA.md` – aktueller Arbeitsstand, offene Fehler und nächste Schritte.
- Aktueller Plugin-Quellcode – tatsächlicher technischer Stand.

Der neueste Stand auf GitHub und die zuletzt bereitgestellte Plugin-ZIP sollen dieselbe Versionsnummer besitzen.

## Aktueller Schwerpunkt

WooCommerce-Systemseiten müssen unter allen aktiven Sprachen genauso zuverlässig funktionieren wie in der Standardsprache.

### Aktuell bestätigtes Problem

Deutsch / Standardsprache:
- `/mein-konto/orders/` funktioniert.
- Bestellungen, Adressen, Kontodetails und weitere WooCommerce-Endpoints werden korrekt geöffnet.

Nicht-Standardsprachen, aktuell insbesondere Arabisch:
- `/ar/mein-konto/` öffnet die Kontoübersicht.
- Beim Klick auf Bestellungen, Adressen oder Kontodetails wird der gewünschte Bereich noch nicht zuverlässig geöffnet.
- Die Seite bleibt bzw. fällt auf die Kontoübersicht zurück.

**Status:** noch offen. v0.12.7 hat den Bereich gehärtet, der reale Test zeigt aber, dass das Problem noch nicht vollständig gelöst ist.

## Bereits vorhandene Hauptfunktionen

### Translation Core
- Dynamische Sprachenverwaltung.
- Standardsprache frei wählbar.
- LTR/RTL-Unterstützung.
- Sprachabhängiges Routing und Fallbacks.
- Modulare Adapter-Architektur.

### WordPress / Elementor
- Seiten und Beiträge mit Sprachversionen.
- Elementor-Strukturkopie.
- Layoutschutz für Container, CSS, IDs, Bilder und technische Einstellungen.
- Verknüpfen vorhandener Sprachseiten.
- Builder-Textfelder strukturiert übersetzbar.

### WooCommerce
- Ein physisches Produkt pro Produkt-ID, keine Produktduplikate je Sprache.
- Produktname, Kurzbeschreibung und Langbeschreibung.
- Kategorien, Tags, Attribute und Attributwerte.
- Sprachabhängige Slugs.
- Produkt-Routing.
- Cart-/Checkout-/My-Account-Sprachlogik.
- XLSX/CSV-Produktübersetzungen.

### String Translation
- PHP-Gettext-Scanner für Plugins und Themes.
- Eigene gespeicherte Übersetzungen ohne Änderung fremder Quelldateien.
- Suche und Statusverwaltung.
- Native WordPress-/WooCommerce-Sprachpakete als Fallback.
- JavaScript-i18n wurde bereits erweitert, ist aber noch nicht vollständig abgeschlossen.

### Frontend Live Translation
- Admin-Modus auf der echten Website.
- Anklickbare unterstützte Texte.
- Drawer/Editor für aktive Sprachen.
- Schutz dynamischer Variablen und Markup wird weiter ausgebaut.

### SEO / Diagnose
- Canonical.
- hreflang.
- x-default.
- Sprachabhängige SEO-Felder.
- Sitemap.
- Routing-/Slug-Diagnose und Reparaturwerkzeuge.

## Nächste Aufgaben – Priorität

### P0 – aktuell zuerst erledigen
- WooCommerce „Mein Konto“ Endpoints für Nicht-Standardsprachen vollständig reparieren.
- Arabisch und Englisch testen:
  - Dashboard
  - Bestellungen
  - Einzelbestellung
  - Downloads
  - Adressen
  - Kontodetails
  - Zahlungsmethoden, falls aktiv
  - Abmelden
- Prüfen, ob WoodMart eigene Endpoint-/Navigation-Logik dazwischenfunkt.
- Prüfen, ob Sprachrouter den WooCommerce Query Var nach dem WordPress Rewrite erneut überschreibt.
- Systemseiten physisch nur einmal verwenden, Sprache virtuell beibehalten.

### P1 – danach
- WooCommerce End-to-End je Sprache:
  - Shop
  - Suche
  - Produkt
  - Kategorien
  - Filter
  - Mini-Cart
  - Warenkorb
  - Checkout
  - Mein Konto
  - Wishlist
- Nicht übersetzbare Frontend-Texte automatisch erkennen und zentral verfügbar machen.
- Frontend Live Translation für Kategorien, Menüs, globale Strings und weitere WoodMart-Inhalte erweitern.
- WooCommerce-/WoodMart-Strings in AJAX/Blocks systematisch testen.

### P2 – Richtung v1.0
- JavaScript-i18n und sichere Pluralformen vervollständigen.
- Rollen/Capability wie `manage_translations`.
- Globale Kategorien/Tags/Attribute in Import/Export erweitern.
- Backup/Restore von Plugin-Konfiguration und Übersetzungsdaten.
- Datenbank-Migrationssystem für Updates.
- Performance-/Caching-Tests bei großen Shops.
- Lizenz-/Update-System erst nach stabiler v1.0-Basis.
- Optionale KI-Übersetzung später als separates Modul mit Glossar und Kostenkontrolle.

## Versionsregel

Bei jedem Release müssen mindestens folgende Stellen dieselbe Version zeigen:

- Plugin Header in `it-kayali-translate.php`
- `ITKT_VERSION`
- WordPress `readme.txt`
- `README.md`
- `CHANGELOG.md`
- `AGENDA.md`
- Name der im Chat bereitgestellten ZIP

## Qualitätsregel

Eine neue Version gilt erst als fertig, wenn:
- der Code gespeichert ist,
- die ZIP erstellt wurde,
- GitHub aktualisiert wurde,
- README/CHANGELOG/AGENDA aktualisiert wurden,
- und der konkrete Fix mindestens auf dem betroffenen Testpfad geprüft wurde.

---

**Projekt:** IT-Kayali Translate  
**Website:** it-kayali.de  
**Aktuelle Version:** v0.12.7
