<?php
declare(strict_types=1);

require_once __DIR__ . '/core/bootstrap.php';

use OlliCMS\BlockRenderer;
use OlliCMS\ThemeManager;

$siteConfig = $storage->getSiteConfig();
$themeConfig = $storage->getThemeConfig();
$layouts = ThemeManager::getLayouts();
$blockCatalog = BlockRenderer::getBlockCatalog();

// Käsitellään muokkaustilan (Moodle-tyylinen Edit Mode) kytkentä
if (isset($_GET['toggle_edit']) && $auth->isLoggedIn()) {
    if ($auth->verifyCsrf($_GET['csrf'] ?? '')) {
        $_SESSION['olli_edit_mode'] = empty($_SESSION['olli_edit_mode']);
    }
    $redirect = strtok($_SERVER['REQUEST_URI'] ?? 'index.php', '?');
    $params = $_GET;
    unset($params['toggle_edit'], $params['csrf']);
    $query = http_build_query($params);
    header('Location: ' . $redirect . ($query ? '?' . $query : ''));
    exit;
}

$isLoggedIn = $auth->isLoggedIn();
$editMode = $isLoggedIn && !empty($_SESSION['olli_edit_mode']);

// Reititys: artikkeli, artikkelilistaus, haku vai sivu
$viewType = 'page';
$currentPost = null;
$currentPage = null;
$searchQuery = trim($_GET['q'] ?? '');

if ($searchQuery !== '') {
    $viewType = 'search';
} elseif (!empty($_GET['post'])) {
    $viewType = 'post';
    $currentPost = $storage->getPost((string)$_GET['post']);
} elseif (($_GET['view'] ?? '') === 'posts') {
    $viewType = 'posts_archive';
} else {
    $slug = $_GET['page'] ?? ($siteConfig['homepage_slug'] ?? 'etusivu');
    $currentPage = $storage->getPage((string)$slug);
    if (!$currentPage) {
        $pages = $storage->listPages(true);
        $currentPage = $pages[0] ?? null;
    }
}

$renderer = new BlockRenderer($storage, $editMode, $auth->getCsrfToken());

$pageTitle = match ($viewType) {
    'post' => ($currentPost['title'] ?? 'Artikkeli') . ' — ' . ($siteConfig['site_name'] ?? 'Olli CMS'),
    'posts_archive' => 'Artikkelit & julkaisut — ' . ($siteConfig['site_name'] ?? 'Olli CMS'),
    'search' => 'Haku: ' . $searchQuery . ' — ' . ($siteConfig['site_name'] ?? 'Olli CMS'),
    default => ($currentPage['title'] ?? 'Etusivu') . ' — ' . ($siteConfig['site_name'] ?? 'Olli CMS'),
};

$cardStyle = htmlspecialchars($themeConfig['card_style'] ?? 'elevated');
$headerStyle = htmlspecialchars($themeConfig['header_style'] ?? 'glass');
$themeMode = htmlspecialchars($themeConfig['mode'] ?? 'light');
?>
<!DOCTYPE html>
<html lang="fi" data-theme="<?= $themeMode ?>" data-card-style="<?= $cardStyle ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        <?= $themeManager->getCssVariables() ?>
    </style>
