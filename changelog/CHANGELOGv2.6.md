# 🏰 FINALE INSTALLATION - Alle Räume

## ✨ KOMPLETTE STRUKTUR

```
index.php          → Dämmerhafen (Stadt-Übersicht mit Hotspots)
bibliothek.php     → Bibliothek (Vollbild-Raum mit Dateiverwaltung)
miliz.php          → Miliz (Vollbild-Raum, vorerst leer)
verwaltung.php     → Verwaltung (Vollbild-Raum, vorerst leer)
aushaenge.php      → Aushänge (Vollbild-Raum, vorerst leer)
```

**Alle Räume haben:**
- ✅ Gleiche Navigation
- ✅ "⌂ Raum verlassen" Button (konsistent)
- ✅ Eigenes Hintergrundbild
- ✅ Bereit für zukünftige Hotspots

---

## 📥 INSTALLATION - 9 DATEIEN

### Schritt 1: PHP-Dateien hochladen

1. **index_hauptseite.php** → umbenennen zu `index.php` ✅
2. **bibliothek.php** ✅
3. **miliz.php** ✅
4. **verwaltung.php** ✅
5. **aushaenge.php** ✅

### Schritt 2: Bilder hochladen

Du brauchst **5 Bilder**:

#### Pflicht-Bilder (ersetze Platzhalter):
1. **dammerhafen.jpg** - Stadt-Übersicht (für index.php)
2. **room.jpg** - Bibliothek (schon vorhanden)
3. **miliz.jpg** - Kaserne/Waffenkammer
4. **verwaltung.jpg** - Rathaus/Verwaltungsgebäude
5. **aushaenge.jpg** - Schwarzes Brett/Marktplatz

#### Temporäre Platzhalter (falls du noch keine Bilder hast):
- `miliz_platzhalter.svg` → umbenennen zu `miliz.jpg`
- `verwaltung_platzhalter.svg` → umbenennen zu `verwaltung.jpg`
- `aushaenge_platzhalter.svg` → umbenennen zu `aushaenge.jpg`

**Die Platzhalter zeigen dir Text, damit du später echte Bilder hochladen kannst!**

---

## 🎯 HOTSPOTS (SCHON EINGEBAUT!)

Die Hotspots auf der Dämmerhafen-Seite sind bereits perfekt positioniert:

```
📚 Bibliothek:   33.1% oben, 45.3% links, 5% groß
⚔️ Miliz:        38.2% oben, 45.2% links, 5% groß
📋 Verwaltung:   33.8% oben, 51.9% links, 5% groß
📌 Aushänge:     39.4% oben, 52.0% links, 5% groß
```

---

## 🎨 DESIGN-KONSISTENZ

### Alle Räume haben:

**Navigation:**
```
[🛡️ Dämmerhafen] | Die Bibliothek | Die Miliz | Die Verwaltung | Aushänge | [Login]
```

**Vollbild-Hintergrund:**
- Eigenes Bild (.jpg)
- Füllt gesamten Bildschirm
- Platz für Hotspots

**"Raum verlassen" Button:**
- In allen Räumen gleich
- Führt zurück zu index.php (Dämmerhafen)
- Position kann später angepasst werden

---

## 🔄 WORKFLOW

```
Nutzer → Website
    ↓
index.php (Dämmerhafen Stadt-Übersicht)
    ↓
Klickt Hotspot:
    ├─ Bibliothek → bibliothek.php (funktional, mit Dateien)
    ├─ Miliz → miliz.php (leer, bereit für Inhalte)
    ├─ Verwaltung → verwaltung.php (leer, bereit für Inhalte)
    └─ Aushänge → aushaenge.php (leer, bereit für Inhalte)
```

---

## 📋 DATEI-STRUKTUR

```
Hauptverzeichnis/
├── index.php (NEU - Dämmerhafen)
├── bibliothek.php (NEU - Bibliothek mit Dateiverwaltung)
├── miliz.php (NEU - Leerer Raum)
├── verwaltung.php (NEU - Leerer Raum)
├── aushaenge.php (NEU - Leerer Raum)
├── functions.php (unverändert)
├── style.css (unverändert)
├── wappen.png
├── dammerhafen.jpg (Stadt)
├── room.jpg (Bibliothek)
├── miliz.jpg (Kaserne)
├── verwaltung.jpg (Rathaus)
├── aushaenge.jpg (Schwarzes Brett)
└── uploads/
    └── verboten/
```

---

## 🐛 TROUBLESHOOTING

### Problem: Platzhalter-Bilder werden angezeigt
**Ursache:** Du hast noch keine eigenen Bilder hochgeladen
**Lösung:**
1. Erstelle/lade eigene Bilder:
   - `miliz.jpg` (Kaserne, Waffenkammer)
   - `verwaltung.jpg` (Rathaus, Büro)
   - `aushaenge.jpg` (Schwarzes Brett, Marktplatz)
2. Lade sie hoch (ersetzen die Platzhalter)

### Problem: "Bild nicht gefunden" Fehler
**Ursache:** Dateiname falsch
**Lösung:**
- Stelle sicher die Dateien heißen exakt:
  - `miliz.jpg` (nicht miliz.png oder Miliz.jpg!)
  - `verwaltung.jpg`
  - `aushaenge.jpg`

### Problem: Bibliothek zeigt keine Dateien
**Ursache:** `room.jpg` fehlt oder bibliothek.php fehlerhaft
**Lösung:**
1. Prüfe dass `room.jpg` vorhanden ist
2. Prüfe dass `uploads/` Ordner existiert
3. Schreibrechte prüfen (chmod 755)

