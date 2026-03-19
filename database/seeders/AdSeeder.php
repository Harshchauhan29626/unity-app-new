<?php

namespace Database\Seeders;

use App\Models\Ad;
use Illuminate\Database\Seeder;

class AdSeeder extends Seeder
{
    public function run(): void
    {
        $ads = [
            [
                'title' => 'Summer Offer',
                'subtitle' => 'Limited Time',
                'description' => 'Get special pricing on premium features this season.',
                'placement' => 'timeline',
                'timeline_position' => 3,
                'sort_order' => 1,
                'button_text' => 'Learn More',
                'redirect_url' => 'https://example.com/summer-offer',
                'is_active' => true,
            ],
            [
                'title' => 'Business Ad 2',
                'subtitle' => 'Grow with us',
                'description' => 'Expand your network with verified leaders and peers.',
                'placement' => 'timeline',
                'timeline_position' => 6,
                'sort_order' => 2,
                'button_text' => 'Open',
                'redirect_url' => 'https://example.com/business-ad-2',
                'is_active' => true,
            ],
            [
                'title' => 'Starter Plan Promo',
                'subtitle' => 'New Members',
                'description' => 'Join now and unlock onboarding support.',
                'placement' => 'timeline',
                'timeline_position' => 9,
                'sort_order' => 3,
                'button_text' => 'Get Started',
                'redirect_url' => 'https://example.com/starter-plan',
                'is_active' => true,
            ],
        ];

        foreach ($ads as $payload) {
            Ad::query()->updateOrCreate(
                [
                    'title' => $payload['title'],
                    'placement' => $payload['placement'],
                    'timeline_position' => $payload['timeline_position'],
                ],
                $payload
            );
        }
    }
}
