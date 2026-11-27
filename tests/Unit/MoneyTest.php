<?php

declare(strict_types=1);

use App\Planning\Money;
use App\Planning\SettleUp;

it('parses what people type into öre', function (string $typed, int $minor): void {
    expect(Money::toMinor($typed))->toBe($minor);
})->with([
    ['120', 12000],
    ['120.5', 12050],
    ['120,05', 12005],
    [' 0.01 ', 1],
]);

it('rejects things that are not amounts', function (string $typed): void {
    Money::toMinor($typed);
})->throws(InvalidArgumentException::class)->with(['', '-5', '1.234', 'abc', '1e3']);

it('splits equally without losing an öre', function (): void {
    $shares = Money::splitEqually(10000, [7, 8, 9]);

    expect($shares)->toBe([7 => 3334, 8 => 3333, 9 => 3333])
        ->and(array_sum($shares))->toBe(10000)
        ->and(Money::splitEqually(100, []))->toBe([]);
});

it('settles up biggest debtor to biggest creditor', function (): void {
    // A paid 300 for A, B and C; C paid 60 for B and C.
    $transfers = SettleUp::transfers([1 => 20000, 2 => -13000, 3 => -7000]);

    expect($transfers)->toBe([
        ['from' => 2, 'to' => 1, 'amount' => 13000],
        ['from' => 3, 'to' => 1, 'amount' => 7000],
    ]);
});

it('needs no transfers when everyone is even', function (): void {
    expect(SettleUp::transfers([1 => 0, 2 => 0]))->toBe([]);
});

it('always balances out, with at most n - 1 transfers', function (): void {
    $balances = [1 => 5000, 2 => 2500, 3 => -4000, 4 => -2000, 5 => -1500];
    $transfers = SettleUp::transfers($balances);

    foreach ($transfers as $transfer) {
        $balances[$transfer['from']] += $transfer['amount'];
        $balances[$transfer['to']] -= $transfer['amount'];
    }

    expect(array_filter($balances))->toBe([])->and(count($transfers))->toBeLessThanOrEqual(4);
});

it('splits by weight without losing an öre', function (): void {
    // Bo brings two guests: three shares of four.
    $shares = Money::splitByWeight(10000, [7 => 1, 8 => 3]);

    expect($shares)->toBe([7 => 2500, 8 => 7500])
        ->and(array_sum(Money::splitByWeight(1000, [1 => 1, 2 => 1, 3 => 1])))->toBe(1000)
        ->and(Money::splitByWeight(100, []))->toBe([]);
});
