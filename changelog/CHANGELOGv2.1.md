# 🎯 FINALE INSTALLATION - Mit Hotspot-Tool

## ✨ WAS IST NEU?

### 1️⃣ Neue Navigation
```
[Wappen] Dämmerhafen | Die Bibliothek | Die Miliz | Die Verwaltung | Aushänge | [Login]
```

### 2️⃣ "Die Mitte" → "Die Miliz" ✅

### 3️⃣ Interaktives Hotspot-Tool
**Endlich die richtigen Positionen einstellen!**

---

## 📥 INSTALLATION

### Schritt 1: Dateien hochladen

Lade diese **7 Dateien** hoch:

1. **Bibliothek3.jpeg** → umbenennen zu `room.jpg` ✅
2. **wappen.svg** (oder dein eigenes Wappen als `wappen.png`) ✅
3. **index_neu.php** → umbenennen zu `index.php` ✅
4. **style_neu.css** → umbenennen zu `style.css` ✅
5. **miliz.php** ✅
6. **verwaltung.php** ✅
7. **aushaenge.php** ✅

---

### Schritt 2: Hotspots anpassen (WICHTIG!)

Die Hotspot-Positionen stimmen noch nicht perfekt? **Kein Problem!**

#### Option A: Interaktives Tool (Empfohlen)

1. Öffne **hotspot-tool.html** im Browser
2. Lade dein **Bibliothek3.jpeg** hoch
3. **Ziehe die farbigen Rechtecke** an die richtige Position
4. **Ziehe an den Ecken** um die Größe anzupassen
5. Klicke **"Code generieren"**
6. Kopiere den generierten Code
7. Ersetze die Hotspot-Sektion in `index.php` (Zeile ~145-180)

#### Option B: Manuelle Anpassung

Öffne `index.php` und suche nach:
```php
<!-- HOTSPOTS NACH NEUEM DESIGN -->
```

Passe die Werte an:
```php
<a href="?cat=images" ... style="top: 25%; left: 10%; width: 25%; height: 40%;">
     ↑ vertikal  ↑ horizontal  ↑ Breite    ↑ Höhe
```

**Beispiel-Werte zum Testen:**
- **Zeichnungen:** `top: 30%; left: 5%; width: 28%; height: 45%;`
- **Bücher:** `top: 58%; left: 38%; width: 18%; height: 18%;`
- **Index:** `top: 65%; left: 58%; width: 14%; height: 15%;`
- **Verboten:** `top: 12%; left: 68%; width: 27%; height: 32%;`
- **Archiv:** `top: 46%; left: 68%; width: 27%; height: 36%;`

---

### Schritt 3: Cache leeren
**Strg + F5** drücken!

---

## 🎨 WAPPEN ANPASSEN

### Eigenes Wappen verwenden

Wenn du ein eigenes Wappen-PNG hast:

1. Benenne es um zu **wappen.png**
2. Lade es hoch
3. **Wichtig:** Transparenter Hintergrund empfohlen!
4. Empfohlene Größe: **80x100px** oder ähnlich

### Platzhalter-Wappen

