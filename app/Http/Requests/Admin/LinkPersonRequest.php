<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Link a person to a Discord account (or unlink with null). An account can
 * belong to one person only; merge the two people instead.
 */
final class LinkPersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $person = $this->route('person');

        return [
            'discord_user_id' => [
                'nullable',
                'integer',
                'exists:discord_users,id',
                Rule::unique('people', 'discord_user_id')->ignore($person instanceof Person ? $person->id : null),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['discord_user_id.unique' => 'That account already belongs to another person. Merge the two people instead.'];
    }
}
