# IT-Kayali Translate – Entwicklungsagenda

**Aktueller Entwicklungsstand: v0.12.16 – Produktionskandidat**  
**Letzte Aktualisierung: 16.09.2026**

Der aktuelle Fokus ist ein **fertiges, stabiles Plugin für den eigenen produktiven Einsatz**. Verkauf, Lizenzierung, Marketplace und Kunden-Updater bleiben bewusst für später zurückgestellt.

## Arbeitsregel

Bei jeder funktionalen Version müssen Quellcode, Versionsnummern, `readme.txt`, `README.md`, `CHANGELOG.md`, `AGENDA.md`, GitHub und die installierbare ZIP synchron bleiben. Eine Funktion gilt erst nach einem relevanten Realtest als bestätigt.

## Bereits bestätigt

### P0 – WooCommerce Mein Konto

v0.12.10 wurde auf der echten Staging-Seite erfolgreich bestätigt:

- DE funktioniert.
- Nicht-Standardsprachen funktionieren im getesteten Account-Ablauf.
- Orders, Downloads, Adressen und Kontodetails fallen nicht mehr auf das Dashboard zurück.
- Der My-Account-Rescue-Mechanismus bleibt Bestandteil des aktuellen Codes.

## Code-seitig jetzt umgesetzt

### P1 – WooCommerce End-to-End Routing

v0.12.14 stabilisiert zusätzlich den Sprachwechsel für:

- Shop
- Warenkorb
- Checkout
- `order-pay`
- `order-received`
- Mein Konto + Endpoints

Produkt-, Taxonomie-, Such- und Filterzustand nutzen weiterhin die vorhandenen ITKT-Router. Sichere Query-Parameter werden erhalten.

### P2 – Frontend-Texte im Backend

**IT-Kayali Translate → Frontend Texte** erfasst sichtbare Texte in der Standardsprache und ordnet sie Bereichen zu:

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

Erfasst werden außerdem unterstützte `placeholder`, `aria-label`, `title`, Button-Werte und Select-Optionen. Preise/Mengen/technische Werte werden soweit möglich ausgeschlossen.

### P3 – Dynamische WooCommerce/WoodMart/AJAX-Übersetzungen

Implementiert sind:

- WooCommerce Classic-Refreshes
- WooCommerce Blocks Cart/Checkout Events
- WooCommerce Fragment-Refresh
- Coupons
- Versandmethoden
- Checkout-Fehler
- dynamisch geänderte Attribute
- MutationObserver-Nachbearbeitung
- Popup-/Offcanvas-/Mini-Cart-Nachladen

### P4 – Live-Übersetzung

v0.12.14 erweitert die Live-Bereiche auf:

- Header
- Footer / Widgets
- Menü
- Mini-Cart
- Warenkorb
- Checkout
- Mein Konto
- Wishlist
- Popup / Offcanvas / Drawer
- Hinweise / Fehler
- Filter
- Shop / Suche
- gemeinsame WooCommerce-/Widget-Texte

### P5 – JavaScript-i18n

- `gettext` und `gettext_with_context` Runtime vorhanden.
- `ngettext` und `ngettext_with_context` Runtime vorhanden.
- v0.12.14 nutzt `Intl.PluralRules` für passende Pluralkategorien.
- Arabisch kann native zero/one/two/few/many/other-Formen verwenden.
- AJAX-/Blocks-Neurendering löst erneut Runtime-Übersetzungen aus.

### P6 – Backup / Restore

v0.12.13 implementiert:

- Einstellungen
- Sprachen
- String-/Source-/Translation-Tabellen
- globale/visuelle Übersetzungen
- Produktübersetzungen
- Taxonomieübersetzungen
- verknüpfte WordPress-Inhalte

Restore erzeugt keine neuen Produkte/Seiten/Beiträge/Begriffe. Fehlende IDs werden übersprungen.

### P7 – Performance & Cache

v0.12.14 ergänzt:

- versionierte Runtime-Maps im WordPress Object Cache
- weniger wiederholte Runtime-Datenbankarbeit
- gebündelte Cache-Invalidierung pro Request
- WordPress Object-Cache Flush nach Übersetzungsänderungen
- WP Fastest Cache automatische Leerung, wenn dessen API verfügbar ist
- offener Hook `itkt_after_cache_purge` für Server-/Hosting-Cache-Integrationen
- Cache-Invalidierung nach Restore

