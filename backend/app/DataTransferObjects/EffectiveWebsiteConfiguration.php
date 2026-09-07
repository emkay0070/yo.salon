<?php

namespace App\DataTransferObjects;

class EffectiveWebsiteConfiguration
{
    public function __construct(
        public readonly array $salon,
        public readonly array $brand,
        public readonly array $theme,
        public readonly array $sections,
        public readonly array $navigation,
    ) {}

    /**
     * Create from resolver output
     */
    public static function fromArray(array $data): self
    {
        return new self(
            salon: $data['salon'],
            brand: $data['brand'],
            theme: $data['theme'],
            sections: $data['sections'],
            navigation: $data['navigation'],
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'salon' => $this->salon,
            'brand' => $this->brand,
            'theme' => $this->theme,
            'sections' => $this->sections,
            'navigation' => $this->navigation,
        ];
    }

    /**
     * Get final colors (merged theme defaults with custom brand colors)
     */
    public function getFinalColors(): array
    {
        $themeDefaults = $this->getThemeDefaultColors();
        
        return array_merge($themeDefaults, [
            'primary' => $this->brand['primary_color'],
            'secondary' => $this->brand['secondary_color'],
            'accent' => $this->brand['accent_color'],
            'text' => '#FFFFFF',
        ]);
    }

    /**
     * Get default colors from theme family
     */
    protected function getThemeDefaultColors(): array
    {
        $themeFamily = $this->theme['family'];
        $themeConfig = config('experience_families.' . $themeFamily, config('experience_families.luxury_noir'));
        
        return $themeConfig['default_colors'] ?? [
            'background' => '#0A0A0A',
            'surface' => '#1A1A1A',
        ];
    }

    /**
     * Check if a section is enabled
     */
    public function isSectionEnabled(string $section): bool
    {
        return in_array($section, $this->sections['enabled']);
    }

    /**
     * Get section position in order
     */
    public function getSectionPosition(string $section): ?int
    {
        $position = array_search($section, $this->sections['order']);
        return $position !== false ? $position : null;
    }
}
