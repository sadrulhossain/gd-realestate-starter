<?php

namespace Database\Seeders;

use App\Models\Section;

class SectionsModuleSeeder extends ModuleSeeder
{
    /** key => [title, visible]. Core sections are on, optional ones off until an admin enables them. */
    private const DEFAULTS = [
        'hero' => ['Welcome', true],
        'featured_properties' => ['Featured properties', true],
        'property_types' => ['Browse by type', true],
        'locations' => ['Popular locations', true],
        'projects' => ['New developments', true],
        'about' => ['About us', true],
        'services' => ['Our services', true],
        'agents' => ['Our agents', true],
        'pricing' => ['Pricing', false],
        'contact' => ['Contact us', true],
        'testimonials' => ['Testimonials', false],
        'gallery' => ['Gallery', false],
        'faq' => ['Frequently asked questions', false],
        'team' => ['Our team', false],
    ];

    protected function permissions(): array
    {
        return ['sections.view', 'sections.update'];
    }

    protected function menus(): array
    {
        return [
            ['title' => 'Content', 'sort' => 20, 'children' => [
                ['title' => 'Sections', 'permission' => 'sections.view', 'path' => '/sections', 'icon' => 'layers'],
            ]],
        ];
    }

    protected function grants(): array
    {
        return ['admin' => ['sections.view', 'sections.update']];
    }

    public function run(): void
    {
        parent::run();

        $order = 0;

        foreach (self::DEFAULTS as $key => [$title, $visible]) {
            // firstOrCreate: re-seeding never overwrites what an admin edited.
            Section::query()->firstOrCreate(['key' => $key], [
                'title' => $title,
                'sort_order' => $order += 10,
                'is_visible' => $visible,
            ]);
        }
    }
}
