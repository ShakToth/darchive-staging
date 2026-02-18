<template>
    <div
        class="dh-upload"
        :class="{
            'dh-upload--dragover': istDragover,
            'dh-upload--uploading': istUploading,
            'dh-upload--done': istFertig
        }"
        @dragover.prevent="onDragover"
        @dragleave.prevent="onDragleave"
        @drop.prevent="onDrop"
    >
        <!-- Upload-Zone -->
        <div v-if="!istUploading && !istFertig" class="dh-upload__zone" @click="dateiAuswaehlen">
            <div class="dh-upload__icon">📜</div>
            <div class="dh-upload__text">
                <strong>Dateien hierher ziehen</strong>
                <span>oder klicken zum Auswählen</span>
            </div>
            <div class="dh-upload__hint">
                Max. {{ maxSizeMB }} MB · PDF, Bilder, Dokumente, Archive
            </div>
        </div>

        <!-- Qualitäts-Auswahl -->
        <div v-if="ausgewaehlteDatei && !istUploading && !istFertig" class="dh-upload__meta">
            <div class="dh-upload__datei-info">
                <span class="dh-upload__datei-name">{{ ausgewaehlteDatei.name }}</span>
                <span class="dh-upload__datei-groesse">{{ formatGroesse(ausgewaehlteDatei.size) }}</span>
            </div>

            <div class="dh-upload__quality">
                <label>Qualität:</label>
                <select v-model="qualitaet" class="dh-upload__select">
                    <option value="common">⬜ Gewöhnlich</option>
                    <option value="uncommon">🟢 Ungewöhnlich</option>
                    <option value="rare">🔵 Selten</option>
                    <option value="epic">🟣 Episch</option>
                    <option value="legendary">🟠 Legendär</option>
                </select>
            </div>

            <button class="dh-upload__btn" @click="hochladen">
                📤 Hochladen
            </button>
        </div>

        <!-- Fortschrittsanzeige -->
        <div v-if="istUploading" class="dh-upload__progress">
            <div class="dh-upload__progress-text">
                Lade hoch: {{ ausgewaehlteDatei?.name }}
            </div>
            <div class="dh-upload__progress-bar">
                <div
                    class="dh-upload__progress-fill"
                    :style="{ width: fortschritt + '%' }"
                ></div>
            </div>
            <div class="dh-upload__progress-percent">{{ fortschritt }}%</div>
        </div>

        <!-- Erfolgs-Nachricht -->
        <div v-if="istFertig" class="dh-upload__done">
            <div class="dh-upload__done-icon">✅</div>
            <div class="dh-upload__done-text">{{ erfolgText }}</div>
            <button class="dh-upload__btn dh-upload__btn--secondary" @click="zuruecksetzen">
                Weitere Datei hochladen
            </button>
        </div>

        <!-- Fehler -->
        <div v-if="fehler" class="dh-upload__error">
            ❌ {{ fehler }}
            <button class="dh-upload__error-close" @click="fehler = ''">&times;</button>
        </div>

        <!-- Verstecktes File-Input -->
        <input
            ref="dateiInput"
            type="file"
            style="display: none"
            @change="onDateiGewaehlt"
            :accept="erlaubteExtensions"
        >
    </div>
</template>

<script>
import { apiUpload } from '../api/client.js'

export default {
    name: 'DateiUpload',

    props: {
        /** API-Endpoint URL */
        apiUrl: {
            type: String,
            default: '/api/upload.php'
        },
        /** Maximale Dateigröße in MB */
        maxSizeMB: {
            type: Number,
            default: 320
        },
        /** Ziel-Kategorie (normal oder forbidden) */
        targetCategory: {
            type: String,
            default: 'normal'
        }
    },

    data() {
        return {
            istDragover: false,
            istUploading: false,
            istFertig: false,
            ausgewaehlteDatei: null,
            qualitaet: 'common',
            fortschritt: 0,
            fehler: '',
            erfolgText: '',
            erlaubteExtensions: '.pdf,.txt,.md,.doc,.docx,.xls,.xlsx,.zip,.rar,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.epub'
        }
    },

    methods: {
        dateiAuswaehlen() {
            this.$refs.dateiInput.click()
        },

        onDateiGewaehlt(event) {
            const datei = event.target.files[0]
            if (datei) {
                this.dateiPruefen(datei)
            }
        },

        onDragover() {
            this.istDragover = true
        },

        onDragleave() {
            this.istDragover = false
        },

        onDrop(event) {
            this.istDragover = false
            const datei = event.dataTransfer.files[0]
            if (datei) {
                this.dateiPruefen(datei)
            }
        },

        dateiPruefen(datei) {
            this.fehler = ''

            // Größenprüfung
            const maxBytes = this.maxSizeMB * 1024 * 1024
            if (datei.size > maxBytes) {
                this.fehler = `Datei zu groß (${this.formatGroesse(datei.size)}). Maximum: ${this.maxSizeMB} MB`
                return
            }

            // Extension prüfen
            const ext = datei.name.split('.').pop().toLowerCase()
            const erlaubt = this.erlaubteExtensions.replace(/\./g, '').split(',')
            if (!erlaubt.includes(ext)) {
                this.fehler = `Dateityp ".${ext}" nicht erlaubt`
                return
            }

            this.ausgewaehlteDatei = datei
        },

        async hochladen() {
            if (!this.ausgewaehlteDatei) return

            this.istUploading = true
            this.fortschritt = 0
            this.fehler = ''

            try {
                const formData = new FormData()
                formData.append('datei', this.ausgewaehlteDatei)
                formData.append('quality', this.qualitaet)
                formData.append('target_cat', this.targetCategory)

                const result = await apiUpload(
                    this.apiUrl,
                    formData,
                    (percent) => { this.fortschritt = percent }
                )

                this.istUploading = false
                this.istFertig = true
                this.erfolgText = result.message || 'Datei erfolgreich hochgeladen!'
            } catch (error) {
                this.istUploading = false
                this.fehler = error.message || 'Upload fehlgeschlagen'
            }
        },

        zuruecksetzen() {
            this.ausgewaehlteDatei = null
            this.istFertig = false
            this.istUploading = false
            this.fortschritt = 0
            this.fehler = ''
            this.erfolgText = ''
            this.qualitaet = 'common'
            if (this.$refs.dateiInput) {
                this.$refs.dateiInput.value = ''
            }
        },

        formatGroesse(bytes) {
            if (bytes === 0) return '0 B'
            const einheiten = ['B', 'KB', 'MB', 'GB']
            const i = Math.floor(Math.log(bytes) / Math.log(1024))
            return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + einheiten[i]
        }
    }
}
</script>

