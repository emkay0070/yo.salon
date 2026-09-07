<?php

namespace Database\Seeders;

use App\Models\CraftTaxonomy;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CraftTaxonomySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxonomy = [
            // Hair
            [
                'name' => 'Hair',
                'slug' => 'hair',
                'sort_order' => 1,
                'children' => [
                    [
                        'name' => 'Hair Cutting',
                        'slug' => 'hair-cutting',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Fades', 'slug' => 'fades', 'sort_order' => 1],
                            ['name' => 'Precision Cutting', 'slug' => 'precision-cutting', 'sort_order' => 2],
                            ['name' => 'Layering', 'slug' => 'layering', 'sort_order' => 3],
                            ['name' => 'Texturizing', 'slug' => 'texturizing', 'sort_order' => 4],
                            ['name' => 'Buzz Cuts', 'slug' => 'buzz-cuts', 'sort_order' => 5],
                        ],
                    ],
                    [
                        'name' => 'Hair Coloring',
                        'slug' => 'hair-coloring',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Balayage', 'slug' => 'balayage', 'sort_order' => 1],
                            ['name' => 'Highlights', 'slug' => 'highlights', 'sort_order' => 2],
                            ['name' => 'Color Correction', 'slug' => 'color-correction', 'sort_order' => 3],
                            ['name' => 'Single Process Color', 'slug' => 'single-process-color', 'sort_order' => 4],
                            ['name' => 'Ombre', 'slug' => 'ombre', 'sort_order' => 5],
                        ],
                    ],
                    [
                        'name' => 'Hair Styling',
                        'slug' => 'hair-styling',
                        'sort_order' => 3,
                        'children' => [
                            ['name' => 'Blowouts', 'slug' => 'blowouts', 'sort_order' => 1],
                            ['name' => 'Updos', 'slug' => 'updos', 'sort_order' => 2],
                            ['name' => 'Braiding', 'slug' => 'braiding', 'sort_order' => 3],
                            ['name' => 'Straightening', 'slug' => 'straightening', 'sort_order' => 4],
                        ],
                    ],
                    [
                        'name' => 'Hair Extensions',
                        'slug' => 'hair-extensions',
                        'sort_order' => 4,
                        'children' => [
                            ['name' => 'Tape-ins', 'slug' => 'tape-ins', 'sort_order' => 1],
                            ['name' => 'Sew-ins', 'slug' => 'sew-ins', 'sort_order' => 2],
                            ['name' => 'Micro-links', 'slug' => 'micro-links', 'sort_order' => 3],
                            ['name' => 'Bonded', 'slug' => 'bonded', 'sort_order' => 4],
                        ],
                    ],
                ],
            ],
            // Nails
            [
                'name' => 'Nails',
                'slug' => 'nails',
                'sort_order' => 2,
                'children' => [
                    [
                        'name' => 'Manicure',
                        'slug' => 'manicure',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Classic Manicure', 'slug' => 'classic-manicure', 'sort_order' => 1],
                            ['name' => 'Gel Manicure', 'slug' => 'gel-manicure', 'sort_order' => 2],
                            ['name' => 'Dip Powder', 'slug' => 'dip-powder', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Pedicure',
                        'slug' => 'pedicure',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Classic Pedicure', 'slug' => 'classic-pedicure', 'sort_order' => 1],
                            ['name' => 'Spa Pedicure', 'slug' => 'spa-pedicure', 'sort_order' => 2],
                            ['name' => 'Gel Pedicure', 'slug' => 'gel-pedicure', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Nail Extensions',
                        'slug' => 'nail-extensions',
                        'sort_order' => 3,
                        'children' => [
                            ['name' => 'Acrylics', 'slug' => 'acrylics', 'sort_order' => 1],
                            ['name' => 'Gel Extensions', 'slug' => 'gel-extensions', 'sort_order' => 2],
                            ['name' => 'Polygel', 'slug' => 'polygel', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Nail Art',
                        'slug' => 'nail-art',
                        'sort_order' => 4,
                        'children' => [
                            ['name' => 'Hand Painted', 'slug' => 'hand-painted', 'sort_order' => 1],
                            ['name' => '3D Art', 'slug' => '3d-art', 'sort_order' => 2],
                            ['name' => 'Chrome', 'slug' => 'chrome', 'sort_order' => 3],
                        ],
                    ],
                ],
            ],
            // Makeup
            [
                'name' => 'Makeup',
                'slug' => 'makeup',
                'sort_order' => 3,
                'children' => [
                    [
                        'name' => 'Bridal Makeup',
                        'slug' => 'bridal-makeup',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Traditional Bridal', 'slug' => 'traditional-bridal', 'sort_order' => 1],
                            ['name' => 'Modern Bridal', 'slug' => 'modern-bridal', 'sort_order' => 2],
                            ['name' => 'Cultural Bridal', 'slug' => 'cultural-bridal', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Editorial Makeup',
                        'slug' => 'editorial-makeup',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'High Fashion', 'slug' => 'high-fashion', 'sort_order' => 1],
                            ['name' => 'Avant-Garde', 'slug' => 'avant-garde', 'sort_order' => 2],
                            ['name' => 'Creative', 'slug' => 'creative', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Event Makeup',
                        'slug' => 'event-makeup',
                        'sort_order' => 3,
                        'children' => [
                            ['name' => 'Natural', 'slug' => 'natural', 'sort_order' => 1],
                            ['name' => 'Glam', 'slug' => 'glam', 'sort_order' => 2],
                            ['name' => 'Smoky Eye', 'slug' => 'smoky-eye', 'sort_order' => 3],
                        ],
                    ],
                ],
            ],
            // Beard & Grooming
            [
                'name' => 'Beard & Grooming',
                'slug' => 'beard-grooming',
                'sort_order' => 4,
                'children' => [
                    [
                        'name' => 'Beard Design',
                        'slug' => 'beard-design',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Beard Shaping', 'slug' => 'beard-shaping', 'sort_order' => 1],
                            ['name' => 'Beard Lining', 'slug' => 'beard-lining', 'sort_order' => 2],
                            ['name' => 'Beard Sculpting', 'slug' => 'beard-sculpting', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Beard Fading',
                        'slug' => 'beard-fading',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Low Fade', 'slug' => 'low-fade', 'sort_order' => 1],
                            ['name' => 'Mid Fade', 'slug' => 'mid-fade', 'sort_order' => 2],
                            ['name' => 'High Fade', 'slug' => 'high-fade', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Shaving',
                        'slug' => 'shaving',
                        'sort_order' => 3,
                        'children' => [
                            ['name' => 'Straight Razor', 'slug' => 'straight-razor', 'sort_order' => 1],
                            ['name' => 'Hot Towel Shave', 'slug' => 'hot-towel-shave', 'sort_order' => 2],
                            ['name' => 'Head Shave', 'slug' => 'head-shave', 'sort_order' => 3],
                        ],
                    ],
                ],
            ],
            // Skin
            [
                'name' => 'Skin',
                'slug' => 'skin',
                'sort_order' => 5,
                'children' => [
                    [
                        'name' => 'Facials',
                        'slug' => 'facials',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Deep Cleansing', 'slug' => 'deep-cleansing', 'sort_order' => 1],
                            ['name' => 'Hydrating', 'slug' => 'hydrating', 'sort_order' => 2],
                            ['name' => 'Anti-Aging', 'slug' => 'anti-aging', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Skin Treatments',
                        'slug' => 'skin-treatments',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Chemical Peels', 'slug' => 'chemical-peels', 'sort_order' => 1],
                            ['name' => 'Microdermabrasion', 'slug' => 'microdermabrasion', 'sort_order' => 2],
                            ['name' => 'LED Therapy', 'slug' => 'led-therapy', 'sort_order' => 3],
                        ],
                    ],
                ],
            ],
            // Lashes & Brows
            [
                'name' => 'Lashes & Brows',
                'slug' => 'lashes-brows',
                'sort_order' => 6,
                'children' => [
                    [
                        'name' => 'Lash Extensions',
                        'slug' => 'lash-extensions',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Classic Lashes', 'slug' => 'classic-lashes', 'sort_order' => 1],
                            ['name' => 'Volume Lashes', 'slug' => 'volume-lashes', 'sort_order' => 2],
                            ['name' => 'Hybrid Lashes', 'slug' => 'hybrid-lashes', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Lash Lifting',
                        'slug' => 'lash-lifting',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Lash Lift', 'slug' => 'lash-lift', 'sort_order' => 1],
                            ['name' => 'Lash Tint', 'slug' => 'lash-tint', 'sort_order' => 2],
                        ],
                    ],
                    [
                        'name' => 'Brow Services',
                        'slug' => 'brow-services',
                        'sort_order' => 3,
                        'children' => [
                            ['name' => 'Brow Shaping', 'slug' => 'brow-shaping', 'sort_order' => 1],
                            ['name' => 'Brow Tinting', 'slug' => 'brow-tinting', 'sort_order' => 2],
                            ['name' => 'Brow Lamination', 'slug' => 'brow-lamination', 'sort_order' => 3],
                        ],
                    ],
                ],
            ],
            // Body
            [
                'name' => 'Body',
                'slug' => 'body',
                'sort_order' => 7,
                'children' => [
                    [
                        'name' => 'Waxing',
                        'slug' => 'waxing',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Full Body Wax', 'slug' => 'full-body-wax', 'sort_order' => 1],
                            ['name' => 'Brazilian', 'slug' => 'brazilian', 'sort_order' => 2],
                            ['name' => 'Underarm', 'slug' => 'underarm', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Body Treatments',
                        'slug' => 'body-treatments',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Body Scrubs', 'slug' => 'body-scrubs', 'sort_order' => 1],
                            ['name' => 'Body Wraps', 'slug' => 'body-wraps', 'sort_order' => 2],
                            ['name' => 'Body Massage', 'slug' => 'body-massage', 'sort_order' => 3],
                        ],
                    ],
                ],
            ],
            // Wellness
            [
                'name' => 'Wellness',
                'slug' => 'wellness',
                'sort_order' => 8,
                'children' => [
                    [
                        'name' => 'Massage',
                        'slug' => 'massage',
                        'sort_order' => 1,
                        'children' => [
                            ['name' => 'Swedish Massage', 'slug' => 'swedish-massage', 'sort_order' => 1],
                            ['name' => 'Deep Tissue', 'slug' => 'deep-tissue', 'sort_order' => 2],
                            ['name' => 'Hot Stone', 'slug' => 'hot-stone', 'sort_order' => 3],
                        ],
                    ],
                    [
                        'name' => 'Relaxation',
                        'slug' => 'relaxation',
                        'sort_order' => 2,
                        'children' => [
                            ['name' => 'Aromatherapy', 'slug' => 'aromatherapy', 'sort_order' => 1],
                            ['name' => 'Meditation', 'slug' => 'meditation', 'sort_order' => 2],
                        ],
                    ],
                ],
            ],
        ];

        $this->createTaxonomy($taxonomy);
    }

    private function createTaxonomy(array $items, ?string $parentId = null): void
    {
        foreach ($items as $item) {
            $children = $item['children'] ?? null;
            unset($item['children']);

            $taxonomy = CraftTaxonomy::firstOrCreate(
                ['slug' => $item['slug']],
                [
                    ...$item,
                    'id' => Str::uuid(),
                    'parent_id' => $parentId,
                    'is_active' => true,
                ]
            );

            if ($children) {
                $this->createTaxonomy($children, $taxonomy->id);
            }
        }
    }
}
