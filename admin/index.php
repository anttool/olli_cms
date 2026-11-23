<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

use OlliCMS\BlockRenderer;
use OlliCMS\ThemeManager;

$loginError = '';

// Kirjautuminen ulos (varmistetaan CSRF)
if (($_GET['action'] ?? '') === 'logout') {
    if ($auth->verifyCsrf($_GET['csrf'] ?? '')) {
        $auth->logout();
    }
    header('Location: index.php');
    exit;
}

// Kirjautumislomakkeen käsittely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? '')) {
        $loginError = 'Istuntotunniste vanhentui. Yritä uudelleen.';
    } elseif ($auth->isRateLimited()) {
        $loginError = 'Liian monta epäonnistunutta kirjautumisyritystä. Odota 2 minuuttia.';
    } else {
        $u = trim((string)($_POST['username'] ?? ''));
        $p = (string)($_POST['password'] ?? '');
        if ($auth->login($u, $p)) {
            header('Location: index.php');
            exit;
        } else {
            $loginError = 'Virheellinen käyttäjätunnus tai salasana.';
        }
    }
}

$isLoggedIn = $auth->isLoggedIn();
$siteConfig = $storage->getSiteConfig();
$themeConfig = $storage->getThemeConfig();
$pages = $storage->listPages(false);
$posts = $storage->listPosts(false);
$media = $storage->listMedia();
$messages = $storage->listMessages();
$layouts = ThemeManager::getLayouts();
$presets = ThemeManager::getPresets();
$blockCatalog = BlockRenderer::getBlockCatalog();
$activeTab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hallintapaneeli — <?= htmlspecialchars($siteConfig['site_name'] ?? 'Olli CMS') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- KIRJAUTUMISNÄKYMÄ -->
    <div class="login-screen">
        <div class="login-card">
            <div class="login-brand">
                <span class="brand-icon">◈</span>
                <div>
                    <h1>Olli CMS</h1>
                    <p>Tietokannaton palsta- ja lohkojulkaisujärjestelmä</p>
                </div>
            </div>

            <?php if ($loginError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>

            <form method="post" action="index.php" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($auth->getCsrfToken()) ?>">
                <label>Käyttäjätunnus
                    <input type="text" name="username" value="admin" required autofocus>
                </label>
                <label>Salasana
                    <input type="password" name="password" value="admin123" required>
                </label>
                <button type="submit" name="login_submit" value="1" class="btn btn-primary btn-block">Kirjaudu hallintapaneeliin →</button>
            </form>

            <div class="login-hint">
                <strong>Oletustunnukset kokeiluun:</strong><br>
                Tunnus: <code>admin</code> &nbsp;|&nbsp; Salasana: <code>admin123</code>
            </div>

            <a href="../index.php" class="back-to-site-link">← Palaa julkiselle sivustolle</a>
        </div>
    </div>
<?php else: ?>
    <!-- HALLINTAPANEELIN RUNKO -->
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <span class="brand-icon">◈</span>
                <div>
                    <strong>Olli CMS</strong>
                    <small>Flat-File Engine</small>
                </div>
            </div>

            <nav class="sidebar-nav">
                <button type="button" class="side-nav-btn <?= $activeTab === 'dashboard' ? 'active' : '' ?>" data-tab="dashboard" onclick="OlliAdmin.switchTab('dashboard')">
                    <span>📊</span> Yleiskatsaus
                </button>
                <button type="button" class="side-nav-btn <?= $activeTab === 'pages' ? 'active' : '' ?>" data-tab="pages" onclick="OlliAdmin.switchTab('pages')">
                    <span>📐</span> Sivut & Palstoitus <span class="nav-count"><?= count($pages) ?></span>
                </button>
                <button type="button" class="side-nav-btn <?= $activeTab === 'posts' ? 'active' : '' ?>" data-tab="posts" onclick="OlliAdmin.switchTab('posts')">
                    <span>📰</span> Artikkelit <span class="nav-count"><?= count($posts) ?></span>
                </button>
                <button type="button" class="side-nav-btn <?= $activeTab === 'theme' ? 'active' : '' ?>" data-tab="theme" onclick="OlliAdmin.switchTab('theme')">
                    <span>🎨</span> Ulkoasu & Teema
                </button>
                <button type="button" class="side-nav-btn <?= $activeTab === 'media' ? 'active' : '' ?>" data-tab="media" onclick="OlliAdmin.switchTab('media')">
                    <span>🖼️</span> Mediapankki <span class="nav-count"><?= count($media) ?></span>
                </button>
                <button type="button" class="side-nav-btn <?= $activeTab === 'messages' ? 'active' : '' ?>" data-tab="messages" onclick="OlliAdmin.switchTab('messages')">
                    <span>✉️</span> Viestit <span class="nav-count"><?= count($messages) ?></span>
                </button>
                <button type="button" class="side-nav-btn <?= $activeTab === 'settings' ? 'active' : '' ?>" data-tab="settings" onclick="OlliAdmin.switchTab('settings')">
                    <span>⚙️</span> Valikot & Asetukset
                </button>
            </nav>

            <div class="sidebar-footer">
                <a href="../index.php" target="_blank" class="btn btn-outline btn-block">🌐 Avaa sivusto ↗</a>
                <a href="../index.php?toggle_edit=1&csrf=<?= urlencode($auth->getCsrfToken()) ?>" class="btn btn-accent-soft btn-block">✨ Muokkaa suoraan sivulla</a>
                <a href="?action=logout&csrf=<?= urlencode($auth->getCsrfToken()) ?>" class="logout-link">Kirjaudu ulos (<?= htmlspecialchars($auth->getUser()['username'] ?? 'admin') ?>)</a>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div>
                    <h2 id="topbarTitle">Yleiskatsaus</h2>
                    <p class="topbar-sub">Kaikki muutokset tallennetaan suoraan JSON-tiedostoihin ilman tietokantaa.</p>
                </div>
                <div class="topbar-actions">
                    <span id="adminToast" class="admin-toast"></span>
                    <a href="api.php?action=export_backup&csrf_token=<?= urlencode($auth->getCsrfToken()) ?>" class="btn btn-outline">📦 Lataa ZIP-varmuuskopio</a>
                </div>
            </header>

            <div class="admin-content">

                <!-- 1. YLEISKATSAUS (DASHBOARD) -->
                <section id="tab-dashboard" class="admin-tab <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
                    <div class="stats-row">
                        <div class="stat-card" onclick="OlliAdmin.switchTab('pages')">
                            <span class="stat-icon">📐</span>
                            <div>
                                <h3><?= count($pages) ?></h3>
                                <p>Sivua & palstarakennetta</p>
                            </div>
                        </div>
                        <div class="stat-card" onclick="OlliAdmin.switchTab('posts')">
                            <span class="stat-icon">📰</span>
                            <div>
                                <h3><?= count($posts) ?></h3>
                                <p>Artikkelia / uutista</p>
                            </div>
                        </div>
                        <div class="stat-card" onclick="OlliAdmin.switchTab('media')">
                            <span class="stat-icon">🖼️</span>
                            <div>
                                <h3><?= count($media) ?></h3>
                                <p>Tiedostoa mediapankissa</p>
                            </div>
                        </div>
                        <div class="stat-card" onclick="OlliAdmin.switchTab('messages')">
                            <span class="stat-icon">✉️</span>
                            <div>
                                <h3><?= count($messages) ?></h3>
                                <p>Saapunutta viestiä</p>
                            </div>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="panel-card">
                            <div class="panel-header">
                                <h3>🚀 Pika-aloitus: Palstoitus & Lohkot</h3>
                            </div>
                            <p>Olli CMS yhdistää Moodlen monipalstaisen lohkoajattelun ja modernin Bento Grid -muotoilun:</p>
                            <ol class="guide-steps">
                                <li><strong>Valitse sivun palstoitus:</strong> Siirry <em>Sivut & Palstoitus</em> -välilehdelle ja valitse sivulle 1, 2 tai 3 palstaa eri leveyssuhteilla.</li>
                                <li><strong>Lisää sisältölohkoja palstoihin:</strong> Lisää vasempaan, keski- tai oikeaan palstaan esim. <em>Huomiolaatikoita, Haitareita, Artikkelisyötteitä, Tiedostolistoja, Kuvagallerioita tai Lomakkeita</em>.</li>
                                <li><strong>Järjestä raahaamalla:</strong> Voit raahata lohkoja palstasta toiseen joko täällä hallintapaneelissa tai suoraan julkisella sivustolla kytkemällä yläpalkista <strong>Muokkaustilan päälle</strong>!</li>
                            </ol>
                            <div style="display:flex;gap:0.75rem;margin-top:1.25rem;flex-wrap:wrap;">
                                <button type="button" class="btn btn-primary" onclick="OlliAdmin.switchTab('pages')">📐 Muokkaa sivuja ja palstoja</button>
                                <button type="button" class="btn btn-outline" onclick="OlliAdmin.switchTab('theme')">🎨 Kustomoi värit ja teema</button>
                            </div>
                        </div>

                        <div class="panel-card">
                            <div class="panel-header">
                                <h3>📦 Siirtäminen webhotelliin</h3>
                            </div>
                            <p>Sivusto ei käytä SQL-tietokantaa. Kaikki sisältö sijaitsee <code>content/</code>-kansiossa:</p>
                            <ul class="clean-list">
                                <li>📄 <code>content/pages/*.json</code> — Sivut, palstoitukset ja lohkot</li>
                                <li>📰 <code>content/posts/*.json</code> — Artikkelit ja tiedotteet</li>
                                <li>🎨 <code>content/config/theme.json</code> — Ulkoasuasetukset</li>
                                <li>🖼️ <code>content/uploads/</code> — Kuvat ja liitetiedostot</li>
                            </ul>
                            <p class="text-muted" style="font-size:0.85rem;margin-top:0.75rem;">
                                Kun siirrät koko <code>olli_cms</code>-kansion FTP:llä mihin tahansa PHP 8 -webhotelliin, sivusto toimii välittömästi ilman asennusta.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 2. SIVUT & PALSTOITUS (VISUAL PAGE & COLUMN BUILDER) -->
                <section id="tab-pages" class="admin-tab <?= $activeTab === 'pages' ? 'active' : '' ?>">
                    <div class="split-workspace">
                        <!-- Vasen lista sivuista -->
                        <div class="workspace-list panel-card">
                            <div class="panel-header">
                                <h3>Sivut</h3>
                                <button type="button" class="btn btn-sm btn-primary" onclick="OlliAdmin.createNewPage()">＋ Uusi sivu</button>
                            </div>
                            <div id="pagesListContainer" class="item-list"></div>
                        </div>

                        <!-- Oikea visuaalinen palsta- ja lohkoeditori -->
                        <div class="workspace-editor panel-card">
                            <div class="panel-header">
                                <h3 id="pageEditorHeading">Muokkaa sivua</h3>
                                <div style="display:flex;gap:0.5rem;">
                                    <a id="pagePreviewLink" href="../index.php" target="_blank" class="btn btn-sm btn-outline">👁️ Esikatsele</a>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="OlliAdmin.deleteCurrentPage()">🗑️ Poista</button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="OlliAdmin.saveCurrentPage()">💾 Tallenna sivu</button>
                                </div>
                            </div>

                            <!-- Sivun perustiedot -->
                            <div class="form-grid-3">
                                <label>Sivun otsikko *
                                    <input type="text" id="pageTitle" placeholder="esim. Kurssimateriaalit" oninput="OlliAdmin.autoSlugPage()">
                                </label>
                                <label>URL-tunniste (slug)
                                    <input type="text" id="pageSlug" placeholder="esim. kurssimateriaalit">
                                </label>
                                <div class="form-grid-2">
                                    <label>Tila
                                        <select id="pageStatus">
                                            <option value="published">Julkaistu</option>
                                            <option value="draft">Luonnos</option>
                                        </select>
                                    </label>
                                    <label>Järjestys
                                        <input type="number" id="pageOrder" value="1" min="1" max="99">
                                    </label>
                                </div>
                            </div>

                            <label style="margin-top:0.75rem;">Alaotsikko / johdantoteksti
                                <input type="text" id="pageSubtitle" placeholder="Näytetään pääotsikon alapuolella...">
                            </label>

                            <!-- Visuaalinen palstoituksen valitsin -->
                            <div class="layout-selector-section">
                                <div class="section-label-row">
                                    <strong>📐 Valitse sivun palstoitus (Page Layout):</strong>
                                    <div class="global-toggles">
                                        <label><input type="checkbox" id="pageGlobalLeft"> Näytä globaalit lohkot vasemmalla</label>
                                        <label><input type="checkbox" id="pageGlobalRight"> Näytä globaalit lohkot oikealla</label>
                                    </div>
                                </div>
                                <div class="layout-cards-grid" id="layoutCardsPicker">
                                    <?php foreach ($layouts as $lKey => $lInfo): ?>
                                        <button type="button" class="layout-option-card" data-layout="<?= htmlspecialchars($lKey) ?>" onclick="OlliAdmin.selectPageLayout('<?= htmlspecialchars($lKey) ?>')">
                                            <div class="layout-mini-preview">
                                                <?php foreach ($lInfo['preview'] as $pct): ?>
                                                    <span style="flex: <?= (int)$pct ?>"><i><?= htmlspecialchars($pct) ?></i></span>
                                                <?php endforeach; ?>
                                            </div>
                                            <strong><?= htmlspecialchars($lInfo['name']) ?></strong>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Kolmipalstainen visuaalinen lohkoalue (Vasen / Keski / Oikea) -->
                            <div class="visual-columns-builder" id="adminColumnsCanvas">
                                <div class="builder-col" data-col="left">
                                    <div class="builder-col-header">
                                        <span>Vasen palsta (Alue A)</span>
                                        <button type="button" class="btn btn-xs btn-outline" onclick="OlliAdmin.openBlockPicker('left')">＋ Lisää lohko</button>
                                    </div>
                                    <div class="builder-col-list" id="colList-left" data-col="left"></div>
                                </div>

                                <div class="builder-col" data-col="main">
                                    <div class="builder-col-header">
                                        <span>Keskipalsta (Pääalue B)</span>
                                        <button type="button" class="btn btn-xs btn-primary" onclick="OlliAdmin.openBlockPicker('main')">＋ Lisää lohko</button>
                                    </div>
                                    <div class="builder-col-list" id="colList-main" data-col="main"></div>
                                </div>

                                <div class="builder-col" data-col="right">
                                    <div class="builder-col-header">
                                        <span>Oikea palsta (Alue C)</span>
                                        <button type="button" class="btn btn-xs btn-outline" onclick="OlliAdmin.openBlockPicker('right')">＋ Lisää lohko</button>
                                    </div>
                                    <div class="builder-col-list" id="colList-right" data-col="right"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 3. ARTIKKELIT (POSTS) -->
                <section id="tab-posts" class="admin-tab <?= $activeTab === 'posts' ? 'active' : '' ?>">
                    <div class="split-workspace">
                        <div class="workspace-list panel-card">
                            <div class="panel-header">
                                <h3>Artikkelit</h3>
                                <button type="button" class="btn btn-sm btn-primary" onclick="OlliAdmin.createNewPost()">＋ Uusi artikkeli</button>
                            </div>
                            <div id="postsListContainer" class="item-list"></div>
                        </div>

                        <div class="workspace-editor panel-card">
                            <div class="panel-header">
                                <h3 id="postEditorHeading">Muokkaa artikkelia</h3>
                                <div style="display:flex;gap:0.5rem;">
                                    <button type="button" class="btn btn-sm btn-danger" onclick="OlliAdmin.deleteCurrentPost()">🗑️ Poista</button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="OlliAdmin.saveCurrentPost()">💾 Tallenna artikkeli</button>
                                </div>
                            </div>

                            <div class="form-grid-3">
                                <label>Artikkelin otsikko *
                                    <input type="text" id="postTitle" oninput="OlliAdmin.autoSlugPost()">
                                </label>
                                <label>URL-tunniste (slug)
                                    <input type="text" id="postSlug">
                                </label>
                                <label>Kategoria
                                    <input type="text" id="postCategory" placeholder="esim. Uutiset, Oppaat">
                                </label>
                            </div>

                            <div class="form-grid-4" style="margin-top:0.75rem;">
                                <label>Julkaisupäivä
                                    <input type="date" id="postDate">
                                </label>
                                <label>Kirjoittaja
                                    <input type="text" id="postAuthor" value="Pääkäyttäjä">
                                </label>
                                <label>Arvioitu lukuaika
                                    <input type="text" id="postReadTime" placeholder="esim. 3 min">
                                </label>
                                <label>Tila
                                    <select id="postStatus">
                                        <option value="published">Julkaistu</option>
                                        <option value="draft">Luonnos</option>
                                    </select>
                                </label>
                            </div>

                            <label style="margin-top:0.75rem;">Otsikkokuvan URL (tai valitse mediapankista)
                                <input type="text" id="postImage" placeholder="content/uploads/kuva.jpg tai https://...">
                            </label>

                            <label style="margin-top:0.75rem;">Lyhyt tiivistelmä (Ingressi korttinäkymiin)
                                <textarea id="postExcerpt" rows="2"></textarea>
                            </label>

                            <div class="rich-editor-toolbar" style="margin-top:0.75rem;">
                                <button type="button" onclick="OlliAdmin.insertPostTag('<h2>', '</h2>')">H2</button>
                                <button type="button" onclick="OlliAdmin.insertPostTag('<h3>', '</h3>')">H3</button>
                                <button type="button" onclick="OlliAdmin.insertPostTag('<strong>', '</strong>')"><b>B</b></button>
                                <button type="button" onclick="OlliAdmin.insertPostTag('<em>', '</em>')"><i>I</i></button>
                                <button type="button" onclick="OlliAdmin.insertPostTag('<ul>\n  <li>', '</li>\n</ul>')">• Lista</button>
                                <button type="button" onclick="OlliAdmin.insertPostTag('<blockquote>', '</blockquote>')">” Lainaus</button>
                                <button type="button" onclick="OlliAdmin.insertPostTag('<p>', '</p>')">Kappale</button>
                            </div>
                            <label>Artikkelin sisältö (HTML / Teksti)
                                <textarea id="postContent" rows="11" style="font-family:monospace;"></textarea>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- 4. ULKOASU & TEEMA (THEME CUSTOMIZER) -->
                <section id="tab-theme" class="admin-tab <?= $activeTab === 'theme' ? 'active' : '' ?>">
                    <div class="panel-card" style="margin-bottom:1.5rem;">
                        <div class="panel-header">
                            <h3>✨ Valmiit teemapaketit (1 klikkauksen vaihto)</h3>
                        </div>
                        <div class="preset-cards-grid">
                            <?php foreach ($presets as $pKey => $pInfo): ?>
                                <div class="preset-card" onclick="OlliAdmin.applyThemePreset('<?= htmlspecialchars($pKey) ?>')">
                                    <div class="preset-swatches">
                                        <span style="background:<?= htmlspecialchars($pInfo['bg_color']) ?>"></span>
                                        <span style="background:<?= htmlspecialchars($pInfo['surface_color']) ?>"></span>
                                        <span style="background:<?= htmlspecialchars($pInfo['primary_color']) ?>"></span>
                                        <span style="background:<?= htmlspecialchars($pInfo['accent_color']) ?>"></span>
                                    </div>
                                    <strong><?= htmlspecialchars($pInfo['name']) ?></strong>
                                    <small><?= htmlspecialchars($pInfo['description']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="panel-card">
                        <div class="panel-header">
                            <h3>🎨 Tarkka ulkoasun ja mittojen säätö</h3>
                            <button type="button" class="btn btn-primary" onclick="OlliAdmin.saveThemeConfig()">💾 Tallenna ulkoasu</button>
                        </div>

                        <div class="form-grid-4">
                            <label>Pääkorostusväri (Primary)
                                <input type="color" id="themePrimary" value="<?= htmlspecialchars($themeConfig['primary_color'] ?? '#4f46e5') ?>">
                            </label>
                            <label>Sivukorostusväri (Accent)
                                <input type="color" id="themeAccent" value="<?= htmlspecialchars($themeConfig['accent_color'] ?? '#0ea5e9') ?>">
                            </label>
                            <label>Sivuston taustaväri
                                <input type="color" id="themeBg" value="<?= htmlspecialchars($themeConfig['bg_color'] ?? '#f4f6fb') ?>">
                            </label>
                            <label>Korttien pohjaväri (Surface)
                                <input type="color" id="themeSurface" value="<?= htmlspecialchars($themeConfig['surface_color'] ?? '#ffffff') ?>">
                            </label>
                        </div>

                        <div class="form-grid-4" style="margin-top:1rem;">
                            <label>Tekstin pääväri
                                <input type="color" id="themeText" value="<?= htmlspecialchars($themeConfig['text_color'] ?? '#0f172a') ?>">
                            </label>
                            <label>Väritila
                                <select id="themeMode">
                                    <option value="light" <?= ($themeConfig['mode'] ?? '') === 'light' ? 'selected' : '' ?>>Vaalea (Light)</option>
                                    <option value="dark" <?= ($themeConfig['mode'] ?? '') === 'dark' ? 'selected' : '' ?>>Tumma (Dark)</option>
                                </select>
                            </label>
                            <label>Otsikkofontti
                                <select id="themeFontHeading">
                                    <?php foreach (['Plus Jakarta Sans', 'Space Grotesk', 'Outfit', 'Fraunces', 'Inter'] as $f): ?>
                                        <option value="<?= $f ?>" <?= ($themeConfig['font_heading'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Leipätekstifontti
                                <select id="themeFontBody">
                                    <?php foreach (['Inter', 'Plus Jakarta Sans', 'Outfit'] as $fb): ?>
                                        <option value="<?= $fb ?>" <?= ($themeConfig['font_body'] ?? '') === $fb ? 'selected' : '' ?>><?= $fb ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="form-grid-4" style="margin-top:1rem;">
                            <label>Korttien kulmapyöristys (px)
                                <input type="number" id="themeRadius" value="<?= (int)($themeConfig['border_radius'] ?? 18) ?>" min="0" max="36">
                            </label>
                            <label>Palstojen ja lohkojen väli (px)
                                <input type="number" id="themeGap" value="<?= (int)($themeConfig['grid_gap'] ?? 24) ?>" min="8" max="48">
                            </label>
                            <label>Sivuston enimmäisleveys (px)
                                <input type="number" id="themeWidth" value="<?= (int)($themeConfig['container_width'] ?? 1380) ?>" min="1000" max="1800" step="20">
                            </label>
                            <label>Korttien tyyli
                                <select id="themeCardStyle">
                                    <option value="elevated" <?= ($themeConfig['card_style'] ?? '') === 'elevated' ? 'selected' : '' ?>>Pehmeä varjo (Elevated Bento)</option>
                                    <option value="glass" <?= ($themeConfig['card_style'] ?? '') === 'glass' ? 'selected' : '' ?>>Lasimainen (Glassmorphism)</option>
                                    <option value="bordered" <?= ($themeConfig['card_style'] ?? '') === 'bordered' ? 'selected' : '' ?>>Selkeä reunaviiva (Moodle Clean)</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- 5. MEDIAPANKKI (MEDIA) -->
                <section id="tab-media" class="admin-tab <?= $activeTab === 'media' ? 'active' : '' ?>">
                    <div class="panel-card">
                        <div class="panel-header">
                            <h3>🖼️ Mediapankki & Tiedostot</h3>
                            <label class="btn btn-primary" style="cursor:pointer;">
                                ⬆️ Lataa tiedosto koneelta
                                <input type="file" id="mediaUploadInput" style="display:none;" onchange="OlliAdmin.uploadMediaFile(this)">
                            </label>
                        </div>
                        <p class="text-muted">Voit ladata kuvia (JPG, PNG, WEBP, SVG) ja liitetiedostoja (PDF, ZIP, DOCX) ja kopioida niiden osoitteen lohkoihin tai artikkeleihin.</p>
                        <div id="mediaGridContainer" class="media-grid"></div>
                    </div>
                </section>

                <!-- 6. SAAPUNEET VIESTIT (MESSAGES) -->
                <section id="tab-messages" class="admin-tab <?= $activeTab === 'messages' ? 'active' : '' ?>">
                    <div class="panel-card">
                        <div class="panel-header">
                            <h3>✉️ Yhteydenottolomakkeen viestit</h3>
                        </div>
                        <div id="messagesListContainer" class="messages-list"></div>
                    </div>
                </section>

                <!-- 7. VALIKOT, GLOBAALIT LOHKOT & ASETUKSET -->
                <section id="tab-settings" class="admin-tab <?= $activeTab === 'settings' ? 'active' : '' ?>">
                    <div class="dashboard-grid">
                        <div class="panel-card">
                            <div class="panel-header">
                                <h3>⚙️ Sivuston perustiedot & Päävalikko</h3>
                                <button type="button" class="btn btn-primary" onclick="OlliAdmin.saveSiteSettings()">💾 Tallenna asetukset</button>
                            </div>
                            <label>Sivuston nimi
                                <input type="text" id="cfgSiteName" value="<?= htmlspecialchars($siteConfig['site_name'] ?? '') ?>">
                            </label>
                            <label style="margin-top:0.75rem;">Iskulause (Tagline)
                                <input type="text" id="cfgTagline" value="<?= htmlspecialchars($siteConfig['tagline'] ?? '') ?>">
                            </label>
                            <label style="margin-top:0.75rem;">Etusivuna näytettävä sivu (slug)
                                <input type="text" id="cfgHomepage" value="<?= htmlspecialchars($siteConfig['homepage_slug'] ?? 'etusivu') ?>">
                            </label>
                            <label style="margin-top:0.75rem;">Alatunnisteen (Footer) teksti
                                <input type="text" id="cfgFooter" value="<?= htmlspecialchars($siteConfig['footer_text'] ?? '') ?>">
                            </label>

                            <div style="margin-top:1.25rem;">
                                <div class="section-label-row">
                                    <strong>🧭 Päävalikon linkit</strong>
                                    <button type="button" class="btn btn-xs btn-outline" onclick="OlliAdmin.addMenuRow()">＋ Lisää valikkorivi</button>
                                </div>
                                <div id="menuRowsContainer" style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.5rem;"></div>
                            </div>
                        </div>

                        <div class="panel-card">
                            <div class="panel-header">
                                <h3>🌐 Globaalit sivupalstalohkot & Tietoturva</h3>
                            </div>
                            <p class="text-muted" style="font-size:0.85rem;">Globaalit lohkot näkyvät automaattisesti kaikilla sivuilla, joissa vasen tai oikea globaali palsta on kytketty päälle.</p>
                            <label>Globaalit lohkot (JSON-muoto: left & right)
                                <textarea id="cfgGlobalBlocks" rows="10" style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars(json_encode($siteConfig['global_blocks'] ?? ['left' => [], 'right' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                            </label>

                            <hr style="border:0;border-top:1px solid #e2e8f0;margin:1.5rem 0;">

                            <h4>🔑 Vaihda ylläpitäjän salasana</h4>
                            <div style="display:flex;gap:0.5rem;align-items:flex-end;">
                                <label style="flex:1;">Uusi salasana (väh. 6 merkkiä)
                                    <input type="password" id="newAdminPassword" placeholder="Syötä uusi salasana">
                                </label>
                                <button type="button" class="btn btn-outline" onclick="OlliAdmin.changePassword()">Päivitä salasana</button>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </div>

    <!-- MODAALI: LOHKON VALINTA JA VISUAALINEN MUOKKAUS HALLINTAPANEELISSA -->
    <div id="adminBlockModal" class="admin-modal-backdrop" style="display:none;">
        <div class="admin-modal">
            <div class="admin-modal-header">
                <h3 id="adminModalTitle">Muokkaa sisältölohkoa</h3>
                <button type="button" class="modal-close" onclick="OlliAdmin.closeBlockModal()">✕</button>
            </div>
            <div class="admin-modal-body" id="adminModalBody"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        window.OLLI_ADMIN_STATE = {
            csrfToken: <?= json_encode($auth->getCsrfToken()) ?>,
            pages: <?= json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            posts: <?= json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            media: <?= json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            messages: <?= json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            siteConfig: <?= json_encode($siteConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            layouts: <?= json_encode($layouts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            blockCatalog: <?= json_encode($blockCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="assets/admin.js"></script>
<?php endif; ?>

</body>
</html>
