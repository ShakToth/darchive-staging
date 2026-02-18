<?php
$pageTitle = 'Administration - Dämmerhafen';
$bodyClass = 'rp-view-immersive';

require_once 'functions.php';

// Nur Meister dürfen hier rein
if (!isMeister()) {
    header('HTTP/1.0 403 Forbidden');
    die('⛔ Zugriff verweigert. Nur für Meister.');
}

$message = null;

// POST-Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRFToken($_POST['csrf_token'])) {

    // Neuen Benutzer erstellen
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'buerger';

        if ($username && $password) {
            if (createUser($username, $password, $role)) {
                $message = ['type' => 'success', 'text' => "✅ Benutzer '$username' erfolgreich erstellt!"];
            } else {
                $message = ['type' => 'error', 'text' => '❌ Fehler beim Erstellen (Username bereits vergeben oder ungültige Rolle).'];
            }
        } else {
            $message = ['type' => 'error', 'text' => '❌ Benutzername und Passwort sind Pflichtfelder!'];
        }
    }

    // Benutzer löschen
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);
        if (deleteUser($userId)) {
            $message = ['type' => 'success', 'text' => '🔥 Benutzer gelöscht!'];
        } else {
            $message = ['type' => 'error', 'text' => '❌ Fehler beim Löschen.'];
        }
    }

    // Rolle ändern
    if (isset($_POST['update_role'])) {
        $userId = intval($_POST['user_id']);
        $newRole = $_POST['new_role'] ?? '';
        if (updateUserRole($userId, $newRole)) {
            $message = ['type' => 'success', 'text' => '✅ Rolle erfolgreich geändert!'];
        } else {
            $message = ['type' => 'error', 'text' => '❌ Fehler beim Ändern der Rolle.'];
        }
    }

    // Passwort ändern
    if (isset($_POST['change_password'])) {
        $userId = intval($_POST['user_id']);
        $newPassword = $_POST['new_password'] ?? '';

        if ($newPassword) {
            if (changePassword($userId, $newPassword)) {
                $message = ['type' => 'success', 'text' => '✅ Passwort erfolgreich geändert!'];
            } else {
                $message = ['type' => 'error', 'text' => '❌ Fehler beim Ändern des Passworts.'];
            }
        } else {
            $message = ['type' => 'error', 'text' => '❌ Passwort darf nicht leer sein!'];
        }
    }

    // === ROLLENVERWALTUNG ===

    // Neue Rolle erstellen
    if (isset($_POST['create_role'])) {
        $roleName = strtolower(trim($_POST['role_name'] ?? ''));
        $displayName = trim($_POST['role_display_name'] ?? '');
        $icon = trim($_POST['role_icon'] ?? '');
        $color = trim($_POST['role_color'] ?? '#9d9d9d');

        if ($roleName && $displayName) {
            if (createRole($roleName, $displayName, $icon, $color)) {
                $message = ['type' => 'success', 'text' => "✅ Rolle '$displayName' erfolgreich erstellt!"];
            } else {
                $message = ['type' => 'error', 'text' => '❌ Fehler beim Erstellen (Name bereits vergeben oder ungültig). Nur Kleinbuchstaben, Zahlen und Unterstriche erlaubt.'];
            }
        } else {
            $message = ['type' => 'error', 'text' => '❌ Rollenname und Anzeigename sind Pflichtfelder!'];
        }
    }

    // Rolle löschen
    if (isset($_POST['delete_role'])) {
        $roleName = $_POST['role_name'] ?? '';
        if (deleteRole($roleName)) {
            $message = ['type' => 'success', 'text' => '🔥 Rolle gelöscht!'];
        } else {
            $message = ['type' => 'error', 'text' => '❌ Rolle kann nicht gelöscht werden (System-Rolle oder noch Benutzer zugewiesen).'];
        }
    }

    // Berechtigungen aktualisieren
    if (isset($_POST['update_permissions'])) {
        $roleName = $_POST['perm_role'] ?? '';
        if ($roleName && $roleName !== 'meister') {
            foreach (PERMISSION_SECTIONS as $section) {
                $canRead = isset($_POST["perm_{$section}_read"]) ? 1 : 0;
                $canWrite = isset($_POST["perm_{$section}_write"]) ? 1 : 0;
                $canUpload = isset($_POST["perm_{$section}_upload"]) ? 1 : 0;
                updateRolePermissions($roleName, $section, $canRead, $canWrite, $canUpload);
            }
            $message = ['type' => 'success', 'text' => '✅ Berechtigungen aktualisiert!'];
        }
    }

    // Rollen-Anzeige aktualisieren
    if (isset($_POST['update_role_display'])) {
        $roleName = $_POST['edit_role_name'] ?? '';
        $displayName = trim($_POST['edit_display_name'] ?? '');
        $icon = trim($_POST['edit_icon'] ?? '');
        $color = trim($_POST['edit_color'] ?? '#9d9d9d');

        if ($roleName && $displayName) {
            if (updateRoleDisplay($roleName, $displayName, $icon, $color)) {
                $message = ['type' => 'success', 'text' => '✅ Rolle aktualisiert!'];
            } else {
                $message = ['type' => 'error', 'text' => '❌ Fehler beim Aktualisieren.'];
            }
        }
    }
}

