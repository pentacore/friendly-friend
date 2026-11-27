<?php

declare(strict_types=1);

use App\Enums\Currency;
use App\Enums\RsvpStatus;
use App\Models\DiscordUser;
use App\Models\Event;
use App\Models\EventQuestionAnswer;
use App\Models\EventRide;
use App\Models\EventRsvp;
use App\Models\EventShoppingItem;
use App\Models\Guild;
use App\Models\User;

/**
 * Renders the event pages in a real browser, in light and dark mode, and
 * goes through creating an event and answering it.
 */
beforeEach(function (): void {
    $this->guild = Guild::factory()->create(['name' => 'Claesson Notify']);
    $this->user = User::factory()->memberOf($this->guild, ['900'])->create();
    $this->event = Event::factory()->create([
        'guild_id' => $this->guild->id,
        'created_by_discord_user_id' => $this->user->discord_user_id,
        'title' => 'Crayfish party',
        'location' => 'The cabin',
        'location_url' => 'https://maps.example/cabin',
        'description' => "Bring a hat.\nThe sauna is on from 16:00.",
    ]);
    Event::factory()->past()->create(['guild_id' => $this->guild->id, 'title' => 'Midsummer']);

    foreach (['Bo' => 'going', 'Kim' => 'maybe', 'Alex' => 'declined', 'Sam' => 'going'] as $name => $status) {
        EventRsvp::query()->create([
            'event_id' => $this->event->id,
            'discord_user_id' => DiscordUser::factory()->create(['global_name' => $name])->id,
            'status' => $status,
            'guests' => $name === 'Bo' ? 1 : 0,
            'needs_ride' => $name === 'Sam',
            'note' => $name === 'Kim' ? 'Depends on work' : null,
        ]);
    }
});

it('renders the events list', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit('/events');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Crayfish party')->assertSee('Midsummer')->assertSee('Together')->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'events-index-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('renders an event and takes an RSVP', function (bool $dark): void {
    $this->actingAs($this->user);

    $page = visit("/events/{$this->event->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Crayfish party')->assertSee('Depends on work')->assertNoJavaScriptErrors();
    $page->script('document.fonts.ready');
    $page->screenshot(filename: 'events-show-'.($dark ? 'dark' : 'light'))
        ->click('Maybe')
        ->assertSee('I need a ride')
        ->assertNoJavaScriptErrors();

    expect(EventRsvp::query()->where('discord_user_id', $this->user->discord_user_id)->value('status'))->toBe(RsvpStatus::Maybe);
})->with(['light' => false, 'dark' => true]);

it('creates an event', function (): void {
    $this->actingAs($this->user);

    visit('/events/create')
        ->fill('title', 'Board game night')
        ->fill('starts_at', now()->addDays(15)->format('Y-m-d').'T19:00')
        ->fill('location', 'Kim’s place')
        ->select('currency', 'EUR')
        ->screenshot(filename: 'events-create-light')
        ->press('Create event')
        ->assertSee('Board game night')
        ->assertSee('Going · 1')
        ->assertNoJavaScriptErrors();

    expect(Event::query()->where('title', 'Board game night')->sole()->currency)->toBe(Currency::Eur);
});

it('renders an event on a phone', function (): void {
    $this->actingAs($this->user);

    visit("/events/{$this->event->id}")->resize(390, 844)
        ->assertSee('Are you coming?')
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: true, filename: 'events-show-phone');
});

it('plans the food', function (bool $dark): void {
    $this->user->discordUser->forceFill(['dietary_notes' => 'No nuts'])->save();
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $this->user->discord_user_id, 'status' => 'going']);
    $this->actingAs($this->user);

    $page = visit("/events/{$this->event->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->click('[role="tab"]:nth-child(2)')
        ->assertSee('Bring list')
        ->assertSee('Dietary needs')
        ->fill('[data-test="bring-list"] input[aria-label="Item"]', 'Folding chairs')
        ->click('[data-test="bring-list"] button[type="submit"]')
        ->assertSee('Folding chairs')
        ->click("I'll bring it")
        ->assertSee('1 / 1')
        ->fill('[data-test="shopping-list"] input[aria-label="Item"]', 'Crisps')
        ->fill('[data-test="shopping-list"] input[aria-label="How much"]', '3 bags')
        ->click('[data-test="shopping-list"] button[type="submit"]')
        ->assertSee('Crisps')
        ->fill('[data-test="meal-plan"] input[aria-label="What"]', 'Tacos')
        ->click('Add meal')
        ->assertSee('Tacos')
        ->click('I’ll cook')
        ->assertSee('Not cooking')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'events-food-'.($dark ? 'dark' : 'light'));
})->with(['light' => false, 'dark' => true]);