### Problem: Navigation zeigt alte Links
**Ursache:** Cache nicht geleert
**Lösung:**
- Strg + F5 drücken
- Inkognito-Modus testen

---

## ✅ INSTALLATIONS-CHECKLISTE

- [ ] **Alte index.php gesichert** (→ index_BACKUP.php)
- [ ] **Neue index.php hochgeladen** (aus index_hauptseite.php)
- [ ] **bibliothek.php hochgeladen**
- [ ] **miliz.php hochgeladen**
- [ ] **verwaltung.php hochgeladen**
- [ ] **aushaenge.php hochgeladen**
- [ ] **dammerhafen.jpg hochgeladen** (Stadt-Übersicht)
- [ ] **room.jpg vorhanden** (Bibliothek)
- [ ] **miliz.jpg hochgeladen** (oder Platzhalter)
- [ ] **verwaltung.jpg hochgeladen** (oder Platzhalter)
- [ ] **aushaenge.jpg hochgeladen** (oder Platzhalter)
- [ ] **Cache geleert** (Strg+F5)
- [ ] **Website getestet:**
  - [ ] Dämmerhafen-Seite lädt
  - [ ] Hotspots funktionieren
  - [ ] Alle 4 Räume erreichbar
  - [ ] Navigation funktioniert
  - [ ] Bibliothek zeigt Dateien
  - [ ] Login funktioniert

---

## 🎨 BILD-EMPFEHLUNGEN

### dammerhafen.jpg (Stadt-Übersicht):
- Querformat (16:9)
- Mindestens 1920x1080px
- Übersicht der Stadt von oben
- Zeigt wichtige Gebäude

### room.jpg (Bibliothek):
- Bibliotheks-Innenraum
- Regale, Tische, Bücher
- Mittelalterlicher Stil
- Schon vorhanden ✓

### miliz.jpg (Kaserne):
- Waffenkammer oder Kaserne
- Rüstungen, Waffen, Schilde
- Trainingsbereich
- Mittelalterlicher Militär-Stil

### verwaltung.jpg (Rathaus):
- Verwaltungsraum oder Büro
- Schreibtisch, Karten, Dokumente
- Offiziell wirkend
- Mittelalterlicher Amtsstil

### aushaenge.jpg (Schwarzes Brett):
- Marktplatz oder Taverne
- Schwarzes Brett mit Zetteln
- Belebter Bereich
- Öffentlicher Raum

---

## 🚀 NÄCHSTE SCHRITTE

### Phase 1: Basis läuft ✅
- [x] Dämmerhafen Hauptseite
- [x] Alle Räume erreichbar
- [x] Navigation funktioniert
- [x] Bibliothek funktional

### Phase 2: Bilder austauschen
- [ ] Eigene Bilder für Miliz erstellen/finden
- [ ] Eigene Bilder für Verwaltung erstellen/finden
- [ ] Eigene Bilder für Aushänge erstellen/finden
- [ ] Platzhalter ersetzen

### Phase 3: Räume mit Inhalten füllen
Du kannst wählen, welchen Raum du zuerst ausbauen möchtest:

**Option A: Die Miliz ⚔️**
- Mitglieder-Liste
- Dienstpläne
- Waffenregister
- Trainings-Protokolle

**Option B: Die Verwaltung 📋**
- Bevölkerungslisten
- Steuerregister
- Gesetze & Verordnungen
- Verwaltungsdokumente

**Option C: Aushänge 📌**
- News/Ankündigungen
- Veranstaltungskalender
- Gesuche & Angebote
- Schwarzes Brett System

**Option D: Alle Räume mit Hotspots**
- Hotspot-Tool für jeden Raum nutzen
- Interaktive Bereiche hinzufügen
- Wie in der Bibliothek

---

## 💡 TEMPORÄRE LÖSUNG

Wenn du noch keine eigenen Bilder hast:

### Platzhalter nutzen:
```
miliz_platzhalter.svg → umbenennen zu miliz.jpg
verwaltung_platzhalter.svg → umbenennen zu verwaltung.jpg
aushaenge_platzhalter.svg → umbenennen zu aushaenge.jpg
```

Die Platzhalter zeigen:
- Passendes Icon (Schwert, Schriftrolle, Brett)
- Raum-Namen
- Hinweis "Ersetze dieses Bild mit..."
- Mittelalterlicher Stil

### Später ersetzen:
Einfach die echten Bilder hochladen und die Platzhalter überschreiben!

---

## 📊 VORHER/NACHHER

### Vorher:
```
index.php = Bibliothek (einzige Seite)
```

### Jetzt:
```
index.php = Dämmerhafen (Hauptseite mit 4 Hotspots)
    ├─ bibliothek.php (funktional)
    ├─ miliz.php (vorbereitet)
    ├─ verwaltung.php (vorbereitet)
    └─ aushaenge.php (vorbereitet)
```

---

**Alles bereit!** 🏰✨

**Welchen Raum möchtest du als nächstes ausbauen?** ⚔️📋📌

---

## 🔧 SCHNELL-HILFE

```bash
# Dateien hochladen (via FTP/Synology):
index.php
bibliothek.php
miliz.php
verwaltung.php
aushaenge.php

# Bilder hochladen:
dammerhafen.jpg
room.jpg
miliz.jpg (oder Platzhalter)
verwaltung.jpg (oder Platzhalter)
aushaenge.jpg (oder Platzhalter)

# Testen:
Strg + F5
Website aufrufen
Alle Hotspots testen
```

**Los geht's!** 🚀
