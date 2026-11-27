<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Guild;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Owners changing a server's access settings and quotes channel; roles and channels must belong to that server.
 */
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
        $channelIds = $guild instanceof Guild ? array_column($guild->channels, 'id') : [];

        return [
            'enabled' => ['required', 'boolean'],
            'allow_all_members' => ['required', 'boolean'],
            'allowed_role_ids' => ['present', 'array'],
            'allowed_role_ids.*' => ['string', Rule::in($roleIds)],
            'admin_role_ids' => ['present', 'array'],
            'admin_role_ids.*' => ['string', Rule::in($roleIds)],
            'quotes_channel_id' => ['nullable', 'string', Rule::in($channelIds)],
            'events_channel_id' => ['nullable', 'string', Rule::in($channelIds)],
            'watched_channel_ids' => ['array', 'max:10'],
            'watched_channel_ids.*' => ['string', 'distinct', Rule::in($channelIds)],
            'publish_watched_quotes' => ['boolean'],
            'private_random_quotes' => ['boolean'],
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
            'quotes_channel_id.in' => 'That is not a text channel of this server.',
            'events_channel_id.in' => 'That is not a text channel of this server.',
            'watched_channel_ids.*.in' => 'That is not a text channel of this server.',
        ];
    }
}
