<?php

namespace Database\Seeders;

use App\Domain\Sport\Models\Sport;
use Illuminate\Database\Seeder;

class SportSeeder extends Seeder
{
    /** @var array<string, array{en: string, ar: string}> */
    private const SPORTS = [
        'karate' => ['en' => 'Karate', 'ar' => 'كاراتيه'],
        'judo' => ['en' => 'Judo', 'ar' => 'الجودو'],
        'taekwondo' => ['en' => 'Taekwondo', 'ar' => 'التايكوندو'],
        'gymnastics' => ['en' => 'Gymnastics', 'ar' => 'الجمباز'],
    ];

    public function run(): void
    {
        foreach (self::SPORTS as $slug => $name) {
            Sport::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
