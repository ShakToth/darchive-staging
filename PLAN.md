# Implementierungsplan — Fehlende Features

8 Features in 5 Arbeitspaketen. Geschätzte Dateien: ~15 neue/geänderte.

---

## Paket A: Bibliothek-Erweiterungen

### A1 — Manuelle Qualitätsvergabe beim Upload
**Problem:** Qualität wird automatisch nach Extension vergeben. Bibliothekar/Meister soll sie frei wählen können.

**Änderungen:**

1. **Neue SQLite-Tabelle `file_metadata`** (in `functions.php`)
   - Neue DB: `uploads/bibliothek.db` — Singleton via `getBibliothekDB()`
   - Schema:
     ```sql
     CREATE TABLE file_metadata (
         id INTEGER PRIMARY KEY AUTOINCREMENT,
         filename TEXT UNIQUE NOT NULL,
         category TEXT DEFAULT 'normal',   -- 'normal' oder 'forbidden'
         quality TEXT DEFAULT NULL,         -- NULL = auto, sonst manuell gesetzt
         description TEXT DEFAULT '',
         uploaded_by TEXT DEFAULT '',
         uploaded_at INTEGER NOT NULL,
         last_read_by TEXT DEFAULT '',      -- Für Ausleih-Register (A3)
         last_read_at INTEGER DEFAULT NULL
     );
     ```
   - Auto-Migration in `getBibliothekDB()`

2. **`functions.php` anpassen:**
   - `getItemQuality($filename, $isForbidden)` → prüft zuerst `file_metadata.quality`, fällt zurück auf Auto-Erkennung wenn NULL
   - `handleUpload()` → speichert übergebene Qualität in DB (neuer Parameter `$quality`)
   - `getFiles()` → JOIN mit `file_metadata` für manuelle Qualitäts-Daten
   - Neue Funktion: `setFileQuality($filename, $quality)` — für nachträgliches Ändern
   - Neue Funktion: `getFileMetadata($filename)` — einzelne Datei-Metadaten

3. **`bibliothek.php` anpassen:**
   - Upload-Formular bekommt Qualitäts-Dropdown (nur wenn `hasPermission('bibliothek', 'upload')`)
   - Datei-Karten: Klick auf Quality-Badge → Dropdown zum Ändern (nur Bibliothekar/Meister)
   - POST-Handler für Qualitätsänderung

4. **`api/upload.php` anpassen:**
   - `quality`-Parameter wird an `handleUpload()` weitergegeben und in DB gespeichert

### A2 — CSS-basierter Cover-Generator
**Problem:** Keine Vorschaubilder für Bücher/Dokumente.

**Umsetzung:** Rein CSS/HTML — kein Imagick/GD nötig.

1. **`bibliothek.php` anpassen:**
   - Für Nicht-Bilder: statt nur Emoji-Icon, ein CSS-gerendetes "Buchcover" generieren:
     ```html
     <div class="rp-book-cover quality-{quality}">
         <div class="rp-book-cover__spine"></div>
         <div class="rp-book-cover__title">{dateiname}</div>
         <div class="rp-book-cover__ext">{.PDF}</div>
     </div>
     ```

2. **`style.css` erweitern:**
   - `.rp-book-cover` — Leder-Buchrücken-Optik, Qualitätsfarbe als Rahmen
   - `.rp-book-cover__spine` — Dunkler Streifen links (Buchrücken)
   - `.rp-book-cover__title` — Goldene Schrift (Cinzel-Font)
   - `.rp-book-cover__ext` — Dateityp-Badge unten rechts
   - Qualitäts-Varianten: Common=grau, Legendary=orange Glühen etc.

### A3 — Ausleih-Register (Einfaches Logbuch)
**Problem:** Keine Tracking wer was gelesen hat.

**Umsetzung:** Einfacher "Ich lese das"-Button.

