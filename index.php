<?php
require_once __DIR__ . '/functions.php';

if (isset($_POST['save_startseite_inhalt'])) {
    if (!isLoggedIn() || !hasPermission('verwaltung', 'write')) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Du hast keine Berechtigung, die Startseite zu bearbeiten.'];
    } elseif (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => '🚫 Ungültige Anfrage (CSRF)!'];
    } else {
        $slug = $_POST['kategorie'] ?? '';
        $inhalt = $_POST['inhalt'] ?? '';

        if (updateStartseiteInhalt($slug, $inhalt, getUsername())) {
            $_SESSION['flash'] = ['type' => 'success', 'text' => 'Abschnitt erfolgreich aktualisiert.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'text' => 'Speichern fehlgeschlagen. Bitte erneut versuchen.'];
        }
    }

    $ziel = isset($_POST['kategorie']) ? '#'.rawurlencode($_POST['kategorie']) : '';
    header('Location: index.php' . $ziel);
    exit;
}

$message = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Dämmerhafen - Portal';
$bodyClass = 'rp-view-room';
$startseitenInhalte = getStartseiteInhalte();
$canEditStartseite = isLoggedIn() && hasPermission('verwaltung', 'write');

require_once 'header.php';
?>

<img src="dammerhafen.jpg" alt="Dämmerhafen" class="rp-bg-fullscreen">

<main class="tw:relative tw:z-10 tw:min-h-[calc(100vh-120px)] tw:px-4 tw:pb-36 tw:pt-24 sm:tw:px-8 lg:tw:px-12">
    <section class="tw:mx-auto tw:mb-8 tw:max-w-6xl tw:rounded-2xl tw:border tw:border-amber-200/50 tw:bg-stone-950/60 tw:p-6 tw:text-amber-50 tw:backdrop-blur-md">
        <p class="tw:text-sm tw:uppercase tw:tracking-[0.3em] tw:text-amber-200/80">Willkommen in Dämmerhafen</p>
        <h1 class="tw:mt-2 tw:text-3xl tw:font-semibold md:tw:text-4xl">Chronik, Kodex & Stimmen der Hafenstadt</h1>
        <p class="tw:mt-3 tw:max-w-3xl tw:text-amber-100/85">
            Unten findest du die wichtigsten Grundlagen eurer Gemeinschaft. Jede Kategorie ist separat gepflegt und kann von berechtigten Admins direkt auf dieser Seite aktualisiert werden.
        </p>
    </section>

    <div class="tw:mx-auto tw:grid tw:max-w-6xl tw:gap-5 lg:tw:grid-cols-2">
        <?php foreach ($startseitenInhalte as $slug => $eintrag): ?>
            <article id="<?php echo htmlspecialchars($slug); ?>" class="tw:rounded-2xl tw:border tw:border-amber-100/30 tw:bg-stone-900/70 tw:p-5 tw:text-amber-50 tw:shadow-2xl tw:backdrop-blur-sm">
                <div class="tw:mb-4 tw:flex tw:items-center tw:justify-between tw:gap-4">
                    <h2 class="tw:text-2xl tw:font-semibold tw:text-amber-200"><?php echo htmlspecialchars($eintrag['titel']); ?></h2>
                    <span class="tw:rounded-full tw:bg-amber-200/15 tw:px-3 tw:py-1 tw:text-xs tw:uppercase tw:tracking-widest tw:text-amber-100/80"><?php echo htmlspecialchars($slug); ?></span>
                </div>

                <div class="tw:prose tw:prose-invert tw:max-w-none tw:text-amber-50/95 tw:prose-p:tw:my-2 tw:prose-strong:tw:text-amber-200">
                    <?php echo parseRichText($eintrag['inhalt']); ?>
                </div>

                <?php if (!empty($eintrag['aktualisiert_am'])): ?>
                    <p class="tw:mt-4 tw:text-xs tw:text-amber-100/70">
                        Zuletzt bearbeitet von <?php echo htmlspecialchars($eintrag['aktualisiert_von'] ?: 'Unbekannt'); ?> am <?php echo htmlspecialchars($eintrag['aktualisiert_am']); ?>
                    </p>
                <?php endif; ?>

                <?php if ($canEditStartseite): ?>
                    <details class="tw:mt-5 tw:rounded-xl tw:border tw:border-amber-100/25 tw:bg-stone-950/50 tw:p-3">
                        <summary class="tw:cursor-pointer tw:font-medium tw:text-amber-200">Bearbeiten (Admin)</summary>
                        <form method="POST" class="tw:mt-3 tw:space-y-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="kategorie" value="<?php echo htmlspecialchars($slug); ?>">
                            <textarea name="inhalt" rows="12" class="tw:w-full tw:rounded-lg tw:border tw:border-amber-200/30 tw:bg-stone-900/80 tw:p-3 tw:text-sm tw:text-amber-50 focus:tw:outline-none focus:tw:ring-2 focus:tw:ring-amber-400"><?php echo htmlspecialchars($eintrag['inhalt']); ?></textarea>
                            <button type="submit" name="save_startseite_inhalt" value="1" class="tw:rounded-lg tw:bg-amber-500 tw:px-4 tw:py-2 tw:font-semibold tw:text-stone-900 hover:tw:bg-amber-400">
                                Abschnitt speichern
                            </button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</main>

<nav class="miliz-bottom-nav tw:!grid tw:!grid-cols-2 md:tw:!grid-cols-4 tw:gap-2 tw:!bg-stone-950/90 tw:backdrop-blur-md">
    <a href="#geschichte" class="miliz-nav-btn tw:!justify-center">
        <span class="miliz-nav-label">Geschichte</span>
    </a>
    <a href="#regeln" class="miliz-nav-btn tw:!justify-center">
        <span class="miliz-nav-label">Regeln</span>
    </a>
    <a href="#ansprechpartner" class="miliz-nav-btn tw:!justify-center">
        <span class="miliz-nav-label">Ansprechpartner</span>
    </a>
    <a href="#regelwerk" class="miliz-nav-btn tw:!justify-center">
        <span class="miliz-nav-label">Regelwerk</span>
    </a>
</nav>

<?php require_once 'footer.php'; ?>
