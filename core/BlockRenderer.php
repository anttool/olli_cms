<?php
declare(strict_types=1);

namespace OlliCMS;

class BlockRenderer
{
    private Storage $storage;
    private bool $editMode;
    private string $csrfToken;

    public function __construct(Storage $storage, bool $editMode = false, string $csrfToken = '')
    {
        $this->storage = $storage;
        $this->editMode = $editMode;
        $this->csrfToken = $csrfToken;
    }

    public static function getBlockCatalog(): array
    {
        return [
            'hero' => [
                'name' => 'Hero-nosto / Banneri',
                'icon' => '🚀',
                'desc' => 'Näyttävä otsikkokortti painikkeilla ja väriteemalla.'
            ],
            'richtext' => [
                'name' => 'Teksti & Artikkelisisältö',
                'icon' => '📝',
                'desc' => 'Muotoiltu tekstisisältö, otsikot, kappaleet ja listat.'
            ],
            'callout' => [
                'name' => 'Huomiolaatikko / Info (Moodle-tyyli)',
                'icon' => '💡',
                'desc' => 'Korostettu tietolaatikko ikonilla, tunnisteella ja linkillä.'
            ],
            'accordion' => [
                'name' => 'Haitari / Kurssimoduulit / UKK',
                'icon' => '📂',
                'desc' => 'Avautuvat ja sulkeutuvat sisältöosiot (kuten Moodlen kurssiosiot).'
            ],
            'post_feed' => [
                'name' => 'Artikkelilistaus (Uutissyöte)',
                'icon' => '📰',
                'desc' => 'Näyttää tuoreimmat artikkelit automaattisesti kortteina tai listana.'
            ],
            'links' => [
                'name' => 'Pikavalikko / Tapahtumalista',
                'icon' => '🔗',
                'desc' => 'Kuvakkeellinen linkkilista sivupalstaan tai portaalinäkymään.'
            ],
            'files' => [
                'name' => 'Tiedostot & Ladattavat materiaalit',
                'icon' => '📎',
                'desc' => 'Selkeä lista ladattavista tiedostoista ja PDF-dokumenteista.'
            ],
            'gallery' => [
                'name' => 'Kuvagalleria / Kuvaruudukko',
                'icon' => '🖼️',
                'desc' => 'Responsiivinen kuvaruudukko kuvateksteillä ja suurennoksella.'
            ],
            'stats' => [
                'name' => 'Mittarit & Avainluvut (Bento Stats)',
                'icon' => '📊',
                'desc' => 'Visuaaliset numerokortit ja tilastonostot.'
            ],
            'contact_form' => [
                'name' => 'Yhteydenottolomake',
                'icon' => '✉️',
                'desc' => 'Valmis lomake, joka tallentaa viestit suoraan hallintapaneeliin.'
            ],
            'embed' => [
                'name' => 'Video / Kartta / Upote',
                'icon' => '🎬',
                'desc' => 'YouTube-video tai muu ulkoinen iframe-upote.'
            ],
            'code' => [
                'name' => 'Koodi- / Komentolaatikko',
                'icon' => '💻',
                'desc' => 'Tyylitelty koodiesimerkki kopiointipainikkeella.'
            ]
        ];
    }

