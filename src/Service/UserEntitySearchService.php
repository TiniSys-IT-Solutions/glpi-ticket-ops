<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Security\EntityIntersection;
use Profile_User;
use Session;
use User;

final class UserEntitySearchService
{
    /** @return array{user: User, entity_ids: list<int>, suggested_entity_id: int|null}|null */
    public function resolve(int $userId): ?array
    {
        $user = new User();
        if ($userId <= 1 || !$user->getFromDB($userId) || !(bool) ($user->fields['is_active'] ?? false)
            || (bool) ($user->fields['is_deleted'] ?? false) || !$this->isWithinValidityDates($user)) {
            return null;
        }
        $valid = EntityIntersection::validTargets(
            array_map('intval', Profile_User::getUserEntities($userId, true)),
            array_map('intval', Session::getActiveEntities()),
        );
        if ($valid === []) {
            return null;
        }

        return ['user' => $user, 'entity_ids' => $valid, 'suggested_entity_id' => EntityIntersection::suggestedTarget($valid, (int) ($user->fields['entities_id'] ?? 0))];
    }

    private function isWithinValidityDates(User $user): bool
    {
        $now = time();
        $begin = (string) ($user->fields['begin_date'] ?? '');
        $end = (string) ($user->fields['end_date'] ?? '');

        return ($begin === '' || strtotime($begin) <= $now) && ($end === '' || strtotime($end) >= $now);
    }
}
