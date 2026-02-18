# CLAUDE.md - Dämmerhafen Project Guide

## Project Overview

**Dämmerhafen** ("Twilight Harbor") is a medieval/fantasy roleplay portal and document management platform. It is a PHP web application with SQLite databases, designed for deployment on Synology NAS (Web Station with PHP-FPM) or shared hosting.

**Dämmerhafen 2.0** adds a hybrid frontend layer: **Vue 3 Islands** + **Tailwind CSS v4** built with **Vite**, while PHP remains the server-rendered backbone. Assets are built locally on Windows, only compiled files (`dist/app.js`, `dist/app.css`) are deployed to the NAS. No Node.js required on the server.

All UI text, variable names, and comments are in **German**.

## Architecture

### Core Pages (root level)
- `index.php` — Landing page / portal entry
- `daemmerhafen.php` — Alternative home portal
- `bibliothek.php` — Library: file/document management with WoW item quality system
- `miliz.php` — Militia: organizational hub with 6 categorized sections
- `aushaenge.php` — Bulletin board: community notice posting
- `verwaltung.php` — Administration (under development)
- `login.php` — Standalone login page
- `admin.php` — User & role management dashboard (meister-only)

### Shared Includes
- `header.php` — Global header template (included first in every page)
- `footer.php` — Global footer template (lightbox, mobile menu JS)
- `functions.php` — Core functions: auth, permissions, uploads, rich text, CSRF, sessions, role CRUD
- `functions_aushaenge.php` — Bulletin board DB operations
- `functions_miliz.php` — Militia DB operations

### Directories
| Directory | Purpose |
|-----------|---------|
| `auth/` | User auth DB (`users.db`) with users, roles, and role_permissions tables + init script |
| `miliz/` | Militia DB (`miliz.db`) + category subdirs (befehle, steckbriefe, gesucht, protokolle, waffenkammer, intern) |
| `aushaenge/` | Bulletin DB (`aushaenge.db`) + `bilder/` for images |
| `books/bibliothek/` | Book metadata |
| `uploads/` | Public file archive |
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
2. **`miliz/miliz.db`** — Three tables:
   - `miliz_entries` (id, category, title, content, author, file_path, created_at, updated_at, priority, visible, status) — General entries with status filter (aktiv/fluechtig/inhaftiert/verstorben)
   - `miliz_briefkasten` (id, betreff, nachricht, absender, erstellt_am, gelesen, gelesen_von, gelesen_am) — Anonymous citizen tips
   - `miliz_waffenkammer` (id, name, beschreibung, bestand, zustand, ausgegeben_an, erstellt_am, aktualisiert_am) — Armory inventory
3. **`aushaenge/aushaenge.db`** — Two tables:
   - `zettel` (id, titel, inhalt, signatur, bild_pfad, datum, author_id, format_type, ist_wichtig, angeheftet) — Bulletin board posts
   - `zettel_notizen` (id, zettel_id, text, autor_name, autor_id, erstellt_am) — Comments on posts (FK CASCADE)
4. **`uploads/bibliothek.db`** — Two tables:
   - `file_metadata` (id, filename, category, quality, description, uploaded_by, uploaded_at, last_read_by, last_read_at) — File metadata with manual quality override
   - `read_log` (id, filename, reader_name, read_at) — Reading/loan history

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
- **Output escaping** with `htmlspecialchars(ENT_QUOTES, 'UTF-8')` on all user-facing output
- **File uploads** validated by extension whitelist, MIME type, and size (320 MB max)

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
- Auto-migration in `getUserDB()` via `migrateRolesSystem()` for the roles/permissions system

## Styling

### style.css (Medieval Theme — unchanged)
- Medieval parchment theme with WoW-inspired elements
- **CSS Custom Properties** for colors, fonts, z-index layers
- **Fonts:** MedievalSharp (headings), Crimson Text (body), Cinzel (elegant) — via Google Fonts
- **WoW quality colors:** common (gray), uncommon (green), rare (blue), epic (purple), legendary (orange)
- Responsive design with mobile hamburger menu
- Key classes: `.top-nav`, `.rp-bg-fullscreen`, `.rp-view-room`, `.rp-view-immersive`, `.msg`, `.lightbox`
- Role badges use dynamic inline styles from DB (`role.color`)

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

## File Upload System

- Max size: 320 MB
- Allowed extensions: pdf, txt, md, doc, docx, xls, xlsx, zip, rar, jpg, jpeg, png, gif, webp, mp4, mov, epub
- MIME validation via `finfo`
- Filename sanitization (strip special chars, deduplicate with counter suffix)

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

## Development Notes

- `.user.ini` currently has `display_errors = On` (development mode)
- Tools in `tools/` directory for hotspot editing, security testing, version checking
- Changelogs tracked in `changelog/` directory
- IDE config in `.idea/` (JetBrains PhpStorm)
- Existing users must re-login after the roles migration to get `role_display`/`role_icon` in their session
- **Frontend dev:** `cd frontend && npm run dev` for Vite HMR (requires PHP running separately)
- **Frontend build:** `cd frontend && npm run build` outputs to `dist/`
- **Graceful degradation:** If `dist/app.js` doesn't exist, PHP falls back to inline JS (e.g. message toasts)
