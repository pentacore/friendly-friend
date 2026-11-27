<?php

declare(strict_types=1);

namespace App\Planning;

use InvalidArgumentException;

/**
 * Money as integer minor units (öre, cents). Parsing from what people
 * type and splitting without losing an öre.
 */
final class Money
{
    /** "120", "120.5", "120,50" → 12050. */
    public const string PATTERN = '/^\d{1,7}([.,]\d{1,2})?$/';

    public static function toMinor(string $amount): int
    {
        $amount = trim($amount);

        if (preg_match(self::PATTERN, $amount) !== 1) {
            throw new InvalidArgumentException("Not an amount: {$amount}");
        }

        [$whole, $fraction] = array_pad(preg_split('/[.,]/', $amount) ?: [], 2, '');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    /**
     * Split an amount equally. The öre that don't divide evenly go to the
     * first people, one each, so the shares always add up.
     *
     * @param  list<int>  $people
     * @return array<int, int> person => share
     */
    public static function splitEqually(int $amount, array $people): array
    {
        $people = array_values(array_unique($people));
        $count = count($people);

        if ($count === 0) {
            return [];
        }

        $base = intdiv($amount, $count);
        $remainder = $amount - $base * $count;
        $shares = [];

        foreach ($people as $index => $person) {
            $shares[$person] = $base + ($index < $remainder ? 1 : 0);
        }

        return $shares;
    }

    /**
     * Split an amount by weight (someone bringing two guests weighs 3).
     * Leftover öre go to the largest remainders, so shares always add up.
     *
     * @param  array<int, int>  $weights  person => weight (> 0)
     * @return array<int, int> person => share
     */
    public static function splitByWeight(int $amount, array $weights): array
    {
        $weights = array_filter($weights, fn (int $weight): bool => $weight > 0);
        $total = array_sum($weights);

        if ($total === 0) {
            return [];
        }

        $shares = [];
        $remainders = [];

        foreach ($weights as $person => $weight) {
            $shares[$person] = intdiv($amount * $weight, $total);
            $remainders[$person] = ($amount * $weight) % $total;
        }

        arsort($remainders);

        foreach (array_slice(array_keys($remainders), 0, $amount - array_sum($shares)) as $person) {
            $shares[$person]++;
        }

        return $shares;
    }
}
