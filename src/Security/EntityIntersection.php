<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Security;

final class EntityIntersection
{
    /**
     * @param list<int> $requesterEntities
     * @param list<int> $operatorEntities
     * @return list<int>
     */
    public static function validTargets(array $requesterEntities, array $operatorEntities): array
    {
        $targets = array_values(array_unique(array_map('intval', array_intersect($requesterEntities, $operatorEntities))));
        sort($targets);

        return $targets;
    }

    /** @param list<int> $targets */
    public static function suggestedTarget(array $targets, ?int $preferredEntity): ?int
    {
        if (count($targets) === 1) {
            return $targets[0];
        }

        return $preferredEntity !== null && in_array($preferredEntity, $targets, true) ? $preferredEntity : null;
    }
}
