<?php
$includeMiliz = true;
$view = isset($_GET['cat']) ? $_GET['cat'] : 'room';
$pageTitle = 'Die Miliz - Dämmerhafen';
$bodyClass = ($view === 'room') ? 'rp-view-room' : 'rp-view-immersive';

// Vor header.php: Spezifische Aktionen
require_once 'functions.php';
require_once 'functions_miliz.php';

// Lesezugriff für alle - nur Aktionen sind geschützt

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_entry']) && isset($_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'write')) {
        $category = $_POST['entry_category'] ?? '';
        $title = trim($_POST['entry_title'] ?? '');
        $content = trim($_POST['entry_content'] ?? '');
        $author = trim($_POST['entry_author'] ?? 'Die Miliz');
        $priority = intval($_POST['entry_priority'] ?? 0);
        $wantedData = [];

        if ($category === 'gesucht') {
            $title = trim($_POST['wanted_name'] ?? $title);
            $content = trim($_POST['wanted_crime'] ?? $content);
            $priority = max($priority, 2);
            $wantedData = [
                'gold' => max(0, intval($_POST['wanted_gold'] ?? 0)),
                'silver' => max(0, intval($_POST['wanted_silver'] ?? 0)),
                'copper' => max(0, intval($_POST['wanted_copper'] ?? 0)),
                'crime_summary' => $content,
                'status' => $_POST['wanted_status'] ?? 'aktiv'
            ];
        }


        if ($category === 'steckbriefe') {
            $wantedData['rank_text'] = trim($_POST['entry_rank'] ?? '');
        }
        
        $filePath = null;
        
        if (isset($_FILES['entry_file']) && $_FILES['entry_file']['size'] > 0) {
            $uploadError = $_FILES['entry_file']['error'];
            
            if ($uploadError === UPLOAD_ERR_OK) {
                $uploadResult = handleMilizFileUpload($_FILES['entry_file'], $category);
                if ($uploadResult['success']) {
                    $filePath = $uploadResult['path'];
                } else {
                    $message = ['type' => 'error', 'text' => '⚠️ Upload fehlgeschlagen: ' . $uploadResult['message']];
                }
            } else {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'Datei zu groß (server upload_max_filesize)',
                    UPLOAD_ERR_FORM_SIZE => 'Datei zu groß (Formular-Limit)',
                    UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen',
                    UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt',
                    UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Upload-Ordner fehlt',
                    UPLOAD_ERR_CANT_WRITE => 'Schreibfehler auf Festplatte',
                    UPLOAD_ERR_EXTENSION => 'PHP-Extension hat Upload gestoppt'
                ];
                $errorMsg = $uploadErrors[$uploadError] ?? 'Unbekannter Upload-Fehler (#' . $uploadError . ')';
                $message = ['type' => 'error', 'text' => '⚠️ ' . $errorMsg];
            }
        }
        
        if (!isset($message) && $title && $content && array_key_exists($category, MILIZ_CATEGORIES)) {
            $message = createMilizEntry($category, $title, $content, $author, $filePath, $priority, $wantedData);
        } elseif (!isset($message)) {
            $message = ['type' => 'error', 'text' => '⚠️ Titel und Inhalt sind Pflichtfelder!'];
        }
    }
}

if (isset($_POST['delete_entry']) && isset($_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'write')) {
        $entryId = intval($_POST['delete_entry']);
        $message = deleteMilizEntry($entryId);
    }
}

// Status ändern
if (isset($_POST['set_status'], $_POST['status_entry_id'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'write')) {
        $message = updateMilizEntryStatus(intval($_POST['status_entry_id']), $_POST['set_status']);
    }
}

// Wanted-Belohnung ändern
if (isset($_POST['wanted_update_bounty'], $_POST['wanted_entry_id'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'write')) {
        $message = updateMilizWantedBounty(
            intval($_POST['wanted_entry_id']),
            $_POST['wanted_gold'] ?? 0,
            $_POST['wanted_silver'] ?? 0,
            $_POST['wanted_copper'] ?? 0
        );
    }
}

