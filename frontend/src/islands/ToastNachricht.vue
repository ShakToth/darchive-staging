<template>
    <Transition name="dh-toast">
        <div
            v-if="sichtbar"
            class="dh-toast"
            :class="['dh-toast--' + typ]"
            @click="schliessen"
        >
            <span class="dh-toast__icon">{{ icon }}</span>
            <span class="dh-toast__text">{{ text }}</span>
            <button class="dh-toast__close" @click.stop="schliessen" aria-label="Schließen">&times;</button>
            <div class="dh-toast__timer" :style="{ animationDuration: dauer + 'ms' }"></div>
        </div>
    </Transition>
</template>

<script>
export default {
    name: 'ToastNachricht',

    props: {
        /** Nachrichtentyp: 'success', 'error', 'info', 'warning' */
        type: {
            type: String,
            default: 'info',
            validator: (v) => ['success', 'error', 'info', 'warning'].includes(v)
        },
        /** Nachrichtentext */
        text: {
            type: String,
            default: ''
        },
        /** Auto-Dismiss Dauer in Millisekunden (0 = kein Auto-Dismiss) */
        duration: {
            type: Number,
            default: 4000
        }
    },

    data() {
        return {
            sichtbar: false,
            timer: null
        }
    },

    computed: {
        typ() {
            return this.type
        },
        dauer() {
            return this.duration
        },
        icon() {
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            }
            return icons[this.typ] || 'ℹ️'
        }
    },

    mounted() {
        // Kurz verzögert einblenden für Animationseffekt
        requestAnimationFrame(() => {
            this.sichtbar = true
        })

        // Auto-Dismiss starten
        if (this.dauer > 0) {
            this.timer = setTimeout(() => {
                this.schliessen()
            }, this.dauer)
        }
    },

    beforeUnmount() {
        if (this.timer) {
            clearTimeout(this.timer)
        }
    },

    methods: {
        schliessen() {
            this.sichtbar = false
        }
    }
}
</script>

<style scoped>
.dh-toast {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border-radius: 8px;
    font-family: 'Crimson Text', serif;
    font-size: 1.05rem;
    color: #3e2e1e;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    max-width: 500px;
    min-width: 300px;
    overflow: hidden;
    backdrop-filter: blur(8px);
}

/* Typ-Varianten */
.dh-toast--success {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.95), rgba(33, 136, 56, 0.95));
    color: #fff;
    border-left: 4px solid #1a8a3a;
}

.dh-toast--error {
    background: linear-gradient(135deg, rgba(180, 40, 40, 0.95), rgba(140, 20, 20, 0.95));
    color: #fff;
    border-left: 4px solid #8b0000;
}

.dh-toast--warning {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.95), rgba(180, 148, 40, 0.95));
    color: #3e2e1e;
    border-left: 4px solid #b8860b;
}

.dh-toast--info {
    background: linear-gradient(135deg, rgba(244, 228, 188, 0.97), rgba(227, 209, 162, 0.97));
    color: #3e2e1e;
    border-left: 4px solid #d4af37;
}

.dh-toast__icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.dh-toast__text {
    flex: 1;
    line-height: 1.4;
}

.dh-toast__close {
    background: none;
    border: none;
    color: inherit;
    font-size: 1.4rem;
    cursor: pointer;
    padding: 0 4px;
    opacity: 0.7;
    transition: opacity 0.2s;
    flex-shrink: 0;
}

.dh-toast__close:hover {
    opacity: 1;
}

/* Timer-Leiste am unteren Rand */
.dh-toast__timer {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(255, 255, 255, 0.4);
    animation: dh-toast-timer linear forwards;
    width: 100%;
}

@keyframes dh-toast-timer {
    from { width: 100%; }
    to { width: 0%; }
}

/* Responsive */
@media (max-width: 600px) {
    .dh-toast {
        right: 10px;
        left: 10px;
        max-width: none;
        min-width: auto;
    }
}
</style>
