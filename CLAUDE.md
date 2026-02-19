# CLAUDE.md - Dämmerhafen Project Guide

## Project Overview

**Dämmerhafen** ("Twilight Harbor") is a medieval/fantasy roleplay portal and document management platform. It is a PHP web application with SQLite databases, designed for deployment on Synology NAS (Web Station with PHP-FPM) or shared hosting.

**Dämmerhafen 2.0** adds a hybrid frontend layer: **Vue 3 Islands** + **Tailwind CSS v4** built with **Vite**, while PHP remains the server-rendered backbone. Assets are built locally on Windows, only compiled files (`dist/app.js`, `dist/app.css`) are deployed to the NAS. No Node.js required on the server.

All UI text, variable names, and comments are in **German**.

## Architecture

### Core Pages (root level)
- `index.php` — Landing page / portal entry
- `daemmerhafen.php` — Alternative home portal
- `bibliothek.php` — Library: file/document management with WoW item quality system, loan tracking, MD/HTML/TXT lightbox viewer
- `miliz.php` — Militia: organizational hub with 6 categorized sections
- `aushaenge.php` — Bulletin board: community notice posting
- `verwaltung.php` — Administration (under development)
- `login.php` — Standalone login page
- `admin.php` — User & role management dashboard (meister-only)

### Shared Includes
- `header.php` — Global header template (included first in every page)
- `footer.php` — Global footer template (lightbox, mobile menu JS)
- `functions.php` — Core functions: auth, permissions, uploads, rich text, CSRF, sessions, role CRUD, bibliothek DB + loan system
- `functions_aushaenge.php` — Bulletin board DB operations
- `functions_miliz.php` — Militia DB operations

### Directories
| Directory | Purpose |
|-----------|---------|
| `auth/` | User auth DB (`users.db`) with users, roles, and role_permissions tables + init script |
| `miliz/` | Militia DB (`miliz.db`) + category subdirs (befehle, steckbriefe, gesucht, protokolle, waffenkammer, intern) |
| `aushaenge/` | Bulletin DB (`aushaenge.db`) + `bilder/` for images |
| `books/bibliothek/` | Book metadata |
| `uploads/` | Public file archive (including .html, .md, .txt documents) |
| `uploads/verboten/` | Restricted "legendary" items |
| `tools/` | Dev utilities (hotspot tool, security tests) |
| `changelog/` | Version history files |
| `frontend/` | **2.0** Vue/Tailwind source code (NOT deployed to NAS) |
| `frontend/src/islands/` | **2.0** Vue Island components (.vue files) |
| `frontend/src/api/` | **2.0** JavaScript API client |
| `dist/` | **2.0** Compiled assets (app.js, app.css) — deployed to NAS |
| `api/` | **2.0** JSON API endpoints for Vue components |

## Databases (SQLite via PDO)

Four SQLite databases:

1. **`auth/users.db`** — Three tables:
   - `users` (id, username, password_hash, role, created_at, last_login) — FK to roles.name
   - `roles` (id, name, display_name, icon, color, is_system, created_at) — Role definitions
   - `role_permissions` (id, role_name, section, can_read, can_write, can_upload) — Permission matrix

2. **`miliz/miliz.db`** — Five tables:
   - `miliz_entries` (id, category, title, content, author, file_path, created_at, updated_at, priority, visible, status) — General entries with status filter (aktiv/fluechtig/inhaftiert/verstorben)
   - `miliz_briefkasten` (id, betreff, nachricht, absender, erstellt_am, gelesen, gelesen_von, gelesen_am) — Anonymous citizen tips
   - `miliz_waffenkammer` (id, name, beschreibung, bestand, zustand, erstellt_am, aktualisiert_am) — Armory inventory (**Zustand**: gut/beschaedigt/defekt — kein "ausgegeben" als Zustand)
   - `miliz_waffenkammer_ausgaben` (id, item_id FK, ausgegeben_an, anzahl, ausgegeben_am) — Individual loan records per item (CASCADE DELETE)

3. **`aushaenge/aushaenge.db`** — Two tables:
   - `zettel` (id, titel, inhalt, signatur, bild_pfad, datum, author_id, format_type, ist_wichtig, angeheftet) — Bulletin board posts
   - `zettel_notizen` (id, zettel_id, text, autor_name, autor_id, erstellt_am) — Comments on posts (FK CASCADE)

