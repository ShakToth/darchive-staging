<?php
$includeAushaenge = true;
$view = isset($_GET['view']) ? $_GET['view'] : 'board';
$pageTitle = 'Das Schwarze Brett - Dämmerhafen';
$bodyClass = ($view === 'board') ? 'rp-view-board' : 'rp-view-zettelkiste';

require_once 'functions.php';
require_once 'functions_aushaenge.php';

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

// -------------------------------------------------------
// POST-Handling mit PRG-Pattern (verhindert Doppel-Submit)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {

    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Das Pergament ist verrutscht (CSRF-Fehler).'];
        header('Location: aushaenge.php?view=' . urlencode($view));
        exit;
    }

    // Aushang erstellen
    if (isset($_POST['create_aushang']) && hasPermission('aushaenge', 'write')) {
        $bild = (isset($_FILES['bild']) && $_FILES['bild']['error'] !== UPLOAD_ERR_NO_FILE) ? $_FILES['bild'] : null;
        $result = createAushang(
            $_POST['titel']       ?? '',
            $_POST['inhalt']      ?? '',
            $_POST['signatur']    ?? 'Unbekannt',
            $bild,
            $_POST['format_type'] ?? 'markdown'
        );
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        header('Location: aushaenge.php?view=board');
        exit;
    }

    // Aushang bearbeiten
    if (isset($_POST['edit_aushang'])) {
        $result = updateAushang(
            intval($_POST['aushang_id']),
            $_POST['titel']        ?? '',
            $_POST['inhalt']       ?? '',
            $_POST['signatur']     ?? '',
            $_POST['format_type']  ?? 'markdown'
        );
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        header('Location: aushaenge.php?view=' . urlencode($view));
        exit;
    }

    // Aushang löschen
    if (isset($_POST['delete_aushang']) && isMeister()) {
        $result = deleteAushang($_POST['aushang_id']);
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        header('Location: aushaenge.php?view=' . urlencode($view));
        exit;
    }

    // Wichtig-Siegel setzen/entfernen (nur Meister)
    if (isset($_POST['toggle_wichtig']) && isMeister()) {
        $result = toggleWichtig(intval($_POST['aushang_id']));
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        header('Location: aushaenge.php?view=' . urlencode($view));
        exit;
    }

    // Anheften/Losheften (nur Meister)
    if (isset($_POST['toggle_angeheftet']) && isMeister()) {
        $result = toggleAngeheftet(intval($_POST['aushang_id']));
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        header('Location: aushaenge.php?view=' . urlencode($view));
        exit;
    }

    // Notiz anheften
    if (isset($_POST['add_notiz']) && hasPermission('aushaenge', 'write')) {
        $autorName = $_POST['notiz_autor'] ?? ($_SESSION['username'] ?? 'Unbekannt');
        $autorId = isLoggedIn() ? ($_SESSION['user_id'] ?? null) : null;
        $result = addNotiz(
            intval($_POST['zettel_id']),
            $_POST['notiz_text'] ?? '',
            $autorName,
            $autorId
        );
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        header('Location: aushaenge.php?view=' . urlencode($view));
        exit;
    }

    // Notiz löschen
    if (isset($_POST['delete_notiz'])) {
        $result = deleteNotiz(intval($_POST['notiz_id']));
        $_SESSION['flash'] = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
        header('Location: aushaenge.php?view=' . urlencode($view));
        exit;
    }

    // Fallback
    header('Location: aushaenge.php?view=' . urlencode($view));
    exit;
}

