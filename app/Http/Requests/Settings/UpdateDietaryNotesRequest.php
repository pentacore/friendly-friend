<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Your allergies and food preferences, shown to organisers of events you RSVP to.
 */
final class UpdateDietaryNotesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dietary_notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