1. **DB-Erweiterung** in `uploads/bibliothek.db`:
   - `file_metadata.last_read_by` und `file_metadata.last_read_at` (siehe A1 Schema)
   - Plus neues `read_log`-Tabelle für Historie:
     ```sql
     CREATE TABLE read_log (
         id INTEGER PRIMARY KEY AUTOINCREMENT,
         filename TEXT NOT NULL,
         reader_name TEXT NOT NULL,
         read_at INTEGER NOT NULL
     );
     ```

2. **`functions.php` erweitern:**
   - `markFileAsRead($filename, $readerName)` — Eintrag in `read_log` + Update `last_read_by`/`last_read_at`
   - `getReadLog($filename, $limit = 5)` — Letzte Leser abrufen

3. **`bibliothek.php` anpassen:**
   - Tooltip zeigt "Zuletzt gelesen von: [Name]" wenn vorhanden
   - Button "📖 Ich lese das" bei jedem Buch (nur für eingeloggte Benutzer)
   - POST-Handler für `mark_read`

---

## Paket B: Miliz-Erweiterungen

### B1 — Status-Filter (vervollständigen)
**Problem:** Priority-System existiert, aber kein UI zum Filtern.

1. **`miliz.php` anpassen:**
   - Filter-Leiste über den Einträgen (Buttons: Alle, Wichtig, Sehr wichtig, Dringend)
   - URL-Parameter: `?cat=gesucht&priority=2`
   - Zusätzlich für "Gesucht"/"Steckbriefe": Status-Feld als neues DB-Feld

2. **DB-Migration in `functions_miliz.php`:**
   - `ALTER TABLE miliz_entries ADD COLUMN status TEXT DEFAULT 'aktiv'`
   - Erlaubte Werte: 'aktiv', 'fluechtig', 'inhaftiert', 'verstorben', 'erledigt'

3. **`miliz.php` erweitern:**
   - Status-Dropdown im Erstellungsformular
   - Status-Badge auf Karten
   - Filter nach Status: `?cat=gesucht&status=fluechtig`

### B2 — Wanted-Poster Generator
**Problem:** "Gesucht"-Kategorie hat kein visuelles Plakat-System.

1. **`miliz.php` anpassen:**
   - Spezielles Erstellungsformular für Kategorie "gesucht" mit Feldern:
     - Name des Gesuchten
     - Bild-Upload (Steckbrieffoto)
     - Verbrechen (Textfeld)
     - Belohnung: 3 Felder für 🟡 Gold, ⚪ Silber, 🟤 Kupfer
     - Priorität (Standard: Dringend)
   - Die Felder werden als JSON in `content` gespeichert

2. **Spezielle Rendering-Funktion:**
   - `renderWantedPoster($entry)` — Parst JSON-Content und generiert das Plakat-HTML
   - Visuell wie ein Wanted-Poster: Pergament-Hintergrund, "GESUCHT"-Header, Bild, Verbrechen, Belohnung

3. **`style.css` erweitern:**
   - `.wanted-poster` — Pergament-Hintergrund, Brandrand-Effekt
   - `.wanted-poster__header` — Große rote "GESUCHT"-Schrift (MedievalSharp)
   - `.wanted-poster__foto` — Sepia-getöntes Bild in Holzrahmen
   - `.wanted-poster__belohnung` — Gold/Silber/Kupfer-Coins mit Farben
   - `.wanted-poster__verbrechen` — Kursiver Text

### B3 — Bürger-Briefkasten
**Problem:** Kein anonymes Hinweis-System.

1. **Neue DB-Tabelle in `miliz/miliz.db`:**
   ```sql
   CREATE TABLE IF NOT EXISTS briefkasten (
       id INTEGER PRIMARY KEY AUTOINCREMENT,
       absender_name TEXT DEFAULT 'Anonymer Bürger',
       absender_id INTEGER DEFAULT NULL,  -- NULL wenn nicht eingeloggt
       betreff TEXT NOT NULL,
       nachricht TEXT NOT NULL,
       gelesen INTEGER DEFAULT 0,
       erstellt_am INTEGER NOT NULL
   );
   ```

