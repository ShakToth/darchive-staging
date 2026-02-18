# 🔒 Sicherheits- und Feature-Updates für "Die Bibliothek"

## ✅ Implementierte Verbesserungen

### 🔴 KRITISCHE SICHERHEIT

#### 1. CSRF-Schutz (Cross-Site Request Forgery)
- **Problem behoben:** Alle Formulare waren anfällig für CSRF-Angriffe
- **Lösung:** 
  - Neue Funktionen: `generateCSRFToken()` und `verifyCSRFToken()`
  - Alle Formulare enthalten jetzt ein `csrf_token` Hidden-Field
  - Alle POST-Actions prüfen den Token vor der Ausführung
- **Betroffene Actions:** Login, Upload, Löschen

#### 2. Upload-Validierung
- **Problem behoben:** Jede Datei konnte hochgeladen werden (inkl. .php Shell-Scripts!)
- **Lösung:**
  - **Extension Whitelist:** Nur erlaubte Dateitypen (pdf, txt, md, doc, docx, xls, xlsx, zip, rar, jpg, png, gif, webp, mp4, mov, epub)
  - **MIME-Type Validierung:** Zusätzliche Prüfung des echten Dateityps
  - **Größenlimit:** Max. 50 MB pro Datei (konfigurierbar)
  - **Duplikat-Schutz:** Automatisches Umbenennen bei gleichem Dateinamen (datei.pdf → datei_1.pdf)
- **Konfiguration in:** `functions.php` → Konstanten `MAX_FILE_SIZE`, `ALLOWED_EXTENSIONS`, `ALLOWED_MIMES`

#### 3. POST-basierte Löschung
- **Problem behoben:** Dateien konnten per GET-Link gelöscht werden
- **Lösung:** 
  - Löschen funktioniert jetzt nur noch per POST-Formular
  - Verhindert versehentliches Löschen durch Link-Klick oder Browser-Prefetch
  - CSRF-Token zusätzlich erforderlich

#### 4. Login Rate-Limiting
- **Problem behoben:** Unbegrenzte Login-Versuche ermöglichten Brute-Force-Angriffe
- **Lösung:**
  - Max. 5 Fehlversuche, dann 5 Minuten Sperre
  - Session-basierte Zählung
  - Benutzerfreundliche Fehlermeldungen mit verbleibenden Versuchen
- **Konfiguration:** `MAX_LOGIN_ATTEMPTS` und `LOGIN_LOCKOUT_TIME` in `functions.php`

---

### 🟢 NEUE FEATURES

#### 5. Kategorieübergreifende Suche (Dein Vorschlag!)
- **Feature:** Suche über alle Bereiche gleichzeitig (Normal + Verboten)
- **Funktion:** `getAllFiles($searchQuery)`
- **UI:** 
  - Suchfeld durchsucht automatisch alle Kategorien
  - Ergebnisse zeigen Kategorie-Badge (📚 Normal / ⛔ Verboten)
  - Kategorie-spezifische Suche weiterhin möglich

#### 6. Datei-Metadaten
- **Dateigröße:** Wird jetzt angezeigt (KB/MB/GB formatiert)
- **Upload-Datum:** Gespeichert und abrufbar (noch nicht im UI angezeigt)
- **Helper-Funktion:** `formatFileSize($bytes)` für schöne Darstellung

#### 7. Verbesserte Benutzerfreundlichkeit
- **Upload-Feedback:** Genauere Fehlermeldungen
  - "Datei zu groß! Maximum: 50 MB"
  - "Dateityp nicht erlaubt! Nur: pdf, txt, md..."
  - "Ungültiger Dateityp erkannt!" (MIME-Type-Fehler)
- **Löschen-Bestätigung:** Browser-Dialog vor dem Löschen
- **Dateiname im Erfolg:** "Schriftrolle 'rezept.pdf' erfolgreich archiviert!"

---

## 📁 Geänderte Dateien

