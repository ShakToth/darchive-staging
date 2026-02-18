<template>
    <div class="dh-suche">
        <!-- Suchfeld -->
        <div class="dh-suche__eingabe">
            <span class="dh-suche__icon">🔍</span>
            <input
                v-model="suchText"
                type="text"
                class="dh-suche__input"
                placeholder="Dokument suchen..."
                @input="onSuche"
            >
            <button
                v-if="suchText"
                class="dh-suche__clear"
                @click="suchText = ''; onSuche()"
                aria-label="Suche leeren"
            >&times;</button>
        </div>

        <!-- Filter-Leiste -->
        <div class="dh-suche__filter">
            <!-- Qualitäts-Filter -->
            <div class="dh-suche__filter-group">
                <button
                    v-for="q in qualitaeten"
                    :key="q.value"
                    class="dh-suche__filter-btn"
                    :class="{ 'dh-suche__filter-btn--active': aktiveQualitaet === q.value }"
                    :style="aktiveQualitaet === q.value ? { borderColor: q.color, color: q.color } : {}"
                    @click="filterQualitaet(q.value)"
                >
                    {{ q.label }}
                </button>
            </div>

            <!-- Sortierung -->
            <div class="dh-suche__sort">
                <select v-model="sortierung" class="dh-suche__select" @change="onSuche">
                    <option value="name-asc">Name (A–Z)</option>
                    <option value="name-desc">Name (Z–A)</option>
                    <option value="date-desc">Neueste zuerst</option>
                    <option value="date-asc">Älteste zuerst</option>
                    <option value="size-desc">Größte zuerst</option>
                    <option value="size-asc">Kleinste zuerst</option>
                </select>
            </div>
        </div>

        <!-- Ergebnis-Zähler -->
        <div v-if="suchText || aktiveQualitaet !== 'alle'" class="dh-suche__ergebnis">
            {{ gefilterteDateien.length }} von {{ dateien.length }} Dokumenten
        </div>

        <!-- Gefilterte Dateiliste (wird als Slot/Event an Eltern-PHP übergeben) -->
        <div class="dh-suche__ergebnis-liste">
            <slot :dateien="gefilterteDateien" :suchText="suchText"></slot>
        </div>
    </div>
</template>

<script>
export default {
    name: 'DateiSuche',

    props: {
        /** Initiale Dateiliste (aus PHP übergeben) */
        initialFiles: {
            type: Array,
            default: () => []
        }
    },

    data() {
        return {
            suchText: '',
            aktiveQualitaet: 'alle',
            sortierung: 'name-asc',
            dateien: [],
            suchTimer: null,
            qualitaeten: [
                { value: 'alle', label: 'Alle', color: '#d4af37' },
                { value: 'common', label: '⬜ Gewöhnlich', color: '#9d9d9d' },
                { value: 'uncommon', label: '🟢 Ungewöhnlich', color: '#1eff00' },
                { value: 'rare', label: '🔵 Selten', color: '#0070dd' },
                { value: 'epic', label: '🟣 Episch', color: '#a335ee' },
                { value: 'legendary', label: '🟠 Legendär', color: '#ff8000' }
            ]
        }
    },

    computed: {
        gefilterteDateien() {
            let ergebnis = [...this.dateien]

            // Textsuche
            if (this.suchText) {
                const suche = this.suchText.toLowerCase()
                ergebnis = ergebnis.filter(d =>
                    d.name.toLowerCase().includes(suche) ||
                    (d.description && d.description.toLowerCase().includes(suche))
                )
            }

            // Qualitäts-Filter
            if (this.aktiveQualitaet !== 'alle') {
                ergebnis = ergebnis.filter(d => d.quality === this.aktiveQualitaet)
            }

            // Sortierung
            const [feld, richtung] = this.sortierung.split('-')
            ergebnis.sort((a, b) => {
                let vergleich = 0
                if (feld === 'name') {
                    vergleich = a.name.localeCompare(b.name, 'de')
                } else if (feld === 'date') {
                    vergleich = (a.modified || 0) - (b.modified || 0)
                } else if (feld === 'size') {
                    vergleich = (a.size || 0) - (b.size || 0)
                }
                return richtung === 'desc' ? -vergleich : vergleich
            })

            return ergebnis
        }
    },

    mounted() {
        this.dateien = this.initialFiles
    },

    methods: {
        onSuche() {
            // Debounce: 200ms
            clearTimeout(this.suchTimer)
            this.suchTimer = setTimeout(() => {
                this.$emit('filter-change', {
                    search: this.suchText,
                    quality: this.aktiveQualitaet,
                    sort: this.sortierung,
                    results: this.gefilterteDateien
                })
            }, 200)
        },

        filterQualitaet(q) {
            this.aktiveQualitaet = (this.aktiveQualitaet === q) ? 'alle' : q
            this.onSuche()
        }
    }
}
</script>

<style scoped>
.dh-suche {
    margin-bottom: 20px;
}

.dh-suche__eingabe {
    display: flex;
    align-items: center;
    background: rgba(244, 228, 188, 0.4);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 8px;
    padding: 8px 14px;
    gap: 8px;
    transition: border-color 0.2s;
}

.dh-suche__eingabe:focus-within {
    border-color: var(--accent-gold, #d4af37);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
}

.dh-suche__icon {
    font-size: 1.1rem;
    flex-shrink: 0;
}

.dh-suche__input {
    flex: 1;
    background: none;
    border: none;
    outline: none;
    font-family: 'Crimson Text', serif;
    font-size: 1.05rem;
    color: var(--text-ink, #3e2e1e);
}

.dh-suche__input::placeholder {
    color: rgba(62, 46, 30, 0.4);
}

.dh-suche__clear {
    background: none;
    border: none;
    font-size: 1.4rem;
    color: rgba(62, 46, 30, 0.5);
    cursor: pointer;
    padding: 0 4px;
}

.dh-suche__clear:hover {
    color: var(--text-ink, #3e2e1e);
}

/* Filter-Leiste */
.dh-suche__filter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    gap: 12px;
    flex-wrap: wrap;
}

.dh-suche__filter-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.dh-suche__filter-btn {
    padding: 4px 10px;
    background: rgba(244, 228, 188, 0.3);
    border: 1px solid rgba(62, 46, 30, 0.15);
    border-radius: 20px;
    font-family: 'Crimson Text', serif;
    font-size: 0.85rem;
    color: var(--text-ink, #3e2e1e);
    cursor: pointer;
    transition: all 0.2s;
}

.dh-suche__filter-btn:hover {
    background: rgba(244, 228, 188, 0.5);
}

.dh-suche__filter-btn--active {
    background: rgba(212, 175, 55, 0.15);
    font-weight: 600;
}

.dh-suche__select {
    padding: 5px 10px;
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 6px;
    background: rgba(244, 228, 188, 0.3);
    font-family: 'Crimson Text', serif;
    font-size: 0.9rem;
    color: var(--text-ink, #3e2e1e);
}

/* Ergebnis-Zähler */
.dh-suche__ergebnis {
    margin-top: 10px;
    font-family: 'Crimson Text', serif;
    font-size: 0.9rem;
    color: rgba(62, 46, 30, 0.6);
}

/* Responsive */
@media (max-width: 600px) {
    .dh-suche__filter {
        flex-direction: column;
        align-items: flex-start;
    }

    .dh-suche__filter-btn {
        font-size: 0.78rem;
        padding: 3px 8px;
    }
}
</style>
