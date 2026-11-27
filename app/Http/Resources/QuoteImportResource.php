<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\QuoteImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * A channel import on the admin imports page. Load `guild` and
 * `pending_count` (withCount).
 */
final class QuoteImportResource extends JsonResource
{
    public function __construct(private readonly QuoteImport $import)
    {
        parent::__construct($import);
    }

    /**
     * @return array{
     *     id: int,
     *     guild: array{id: int, name: string},
     *     channel_id: string,
     *     channel_name: string|null,
     *     status: string,
     *     messages_scanned: int,
     *     quotes_found: int,
     *     pending_count: int,
     *     error: string|null,
     *     started_at: string|null,
     *     finished_at: string|null,
     * }
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $import = $this->import;
        $channel = array_find($import->guild->channels, fn (array $channel): bool => $channel['id'] === $import->channel_id);

        return [
            'id' => $import->id,
            'guild' => ['id' => $import->guild->id, 'name' => $import->guild->name],
            'channel_id' => $import->channel_id,
            'channel_name' => $channel['name'] ?? null,
            'status' => $import->status,
            'messages_scanned' => $import->messages_scanned,
            'quotes_found' => $import->quotes_found,
            'pending_count' => $import->pending_count ?? 0,
            'error' => $import->error,
            'started_at' => $import->started_at?->toIso8601String(),
            'finished_at' => $import->finished_at?->toIso8601String(),
        ];
    }
}
