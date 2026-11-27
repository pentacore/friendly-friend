<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Guild;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGuildRequest extends FormRequest
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
        $guild = $this->route('guild');
        $roleIds = $guild instanceof Guild
            ? array_column($guild->assignableRoles(), 'id')
            : [];

        return [
            'enabled' => ['required', 'boolean'],
            'allow_all_members' => ['required', 'boolean'],
            'allowed_role_ids' => ['present', 'array'],
            'allowed_role_ids.*' => ['string', Rule::in($roleIds)],
            'admin_role_ids' => ['present', 'array'],
            'admin_role_ids.*' => ['string', Rule::in($roleIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allowed_role_ids.*.in' => 'That role is not an assignable role of this server.',
            'admin_role_ids.*.in' => 'That role is not an assignable role of this server.',
        ];
    }
}