<style scoped>
.dh-upload {
    border: 2px dashed rgba(212, 175, 55, 0.4);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    background: rgba(244, 228, 188, 0.1);
    transition: all 0.3s ease;
    position: relative;
}

.dh-upload--dragover {
    border-color: var(--accent-gold, #d4af37);
    background: rgba(212, 175, 55, 0.15);
    transform: scale(1.01);
}

.dh-upload__zone {
    cursor: pointer;
    padding: 20px;
}

.dh-upload__zone:hover {
    opacity: 0.8;
}

.dh-upload__icon {
    font-size: 3rem;
    margin-bottom: 10px;
}

.dh-upload__text {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-family: 'Crimson Text', serif;
    font-size: 1.1rem;
    color: var(--text-ink, #3e2e1e);
}

.dh-upload__text strong {
    font-family: 'MedievalSharp', cursive;
    font-size: 1.2rem;
    color: var(--accent-gold, #d4af37);
}

.dh-upload__hint {
    margin-top: 10px;
    font-size: 0.85rem;
    color: rgba(62, 46, 30, 0.6);
    font-family: 'Crimson Text', serif;
}

/* Datei-Info & Qualitäts-Auswahl */
.dh-upload__meta {
    margin-top: 20px;
    padding: 15px;
    background: rgba(244, 228, 188, 0.3);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
}

.dh-upload__datei-info {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Crimson Text', serif;
}

.dh-upload__datei-name {
    font-weight: 600;
    color: var(--text-ink, #3e2e1e);
}

.dh-upload__datei-groesse {
    color: rgba(62, 46, 30, 0.6);
    font-size: 0.9rem;
}

.dh-upload__quality {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Crimson Text', serif;
}

.dh-upload__select {
    padding: 6px 12px;
    border: 1px solid rgba(212, 175, 55, 0.4);
    border-radius: 6px;
    background: rgba(244, 228, 188, 0.5);
    font-family: 'Crimson Text', serif;
    font-size: 1rem;
    color: var(--text-ink, #3e2e1e);
}

.dh-upload__btn {
    padding: 10px 24px;
    background: linear-gradient(135deg, #d4af37, #b8860b);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-family: 'MedievalSharp', cursive;
    font-size: 1.05rem;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.dh-upload__btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.4);
}

.dh-upload__btn--secondary {
    background: rgba(244, 228, 188, 0.5);
    color: var(--text-ink, #3e2e1e);
    border: 1px solid rgba(212, 175, 55, 0.4);
}

/* Fortschrittsanzeige */
.dh-upload__progress {
    padding: 20px;
}

.dh-upload__progress-text {
    font-family: 'Crimson Text', serif;
    margin-bottom: 12px;
    color: var(--text-ink, #3e2e1e);
}

.dh-upload__progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(44, 30, 18, 0.2);
    border-radius: 4px;
    overflow: hidden;
}

.dh-upload__progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #d4af37, #ff8000);
    border-radius: 4px;
    transition: width 0.3s ease;
}

.dh-upload__progress-percent {
    margin-top: 8px;
    font-family: 'Cinzel', serif;
    font-size: 1.3rem;
    color: var(--accent-gold, #d4af37);
    font-weight: 700;
}

/* Erfolg */
.dh-upload__done {
    padding: 20px;
}

.dh-upload__done-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.dh-upload__done-text {
    font-family: 'Crimson Text', serif;
    font-size: 1.1rem;
    color: #28a745;
    margin-bottom: 15px;
}

/* Fehler */
.dh-upload__error {
    margin-top: 12px;
    padding: 10px 15px;
    background: rgba(180, 40, 40, 0.1);
    border: 1px solid rgba(180, 40, 40, 0.3);
    border-radius: 8px;
    color: #b42828;
    font-family: 'Crimson Text', serif;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dh-upload__error-close {
    margin-left: auto;
    background: none;
    border: none;
    color: #b42828;
    font-size: 1.3rem;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 600px) {
    .dh-upload {
        padding: 15px;
    }

    .dh-upload__meta {
        padding: 10px;
    }
}
</style>
