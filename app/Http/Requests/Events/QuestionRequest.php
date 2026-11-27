<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\QuestionKind;
use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A question for everyone coming (organisers): the prompt, its kind and,
 * for a choice, two or more options.
 */
final class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event && ($this->user()?->can('update', $event) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::enum(QuestionKind::class)],
            // Only pick-one questions have options; for the others they're dropped.
            'options' => ['exclude_unless:kind,'.QuestionKind::Choice->value, 'required', 'array', 'min:2', 'max:12'],
            'options.*' => ['exclude_unless:kind,'.QuestionKind::Choice->value, 'required', 'string', 'max:80', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function options(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $option): string => is_string($option) ? trim($option) : '',
            $this->array('options'),
        )));
    }
}
