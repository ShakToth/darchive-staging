# Feature-Check & Umsetzungsplan (Stand: heute)

## 1) Vollständigkeits-Check der genannten Wunschliste

| Bereich | Feature | Status | Hinweis |
|---|---|---|---|
| Miliz | "Wanted"-Poster Generator (Bild, Name, Verbrechen, Belohnung in Gold/Silber/Kupfer) | **Erfüllt** | Spezielles Formular + strukturierte Belohnung (Gold/Silber/Kupfer) + Wanted-Rendering umgesetzt. |
| Miliz | Bürger-Briefkasten (auch ohne Admin-Passwort) | **Erfüllt** | Öffentliches Formular + interner Ungelesen/Gelesen-Workflow vorhanden. |
| Miliz | Waffenkammer-Inventar als echte Tabelle | **Erfüllt** | DB-gestütztes Inventar inkl. Bestand/Zustand/Ausgegeben an vorhanden. |
| Bibliothek | RP-Metadaten/Lore-Qualität beim Upload | **Erfüllt** | Qualitätsauswahl beim Upload + spätere Änderung vorhanden. |
| Bibliothek | Ausleih-Register (Historie, "Zuletzt gelesen von") | **Erfüllt** | Sichtbare Anzeige direkt an der Karte und in Tooltip inkl. kurzer Leser-Historie vorhanden. |
| Bibliothek | Cover-Generator für PDFs | **Erfüllt** | PDF-Vorschau wird als stilisiertes Buchcover gerendert. |
| Aushänge | Angeheftete Notizen (Kommentare) | **Erfüllt** | Notizsystem pro Aushang inkl. Erstellen/Löschen vorhanden. |
| Aushänge | "Wichtig"-Siegel | **Erfüllt** | Wichtig/Anheften inkl. Sortierung und UI vorhanden. |
| Miliz | Status-Filter (Flüchtig/Inhaftiert/Verstorben) | **Erfüllt** | Filter in "Gesucht" und "Steckbriefe" vorhanden. |
| Overall | Rich-Text-Formatierung (BBCode/Markdown minimal) | **Erfüllt** | Markdown-artiger Parser + formatierte Aushänge vorhanden. |
| System/UI | Rollensystem statt Master-Passwort | **Erfüllt** | Rollen + bereichsbezogene Permissions vorhanden, `isAdmin()` nur Legacy-Alias. |

---

## 2) Plan nur für verbleibende Features

Aktuell sind die Punkte aus der ursprünglichen Liste implementiert.
Nächster Schritt im TODO kann ein UX-Feinschliff-Paket sein (z. B. Inline-Edit für Wanted-Belohnung, erweiterte Cover-Varianten).

## 3) Empfohlene Reihenfolge
1. **Paket A (Wanted)** — höchster RP-Mehrwert im Miliz-Gameplay.
2. **Paket B (Ausleih-Register sichtbar)** — Daten sind schon da, geringer Implementierungsaufwand.
3. **Paket C (Cover-Generator)** — rein visuell, danach Feinschliff.

## 4) Aufwandsschätzung (grob)
- Paket A: **M** (Schema + Formular + Rendering)
- Paket B: **S** (primär UI + kleine Datenabfrage)
- Paket C: **S-M** (Markup/CSS)