it('renders the food settings', function (): void {
    $this->actingAs($this->user);

    visit('/settings/food')
        ->fill('dietary_notes', 'Vegetarian')
        ->press('Save')
        ->assertSee('Saved.')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'settings-food-light');

    expect($this->user->discordUser->fresh()?->dietary_notes)->toBe('Vegetarian');
});

it('arranges rides', function (bool $dark): void {
    $driver = DiscordUser::factory()->create(['global_name' => 'Robin']);
    EventRide::query()->create(['event_id' => $this->event->id, 'driver_discord_user_id' => $driver->id, 'direction' => 'to', 'seats' => 3, 'departs_from' => 'Central station', 'departs_at' => $this->event->starts_at->copy()->subHours(2)]);
    $this->actingAs($this->user);

    $page = visit("/events/{$this->event->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->click('[role="tab"]:nth-child(3)')
        ->assertSee('Still needs a ride there')
        ->assertSee('Robin drives')
        ->fill('[data-test="transport"] input[aria-label="Pickup"]', 'By the bakery')
        ->click('Take a seat')
        ->assertSee('By the bakery')
        ->assertSee('2 free')
        ->click('Offer a ride')
        ->fill('#ride-from', 'Kim’s place')
        ->click('[data-test="transport"] form button[type="submit"]')
        ->assertDontSee('Never mind')
        ->assertSee('There and back')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'events-transport-'.($dark ? 'dark' : 'light'));

    expect(EventRide::query()->count())->toBe(2);
})->with(['light' => false, 'dark' => true]);

it('splits costs and settles up', function (bool $dark): void {
    EventRsvp::query()->create(['event_id' => $this->event->id, 'discord_user_id' => $this->user->discord_user_id, 'status' => 'going']);
    $crayfish = EventShoppingItem::query()->create(['event_id' => $this->event->id, 'label' => 'Crayfish', 'added_by_discord_user_id' => $this->user->discord_user_id]);
    $this->actingAs($this->user);

    $page = visit("/events/{$this->event->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->click('[role="tab"]:nth-child(4)')
        ->assertSee('Add expenses and this works out who pays whom.')
        ->click('Add an expense')
        ->fill('#expense-description', 'Crayfish, 3 kg')
        ->fill('#expense-amount', '450')
        ->click('[data-test="expense-shopping"] button[role="checkbox"]')
        ->click('[data-test="costs"] form button[type="submit"]')
        ->assertSee('Split 4 ways')
        ->assertSee('Paid for: Crayfish')
        ->assertSee("You're owed")
        ->assertSee('Mark as paid')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'events-costs-'.($dark ? 'dark' : 'light'));

    $page->click('Mark as paid')->assertSee('Paid back')->assertNoJavaScriptErrors();

    expect($crayfish->fresh()?->expense_id)->not->toBeNull();
})->with(['light' => false, 'dark' => true]);

it('asks questions and shows the answers', function (bool $dark): void {
    $this->event->update(['rsvp_by' => now()->addDays(3)]);
    $this->actingAs($this->user);

    $page = visit("/events/{$this->event->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Please answer by')
        ->click('Add a question')
        ->fill('#question-prompt', 'Will you eat crayfish?')
        ->click('Add question')
        ->assertSee('0 answered')
        ->click('Add a question')
        ->fill('#question-prompt', 'Bringing a towel?')
        ->click('Add question')
        ->click('[aria-label="Move question Bringing a towel? up"]')
        ->assertSeeIn('[data-test="questions"] article:first-of-type', 'Bringing a towel?')
        ->click('[data-test="rsvp"] button[aria-pressed]:first-child')
        ->click('[data-test^="question-"] button:first-child >> nth=0')
        ->assertSee('1 answered')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'events-questions-'.($dark ? 'dark' : 'light'));

    expect(EventQuestionAnswer::query()->value('value'))->toBe('yes')
        ->and($this->event->questions()->pluck('prompt')->all())->toBe(['Bringing a towel?', 'Will you eat crayfish?']);
})->with(['light' => false, 'dark' => true]);

