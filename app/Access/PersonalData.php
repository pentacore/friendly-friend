<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\GuildMember;
use App\Models\Person;
use App\Models\PersonAlias;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Everything stored about one person, for "Download my data" on
 * /settings/privacy: account, servers, quotes they said or saved, votes,
 * event answers and costs, and stats. Other people's notes and answers are
 * never included, only rows that are about this person.
 */
final readonly class PersonalData
{
    /**
     * @return array<string, mixed>
     */
    public static function for(User $user): array
    {
        $member = $user->discordUser;
        $me = $member->id;
        $person = Person::query()->where('discord_user_id', $me)->with('aliases')->first();
        $quote = fn (Quote $quote): array => [
            'id' => $quote->id,
            'server' => $quote->guild->name,
            'status' => $quote->status->value,
            'lines' => array_values($quote->lines->map(fn (QuoteLine $line): array => ['speaker' => $line->speaker_name, 'text' => $line->text])->all()),
            'context' => $quote->context,
            'said_at' => $quote->said_at?->toIso8601String(),
            'saved_at' => $quote->created_at?->toIso8601String(),
        ];
        $timezone = config()->string('stats.timezone');

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'discord_id' => $member->discord_id,
                'username' => $member->username,
                'display_name' => $member->display_name,
                'dietary_notes' => $member->dietary_notes,
                'stats_opted_out_at' => $member->tracking_opted_out_at?->toIso8601String(),
                'first_seen_at' => $member->created_at?->toIso8601String(),
                'calendar_feed_enabled' => $user->calendar_token !== null,
            ],
            'servers' => array_values(GuildMember::query()->where('discord_user_id', $me)->with('guild')->get()
                ->map(fn (GuildMember $membership): array => [
                    'name' => $membership->guild->name,
                    'nickname' => $membership->nick,
                    'still_member' => $membership->in_guild,
                ])->all()),
            'person' => $person instanceof Person ? [
                'name' => $person->display_name,
                'nicknames' => array_values($person->aliases->map(fn (PersonAlias $alias): string => $alias->alias)->all()),
            ] : null,
            'quotes_said' => $person instanceof Person
                ? array_values(Quote::query()->whereHas('lines', fn (Builder $lines) => $lines->where('person_id', $person->id))->with(['guild', 'lines'])->orderBy('id')->get()->map($quote)->all())
                : [],
            'quotes_saved' => array_values(Quote::query()->where('submitted_by_discord_user_id', $me)->with(['guild', 'lines'])->orderBy('id')->get()->map($quote)->all()),
            'quote_votes' => self::rows(DB::table('quote_votes')->where('user_id', $user->id)->orderBy('quote_id')->select('quote_id', 'value')),
            'quote_edits' => self::rows(DB::table('quote_revisions')->where('editor_discord_user_id', $me)->orderBy('id')->select('quote_id', 'created_at as edited_at')),
            'event_rsvps' => self::rows(DB::table('event_rsvps')->join('events', 'events.id', '=', 'event_rsvps.event_id')
                ->where('event_rsvps.discord_user_id', $me)->orderBy('events.starts_at')
                ->select('events.title as event', 'events.starts_at', 'event_rsvps.status', 'event_rsvps.guests', 'event_rsvps.note', 'event_rsvps.needs_ride')),
            'event_answers' => self::rows(DB::table('event_question_answers')
                ->join('event_questions', 'event_questions.id', '=', 'event_question_answers.event_question_id')
                ->join('events', 'events.id', '=', 'event_questions.event_id')
                ->where('event_question_answers.discord_user_id', $me)
                ->select('events.title as event', 'event_questions.prompt as question', 'event_question_answers.value as answer')),
            'event_date_votes' => self::rows(DB::table('event_date_votes')
                ->join('event_date_options', 'event_date_options.id', '=', 'event_date_votes.event_date_option_id')
                ->join('events', 'events.id', '=', 'event_date_options.event_id')
                ->where('event_date_votes.discord_user_id', $me)
                ->select('events.title as event', 'event_date_options.starts_at as date', 'event_date_votes.answer')),
            'event_bring' => self::rows(DB::table('event_bring_claims')
                ->join('event_bring_items', 'event_bring_items.id', '=', 'event_bring_claims.event_bring_item_id')
                ->join('events', 'events.id', '=', 'event_bring_items.event_id')
                ->where('event_bring_claims.discord_user_id', $me)
                ->select('events.title as event', 'event_bring_items.label as item', 'event_bring_claims.quantity', 'event_bring_claims.note')),
            'event_rides_offered' => self::rows(DB::table('event_rides')->join('events', 'events.id', '=', 'event_rides.event_id')
                ->where('event_rides.driver_discord_user_id', $me)
                ->select('events.title as event', 'event_rides.direction', 'event_rides.departs_from', 'event_rides.departs_at', 'event_rides.seats', 'event_rides.note')),
            'event_rides_joined' => self::rows(DB::table('event_ride_passengers')
                ->join('event_rides', 'event_rides.id', '=', 'event_ride_passengers.event_ride_id')
                ->join('events', 'events.id', '=', 'event_rides.event_id')
                ->where('event_ride_passengers.discord_user_id', $me)
                ->select('events.title as event', 'event_rides.direction', 'event_ride_passengers.pickup_note')),
            'event_expenses_paid' => self::rows(DB::table('event_expenses')->join('events', 'events.id', '=', 'event_expenses.event_id')
                ->where('event_expenses.paid_by_discord_user_id', $me)
                ->select('events.title as event', 'events.currency', 'event_expenses.description', 'event_expenses.amount as amount_minor_units')),
            'event_expense_shares' => self::rows(DB::table('event_expense_shares')
                ->join('event_expenses', 'event_expenses.id', '=', 'event_expense_shares.event_expense_id')
                ->join('events', 'events.id', '=', 'event_expenses.event_id')
                ->where('event_expense_shares.discord_user_id', $me)
                ->select('events.title as event', 'events.currency', 'event_expenses.description', 'event_expense_shares.amount as amount_minor_units')),
            'event_payments' => self::rows(DB::table('event_payments')->join('events', 'events.id', '=', 'event_payments.event_id')
                ->where(fn (QueryBuilder $mine) => $mine->where('event_payments.from_discord_user_id', $me)->orWhere('event_payments.to_discord_user_id', $me))
                ->selectRaw('events.title as event, events.currency, event_payments.amount as amount_minor_units, event_payments.settled_at, (event_payments.from_discord_user_id = ?) as paid_by_you', [$me])),
            'stats' => [
                'messages_per_day' => self::rows(DB::table('stat_messages')->join('guilds', 'guilds.id', '=', 'stat_messages.guild_id')
                    ->where('stat_messages.discord_user_id', $me)
                    ->selectRaw('guilds.name as server, (stat_messages.hour at time zone ?)::date::text as day, sum(stat_messages.count)::int as messages', [$timezone])
                    ->groupByRaw('1, 2')->orderByRaw('2, 1')),
                'reactions_given' => self::rows(DB::table('stat_reactions')->join('guilds', 'guilds.id', '=', 'stat_reactions.guild_id')
                    ->where('stat_reactions.reactor_discord_user_id', $me)
                    ->selectRaw('guilds.name as server, stat_reactions.day::text as day, stat_reactions.emoji, stat_reactions.count')
                    ->orderBy('stat_reactions.day')),
                'voice_sessions' => self::rows(DB::table('stat_voice_sessions')->join('guilds', 'guilds.id', '=', 'stat_voice_sessions.guild_id')
                    ->where('stat_voice_sessions.discord_user_id', $me)
                    ->select('guilds.name as server', 'stat_voice_sessions.channel_id', 'stat_voice_sessions.started_at', 'stat_voice_sessions.ended_at')
                    ->orderBy('stat_voice_sessions.started_at')),
                'game_sessions' => self::rows(DB::table('stat_activity_sessions')->join('guilds', 'guilds.id', '=', 'stat_activity_sessions.guild_id')
                    ->where('stat_activity_sessions.discord_user_id', $me)
                    ->select('guilds.name as server', 'stat_activity_sessions.activity_name as game', 'stat_activity_sessions.started_at', 'stat_activity_sessions.ended_at')
                    ->orderBy('stat_activity_sessions.started_at')),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rows(QueryBuilder $query): array
    {
        return array_values($query->get()->map(function (object $row): array {
            $columns = [];

            foreach (get_object_vars($row) as $column => $value) {
                if (is_string($column)) {
                    $columns[$column] = $value;
                }
            }

            return $columns;
        })->all());
    }
}
