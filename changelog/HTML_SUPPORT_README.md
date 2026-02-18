# 📖 HTML-Support in der Bibliothek

**Status:** ✅ Implementiert  
**Dateien geändert:** `footer.php`, `bibliothek.php`, `functions.php`

---

## 🎯 Was ist neu?

Du kannst jetzt **HTML-Dateien** in die Bibliothek hochladen und sie werden im Lightbox-Viewer angezeigt - mit **vollem CSS-Support aber ohne JavaScript** (aus Sicherheitsgründen).

### **Perfekt für:**
- 📜 Formatierte Geschichten & Chroniken
- 📖 Bücher mit schönem Layout
- 🎨 Künstlerisch gestaltete Texte
- 📝 Dokumente mit komplexem Styling

---

## 🔒 SICHERHEIT: Sandbox-Modus

### **Was ist erlaubt:**
✅ HTML-Struktur (Überschriften, Absätze, Listen, etc.)
✅ CSS-Styling (inline, `<style>`-Tags, externe Stylesheets)
✅ Bilder (als Base64 eingebettet oder aus erlaubten Quellen)
✅ Schriftarten (Google Fonts, etc.)
✅ Tabellen, Flexbox, Grid

### **Was ist BLOCKIERT:**
❌ JavaScript (keine `<script>`-Tags werden ausgeführt)
❌ Formulare (können nicht abgeschickt werden)
❌ Pop-ups
❌ Navigation zu anderen Seiten
❌ Cookies setzen
❌ LocalStorage-Zugriff

**Technische Umsetzung:** 
```html
<iframe sandbox="allow-same-origin" src="..."></iframe>
```

Das `sandbox`-Attribut verhindert alle potenziell gefährlichen Operationen.

---

## 📝 HTML-Datei erstellen

### **Beispiel 1: Einfacher Text**

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Meine Geschichte</title>
    <style>
        body {
            font-family: 'Georgia', serif;
            background: #f4e4bc;
            color: #2c1e12;
            padding: 40px;
            line-height: 1.8;
        }
        h1 {
            text-align: center;
            color: #8b5a2b;
            border-bottom: 2px solid #8b5a2b;
            padding-bottom: 20px;
        }
        p {
            text-align: justify;
            text-indent: 2em;
        }
    </style>
</head>
<body>
    <h1>Der Titel</h1>
    <p>Hier beginnt die Geschichte...</p>
</body>
</html>
```

### **Beispiel 2: Mit Google Fonts**

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Eleganter Text</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel&family=EB+Garamond&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'EB Garamond', serif;
            background: linear-gradient(135deg, #f4e4bc 0%, #e3d1a2 100%);
            padding: 60px;
        }
        h1 {
            font-family: 'Cinzel', serif;
            font-size: 3em;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Mittelalterliche Chronik</h1>
    <p>Im Jahre des Herrn 1247...</p>
</body>
</html>
```