4. **`uploads/bibliothek.db`** — Three tables:
   - `file_metadata` (id, filename, category, quality, description, uploaded_by, uploaded_at, last_read_by, last_read_at) — File metadata with manual quality override
   - `read_log` (id, filename, reader_name, read_at) — Legacy reading log
   - `bibliothek_ausleihen` (id, filename, ausgeliehen_von, ausgeliehen_am, zurueckgegeben_am) — **Active and historical loan tracking** (`zurueckgegeben_am IS NULL` = active)

All SQL uses **prepared statements with named parameters** via PDO. No ORM.

## Authentication & Roles

### Dynamic Role System
Roles are stored in the database and fully configurable via the admin panel. The meister can:
- **Create new roles** with custom name, display name, icon (emoji), and color
- **Configure permissions** per role for each of 4 sections (Bibliothek, Miliz, Aushänge, Verwaltung)
- **Three permission toggles** per section: Lesen (read), Schreiben (write), Upload
- **Edit role appearance** (display name, icon, color)
- **Delete custom roles** (only if no users are assigned; system roles cannot be deleted)

### Default Roles (system roles, cannot be deleted)
1. **meister** — Full admin access (hardcoded bypass in all permission checks)
2. **bibliothekar** — Library read/write/upload, read elsewhere
3. **miliz** — Militia read/write/upload, read elsewhere
4. **buerger** — Read-only, can write Aushänge

### Permission Sections
Defined in `PERMISSION_SECTIONS` constant: `bibliothek`, `miliz`, `aushaenge`, `verwaltung`

### Key Auth & Permission Functions in `functions.php`
- `loginUser()`, `logoutUser()`, `isLoggedIn()`
- `hasRole($role)`, `hasAnyRole($roles)`, `isMeister()` — Legacy role checks (still used for meister-only guards)
- `hasPermission($section, $action)` — **Primary permission check** (cached per request, meister always true)
- `requirePermission($section, $action)` — 403 if denied
- `requireLogin()`, `requireRole($role)`
- Rate limiting: 5 attempts, 5-minute lockout

### Role Management Functions in `functions.php`
- `getAllRoles()`, `getRoleInfo($roleName)`, `getRolePermissions($roleName)`
- `createRole($name, $displayName, $icon, $color)` — Creates role + initializes empty permissions
- `deleteRole($roleName)` — Only non-system roles with no assigned users
- `updateRolePermissions($roleName, $section, $canRead, $canWrite, $canUpload)`
- `updateRoleDisplay($roleName, $displayName, $icon, $color)`
- `getUserCountByRole($roleName)`

### Auto-Migration
`migrateRolesSystem($db)` runs automatically on first `getUserDB()` call. It:
- Creates `roles` and `role_permissions` tables if they don't exist
- Seeds the 4 default system roles with permissions matching previous hardcoded behavior
- Recreates the `users` table without the old CHECK constraint (adds FK to roles)
- Enables `PRAGMA foreign_keys = ON`

## Bibliothek — Feature Overview

### Qualitätssystem (WoW-Style)
- **Auto-Erkennung** per Dateiendung (pdf→rare, epub→epic, etc.)
- **Manuelle Überschreibung** per Dropdown auf jeder Karte (nur mit `bibliothek write`)
- Dropdown oben-rechts auf Karte (bei Hover): Automatisch / Gewöhnlich / Ungewöhnlich / Selten / Episch / Legendär
- `setFileQuality($filename, $quality)` setzt `null` für Auto

### Ausleihe-System
- **Kein Login erforderlich** — jeder kann unter eigenem Namen ausleihen
- Karte zeigt blaues „📖 Ausgeliehen"-Badge wenn aktiv ausgeliehen
- Karten-Unterleiste (`.rp-card__bottom-bar`): links Ausleihe, rechts 🔥 Löschen
- Ausleihe-Panel klappt per JS auf (`toggleAusleihePanel()`), Namenseingabe + ✓/✗
- Aktive Ausleihe: zeigt Name + ↩ Zurückgeben-Button (kein Login nötig)
- Tooltip zeigt Ausleihenden + Datum
- DB: `bibliothek_ausleihen`, aktiv = `zurueckgegeben_am IS NULL`