    public function renderBlock(array $block, string $columnKey = 'main', bool $isGlobal = false): string
    {
        $type = $block['type'] ?? 'richtext';
        $style = htmlspecialchars($block['style'] ?? 'surface');
        $id = htmlspecialchars($block['id'] ?? ('blk-' . substr(md5((string)mt_rand()), 0, 6)));
        $title = $block['title'] ?? '';
        $badge = $block['badge'] ?? ($isGlobal ? 'Globaali' : '');

        $editControls = '';
        $editAttrs = '';
        if ($this->editMode && !$isGlobal) {
            $blockJson = htmlspecialchars(json_encode($block, JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8');
            $catalog = self::getBlockCatalog();
            $typeLabel = $catalog[$type]['name'] ?? $type;
            $typeIcon = $catalog[$type]['icon'] ?? '📦';
            $editAttrs = ' data-block-id="' . $id . '" data-column="' . htmlspecialchars($columnKey) . '" data-block-json="' . $blockJson . '"';
            $editControls = '
                <div class="block-edit-toolbar">
                    <span class="block-drag-handle" title="Raahaa siirtääksesi">⠿ ' . $typeIcon . ' ' . htmlspecialchars($typeLabel) . '</span>
                    <div class="block-edit-actions">
                        <button type="button" class="btn-block-action" onclick="OlliInlineEditor.openEditModal(this)" title="Muokkaa lohkoa">✏️ Muokkaa</button>
                        <button type="button" class="btn-block-action btn-block-delete" onclick="OlliInlineEditor.deleteBlock(this)" title="Poista lohko">🗑️</button>
                    </div>
                </div>';
        } elseif ($this->editMode && $isGlobal) {
            $editControls = '
                <div class="block-edit-toolbar is-global">
                    <span>🌐 Globaali sivupalstalohko</span>
                    <a href="admin/?tab=settings" class="btn-block-action">Muokkaa asetuksissa →</a>
                </div>';
        }

        $innerHtml = match ($type) {
            'hero' => $this->renderHero($block),
            'callout' => $this->renderCallout($block),
            'accordion' => $this->renderAccordion($block),
            'post_feed' => $this->renderPostFeed($block),
            'links' => $this->renderLinks($block),
            'files' => $this->renderFiles($block),
            'gallery' => $this->renderGallery($block),
            'stats' => $this->renderStats($block),
            'contact_form' => $this->renderContactForm($block),
            'embed' => $this->renderEmbed($block),
            'code' => $this->renderCode($block),
            default => $this->renderRichText($block),
        };

        $headerHtml = '';
        if ($type !== 'hero' && ($title !== '' || $badge !== '')) {
            $badgeHtml = $badge !== '' ? '<span class="block-badge">' . htmlspecialchars($badge) . '</span>' : '';
            $titleHtml = $title !== '' ? '<h3 class="block-title">' . htmlspecialchars($title) . '</h3>' : '';
            $headerHtml = '<div class="block-header">' . $titleHtml . $badgeHtml . '</div>';
        }

        return '<section class="bento-block block-type-' . htmlspecialchars($type) . ' block-style-' . $style . ($isGlobal ? ' is-global-block' : '') . '" id="' . $id . '"' . $editAttrs . '>'
            . $editControls
            . $headerHtml
            . '<div class="block-body">' . $innerHtml . '</div>'
            . '</section>';
    }

    public static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }
        // Estetään vaaralliset protokollat kuten javascript:, data:, vbscript:
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $url)) {
            return '#';
        }
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeHtml(string $html): string
    {
        // Poistetaan mahdolliset <script>, <iframe>, <object>, <embed> ja on*-tapahtumakäsittelijät
        $clean = preg_replace('/<(script|iframe|object|embed|form|style)\b[^>]*>.*?<\/\1>/is', '', $html) ?? $html;
        $clean = preg_replace('/<(script|iframe|object|embed|meta|link)\b[^>]*\/?>/is', '', $clean) ?? $clean;
        $clean = preg_replace('/\bon[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(href|src)\s*=\s*["\']?\s*(javascript|data|vbscript):[^"\'\s>]*/i', '$1="#"', $clean) ?? $clean;
        return $clean;
    }

    private function renderHero(array $b): string
    {
        $badge = !empty($b['badge']) ? '<span class="hero-badge">' . htmlspecialchars($b['badge']) . '</span>' : '';
        $title = htmlspecialchars($b['title'] ?? '');
        $content = nl2br(htmlspecialchars($b['content'] ?? ''));
        $btn1 = '';
        if (!empty($b['button_text'])) {
            $url = self::sanitizeUrl((string)($b['button_url'] ?? '#'));
            $btn1 = '<a href="' . $url . '" class="btn btn-primary">' . htmlspecialchars($b['button_text']) . '</a>';
        }
        $btn2 = '';
        if (!empty($b['secondary_text'])) {
            $url2 = self::sanitizeUrl((string)($b['secondary_url'] ?? '#'));
            $btn2 = '<a href="' . $url2 . '" class="btn btn-glass">' . htmlspecialchars($b['secondary_text']) . '</a>';
        }
        $actions = ($btn1 || $btn2) ? '<div class="hero-actions">' . $btn1 . $btn2 . '</div>' : '';

        return '<div class="hero-inner">'
            . $badge
            . '<h2 class="hero-heading">' . $title . '</h2>'
            . '<p class="hero-lead">' . $content . '</p>'
            . $actions
            . '</div>';
    }

    private function renderRichText(array $b): string
    {
        $content = (string)($b['content'] ?? '');
        // Jos tekstissä ei ole HTML-tageja, muunnetaan rivinvaihdot kappaleiksi
        if ($content === strip_tags($content)) {
            return '<div class="prose">' . $this->simpleMarkdown($content) . '</div>';
        }
        return '<div class="prose">' . self::sanitizeHtml($content) . '</div>';
    }

    private function renderCallout(array $b): string
    {
        $icon = !empty($b['icon']) ? '<div class="callout-icon">' . htmlspecialchars($b['icon']) . '</div>' : '';
        $text = $this->simpleMarkdown($b['content'] ?? '');
        $btn = '';
        if (!empty($b['button_text'])) {
            $btn = '<div class="callout-action"><a href="' . self::sanitizeUrl((string)($b['button_url'] ?? '#')) . '" class="btn btn-sm btn-outline">'
                . htmlspecialchars($b['button_text']) . ' →</a></div>';
        }
        return '<div class="callout-box">' . $icon . '<div class="callout-content">' . $text . $btn . '</div></div>';
    }

    private function renderAccordion(array $b): string
    {
        $items = $b['items'] ?? [];
        if (empty($items)) {
            return '<p class="text-muted">Ei haitarielementtejä.</p>';
        }
        $html = '<div class="accordion-list">';
        foreach ($items as $idx => $item) {
            $q = htmlspecialchars($item['question'] ?? 'Otsikko');
            $a = $this->simpleMarkdown($item['answer'] ?? '');
            $open = $idx === 0 ? ' open' : '';
            $html .= '<details class="accordion-item"' . $open . '>'
                . '<summary class="accordion-trigger"><span>' . $q . '</span><span class="acc-chevron">⌄</span></summary>'
                . '<div class="accordion-panel">' . $a . '</div>'
                . '</details>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderPostFeed(array $b): string
    {
        $category = $b['category'] ?? 'all';
        $limit = (int)($b['limit'] ?? 3);
        $mode = $b['layout_mode'] ?? 'cards';
        $posts = $this->storage->listPosts(true, $category, $limit);

        if (empty($posts)) {
            return '<p class="text-muted">Ei julkaistuja artikkeleita.</p>';
        }

        $html = '<div class="post-feed-grid mode-' . htmlspecialchars($mode) . '">';
        foreach ($posts as $post) {
            $slug = urlencode($post['slug'] ?? '');
            $title = htmlspecialchars($post['title'] ?? '');
            $excerpt = htmlspecialchars($post['excerpt'] ?? '');
            $cat = htmlspecialchars($post['category'] ?? 'Yleinen');
            $date = htmlspecialchars($post['date'] ?? '');
            $img = !empty($post['featured_image'])
                ? '<a href="?post=' . $slug . '" class="post-card-img"><img src="' . htmlspecialchars($post['featured_image']) . '" alt="' . $title . '" loading="lazy"></a>'
                : '';

            $html .= '<article class="post-feed-card">'
                . $img
                . '<div class="post-card-content">'
                . '<div class="post-meta"><span class="post-cat">' . $cat . '</span><span>•</span><time>' . $date . '</time></div>'
                . '<h4><a href="?post=' . $slug . '">' . $title . '</a></h4>'
                . '<p>' . $excerpt . '</p>'
                . '<a href="?post=' . $slug . '" class="read-more-link">Lue artikkeli →</a>'
                . '</div>'
                . '</article>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderLinks(array $b): string
    {
        $items = $b['items'] ?? [];
        if (empty($items)) {
            return '<p class="text-muted">Ei linkkejä.</p>';
        }
        $html = '<ul class="quick-links-list">';
        foreach ($items as $item) {
            $icon = htmlspecialchars($item['icon'] ?? '🔸');
            $label = htmlspecialchars($item['label'] ?? 'Linkki');
            $url = self::sanitizeUrl((string)($item['url'] ?? '#'));
            $html .= '<li><a href="' . $url . '" class="quick-link-item">'
                . '<span class="ql-icon">' . $icon . '</span>'
                . '<span class="ql-label">' . $label . '</span>'
                . '<span class="ql-arrow">→</span>'
                . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    private function renderFiles(array $b): string
    {
        $items = $b['items'] ?? [];
        if (empty($items)) {
            return '<p class="text-muted">Ei liitetiedostoja.</p>';
        }
        $html = '<div class="file-download-list">';
        foreach ($items as $item) {
            $name = htmlspecialchars($item['name'] ?? 'Tiedosto');
            $size = htmlspecialchars($item['size'] ?? '');
            $url = self::sanitizeUrl((string)($item['url'] ?? '#'));
            $html .= '<a href="' . $url . '" class="file-download-item" download>'
                . '<span class="file-icon">📄</span>'
                . '<span class="file-info"><strong>' . $name . '</strong><small>' . $size . '</small></span>'
                . '<span class="file-dl-badge">Lataa ↓</span>'
                . '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderGallery(array $b): string
    {
        $items = $b['items'] ?? [];
        $cols = max(1, min(4, (int)($b['columns'] ?? 2)));
        if (empty($items)) {
            return '<p class="text-muted">Ei kuvia galleriassa.</p>';
        }
        $html = '<div class="gallery-grid cols-' . $cols . '">';
        foreach ($items as $item) {
            $rawUrl = trim((string)($item['url'] ?? ''));
            if ($rawUrl === '') {
                continue;
            }
            $url = self::sanitizeUrl($rawUrl);
            $caption = htmlspecialchars((string)($item['caption'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<figure class="gallery-item" data-url="' . $url . '" data-caption="' . $caption . '" onclick="OlliSite.openLightbox(this)">'
                . '<img src="' . $url . '" alt="' . $caption . '" loading="lazy">'
                . ($caption !== '' ? '<figcaption>' . $caption . '</figcaption>' : '')
                . '</figure>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderStats(array $b): string
    {
        $items = $b['items'] ?? [];
        if (empty($items)) {
            return '';
        }
        $html = '<div class="stats-grid">';
        foreach ($items as $item) {
            $val = htmlspecialchars($item['value'] ?? '0');
            $label = htmlspecialchars($item['label'] ?? '');
            $html .= '<div class="stat-pill">'
                . '<span class="stat-value">' . $val . '</span>'
                . '<span class="stat-label">' . $label . '</span>'
                . '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderContactForm(array $b): string
    {
        $desc = !empty($b['description']) ? '<p class="form-desc">' . htmlspecialchars($b['description']) . '</p>' : '';
        $submit = htmlspecialchars($b['submit_text'] ?? 'Lähetä viesti');
        return $desc . '
            <form class="olli-contact-form" onsubmit="return OlliSite.submitContactForm(event, this)">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->csrfToken) . '">
                <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true">
                <div class="form-row">
                    <label>Nimesi *
                        <input type="text" name="name" required maxlength="120" placeholder="Matti Meikäläinen">
                    </label>
                    <label>Sähköpostiosoite *
                        <input type="email" name="email" required maxlength="150" placeholder="matti@esimerkki.fi">
                    </label>
                </div>
                <label>Aihe
                    <input type="text" name="subject" maxlength="200" placeholder="Yhteydenoton aihe">
                </label>
                <label>Viesti *
                    <textarea name="message" rows="4" required maxlength="4000" placeholder="Kirjoita viestisi tähän..."></textarea>
                </label>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">' . $submit . '</button>
                    <span class="form-status" aria-live="polite"></span>
                </div>
            </form>';
    }

    private function renderEmbed(array $b): string
    {
        $url = trim((string)($b['embed_url'] ?? ''));
        $caption = htmlspecialchars($b['caption'] ?? '');
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return '<p class="text-muted">Upotteen osoitetta ei ole asetettu tai se ei ole kelvollinen HTTPS-osoite.</p>';
        }
        // Muunnetaan tavallinen YouTube-osoite embed-muotoon
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m)) {
            $url = 'https://www.youtube.com/embed/' . $m[1];
        }
        return '<div class="embed-wrapper"><iframe src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" loading="lazy" sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen></iframe></div>'
            . ($caption !== '' ? '<p class="embed-caption">' . $caption . '</p>' : '');
    }

    private function renderCode(array $b): string
    {
        $code = htmlspecialchars($b['content'] ?? '');
        $lang = htmlspecialchars($b['language'] ?? 'text');
        return '<div class="code-block-wrap">'
            . '<div class="code-block-bar"><span>' . $lang . '</span><button type="button" onclick="OlliSite.copyCode(this)">Kopioi</button></div>'
            . '<pre><code>' . $code . '</code></pre>'
            . '</div>';
    }

    private function simpleMarkdown(string $text): string
    {
        $escaped = htmlspecialchars($text);
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/`(.+?)`/', '<code>$1</code>', $escaped) ?? $escaped;
        $paragraphs = preg_split('/\n\s*\n/', trim($escaped)) ?: [];
        return '<p>' . implode('</p><p>', array_map('nl2br', $paragraphs)) . '</p>';
    }
}
