<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AppSetting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Owners changing the limits on the Settings page. Each value stays inside
 * its setting's bounds, and the second reminder comes after the first.
 */
final class UpdateAppSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-guilds') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (AppSetting::cases() as $setting) {
            $rules[$setting->value] = ['required', 'integer', 'min:'.$setting->min(), 'max:'.$setting->max()];
        }

        $rules[AppSetting::ReminderSecondHours->value][] = 'lt:'.AppSetting::ReminderFirstHours->value;

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            AppSetting::ReminderSecondHours->value.'.lt' => 'The second reminder must come after the first, so it needs fewer hours.',
        ];
    }
}
