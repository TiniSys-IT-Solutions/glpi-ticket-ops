<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Install;

final class RightSet
{
    /**
     * @param list<string> $required
     * @param array<string, int> $existing
     * @return list<string>
     */
    public static function missing(array $required, array $existing): array
    {
        return array_values(array_filter(
            $required,
            static fn(string $right): bool => !array_key_exists($right, $existing),
        ));
    }
}
