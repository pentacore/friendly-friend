<?php

declare(strict_types=1);

namespace App\Access\Actions;

use App\Enums\AppSetting;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Saves the owners' settings from the Settings page, one row per setting.
 */
final readonly class UpdateAppSettings
{
    /**
     * @param  array<string, int>  $values  setting key => value, already validated
     */
    public function handle(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (AppSetting::cases() as $setting) {
                if (array_key_exists($setting->value, $values)) {
                    Setting::query()->updateOrCreate(['key' => $setting->value], ['value' => $values[$setting->value]]);
                }
            }
        });
    }
}