2. **`functions_miliz.php` erweitern:**
   - `createHinweis($betreff, $nachricht, $absenderName, $absenderId)` — Kein Permission-Check (öffentlich)
   - `getHinweise($nurUngelesen = false)` — Miliz-Permission erforderlich
   - `markHinweisGelesen($id)` — Miliz-Permission erforderlich
   - `deleteHinweis($id)` — Miliz-Permission erforderlich

3. **`miliz.php` anpassen:**
   - Neuer Bereich "📬 Briefkasten" — sichtbar für alle, auch ohne Login
   - Formular: Betreff + Nachricht + optionaler Name
   - CSRF-Schutz + Rate-Limiting (max 3 Hinweise pro IP pro Stunde)
   - Interne Ansicht (nur Miliz/Meister): Liste der Hinweise mit gelesen/ungelesen Badge
   - Ungelesene Hinweise als Zähler im Miliz-Menü

4. **`header.php` anpassen:**
   - Miliz-Nav-Link zeigt Badge mit ungelesenen Hinweisen (nur für Miliz/Meister)

### B4 — Waffenkammer-Inventar (vervollständigen)
**Problem:** Tabellenansicht existiert, aber kein echtes Inventarsystem.

1. **DB-Migration in `functions_miliz.php`:**
   - Neue Tabelle `waffenkammer` in `miliz/miliz.db`:
     ```sql
     CREATE TABLE IF NOT EXISTS waffenkammer (
         id INTEGER PRIMARY KEY AUTOINCREMENT,
         name TEXT NOT NULL,
         beschreibung TEXT DEFAULT '',
         bestand INTEGER DEFAULT 1,
         zustand TEXT DEFAULT 'gut',    -- 'neu', 'gut', 'abgenutzt', 'reparaturbeduerftig'
         ausgegeben_an TEXT DEFAULT '',
         bild_pfad TEXT DEFAULT NULL,
         erstellt_am INTEGER NOT NULL,
         aktualisiert_am INTEGER NOT NULL
     );
     ```

2. **`functions_miliz.php` erweitern:**
   - `getWaffenkammerInventar()` — Alle Gegenstände
   - `createWaffenkammerItem($name, $beschreibung, $bestand, $zustand)`
   - `updateWaffenkammerItem($id, ...)` — Bestand/Zustand/Ausgabe ändern
   - `deleteWaffenkammerItem($id)`

3. **`miliz.php` anpassen:**
   - Waffenkammer-Kategorie zeigt echte Inventar-Tabelle:
     | Waffe/Ausrüstung | Bestand | Zustand | Ausgegeben an | Aktionen |
   - Zustand als farbcodierte Badges (Grün=Neu/Gut, Gelb=Abgenutzt, Rot=Reparatur)
   - Inline-Edit für "Ausgegeben an" per Klick
   - "Neues Item"-Formular am Ende

---

## Paket C: Aushänge-Erweiterungen

### C1 — Angeheftete Notizen (Kommentar-System)
**Problem:** Keine Möglichkeit auf Aushänge zu antworten.

1. **DB-Migration in `functions_aushaenge.php`:**
   - Neue Tabelle `zettel_notizen`:
     ```sql
     CREATE TABLE IF NOT EXISTS zettel_notizen (
         id INTEGER PRIMARY KEY AUTOINCREMENT,
         zettel_id INTEGER NOT NULL,
         text TEXT NOT NULL,
         autor_name TEXT NOT NULL,
         autor_id INTEGER DEFAULT NULL,
         erstellt_am DATETIME DEFAULT CURRENT_TIMESTAMP,
         FOREIGN KEY (zettel_id) REFERENCES zettel(id) ON DELETE CASCADE
     );
     ```

2. **`functions_aushaenge.php` erweitern:**
   - `addNotiz($zettelId, $text, $autorName, $autorId)` — Permission: `aushaenge.write`
   - `getNotizen($zettelId)` — Alle Notizen zu einem Zettel
   - `deleteNotiz($id)` — Meister oder eigene Notiz
   - `getNotizCount($zettelId)` — Für Badge auf Karte