### v0.12.15 – Standardsprache direkt korrigierbar

Der Frontend-Textkatalog und der Live-/Runtime-String-Layer erlauben jetzt auch einen **optionalen Override der Standardsprache**. Damit können sichtbare Fremdtexte wie englische WoodMart-/WooCommerce-Labels auf einer deutschen Standardsprache direkt in ITKT korrigiert werden. Leer lassen bedeutet weiterhin: Original/native Standard verwenden. Der Runtime-Refresh wird auch in der Standardsprache nachgeladen, damit Full-Page-Cache keine alte Korrektur festhält.

### v0.12.16 – Produktions-Preflight

Der bestehende Bereich **IT-Kayali Translate → Systemstatus** prüft jetzt zusätzlich automatisch:

- alle drei String-Translation-Datenbanktabellen
- ob der Frontend-Textkatalog bereits echte Shop-Texte erfasst hat
- WooCommerce-Systemseiten Shop, Warenkorb, Checkout und Mein Konto
- zentrale Mein-Konto-Endpunkte
- registrierte Sprachisolation für transaktionale WooCommerce-E-Mails
- geladene Module für Dynamic Runtime, Backup/Restore und Cache-Invalidierung
- WP Fastest Cache Integration, wenn WP Fastest Cache erkannt wird

Damit können Konfigurations-/Schemafehler vor dem manuellen Browser-Endtest erkannt werden.

## Was jetzt wirklich noch offen ist

**Kein großer Funktionsblock fehlt mehr im Code für deinen aktuellen Einsatzzweck.** Offen ist die abschließende reale Abnahme auf der Website. Dabei müssen wir eventuelle konkrete WoodMart-/WooCommerce-Sonderfälle reparieren, die nur in deiner echten Installation sichtbar werden.

### Finaler Realtest

1. v0.12.16 installieren, **Systemstatus** öffnen und alle Hinweise prüfen; danach WP Fastest Cache + IONOS/Server-Cache einmal leeren.
2. DE, EN und AR vollständig durchgehen: Shop → Suche → Produkt → Kategorie → Filter → Mini-Cart → Warenkorb → Checkout → Mein Konto → Wishlist → Popup/Offcanvas.
3. Auf jeder relevanten Seite Sprache wechseln und prüfen, dass dieselbe logische Seite/dasselbe Produkt/Endpoint erhalten bleibt.
4. Als Admin in DE alle Bereiche einmal öffnen, danach **Frontend Texte** prüfen und einige EN/AR-Texte speichern.
5. Checkout-Fehler, Coupon, Versand-/Zahlungsänderung und Warenkorb-AJAX auslösen. Übersetzungen dürfen nach dem Refresh nicht wieder Deutsch werden.
6. Live-Editor an Header, Footer, Menü, Popup/Offcanvas, Wishlist und Notice testen.
7. Backup exportieren → harmlose Übersetzung ändern → Restore → alter Wert muss zurückkommen, ohne Duplikate.
8. Cache aktiv lassen, Übersetzung ändern und in privatem Browser prüfen, dass die neue Übersetzung öffentlich erscheint.
9. Erst wenn diese Matrix ohne Fehler durchläuft, wird die Version als produktiv bestätigt markiert.

## Falls im Finaltest noch etwas auffällt

Dann wird nur der konkrete reproduzierbare Fehler behoben; keine neuen Verkaufs-/Lizenzfeatures werden dazwischen geschoben. README, CHANGELOG und AGENDA bleiben die Übergabequelle für neue Chats.

## Produktregeln

- WooCommerce-Produkte werden nicht pro Sprache dupliziert.
- Preise, SKU, Bestand, Bilder und technische Daten bleiben gemeinsam.
- Theme-/Plugin-Quelldateien werden nicht verändert.
- Frontend-Sprache und Adminsprache bleiben unabhängig.
- RTL kann auf Content/Text beschränkt bleiben.
- Rechnungen, Lieferscheine und transaktionale E-Mails bleiben in der konfigurierten Standardsprache.
- Verkauf/Lizenzierung erst später.
