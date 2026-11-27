<?php

declare(strict_types=1);

namespace App\Access;

use App\Enums\AppSetting;
use App\Models\Setting;

/**
 * Reads the limits owners tune on the Settings page. A setting nobody has
 * saved yet is its default, and a stored value is clamped to the setting's
 * bounds, so a bad row can never send a reminder a year early.
 */
final readonly class AppSettings
{
    public function get(AppSetting $setting): int
    {
        $stored = Setting::query()->find($setting->value)?->value;

        return max($setting->min(), min($setting->max(), $stored ?? $setting->default()));
    }

    /**
     * @return array<string, int> every setting's current value, keyed by setting
     */
    public function all(): array
    {
        $stored = Setting::query()->pluck('value', 'key')->all();
        $values = [];

        foreach (AppSetting::cases() as $setting) {
            $value = $stored[$setting->value] ?? null;
            $values[$setting->value] = max($setting->min(), min($setting->max(), is_numeric($value) ? (int) $value : $setting->default()));
        }

        return $values;
    }
}