// Briefkasten: Anonymen Hinweis einwerfen (auch ohne Login)
if (isset($_POST['briefkasten_submit'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $bkResult = createBriefkastenNachricht(
            $_POST['bk_betreff'] ?? '',
            $_POST['bk_nachricht'] ?? '',
            $_POST['bk_absender'] ?? 'Anonym'
        );
        $message = ['type' => $bkResult['success'] ? 'success' : 'error', 'text' => $bkResult['message']];
    }
}

// Briefkasten: Als gelesen markieren
if (isset($_POST['bk_gelesen'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'read')) {
        markBriefkastenGelesen(intval($_POST['bk_gelesen']));
    }
}

// Briefkasten: Löschen
if (isset($_POST['bk_loeschen'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && isMeister()) {
        $result = deleteBriefkastenNachricht(intval($_POST['bk_loeschen']));
        $message = ['type' => $result['success'] ? 'success' : 'error', 'text' => $result['message']];
    }
}

// Waffenkammer-Inventar: Erstellen
if (isset($_POST['wk_create'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'write')) {
        $message = createWaffenkammerItem(
            $_POST['wk_name'] ?? '',
            $_POST['wk_beschreibung'] ?? '',
            $_POST['wk_bestand'] ?? 1,
            $_POST['wk_zustand'] ?? 'gut'
        );
    }
}

// Waffenkammer-Inventar: Aktualisieren
if (isset($_POST['wk_update'], $_POST['wk_item_id'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'write')) {
        $updateData = [];
        foreach (['name', 'beschreibung', 'bestand', 'zustand', 'ausgegeben_an'] as $f) {
            $key = 'wk_' . $f;
            if (isset($_POST[$key])) {
                $updateData[$f] = $_POST[$key];
            }
        }
        $message = updateWaffenkammerItem(intval($_POST['wk_item_id']), $updateData);
    }
}

// Waffenkammer-Inventar: Löschen
if (isset($_POST['wk_delete'], $_POST['csrf_token'])) {
    if (verifyCSRFToken($_POST['csrf_token']) && hasPermission('miliz', 'write')) {
        $message = deleteWaffenkammerItem(intval($_POST['wk_delete']));
    }
}

$entries = [];
$stats = getMilizStats();

// Status-Filter aus URL
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;

if ($view !== 'room' && array_key_exists($view, MILIZ_CATEGORIES)) {
    $entries = getMilizEntries($view, !hasPermission('miliz', 'write'), $statusFilter);
}

require_once 'header.php';
?>

<?php if ($view === 'room'): ?>
    <img src="miliz.jpg" alt="Die Miliz" class="rp-bg-fullscreen">

    <div class="miliz-bottom-nav">
        <?php foreach (MILIZ_CATEGORIES as $catKey => $catData):
            $count = $stats[$catKey] ?? 0;
            $icons = [
                'befehle' => '📜',
                'steckbriefe' => '🎖️',
                'gesucht' => '⚔️',
                'protokolle' => '📋',
                'waffenkammer' => '🗡️',
                'intern' => '🔒'
            ];
        ?>
            <a href="?cat=<?php echo $catKey; ?>" class="miliz-nav-btn">
                <span class="miliz-nav-icon"><?php echo $icons[$catKey]; ?></span>
                <span class="miliz-nav-label">
                    <?php echo str_replace(['📜 ', '🎖️ ', '⚔️ ', '📋 ', '🗡️ ', '🔒 '], '', $catData['label']); ?>
                </span>
                <?php if ($count > 0): ?>
                    <span class="miliz-nav-counter" style="background: <?php echo $catData['color']; ?>;">
                        <?php echo $count; ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <!-- IMMERSIVE MODE: Scrollender Hintergrund + Transparente Container -->
    <div id="milizParallaxBg" class="rp-bg-parallax" data-category="<?php echo $view; ?>"></div>
    
    <div class="rp-container rp-container--immersive">
        <div class="rp-card rp-card--header">
            <h1><?php echo MILIZ_CATEGORIES[$view]['label'] ?? 'Unbekannte Kategorie'; ?></h1>
        </div>

        <!-- BRIEFKASTEN: Öffentliches Formular (auch ohne Login) -->
        <?php if ($view === 'intern'): ?>
            <div class="briefkasten-form" style="margin-bottom:30px;">
                <h3>📬 Bürger-Briefkasten</h3>
                <div class="briefkasten-hinweis">
                    <strong>Anonym:</strong> Hier können Bürger der Miliz vertrauliche Hinweise zukommen lassen. Kein Login erforderlich.
                </div>
                <form method="post" style="display:flex; flex-direction:column; gap:12px;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="briefkasten_submit" value="1">
                    <div>
                        <label class="rp-label" style="color:var(--text-ink);">Betreff *</label>
                        <input type="text" name="bk_betreff" class="rp-input" required maxlength="200" style="width:100%; box-sizing:border-box;">
                    </div>
                    <div>
                        <label class="rp-label" style="color:var(--text-ink);">Nachricht *</label>
                        <textarea name="bk_nachricht" class="rp-textarea" required maxlength="2000" rows="4" style="width:100%; box-sizing:border-box;"></textarea>
                    </div>
                    <div>
                        <label class="rp-label" style="color:var(--text-ink);">Absender (optional)</label>
                        <input type="text" name="bk_absender" class="rp-input" value="Anonym" maxlength="100" style="width:100%; box-sizing:border-box;">
                    </div>
                    <button type="submit" class="rp-btn rp-btn--primary">📬 Hinweis einwerfen</button>
                </form>
            </div>

            <!-- Briefkasten-Eingang (nur für Miliz/Meister) -->
            <?php if (hasPermission('miliz', 'read')): ?>
                <?php $bkNachrichten = getBriefkastenNachrichten(); $bkUnread = getBriefkastenUnreadCount(); ?>
                <?php if (!empty($bkNachrichten)): ?>
                    <div class="rp-card rp-card--header" style="margin-bottom:20px;">
                        <h2 style="margin:0; font-size:1.5rem; color:var(--accent-gold);">
                            📬 Eingang <?php if ($bkUnread > 0): ?><span class="briefkasten-badge briefkasten-badge--ungelesen"><?= $bkUnread ?> ungelesen</span><?php endif; ?>
                        </h2>
                    </div>
                    <div class="rp-grid rp-grid--entries" style="margin-bottom:40px;">
                        <?php foreach ($bkNachrichten as $bk): ?>
                            <div class="rp-card rp-card--entry rp-card--transparent <?= $bk['gelesen'] ? '' : 'priority-medium' ?>">
                                <div class="rp-card__header">
                                    <h3 class="rp-card__title"><?= htmlspecialchars($bk['betreff']) ?></h3>
                                    <?php if (!$bk['gelesen']): ?>
                                        <span class="briefkasten-badge briefkasten-badge--ungelesen">NEU</span>
                                    <?php endif; ?>
                                </div>
                                <div class="rp-card__meta">
                                    <span class="rp-meta-author">📝 <?= htmlspecialchars($bk['absender']) ?></span>
                                    <span class="rp-meta-date">📅 <?= formatDate($bk['erstellt_am']) ?></span>
                                    <?php if ($bk['gelesen'] && $bk['gelesen_von']): ?>
                                        <span style="color:rgba(244,228,188,0.5);">✓ Gelesen von <?= htmlspecialchars($bk['gelesen_von']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="rp-card__content"><?= parseRichText($bk['nachricht']) ?></div>
                                <div class="rp-card__footer">
                                    <?php if (!$bk['gelesen']): ?>
                                        <form method="post" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="bk_gelesen" value="<?= $bk['id'] ?>">
                                            <button type="submit" class="rp-btn rp-btn--primary rp-btn--small">✓ Als gelesen markieren</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (isMeister()): ?>
                                        <form method="post" style="margin:0;" onsubmit="return confirm('Nachricht löschen?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="bk_loeschen" value="<?= $bk['id'] ?>">
                                            <button type="submit" class="rp-btn rp-btn--danger rp-btn--small">🔥</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('miliz', 'write')): ?>
            <div class="rp-controls" style="margin-bottom: 30px;">
                <form method="post" enctype="multipart/form-data" style="width: 100%;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="entry_category" value="<?php echo $view; ?>">
                    <input type="hidden" name="create_entry" value="1">

                    <?php if ($view === 'gesucht'): ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div>
                                <label class="rp-label">👤 Name des Gesuchten *</label>
                                <input type="text" name="wanted_name" class="rp-input" required maxlength="200" style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="rp-label">✍️ Aussteller</label>
                                <input type="text" name="entry_author" class="rp-input" value="Die Miliz" style="width:100%; box-sizing:border-box;">
                            </div>
                        </div>

                        <div style="margin-bottom:15px;">
                            <label class="rp-label">⚖️ Verbrechen *</label>
                            <textarea name="wanted_crime" class="rp-textarea" required rows="5" style="width:100%; box-sizing:border-box;" placeholder="Beschreibe die Tat, letzte Sichtung und Hinweise..."></textarea>
                        </div>

                        <div style="margin-bottom:15px;">
                            <label class="rp-label">📌 Start-Status</label>
                            <select name="wanted_status" class="rp-select" style="width:100%;">
                                <?php foreach (MILIZ_STATUS_VALUES as $sKey => $sData): ?>
                                    <option value="<?= $sKey ?>" <?= $sKey === 'fluechtig' ? 'selected' : '' ?>><?= $sData['icon'] ?> <?= $sData['label'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:15px;">
                            <div>
                                <label class="rp-label">🟡 Gold</label>
                                <input type="number" name="wanted_gold" min="0" value="0" class="rp-input" style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="rp-label">⚪ Silber</label>
                                <input type="number" name="wanted_silver" min="0" max="99" value="0" class="rp-input" style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="rp-label">🟤 Kupfer</label>
                                <input type="number" name="wanted_copper" min="0" max="99" value="0" class="rp-input" style="width:100%; box-sizing:border-box;">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div>
                                <label class="rp-label">📎 Steckbriefbild (optional)</label>
                                <div class="rp-file-wrapper">
                                    <input type="file" name="entry_file" id="miliz-file-input" class="rp-file-input" accept="image/*">
                                    <label for="miliz-file-input" class="rp-file-button">📂 Datei auswählen</label>
                                    <span id="file-name-display" class="rp-file-display">Keine Datei gewählt</span>
                                </div>
                            </div>
                            <div>
                                <label class="rp-label">⭐ Priorität</label>
                                <select name="entry_priority" class="rp-select" style="width:100%;">
                                    <option value="2" selected>Sehr wichtig</option>
                                    <option value="3">Dringend</option>
                                    <option value="1">Wichtig</option>
                                    <option value="0">Normal</option>
                                </select>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="rp-label">📜 Titel *</label>
                                <input type="text" name="entry_title" class="rp-input" required style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div>
                                <label class="rp-label">✍️ Autor</label>
                                <input type="text" name="entry_author" class="rp-input" value="Die Miliz" style="width: 100%; box-sizing: border-box;">
                            </div>
                        </div>

                        <div style="margin-bottom: 15px;">
                            <label class="rp-label">📝 Inhalt *</label>
                            <textarea name="entry_content" class="rp-textarea" required rows="6" style="width: 100%; box-sizing: border-box;"></textarea>
                            <small style="color: #666; font-size: 0.85rem;">Formatierung: **fett** *kursiv* - Listen [Link](URL)</small>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="rp-label">📎 Anhang (optional)</label>
                                <div class="rp-file-wrapper">
                                    <input type="file" name="entry_file" id="miliz-file-input" class="rp-file-input">
                                    <label for="miliz-file-input" class="rp-file-button">📂 Datei auswählen</label>
                                    <span id="file-name-display" class="rp-file-display">Keine Datei gewählt</span>
                                </div>
                            </div>
                            <div>
                                <?php if ($view === 'steckbriefe'): ?>
                                    <label class="rp-label">🎖️ Rang</label>
                                    <input type="text" name="entry_rank" class="rp-input" placeholder="z. B. Hauptmann" maxlength="120" style="width:100%; box-sizing:border-box;">
                                    <input type="hidden" name="entry_priority" value="0">
                                <?php else: ?>
                                    <label class="rp-label">⭐ Priorität</label>
                                    <select name="entry_priority" class="rp-select" style="width: 100%;">
                                        <option value="0">Normal</option>
                                        <option value="1">Wichtig</option>
                                        <option value="2">Sehr wichtig</option>
                                        <option value="3">Dringend</option>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="rp-btn rp-btn--primary">⚔️ Eintrag erstellen</button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($view === 'waffenkammer'): ?>
            <!-- ECHTES WAFFENKAMMER-INVENTAR -->
            <?php $wkItems = getWaffenkammerItems(); ?>

            <?php if (hasPermission('miliz', 'write')): ?>
                <div class="rp-controls" style="margin-bottom:20px;">
                    <form method="post" style="width:100%;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="wk_create" value="1">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                            <div>
                                <label class="rp-label">🗡️ Name *</label>
                                <input type="text" name="wk_name" class="rp-input" required style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="rp-label">🔢 Bestand</label>
                                <input type="number" name="wk_bestand" class="rp-input" value="1" min="0" style="width:100%; box-sizing:border-box;">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:12px;">
                            <div>
                                <label class="rp-label">📝 Beschreibung</label>
                                <input type="text" name="wk_beschreibung" class="rp-input" style="width:100%; box-sizing:border-box;">
                            </div>
                            <div>
                                <label class="rp-label">🔧 Zustand</label>
                                <select name="wk_zustand" class="rp-select" style="width:100%;">
                                    <?php foreach (WAFFENKAMMER_ZUSTAND as $zKey => $zData): ?>
                                        <option value="<?= $zKey ?>"><?= $zData['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="rp-btn rp-btn--primary">🗡️ Hinzufügen</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="rp-table-wrapper">
                <table class="rp-table rp-table--waffenkammer">
                    <thead>
                        <tr>
                            <th>Gegenstand</th>
                            <th>Beschreibung</th>
                            <th>Bestand</th>
                            <th>Zustand</th>
                            <th>Ausgegeben an</th>
                            <?php if (hasPermission('miliz', 'write')): ?><th>Aktion</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($wkItems)): ?>
                            <tr><td colspan="6" style="text-align:center; color:#888;">Die Waffenkammer ist leer.</td></tr>
                        <?php else: ?>
                            <?php foreach ($wkItems as $item): ?>
                                <tr>
                                    <td class="rp-table__name" data-label="Gegenstand"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="rp-table__desc" data-label="Beschreibung"><?= htmlspecialchars($item['beschreibung']) ?></td>
                                    <td data-label="Bestand" style="text-align:center; font-weight:bold;"><?= (int)$item['bestand'] ?></td>
                                    <td class="rp-table__status" data-label="Zustand">
                                        <?php
                                        $zInfo = WAFFENKAMMER_ZUSTAND[$item['zustand']] ?? WAFFENKAMMER_ZUSTAND['gut'];
                                        ?>
                                        <span class="inventar-zustand <?= $zInfo['class'] ?>"><?= $zInfo['label'] ?></span>
                                    </td>
                                    <td data-label="Ausgegeben an"><span class="waffenkammer-issued-name"><?= htmlspecialchars($item['ausgegeben_an'] ?? '—') ?></span></td>
                                    <?php if (hasPermission('miliz', 'write')): ?>
                                        <td data-label="Aktionen" style="white-space:nowrap;">
                                            <div class="waffenkammer-actions">
                                                <form method="post" class="waffenkammer-action-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="wk_update" value="1">
                                                    <input type="hidden" name="wk_item_id" value="<?= $item['id'] ?>">
                                                    <select name="wk_zustand" style="font-size:0.8rem; padding:3px;">
                                                        <?php foreach (WAFFENKAMMER_ZUSTAND as $zKey => $zData): ?>
                                                            <option value="<?= $zKey ?>" <?= $item['zustand'] === $zKey ? 'selected' : '' ?>><?= $zData['label'] ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="rp-btn rp-btn--primary rp-btn--small" style="padding:3px 8px;">Zustand</button>
                                                </form>
                                                <form method="post" class="waffenkammer-action-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="wk_update" value="1">
                                                    <input type="hidden" name="wk_item_id" value="<?= $item['id'] ?>">
                                                    <input type="text" name="wk_ausgegeben_an" placeholder="Ausgegeben an..." value="<?= htmlspecialchars($item['ausgegeben_an'] ?? '') ?>" style="width:140px; font-size:0.8rem; padding:3px;">
                                                    <button type="submit" class="rp-btn rp-btn--primary rp-btn--small" style="padding:3px 8px;">Person</button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Entfernen?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="wk_delete" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="rp-btn rp-btn--danger rp-btn--mini">🔥</button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Alte Einträge aus miliz_entries (Abwärtskompatibilität) -->
            <?php if (!empty($entries)): ?>
                <h3 style="color:var(--bg-parchment); margin-top:30px;">📜 Ältere Einträge</h3>
                <div class="rp-grid rp-grid--entries">
                    <?php foreach ($entries as $entry): ?>
                        <div class="rp-card rp-card--entry rp-card--transparent">
                            <div class="rp-card__header">
                                <h3 class="rp-card__title"><?= parseRichTextSimple($entry['title']) ?></h3>
                            </div>
                            <div class="rp-card__content"><?= parseRichText($entry['crime_summary'] ?? $entry['content']) ?></div>
                            <?php if (hasPermission('miliz', 'write')): ?>
                                <div class="rp-card__footer">
                                    <form method="post" onsubmit="return confirm('Löschen?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="delete_entry" value="<?= $entry['id'] ?>">
                                        <button type="submit" class="rp-btn rp-btn--danger rp-btn--small">🔥</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($view === 'gesucht'): ?>
            <!-- GESUCHTE PERSONEN: Status-Filter + Wanted-Poster-Stil -->
            <div class="miliz-filter">
                <a href="?cat=gesucht" class="miliz-filter__btn <?= !$statusFilter ? 'miliz-filter__btn--active' : '' ?>">Alle</a>
                <?php foreach (MILIZ_STATUS_VALUES as $sKey => $sData): ?>
                    <a href="?cat=gesucht&status=<?= $sKey ?>" class="miliz-filter__btn <?= $statusFilter === $sKey ? 'miliz-filter__btn--active' : '' ?>">
                        <?= $sData['icon'] ?> <?= $sData['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="rp-grid rp-grid--entries">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $entryStatus = $entry['status'] ?? 'aktiv';
                    $statusInfo = MILIZ_STATUS_VALUES[$entryStatus] ?? MILIZ_STATUS_VALUES['aktiv'];
                    ?>
                    <div class="wanted-poster">
                        <div class="wanted-poster__header">
                            <h2>GESUCHT</h2>
                            <span class="wanted-subtitle"><?= $statusInfo['icon'] ?> <?= $statusInfo['label'] ?></span>
                        </div>

                        <?php if ($entry['file_path']):
                            $fileName = basename($entry['file_path']);
                            $webPath = 'miliz/' . $view . '/' . rawurlencode($fileName);
                            $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileName);
                        ?>
                            <?php if ($isImage): ?>
                                <div class="wanted-poster__foto">
                                    <img src="<?= $webPath ?>" alt="Bild" onclick="openLightbox('<?= $webPath ?>', 'image')" style="cursor:pointer;">
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="wanted-poster__foto">
                                <div class="wanted-no-foto">❓</div>
                            </div>
                        <?php endif; ?>

                        <div class="wanted-poster__name"><?= parseRichTextSimple($entry['title']) ?></div>

                        <div class="wanted-poster__verbrechen">
                            <?= parseRichText($entry['crime_summary'] ?? $entry['content']) ?>
                        </div>

                        <div class="wanted-poster__belohnung">
                            <span class="belohnung-coins"><span class="coin coin--gold">🟡 <?= (int)($entry['bounty_gold'] ?? 0) ?></span><span class="coin coin--silver">⚪ <?= (int)($entry['bounty_silver'] ?? 0) ?></span><span class="coin coin--copper">🟤 <?= (int)($entry['bounty_copper'] ?? 0) ?></span></span>
                        </div>

                        <?php if (hasPermission('miliz', 'write')): ?>
                            <div style="padding:10px 20px; display:flex; gap:6px; flex-wrap:wrap; border-top:1px solid rgba(139,90,43,0.2);">
                                <!-- Status ändern -->
                                <form method="post" style="margin:0; display:inline-flex; gap:4px; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="status_entry_id" value="<?= $entry['id'] ?>">
                                    <select name="set_status" onchange="this.form.submit()" style="font-size:0.85rem; padding:4px 8px; border:1px solid #8b5a2b; border-radius:3px;">
                                        <?php foreach (MILIZ_STATUS_VALUES as $sKey => $sData): ?>
                                            <option value="<?= $sKey ?>" <?= $entryStatus === $sKey ? 'selected' : '' ?>><?= $sData['icon'] ?> <?= $sData['label'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <form method="post" style="margin:0; display:inline-flex; gap:4px; align-items:center;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="wanted_update_bounty" value="1">
                                    <input type="hidden" name="wanted_entry_id" value="<?= $entry['id'] ?>">
                                    <input type="number" name="wanted_gold" min="0" value="<?= (int)($entry['bounty_gold'] ?? 0) ?>" title="Gold" style="width:58px; font-size:0.8rem; padding:3px;">
                                    <input type="number" name="wanted_silver" min="0" max="99" value="<?= (int)($entry['bounty_silver'] ?? 0) ?>" title="Silber" style="width:58px; font-size:0.8rem; padding:3px;">
                                    <input type="number" name="wanted_copper" min="0" max="99" value="<?= (int)($entry['bounty_copper'] ?? 0) ?>" title="Kupfer" style="width:58px; font-size:0.8rem; padding:3px;">
                                    <button type="submit" class="rp-btn rp-btn--primary rp-btn--small">💰</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Steckbrief vernichten?');" style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="delete_entry" value="<?= $entry['id'] ?>">
                                    <button type="submit" class="rp-btn rp-btn--danger rp-btn--small">🔥</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($view === 'steckbriefe'): ?>
            <!-- STECKBRIEFE: Status-Filter -->
            <div class="miliz-filter">
                <a href="?cat=steckbriefe" class="miliz-filter__btn <?= !$statusFilter ? 'miliz-filter__btn--active' : '' ?>">Alle</a>
                <?php foreach (MILIZ_STATUS_VALUES as $sKey => $sData): ?>
                    <a href="?cat=steckbriefe&status=<?= $sKey ?>" class="miliz-filter__btn <?= $statusFilter === $sKey ? 'miliz-filter__btn--active' : '' ?>">
                        <?= $sData['icon'] ?> <?= $sData['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="rp-grid rp-grid--entries">
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $entryStatus = $entry['status'] ?? 'aktiv';
                    $statusInfo = MILIZ_STATUS_VALUES[$entryStatus] ?? MILIZ_STATUS_VALUES['aktiv'];
                    $rankText = trim((string)($entry['rank_text'] ?? ''));
                    ?>

                    <div class="rp-card rp-card--entry rp-card--transparent">
                        <div class="rp-card__header">
                            <h3 class="rp-card__title"><?= parseRichTextSimple($entry['title']) ?></h3>
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                                <span class="miliz-status-badge miliz-status-badge--<?= $entryStatus ?>"><?= $statusInfo['icon'] ?> <?= $statusInfo['label'] ?></span>
                                <span class="miliz-rang-badge">🎖️ <?= htmlspecialchars($rankText !== '' ? $rankText : 'Milizionär') ?></span>
                            </div>
                        </div>

                        <div class="rp-card__meta">
                            <span class="rp-meta-author">✍️ <?= htmlspecialchars($entry['author']) ?></span>
                            <span class="rp-meta-date">📅 <?= formatDate($entry['created_at']) ?></span>
                        </div>

                        <div class="rp-card__content"><?= parseRichText($entry['crime_summary'] ?? $entry['content']) ?></div>

                        <?php if ($entry['file_path'] || hasPermission('miliz', 'write')): ?>
                            <div class="rp-card__footer">
                                <?php if ($entry['file_path']):
                                    $fileName = basename($entry['file_path']);
                                    $webPath = 'miliz/' . $view . '/' . rawurlencode($fileName);
                                    $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileName);
                                ?>
                                    <div>
                                        <?php if ($isImage): ?>
                                            <a href="javascript:void(0);" onclick="openLightbox('<?= $webPath ?>', 'image')">
                                                <img src="<?= $webPath ?>" alt="Anhang" style="max-width:100%; border-radius:4px; cursor:pointer;">
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= $webPath ?>" target="_blank" class="rp-btn rp-btn--primary rp-btn--small">📎 <?= htmlspecialchars($fileName) ?></a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (hasPermission('miliz', 'write')): ?>
                                    <div style="display:flex; gap:6px; align-items:center;">
                                        <form method="post" style="margin:0; display:inline-flex; gap:4px; align-items:center;">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="status_entry_id" value="<?= $entry['id'] ?>">
                                            <select name="set_status" onchange="this.form.submit()" style="font-size:0.8rem; padding:3px 6px; background:rgba(0,0,0,0.3); color:#f4e4bc; border:1px solid rgba(212,175,55,0.3); border-radius:3px;">
                                                <?php foreach (MILIZ_STATUS_VALUES as $sKey => $sData): ?>
                                                    <option value="<?= $sKey ?>" <?= $entryStatus === $sKey ? 'selected' : '' ?>><?= $sData['icon'] ?> <?= $sData['label'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Eintrag löschen?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="delete_entry" value="<?= $entry['id'] ?>">
                                            <button type="submit" class="rp-btn rp-btn--danger rp-btn--small">🔥</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="rp-grid rp-grid--entries">
                <?php foreach ($entries as $entry): ?>
                    <?php 
                    $priorityClass = '';
                    $priorityLabel = '';
                    if ($entry['priority'] >= 3) {
                        $priorityClass = 'priority-urgent';
                        $priorityLabel = '🔥 DRINGEND';
                    } elseif ($entry['priority'] == 2) {
                        $priorityClass = 'priority-high';
                        $priorityLabel = '⚠️ Sehr wichtig';
                    } elseif ($entry['priority'] == 1) {
                        $priorityClass = 'priority-medium';
                        $priorityLabel = '⭐ Wichtig';
                    }
                    ?>
                    
                    <div class="rp-card rp-card--entry rp-card--transparent <?php echo $priorityClass; ?>">
                        <div class="rp-card__header">
                            <h3 class="rp-card__title"><?php echo parseRichTextSimple($entry['title']); ?></h3>
                            <?php if ($priorityLabel): ?>
                                <span class="rp-priority-badge"><?php echo $priorityLabel; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="rp-card__meta">
                            <span class="rp-meta-author">✍️ <?php echo htmlspecialchars($entry['author']); ?></span>
                            <span class="rp-meta-date">📅 <?php echo formatDate($entry['created_at']); ?></span>
                        </div>
                        
                        <div class="rp-card__content">
                            <?php echo parseRichText($entry['content']); ?>
                        </div>
                        
                        <?php if ($entry['file_path'] || hasPermission('miliz', 'write')): ?>
                            <div class="rp-card__footer">
                                <?php if ($entry['file_path']): ?>
                                    <?php 
                                    $fileName = basename($entry['file_path']);
                                    $webPath = 'miliz/' . $view . '/' . rawurlencode($fileName);
                                    $isImage = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $fileName);
                                    ?>
                                    <div>
                                        <?php if ($isImage): ?>
                                            <a href="javascript:void(0);" onclick="openLightbox('<?php echo $webPath; ?>', 'image')">
                                                <img src="<?php echo $webPath; ?>" alt="Anhang" style="max-width: 100%; border-radius: 4px; cursor: pointer;" onerror="this.parentElement.innerHTML='❌ Fehler beim Laden des Bildes';">
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo $webPath; ?>" target="_blank" class="rp-btn rp-btn--primary rp-btn--small">
                                                📎 <?php echo htmlspecialchars($fileName); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (hasPermission('miliz', 'write')): ?>
                                    <form method="post" onsubmit="return confirm('Eintrag wirklich löschen?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="delete_entry" value="<?php echo $entry['id']; ?>">
                                        <button type="submit" class="rp-btn rp-btn--danger rp-btn--small">🔥 Löschen</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- BOTTOM NAVIGATION IN KATEGORIEN-ANSICHT -->
    <div class="miliz-bottom-nav">
        <a href="miliz.php" class="miliz-nav-btn miliz-nav-back">
            <span class="miliz-nav-icon">🚪</span>
            <span class="miliz-nav-label">Zurück zum Raum</span>
        </a>
        
        <?php 
        $icons = [
            'befehle' => '📜',
            'steckbriefe' => '🎖️',
            'gesucht' => '⚔️',
            'protokolle' => '📋',
            'waffenkammer' => '🗡️',
            'intern' => '🔒'
        ];
        
        foreach (MILIZ_CATEGORIES as $catKey => $catData):
            // Aktuelle Kategorie nicht anzeigen
            if ($catKey === $view) continue;
            
            $count = $stats[$catKey] ?? 0;
        ?>
            <a href="?cat=<?php echo $catKey; ?>" class="miliz-nav-btn">
                <span class="miliz-nav-icon"><?php echo $icons[$catKey]; ?></span>
                <span class="miliz-nav-label">
                    <?php echo str_replace(['📜 ', '🎖️ ', '⚔️ ', '📋 ', '🗡️ ', '🔒 '], '', $catData['label']); ?>
                </span>
                <?php if ($count > 0): ?>
                    <span class="miliz-nav-counter" style="background: <?php echo $catData['color']; ?>;">
                        <?php echo $count; ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// Parallax-Effekt für Miliz-Hintergrund
window.addEventListener('scroll', function() {
    var bg = document.getElementById('milizParallaxBg');
    if (bg) {
        var scrollPosition = window.pageYOffset;
        // Faktor -0.3: Hintergrund scrollt langsamer als Content
        bg.style.transform = 'translateY(' + (scrollPosition * -0.3) + 'px)';
    }
});

// File Input Display Update
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('miliz-file-input');
    var fileDisplay = document.getElementById('file-name-display');
    
    if (fileInput && fileDisplay) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileDisplay.textContent = this.files[0].name;
            } else {
                fileDisplay.textContent = 'Keine Datei gewählt';
            }
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>
