<template>
    <div class="dh-rollen">
        <!-- Rollenliste -->
        <div class="dh-rollen__liste">
            <div
                v-for="rolle in rollen"
                :key="rolle.name"
                class="dh-rollen__karte"
            >
                <div class="dh-rollen__karte-header">
                    <span class="dh-rollen__icon">{{ rolle.icon }}</span>
                    <span class="dh-rollen__name" :style="{ color: rolle.color }">
                        {{ rolle.display_name }}
                    </span>
                    <span v-if="rolle.is_system" class="dh-rollen__system-badge">System</span>
                </div>

                <!-- Permission-Matrix für diese Rolle -->
                <div class="dh-rollen__matrix">
                    <div class="dh-rollen__matrix-header">
                        <span class="dh-rollen__matrix-label"></span>
                        <span class="dh-rollen__matrix-col">Lesen</span>
                        <span class="dh-rollen__matrix-col">Schreiben</span>
                        <span class="dh-rollen__matrix-col">Upload</span>
                    </div>

                    <div
                        v-for="section in sektionen"
                        :key="section.key"
                        class="dh-rollen__matrix-row"
                    >
                        <span class="dh-rollen__matrix-label">
                            {{ section.icon }} {{ section.label }}
                        </span>
                        <span class="dh-rollen__matrix-col">
                            <input
                                type="checkbox"
                                :checked="getPermission(rolle.name, section.key, 'can_read')"
                                :disabled="rolle.name === 'meister' || istSpeichern"
                                @change="setPermission(rolle.name, section.key, 'can_read', $event.target.checked)"
                                class="dh-rollen__checkbox"
                            >
                        </span>
                        <span class="dh-rollen__matrix-col">
                            <input
                                type="checkbox"
                                :checked="getPermission(rolle.name, section.key, 'can_write')"
                                :disabled="rolle.name === 'meister' || istSpeichern"
                                @change="setPermission(rolle.name, section.key, 'can_write', $event.target.checked)"
                                class="dh-rollen__checkbox"
                            >
                        </span>
                        <span class="dh-rollen__matrix-col">
                            <input
                                type="checkbox"
                                :checked="getPermission(rolle.name, section.key, 'can_upload')"
                                :disabled="rolle.name === 'meister' || istSpeichern"
                                @change="setPermission(rolle.name, section.key, 'can_upload', $event.target.checked)"
                                class="dh-rollen__checkbox"
                            >
                        </span>
                    </div>
                </div>

                <!-- Meister-Hinweis -->
                <div v-if="rolle.name === 'meister'" class="dh-rollen__hinweis">
                    🛡️ Der Meister hat immer vollen Zugriff
                </div>
            </div>
        </div>

        <!-- Status-Anzeige -->
        <Transition name="dh-fade">
            <div v-if="statusText" class="dh-rollen__status" :class="'dh-rollen__status--' + statusTyp">
                {{ statusText }}
            </div>
        </Transition>
    </div>
</template>

<script>
import { apiPost } from '../api/client.js'

