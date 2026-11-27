<?php

declare(strict_types=1);

namespace App\Planning\Actions\Questions;

use App\Events\EventChanged;
use App\Models\EventQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Moves a question one place up or down. Positions are renumbered 1..n
 * on the way, so gaps left by removed questions don't matter.
 */
final readonly class MoveQuestion
{
    public function handle(EventQuestion $question, bool $up): void
    {
        DB::transaction(function () use ($question, $up): void {
            $ids = EventQuestion::query()
                ->where('event_id', $question->event_id)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id'])
                ->map(fn (EventQuestion $sibling): int => $sibling->id)
                ->values()
                ->all();

            $from = array_search($question->id, $ids, true);

            if ($from === false) {
                return;
            }

            $to = $up ? $from - 1 : $from + 1;

            if (! isset($ids[$to])) {
                return;
            }

            [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

            foreach ($ids as $index => $id) {
                EventQuestion::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        broadcast(new EventChanged($question->event_id, EventChanged::OVERVIEW));
    }
}