**Funktionen in `functions.php`:**
- `ausleihenDatei($filename, $name)` — prüft ob bereits ausgeliehen, speichert
- `zurueckgebenDatei($id)` — setzt `zurueckgegeben_am = now`
- `getAktiveAusleihe($filename)` — aktive Ausleihe oder null
- `getAusleihen($filename, $nurAktive)` — komplette Ausleih-Historie

### Lightbox-Viewer (MD / TXT / HTML)
Alle drei Typen öffnen eine modale Lightbox ohne Seitennavigation:

| Typ | JS-Funktion | Methode | Sicherheit |
|-----|-------------|---------|------------|
| `.md` | `openMarkdownLightbox()` | `fetch()` + `markdownToHtml()` | Vollständig escaped vor Parsing |
| `.txt` | `openTextLightbox()` | `fetch()` + `<pre>` escaped | Vollständig escaped |
| `.html` | `openHtmlLightbox()` | `<iframe sandbox="...">` | allow-scripts (Fonts), kein allow-forms/popups/top-nav |
| `.pdf` | `openLightbox()` | `<iframe>` (global) | Standard |
| Bild | `openLightbox()` | `<img>` (global) | Standard |

**Markdown-Parser** (`markdownToHtml()` in `bibliothek.php`) unterstützt vollständig:
- H1–H6, **fett**, *kursiv*, ***beides***, ~~Durchgestrichen~~, `Inline-Code`, ^Hochgestellt^
- Code-Blöcke (` ``` `) — Inhalt vollständig escaped, vor dem Parser extrahiert
- Tabellen (`|col|col|`), Blockquotes (`>`), Horizontale Linien (`--- *** ___`)
- Ungeordnete / geordnete / verschachtelte Listen (4-Space-Einrückung)
- Checklisten (`- [x]` / `- [ ]`) mit disabled checkboxes
- Fußnoten (`[^id]` + `[^id]: Text`) — gesammelt und am Ende gerendert
- `<details>`/`<summary>` — escaped, dann sicher wiederhergestellt
- Links (nur http/https), relative Bilder (`![alt](pfad)`)

### Upload-Whitelist (Bibliothek)
Erlaubte Extensions inkl. neu:
```
pdf, txt, md, doc, docx, xls, xlsx, zip, rar, jpg, jpeg, png, gif, webp, mp4, mov, epub, html, htm
```
- `text/html` in `ALLOWED_MIMES` enthalten
- `.html`/`.htm` erscheint im **Bücher**-Reiter

### Karten-Layout Übersicht
```
┌─────────────────────────────┐
│ [★ Qualität ▼]  (oben re.) │  ← nur bei Hover, nur write-Berechtigung
│                             │
│   [Icon / Vorschaubild]     │
│   Dateiname                 │
│   Größe  [Kategorie]        │
│                             │
│ [📖 Ausgeliehen-Badge]      │  ← oben links, wenn ausgeliehen
│ [📖 Ausleihen] [Eingabe ✓✗] │  ← Unterleiste links
│                         [🔥]│  ← Unterleiste rechts (write)
└─────────────────────────────┘
```

## Miliz — Feature Overview

### Status-System
- `MILIZ_STATUS_VALUES`: `aktiv` (🟢), `fluechtig` (🟠), `inhaftiert` (⚪), `verstorben` (💀)
- Filterbar per URL-Parameter `?cat=steckbriefe&status=aktiv`
- `getMilizEntries($category, $visibleOnly, $statusFilter)` — optionaler Filter
- `updateMilizEntryStatus($id, $status)` — Status ändern

### Gesucht-Poster (`?cat=gesucht`)
- Entries werden als `.wanted-poster` Karten gerendert (Plakat-Stil)
- Status-Filterleiste oben, Inline-Statusänderung für miliz/meister

### Bürger-Briefkasten (`?cat=intern`)
- Anonyme Hinweise ohne Login (Rate-Limit: 5 Min. Cooldown per Session)
- Miliz/Meister: Eingang mit Ungelesen-Badge, Gelesen markieren, Löschen
- Funktionen: `createBriefkastenNachricht()`, `getBriefkastenNachrichten()`, `markBriefkastenGelesen()`, `deleteBriefkastenNachricht()`, `getBriefkastenUnreadCount()`

### Waffenkammer-Inventar (`?cat=waffenkammer`)
**Zustand** (physischer Zustand) und **Ausgegeben** sind zwei getrennte Konzepte:

- `WAFFENKAMMER_ZUSTAND`: `gut`, `beschaedigt`, `defekt` — **kein `ausgegeben`**
- Bestandsanzeige: **Gesamt / Ausgegeben / Verfügbar** (farbcodiert grün/gelb/rot)
- ⚙️-Dropdown: Stammdaten (Name, Bestand, Beschreibung) + Löschen
- 📤-Dropdown: Ausgabe registrieren (Verfügbarkeits-Check) + aktive Ausgaben mit ↩ Rückbuchen

**Funktionen in `functions_miliz.php`:**
- `createWaffenkammerItem($name, $beschreibung, $bestand, $zustand)`
- `getWaffenkammerItems()` — inkl. `ausgegeben_gesamt` + `verfuegbar` per SQL-Subquery
- `getWaffenkammerAusgaben($itemId)` — aktive Ausgaben eines Items
- `createWaffenkammerAusgabe($itemId, $ausgegeben_an, $anzahl)` — prüft Verfügbarkeit
- `deleteWaffenkammerAusgabe($ausgabeId)` — Rückbuchung
- `updateWaffenkammerItem($id, $data)` — Stammdaten (ohne ausgegeben_an)
- `deleteWaffenkammerItem($id)` — löscht auch Ausgaben per CASCADE

## Aushänge — Feature Overview

### Notizen
- `zettel_notizen` Tabelle (FK CASCADE auf `zettel`)
- `addNotiz()`, `getNotizen()`, `getNotizCount()`, `deleteNotiz()`
- CSS: `.zettel-notiz` mit Büroklammer-Pseudo-Element

### Wichtig-Siegel & Angeheftet
- `ist_wichtig` → rote Umrandung (`.zettel--wichtig`), Wachs-Siegel `::after`
- `angeheftet` → goldene Oberkante + Badge, sortiert zuerst (`ORDER BY angeheftet DESC, datum DESC`)
- `toggleWichtig($id)`, `toggleAngeheftet($id)` — nur Meister

## Coding Conventions

### Naming
- **Functions:** camelCase — `loginUser()`, `createUser()`, `handleUpload()`, `hasPermission()`
- **DB getters:** `get[Entity]()` pattern
- **Booleans:** `is`/`has` prefix — `isMeister()`, `hasRole()`, `isLoggedIn()`, `hasPermission()`

### Permission Check Pattern (always use this for section access)
```php
// Reading: check if user can view the section
if (hasPermission('bibliothek', 'read')) { ... }

// Writing: check if user can create/edit/delete
if (hasPermission('miliz', 'write')) { ... }

// Uploading: check if user can upload files
if (hasPermission('bibliothek', 'upload')) { ... }

// Meister-only operations (admin panel, user management)
if (isMeister()) { ... }
```

### Security Patterns (always follow these)
- **Direct access prevention** on all helper PHP files:
  ```php
  if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
      http_response_code(403);
      die('Direct access not allowed');
  }
  ```
- **CSRF tokens** on every form via `generateCSRFToken()` / `verifyCSRFToken()`
- **Prepared statements** for all SQL — never concatenate user input
- **Output escaping** with `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` on all user-facing output
- **File uploads** validated by extension whitelist, MIME type, and size (320 MB max)
- **HTML-Lightbox** uses `sandbox="allow-same-origin allow-scripts"` — no `allow-forms`, no `allow-top-navigation`, no `allow-popups`
- **Markdown rendering** always escapes HTML entities before parsing — XSS not possible

### Page Include Pattern
Pages set flags before including `header.php`:
```php
$includeAushaenge = true;
$pageTitle = 'Seitentitel';
$bodyClass = 'css-class';
require_once 'header.php';
```

### Message Pattern
- **Immediate:** `$message = ['type' => 'success', 'text' => '...'];` (same request)
- **Flash (PRG):** `$_SESSION['flash'] = ['type' => 'success', 'text' => '...'];` then redirect

### DB Connection Pattern
Singleton pattern in getter functions:
```php
static $db = null;
if ($db === null) { /* PDO init */ }
return $db;
```

### DB Migration Pattern
- `CREATE TABLE IF NOT EXISTS` + `ALTER TABLE ADD COLUMN` for backward compat
- Auto-migration in `getUserDB()` via `migrateRolesSystem()`
- Auto-migration in `getBibliothekDB()` via `migrateBibliothekDB()`
- Auto-migration in `initMilizDB()` inline
- `PRAGMA foreign_keys = ON` in every DB connection

## Styling

### style.css (Medieval Theme)
- Medieval parchment theme with WoW-inspired elements
- **CSS Custom Properties** for colors, fonts, z-index layers
- **Fonts:** MedievalSharp (headings), Crimson Text (body), Cinzel (elegant) — via Google Fonts
- **WoW quality colors:** common (gray), uncommon (green), rare (blue), epic (purple), legendary (orange)
- Responsive design with mobile hamburger menu
- Key classes: `.top-nav`, `.rp-bg-fullscreen`, `.rp-view-room`, `.rp-view-immersive`, `.msg`, `.lightbox`
- Role badges use dynamic inline styles from DB (`role.color`)

### CSS-Sektionsnummern (style.css)
| § | Inhalt |
|---|--------|
| 1–20 | Basis-Layout, Navigation, Karten, Formulare, Upload, etc. |
| 20b | Bibliothek Karten-Unterleiste (`.rp-card__bottom-bar`, Ausleihe, Löschen) |
| 20c | Text-Lightbox (`.bib-text-lightbox`, `.bib-text-modal`, `.bib-md-table`, `.bib-code-block`, `.bib-footnotes`) |
| 21 | CSS Book Covers (`.rp-book-cover` mit Quality-Farben) |
| 22 | Quality-Labels (`.rp-card__quality-label--manual/auto`) |
| 23 | Wanted-Poster (`.wanted-poster` Layout) |
| 24 | Zettel-Notizen (`.zettel-notiz` mit Büroklammer) |
| 25 | Wichtig-Siegel + Angeheftet (`.zettel--wichtig`, `.zettel--angeheftet`) |
| 26 | Waffenkammer-Zustandsbadges (`.inventar-zustand--gut/beschaedigt/defekt`) |
| 27 | Miliz Status-Filter (`.miliz-filter`, `.miliz-status-badge--*`) |
| 28 | Briefkasten (`.briefkasten-form`, `.briefkasten-badge--ungelesen`) |

### Tailwind CSS v4 (2.0 Addition — dist/app.css)
- **Prefix:** `tw` — all Tailwind classes use `tw:` prefix to avoid conflicts (e.g. `tw:flex`, `tw:p-4`)
- Configured in `frontend/src/style.css` via `@import "tailwindcss" prefix(tw)`
- Theme tokens map to existing CSS variables (wood, parchment, gold, ink, etc.)
- **Augments** — does not replace the medieval theme
- Built via Vite, output to `dist/app.css`

## Text Formatting

- `parseRichText($text)` — Markdown-style to HTML (bold, italic, lists, links, line breaks)
- `parseRichTextSimple($text)` — Lighter version for titles (bold/italic only)
- `sanitizeHTML($html)` — Whitelist-based HTML sanitizer (strips events, JS URLs)
- `markdownToHtml()` (JS, inline in `bibliothek.php`) — Full client-side MD parser for lightbox display

## File Upload System

- Max size: 320 MB
- Allowed extensions: `pdf, txt, md, doc, docx, xls, xlsx, zip, rar, jpg, jpeg, png, gif, webp, mp4, mov, epub, html, htm`
- Allowed MIME types include `text/html`
- MIME validation via `finfo`
- Filename sanitization (strip special chars, deduplicate with counter suffix)
- `.html`/`.htm` categorized as **Bücher** in bibliothek.php

## Frontend 2.0 — Vue Islands Architecture

### Philosophy
- PHP renders the page, Vue **enhances** specific sections
- Vue does NOT control routing — PHP handles all navigation
- Each interactive component is a self-contained "island"

### Vue Island Pattern
In PHP templates:
```html
<div data-vue-island="ComponentName" data-props='{"key":"value"}'>
    <!-- Fallback content for non-JS -->
