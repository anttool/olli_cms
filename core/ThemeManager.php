<?php
declare(strict_types=1);

namespace OlliCMS;

class ThemeManager
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public static function getPresets(): array
    {
        return [
            'nordic-bento' => [
                'name' => 'Nordic Bento (Oletus)',
                'description' => 'Raikas, ilmava ja moderni pohjoismainen korttiulkoasu.',
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
            ],
            'midnight-glass' => [
                'name' => 'Midnight Glass (Tumma)',
                'description' => 'Tyylikäs tumma teema lasimaisilla korteilla ja neon-korostuksilla.',
                'mode' => 'dark',
                'primary_color' => '#818cf8',
                'accent_color' => '#38bdf8',
                'bg_color' => '#0b0f19',
                'surface_color' => '#151c2c',
                'text_color' => '#f1f5f9',
                'font_heading' => 'Space Grotesk',
                'font_body' => 'Inter',
                'border_radius' => '20',
                'grid_gap' => '24',
                'container_width' => '1400',
                'card_style' => 'glass',
                'header_style' => 'glass'
            ],
            'campus-hub' => [
                'name' => 'Campus Hub (Moodle Modern)',
                'description' => 'Rauhallinen vihreä/luonnonvalkoinen oppimis- ja tietoportaaliteema.',
                'mode' => 'light',
                'primary_color' => '#059669',
                'accent_color' => '#f59e0b',
                'bg_color' => '#f0fdf4',
                'surface_color' => '#ffffff',
                'text_color' => '#1e293b',
                'font_heading' => 'Outfit',
                'font_body' => 'Plus Jakarta Sans',
                'border_radius' => '14',
                'grid_gap' => '20',
                'container_width' => '1440',
                'card_style' => 'bordered',
                'header_style' => 'solid'
            ],
            'editorial-warm' => [
                'name' => 'Editorial Warm (Lehtimäinen)',
                'description' => 'Lämmin sävymaailma ja selkeä kontrastinen reunaviivatyyli.',
                'mode' => 'light',
                'primary_color' => '#ea580c',
                'accent_color' => '#db2777',
                'bg_color' => '#faf8f5',
                'surface_color' => '#ffffff',
                'text_color' => '#1c1917',
                'font_heading' => 'Fraunces',
                'font_body' => 'Inter',
                'border_radius' => '12',
                'grid_gap' => '24',
                'container_width' => '1320',
                'card_style' => 'bordered',
                'header_style' => 'minimal'
            ]
        ];
    }

    public static function getLayouts(): array
    {
        return [
            '1col-full' => [
                'name' => '1 palsta – Täysleveä (100 %)',
                'desc' => 'Vain pääpalsta koko sivun leveydeltä.',
                'cols' => ['main'],
                'grid_css' => '1fr',
                'preview' => ['100%']
            ],
            '2col-left' => [
                'name' => '2 palstaa – Vasen sivupalsta (30 % / 70 %)',
                'desc' => 'Kapea vasen palsta ja leveä pääpalsta.',
                'cols' => ['left', 'main'],
                'grid_css' => 'minmax(260px, 3fr) minmax(0, 7fr)',
                'preview' => ['30%', '70%']
            ],
            '2col-right' => [
                'name' => '2 palstaa – Oikea sivupalsta (70 % / 30 %)',
                'desc' => 'Leveä pääpalsta ja kapea oikea sivupalsta.',
                'cols' => ['main', 'right'],
                'grid_css' => 'minmax(0, 7fr) minmax(260px, 3fr)',
                'preview' => ['70%', '30%']
            ],
            '2col-equal' => [
                'name' => '2 palstaa – Tasajako (50 % / 50 %)',
                'desc' => 'Kaksi yhtä leveää palstaa rinnakkain.',
                'cols' => ['left', 'main'],
                'grid_css' => 'repeat(2, minmax(0, 1fr))',
                'preview' => ['50%', '50%']
            ],
            '3col-moodle' => [
                'name' => '3 palstaa – Moodle / Portaali (25 % / 50 % / 25 %)',
                'desc' => 'Klassinen oppimisympäristö- ja portaalirakenne kolmella palstalla.',
                'cols' => ['left', 'main', 'right'],
                'grid_css' => 'minmax(240px, 3fr) minmax(0, 6fr) minmax(240px, 3fr)',
                'preview' => ['25%', '50%', '25%']
            ],
            '3col-bento' => [
                'name' => '3 palstaa – Bento-tasajako (33 % / 33 % / 33 %)',
                'desc' => 'Kolme yhtä leveää korttipalstaa.',
                'cols' => ['left', 'main', 'right'],
                'grid_css' => 'repeat(3, minmax(0, 1fr))',
                'preview' => ['33%', '33%', '33%']
            ],
        ];
    }

    private function sanitizeColor(string $val, string $fallback): string
    {
        $val = trim($val);
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $val)) {
            return $val;
        }
        return $fallback;
    }

    private function sanitizeFont(string $val, string $fallback): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9 \-]/', '', $val) ?? '';
        return $clean !== '' ? $clean : $fallback;
    }

    public function getCssVariables(): string
    {
        $theme = $this->storage->getThemeConfig();
        $mode = ($theme['mode'] ?? 'light') === 'dark' ? 'dark' : 'light';
        $primary = $this->sanitizeColor((string)($theme['primary_color'] ?? ''), '#4f46e5');
        $accent = $this->sanitizeColor((string)($theme['accent_color'] ?? ''), '#0ea5e9');
        $bg = $this->sanitizeColor((string)($theme['bg_color'] ?? ''), $mode === 'dark' ? '#0b0f19' : '#f4f6fb');
        $surface = $this->sanitizeColor((string)($theme['surface_color'] ?? ''), $mode === 'dark' ? '#151c2c' : '#ffffff');
        $text = $this->sanitizeColor((string)($theme['text_color'] ?? ''), $mode === 'dark' ? '#f1f5f9' : '#0f172a');
        $muted = $mode === 'dark' ? '#94a3b8' : '#475569';
        $border = $mode === 'dark' ? 'rgba(255, 255, 255, 0.1)' : 'rgba(15, 23, 42, 0.09)';

        $fontHeading = $this->sanitizeFont((string)($theme['font_heading'] ?? ''), 'Plus Jakarta Sans');
        $fontBody = $this->sanitizeFont((string)($theme['font_body'] ?? ''), 'Inter');
        $radius = max(0, min(48, (int)($theme['border_radius'] ?? 18)));
        $gap = max(8, min(64, (int)($theme['grid_gap'] ?? 24)));
        $width = max(900, min(2000, (int)($theme['container_width'] ?? 1380)));

        return ":root {\n"
            . "  --primary: {$primary};\n"
            . "  --accent: {$accent};\n"
            . "  --bg: {$bg};\n"
            . "  --surface: {$surface};\n"
            . "  --text: {$text};\n"
            . "  --text-muted: {$muted};\n"
            . "  --border: {$border};\n"
            . "  --font-heading: '{$fontHeading}', system-ui, sans-serif;\n"
            . "  --font-body: '{$fontBody}', system-ui, sans-serif;\n"
            . "  --radius: {$radius}px;\n"
            . "  --grid-gap: {$gap}px;\n"
            . "  --container-width: {$width}px;\n"
            . "}";
    }
}