</head>
<body class="<?= $editMode ? 'is-edit-mode' : '' ?>">

    <!-- Ylläpitäjän & Moodle-henkinen muokkaustilan yläpalkki -->
    <div class="cms-top-utility">
        <div class="container utility-inner">
            <div class="utility-left">
                <span class="cms-status-dot"></span>
                <span class="utility-brand"><?= htmlspecialchars($siteConfig['site_name'] ?? 'Olli CMS') ?></span>
                <?php if ($viewType === 'page' && $currentPage): ?>
                    <span class="utility-pill">
                        📐 Palstoitus: <strong><?= htmlspecialchars($layouts[$currentPage['layout'] ?? '3col-moodle']['name'] ?? '3 palstaa') ?></strong>
                    </span>
                <?php endif; ?>
            </div>
            <div class="utility-right">
                <form action="index.php" method="get" class="quick-search-form">
                    <input type="search" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Hae sivustolta..." aria-label="Hae sivustolta">
                </form>

                <?php if ($isLoggedIn): ?>
                    <?php if ($viewType === 'page' && $currentPage): ?>
                        <?php
                        $toggleParams = $_GET;
                        $toggleParams['toggle_edit'] = '1';
                        $toggleParams['csrf'] = $auth->getCsrfToken();
                        $toggleUrl = '?' . http_build_query($toggleParams);
                        ?>
                        <a href="<?= htmlspecialchars($toggleUrl) ?>" class="moodle-edit-switch <?= $editMode ? 'active' : '' ?>">
                            <span class="switch-track"><span class="switch-thumb"></span></span>
                            <span><?= $editMode ? 'Muokkaustila: PÄÄLLÄ' : 'Muokkaustila' ?></span>
                        </a>
                    <?php endif; ?>
                    <a href="admin/" class="utility-btn">⚙️ Hallintapaneeli</a>
                <?php else: ?>
                    <a href="admin/" class="utility-btn">🔒 Kirjaudu ylläpitoon</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Päänavigaatio -->
    <header class="site-header header-<?= $headerStyle ?>">
        <div class="container header-inner">
            <a href="index.php" class="site-brand">
                <span class="brand-logo-mark">◈</span>
                <div class="brand-text">
                    <strong><?= htmlspecialchars($siteConfig['site_name'] ?? 'Olli CMS') ?></strong>
                    <span><?= htmlspecialchars($siteConfig['tagline'] ?? '') ?></span>
                </div>
            </a>

            <button type="button" class="mobile-menu-toggle" onclick="document.querySelector('.main-nav').classList.toggle('open')" aria-label="Valikko">
                ☰
            </button>

            <nav class="main-nav">
                <?php foreach ($siteConfig['menu'] ?? [] as $item): ?>
                    <a href="<?= BlockRenderer::sanitizeUrl((string)($item['url'] ?? '#')) ?>" class="nav-link">
                        <?php if (!empty($item['icon'])): ?>
                            <span class="nav-icon"><?= htmlspecialchars($item['icon']) ?></span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($item['label'] ?? 'Linkki') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <?php if ($editMode && $viewType === 'page' && $currentPage): ?>
        <!-- MOODLE-TYYLINEN SIVUN PALSTOITUS- JA PIKATYÖKALUPALKKI -->
        <div class="inline-builder-ribbon">
            <div class="container ribbon-inner">
                <div class="ribbon-group">
                    <label for="inlineLayoutSelect"><strong>📐 Valitse sivun palstoitus:</strong></label>
                    <select id="inlineLayoutSelect" onchange="OlliInlineEditor.changePageLayout(this.value)">
                        <?php foreach ($layouts as $lKey => $lInfo): ?>
                            <option value="<?= htmlspecialchars($lKey) ?>" <?= ($currentPage['layout'] ?? '3col-moodle') === $lKey ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lInfo['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ribbon-group ribbon-checkboxes">
                    <label class="ribbon-check">
                        <input type="checkbox" id="inlineGlobalLeft" <?= !empty($currentPage['show_global_left']) ? 'checked' : '' ?> onchange="OlliInlineEditor.savePageStructure()">
                        <span>Näytä globaalit vasemmassa palstassa</span>
                    </label>
                    <label class="ribbon-check">
                        <input type="checkbox" id="inlineGlobalRight" <?= !empty($currentPage['show_global_right']) ? 'checked' : '' ?> onchange="OlliInlineEditor.savePageStructure()">
                        <span>Näytä globaalit oikeassa palstassa</span>
                    </label>
                </div>

                <div class="ribbon-actions">
                    <span id="inlineSaveIndicator" class="save-indicator">Muokkaustila aktiivinen – raahaa tai lisää lohkoja</span>
                    <button type="button" class="btn btn-sm btn-primary" onclick="OlliInlineEditor.savePageStructure()">💾 Tallenna sivu</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- PÄÄSISÄLTÖALUE -->
    <main class="site-main container">

        <?php if ($viewType === 'page' && $currentPage): ?>
            <?php
            $layoutKey = $currentPage['layout'] ?? '3col-moodle';
            $layoutDef = $layouts[$layoutKey] ?? $layouts['3col-moodle'];
            $activeCols = $layoutDef['cols'];
            $columnsData = $currentPage['columns'] ?? ['left' => [], 'main' => [], 'right' => []];
            $globalBlocks = $siteConfig['global_blocks'] ?? ['left' => [], 'right' => []];
            ?>

            <div class="page-heading-bar">
                <div>
                    <h1 class="page-main-title"><?= htmlspecialchars($currentPage['title'] ?? '') ?></h1>
                    <?php if (!empty($currentPage['subtitle'])): ?>
                        <p class="page-subtitle"><?= htmlspecialchars($currentPage['subtitle']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bento-layout-grid layout-<?= htmlspecialchars($layoutKey) ?>" style="grid-template-columns: <?= $layoutDef['grid_css'] ?>;">
                <?php
                $colTitles = [
                    'left' => 'Vasen palsta (Sivualue A)',
                    'main' => 'Keskipalsta (Pääsisältöalue)',
                    'right' => 'Oikea palsta (Sivualue B)'
                ];

                foreach ($activeCols as $colKey):
                    $colBlocks = $columnsData[$colKey] ?? [];
                    // Jos jokin palsta on piilotettu valitussa palstoituksessa (esim. 1col-full piilottaa left/right),
                    // näytetään silti omat lohkot tai pidetään ne tallessa JSONissa
                ?>
                    <div class="bento-column col-<?= htmlspecialchars($colKey) ?>" data-column-key="<?= htmlspecialchars($colKey) ?>">
                        <?php if ($editMode): ?>
                            <div class="column-region-label">
                                <span><?= htmlspecialchars($colTitles[$colKey] ?? $colKey) ?></span>
                                <span class="region-badge"><?= count($colBlocks) ?> lohkoa</span>
                            </div>
                        <?php endif; ?>

                        <!-- Globaalit lohkot (vasen / oikea) -->
                        <?php
                        if ($colKey === 'left' && !empty($currentPage['show_global_left']) && !empty($globalBlocks['left'])) {
                            foreach ($globalBlocks['left'] as $gBlock) {
                                echo $renderer->renderBlock($gBlock, 'left', true);
                            }
                        }
                        if ($colKey === 'right' && !empty($currentPage['show_global_right']) && !empty($globalBlocks['right'])) {
                            foreach ($globalBlocks['right'] as $gBlock) {
                                echo $renderer->renderBlock($gBlock, 'right', true);
                            }
                        }
                        ?>

                        <!-- Sivukohtaiset lohkot (Sortable-alue) -->
                        <div class="column-blocks-sortable" data-column="<?= htmlspecialchars($colKey) ?>">
                            <?php foreach ($colBlocks as $block): ?>
                                <?= $renderer->renderBlock($block, $colKey, false) ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($editMode): ?>
                            <button type="button" class="btn-add-block-region" onclick="OlliInlineEditor.openAddBlockModal('<?= htmlspecialchars($colKey) ?>')">
                                <span>＋ Lisää sisältöelementti tähän palstaan</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($viewType === 'post' && $currentPost): ?>
            <!-- YKSITTÄISEN ARTIKKELIN NÄKYMÄ -->
            <div class="bento-layout-grid" style="grid-template-columns: minmax(0, 7fr) minmax(260px, 3fr);">
                <div class="bento-column">
                    <article class="bento-block single-post-card">
                        <div class="post-breadcrumb">
                            <a href="?view=posts">← Kaikki artikkelit</a>
                            <span class="post-cat"><?= htmlspecialchars($currentPost['category'] ?? 'Yleinen') ?></span>
                        </div>
                        <h1 class="single-post-title"><?= htmlspecialchars($currentPost['title'] ?? '') ?></h1>
                        <div class="single-post-meta">
                            <span>✍️ <?= htmlspecialchars($currentPost['author'] ?? 'Ylläpito') ?></span>
                            <span>📅 <?= htmlspecialchars($currentPost['date'] ?? '') ?></span>
                            <?php if (!empty($currentPost['read_time'])): ?>
                                <span>⏱️ Lukuaika <?= htmlspecialchars($currentPost['read_time']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($currentPost['featured_image'])): ?>
                            <div class="single-post-hero-img">
                                <img src="<?= BlockRenderer::sanitizeUrl((string)$currentPost['featured_image']) ?>" alt="<?= htmlspecialchars($currentPost['title'] ?? '') ?>">
                            </div>
                        <?php endif; ?>
                        <div class="prose single-post-body">
                            <?= BlockRenderer::sanitizeHtml((string)($currentPost['content'] ?? '')) ?>
                        </div>
                    </article>
                </div>

                <aside class="bento-column">
                    <?= $renderer->renderBlock([
                        'id' => 'post-sidebar-recent',
                        'type' => 'post_feed',
                        'title' => 'Muita artikkeleita',
                        'style' => 'surface',
                        'limit' => 3,
                        'layout_mode' => 'compact'
                    ], 'right', true) ?>
                </aside>
            </div>

        <?php elseif ($viewType === 'posts_archive'): ?>
            <!-- ARTIKKELIARKISTO -->
            <?php
            $selectedCat = $_GET['cat'] ?? 'all';
            $allPosts = $storage->listPosts(true);
            $categories = array_values(array_unique(array_filter(array_map(fn($p) => $p['category'] ?? '', $allPosts))));
            $filteredPosts = $storage->listPosts(true, $selectedCat);
            ?>
            <div class="page-heading-bar">
                <div>
                    <h1 class="page-main-title">Artikkelit & julkaisut</h1>
                    <p class="page-subtitle">Selaa ajankohtaisia kirjoituksia, oppaita ja tiedotteita aiheittain.</p>
                </div>
                <div class="category-filter-pills">
                    <a href="?view=posts" class="cat-pill <?= $selectedCat === 'all' ? 'active' : '' ?>">Kaikki</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="?view=posts&cat=<?= urlencode($cat) ?>" class="cat-pill <?= strcasecmp($selectedCat, $cat) === 0 ? 'active' : '' ?>">
                            <?= htmlspecialchars($cat) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="archive-posts-grid">
                <?php foreach ($filteredPosts as $post): ?>
                    <article class="bento-block post-feed-card">
                        <?php if (!empty($post['featured_image'])): ?>
                            <a href="?post=<?= urlencode($post['slug']) ?>" class="post-card-img">
                                <img src="<?= BlockRenderer::sanitizeUrl((string)$post['featured_image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
                            </a>
                        <?php endif; ?>
                        <div class="post-card-content">
                            <div class="post-meta">
                                <span class="post-cat"><?= htmlspecialchars($post['category'] ?? 'Yleinen') ?></span>
                                <span>•</span>
                                <time><?= htmlspecialchars($post['date'] ?? '') ?></time>
                            </div>
                            <h3><a href="?post=<?= urlencode($post['slug']) ?>"><?= htmlspecialchars($post['title']) ?></a></h3>
                            <p><?= htmlspecialchars($post['excerpt'] ?? '') ?></p>
                            <a href="?post=<?= urlencode($post['slug']) ?>" class="read-more-link">Lue artikkeli →</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif ($viewType === 'search'): ?>
            <!-- HAKUTULOKSET -->
            <?php
            $qLower = mb_strtolower($searchQuery);
            $matchedPages = array_filter($storage->listPages(true), function ($p) use ($qLower) {
                $haystack = mb_strtolower(json_encode($p, JSON_UNESCAPED_UNICODE) ?: '');
                return str_contains($haystack, $qLower);
            });
            $matchedPosts = array_filter($storage->listPosts(true), function ($p) use ($qLower) {
                $haystack = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['excerpt'] ?? '') . ' ' . ($p['content'] ?? ''));
                return str_contains($haystack, $qLower);
            });
            ?>
            <div class="page-heading-bar">
                <div>
                    <h1 class="page-main-title">Hakutulokset: ”<?= htmlspecialchars($searchQuery) ?>”</h1>
                    <p class="page-subtitle">Löytyi <?= count($matchedPages) ?> sivua ja <?= count($matchedPosts) ?> artikkelia.</p>
                </div>
            </div>

            <div class="bento-layout-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="bento-block">
                    <h3 class="block-title">Sivut (<?= count($matchedPages) ?>)</h3>
                    <?php if (empty($matchedPages)): ?>
                        <p class="text-muted">Ei osumia sivuista.</p>
                    <?php else: ?>
                        <ul class="quick-links-list">
                            <?php foreach ($matchedPages as $mp): ?>
                                <li>
                                    <a href="?page=<?= urlencode($mp['slug']) ?>" class="quick-link-item">
                                        <span class="ql-icon">📄</span>
                                        <span class="ql-label"><strong><?= htmlspecialchars($mp['title']) ?></strong><br><small><?= htmlspecialchars($mp['subtitle'] ?? '') ?></small></span>
                                        <span class="ql-arrow">→</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="bento-block">
                    <h3 class="block-title">Artikkelit (<?= count($matchedPosts) ?>)</h3>
                    <?php if (empty($matchedPosts)): ?>
                        <p class="text-muted">Ei osumia artikkeleista.</p>
                    <?php else: ?>
                        <ul class="quick-links-list">
                            <?php foreach ($matchedPosts as $mpo): ?>
                                <li>
                                    <a href="?post=<?= urlencode($mpo['slug']) ?>" class="quick-link-item">
                                        <span class="ql-icon">📰</span>
                                        <span class="ql-label"><strong><?= htmlspecialchars($mpo['title']) ?></strong><br><small><?= htmlspecialchars($mpo['excerpt'] ?? '') ?></small></span>
                                        <span class="ql-arrow">→</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Kuvien suurennos (Lightbox) -->
    <div id="olliLightbox" class="olli-lightbox" onclick="OlliSite.closeLightbox()">
        <div class="lightbox-inner" onclick="event.stopPropagation()">
            <button type="button" class="lightbox-close" onclick="OlliSite.closeLightbox()">✕</button>
            <img id="olliLightboxImg" src="" alt="">
            <p id="olliLightboxCaption"></p>
        </div>
    </div>

    <?php if ($editMode && $viewType === 'page' && $currentPage): ?>
        <!-- MODAALI: LISÄÄ TAI MUOKKAA LOHKOA SUORAAN SIVULLA -->
        <div id="inlineBlockModal" class="olli-modal-backdrop" style="display:none;">
            <div class="olli-modal">
                <div class="olli-modal-header">
                    <h3 id="inlineModalTitle">Lisää sisältöelementti</h3>
                    <button type="button" class="modal-close-btn" onclick="OlliInlineEditor.closeModal()">✕</button>
                </div>
                <div class="olli-modal-body" id="inlineModalBody"></div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
        <script>
            window.OLLI_PAGE_DATA = <?= json_encode($currentPage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            window.OLLI_BLOCK_CATALOG = <?= json_encode($blockCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            window.OLLI_CSRF_TOKEN = <?= json_encode($auth->getCsrfToken()) ?>;
        </script>
    <?php endif; ?>

    <footer class="site-footer">
        <div class="container footer-inner">
            <p><?= htmlspecialchars($siteConfig['footer_text'] ?? '') ?></p>
            <div class="footer-links">
                <a href="index.php">Etusivu</a>
                <a href="?view=posts">Artikkelit</a>
                <a href="admin/">Ylläpito (Admin)</a>
            </div>
        </div>
    </footer>

    <script src="assets/js/site.js"></script>
</body>
</html>
