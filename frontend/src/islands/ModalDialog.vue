<template>
    <Teleport to="body">
        <Transition name="dh-modal">
            <div
                v-if="sichtbar"
                class="dh-modal__overlay"
                @click.self="schliessenBeiKlick"
                @keydown.escape="schliessen"
            >
                <div
                    class="dh-modal__content"
                    :class="['dh-modal__content--' + groesse]"
                    role="dialog"
                    :aria-label="titel"
                    aria-modal="true"
                >
                    <!-- Header -->
                    <div class="dh-modal__header">
                        <h3 class="dh-modal__titel">{{ titel }}</h3>
                        <button
                            class="dh-modal__close"
                            @click="schliessen"
                            aria-label="Schließen"
                        >&times;</button>
                    </div>

                    <!-- Body -->
                    <div class="dh-modal__body">
                        <slot></slot>
                    </div>

                    <!-- Footer (optional) -->
                    <div v-if="$slots.footer" class="dh-modal__footer">
                        <slot name="footer"></slot>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script>
export default {
    name: 'ModalDialog',

    props: {
        /** Titel des Modals */
        titel: {
            type: String,
            default: ''
        },
        /** Sichtbarkeit (v-model) */
        modelValue: {
            type: Boolean,
            default: false
        },
        /** Größe: 'klein', 'normal', 'gross', 'breit' */
        groesse: {
            type: String,
            default: 'normal',
            validator: (v) => ['klein', 'normal', 'gross', 'breit'].includes(v)
        },
        /** Schließen bei Klick außerhalb */
        schliessenBeiAussen: {
            type: Boolean,
            default: true
        }
    },

    emits: ['update:modelValue', 'close'],

    computed: {
        sichtbar: {
            get() { return this.modelValue },
            set(val) { this.$emit('update:modelValue', val) }
        }
    },

    watch: {
        sichtbar(neu) {
            if (neu) {
                document.body.style.overflow = 'hidden'
                // ESC-Listener
                this._escListener = (e) => {
                    if (e.key === 'Escape') this.schliessen()
                }
                document.addEventListener('keydown', this._escListener)
            } else {
                document.body.style.overflow = ''
                if (this._escListener) {
                    document.removeEventListener('keydown', this._escListener)
                }
            }
        }
    },

    beforeUnmount() {
        document.body.style.overflow = ''
        if (this._escListener) {
            document.removeEventListener('keydown', this._escListener)
        }
    },

    methods: {
        schliessen() {
            this.sichtbar = false
            this.$emit('close')
        },

        schliessenBeiKlick() {
            if (this.schliessenBeiAussen) {
                this.schliessen()
            }
        }
    }
}
</script>

<style scoped>
/* Overlay */
.dh-modal__overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2000;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

/* Content */
.dh-modal__content {
    background: linear-gradient(135deg, #f4e4bc, #e8d5a0);
    border-radius: 12px;
    box-shadow:
        0 20px 60px rgba(0, 0, 0, 0.5),
        0 0 0 1px rgba(212, 175, 55, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    max-height: 90vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

/* Größen-Varianten */
.dh-modal__content--klein {
    width: 100%;
    max-width: 400px;
}

.dh-modal__content--normal {
    width: 100%;
    max-width: 550px;
}

.dh-modal__content--gross {
    width: 100%;
    max-width: 750px;
}

.dh-modal__content--breit {
    width: 100%;
    max-width: 900px;
}

/* Header */
.dh-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    border-bottom: 2px solid rgba(212, 175, 55, 0.3);
}

.dh-modal__titel {
    margin: 0;
    font-family: 'MedievalSharp', cursive;
    font-size: 1.4rem;
    color: var(--text-ink, #3e2e1e);
}

.dh-modal__close {
    background: none;
    border: none;
    font-size: 2rem;
    color: rgba(62, 46, 30, 0.5);
    cursor: pointer;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
}

.dh-modal__close:hover {
    color: #b42828;
}

/* Body */
.dh-modal__body {
    padding: 24px;
    flex: 1;
    font-family: 'Crimson Text', serif;
    color: var(--text-ink, #3e2e1e);
    line-height: 1.6;
}

/* Footer */
.dh-modal__footer {
    padding: 16px 24px;
    border-top: 1px solid rgba(212, 175, 55, 0.2);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Transition */
.dh-modal-enter-active {
    transition: opacity 0.25s ease;
}

.dh-modal-enter-active .dh-modal__content {
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.25s ease;
}

.dh-modal-leave-active {
    transition: opacity 0.2s ease;
}

.dh-modal-leave-active .dh-modal__content {
    transition: transform 0.2s ease, opacity 0.2s ease;
}

.dh-modal-enter-from {
    opacity: 0;
}

.dh-modal-enter-from .dh-modal__content {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
}

.dh-modal-leave-to {
    opacity: 0;
}

.dh-modal-leave-to .dh-modal__content {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
}

/* Responsive */
@media (max-width: 600px) {
    .dh-modal__overlay {
        padding: 10px;
    }

    .dh-modal__content {
        max-height: 95vh;
    }

    .dh-modal__header {
        padding: 14px 16px;
    }

    .dh-modal__body {
        padding: 16px;
    }

    .dh-modal__footer {
        padding: 12px 16px;
    }
}
</style>
