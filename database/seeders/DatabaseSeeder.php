<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds nothing yet. Real data comes from Discord (`discord:sync-members` and
 * the bot), and tests build theirs with factories. A local convenience added
 * here must be idempotent and return early outside `local`
 * (.ai/rules/seeders.md).
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Nothing to seed: real data comes from Discord, run discord:sync-members.');
    }
}