</div>
```

In `frontend/src/main.js`, islands auto-mount on `DOMContentLoaded` by scanning `[data-vue-island]` elements.

### Available Vue Islands
| Component | File | Used In | Purpose |
|-----------|------|---------|---------|
| `ToastNachricht` | `islands/ToastNachricht.vue` | `header.php` | Animated success/error messages |
| `DateiUpload` | `islands/DateiUpload.vue` | `bibliothek.php` | Drag-drop upload with progress bar |
| `DateiSuche` | `islands/DateiSuche.vue` | `bibliothek.php` | Live search with quality/sort filters |
| `ZettelFormular` | `islands/ZettelFormular.vue` | `aushaenge.php` | Rich bulletin board post form |
| `ModalDialog` | `islands/ModalDialog.vue` | Various | Reusable modal with teleport |
| `RollenMatrix` | `islands/RollenMatrix.vue` | `admin.php` | Interactive permission checkboxes |

### Adding a New Vue Island
1. Create `frontend/src/islands/MyComponent.vue`
2. Add mount point in PHP: `<div data-vue-island="MyComponent" data-props='...'>`
3. Run `npm run build` in `frontend/`
4. Deploy `dist/` to NAS

### API Client (`frontend/src/api/client.js`)
- Reads CSRF token from `<meta name="csrf-token">` (set in header.php)
- All requests include `X-CSRF-Token` header and `credentials: 'same-origin'`
- Functions: `apiFetch()`, `apiGet()`, `apiPost()`, `apiPostForm()`, `apiDelete()`, `apiUpload()`
- `apiUpload()` uses XMLHttpRequest for progress tracking

### JSON API Endpoints (`api/`)
| File | Methods | Purpose |
|------|---------|---------|
| `api/index.php` | (include only) | Shared helpers: `jsonResponse()`, `jsonError()`, `requireApiAuth()`, `validateApiCsrf()` |
| `api/upload.php` | POST | File upload with quality selection |
| `api/aushaenge.php` | GET, POST, DELETE | Bulletin board CRUD |
| `api/miliz.php` | GET, POST, DELETE | Militia entries CRUD |

### API Security Pattern
```php
require_once __DIR__ . '/index.php';
allowMethods(['GET', 'POST']);
validateApiCsrf();                          // Checks X-CSRF-Token header
requireApiPermission('bibliothek', 'write'); // Session auth + permission check
// ... handle request ...
jsonSuccess('Ergebnis', ['id' => $newId]);
```

## Deployment

### Production (Synology NAS)
- PHP 7.2+ required, SQLite PDO extension required
- Config: `.htaccess_synology` (security headers, access rules), `.user.ini` (PHP settings)
- `robots.txt` disallows all crawlers (private site)
- NAS serves: PHP files + `style.css` + `dist/app.js` + `dist/app.css`
- **No Node.js on NAS** — only compiled static assets

### Build Process (Local Windows)
```bash
cd frontend
npm install       # First time only
npm run build     # Builds dist/app.js + dist/app.css
```

### Deploy Workflow
1. Build locally: `cd frontend && npm run build`
2. Sync project to NAS (include `dist/`, exclude `frontend/node_modules/`)
3. NAS serves compiled assets via `header.php` / `footer.php` (with `file_exists()` guards)

### Branch-Strategie
- **`main`** — Stable production branch
- **`nightly`** — Aktive Weiterentwicklung, neueste Features
- **`stagingLocal`** — Lokales Staging / Experimente

**Bibliothek-Features aus `stagingLocal` in `nightly` übernehmen:**
```bash
# PHP-Dateien direkt übernehmen (Bibliothek wurde komplett neu geschrieben)
git checkout stagingLocal -- bibliothek.php
git checkout stagingLocal -- functions.php
git checkout stagingLocal -- functions_miliz.php
git checkout stagingLocal -- miliz.php

# style.css: NUR neue Sektionen 20b + 20c manuell einfügen
# (nightly hat eigene CSS-Änderungen → kein blindes checkout)
git diff stagingLocal nightly -- style.css
```

**KI-Rekonstruktion:** Alle Bibliothek-Features können aus dem Seitenquelltext + den drei Quelldateien (`bibliothek.php`, `functions.php`, `style.css`) eines anderen Branches vollständig rekonstruiert werden — der komplette JS-/CSS-Code steckt inline in `bibliothek.php`, kein externer Build nötig.

## Development Notes

- `.user.ini` currently has `display_errors = On` (development mode)
- Tools in `tools/` directory for hotspot editing, security testing, version checking
- Changelogs tracked in `changelog/` directory
- IDE config in `.idea/` (JetBrains PhpStorm)
- Existing users must re-login after the roles migration to get `role_display`/`role_icon` in their session
- **Frontend dev:** `cd frontend && npm run dev` for Vite HMR (requires PHP running separately)
- **Frontend build:** `cd frontend && npm run build` outputs to `dist/`
- **Graceful degradation:** If `dist/app.js` doesn't exist, PHP falls back to inline JS (e.g. message toasts)
