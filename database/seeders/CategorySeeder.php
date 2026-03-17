<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Industrial machinery manufacturers',
            'Auto components',
            'Electrical equipment',
            'Electronics manufacturing',
            'Packaging manufacturers',
            'Plastic moulding',
        ];

        foreach ($categories as $categoryName) {
            Category::query()->updateOrCreate(
                ['category_name' => $categoryName],
                [
                    'sector' => 'Manufacturing',
                    'remarks' => null,
                ]
            );
        }
    }
}
