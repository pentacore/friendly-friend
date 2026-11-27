<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventQuestion;
use App\Models\EventQuestionAnswer;
use App\Models\Guild;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->guild = Guild::factory()->create();
    $this->host = User::factory()->memberOf($this->guild)->create();
    $this->guest = User::factory()->memberOf($this->guild)->create();
    $this->event = Event::factory()->create(['guild_id' => $this->guild->id, 'created_by_discord_user_id' => $this->host->discord_user_id]);
});

function ask(User $host, Event $event, array $question): EventQuestion
{
    test()->actingAs($host)->post(route('events.questions.store', $event), $question)->assertSessionHasNoErrors();

    return EventQuestion::query()->latest('id')->firstOrFail();
}

it('lets organisers ask yes/no, pick-one and free-text questions', function (): void {
    ask($this->host, $this->event, ['prompt' => 'Will you eat crayfish?', 'kind' => 'yes_no', 'options' => []]);
    $choice = ask($this->host, $this->event, ['prompt' => 'Which sauna slot?', 'kind' => 'choice', 'options' => ['16:00', '18:00']]);

    expect($choice->options)->toBe(['16:00', '18:00']);
    $this->actingAs($this->host)->post(route('events.questions.store', $this->event), ['prompt' => 'One?', 'kind' => 'choice', 'options' => ['only']])
        ->assertSessionHasErrors('options');
    $this->actingAs($this->guest)->post(route('events.questions.store', $this->event), ['prompt' => 'Mine?', 'kind' => 'text'])
        ->assertForbidden();
});

it('saves answers with the RSVP and ignores values a question does not take', function (): void {
    $crayfish = ask($this->host, $this->event, ['prompt' => 'Will you eat crayfish?', 'kind' => 'yes_no']);
    $slot = ask($this->host, $this->event, ['prompt' => 'Which sauna slot?', 'kind' => 'choice', 'options' => ['16:00', '18:00']]);
    $note = ask($this->host, $this->event, ['prompt' => 'Anything else?', 'kind' => 'text']);

    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), [
        'status' => 'going',
        'answers' => [$crayfish->id => 'yes', $slot->id => '20:00', $note->id => 'Bringing a hat'],
    ])->assertSessionHasNoErrors();

    expect(EventQuestionAnswer::query()->orderBy('event_question_id')->pluck('value')->all())->toBe(['yes', 'Bringing a hat']);

    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'going', 'answers' => [$crayfish->id => '']]);
    expect(EventQuestionAnswer::query()->count())->toBe(1);
});

it('shows counts to everyone and who answered what to organisers', function (): void {
    $crayfish = ask($this->host, $this->event, ['prompt' => 'Will you eat crayfish?', 'kind' => 'yes_no']);
    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'going', 'answers' => [$crayfish->id => 'no']]);
    $this->actingAs($this->host)->put(route('events.rsvp', $this->event), ['status' => 'going', 'answers' => [$crayfish->id => 'yes']]);

    $this->actingAs($this->guest)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('questions.0.counts', [['value' => 'yes', 'total' => 1], ['value' => 'no', 'total' => 1]])
            ->where('questions.0.mine', 'no')
            ->where('questions.0.answers', null));

    $this->actingAs($this->host)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page->has('questions.0.answers', 2));
});

it('leaves people who can\'t come out of the counts', function (): void {
    $crayfish = ask($this->host, $this->event, ['prompt' => 'Will you eat crayfish?', 'kind' => 'yes_no']);
    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'going', 'answers' => [$crayfish->id => 'yes']]);
    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'declined']);

    $this->actingAs($this->host)->get(route('events.show', $this->event))
        ->assertInertia(fn (Assert $page): Assert => $page->where('questions.0.answered', 0));
});

it('ignores answers to another event\'s questions', function (): void {
    $other = Event::factory()->create(['guild_id' => $this->guild->id]);
    $theirs = EventQuestion::query()->create(['event_id' => $other->id, 'prompt' => 'x', 'kind' => 'text', 'options' => []]);

    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'going', 'answers' => [$theirs->id => 'sneaky']]);

    expect(EventQuestionAnswer::query()->count())->toBe(0);
});

it('removes a question with its answers', function (): void {
    $crayfish = ask($this->host, $this->event, ['prompt' => 'Will you eat crayfish?', 'kind' => 'yes_no']);
    $this->actingAs($this->guest)->put(route('events.rsvp', $this->event), ['status' => 'going', 'answers' => [$crayfish->id => 'yes']]);

    $this->actingAs($this->guest)->delete(route('events.questions.destroy', [$this->event, $crayfish]))->assertForbidden();
    $this->actingAs($this->host)->delete(route('events.questions.destroy', [$this->event, $crayfish]))->assertRedirect();

    expect(EventQuestion::query()->count() + EventQuestionAnswer::query()->count())->toBe(0);
});

it('lets organisers move a question up or down', function (): void {
    $first = ask($this->host, $this->event, ['prompt' => 'First?', 'kind' => 'yes_no']);
    $second = ask($this->host, $this->event, ['prompt' => 'Second?', 'kind' => 'yes_no']);
    $third = ask($this->host, $this->event, ['prompt' => 'Third?', 'kind' => 'yes_no']);
    $order = fn (): array => $this->event->questions()->pluck('prompt')->all();

    $this->actingAs($this->guest)->patch(route('events.questions.move', [$this->event, $third]), ['direction' => 'up'])->assertForbidden();

    $this->actingAs($this->host)->patch(route('events.questions.move', [$this->event, $third]), ['direction' => 'up'])->assertRedirect();
    expect($order())->toBe(['First?', 'Third?', 'Second?']);

    $this->actingAs($this->host)->patch(route('events.questions.move', [$this->event, $first]), ['direction' => 'down']);
    expect($order())->toBe(['Third?', 'First?', 'Second?']);

    // Already at the edge: nothing changes.
    $this->actingAs($this->host)->patch(route('events.questions.move', [$this->event, $third]), ['direction' => 'up']);
    $this->actingAs($this->host)->patch(route('events.questions.move', [$this->event, $second]), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    expect($order())->toBe(['Third?', 'First?', 'Second?']);
});
