<?php

namespace Database\Seeders;

use Database\Seeders\StorySeeds\DiscrepancyResolutionStorySeeder;
use Database\Seeders\StorySeeds\FocusTimeAlignmentStorySeeder;
use Database\Seeders\StorySeeds\GoldenPathExecutiveArtifactStorySeeder;
use Database\Seeders\StorySeeds\ImpersonationStorySeeder;
use Illuminate\Database\Seeder;

class StorySeedsSeeder extends Seeder
{
    /**
     * @var list<class-string<Seeder>>
     */
    protected array $seeders = [
        DiscrepancyResolutionStorySeeder::class,
        GoldenPathExecutiveArtifactStorySeeder::class,
        FocusTimeAlignmentStorySeeder::class,
        ImpersonationStorySeeder::class,
    ];

    public function run(): void
    {
        foreach ($this->seeders as $seeder) {
            $this->call($seeder);
        }
    }
}