it('votes on dates and chooses one', function (bool $dark): void {
    $poll = Event::factory()->create([
        'guild_id' => $this->guild->id,
        'created_by_discord_user_id' => $this->user->discord_user_id,
        'title' => 'Board game night',
        'date_polling' => true,
        'starts_at' => now()->addWeek()->setTime(17, 0),
    ]);
    $first = $poll->dateOptions()->create(['starts_at' => now()->addWeek()->setTime(17, 0)]);
    $poll->dateOptions()->create(['starts_at' => now()->addWeeks(2)->setTime(17, 0)]);
    $first->votes()->create(['discord_user_id' => DiscordUser::factory()->create(['global_name' => 'Kim'])->id, 'answer' => 'yes']);
    $this->actingAs($this->user);

    $page = visit("/events/{$poll->id}");
    $page = $dark ? $page->inDarkMode() : $page;

    $page->assertSee('Which dates work for you?')
        ->assertSee('Best so far')
        ->click("[data-test=\"date-{$first->id}\"] button[aria-pressed]:first-child")
        ->assertSee('2 yes')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'events-date-poll-'.($dark ? 'dark' : 'light'));

    $page->click("[data-test=\"date-{$first->id}\"] >> text=Choose this date")
        ->click('Choose it')
        ->assertSee('Are you coming?')
        ->assertNoJavaScriptErrors();

    expect($poll->fresh()?->date_polling)->toBeFalse();
})->with(['light' => false, 'dark' => true]);

it('creates a date vote with the first date’s times and plans ahead', function (bool $dark): void {
    foreach (['Alex', 'Robin', 'Kim', 'Sam', 'Noor', 'Liv', 'Ture'] as $name) {
        User::factory()->forDiscordUser(DiscordUser::factory()->create(['global_name' => $name]))->memberOf($this->guild)->create();
    }
    $this->actingAs($this->user);
    $day = fn (int $days): string => now()->addDays($days)->format('Y-m-d');

    $page = visit('/events/create');
    $page = $dark ? $page->inDarkMode() : $page;

    $page->fill('title', 'Cabin weekend')
        ->click('Not sure when? Let people vote on the date')
        ->fill('[data-test="date-option-0"] input[aria-label$=": day"]', $day(10))
        ->fill('[data-test="date-option-0"] input[aria-label$="from"]', '17:30')
        ->fill('[data-test="date-option-0"] input[aria-label$="until day (optional)"]', $day(12))
        ->fill('[data-test="date-option-0"] input[aria-label$="until (optional)"]', '12:00')
        ->click('Another date')
        ->assertValue('[data-test="date-option-2"] input[aria-label$="from"]', '17:30')
        ->fill('[data-test="date-option-1"] input[aria-label$=": day"]', $day(17))
        ->fill('[data-test="date-option-2"] input[aria-label$=": day"]', $day(24))
        ->assertValue('[data-test="date-option-2"] input[aria-label$="until day (optional)"]', $day(26))
        ->fill('input[aria-label="Find a co-host"]', 'rob')
        ->assertSee('Robin')
        ->assertDontSee('Ture')
        ->click('label:has-text("Robin") button')
        ->fill('input[aria-label="Find a co-host"]', '')
        ->click('[data-test="plan-ahead"] button:has-text("Questions")')
        ->fill('[data-test="plan-questions"] input', 'Will you eat crayfish?')
        ->click('[data-test="plan-ahead"] button:has-text("Costs")')
        ->fill('[data-test="plan-costs"] input[aria-label="Cost 1"]', 'Cabin rent')
        ->fill('[data-test="plan-costs"] input[aria-label="Amount of cost 1"]', '4000')
        ->click('[data-test="plan-ahead"] button:has-text("Bring list")')
        ->click('[data-test="plan-ahead"] button:has-text("Shopping")')
        ->click('[data-test="plan-shopping"] button[aria-label="Remove this section"]')
        ->assertMissing('[data-test="plan-shopping"]')
        ->assertNoJavaScriptErrors();

    $page->script('document.fonts.ready');
    $page->screenshot(fullPage: true, filename: 'events-create-'.($dark ? 'dark' : 'light'));

    $page->press('Create event')->assertSee('Which dates work for you?')->assertNoJavaScriptErrors();

    $event = Event::query()->where('title', 'Cabin weekend')->sole();
    expect($event->date_polling)->toBeTrue()
        ->and($event->dateOptions()->get()->map(fn ($o) => $o->starts_at->setTimezone('Europe/Stockholm')->format('H:i'))->all())->toBe(['17:30', '17:30', '17:30'])
        ->and($event->dateOptions()->get()->map(fn ($o) => $o->ends_at?->setTimezone('Europe/Stockholm')->format('Y-m-d H:i'))->all())->toBe([$day(12).' 12:00', $day(19).' 12:00', $day(26).' 12:00'])
        ->and($event->hosts()->pluck('global_name')->all())->toBe(['Robin'])
        ->and($event->questions()->count())->toBe(1)
        ->and($event->expenses()->count())->toBe(1)
        ->and($event->bringItems()->count())->toBe(0);
})->with(['light' => false, 'dark' => true]);
