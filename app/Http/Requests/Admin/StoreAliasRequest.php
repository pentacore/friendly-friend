<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\PersonAlias;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new nickname for a person; aliases are unique regardless of case.
 */
final class StoreAliasRequest extends FormRequest
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
        return [
            'alias' => ['required', 'string', 'max:80', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && PersonAlias::query()->whereRaw('lower(alias) = ?', [mb_strtolower(trim($value))])->exists()) {
                    $fail('Someone already goes by that name.');
                }
            }],
        ];
    }
}
