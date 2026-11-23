<?php
declare(strict_types=1);

namespace OlliCMS;

class Storage
{
    private string $contentDir;

    public function __construct(string $baseDir)
    {
        $this->contentDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . 'content';
        $this->ensureDirectories();
        $this->ensureDefaultData();
    }

    public function getContentDir(): string
    {
        return $this->contentDir;
    }

    private function ensureDirectories(): void
    {
        $dirs = [
            $this->contentDir,
            $this->contentDir . '/config',
            $this->contentDir . '/pages',
            $this->contentDir . '/posts',
            $this->contentDir . '/messages',
            $this->contentDir . '/uploads',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Suojataan arkaluonteiset kansiot suorilta selainpyynnöiltä Apache-palvelimilla
        $protectedDirs = ['config', 'pages', 'posts', 'messages'];
        foreach ($protectedDirs as $sub) {
            $htaccess = $this->contentDir . '/' . $sub . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
            }
        }

        // Estetään skriptien suoritus uploads-kansiossa
        $uploadsHtaccess = $this->contentDir . '/uploads/.htaccess';
        if (!file_exists($uploadsHtaccess)) {
            $rules = "Options -Indexes -ExecCGI\n"
                . "<FilesMatch \"\\.(php|php[0-9]|phtml|phar|pl|py|cgi|sh|htaccess|html|htm|svg|js)$\">\n"
                . "    <IfModule mod_authz_core.c>\n"
                . "        Require all denied\n"
                . "    </IfModule>\n"
                . "    <IfModule !mod_authz_core.c>\n"
                . "        Order allow,deny\n"
                . "        Deny from all\n"
                . "    </IfModule>\n"
                . "</FilesMatch>\n";
            file_put_contents($uploadsHtaccess, $rules);
        }
    }

