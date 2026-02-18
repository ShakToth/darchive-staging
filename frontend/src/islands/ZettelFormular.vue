<template>
    <div class="dh-zettel-form">
        <!-- Formular -->
        <form @submit.prevent="absenden" enctype="multipart/form-data">
            <!-- Titel -->
            <div class="dh-zettel-form__feld">
                <label class="dh-zettel-form__label" for="zettel-titel">Überschrift</label>
                <input
                    id="zettel-titel"
                    v-model="titel"
                    type="text"
                    class="dh-zettel-form__input"
                    placeholder="Titel des Aushangs..."
                    required
                    maxlength="200"
                >
                <span class="dh-zettel-form__zaehler">{{ titel.length }}/200</span>
            </div>

            <!-- Inhalt -->
            <div class="dh-zettel-form__feld">
                <label class="dh-zettel-form__label" for="zettel-inhalt">Inhalt</label>
                <textarea
                    id="zettel-inhalt"
                    v-model="inhalt"
                    class="dh-zettel-form__textarea"
                    placeholder="Was soll auf dem Zettel stehen?&#10;&#10;*kursiv* **fett** [Link](url)"
                    required
                    rows="8"
                    maxlength="5000"
                ></textarea>
                <span class="dh-zettel-form__zaehler">{{ inhalt.length }}/5000</span>
            </div>

            <!-- Signatur -->
            <div class="dh-zettel-form__feld">
                <label class="dh-zettel-form__label" for="zettel-signatur">Signatur</label>
                <input
                    id="zettel-signatur"
                    v-model="signatur"
                    type="text"
                    class="dh-zettel-form__input"
                    placeholder="Euer Name oder Titel..."
                    maxlength="100"
                >
            </div>

            <!-- Format-Auswahl -->
            <div class="dh-zettel-form__feld">
                <label class="dh-zettel-form__label">Format</label>
                <div class="dh-zettel-form__format-gruppe">
                    <label
                        v-for="f in formate"
                        :key="f.value"
                        class="dh-zettel-form__format-option"
                        :class="{ 'dh-zettel-form__format-option--active': formatType === f.value }"
                    >
                        <input
                            v-model="formatType"
                            type="radio"
                            :value="f.value"
                            class="dh-zettel-form__format-radio"
                        >
                        <span class="dh-zettel-form__format-icon">{{ f.icon }}</span>
                        <span class="dh-zettel-form__format-name">{{ f.label }}</span>
                    </label>
                </div>
            </div>

            <!-- Bild-Upload -->
            <div class="dh-zettel-form__feld">
                <label class="dh-zettel-form__label">Bild (optional)</label>
                <div class="dh-zettel-form__bild-zone" @click="$refs.bildInput.click()">
                    <div v-if="!bildVorschau">
                        🖼️ Bild auswählen (optional)
                    </div>
                    <div v-else class="dh-zettel-form__bild-vorschau">
                        <img :src="bildVorschau" alt="Vorschau">
                        <button
                            type="button"
                            class="dh-zettel-form__bild-remove"
                            @click.stop="bildEntfernen"
                        >&times;</button>
                    </div>
                </div>
                <input
                    ref="bildInput"
                    type="file"
                    style="display: none"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    @change="bildGewaehlt"
                >
            </div>

            <!-- Absenden -->
            <div class="dh-zettel-form__aktionen">
                <button
                    type="submit"
                    class="dh-zettel-form__btn dh-zettel-form__btn--primary"
                    :disabled="istAbsenden"
                >
                    {{ istAbsenden ? '📝 Wird angeheftet...' : '📌 Zettel anpinnen' }}
                </button>
            </div>
        </form>

        <!-- Fehler/Erfolg -->
        <div v-if="fehler" class="dh-zettel-form__msg dh-zettel-form__msg--error">
            ❌ {{ fehler }}
        </div>
        <div v-if="erfolg" class="dh-zettel-form__msg dh-zettel-form__msg--success">
            ✅ {{ erfolg }}
        </div>
    </div>
</template>

<script>
import { apiPostForm } from '../api/client.js'