Ich habe ein **wappen.svg** erstellt mit:
- Schild-Form
- Turm-Symbol
- Gold-Farben (#d4af37)
- "DÄMMERHAFEN" Text

Du kannst es verwenden oder ersetzen!

---

## 🐛 TROUBLESHOOTING

### Wappen wird nicht angezeigt
**Problem:** Datei nicht vorhanden oder falscher Pfad
**Lösung:**
1. Prüfe dass `wappen.png` oder `wappen.svg` im Hauptverzeichnis liegt
2. Oder ändere in allen PHP-Dateien:
```php
<img src="wappen.png" alt="Dämmerhafen" ...>
```

### Hotspots sind immer noch falsch
**Lösung:**
1. Nutze das **hotspot-tool.html** 
2. Lade dein Bild hoch
3. Positioniere visuell
4. Generiere Code
5. Kopiere in `index.php`

### Navigation zu breit
**Problem:** 4 Links passen nicht
**Lösung:** In `style.css` ändere:
```css
.nav-center {
    gap: 30px; /* statt 40px */
}

.nav-link {
    font-size: 1.1rem; /* statt 1.3rem */
}
```

### Miliz-Seite nicht gefunden
**Problem:** `miliz.php` fehlt
**Lösung:**
1. Prüfe dass `miliz.php` hochgeladen ist
2. Schreibrechte prüfen

---

## 📋 DATEI-CHECKLISTE

- [ ] `room.jpg` (neu, aus Bibliothek3.jpeg)
- [ ] `wappen.png` oder `wappen.svg`
- [ ] `index.php` (neu, aus index_neu.php)
- [ ] `style.css` (neu, aus style_neu.css)
- [ ] `miliz.php` (neu)
- [ ] `verwaltung.php` (aktualisiert)
- [ ] `aushaenge.php` (aktualisiert)
- [ ] `functions.php` (unverändert)
- [ ] Cache geleert (Strg+F5)

---

## 🎯 HOTSPOT-TOOL ANLEITUNG

### So funktioniert's:

1. **Öffne:** `hotspot-tool.html` im Browser
2. **Lade Bild:** Klicke "Datei auswählen" → wähle Bibliothek3.jpeg
3. **Positioniere:**
   - **Klicke & Ziehe** rote Rechtecke = Bewegen
   - **Ziehe Ecken** = Größe ändern
4. **Code holen:** Klicke "Code generieren"
5. **Kopieren:** Markiere den Code im schwarzen Feld
6. **Einfügen:** In `index.php` ersetze die Hotspot-Sektion

### Tipps:

- Starte mit **Zeichnungen** (größter Bereich)
- Passe **Verboten** und **Archiv** an (rechts)
- **Bücher** und **Index** sind klein - präzise platzieren!
- Teste im Browser nach jedem Upload

---

## ✅ TEST-CHECKLISTE

Nach Installation:

- [ ] Hauptseite lädt
- [ ] Wappen wird angezeigt
- [ ] Navigation zeigt 4 Links
- [ ] "Die Miliz" funktioniert (nicht mehr "Die Mitte")
- [ ] Alle Hotspots klickbar
- [ ] Hotspots überdecken richtige Bereiche
- [ ] Login funktioniert
- [ ] Mobile Ansicht OK

---

## 🔧 HOTSPOT-POSITIONEN FEINTUNING

Falls die generierten Positionen nicht perfekt sind:

### Zeichnungen (Links, groß)
```
top: 20-35%
left: 5-15%
width: 25-30%
height: 35-50%
```

### Bücher (Mitte, klein)
```
top: 50-60%
left: 35-45%
width: 12-18%
height: 12-20%
```

### Index (Mitte-Rechts, klein)
```
top: 60-70%
left: 52-62%
width: 10-15%
height: 10-18%
```

### Verboten (Rechts-Oben, groß)
```
top: 10-20%
left: 65-75%
width: 22-30%
height: 25-35%
```

### Archiv (Rechts-Unten, groß)
```
top: 45-55%
left: 65-75%
width: 22-30%
height: 30-40%
```

---

## 💡 NÄCHSTE SCHRITTE

Nach erfolgreicher Installation:

1. **Teste alle Bereiche**
2. **Passe Hotspots fein an** (mit Tool)
3. **Wähle welchen Bereich du ausbauen möchtest:**
   - 📚 Die Bibliothek (aktuell funktional)
   - ⚔️ Die Miliz (Platzhalter)
   - 📋 Die Verwaltung (Platzhalter)
   - 📌 Aushänge (Platzhalter)

---

## 🎨 DESIGN-VORSCHAU

```
┌────────────────────────────────────────────────────────┐
│ [🛡️] Dämmerhafen | Bibliothek | Miliz | Verwaltung | Aushänge | [🔑] │
└────────────────────────────────────────────────────────┘

         ┌─────────────┐              ┌──────────────┐
         │ Zeichnungen │              │  Verboten ⛔ │
         │             │              │              │
         │   🖼️        │              │      🔥      │
         └─────────────┘              └──────────────┘
                                      
              📚     📇                ┌──────────────┐
            Bücher  Index             │   Archiv 📦  │
                                      │              │
                                      └──────────────┘
```

---

**Los geht's!** 🚀

**Fragen?**
- Hotspots passen nicht → Nutze `hotspot-tool.html`
- Wappen fehlt → Lade `wappen.svg` oder dein PNG hoch
- Navigation kaputt → Prüfe alle PHP-Dateien

**Viel Erfolg!** ⚔️✨
