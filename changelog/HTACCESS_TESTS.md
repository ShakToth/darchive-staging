# ✅ .htaccess Sicherheits-Tests

## 🔒 Test 1: Directory Listing blockiert?

### Was testen wir?
Verhindert `.htaccess` dass man Ordnerinhalte direkt sehen kann?

### So testest du:
Öffne im Browser:
```
https://dämmerhafen.de/uploads/
```

**✅ RICHTIG:** Du siehst eine **leere Seite** oder **403 Forbidden**  
**❌ FALSCH:** Du siehst eine **Liste aller Dateien** im Ordner

---

## 🛡️ Test 2: Geschützte Dateien blockiert?

### Was testen wir?
Können sensible Dateien direkt aufgerufen werden?

### So testest du:

#### Test A: functions.php
```
https://dämmerhafen.de/functions.php
```
**✅ RICHTIG:** **403 Forbidden** oder leere Seite  
**❌ FALSCH:** Du siehst PHP-Code oder Download-Dialog

#### Test B: .htaccess selbst
```
https://dämmerhsfen.de/.htaccess
```
**✅ RICHTIG:** **403 Forbidden** oder 404  
**❌ FALSCH:** Du siehst den Inhalt der .htaccess

#### Test C: test.php (wenn noch vorhanden)
```
https://dämmerhafen.de/test.php
```
**✅ RICHTIG:** **403 Forbidden** (falls in FilesMatch)  
Oder: Funktioniert normal (dann manuell löschen!)

---

## 🔍 Test 3: Sicherheits-Header aktiv?

### Was testen wir?
Sendet der Server die Sicherheits-Header?

### So testest du:

**Methode 1: Browser DevTools**
1. Öffne deine Seite
2. Drücke **F12** (DevTools)
3. Gehe zu **Netzwerk** (Network)
4. Lade die Seite neu (F5)
5. Klicke auf den ersten Request (index.php)
6. Schaue unter **Headers** → **Response Headers**

**Solltest du sehen:**
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
```

**Methode 2: Online-Tool**
Öffne: https://securityheaders.com/
Gib deine URL ein: `https://dämmerhafen.de`

**✅ RICHTIG:** Grade **B** oder besser  
**⚠️ OK:** Grade **C** oder **D** (Header teilweise vorhanden)  
**❌ FALSCH:** Grade **F** (keine Header)

---

## 📊 Schnell-Checkliste

Teste diese 4 URLs nacheinander:

| Test | URL | Erwartetes Ergebnis |
|------|-----|---------------------|
| 1️⃣ | `https://dämmerhafen.de/uploads/` | 403 Forbidden |
| 2️⃣ | `https://dämmerhafen.de/functions.php` | 403 Forbidden |
| 3️⃣ | `https://dämmerhafen.de/.htaccess` | 403 Forbidden |
| 4️⃣ | `https://dämmerhafen.de/` (F12 → Headers) | Sicherheits-Header vorhanden |

---

## ⚠️ Wenn ein Test fehlschlägt:

### Test 1 fehlgeschlagen (Directory Listing sichtbar)
**Problem:** `Options -Indexes` funktioniert nicht  
**Lösung:** 
1. Prüfe ob `.htaccess_synology` wirklich aktiv ist (nicht die alte!)
2. Erstelle eine leere `index.html` in `/uploads/`:
```html
<!-- Leer, verhindert Directory Listing -->
```

### Test 2 fehlgeschlagen (Dateien zugänglich)
**Problem:** `FilesMatch` Direktive wird ignoriert  
**Lösung 1:** Ändere in `.htaccess` von:
```apache
<FilesMatch "...">
    Order allow,deny
    Deny from all
</FilesMatch>
```
Zu:
```apache
<FilesMatch "...">
    Require all denied
</FilesMatch>
```

**Lösung 2:** Falls auch das nicht klappt, nutze PHP-Lösung:
Füge ganz oben in `functions.php` hinzu:
```php
<?php
// Direkter Zugriff verboten
if (basename($_SERVER['PHP_SELF']) === 'functions.php') {
    http_response_code(403);
    die('Access denied');
}
```

### Test 3 fehlgeschlagen (Keine Header)
**Problem:** `mod_headers` nicht verfügbar oder inaktiv  
**Lösung:**
1. Web Station → Erweiterte Einstellungen
2. Apache Module aktivieren:
   - ✅ mod_headers
   - ✅ mod_rewrite
3. Apache neu starten

**Alternativ:** Header per PHP setzen (in `index.php` ganz oben):
```php
<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
require_once 'functions.php';
// Rest...
```

---

## 🎯 Optimale Testergebnisse

Wenn alles korrekt funktioniert:

1. ✅ `/uploads/` → **403 Forbidden**
2. ✅ `/functions.php` → **403 Forbidden**
3. ✅ `/.htaccess` → **403 Forbidden** oder **404**
4. ✅ Security Headers → **Grade B** oder besser

**Dann ist deine Bibliothek sicher! 🔒✨**

---

## 💡 Zusatz-Test: Upload-Limit

Teste ob die 320 MB funktionieren:

1. Erstelle eine große Test-Datei (z.B. 100 MB)
2. Versuche sie hochzuladen
3. **Funktioniert es?** ✅ Super!
4. **Fehler?** → Gehe zu Web Station PHP-Einstellungen

---

## 📋 Test-Protokoll (zum Ausfüllen)

```
Datum: _______________

[ ] Test 1: Directory Listing    → Ergebnis: ________
[ ] Test 2: functions.php        → Ergebnis: ________
[ ] Test 3: .htaccess            → Ergebnis: ________
[ ] Test 4: Security Headers     → Grade: __________
[ ] Bonus: 100MB Upload          → Ergebnis: ________

Notizen:
_________________________________________________
_________________________________________________
```

---

**Los geht's mit den Tests!** 🚀
Sag mir welche Tests durchgefallen sind, falls welche nicht klappen!