3. **`aushaenge.php` anpassen:**
   - Jede Zettel-Karte zeigt Notiz-Zähler: "📎 3 Notizen"
   - Aufklappbarer Notiz-Bereich unter jedem Zettel
   - Kleines Formular "Notiz anheften": Textfeld + Name
   - Visuell: Kleine leicht schräge Zettelchen am Hauptaushang (CSS)

4. **`style.css` erweitern:**
   - `.zettel-notiz` — Kleiner Zettel, leicht gedreht (random rotation), Klebeband-Effekt
   - `.zettel-notizen__container` — Stapel-Ansicht

### C2 — "Wichtig"-Siegel
**Problem:** Keine Möglichkeit offizielle Aushänge hervorzuheben.

1. **DB-Migration in `functions_aushaenge.php`:**
   - `ALTER TABLE zettel ADD COLUMN ist_wichtig INTEGER DEFAULT 0`
   - `ALTER TABLE zettel ADD COLUMN angeheftet INTEGER DEFAULT 0` (oben fixiert)

2. **`functions_aushaenge.php` erweitern:**
   - `toggleWichtig($id)` — Meister-only
   - `toggleAngeheftet($id)` — Meister-only
   - `getAushaenge()` → Sortierung: angeheftet zuerst, dann nach Datum

3. **`aushaenge.php` anpassen:**
   - Wichtige Zettel bekommen rotes Wachssiegel (CSS) + leuchtenden Rahmen
   - Angeheftete Zettel stehen immer oben mit 📌-Icon
   - Meister sieht Toggle-Buttons: "🔴 Siegel setzen" / "📌 Anheften"

4. **`style.css` erweitern:**
   - `.zettel--wichtig` — Roter Wachssiegel-Overlay (CSS pseudo-element), goldener Rahmen, Glüh-Animation
   - `.zettel--angeheftet` — Pin-Icon oben, leicht hervorgehoben

---

## Zusammenfassung: Geänderte/Neue Dateien

| Datei | Änderungen |
|-------|-----------|
| `functions.php` | `getBibliothekDB()`, `setFileQuality()`, `getFileMetadata()`, `markFileAsRead()`, `getReadLog()`, `handleUpload()` erweitern, `getItemQuality()` erweitern, `getFiles()` erweitern |
| `functions_miliz.php` | `briefkasten`-Tabelle, `waffenkammer`-Tabelle, Status-Migration, Wanted-Poster-Funktionen, Briefkasten-CRUD, Waffenkammer-CRUD |
| `functions_aushaenge.php` | `zettel_notizen`-Tabelle, `ist_wichtig`/`angeheftet`-Migration, Notiz-CRUD, Toggle-Funktionen |
| `bibliothek.php` | Upload-Qualitätswahl, Cover-Generator HTML, Ausleih-Button, Qualitäts-Änderung |
| `miliz.php` | Status-Filter UI, Wanted-Poster-Formular+Rendering, Briefkasten-Bereich, Waffenkammer-Inventar |
| `aushaenge.php` | Notiz-System UI, Wichtig-Siegel UI, Anheften UI |
| `style.css` | `.rp-book-cover`, `.wanted-poster`, `.zettel-notiz`, `.zettel--wichtig`, Zustand-Badges, Filter-UI |
| `header.php` | Briefkasten-Badge im Miliz-Nav |
| `api/upload.php` | Quality-Parameter an DB weiterleiten |
| `api/aushaenge.php` | Notizen-Endpoints (GET/POST/DELETE), Wichtig/Anheften-Endpoints |
| `api/miliz.php` | Briefkasten-Endpoints, Waffenkammer-Endpoints, Status-Filter |

---

## Umsetzungsreihenfolge

1. **Paket A** — Bibliothek (A1 Qualität → A2 Cover → A3 Ausleih-Register)
2. **Paket C** — Aushänge (C2 Wichtig-Siegel → C1 Notizen)
3. **Paket B** — Miliz (B1 Status-Filter → B2 Wanted-Poster → B3 Briefkasten → B4 Waffenkammer)

Reihenfolge begründet: A hat die geringsten Abhängigkeiten (neue DB). C baut auf bestehendem Schema auf. B ist das umfangreichste Paket.
