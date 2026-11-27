<?php

declare(strict_types=1);

namespace App\Planning;

/**
 * Suggests few transfers that settle everyone's balance: the biggest
 * debtor pays the biggest creditor, repeat. Not always the theoretical
 * minimum, but at most n - 1 transfers and easy to follow.
 */
final class SettleUp
{
    /**
     * @param  array<int, int>  $balances  person => balance in minor units (positive: is owed money)
     * @return list<array{from: int, to: int, amount: int}>
     */
    public static function transfers(array $balances): array
    {
        $creditors = array_filter($balances, fn (int $balance): bool => $balance > 0);
        $debtors = array_map(fn (int $balance): int => -$balance, array_filter($balances, fn (int $balance): bool => $balance < 0));
        $transfers = [];

        while ($creditors !== [] && $debtors !== []) {
            arsort($creditors);
            arsort($debtors);
            $to = array_key_first($creditors);
            $from = array_key_first($debtors);
            $amount = min($creditors[$to], $debtors[$from]);

            $transfers[] = ['from' => $from, 'to' => $to, 'amount' => $amount];

            $creditors[$to] -= $amount;
            $debtors[$from] -= $amount;

            if ($creditors[$to] === 0) {
                unset($creditors[$to]);
            }

            if ($debtors[$from] === 0) {
                unset($debtors[$from]);
            }
        }

        return $transfers;
    }
}
