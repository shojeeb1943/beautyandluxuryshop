<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $colors = [
            ['name' => 'Black', 'code' => '#000000'],
            ['name' => 'White', 'code' => '#FFFFFF'],
            ['name' => 'Red', 'code' => '#FF0000'],
            ['name' => 'Green', 'code' => '#00FF00'],
            ['name' => 'Blue', 'code' => '#0000FF'],
            ['name' => 'Yellow', 'code' => '#FFFF00'],
            ['name' => 'YellowGreen', 'code' => '#9ACD32'],
            ['name' => 'Pink', 'code' => '#FFC0CB'],
            ['name' => 'Purple', 'code' => '#800080'],
            ['name' => 'Violet', 'code' => '#EE82EE'],
            ['name' => 'Orange', 'code' => '#FFA500'],
            ['name' => 'Brown', 'code' => '#A52A2A'],
            ['name' => 'Gray', 'code' => '#808080'],
            ['name' => 'Silver', 'code' => '#C0C0C0'],
            ['name' => 'Gold', 'code' => '#FFD700'],
            ['name' => 'Navy', 'code' => '#000080'],
            ['name' => 'Teal', 'code' => '#008080'],
            ['name' => 'Maroon', 'code' => '#800000'],
            ['name' => 'Olive', 'code' => '#808000'],
            ['name' => 'Lime', 'code' => '#00FF00'],
            ['name' => 'Aqua', 'code' => '#00FFFF'],
            ['name' => 'Fuchsia', 'code' => '#FF00FF'],
            ['name' => 'Wheat', 'code' => '#F5DEB3'],
            ['name' => 'WhiteSmoke', 'code' => '#F5F5F5'],
            ['name' => 'Beige', 'code' => '#F5F5DC'],
            ['name' => 'Coral', 'code' => '#FF7F50'],
            ['name' => 'Crimson', 'code' => '#DC143C'],
            ['name' => 'Indigo', 'code' => '#4B0082'],
            ['name' => 'Khaki', 'code' => '#F0E68C'],
            ['name' => 'Lavender', 'code' => '#E6E6FA'],
            ['name' => 'Magenta', 'code' => '#FF00FF'],
            ['name' => 'Mint', 'code' => '#98FF98'],
            ['name' => 'Peach', 'code' => '#FFDAB9'],
            ['name' => 'Plum', 'code' => '#DDA0DD'],
            ['name' => 'Salmon', 'code' => '#FA8072'],
            ['name' => 'Tan', 'code' => '#D2B48C'],
            ['name' => 'Turquoise', 'code' => '#40E0D0'],
        ];

        foreach ($colors as $color) {
            Color::updateOrCreate(
                ['code' => $color['code']], // Check by code to avoid duplicates
                ['name' => $color['name']]
            );
        }
    }
}
