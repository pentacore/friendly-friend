<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Access\Actions\UpdateAppSettings;
use App\Access\AppSettings;
use App\Enums\AppSetting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAppSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Pentacore\Typefinder\Attributes\TypefinderPage;

/**
 * Owners-only limits (/admin/settings): reminder times, draft expiry, top lists.
 */
final class SettingsController extends Controller
{
    #[TypefinderPage(component: 'admin/Settings', props: [
        'settings' => '{ key: '.AppSetting::class.'; group: string; label: string; help: string; unit: string; value: number; default: number; min: number; max: number }[]',
    ])]
    public function index(AppSettings $settings): Response
    {
        $values = $settings->all();

        return Inertia::render('admin/Settings', [
            'settings' => array_map(static fn (AppSetting $setting): array => [
                'key' => $setting->value,
                'group' => $setting->group(),
                'label' => $setting->label(),
                'help' => $setting->help(),
                'unit' => $setting->unit(),
                'value' => $values[$setting->value],
                'default' => $setting->default(),
                'min' => $setting->min(),
                'max' => $setting->max(),
            ], AppSetting::cases()),
        ]);
    }

    public function update(UpdateAppSettingsRequest $request, UpdateAppSettings $updateSettings): RedirectResponse
    {
        $values = [];

        foreach (AppSetting::cases() as $setting) {
            $values[$setting->value] = $request->integer($setting->value);
        }

        $updateSettings->handle($values);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('admin.settings.index');
    }
}
