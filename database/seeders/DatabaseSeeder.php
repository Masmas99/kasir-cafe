<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@kasir.test',
        ]);

        User::factory()->kasir()->create([
            'name' => 'Kasir',
            'email' => 'kasir@kasir.test',
        ]);

        Setting::setValue('cafe_name', 'Kopi Meja Cafe');
        Setting::setValue('tax_percent', '0');
        Setting::setValue('service_percent', '0');

        $coffee = Category::create(['name' => 'Kopi & Espresso', 'sort_order' => 1]);
        $nonCoffee = Category::create(['name' => 'Non-Kopi', 'sort_order' => 2]);
        $snack = Category::create(['name' => 'Makanan & Pastry', 'sort_order' => 3]);

        $items = [
            [
                'category_id' => $coffee->id,
                'name' => 'Es Kopi Susu Gula Aren',
                'description' => 'Espresso house blend dengan susu segar dan gula aren asli.',
                'price' => 22000,
                'slug' => 'es-kopi-susu',
                'bg' => '#fef3c7',
                'fg' => '#92400e',
            ],
            [
                'category_id' => $coffee->id,
                'name' => 'Hot Caffe Latte',
                'description' => 'Double shot espresso dengan steamed milk lembut.',
                'price' => 26000,
                'slug' => 'caffe-latte',
                'bg' => '#ffedd5',
                'fg' => '#9a3412',
            ],
            [
                'category_id' => $nonCoffee->id,
                'name' => 'Iced Matcha Latte',
                'description' => 'Pure ceremonial grade matcha dengan susu segar.',
                'price' => 28000,
                'slug' => 'matcha-latte',
                'bg' => '#dcfce7',
                'fg' => '#166534',
            ],
            [
                'category_id' => $snack->id,
                'name' => 'Butter Croissant',
                'description' => 'Croissant renyah panggang segar setiap pagi.',
                'price' => 20000,
                'slug' => 'butter-croissant',
                'bg' => '#fef9c3',
                'fg' => '#854d0e',
            ],
        ];

        foreach ($items as $item) {
            $path = 'menu-items/'.$item['slug'].'.svg';
            Storage::disk('public')->put($path, $this->buildMenuSvg($item['name'], $item['bg'], $item['fg']));

            MenuItem::create([
                'category_id' => $item['category_id'],
                'name' => $item['name'],
                'description' => $item['description'],
                'price' => $item['price'],
                'image_path' => $path,
                'is_available' => true,
            ]);
        }

        for ($i = 1; $i <= 6; $i++) {
            DiningTable::create([
                'name' => "Meja {$i}",
                'is_active' => true,
            ]);
        }
    }

    /**
     * Build a clean SVG illustration for a seeded menu item.
     */
    protected function buildMenuSvg(string $title, string $bgColor, string $fgColor): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300" viewBox="0 0 400 300">
    <rect width="400" height="300" fill="{$bgColor}"/>
    <circle cx="200" cy="125" r="58" fill="#ffffff" opacity="0.85"/>
    <path d="M170 110 h50 v35 a25 25 0 0 1 -50 0 z" fill="{$fgColor}"/>
    <path d="M220 116 h10 a10 10 0 0 1 0 20 h-10" fill="none" stroke="{$fgColor}" stroke-width="5"/>
    <text x="200" y="230" font-family="sans-serif" font-size="18" font-weight="bold" fill="{$fgColor}" text-anchor="middle">{$escapedTitle}</text>
</svg>
SVG;
    }
}
