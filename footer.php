<?php
// DIREKTER AUFRUF VERBOTEN
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Direct access to this file is not allowed.</p></body></html>');
}
?>

<!-- GLOBALE JAVASCRIPT FUNKTIONEN -->
<script>
// ESC-Taste schließt alle Modals/Lightboxes
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        // Alle Lightboxes schließen
        document.querySelectorAll('.lightbox').forEach(function(lb) {
            lb.style.display = 'none';
            // PDFs zurücksetzen
            const pdfFrame = lb.querySelector('iframe');
            if (pdfFrame) pdfFrame.src = '';
        });
    }
});

// Zentrale Lightbox-Funktion für Bilder
function openLightbox(url, type = 'image') {
    const lightbox = document.getElementById('globalLightbox') || createGlobalLightbox();
    const img = lightbox.querySelector('.lightbox-image');
    const pdf = lightbox.querySelector('.lightbox-pdf');
    const text = lightbox.querySelector('.lightbox-text');
    
    lightbox.style.display = 'block';
    img.style.display = 'none';
    pdf.style.display = 'none';
    text.style.display = 'none';
    
    if (type === 'pdf') {
        pdf.style.display = 'block';
        pdf.src = url;
    } else if (type === 'text') {
        text.style.display = 'block';
        text.innerHTML = '<div class="library-viewer__loading">Lade Inhalt…</div>';
        fetch('api/library_viewer.php?file=' + encodeURIComponent(url), { credentials: 'same-origin' })
            .then(function(resp) { return resp.text(); })
            .then(function(html) {
                text.innerHTML = html;
            })
            .catch(function() {
                text.innerHTML = '<p>Viewer konnte nicht geladen werden.</p>';
            });
    } else {
        img.style.display = 'block';
        img.src = url;
    }
}

function closeLightbox() {
    const lightbox = document.getElementById('globalLightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
        const pdf = lightbox.querySelector('.lightbox-pdf');
        const text = lightbox.querySelector('.lightbox-text');
        if (pdf) pdf.src = '';
        if (text) text.innerHTML = '';
    }
}

// Erstelle globale Lightbox wenn nicht vorhanden
function createGlobalLightbox() {
    const lb = document.createElement('div');
    lb.id = 'globalLightbox';
    lb.className = 'lightbox';
    lb.onclick = function(e) { if(e.target === this) closeLightbox(); };
    lb.innerHTML = `
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img class="lightbox-content lightbox-image" alt="Vorschau" style="display:none;">
        <iframe class="lightbox-content lightbox-pdf" style="display:none; width:90vw; height:90vh; border:none;"></iframe>
        <div class="lightbox-content lightbox-text" style="display:none; width:min(1000px,92vw); max-height:90vh; overflow:auto;"></div>
    `;
    document.body.appendChild(lb);
    return lb;
}
</script>

<?php if (file_exists(__DIR__ . '/dist/app.js')): ?>
<script src="dist/app.js?v=<?php echo filemtime(__DIR__ . '/dist/app.js'); ?>"></script>
<?php endif; ?>

</body>
</html>