    public function readJson(string $relativePath, array $default = []): array
    {
        $fullPath = $this->contentDir . '/' . ltrim($relativePath, '/\\');
        if (!file_exists($fullPath)) {
            return $default;
        }
        $raw = file_get_contents($fullPath);
        if ($raw === false) {
            return $default;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }

    public function writeJson(string $relativePath, array $data): bool
    {
        $fullPath = $this->contentDir . '/' . ltrim($relativePath, '/\\');
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return file_put_contents($fullPath, $json, LOCK_EX) !== false;
    }

    public function deleteFile(string $relativePath): bool
    {
        $fullPath = $this->contentDir . '/' . ltrim($relativePath, '/\\');
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    public static function slugify(string $text): string
    {
        $map = [
            'ä' => 'a', 'Ä' => 'a',
            'ö' => 'o', 'Ö' => 'o',
            'å' => 'a', 'Å' => 'a',
            'ü' => 'u', 'Ü' => 'u',
        ];
        $text = strtr($text, $map);
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9-]+/', '-', $text) ?? '';
        $text = trim($text, '-');
        return $text !== '' ? $text : 'sivu-' . substr(md5((string)microtime(true)), 0, 6);
    }

    // --- ASETUKSET & GLOBAALIT TIEDOT ---

    public function getSiteConfig(): array
    {
        return $this->readJson('config/site.json');
    }

    public function saveSiteConfig(array $config): bool
    {
        return $this->writeJson('config/site.json', $config);
    }

    public function getThemeConfig(): array
    {
        return $this->readJson('config/theme.json');
    }

    public function saveThemeConfig(array $theme): bool
    {
        return $this->writeJson('config/theme.json', $theme);
    }

    // --- SIVUT (PAGES) ---

    public function listPages(bool $publishedOnly = false): array
    {
        $files = glob($this->contentDir . '/pages/*.json') ?: [];
        $pages = [];
        foreach ($files as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                if ($publishedOnly && ($data['status'] ?? 'published') !== 'published') {
                    continue;
                }
                $pages[] = $data;
            }
        }
        usort($pages, fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));
        return $pages;
    }

    public function getPage(string $slug): ?array
    {
        $safeSlug = preg_replace('/[^a-z0-9-_]/i', '', $slug);
        $data = $this->readJson("pages/{$safeSlug}.json", []);
        return !empty($data) ? $data : null;
    }

    public function savePage(array $page): bool
    {
        $slug = self::slugify($page['slug'] ?? $page['title'] ?? 'sivu');
        $page['slug'] = $slug;
        $page['updated_at'] = date('Y-m-d H:i:s');
        if (empty($page['created_at'])) {
            $page['created_at'] = $page['updated_at'];
        }
        return $this->writeJson("pages/{$slug}.json", $page);
    }

    public function deletePage(string $slug): bool
    {
        $safeSlug = preg_replace('/[^a-z0-9-_]/i', '', $slug);
        return $this->deleteFile("pages/{$safeSlug}.json");
    }

    // --- ARTIKKELIT (POSTS) ---

    public function listPosts(bool $publishedOnly = false, ?string $category = null, int $limit = 0): array
    {
        $files = glob($this->contentDir . '/posts/*.json') ?: [];
        $posts = [];
        foreach ($files as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            if ($publishedOnly && ($data['status'] ?? 'published') !== 'published') {
                continue;
            }
            if ($category && $category !== 'all' && strcasecmp($data['category'] ?? '', $category) !== 0) {
                continue;
            }
            $posts[] = $data;
        }
        usort($posts, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        if ($limit > 0) {
            return array_slice($posts, 0, $limit);
        }
        return $posts;
    }

    public function getPost(string $slug): ?array
    {
        $safeSlug = preg_replace('/[^a-z0-9-_]/i', '', $slug);
        $data = $this->readJson("posts/{$safeSlug}.json", []);
        return !empty($data) ? $data : null;
    }

    public function savePost(array $post): bool
    {
        $slug = self::slugify($post['slug'] ?? $post['title'] ?? 'artikkeli');
        $post['slug'] = $slug;
        $post['updated_at'] = date('Y-m-d H:i:s');
        if (empty($post['date'])) {
            $post['date'] = date('Y-m-d');
        }
        return $this->writeJson("posts/{$slug}.json", $post);
    }

    public function deletePost(string $slug): bool
    {
        $safeSlug = preg_replace('/[^a-z0-9-_]/i', '', $slug);
        return $this->deleteFile("posts/{$safeSlug}.json");
    }

    // --- MEDIAPANKKI (UPLOADS) ---

    public function listMedia(): array
    {
        $dir = $this->contentDir . '/uploads';
        if (!is_dir($dir)) {
            return [];
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..', '.htaccess']);
        $media = [];
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_file($path)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
                $media[] = [
                    'filename' => $file,
                    'url' => 'content/uploads/' . rawurlencode($file),
                    'size' => filesize($path) ?: 0,
                    'size_human' => $this->formatBytes((int)filesize($path)),
                    'is_image' => $isImage,
                    'ext' => $ext,
                    'modified' => date('Y-m-d H:i', (int)filemtime($path)),
                ];
            }
        }
        usort($media, fn($a, $b) => strcmp($b['modified'], $a['modified']));
        return $media;
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' Mt';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' Kt';
        }
        return $bytes . ' t';
    }

    // --- YHTEYDENOTTOLOMAKKEEN VIESTIT ---

    public function listMessages(): array
    {
        $files = glob($this->contentDir . '/messages/*.json') ?: [];
        $messages = [];
        foreach ($files as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) {
                $messages[] = $data;
            }
        }
        usort($messages, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $messages;
    }

    public function saveMessage(array $msg): bool
    {
        $id = 'msg-' . date('Ymd-His') . '-' . substr(md5((string)mt_rand()), 0, 5);
        $msg['id'] = $id;
        $msg['created_at'] = date('Y-m-d H:i:s');
        $msg['read'] = false;
        return $this->writeJson("messages/{$id}.json", $msg);
    }

    public function deleteMessage(string $id): bool
    {
        $safeId = preg_replace('/[^a-z0-9-_]/i', '', $id);
        return $this->deleteFile("messages/{$safeId}.json");
    }

    // --- OLETUSSISÄLLÖN ALUSTUS ---

    private function ensureDefaultData(): void
    {
        // 1. Käyttäjät (oletustunnus: admin / admin123)
        if (!file_exists($this->contentDir . '/config/users.json')) {
            $this->writeJson('config/users.json', [
                'users' => [
                    [
                        'username' => 'admin',
                        'display_name' => 'Pääkäyttäjä',
                        'role' => 'admin',
                        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                    ]
                ]
            ]);
        }

        // 2. Sivuston asetukset ja globaalit sivupalstalohkot
        if (!file_exists($this->contentDir . '/config/site.json')) {
            $this->writeJson('config/site.json', [
                'site_name' => 'Olli CMS',
                'tagline' => 'Moderni palsta- ja lohkopohjainen julkaisujärjestelmä',
                'footer_text' => '© ' . date('Y') . ' Olli CMS — Tietokannaton, huippunopea ja helposti siirrettävä.',
                'homepage_slug' => 'etusivu',
                'show_edit_toggle' => true,
                'menu' => [
                    ['label' => 'Etusivu', 'url' => '?page=etusivu', 'icon' => '🏠'],
                    ['label' => 'Oppimisympäristö & Palstat', 'url' => '?page=moodle-esimerkki', 'icon' => '📐'],
                    ['label' => 'Artikkelit', 'url' => '?view=posts', 'icon' => '📰'],
                    ['label' => 'Ota yhteyttä', 'url' => '?page=yhteystiedot', 'icon' => '✉️'],
                ],
                'global_blocks' => [
                    'left' => [
                        [
                            'id' => 'glob-nav-1',
                            'type' => 'links',
                            'title' => 'Pikavalikko',
                            'badge' => 'Globaali lohko',
                            'style' => 'surface',
                            'items' => [
                                ['label' => 'Etusivun näkymä', 'url' => '?page=etusivu', 'icon' => '✨'],
                                ['label' => '3-palstainen Moodle-sivu', 'url' => '?page=moodle-esimerkki', 'icon' => '📚'],
                                ['label' => 'Kaikki artikkelit', 'url' => '?view=posts', 'icon' => '🗂️'],
                                ['label' => 'Hallintapaneeli', 'url' => 'admin/', 'icon' => '⚙️'],
                            ]
                        ]
                    ],
                    'right' => [
                        [
                            'id' => 'glob-info-1',
                            'type' => 'callout',
                            'title' => 'Tiedote käyttäjille',
                            'badge' => 'Tärkeä',
                            'style' => 'accent',
                            'icon' => '💡',
                            'content' => 'Tämä lohko on määritetty **globaaliksi**, joten se näkyy automaattisesti sivupalstassa kaikilla sivuilla, joissa oikea palsta on käytössä.',
                            'button_text' => 'Muokkaa hallintapaneelissa',
                            'button_url' => 'admin/'
                        ]
                    ]
                ]
            ]);
        }

        // 3. Teeman asetukset
        if (!file_exists($this->contentDir . '/config/theme.json')) {
            $this->writeJson('config/theme.json', [
                'preset' => 'nordic-bento',
                'mode' => 'light',
                'primary_color' => '#4f46e5',
                'accent_color' => '#0ea5e9',
                'bg_color' => '#f4f6fb',
                'surface_color' => '#ffffff',
                'text_color' => '#0f172a',
                'font_heading' => 'Plus Jakarta Sans',
                'font_body' => 'Inter',
                'border_radius' => '18',
                'grid_gap' => '24',
                'container_width' => '1380',
                'card_style' => 'elevated',
                'header_style' => 'glass'
            ]);
        }

        // 4. Oletussivut
        if (empty(glob($this->contentDir . '/pages/*.json'))) {
            // Sivu 1: Etusivu (3 palstaa: 25% / 50% / 25% - Moodle/Bento-yhdistelmä)
            $this->savePage([
                'title' => 'Tervetuloa uuden sukupolven sivustolle',
                'slug' => 'etusivu',
                'subtitle' => 'Unohda kankeat WordPress-muotit. Rakenna sivusi palstoista ja älykkäistä sisältölohkoista.',
                'status' => 'published',
                'order' => 1,
                'layout' => '3col-moodle', // 25% - 50% - 25%
                'show_global_left' => true,
                'show_global_right' => true,
                'columns' => [
                    'left' => [
                        [
                            'id' => 'blk-stats-1',
                            'type' => 'stats',
                            'title' => 'Järjestelmän suorituskyky',
                            'style' => 'surface',
                            'items' => [
                                ['value' => '0 kpl', 'label' => 'Tietokantakyselyitä'],
                                ['value' => '< 15 ms', 'label' => 'Palvelimen vasteaika'],
                                ['value' => '100 %', 'label' => 'Siirrettävyys (Copy & Paste)']
                            ]
                        ]
                    ],
                    'main' => [
                        [
                            'id' => 'blk-hero-1',
                            'type' => 'hero',
                            'badge' => 'Olli CMS 1.0 • Flat-File Engine',
                            'title' => 'Hallitse palstoja ja sisältölohkoja vaivattomasti',
                            'content' => 'Valitse sivulle haluamasi palstoitus (1–3 palstaa eri leveyksillä) ja lisää palstoihin sisältöelementtejä aivan kuten Moodlessa – mutta modernilla Bento Grid -ulkoasulla.',
                            'button_text' => 'Avaa sivunrakentaja',
                            'button_url' => 'admin/',
                            'secondary_text' => 'Tutustu 3-palstaiseen esimerkkiin',
                            'secondary_url' => '?page=moodle-esimerkki',
                            'style' => 'gradient'
                        ],
                        [
                            'id' => 'blk-rich-1',
                            'type' => 'richtext',
                            'title' => 'Miksi tämä järjestelmä on erilainen?',
                            'style' => 'surface',
                            'content' => "<p>Perinteiset julkaisujärjestelmät pakottavat sinut yhteen pitkään tekstikenttään tai raskaisiin lisäosiin. <strong>Olli CMS</strong> jakaa jokaisen sivun selkeisiin palstoihin (alueisiin), joihin voit poimia valmiita toiminnallisia lohkoja:</p><ul><li><strong>Ei tietokantaa:</strong> Kaikki sisältö tallennetaan suoraan selkeisiin JSON-tiedostoihin <code>content/</code>-kansioon.</li><li><strong>Moodle-tyylinen muokkaustila:</strong> Voit kytkeä muokkaustilan päälle suoraan sivuston yläpalkista, lisätä uusia lohkoja mihin tahansa palstaan ja järjestää niitä raahaamalla.</li><li><strong>Täydellinen siirrettävyys:</strong> Kopioi kansio omalta koneelta webhotelliin ja sivusto toimii välittömästi.</li></ul>"
                        ],
                        [
                            'id' => 'blk-feed-1',
                            'type' => 'post_feed',
                            'title' => 'Tuoreimmat artikkelit ja tiedotteet',
                            'style' => 'surface',
                            'category' => 'all',
                            'limit' => 3,
                            'layout_mode' => 'cards'
                        ]
                    ],
                    'right' => [
                        [
                            'id' => 'blk-acc-1',
                            'type' => 'accordion',
                            'title' => 'Usein kysytyt kysymykset',
                            'style' => 'surface',
                            'items' => [
                                [
                                    'question' => 'Miten siirrän sivuston webhotelliin?',
                                    'answer' => 'Kopioi koko projektikansio FTP:llä tai lataa varmuuskopio hallintapaneelista. Mitään tietokanta-asennusta ei tarvita.'
                                ],
                                [
                                    'question' => 'Voinko vaihtaa sivun palstoitusta jälkikäteen?',
                                    'answer' => 'Kyllä! Voit vaihtaa esim. 1-palstaisen sivun 2- tai 3-palstaiseksi milloin tahansa ilman että sisältölohkot katoavat.'
                                ],
                                [
                                    'question' => 'Miten muokkaan värejä ja fontteja?',
                                    'answer' => 'Hallintapaneelin Ulkoasu & Teema -välilehdeltä voit vaihtaa valmiita teemapaketteja tai säätää jokaisen värin, pyöristyksen ja fontin itse.'
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            // Sivu 2: Moodle-esimerkkisivu (Oppimis- / kurssiympäristön tyylinen 3 palstan sivu)
            $this->savePage([
                'title' => 'Oppimisympäristö & Palstaesimerkki',
                'slug' => 'moodle-esimerkki',
                'subtitle' => 'Esimerkki Moodlen kaltaisesta kolmipalstaisesta rakenteesta, jossa on haitareita, tiedostolatauksia ja infolaatikoita.',
                'status' => 'published',
                'order' => 2,
                'layout' => '3col-moodle',
                'show_global_left' => true,
                'show_global_right' => false,
                'columns' => [
                    'left' => [
                        [
                            'id' => 'blk-moodle-prog',
                            'type' => 'stats',
                            'title' => 'Kokonaistilanne',
                            'style' => 'surface',
                            'items' => [
                                ['value' => '4 / 4', 'label' => 'Osioita julkaistu'],
                                ['value' => '10 kpl', 'label' => 'Valmiita lohkotyyppejä'],
                                ['value' => 'A+', 'label' => 'Käytettävyysluokitus']
                            ]
                        ],
                        [
                            'id' => 'blk-moodle-files',
                            'type' => 'files',
                            'title' => 'Jaetut materiaalit',
                            'style' => 'surface',
                            'items' => [
                                ['name' => 'Järjestelmäarkkitehtuuri.pdf', 'size' => '1.2 Mt', 'url' => '#'],
                                ['name' => 'Käyttöopas_ylläpitäjälle.pdf', 'size' => '840 Kt', 'url' => '#'],
                                ['name' => 'Teemojen_kustomointi.zip', 'size' => '2.4 Mt', 'url' => '#']
                            ]
                        ]
                    ],
                    'main' => [
                        [
                            'id' => 'blk-moodle-intro',
                            'type' => 'callout',
                            'title' => 'Tervetuloa modulaariseen palstanäkymään!',
                            'badge' => 'Ohje',
                            'style' => 'primary',
                            'icon' => '🎓',
                            'content' => 'Tällä sivulla näet, kuinka vasen palsta, keskipalsta ja oikea palsta toimivat saumattomasti yhdessä. Kytke yläpalkista **"Muokkaustila päälle"** kokeillaksesi lohkojen lisäämistä tai siirtämistä!',
                            'button_text' => '',
                            'button_url' => ''
                        ],
                        [
                            'id' => 'blk-moodle-topics',
                            'type' => 'accordion',
                            'title' => 'Aihekokonaisuudet (Kurssi- / Projektimoduulit)',
                            'style' => 'surface',
                            'items' => [
                                [
                                    'question' => 'Moduuli 1: Palstoituksen valitseminen (1, 2 tai 3 palstaa)',
                                    'answer' => 'Jokaisella sivulla on oma palstoitusvalintansa: Täysleveä (100 %), Vasen sivupalkki (30/70), Oikea sivupalkki (70/30), Tasajako (50/50), Klassinen 3 palstaa (25/50/25) tai Bento 3 palstaa (33/33/33).'
                                ],
                                [
                                    'question' => 'Moduuli 2: Sisältöelementtien lisääminen palstoihin',
                                    'answer' => 'Klikkaa minkä tahansa palstan alareunasta "+ Lisää sisältölohko", valitse haluamasi elementti (esim. Teksti, Nostokortti, Haitari, Kuvagalleria, Tiedostolista tai Lomake) ja täytä kentät.'
                                ],
                                [
                                    'question' => 'Moduuli 3: Raahaa ja pudota (Drag & Drop)',
                                    'answer' => 'Voit tarttua minkä tahansa lohkon yläreunaan ja raahata sen uuteen kohtaan tai jopa kokonaan toiseen palstaan!'
                                ]
                            ]
                        ],
                        [
                            'id' => 'blk-moodle-gallery',
                            'type' => 'gallery',
                            'title' => 'Visuaaliset nostot & Kuvagalleria',
                            'style' => 'surface',
                            'columns' => 2,
                            'items' => [
                                [
                                    'caption' => 'Moderni Bento Grid -korttinäkymä',
                                    'url' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=800&q=80'
                                ],
                                [
                                    'caption' => 'Responsiivinen monipalstainen asettelu',
                                    'url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=800&q=80'
                                ]
                            ]
                        ]
                    ],
                    'right' => [
                        [
                            'id' => 'blk-moodle-events',
                            'type' => 'links',
                            'title' => 'Tulevat tapahtumat & määräajat',
                            'badge' => 'Kalenteri',
                            'style' => 'surface',
                            'items' => [
                                ['label' => '20.11. Sivuston julkaisu', 'url' => '#', 'icon' => '📅'],
                                ['label' => '28.11. Sisällöntuotannon työpaja', 'url' => '#', 'icon' => '🖥️'],
                                ['label' => '05.12. Teemapäivitys 2.0', 'url' => '#', 'icon' => '🎨']
                            ]
                        ],
                        [
                            'id' => 'blk-moodle-code',
                            'type' => 'code',
                            'title' => 'Helppo käyttöönotto',
                            'style' => 'dark',
                            'language' => 'bash',
                            'content' => "# Ei SQL-tietokantaa!\n# Kopioi vain tiedostot webhotelliin:\nscp -r olli_cms/* user@palvelin:/public_html/"
                        ]
                    ]
                ]
            ]);

            // Sivu 3: Yhteystiedot (2 palstaa 50% / 50%)
            $this->savePage([
                'title' => 'Ota yhteyttä',
                'slug' => 'yhteystiedot',
                'subtitle' => 'Tämä sivu käyttää 50 % / 50 % tasapalstoitusta. Vasemmalla yhteystiedot, oikealla toimiva yhteydenottolomake.',
                'status' => 'published',
                'order' => 3,
                'layout' => '2col-equal',
                'show_global_left' => false,
                'show_global_right' => false,
                'columns' => [
                    'left' => [
                        [
                            'id' => 'blk-contact-info',
                            'type' => 'callout',
                            'title' => 'Asiakaspalvelu & Ylläpito',
                            'badge' => 'Avoinna ma–pe 9–17',
                            'style' => 'surface',
                            'icon' => '📍',
                            'content' => "Voit lähettää meille viestin viereisellä lomakkeella. Kaikki lähetetyt viestit tallentuvat suoraan järjestelmän hallintapaneeliin ilman ulkoisia lisäosia.\n\n**Sähköposti:** info@esimerkki.fi\n**Puhelin:** 040 123 4567",
                            'button_text' => '',
                            'button_url' => ''
                        ],
                        [
                            'id' => 'blk-contact-faq',
                            'type' => 'accordion',
                            'title' => 'Usein kysyttyä tuesta',
                            'style' => 'surface',
                            'items' => [
                                [
                                    'question' => 'Mihin lomakkeen viestit tallentuvat?',
                                    'answer' => 'Viestit tallentuvat suojattuun content/messages/ -kansioon ja ovat luettavissa hallintapaneelin Viestit-välilehdellä.'
                                ]
                            ]
                        ]
                    ],
                    'main' => [
                        [
                            'id' => 'blk-contact-form',
                            'type' => 'contact_form',
                            'title' => 'Lähetä meille viesti',
                            'style' => 'surface',
                            'description' => 'Täytä alla olevat kentät, niin palaamme asiaan mahdollisimman pian.',
                            'submit_text' => 'Lähetä viesti'
                        ]
                    ],
                    'right' => []
                ]
            ]);
        }

        // 5. Oletusartikkelit
        if (empty(glob($this->contentDir . '/posts/*.json'))) {
            $this->savePost([
                'title' => 'Näin rakennat modernin sivuston ilman tietokantaa',
                'slug' => 'moderni-sivusto-ilman-tietokantaa',
                'excerpt' => 'Flat-File CMS yhdistää staattisen sivuston nopeuden ja dynaamisen julkaisujärjestelmän helppouden.',
                'category' => 'Oppaat',
                'author' => 'Pääkäyttäjä',
                'date' => date('Y-m-d'),
                'status' => 'published',
                'featured_image' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1000&q=80',
                'read_time' => '3 min',
                'content' => "<p>Tietokannaton sisällönhallintajärjestelmä (Flat-File CMS) tallentaa sivut, artikkelit ja asetukset suoraan tiedostoihin. Tämä poistaa tietokantapalvelimen hitauden, virheet ja tietoturvariskit.</p><h3>Miksi valita palsta- ja lohkoarkkitehtuuri?</h3><p>Kun jokainen sivu koostuu valittavasta palstoituksesta ja itsenäisistä sisältökorteista, sivuston ilme pysyy aina raikkaana, jäsenneltynä ja mobiiliystävällisenä.</p>"
            ]);

            $this->savePost([
                'title' => 'Moodle-henkinen palstoitus tuo ryhtiä sisällöntuotantoon',
                'slug' => 'moodle-henkinen-palstoitus',
                'excerpt' => 'Monipalstainen rakenne mahdollistaa tiedotteiden, pikavalikoiden, tiedostojen ja pääsisällön esittämisen yhdellä silmäyksellä.',
                'category' => 'Muotoilu',
                'author' => 'Pääkäyttäjä',
                'date' => date('Y-m-d', strtotime('-2 days')),
                'status' => 'published',
                'featured_image' => 'https://images.unsplash.com/photo-1507238691740-187a5b1d37b8?auto=format&fit=crop&w=1000&q=80',
                'read_time' => '4 min',
                'content' => "<p>Perinteiset verkkosivut ovat usein yhtä kapeaa tekstipötköä. Kun otamme mallia oppimisympäristöistä kuten Moodlesta ja yhdistämme sen moderniin Bento Grid -muotoiluun, saamme sivuston, josta kävijä löytää kaiken oleellisen välittömästi.</p><p>Voit valita mille tahansa sivulle 1, 2 tai 3 palstaa ja päättää, näytetäänkö sivulla globaalit sivupalkkilohkot.</p>"
            ]);

            $this->savePost([
                'title' => 'Sivuston siirtäminen webhotelliin 30 sekunnissa',
                'slug' => 'sivuston-siirtaminen-webhotelliin',
                'excerpt' => 'Ei SQL-tietokantojen vientiä tai asetustiedostojen metsästystä. Siirrä kansio ja kaikki on valmista.',
                'category' => 'Tekniikka',
                'author' => 'Pääkäyttäjä',
                'date' => date('Y-m-d', strtotime('-5 days')),
                'status' => 'published',
                'featured_image' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?auto=format&fit=crop&w=1000&q=80',
                'read_time' => '2 min',
                'content' => "<p>Koska Olli CMS käyttää suhteellisia polkuja ja tallentaa kaiken datan <code>content/</code>-kansioon, voit kehittää sivuston valmiiksi omalla tietokoneellasi ja kopioida tiedostot sellaisenaan mihin tahansa PHP-tuelliseen webhotelliin.</p>"
            ]);
        }
    }
}
