# 📤 Upload-Limit auf 320 MB erhöhen

## ✅ Automatische Lösung (empfohlen)

Die aktualisierten Dateien enthalten bereits alle Änderungen:

### 1. `functions.php` (Zeile 26)
```php
define('MAX_FILE_SIZE', 320 * 1024 * 1024); // 320 MB
```

### 2. `.htaccess` (oben hinzugefügt)
```apache
php_value upload_max_filesize 320M
php_value post_max_size 325M
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 512M
```

**→ Einfach die neuen Dateien hochladen, fertig!** 🎉

---

## ⚠️ Falls es nicht funktioniert

Manche Webhoster erlauben keine PHP-Werte in `.htaccess`.

### Symptom:
- Upload bricht ab bei großen Dateien
- "500 Internal Server Error" nach Upload-Versuch
- Fehlermeldung in error.log

### Lösung 1: `php.ini` im Hauptverzeichnis erstellen

Erstelle eine Datei namens `php.ini` neben `index.php`:

```ini
upload_max_filesize = 320M
post_max_size = 325M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```

### Lösung 2: Hoster-Panel (z.B. cPanel, Plesk)

1. Gehe zu **PHP-Einstellungen** / **Select PHP Version**
2. Setze:
   - `upload_max_filesize` → 320M
   - `post_max_size` → 325M
   - `max_execution_time` → 300
   - `memory_limit` → 512M

### Lösung 3: .user.ini (Alternative zu php.ini)

Falls `php.ini` nicht funktioniert, versuche `.user.ini`:

```ini
upload_max_filesize = 320M
post_max_size = 325M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```

---

## 🧪 Testen

Nach dem Upload prüfen:

1. Erstelle eine Testdatei mit 100 MB
2. Versuche sie hochzuladen
3. Funktioniert? ✅ Fertig!
4. Fehler? → Schau in Lösung 1-3

---

## 📊 Was bedeuten die Werte?

| Einstellung | Wert | Bedeutung |
|------------|------|-----------|
| `upload_max_filesize` | 320M | Maximale Dateigröße pro Upload |
| `post_max_size` | 325M | Maximale Größe aller POST-Daten (muss größer sein!) |
| `max_execution_time` | 300s | Max. 5 Minuten für Upload-Verarbeitung |
| `max_input_time` | 300s | Max. 5 Minuten zum Empfangen der Datei |
| `memory_limit` | 512M | Arbeitsspeicher für PHP-Script |

---

## 💡 Pro-Tipp

Falls du später noch größere Dateien brauchst (z.B. Videos):

```php
// In functions.php ändern:
define('MAX_FILE_SIZE', 1024 * 1024 * 1024); // 1 GB
```

```apache
# In .htaccess/php.ini ändern:
upload_max_filesize = 1024M
post_max_size = 1100M
```

**Beachte:** Sehr große Uploads können deinen Server belasten!

---

## ❓ Probleme?

**Upload funktioniert nicht:**
1. Prüfe `error.log` deines Servers
2. Teste mit kleiner Datei (5 MB) → funktioniert?
3. Prüfe PHP-Version: `<?php phpinfo(); ?>` in test.php
4. Kontaktiere Hoster-Support

**Uploads dauern ewig:**
- Normal bei 320 MB! (Abhängig von Upload-Geschwindigkeit)
- Bei 1 Mbit/s Upload = ~40 Minuten für 320 MB
- Bei 10 Mbit/s Upload = ~4 Minuten

---

**Alles klar? Viel Erfolg mit deiner Bibliothek!** 🏰📚