export default {
    name: 'RollenMatrix',

    props: {
        /** Initiale Rollen-Daten (aus PHP) */
        initialRoles: {
            type: Array,
            default: () => []
        },
        /** Initiale Berechtigungen { roleName: { section: { can_read, can_write, can_upload } } } */
        initialPermissions: {
            type: Object,
            default: () => ({})
        },
        /** API-Endpoint */
        apiUrl: {
            type: String,
            default: '/api/rollen.php'
        }
    },

    data() {
        return {
            rollen: [],
            berechtigungen: {},
            istSpeichern: false,
            statusText: '',
            statusTyp: 'info',
            statusTimer: null,
            sektionen: [
                { key: 'bibliothek', label: 'Bibliothek', icon: '📚' },
                { key: 'miliz', label: 'Miliz', icon: '⚔️' },
                { key: 'aushaenge', label: 'Aushänge', icon: '📌' },
                { key: 'verwaltung', label: 'Verwaltung', icon: '📋' }
            ]
        }
    },

    mounted() {
        this.rollen = this.initialRoles
        this.berechtigungen = JSON.parse(JSON.stringify(this.initialPermissions))
    },

    methods: {
        getPermission(roleName, section, field) {
            return this.berechtigungen?.[roleName]?.[section]?.[field] ?? false
        },

        async setPermission(roleName, section, field, value) {
            // Sofort im UI aktualisieren
            if (!this.berechtigungen[roleName]) {
                this.berechtigungen[roleName] = {}
            }
            if (!this.berechtigungen[roleName][section]) {
                this.berechtigungen[roleName][section] = { can_read: false, can_write: false, can_upload: false }
            }
            this.berechtigungen[roleName][section][field] = value

            // Per API speichern
            this.istSpeichern = true
            try {
                // Fallback: Wenn kein dedizierter API-Endpoint existiert,
                // wird das bestehende admin.php POST-Formular genutzt
                const perms = this.berechtigungen[roleName][section]

                const formData = new FormData()
                formData.append('action', 'update_permissions')
                formData.append('role_name', roleName)
                formData.append('section', section)
                formData.append('can_read', perms.can_read ? '1' : '0')
                formData.append('can_write', perms.can_write ? '1' : '0')
                formData.append('can_upload', perms.can_upload ? '1' : '0')
                formData.append('csrf_token', this.$csrfToken)

                // POST an admin.php (bestehendes System)
                const response = await fetch('/admin.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })

                if (response.ok) {
                    this.zeigStatus('✅ Gespeichert', 'success')
                } else {
                    this.zeigStatus('❌ Fehler beim Speichern', 'error')
                }
            } catch (error) {
                this.zeigStatus('❌ ' + (error.message || 'Netzwerkfehler'), 'error')
            } finally {
                this.istSpeichern = false
            }
        },

        zeigStatus(text, typ = 'info') {
            this.statusText = text
            this.statusTyp = typ
            clearTimeout(this.statusTimer)
            this.statusTimer = setTimeout(() => {
                this.statusText = ''
            }, 2500)
        }
    }
}
</script>

<style scoped>
.dh-rollen__liste {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.dh-rollen__karte {
    background: rgba(244, 228, 188, 0.3);
    border: 1px solid rgba(212, 175, 55, 0.2);
    border-radius: 10px;
    padding: 18px;
    transition: box-shadow 0.2s;
}

.dh-rollen__karte:hover {
    box-shadow: 0 2px 12px rgba(212, 175, 55, 0.15);
}

.dh-rollen__karte-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.dh-rollen__icon {
    font-size: 1.5rem;
}

.dh-rollen__name {
    font-family: 'MedievalSharp', cursive;
    font-size: 1.2rem;
    font-weight: 700;
}

.dh-rollen__system-badge {
    font-size: 0.7rem;
    padding: 2px 8px;
    background: rgba(212, 175, 55, 0.2);
    border-radius: 10px;
    color: rgba(62, 46, 30, 0.6);
    font-family: 'Crimson Text', serif;
}

/* Matrix */
.dh-rollen__matrix {
    display: grid;
    grid-template-columns: 1fr 60px 60px 60px;
    gap: 4px;
    align-items: center;
}

.dh-rollen__matrix-header {
    display: contents;
    font-family: 'Crimson Text', serif;
    font-size: 0.85rem;
    color: rgba(62, 46, 30, 0.6);
}

.dh-rollen__matrix-header .dh-rollen__matrix-col {
    text-align: center;
    font-weight: 600;
    padding-bottom: 6px;
}

.dh-rollen__matrix-row {
    display: contents;
}

.dh-rollen__matrix-label {
    font-family: 'Crimson Text', serif;
    font-size: 0.95rem;
    color: var(--text-ink, #3e2e1e);
    padding: 4px 0;
}

.dh-rollen__matrix-col {
    text-align: center;
}

.dh-rollen__checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent-gold, #d4af37);
}

.dh-rollen__checkbox:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Hinweis */
.dh-rollen__hinweis {
    margin-top: 10px;
    padding: 8px 12px;
    background: rgba(212, 175, 55, 0.1);
    border-radius: 6px;
    font-family: 'Crimson Text', serif;
    font-size: 0.85rem;
    color: rgba(62, 46, 30, 0.6);
}

/* Status */
.dh-rollen__status {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 10px 18px;
    border-radius: 8px;
    font-family: 'Crimson Text', serif;
    font-size: 0.95rem;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.dh-rollen__status--success {
    background: rgba(40, 167, 69, 0.95);
    color: #fff;
}

.dh-rollen__status--error {
    background: rgba(180, 40, 40, 0.95);
    color: #fff;
}

.dh-rollen__status--info {
    background: rgba(244, 228, 188, 0.95);
    color: var(--text-ink, #3e2e1e);
}

/* Responsive */
@media (max-width: 500px) {
    .dh-rollen__matrix {
        grid-template-columns: 1fr 50px 50px 50px;
    }

    .dh-rollen__matrix-header .dh-rollen__matrix-col {
        font-size: 0.75rem;
    }
}
</style>