// Flash-Message aus Session holen (nach PRG)
// Eigene Variable damit header.php $message nicht "klaut"
$flashMsg = null;
if (!empty($_SESSION['flash'])) {
    $flashMsg = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$zettelListe      = ($view === 'zettelkiste') ? getAushaenge(null, $searchQuery) : getAushaenge(15);
$totalZettelCount = count(getAushaenge(null));

// Explizit null setzen damit header.php keine alte $message erbt
// Unsere eigene Flash-Meldung läuft über $flashMsg mit Auto-Dismiss
$message = null;

require_once 'header.php';
?>

<a href="index.php" class="rp-btn rp-btn--back">← Raum verlassen</a>

<?php if ($flashMsg): ?>
    <div id="flashMessage" style="
        position: fixed; top: 80px; left: 50%; transform: translateX(-50%);
        z-index: 9999; padding: 15px 30px; border-radius: 8px; font-weight: bold;
        box-shadow: 0 4px 15px rgba(0,0,0,0.5); transition: opacity 0.8s ease;
        background: <?= $flashMsg['type'] === 'success' ? 'rgba(0,130,0,0.92)' : 'rgba(160,0,0,0.92)' ?>;
        color: #fff; max-width: 500px; text-align: center;">
        <?= htmlspecialchars($flashMsg['text']) ?>
    </div>
    <script>
        (function() {
            var el = document.getElementById('flashMessage');
            if (!el) return;
            // Nach 2.5s ausblenden, nach 3.5s aus DOM entfernen
            setTimeout(function() {
                el.style.opacity = '0';
            }, 2500);
            setTimeout(function() {
                if (el && el.parentNode) el.parentNode.removeChild(el);
            }, 3500);
        })();
    </script>
<?php endif; ?>

<?php if ($view === 'board'): ?>
    <div id="parallaxBoardBg" class="rp-bg-parallax" data-category="board"></div>

    <div class="rp-container rp-container--board">
        <?php foreach ($zettelListe as $z):
            $rotation     = rand(-5, 5);
            $fmt          = $z['format_type'] ?: 'markdown';
            $safeSignatur = htmlspecialchars($z['signatur'], ENT_QUOTES, 'UTF-8');
            $safeBild     = $z['bild_pfad'] ? htmlspecialchars($z['bild_pfad'], ENT_QUOTES, 'UTF-8') : '';
            // Render für Anzeige im Zettel
            $renderedTitel  = renderAushangTitle($z['titel'], $fmt);
            $renderedInhalt = renderAushangContent($z['inhalt'], $fmt);
        ?>
            <?php
            // data-* Attribute: rendered HTML für Lightbox, raw für Edit-Modal
            // htmlspecialchars schützt die Attributwerte sicher, egal was drin steht
            $dataRenderedTitel  = htmlspecialchars($renderedTitel,  ENT_QUOTES, 'UTF-8');
            $dataRenderedInhalt = htmlspecialchars($renderedInhalt, ENT_QUOTES, 'UTF-8');
            $dataRawTitel       = htmlspecialchars($z['titel'],     ENT_QUOTES, 'UTF-8');
            $dataRawInhalt      = htmlspecialchars($z['inhalt'],    ENT_QUOTES, 'UTF-8');
            ?>
            <?php
            $istWichtig = !empty($z['ist_wichtig']);
            $istAngeheftet = !empty($z['angeheftet']);
            $notizCount = getNotizCount($z['id']);
            $zettelClasses = 'rp-card rp-card--zettel';
            if ($istWichtig) $zettelClasses .= ' zettel--wichtig';
            if ($istAngeheftet) $zettelClasses .= ' zettel--angeheftet';
            ?>
            <div class="<?= $zettelClasses ?>"
                 style="transform: rotate(<?= $rotation ?>deg);"
                 data-rendered-titel="<?= $dataRenderedTitel ?>"
                 data-rendered-inhalt="<?= $dataRenderedInhalt ?>"
                 data-signatur="<?= $safeSignatur ?>"
                 data-bild="<?= $safeBild ?>"
                 data-id="<?= $z['id'] ?>"
                 data-raw-titel="<?= $dataRawTitel ?>"
                 data-raw-inhalt="<?= $dataRawInhalt ?>"
                 data-fmt="<?= htmlspecialchars($fmt, ENT_QUOTES) ?>"
                 onclick="readZettelFromCard(this)">

                <?php if ($istAngeheftet): ?>
                    <span class="zettel--angeheftet-badge">📌 Angeheftet</span>
                <?php endif; ?>

                <h3 class="rp-card__title"><?= $renderedTitel ?></h3>
                <div class="rp-card__content"><?= $renderedInhalt ?></div>
                <span class="rp-signature">- <?= $safeSignatur ?></span>

                <?php if ($safeBild): ?>
                    <div style="margin-top:10px; color:var(--accent-gold); font-size:0.9rem;">🔎 Beigefügte Skizze</div>
                <?php endif; ?>
                <?php if ($fmt === 'html'): ?>
                    <span style="position:absolute; top:5px; right:8px; font-size:0.7rem; color:#aaa;" title="HTML-Modus">🎨</span>
                <?php endif; ?>

                <?php if ($notizCount > 0): ?>
                    <span class="zettel-notiz__count" title="<?= $notizCount ?> Notiz(en) angeheftet">📎 <?= $notizCount ?> Notiz<?= $notizCount > 1 ? 'en' : '' ?></span>
                <?php endif; ?>

                <div style="display:flex; gap:5px; flex-wrap:wrap; margin-top:10px;" onclick="event.stopPropagation()">
                    <?php if (canEditAushang($z['id'])): ?>
                        <button class="rp-btn rp-btn--primary rp-btn--small" onclick="openEditFromCard(this.closest('.rp-card'))">
                            ✏️ Bearbeiten
                        </button>
                    <?php endif; ?>
                    <?php if (isMeister()): ?>
                        <form method="post" style="margin:0; display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="toggle_wichtig" value="1">
                            <input type="hidden" name="aushang_id" value="<?= $z['id'] ?>">
                            <button type="submit" class="rp-btn rp-btn--small" title="<?= $istWichtig ? 'Siegel entfernen' : 'Siegel setzen' ?>">
                                <?= $istWichtig ? '⭕' : '🔴' ?>
                            </button>
                        </form>
                        <form method="post" style="margin:0; display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="toggle_angeheftet" value="1">
                            <input type="hidden" name="aushang_id" value="<?= $z['id'] ?>">
                            <button type="submit" class="rp-btn rp-btn--small" title="<?= $istAngeheftet ? 'Losheften' : 'Anheften' ?>">
                                <?= $istAngeheftet ? '📌' : '📍' ?>
                            </button>
                        </form>
                        <form method="post" onsubmit="return confirm('Diesen Zettel abreißen?');" style="margin:0;">
                            <input type="hidden" name="csrf_token"     value="<?= $csrfToken ?>">
                            <input type="hidden" name="delete_aushang" value="1">
                            <input type="hidden" name="aushang_id"     value="<?= $z['id'] ?>">
                            <button type="submit" class="rp-btn rp-btn--danger rp-btn--small">🔥 Abreißen</button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Notizen anzeigen + Formular -->
                <div onclick="event.stopPropagation()">
                    <?php $notizen = getNotizen($z['id']); ?>
                    <?php if (!empty($notizen)): ?>
                        <div class="zettel-notizen__container">
                            <?php foreach ($notizen as $notiz): ?>
                                <div class="zettel-notiz">
                                    <div class="zettel-notiz__text"><?= htmlspecialchars($notiz['text'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="zettel-notiz__meta">
                                        — <?= htmlspecialchars($notiz['autor_name']) ?>, <?= date('d.m.Y H:i', strtotime($notiz['erstellt_am'])) ?>
                                        <?php if (isMeister() || (isLoggedIn() && ($notiz['autor_id'] ?? 0) == ($_SESSION['user_id'] ?? -1))): ?>
                                            <form method="post" style="display:inline; margin-left:6px;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="delete_notiz" value="1">
                                                <input type="hidden" name="notiz_id" value="<?= $notiz['id'] ?>">
                                                <button type="submit" style="background:none; border:none; color:#b42828; cursor:pointer; font-size:0.75rem;">✕</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (hasPermission('aushaenge', 'write')): ?>
                        <form method="post" class="zettel-notiz__form" style="margin-top:8px;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="add_notiz" value="1">
                            <input type="hidden" name="zettel_id" value="<?= $z['id'] ?>">
                            <input type="text" name="notiz_text" placeholder="Notiz anheften..." required maxlength="500" style="flex:1;">
                            <input type="hidden" name="notiz_autor" value="<?= htmlspecialchars($_SESSION['username'] ?? 'Unbekannt') ?>">
                            <button type="submit" class="rp-btn rp-btn--primary rp-btn--small">📎</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <div class="rp-container" style="max-width:1000px; margin-top:20px;">
        <h1 style="color:var(--accent-gold); text-shadow:1px 1px 3px rgba(0,0,0,0.8);">Die Zettelkiste</h1>
        <p class="rp-text-intro">Hier liegen alle alten Papiere und Notizen, die vom Brett gefallen sind.</p>

        <form method="get" class="rp-controls" style="margin-bottom:20px; background:rgba(0,0,0,0.4); padding:15px; border-radius:5px;">
            <input type="hidden" name="view" value="zettelkiste">
            <input type="text" name="q" class="rp-input" placeholder="Zettel durchsuchen..."
                   value="<?= htmlspecialchars($searchQuery) ?>" style="width:300px;">
            <button type="submit" class="rp-btn rp-btn--primary">Suchen</button>
            <?php if (!empty($searchQuery)): ?>
                <a href="aushaenge.php?view=zettelkiste" class="rp-btn rp-btn--primary" style="text-decoration:none;">✕</a>
            <?php endif; ?>
        </form>

        <div class="rp-grid rp-grid--2col">
            <?php foreach ($zettelListe as $z):
                $fmt          = $z['format_type'] ?: 'markdown';
                $safeSignatur = htmlspecialchars($z['signatur'], ENT_QUOTES, 'UTF-8');
                $safeBild     = $z['bild_pfad'] ? htmlspecialchars($z['bild_pfad'], ENT_QUOTES, 'UTF-8') : '';
                $renderedTitel  = renderAushangTitle($z['titel'], $fmt);
                $renderedInhalt = renderAushangContent($z['inhalt'], $fmt);
                $dataRenderedTitel  = htmlspecialchars($renderedTitel,  ENT_QUOTES, 'UTF-8');
                $dataRenderedInhalt = htmlspecialchars($renderedInhalt, ENT_QUOTES, 'UTF-8');
                $dataRawTitel       = htmlspecialchars($z['titel'],     ENT_QUOTES, 'UTF-8');
                $dataRawInhalt      = htmlspecialchars($z['inhalt'],    ENT_QUOTES, 'UTF-8');
                $istWichtig    = !empty($z['ist_wichtig']);
                $istAngeheftet = !empty($z['angeheftet']);
                $notizCount    = getNotizCount($z['id']);
                $entryClasses  = 'rp-card rp-card--entry';
                if ($istWichtig)    $entryClasses .= ' zettel--wichtig';
                if ($istAngeheftet) $entryClasses .= ' zettel--angeheftet';
            ?>
                <div class="<?= $entryClasses ?>"
                     data-rendered-titel="<?= $dataRenderedTitel ?>"
                     data-rendered-inhalt="<?= $dataRenderedInhalt ?>"
                     data-signatur="<?= $safeSignatur ?>"
                     data-bild="<?= $safeBild ?>"
                     data-id="<?= $z['id'] ?>"
                     data-raw-titel="<?= $dataRawTitel ?>"
                     data-raw-inhalt="<?= $dataRawInhalt ?>"
                     data-fmt="<?= htmlspecialchars($fmt, ENT_QUOTES) ?>">

                    <div class="rp-card__header">
                        <h3 style="margin:0;">
                            <?php if ($istAngeheftet): ?><span class="zettel--angeheftet-badge">📌 Angeheftet</span> <?php endif; ?>
                            <?= $renderedTitel ?>
                        </h3>
                        <span class="rp-meta-date"><?= date('d.m.Y H:i', strtotime($z['datum'])) ?></span>
                    </div>
                    <div class="rp-card__content" style="font-size:0.9rem; max-height:80px; overflow:hidden;">
                        <?= $renderedInhalt ?>
                    </div>
                    <span class="rp-signature" style="display:block; margin-top:10px; font-style:italic;">- <?= $safeSignatur ?></span>

                    <?php if ($notizCount > 0): ?>
                        <span class="zettel-notiz__count">📎 <?= $notizCount ?> Notiz<?= $notizCount > 1 ? 'en' : '' ?></span>
                    <?php endif; ?>

                    <!-- Notizen anzeigen -->
                    <?php $notizen = getNotizen($z['id']); ?>
                    <?php if (!empty($notizen)): ?>
                        <div class="zettel-notizen__container">
                            <?php foreach ($notizen as $notiz): ?>
                                <div class="zettel-notiz">
                                    <div class="zettel-notiz__text"><?= htmlspecialchars($notiz['text'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="zettel-notiz__meta">
                                        — <?= htmlspecialchars($notiz['autor_name']) ?>, <?= date('d.m.Y H:i', strtotime($notiz['erstellt_am'])) ?>
                                        <?php if (isMeister() || (isLoggedIn() && ($notiz['autor_id'] ?? 0) == ($_SESSION['user_id'] ?? -1))): ?>
                                            <form method="post" style="display:inline; margin-left:6px;">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="delete_notiz" value="1">
                                                <input type="hidden" name="notiz_id" value="<?= $notiz['id'] ?>">
                                                <button type="submit" style="background:none; border:none; color:#b42828; cursor:pointer; font-size:0.75rem;">✕</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Notiz-Formular -->
                    <?php if (hasPermission('aushaenge', 'write')): ?>
                        <form method="post" class="zettel-notiz__form" style="margin-top:8px;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="add_notiz" value="1">
                            <input type="hidden" name="zettel_id" value="<?= $z['id'] ?>">
                            <input type="text" name="notiz_text" placeholder="Notiz anheften..." required maxlength="500" style="flex:1;">
                            <input type="hidden" name="notiz_autor" value="<?= htmlspecialchars($_SESSION['username'] ?? 'Unbekannt') ?>">
                            <button type="submit" class="rp-btn rp-btn--primary rp-btn--small">📎</button>
                        </form>
                    <?php endif; ?>

                    <div class="rp-card__footer" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                        <button class="rp-btn rp-btn--primary rp-btn--small"
                                onclick="readZettelFromCard(this.closest('.rp-card'))">📖 Lesen</button>

                        <?php if (canEditAushang($z['id'])): ?>
                            <button class="rp-btn rp-btn--primary rp-btn--small"
                                    onclick="openEditFromCard(this.closest('.rp-card'))">✏️ Bearbeiten</button>
                        <?php endif; ?>

                        <?php if (isMeister()): ?>
                            <form method="post" style="margin:0; display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="toggle_wichtig" value="1">
                                <input type="hidden" name="aushang_id" value="<?= $z['id'] ?>">
                                <button type="submit" class="rp-btn rp-btn--small" title="<?= $istWichtig ? 'Siegel entfernen' : 'Siegel setzen' ?>">
                                    <?= $istWichtig ? '⭕' : '🔴' ?>
                                </button>
                            </form>
                            <form method="post" style="margin:0; display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <input type="hidden" name="toggle_angeheftet" value="1">
                                <input type="hidden" name="aushang_id" value="<?= $z['id'] ?>">
                                <button type="submit" class="rp-btn rp-btn--small" title="<?= $istAngeheftet ? 'Losheften' : 'Anheften' ?>">
                                    <?= $istAngeheftet ? '📌' : '📍' ?>
                                </button>
                            </form>
                            <form method="post" onsubmit="return confirm('Diesen Zettel verbrennen?');" style="margin:0;">
                                <input type="hidden" name="csrf_token"     value="<?= $csrfToken ?>">
                                <input type="hidden" name="delete_aushang" value="1">
                                <input type="hidden" name="aushang_id"     value="<?= $z['id'] ?>">
                                <button type="submit" class="rp-btn rp-btn--danger rp-btn--small">🔥</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- BOTTOM NAV -->
<div class="miliz-bottom-nav">
    <?php if (hasPermission('aushaenge', 'write')): ?>
        <a href="#" onclick="document.getElementById('modalAushang').style.display='block'; return false;" class="miliz-nav-btn">
            <span class="miliz-nav-icon">📌</span>
            <span class="miliz-nav-label">Zettel anpinnen</span>
        </a>
    <?php endif; ?>
    <?php if ($view === 'zettelkiste'): ?>
        <a href="aushaenge.php?view=board" class="miliz-nav-btn">
            <span class="miliz-nav-icon">🪵</span>
            <span class="miliz-nav-label">Zurück zum Brett</span>
        </a>
    <?php else: ?>
        <a href="aushaenge.php?view=zettelkiste" class="miliz-nav-btn">
            <span class="miliz-nav-icon">🗃️</span>
            <span class="miliz-nav-label">Zettelkiste durchwühlen</span>
            <?php if ($totalZettelCount > 0): ?>
                <span class="miliz-nav-counter" style="background:var(--accent-gold);"><?= $totalZettelCount ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
</div>

<!-- ========================================================
     MODAL: NEUER AUSHANG
     ======================================================== -->
<div id="modalAushang" class="rp-modal" style="display:none; background-color:rgba(0,0,0,0.8);"
     onclick="if(event.target===this) this.style.display='none'">
    <div style="max-width:550px; margin:80px auto; background:var(--bg-parchment); padding:30px;
                border-radius:5px; box-shadow:0 0 20px black; position:relative; color:var(--text-ink);">
        <span class="rp-modal__close" onclick="document.getElementById('modalAushang').style.display='none'"
              style="color:var(--text-ink);">&times;</span>

        <h2 style="font-family:var(--font-heading); margin-top:0;">📌 Neuen Zettel verfassen</h2>

        <form method="post" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:15px;">
            <input type="hidden" name="csrf_token"     value="<?= $csrfToken ?>">
            <input type="hidden" name="create_aushang" value="1">

            <div>
                <label class="rp-label">Überschrift:</label>
                <input type="text" name="titel" required class="rp-input" style="width:100%; box-sizing:border-box;">
            </div>

            <div>
                <label class="rp-label">Format:</label>
                <select name="format_type" id="createFormatType" class="rp-select"
                        style="width:100%; padding:10px; border:2px solid #c9a961; border-radius:5px; font-size:1rem;"
                        onchange="updateFormatHint(this.value, 'createFormatHint'); highlightFormat(this);">
                    <option value="markdown" selected>✏️ Markdown &mdash; **fett** *kursiv* - Liste [Link](URL)</option>
                    <option value="html">🎨 HTML &mdash; &lt;strong&gt; &lt;em&gt; &lt;span style&gt; &lt;ul&gt; etc.</option>
                </select>
                <div id="createFormatBadge" style="display:inline-block; margin-top:5px; padding:3px 10px; border-radius:10px;
                     background:rgba(212,175,55,0.2); border:1px solid var(--accent-gold); font-size:0.8rem; color:var(--text-ink);">
                    Aktuell: <strong>Markdown</strong>
                </div>
            </div>

            <div>
                <label class="rp-label">Text:</label>
                <textarea name="inhalt" rows="8" required class="rp-textarea"
                          style="width:100%; box-sizing:border-box; resize:vertical; font-family:monospace; font-size:0.9rem;"></textarea>
                <small id="createFormatHint" style="color:#666; font-size:0.82rem; display:block; margin-top:4px;">
                    ✨ **fett** &nbsp;|&nbsp; *kursiv* &nbsp;|&nbsp; - Liste &nbsp;|&nbsp; [Link](URL)
                </small>
            </div>

            <div>
                <label class="rp-label">Signatur:</label>
                <input type="text" name="signatur" required class="rp-input"
                       style="width:100%; box-sizing:border-box;" placeholder="Dein Charaktername">
            </div>

            <div style="background:rgba(0,0,0,0.05); padding:15px; border-radius:4px; border:1px dashed rgba(0,0,0,0.3);">
                <label class="rp-label" style="display:block; margin-bottom:8px;">Optional: Skizze beifügen (max. 5 MB, JPG/PNG/WEBP)</label>
                <label class="rp-btn rp-btn--primary" style="cursor:pointer; display:inline-block; margin-top:5px;">
                    📂 Skizze auswählen
                    <input type="file" name="bild" accept="image/jpeg,image/png,image/webp" style="display:none;"
                           onchange="document.getElementById('uploadFileName').innerText = this.files[0] ? this.files[0].name : 'Keine Skizze gewählt';">
                </label>
                <span id="uploadFileName" style="margin-left:10px; font-style:italic; color:#555;">Keine Skizze gewählt</span>
            </div>

            <button type="submit" class="rp-btn rp-btn--primary" style="padding:15px; font-size:1.1rem; margin-top:5px;">
                📌 An das Brett nageln
            </button>
        </form>
    </div>
</div>

<!-- ========================================================
     MODAL: AUSHANG BEARBEITEN
     ======================================================== -->
<div id="modalEdit" class="rp-modal" style="display:none; background-color:rgba(0,0,0,0.8);"
     onclick="if(event.target===this) this.style.display='none'">
    <div style="max-width:600px; margin:80px auto; background:var(--bg-parchment); padding:30px;
                border-radius:5px; box-shadow:0 0 20px black; position:relative; color:var(--text-ink);">
        <span class="rp-modal__close" onclick="document.getElementById('modalEdit').style.display='none'"
              style="color:var(--text-ink);">&times;</span>

        <h2 style="font-family:var(--font-heading); margin-top:0;">✏️ Aushang bearbeiten</h2>

        <form method="post" style="display:flex; flex-direction:column; gap:15px;">
            <input type="hidden" name="csrf_token"   value="<?= $csrfToken ?>">
            <input type="hidden" name="edit_aushang" value="1">
            <input type="hidden" name="aushang_id"   id="editAushangId">

            <div>
                <label class="rp-label">Überschrift:</label>
                <input type="text" name="titel" id="editTitel" required class="rp-input" style="width:100%; box-sizing:border-box;">
            </div>

            <div>
                <label class="rp-label">Format:</label>
                <select name="format_type" id="editFormatType" class="rp-select"
                        style="width:100%; padding:10px; border:2px solid #c9a961; border-radius:5px;"
                        onchange="updateFormatHint(this.value, 'editFormatHint')">
                    <option value="markdown">✏️ Markdown</option>
                    <option value="html">🎨 HTML</option>
                </select>
            </div>

            <div>
                <label class="rp-label">Text (Rohinhalt):</label>
                <textarea name="inhalt" id="editInhalt" rows="10" required class="rp-textarea"
                          style="width:100%; box-sizing:border-box; resize:vertical; font-family:monospace; font-size:0.9rem;"></textarea>
                <small id="editFormatHint" style="color:#666; font-size:0.82rem; display:block; margin-top:4px;">
                    ✨ **fett** &nbsp;|&nbsp; *kursiv* &nbsp;|&nbsp; - Liste &nbsp;|&nbsp; [Link](URL)
                </small>
            </div>

            <div>
                <label class="rp-label">Signatur:</label>
                <input type="text" name="signatur" id="editSignatur" required class="rp-input" style="width:100%; box-sizing:border-box;">
            </div>

            <button type="submit" class="rp-btn rp-btn--primary" style="padding:15px; font-size:1.1rem; margin-top:5px;">
                💾 Änderungen speichern
            </button>
        </form>
    </div>
</div>

<!-- ========================================================
     MODAL: ZETTEL LESEN (Lightbox)
     ======================================================== -->
<div id="modalLesen" class="rp-modal" onclick="if(event.target===this) this.style.display='none'">
    <span class="rp-modal__close" onclick="document.getElementById('modalLesen').style.display='none'">&times;</span>
    <div style="max-width:650px; margin:80px auto; background:var(--bg-parchment-dark); padding:40px;
                border-radius:2px 8px 3px 5px; box-shadow:0 0 30px black; color:var(--text-ink);">
        <h2 id="leseTitel" style="font-family:var(--font-heading); border-bottom:2px solid rgba(0,0,0,0.2); padding-bottom:10px;"></h2>
        <div id="leseInhalt" style="font-size:1.1rem; line-height:1.7;"></div>
        <p id="leseSignatur" style="text-align:right; font-style:italic; font-weight:bold; margin-top:30px;"></p>
        <div id="leseBildContainer" style="display:none; margin-top:20px; text-align:center;
             border-top:1px dashed rgba(0,0,0,0.2); padding-top:20px;">
            <img id="leseBild" src="" style="max-width:100%; max-height:50vh; border-radius:5px; box-shadow:0 4px 8px rgba(0,0,0,0.3);">
        </div>
    </div>
</div>

<script>
// -------------------------------------------------------
// Zettel-Karte lesen: liest aus data-*-Attributen
// → kein HTML in onclick-Strings mehr, keine Escaping-Bugs
// -------------------------------------------------------
function readZettelFromCard(card) {
    var titel    = card.getAttribute('data-rendered-titel');
    var inhalt   = card.getAttribute('data-rendered-inhalt');
    var signatur = card.getAttribute('data-signatur');
    var bild     = card.getAttribute('data-bild');

    document.getElementById('leseTitel').innerHTML   = titel;
    document.getElementById('leseInhalt').innerHTML  = inhalt;
    document.getElementById('leseSignatur').innerText = '– ' + signatur;

    var bildContainer = document.getElementById('leseBildContainer');
    var bildImg       = document.getElementById('leseBild');
    if (bild && bild.trim() !== '') {
        bildImg.src                 = bild;
        bildContainer.style.display = 'block';
    } else {
        bildImg.src                 = '';
        bildContainer.style.display = 'none';
    }
    document.getElementById('modalLesen').style.display = 'block';
}

// -------------------------------------------------------
// Edit-Modal aus data-*-Attributen befüllen
// → Textarea bekommt den RAW-Inhalt (nicht gerendert)
// -------------------------------------------------------
function openEditFromCard(card) {
    document.getElementById('editAushangId').value  = card.getAttribute('data-id');
    document.getElementById('editTitel').value      = card.getAttribute('data-raw-titel');
    document.getElementById('editInhalt').value     = card.getAttribute('data-raw-inhalt');
    document.getElementById('editSignatur').value   = card.getAttribute('data-signatur');
    var fmt = card.getAttribute('data-fmt') || 'markdown';
    document.getElementById('editFormatType').value = fmt;
    updateFormatHint(fmt, 'editFormatHint');
    document.getElementById('modalEdit').style.display = 'block';
}

// -------------------------------------------------------
// Format-Hinweis dynamisch umschalten
// -------------------------------------------------------
function updateFormatHint(val, targetId) {
    var hints = {
        'markdown': '✨ <strong>**fett**</strong> &nbsp;|&nbsp; <em>*kursiv*</em> &nbsp;|&nbsp; - Liste &nbsp;|&nbsp; [Link](URL)',
        'html':     '🎨 &lt;strong&gt;fett&lt;/strong&gt; &nbsp;|&nbsp; &lt;em&gt;kursiv&lt;/em&gt; &nbsp;|&nbsp; &lt;span style="color:gold"&gt;farbig&lt;/span&gt;'
    };
    var el = document.getElementById(targetId);
    if (el) el.innerHTML = hints[val] || hints['markdown'];
}

// Format-Badge im Create-Modal aktualisieren
function highlightFormat(selectEl) {
    var badge = document.getElementById('createFormatBadge');
    if (!badge) return;
    var label = selectEl.value === 'html' ? '🎨 <strong>HTML</strong>' : '✏️ <strong>Markdown</strong>';
    badge.innerHTML = 'Gewählt: ' + label;
    badge.style.background = selectEl.value === 'html'
        ? 'rgba(0,112,221,0.2)'
        : 'rgba(212,175,55,0.2)';
    badge.style.borderColor = selectEl.value === 'html' ? '#0070dd' : 'var(--accent-gold)';
}

// -------------------------------------------------------
// Parallax
// -------------------------------------------------------
window.addEventListener('scroll', function () {
    var bg = document.getElementById('parallaxBoardBg');
    if (bg) bg.style.transform = 'translateY(' + (window.pageYOffset * -0.3) + 'px)';
});

// -------------------------------------------------------
// ESC schließt alle Modals
// -------------------------------------------------------
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        ['modalAushang', 'modalEdit', 'modalLesen'].forEach(function (id) {
            document.getElementById(id).style.display = 'none';
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>
