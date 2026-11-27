<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QuoteRevision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * One entry of a quote's edit history. Load `editor`.
 */
final class QuoteRevisionResource extends JsonResource
{
    public function __construct(private readonly QuoteRevision $revision)
    {
        parent::__construct($revision);
    }

    /**
     * @return array{
     *     id: int,
     *     editor_name: string,
     *     created_at: string|null,
     *     lines: list<array{speaker: string, text: string}>,
     *     context: string|null,
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $snapshot = $this->revision->snapshot;

        return [
            'id' => $this->revision->id,
            'editor_name' => $this->revision->editor->display_name,
            'created_at' => $this->revision->created_at?->toIso8601String(),
            'lines' => array_map(fn (array $line): array => ['speaker' => $line['speaker'], 'text' => $line['text']], $snapshot['lines']),
            'context' => $snapshot['context'],
        ];
    }
}
