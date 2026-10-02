<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use Profile_User;
use Ticket;
use User;

final class OrganizationOptionService
{
    public function isValid(string $kind, int $id, int $entityId, bool $selfAssignment = false): bool
    {
        if ($id <= 0) {
            return false;
        }
        if (in_array($kind, ['technician', 'observer'], true)) {
            if (!User::isValidUserForEntity($id, $entityId)) {
                return false;
            }
            return $kind !== 'technician' || in_array($entityId, array_map('intval', Profile_User::getUserEntitiesForRight(
                $id,
                'ticket',
                $selfAssignment ? (Ticket::OWN | Ticket::STEAL) : Ticket::OWN,
                true,
            )), true);
        }
        $class = $this->classFor($kind);
        if ($class === null) {
            return false;
        }
        $item = new $class();
        if (!$item->getFromDB($id)) {
            return false;
        }
        $owner = (int) ($item->fields['entities_id'] ?? -1);

        return $owner === $entityId
            || ($kind === 'location' && in_array($owner, array_map('intval', getSonsOf('glpi_entities', $entityId)), true))
            || ((bool) ($item->fields['is_recursive'] ?? false)
            && in_array($entityId, array_map('intval', getSonsOf('glpi_entities', $owner)), true));
    }

    /** @return class-string<\CommonGLPI>|null */
    private function classFor(string $kind): ?string
    {
        return match ($kind) {
            'category' => \ITILCategory::class,
            'location' => \Location::class,
            default => null,
        };
    }
}