export default {
    name: 'ZettelFormular',

    props: {
        /** API-Endpoint */
        apiUrl: {
            type: String,
            default: '/api/aushaenge.php'
        }
    },

    data() {
        return {
            titel: '',
            inhalt: '',
            signatur: '',
            formatType: 'standard',
            bild: null,
            bildVorschau: null,
            istAbsenden: false,
            fehler: '',
            erfolg: '',
            formate: [
                { value: 'standard', label: 'Standard', icon: '📄' },
                { value: 'dringend', label: 'Dringend', icon: '🔴' },
                { value: 'feierlich', label: 'Feierlich', icon: '✨' }
            ]
        }
    },

    methods: {
        bildGewaehlt(event) {
            const datei = event.target.files[0]
            if (!datei) return

            // Typ prüfen
            const erlaubt = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
            if (!erlaubt.includes(datei.type)) {
                this.fehler = 'Nur Bilder (JPG, PNG, GIF, WebP) erlaubt'
                return
            }

            // Größe prüfen (5 MB für Zettel-Bilder)
            if (datei.size > 5 * 1024 * 1024) {
                this.fehler = 'Bild zu groß (max. 5 MB)'
                return
            }

            this.bild = datei

            // Vorschau erstellen
            const reader = new FileReader()
            reader.onload = (e) => {
                this.bildVorschau = e.target.result
            }
            reader.readAsDataURL(datei)
        },

        bildEntfernen() {
            this.bild = null
            this.bildVorschau = null
            if (this.$refs.bildInput) {
                this.$refs.bildInput.value = ''
            }
        },

        async absenden() {
            this.fehler = ''
            this.erfolg = ''

            // Validierung
            if (!this.titel.trim()) {
                this.fehler = 'Bitte einen Titel eingeben'
                return
            }
            if (!this.inhalt.trim()) {
                this.fehler = 'Bitte einen Inhalt eingeben'
                return
            }

            this.istAbsenden = true

            try {
                const formData = new FormData()
                formData.append('titel', this.titel)
                formData.append('inhalt', this.inhalt)
                formData.append('signatur', this.signatur)
                formData.append('format_type', this.formatType)
                if (this.bild) {
                    formData.append('bild', this.bild)
                }

                const result = await apiPostForm(this.apiUrl, formData)

                this.erfolg = result.message || 'Zettel wurde angeheftet!'

                // Formular zurücksetzen
                this.titel = ''
                this.inhalt = ''
                this.signatur = ''
                this.formatType = 'standard'
                this.bildEntfernen()

                // Nach 2 Sekunden Seite neu laden um den neuen Zettel zu zeigen
                setTimeout(() => {
                    window.location.reload()
                }, 2000)
            } catch (error) {
                this.fehler = error.message || 'Zettel konnte nicht erstellt werden'
            } finally {
                this.istAbsenden = false
            }
        }
    }
}
</script>

<style scoped>
.dh-zettel-form {
    font-family: 'Crimson Text', serif;
}

.dh-zettel-form__feld {
    margin-bottom: 18px;
    position: relative;
}

.dh-zettel-form__label {
    display: block;
    font-family: 'MedievalSharp', cursive;
    font-size: 1.05rem;
    color: var(--text-ink, #3e2e1e);
    margin-bottom: 6px;
}

.dh-zettel-form__input,
.dh-zettel-form__textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 8px;
    background: rgba(244, 228, 188, 0.3);
    font-family: 'Crimson Text', serif;
    font-size: 1.05rem;
    color: var(--text-ink, #3e2e1e);
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.dh-zettel-form__input:focus,
.dh-zettel-form__textarea:focus {
    outline: none;
    border-color: var(--accent-gold, #d4af37);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
}

.dh-zettel-form__textarea {
    resize: vertical;
    min-height: 120px;
}

.dh-zettel-form__zaehler {
    position: absolute;
    right: 8px;
    bottom: -18px;
    font-size: 0.78rem;
    color: rgba(62, 46, 30, 0.4);
}

/* Format-Auswahl */
.dh-zettel-form__format-gruppe {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.dh-zettel-form__format-option {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border: 1px solid rgba(62, 46, 30, 0.15);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    background: rgba(244, 228, 188, 0.2);
}

.dh-zettel-form__format-option:hover {
    background: rgba(244, 228, 188, 0.4);
}

.dh-zettel-form__format-option--active {
    background: rgba(212, 175, 55, 0.15);
    border-color: var(--accent-gold, #d4af37);
}

.dh-zettel-form__format-radio {
    display: none;
}

.dh-zettel-form__format-icon {
    font-size: 1.2rem;
}

.dh-zettel-form__format-name {
    font-size: 0.95rem;
}

/* Bild-Upload */
.dh-zettel-form__bild-zone {
    border: 2px dashed rgba(212, 175, 55, 0.3);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    color: rgba(62, 46, 30, 0.5);
    transition: border-color 0.2s;
}

.dh-zettel-form__bild-zone:hover {
    border-color: var(--accent-gold, #d4af37);
}

.dh-zettel-form__bild-vorschau {
    position: relative;
    display: inline-block;
}

.dh-zettel-form__bild-vorschau img {
    max-width: 200px;
    max-height: 150px;
    border-radius: 6px;
    object-fit: cover;
}

.dh-zettel-form__bild-remove {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #b42828;
    color: #fff;
    border: none;
    font-size: 1.1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

/* Aktionen */
.dh-zettel-form__aktionen {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
}

.dh-zettel-form__btn {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-family: 'MedievalSharp', cursive;
    font-size: 1.05rem;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.dh-zettel-form__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.dh-zettel-form__btn--primary {
    background: linear-gradient(135deg, #d4af37, #b8860b);
    color: #fff;
}

.dh-zettel-form__btn--primary:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.4);
}

/* Nachrichten */
.dh-zettel-form__msg {
    margin-top: 12px;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 0.95rem;
}

.dh-zettel-form__msg--error {
    background: rgba(180, 40, 40, 0.1);
    border: 1px solid rgba(180, 40, 40, 0.3);
    color: #b42828;
}

.dh-zettel-form__msg--success {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.3);
    color: #1a8a3a;
}
</style>
