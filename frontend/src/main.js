/**
 * Dämmerhafen 2.0 — Vue Islands Entry Point
 *
 * Dieses Script mountet Vue-Komponenten automatisch in PHP-gerenderte Seiten.
 * Jedes HTML-Element mit [data-vue-island="KomponentenName"] wird als
 * eigenständige Vue-App gemountet.
 *
 * Props werden aus dem data-props Attribut als JSON gelesen.
 */

import { createApp } from 'vue'
import './style.css'

// Alle Vue-Island-Komponenten automatisch importieren
const islandModules = import.meta.glob('./islands/*.vue', { eager: true })

/**
 * Registrierte Inseln als Map: Name → Komponente
 * z.B. { 'ToastNachricht': ToastNachrichtComponent, ... }
 */
const islands = {}
for (const path in islandModules) {
    const name = path.replace('./islands/', '').replace('.vue', '')
    islands[name] = islandModules[path].default
}

/**
 * Mountet alle Vue-Inseln im DOM
 */
function mountIslands() {
    const elements = document.querySelectorAll('[data-vue-island]')

    elements.forEach(el => {
        const name = el.dataset.vueIsland

        if (!islands[name]) {
            console.warn(`[Dämmerhafen] Vue-Insel "${name}" nicht gefunden. Verfügbar:`, Object.keys(islands))
            return
        }

        // Props aus data-props Attribut parsen
        let props = {}
        try {
            props = JSON.parse(el.dataset.props || '{}')
        } catch (e) {
            console.error(`[Dämmerhafen] Ungültige Props für Insel "${name}":`, e)
        }

        // Slots aus data-slots Attribut (optional)
        const slotsData = el.dataset.slots || null

        // Vue-App erstellen und mounten
        const app = createApp(islands[name], props)

        // Globale Properties für alle Inseln
        app.config.globalProperties.$csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''

        app.mount(el)

        // Markiere als gemountet
        el.dataset.vueMounted = 'true'

        if (import.meta.env.DEV) {
            console.log(`[Dämmerhafen] Insel "${name}" gemountet`, props)
        }
    })
}

// DOM-Ready: Alle Inseln mounten
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountIslands)
} else {
    mountIslands()
}

// Export für manuelles Mounting (z.B. nach AJAX-Content-Load)
window.__daemmerhafen = {
    mountIslands,
    islands
}