### `functions.php`
- ✅ CSRF-Token Funktionen
- ✅ Upload-Validierung (Extension, MIME, Size)
- ✅ Login mit Rate-Limiting
- ✅ Duplikat-Schutz beim Upload
- ✅ `getAllFiles()` für kategorieübergreifende Suche
- ✅ `formatFileSize()` Helper
- ✅ Erweiterte Konstanten für Sicherheit
- ✅ Code-Cleanup (Duplikate entfernt)

### `index.php`
- ✅ CSRF-Token Integration in allen Formularen
- ✅ POST-basierte Löschung mit Formular
- ✅ Kategorieübergreifende Suche implementiert
- ✅ Kategorie-Badge in Suchergebnissen
- ✅ Dateigröße-Anzeige
- ✅ Verbesserte Sicherheitsprüfungen

### `style.css`
- ✅ Neue Styles für `.file-info`
- ✅ Neue Styles für `.file-meta`
- ✅ Kategorie-Badges (`.category-badge`, `.badge-forbidden`)
- ✅ Verbesserte `.delete-btn` Position (jetzt oben rechts)
- ✅ Responsive Verbesserungen
- ✅ Bessere Card-Layouts

### `.htaccess`
- ✅ Schutz für `.htaccess` selbst hinzugefügt

---

## 🔧 Konfigurationsmöglichkeiten

In `functions.php` kannst du folgende Werte anpassen:

```php
// Upload-Limits
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB (anpassbar)
define('ALLOWED_EXTENSIONS', ['pdf', 'txt', ...']); // Erlaubte Dateitypen

// Login-Sicherheit
define('MAX_LOGIN_ATTEMPTS', 5); // Max. Fehlversuche
define('LOGIN_LOCKOUT_TIME', 300); // Sperrzeit in Sekunden (5 Min)
```

---

## 🚀 Installation

1. **Alle 4 Dateien ersetzen:**
   - `functions.php`
   - `index.php`
   - `style.css`
   - `.htaccess`

2. **Ordner-Struktur prüfen:**
   ```
   /
   ├── index.php
   ├── functions.php
   ├── style.css
   ├── .htaccess
   ├── room.jpg
   ├── bg.jpg (falls vorhanden)
   └── uploads/
       └── verboten/
   ```

3. **Schreibrechte prüfen:**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/verboten/
   ```

4. **Fertig!** 🎉

---

## 🎮 WoW-Style Verbesserungen (Optional)

Falls du noch mehr WoW-Feeling willst:

### Mögliche Erweiterungen:
- **Quest-Log Style:** Upload als "Quest abgeben"
- **Achievement System:** "50 Schriftrollen archiviert!"
- **Tabs statt Kategorien:** WoW-UI-Style Tabs
- **Tooltip auf Hover:** Zeige Dateigröße + Upload-Datum
- **Runen-Animation:** Beim Upload/Löschen
- **Sound-Effekte:** Quest-Complete Sound

---

## 📊 Sicherheits-Checkliste

- ✅ CSRF-Schutz aktiv
- ✅ Upload-Validierung (Extension + MIME)
- ✅ Größenlimit für Uploads
- ✅ POST-only für Löschungen
- ✅ Login Rate-Limiting
- ✅ Session-Sicherheit (httponly, strict)
- ✅ .htaccess Schutz für sensible Dateien
- ✅ Keine Error-Ausgabe im Browser
- ⚠️ HTTPS empfohlen (aktiviere `session.cookie_secure`)

---

## 🐛 Bekannte Limitierungen

- **Mehrfach-Upload:** Noch nicht implementiert (nur eine Datei gleichzeitig)
- **Sortierung:** Noch keine Sortier-Optionen (Name, Datum, Größe)
- **Upload-Progress:** Keine Fortschrittsanzeige
- **Datei-Vorschau:** PDFs können noch nicht inline angezeigt werden

---

## 💬 Feedback

Fragen oder Probleme? Die wichtigsten Verbesserungen sind:
1. **CSRF-Schutz** → Verhindert Remote-Angriffe
2. **Upload-Validierung** → Keine PHP-Shells mehr!
3. **Rate-Limiting** → Schutz vor Brute-Force

**Viel Spaß mit deiner sicheren Bibliothek!** 🏰📚✨
