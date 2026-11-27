<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Person;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Merge the route's person into another one.
 */
final class MergePeopleRequest extends FormRequest
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
            'into_person_id' => [
                'required',
                'integer',
                'exists:people,id',
                Rule::notIn([$person instanceof Person ? $person->id : 0]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['into_person_id.not_in' => 'Pick someone else to merge into.'];
    }
}