### **Beispiel 3: Mit eingebettetem Bild (Base64)**

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mit Bild</title>
    <style>
        img {
            max-width: 100%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <h1>Meine Illustration</h1>
    <img src="data:image/png;base64,iVBORw0KGgo..." alt="Illustration">
    <p>Beschreibung des Bildes...</p>
</body>
</html>
```

---

## 🎨 Design-Vorlagen

### **Vorlage 1: Pergament-Stil**

```css
body {
    font-family: 'Georgia', 'Times New Roman', serif;
    background: linear-gradient(135deg, #f4e4bc 0%, #e3d1a2 100%);
    color: #2c1e12;
    padding: 40px;
    line-height: 1.8;
}

.page {
    max-width: 800px;
    margin: 0 auto;
    background: rgba(255, 255, 255, 0.9);
    padding: 60px;
    box-shadow: 
        0 0 50px rgba(0, 0, 0, 0.1),
        inset 0 0 100px rgba(139, 90, 43, 0.05);
    border-radius: 8px;
    border: 2px solid #8b5a2b;
}

h1, h2, h3 {
    color: #8b5a2b;
    font-variant: small-caps;
}

p::first-letter {
    font-size: 3em;
    float: left;
    margin-right: 10px;
    color: #8b5a2b;
}
```

### **Vorlage 2: Dunkles Buch**

```css
body {
    font-family: 'Georgia', serif;
    background: #1a1109;
    color: #d4af37;
    padding: 40px;
    line-height: 1.8;
}

.page {
    max-width: 900px;
    margin: 0 auto;
    background: rgba(26, 17, 9, 0.95);
    padding: 60px;
    border: 3px solid #d4af37;
    box-shadow: 0 0 100px rgba(212, 175, 55, 0.3);
}

h1 {
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 5px;
    color: #d4af37;
    text-shadow: 0 0 20px rgba(212, 175, 55, 0.5);
}
```

### **Vorlage 3: Moderne Eleganz**

```css
body {
    font-family: 'Inter', sans-serif;
    background: white;
    color: #333;
    padding: 0;
    margin: 0;
}

.page {
    max-width: 700px;
    margin: 100px auto;
    padding: 80px;
}

h1 {
    font-size: 4em;
    font-weight: 100;
    margin-bottom: 60px;
    letter-spacing: -2px;
}

p {
    font-size: 1.2em;
    line-height: 2;
    margin-bottom: 40px;
}
```

---

## 🚀 Upload & Verwendung

### **Schritt 1: HTML-Datei erstellen**
- Erstelle eine `.html` oder `.htm` Datei
- Füge dein CSS direkt im `<style>`-Tag ein
- Speichere die Datei

### **Schritt 2: Upload in Bibliothek**
1. Gehe zu `bibliothek.php?cat=books`
2. Als Admin: Drag & Drop oder Klick auf Upload-Zone
3. Wähle deine HTML-Datei aus
4. Fertig!

### **Schritt 3: Anzeigen**
- HTML-Dateien erscheinen mit 📖-Icon
- Klick öffnet sie im Vollbild-Lightbox
- Voller CSS-Support, sieht aus wie designed
- ESC oder X zum Schließen

---

## 🎯 Verwendungsbeispiele

### **1. Rollenspiel-Chroniken**
```html
<h1>Die Schlacht von Dämmerhafens Tor</h1>
<p class="date">15. Tag des Mondmonats, Jahr 1247</p>
<p>Als die Horde heranrückte...</p>
```

### **2. Charakter-Steckbriefe**
```html
<div class="character">
    <h2>Sir Aldric der Tapfere</h2>
    <dl>
        <dt>Alter:</dt> <dd>32 Jahre</dd>
        <dt>Klasse:</dt> <dd>Ritter</dd>
        <dt>Fähigkeiten:</dt> <dd>Schwertkunst (Meister)</dd>
    </dl>
</div>
```

### **3. Zaubersprüche & Rezepte**
```html
<div class="spell">
    <h3>🔮 Feuerball</h3>
    <p class="level">Stufe: 3</p>
    <p class="components">Komponenten: Schwefel, Gesang</p>
    <p class="effect">Wirft einen Feuerball...</p>
</div>
```

### **4. Karten & Tabellen**
```html
<table>
    <tr>
        <th>Ort</th>
        <th>Entfernung</th>
        <th>Gefahrenlevel</th>
    </tr>
    <tr>
        <td>Dämmerhafens Tor</td>
        <td>0 Meilen</td>
        <td>Sicher</td>
    </tr>
    <tr>
        <td>Der Finstere Wald</td>
        <td>5 Meilen</td>
        <td>Gefährlich</td>
    </tr>
</table>
```

---

## 🎨 CSS-Tricks

### **Pergament-Effekt:**
```css
.page {
    background: #f4e4bc;
    background-image: 
        linear-gradient(90deg, transparent 79px, #abced4 79px, #abced4 81px, transparent 81px),
        linear-gradient(#eee .1em, transparent .1em);
    background-size: 100% 1.2em;
}
```

### **Verbrannte Ränder:**
```css
.page {
    box-shadow: 
        inset 0 0 20px rgba(139, 69, 19, 0.3),
        0 0 50px rgba(0, 0, 0, 0.5);
    border: 2px solid transparent;
    border-image: url('data:image/svg+xml,...') 30;
}
```

### **Fancy Trennlinien:**
```css
hr {
    border: none;
    border-top: 2px solid #8b5a2b;
    position: relative;
}

hr::after {
    content: '☬';
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    padding: 0 20px;
}
```

### **Illuminierte Initialen:**
```css
p::first-letter {
    font-size: 5em;
    float: left;
    line-height: 0.8;
    margin: 10px 15px 0 0;
    color: #d4af37;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    font-family: 'UnifrakturMaguntia', cursive;
}
```

---

## ⚠️ Wichtige Hinweise

### **Was funktioniert NICHT:**

#### **1. JavaScript wird nicht ausgeführt:**
```html
<!-- ❌ Funktioniert NICHT -->
<script>
    alert('Hello');
</script>
```

#### **2. Externe Formulare:**
```html
<!-- ❌ Formulare können nicht abgeschickt werden -->
<form action="external.php" method="post">
    <input type="text" name="data">
    <button>Send</button>
</form>
```

#### **3. Links zu externen Seiten:**
```html
<!-- ❌ Navigation blockiert -->
<a href="https://external.com">Link</a>
```

### **Was funktioniert:**

✅ Alle HTML-Tags (h1-h6, p, div, span, table, etc.)
✅ CSS (inline, `<style>`, externe Stylesheets)
✅ Google Fonts
✅ Base64-eingebettete Bilder
✅ SVG-Grafiken (inline)
✅ Flexbox & Grid
✅ Animationen (CSS-only)

---

## 🐛 Troubleshooting

### **Problem: HTML-Datei wird nicht angezeigt**
✅ Prüfe Dateiendung (.html oder .htm)
✅ Cache leeren (Strg+Shift+R)
✅ Browser-Console öffnen (F12) → Fehler prüfen

### **Problem: CSS wird nicht angewendet**
✅ Stelle sicher, dass CSS im `<style>`-Tag oder inline ist
✅ Externe Stylesheets müssen über HTTPS geladen werden
✅ Prüfe CSS-Syntax (fehlende Klammern, Semikolons)

### **Problem: Bilder werden nicht geladen**
✅ Verwende Base64-eingebettete Bilder
✅ Oder verwende Bilder von HTTPS-Quellen
✅ Relative Pfade funktionieren NICHT (nutze absolute URLs)

### **Problem: Lightbox zeigt leere Seite**
✅ Prüfe, ob `<!DOCTYPE html>` vorhanden ist
✅ Stelle sicher, dass `<html>`, `<head>`, `<body>` vorhanden sind
✅ Validiere HTML mit https://validator.w3.org/

---

## 📊 Performance-Tipps

### **1. Bilder optimieren:**
- Verwende komprimierte JPEGs/PNGs
- Konvertiere zu Base64 mit https://www.base64-image.de/
- Oder nutze externe CDNs (imgur, cloudinary)

### **2. CSS minimieren:**
- Entferne unnötige Leerzeichen
- Nutze Shorthand-Properties
- Kombiniere ähnliche Selektoren

### **3. Schriften begrenzen:**
- Lade max. 2-3 Font-Varianten
- Nutze `&display=swap` bei Google Fonts
- Erwäge system fonts als Fallback

---

## 🎉 Beispiel-Datei

Die Datei `Beispiel_Annalen_des_Webstuhls.html` zeigt:
- ✅ Schönes Pergament-Design
- ✅ Illuminierte Initialen
- ✅ Fancy Trennlinien
- ✅ Kapitel-Überschriften
- ✅ Responsive Layout
- ✅ Vollständig styled

**Einfach hochladen und testen!**

---

## 🔐 Sicherheits-FAQ

### **Q: Kann jemand über HTML Schadcode einschleusen?**
**A:** Nein. Das `sandbox`-Attribut blockiert JavaScript komplett.

### **Q: Können HTML-Dateien auf meine Server-Dateien zugreifen?**
**A:** Nein. Sandbox verhindert jeden Server-Zugriff.

### **Q: Kann über HTML mein Passwort gestohlen werden?**
**A:** Nein. Keine JavaScript-Ausführung = kein Passwort-Diebstahl.

### **Q: Können HTML-Dateien andere Seiten öffnen?**
**A:** Nein. Navigation ist komplett blockiert.

### **Q: Sind externe CSS/Font-Links sicher?**
**A:** Ja, CSS und Fonts können keinen Schadcode ausführen.

---

## ✨ Zusammenfassung

**HTML-Support ermöglicht:**
- 📖 Schön formatierte Texte
- 🎨 Kreative Gestaltung
- 📚 Buch-ähnliche Dokumente
- 🔒 Sicher durch Sandbox

**Dateien ändern:**
- ✅ `footer.php` - Lightbox erweitert
- ✅ `bibliothek.php` - HTML-Erkennung
- ✅ `functions.php` - HTML-Icon (📖)

**Status:** Production-Ready! 🚀

Viel Spaß beim Erstellen wunderschöner HTML-Dokumente! ⚔️📚
