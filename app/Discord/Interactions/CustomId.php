<?php

declare(strict_types=1);

namespace App\Discord\Interactions;

/**
 * Component/modal custom IDs: "quote:<action>:<quote id>".
 */
final readonly class CustomId
{
    public const string SAVE = 'save';

    public const string EDIT = 'edit';

    public const string CANCEL = 'cancel';

    public const string MODAL = 'modal';

    private function __construct(
        public string $action,
        public int $quoteId,
    ) {}

    public static function for(string $action, int $quoteId): string
    {
        return "quote:{$action}:{$quoteId}";
    }

    public static function parse(mixed $customId): ?self
    {
        if (! is_string($customId) || preg_match('/^quote:(save|edit|cancel|modal):(\d+)$/', $customId, $match) !== 1) {
            return null;
        }

        return new self($match[1], (int) $match[2]);
    }
}
