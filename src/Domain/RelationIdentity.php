<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Domain;

final class RelationIdentity
{
    /** @param array<string, mixed> $relation */
    public static function key(array $relation): string
    {
        if (($relation['kind'] ?? 'actor') === 'actor') {
            return sprintf('actor:%s:%d:%d', (string) $relation['itemtype'], (int) $relation['type'], (int) $relation['link_id']);
        }
        if ($relation['kind'] === 'field') {
            return 'field:' . (string) $relation['field'];
        }

        return sprintf('%s:%s:%d', (string) $relation['kind'], (string) $relation['itemtype'], (int) $relation['link_id']);
    }
}