$users = getAllUsers();
$roles = getAllRoles();
$csrfToken = getCSRFToken();

// Sektions-Labels für die Berechtigungsmatrix
$sectionLabels = [
    'bibliothek' => '📚 Bibliothek',
    'miliz' => '⚔️ Miliz',
    'aushaenge' => '📌 Aushänge',
    'verwaltung' => '📋 Verwaltung'
];

require_once 'header.php';
?>

<style>
.admin-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 30px;
    background: var(--bg-parchment);
    border: 3px solid var(--accent-gold);
    border-radius: 10px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
}

.admin-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--accent-gold);
}

.admin-header h1 {
    font-family: var(--font-elegant);
    color: var(--accent-gold);
    font-size: 2.5rem;
    margin: 0 0 10px 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.admin-stats {
    display: flex;
    gap: 20px;
    justify-content: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.stat-card {
    background: rgba(212, 175, 55, 0.1);
    border: 2px solid var(--accent-gold);
    padding: 15px 30px;
    border-radius: 8px;
    text-align: center;
}

.stat-card .number {
    font-size: 2rem;
    font-weight: bold;
    color: var(--accent-gold);
    font-family: var(--font-elegant);
}

.stat-card .label {
    font-size: 0.9rem;
    color: var(--text-ink);
    opacity: 0.8;
}

.admin-actions {
    margin-bottom: 30px;
    text-align: center;
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-admin {
    display: inline-block;
    padding: 12px 30px;
    background: var(--accent-gold);
    color: var(--text-dark);
    border: none;
    border-radius: 5px;
    font-family: var(--font-elegant);
    font-size: 1.1rem;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-admin:hover {
    background: #b8941f;
    transform: scale(1.05);
}

.user-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.user-table th {
    background: var(--accent-gold);
    color: var(--text-dark);
    padding: 12px;
    text-align: left;
    font-family: var(--font-elegant);
    font-size: 1.1rem;
}

.user-table td {
    padding: 12px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.user-table tr:hover {
    background: rgba(212, 175, 55, 0.1);
}

.role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: bold;
    color: #fff;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.9rem;
    margin: 0 3px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-edit {
    background: #0070dd;
    color: #fff;
}

.btn-edit:hover {
    background: #005bb5;
}

.btn-password {
    background: #a335ee;
    color: #fff;
}

.btn-password:hover {
    background: #862db8;
}

.btn-delete {
    background: #cc0000;
    color: #fff;
}

.btn-delete:hover {
    background: #990000;
}

.btn-perm {
    background: #1eff00;
    color: #1a1a1a;
}

.btn-perm:hover {
    background: #17cc00;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 3000;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-parchment);
    padding: 30px;
    border-radius: 10px;
    border: 3px solid var(--accent-gold);
    max-width: 500px;
    width: 90%;
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-content.modal-wide {
    max-width: 700px;
}

.modal-close {
    position: absolute;
    top: 10px;
    right: 20px;
    font-size: 2rem;
    cursor: pointer;
    color: var(--text-ink);
}

.modal-close:hover {
    color: var(--accent-gold);
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: var(--text-ink);
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 2px solid #c9a961;
    border-radius: 5px;
    font-size: 1rem;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--accent-gold);
}

.msg {
    padding: 15px;
    margin: 20px 0;
    border-radius: 5px;
    font-weight: bold;
}

.msg.success {
    background: rgba(0, 200, 0, 0.2);
    border: 2px solid green;
    color: darkgreen;
}

.msg.error {
    background: rgba(200, 0, 0, 0.2);
    border: 2px solid darkred;
    color: darkred;
}

/* Berechtigungsmatrix */
.perm-table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.perm-table th, .perm-table td {
    padding: 10px 12px;
    text-align: center;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.perm-table th {
    background: rgba(212, 175, 55, 0.2);
    font-family: var(--font-elegant);
    font-size: 0.95rem;
}

.perm-table td:first-child {
    text-align: left;
    font-weight: bold;
}

.perm-table input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent-gold);
}

/* Rollen-Abschnitt */
.section-divider {
    margin: 50px 0 30px;
    padding-top: 30px;
    border-top: 2px solid var(--accent-gold);
}

.role-color-preview {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    vertical-align: middle;
    border: 1px solid rgba(0,0,0,0.2);
}

.system-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 8px;
    font-size: 0.75rem;
    background: rgba(212, 175, 55, 0.3);
    color: var(--text-ink);
}

@media (max-width: 768px) {
    .admin-stats {
        flex-direction: column;
    }

    .user-table {
        font-size: 0.85rem;
    }

    .btn-small {
        display: block;
        margin: 5px 0;
        width: 100%;
    }

    .perm-table {
        font-size: 0.85rem;
    }

    .perm-table th, .perm-table td {
        padding: 6px 4px;
    }
}
</style>

<div class="admin-container">
    <div class="admin-header">
        <h1>⚙️ Administration</h1>
        <p style="font-family: var(--font-body); color: var(--text-ink); font-style: italic;">
            Benutzerverwaltung & Berechtigungen
        </p>
    </div>

    <?php if ($message): ?>
        <div class="msg <?php echo $message['type']; ?>">
            <?php echo $message['text']; ?>
        </div>
    <?php endif; ?>

    <div class="admin-stats">
        <div class="stat-card">
            <div class="number"><?php echo count($users); ?></div>
            <div class="label">Gesamt Benutzer</div>
        </div>
        <div class="stat-card">
            <div class="number">
                <?php echo count(array_filter($users, fn($u) => $u['role'] === 'meister')); ?>
            </div>
            <div class="label">Meister</div>
        </div>
        <div class="stat-card">
            <div class="number"><?php echo count($roles); ?></div>
            <div class="label">Rollen</div>
        </div>
        <div class="stat-card">
            <div class="number">
                <?php
                $activeCount = count(array_filter($users, function($u) {
                    return $u['last_login'] && strtotime($u['last_login']) > (time() - 86400 * 7);
                }));
                echo $activeCount;
                ?>
            </div>
            <div class="label">Aktiv (7 Tage)</div>
        </div>
    </div>

    <div class="admin-actions">
        <button class="btn-admin" onclick="openModal('modalCreateUser')">
            ➕ Neuer Benutzer
        </button>
        <button class="btn-admin" onclick="openModal('modalCreateRole')" style="background: #0070dd; color: #fff;">
            🛡️ Neue Rolle
        </button>
    </div>

    <!-- ========== BENUTZERLISTE ========== -->
    <h2 style="font-family: var(--font-elegant); color: var(--accent-gold); margin-top: 40px;">
        👥 Benutzerliste
    </h2>

    <table class="user-table">
        <thead>
            <tr>
                <th>Benutzername</th>
                <th>Rolle</th>
                <th>Erstellt am</th>
                <th>Letzter Login</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <?php $isSelf = ($user['id'] == $_SESSION['user_id']); ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        <?php if ($isSelf): ?>
                            <span style="color: var(--accent-gold); font-size: 0.85rem;"> (Du)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="role-badge" style="background: <?php echo htmlspecialchars($user['role_color'] ?? '#9d9d9d'); ?>;">
                            <?php echo htmlspecialchars(($user['role_icon'] ?? '') . ' ' . ($user['role_display'] ?? $user['role'])); ?>
                        </span>
                    </td>
                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php
                        if ($user['last_login']) {
                            echo date('d.m.Y H:i', strtotime($user['last_login']));
                        } else {
                            echo '<em style="color: #999;">Noch nie</em>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if (!$isSelf): ?>
                            <button class="btn-small btn-edit"
                                    onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>')">
                                ✏️ Rolle
                            </button>
                            <button class="btn-small btn-password"
                                    onclick="openPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                🔑 Passwort
                            </button>
                            <button class="btn-small btn-delete"
                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                🗑️ Löschen
                            </button>
                        <?php else: ?>
                            <button class="btn-small btn-password"
                                    onclick="openPasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                🔑 Mein Passwort
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ========== ROLLENVERWALTUNG ========== -->
    <div class="section-divider">
        <h2 style="font-family: var(--font-elegant); color: var(--accent-gold);">
            🛡️ Rollenverwaltung
        </h2>
        <p style="font-family: var(--font-body); color: var(--text-ink); font-style: italic; margin-bottom: 20px;">
            Rollen erstellen, bearbeiten und Berechtigungen konfigurieren
        </p>
    </div>

    <table class="user-table">
        <thead>
            <tr>
                <th>Rolle</th>
                <th>Interner Name</th>
                <th>Typ</th>
                <th>Benutzer</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
                <?php $userCount = getUserCountByRole($role['name']); ?>
                <tr>
                    <td>
                        <span class="role-badge" style="background: <?php echo htmlspecialchars($role['color']); ?>;">
                            <?php echo htmlspecialchars($role['icon'] . ' ' . $role['display_name']); ?>
                        </span>
                    </td>
                    <td><code><?php echo htmlspecialchars($role['name']); ?></code></td>
                    <td>
                        <?php if ($role['is_system']): ?>
                            <span class="system-badge">System</span>
                        <?php else: ?>
                            <span style="color: #666;">Benutzerdefiniert</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $userCount; ?></td>
                    <td>
                        <?php if ($role['name'] !== 'meister'): ?>
                            <button class="btn-small btn-perm"
                                    onclick="openPermModal('<?php echo htmlspecialchars($role['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($role['display_name'], ENT_QUOTES); ?>')">
                                🔐 Berechtigungen
                            </button>
                        <?php endif; ?>
                        <button class="btn-small btn-edit"
                                onclick="openEditRoleModal('<?php echo htmlspecialchars($role['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($role['display_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($role['icon'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($role['color'], ENT_QUOTES); ?>')">
                            ✏️ Bearbeiten
                        </button>
                        <?php if (!$role['is_system']): ?>
                            <button class="btn-small btn-delete"
                                    onclick="confirmDeleteRole('<?php echo htmlspecialchars($role['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($role['display_name'], ENT_QUOTES); ?>', <?php echo $userCount; ?>)">
                                🗑️ Löschen
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ========== MODALS ========== -->

<!-- MODAL: Neuer Benutzer -->
<div id="modalCreateUser" class="modal" onclick="if(event.target === this) closeModal('modalCreateUser')">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('modalCreateUser')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: var(--accent-gold); margin-top: 0;">
            ➕ Neuer Benutzer
        </h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="create_user" value="1">

            <div class="form-group">
                <label>Benutzername:</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>Passwort:</label>
                <input type="password" name="password" required minlength="6">
                <small style="color: #666;">Mindestens 6 Zeichen</small>
            </div>

            <div class="form-group">
                <label>Rolle:</label>
                <select name="role">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r['name']); ?>">
                            <?php echo htmlspecialchars($r['icon'] . ' ' . $r['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-admin" style="width: 100%; margin-top: 15px;">
                Erstellen
            </button>
        </form>
    </div>
</div>

<!-- MODAL: Rolle ändern (Benutzer) -->
<div id="modalEditRole" class="modal" onclick="if(event.target === this) closeModal('modalEditRole')">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('modalEditRole')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: var(--accent-gold); margin-top: 0;">
            ✏️ Rolle ändern
        </h2>
        <p id="editRoleUsername" style="font-weight: bold; margin-bottom: 20px;"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="update_role" value="1">
            <input type="hidden" name="user_id" id="editRoleUserId">

            <div class="form-group">
                <label>Neue Rolle:</label>
                <select name="new_role" id="editRoleSelect">
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r['name']); ?>">
                            <?php echo htmlspecialchars($r['icon'] . ' ' . $r['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn-admin" style="width: 100%; margin-top: 15px;">
                Speichern
            </button>
        </form>
    </div>
</div>

<!-- MODAL: Passwort ändern -->
<div id="modalPassword" class="modal" onclick="if(event.target === this) closeModal('modalPassword')">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('modalPassword')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: var(--accent-gold); margin-top: 0;">
            🔑 Passwort ändern
        </h2>
        <p id="passwordUsername" style="font-weight: bold; margin-bottom: 20px;"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="change_password" value="1">
            <input type="hidden" name="user_id" id="passwordUserId">

            <div class="form-group">
                <label>Neues Passwort:</label>
                <input type="password" name="new_password" required minlength="6">
                <small style="color: #666;">Mindestens 6 Zeichen</small>
            </div>

            <button type="submit" class="btn-admin" style="width: 100%; margin-top: 15px;">
                Passwort ändern
            </button>
        </form>
    </div>
</div>

<!-- MODAL: Benutzer löschen bestätigen -->
<div id="modalDelete" class="modal" onclick="if(event.target === this) closeModal('modalDelete')">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('modalDelete')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: darkred; margin-top: 0;">
            ⚠️ Benutzer löschen?
        </h2>
        <p id="deleteUsername" style="font-weight: bold; margin-bottom: 20px;"></p>
        <p style="color: darkred; margin-bottom: 20px;">
            Diese Aktion kann nicht rückgängig gemacht werden!
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="delete_user" value="1">
            <input type="hidden" name="user_id" id="deleteUserId">

            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-admin" onclick="closeModal('modalDelete')"
                        style="flex: 1; background: #999;">
                    Abbrechen
                </button>
                <button type="submit" class="btn-admin"
                        style="flex: 1; background: darkred;">
                    🔥 Löschen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Neue Rolle erstellen -->
<div id="modalCreateRole" class="modal" onclick="if(event.target === this) closeModal('modalCreateRole')">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('modalCreateRole')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: var(--accent-gold); margin-top: 0;">
            🛡️ Neue Rolle erstellen
        </h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="create_role" value="1">

            <div class="form-group">
                <label>Interner Name (Slug):</label>
                <input type="text" name="role_name" required pattern="[a-z][a-z0-9_]{1,30}"
                       placeholder="z.B. wachmann" title="Nur Kleinbuchstaben, Zahlen, Unterstriche. Min. 2 Zeichen.">
                <small style="color: #666;">Nur Kleinbuchstaben, Zahlen und _ (kann nicht geändert werden)</small>
            </div>

            <div class="form-group">
                <label>Anzeigename:</label>
                <input type="text" name="role_display_name" required placeholder="z.B. Wachmann">
            </div>

            <div class="form-group">
                <label>Icon (Emoji):</label>
                <input type="text" name="role_icon" placeholder="z.B. 🗡️" maxlength="4">
            </div>

            <div class="form-group">
                <label>Farbe:</label>
                <input type="color" name="role_color" value="#9d9d9d" style="height: 45px; padding: 5px;">
            </div>

            <button type="submit" class="btn-admin" style="width: 100%; margin-top: 15px;">
                Rolle erstellen
            </button>
        </form>
    </div>
</div>

<!-- MODAL: Rolle bearbeiten (Anzeige) -->
<div id="modalEditRoleDisplay" class="modal" onclick="if(event.target === this) closeModal('modalEditRoleDisplay')">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('modalEditRoleDisplay')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: var(--accent-gold); margin-top: 0;">
            ✏️ Rolle bearbeiten
        </h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="update_role_display" value="1">
            <input type="hidden" name="edit_role_name" id="editRoleName">

            <div class="form-group">
                <label>Anzeigename:</label>
                <input type="text" name="edit_display_name" id="editRoleDisplayName" required>
            </div>

            <div class="form-group">
                <label>Icon (Emoji):</label>
                <input type="text" name="edit_icon" id="editRoleIcon" maxlength="4">
            </div>

            <div class="form-group">
                <label>Farbe:</label>
                <input type="color" name="edit_color" id="editRoleColor" style="height: 45px; padding: 5px;">
            </div>

            <button type="submit" class="btn-admin" style="width: 100%; margin-top: 15px;">
                Speichern
            </button>
        </form>
    </div>
</div>

<!-- MODAL: Berechtigungen bearbeiten -->
<div id="modalPermissions" class="modal" onclick="if(event.target === this) closeModal('modalPermissions')">
    <div class="modal-content modal-wide">
        <span class="modal-close" onclick="closeModal('modalPermissions')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: var(--accent-gold); margin-top: 0;">
            🔐 Berechtigungen
        </h2>
        <p id="permRoleDisplay" style="font-weight: bold; margin-bottom: 20px;"></p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="update_permissions" value="1">
            <input type="hidden" name="perm_role" id="permRoleName">

            <table class="perm-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Bereich</th>
                        <th>Lesen</th>
                        <th>Schreiben</th>
                        <th>Upload</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectionLabels as $section => $label): ?>
                        <tr>
                            <td><?php echo $label; ?></td>
                            <td><input type="checkbox" name="perm_<?php echo $section; ?>_read" id="perm_<?php echo $section; ?>_read" value="1"></td>
                            <td><input type="checkbox" name="perm_<?php echo $section; ?>_write" id="perm_<?php echo $section; ?>_write" value="1"></td>
                            <td><input type="checkbox" name="perm_<?php echo $section; ?>_upload" id="perm_<?php echo $section; ?>_upload" value="1"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn-admin" style="width: 100%; margin-top: 15px;">
                Berechtigungen speichern
            </button>
        </form>
    </div>
</div>

<!-- MODAL: Rolle löschen bestätigen -->
<div id="modalDeleteRole" class="modal" onclick="if(event.target === this) closeModal('modalDeleteRole')">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal('modalDeleteRole')">&times;</span>
        <h2 style="font-family: var(--font-elegant); color: darkred; margin-top: 0;">
            ⚠️ Rolle löschen?
        </h2>
        <p id="deleteRoleName" style="font-weight: bold; margin-bottom: 10px;"></p>
        <p id="deleteRoleWarning" style="color: darkred; margin-bottom: 20px;"></p>
        <form method="POST" id="deleteRoleForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="delete_role" value="1">
            <input type="hidden" name="role_name" id="deleteRoleValue">

            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-admin" onclick="closeModal('modalDeleteRole')"
                        style="flex: 1; background: #999;">
                    Abbrechen
                </button>
                <button type="submit" class="btn-admin" id="deleteRoleSubmit"
                        style="flex: 1; background: darkred;">
                    🔥 Löschen
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Berechtigungsdaten für JavaScript bereitstellen
$allPermsData = [];
foreach ($roles as $role) {
    $allPermsData[$role['name']] = getRolePermissions($role['name']);
}
?>

<script>
// Berechtigungsdaten aus PHP
var rolePermissions = <?php echo json_encode($allPermsData); ?>;

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Benutzer-Rolle ändern
function openEditModal(userId, username, currentRole) {
    document.getElementById('editRoleUserId').value = userId;
    document.getElementById('editRoleUsername').textContent = 'Benutzer: ' + username;
    document.getElementById('editRoleSelect').value = currentRole;
    openModal('modalEditRole');
}

// Passwort ändern
function openPasswordModal(userId, username) {
    document.getElementById('passwordUserId').value = userId;
    document.getElementById('passwordUsername').textContent = 'Benutzer: ' + username;
    openModal('modalPassword');
}

// Benutzer löschen
function confirmDelete(userId, username) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUsername').textContent = 'Benutzer: ' + username;
    openModal('modalDelete');
}

// Rolle bearbeiten (Anzeige)
function openEditRoleModal(name, displayName, icon, color) {
    document.getElementById('editRoleName').value = name;
    document.getElementById('editRoleDisplayName').value = displayName;
    document.getElementById('editRoleIcon').value = icon;
    document.getElementById('editRoleColor').value = color;
    openModal('modalEditRoleDisplay');
}

// Berechtigungen bearbeiten
function openPermModal(roleName, displayName) {
    document.getElementById('permRoleName').value = roleName;
    document.getElementById('permRoleDisplay').textContent = 'Rolle: ' + displayName;

    var perms = rolePermissions[roleName] || {};
    var sections = ['bibliothek', 'miliz', 'aushaenge', 'verwaltung'];

    sections.forEach(function(section) {
        var sp = perms[section] || {};
        var readCb = document.getElementById('perm_' + section + '_read');
        var writeCb = document.getElementById('perm_' + section + '_write');
        var uploadCb = document.getElementById('perm_' + section + '_upload');

        if (readCb) readCb.checked = !!parseInt(sp.can_read);
        if (writeCb) writeCb.checked = !!parseInt(sp.can_write);
        if (uploadCb) uploadCb.checked = !!parseInt(sp.can_upload);
    });

    openModal('modalPermissions');
}

// Rolle löschen
function confirmDeleteRole(roleName, displayName, userCount) {
    document.getElementById('deleteRoleValue').value = roleName;
    document.getElementById('deleteRoleName').textContent = 'Rolle: ' + displayName;

    var warning = document.getElementById('deleteRoleWarning');
    var submitBtn = document.getElementById('deleteRoleSubmit');

    if (userCount > 0) {
        warning.textContent = 'Diese Rolle wird noch von ' + userCount + ' Benutzer(n) verwendet. Bitte weise diese Benutzer zuerst einer anderen Rolle zu.';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.5';
        submitBtn.style.cursor = 'not-allowed';
    } else {
        warning.textContent = 'Diese Aktion kann nicht rückgängig gemacht werden!';
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
    }

    openModal('modalDeleteRole');
}

// ESC-Taste schließt alle Modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
    }
});
</script>

<?php require_once 'footer.php'; ?>
