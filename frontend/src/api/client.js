/**
 * Dämmerhafen 2.0 — API Client
 *
 * Zentraler Fetch-Wrapper für alle Vue-zu-PHP API-Aufrufe.
 * Handhabt CSRF-Tokens, Session-Auth und Fehlerbehandlung.
 */

/**
 * Liest das CSRF-Token aus dem Meta-Tag in header.php
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || ''
}

/**
 * Basis-Fetch Wrapper mit CSRF, Session-Auth und JSON-Handling
 *
 * @param {string} url - API-Endpoint (z.B. '/api/upload.php')
 * @param {Object} options - Fetch-Optionen
 * @returns {Promise<Object>} JSON-Response
 */
export async function apiFetch(url, options = {}) {
    const headers = {
        'X-CSRF-Token': getCsrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers || {})
    }

    // Content-Type nur setzen wenn kein FormData (Browser setzt es automatisch mit Boundary)
    if (!(options.body instanceof FormData)) {
        headers['Content-Type'] = 'application/json'
    }

    const response = await fetch(url, {
        credentials: 'same-origin', // Session-Cookies mitsenden
        ...options,
        headers
    })

    // Fehlerbehandlung
    if (!response.ok) {
        let errorData = {}
        try {
            errorData = await response.json()
        } catch {
            // Response war kein JSON
        }

        const error = new Error(errorData.error || `API-Fehler: ${response.status}`)
        error.status = response.status
        error.data = errorData
        throw error
    }

    return response.json()
}

/**
 * GET-Request
 */
export function apiGet(url, params = {}) {
    const query = new URLSearchParams(params).toString()
    const fullUrl = query ? `${url}?${query}` : url
    return apiFetch(fullUrl, { method: 'GET' })
}

/**
 * POST-Request mit JSON-Body
 */
export function apiPost(url, data = {}) {
    return apiFetch(url, {
        method: 'POST',
        body: JSON.stringify(data)
    })
}

/**
 * POST-Request mit FormData (für Datei-Uploads)
 */
export function apiPostForm(url, formData) {
    return apiFetch(url, {
        method: 'POST',
        body: formData
        // Content-Type wird automatisch gesetzt (multipart/form-data mit boundary)
    })
}

/**
 * DELETE-Request
 */
export function apiDelete(url, data = {}) {
    return apiFetch(url, {
        method: 'DELETE',
        body: JSON.stringify(data)
    })
}

/**
 * Upload mit Fortschrittsanzeige via XMLHttpRequest
 *
 * @param {string} url - Upload-Endpoint
 * @param {FormData} formData - Datei-Daten
 * @param {Function} onProgress - Callback(percent) für Fortschritt
 * @returns {Promise<Object>} JSON-Response
 */
export function apiUpload(url, formData, onProgress = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest()

        // Fortschritt tracken
        if (onProgress) {
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100)
                    onProgress(percent)
                }
            })
        }

        xhr.addEventListener('load', () => {
            try {
                const data = JSON.parse(xhr.responseText)
                if (xhr.status >= 200 && xhr.status < 300) {
                    resolve(data)
                } else {
                    const error = new Error(data.error || `Upload-Fehler: ${xhr.status}`)
                    error.status = xhr.status
                    error.data = data
                    reject(error)
                }
            } catch {
                reject(new Error('Ungültige Server-Antwort'))
            }
        })

        xhr.addEventListener('error', () => reject(new Error('Netzwerkfehler beim Upload')))
        xhr.addEventListener('abort', () => reject(new Error('Upload abgebrochen')))

        xhr.open('POST', url)
        xhr.setRequestHeader('X-CSRF-Token', getCsrfToken())
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')
        xhr.withCredentials = true
        xhr.send(formData)
    })
}
