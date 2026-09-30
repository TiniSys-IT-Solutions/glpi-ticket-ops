<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Security\EntityIntersection;
use Profile_User;
use Session;
use User;

final class UserEntitySearchService
{
    /** @return array{results: list<array<string, mixed>>, more: bool} */
    public function search(string $term, int $page = 1, int $limit = 20): array
    {
        global $DB;

        $term = trim($term);
        $page = max(1, $page);
        $limit = max(1, min(50, $limit));
        if (mb_strlen($term) < 2) {
            return ['results' => [], 'more' => false];
        }
        $where = [
            'glpi_users.is_active' => 1,
            'glpi_users.is_deleted' => 0,
            'glpi_users.id' => ['>', 1],
            'OR' => [
                'glpi_users.name' => ['LIKE', "%{$term}%"],
                'glpi_users.firstname' => ['LIKE', "%{$term}%"],
                'glpi_users.realname' => ['LIKE', "%{$term}%"],
                'glpi_useremails.email' => ['LIKE', "%{$term}%"],
            ],
            'AND' => [
                ['OR' => [['glpi_users.begin_date' => null], ['glpi_users.begin_date' => ['<=', date('Y-m-d H:i:s')]]]],
                ['OR' => [['glpi_users.end_date' => null], ['glpi_users.end_date' => ['>=', date('Y-m-d H:i:s')]]]],
            ],
        ];
        $rows = $DB->request([
            'SELECT' => ['glpi_users.id', 'glpi_users.name', 'glpi_users.firstname', 'glpi_users.realname', 'glpi_users.entities_id', 'glpi_useremails.email'],
            'DISTINCT' => true,
            'FROM' => 'glpi_users',
            'LEFT JOIN' => ['glpi_useremails' => ['ON' => ['glpi_users' => 'id', 'glpi_useremails' => 'users_id']]],
            'WHERE' => $where,
            'ORDER' => ['glpi_users.realname', 'glpi_users.firstname', 'glpi_users.name'],
            'START' => ($page - 1) * $limit,
            'LIMIT' => $limit + 1,
        ]);
        $results = [];
        $seen = [];
        $technicianEntities = array_map('intval', Session::getActiveEntities());
        foreach ($rows as $row) {
            $userId = (int) $row['id'];
            if (isset($seen[$userId])) {
                continue;
            }
            $valid = EntityIntersection::validTargets(
                array_map('intval', Profile_User::getUserEntities($userId, true)),
                $technicianEntities,
            );
            if ($valid === []) {
                continue;
            }
            $seen[$userId] = true;
            $preferred = (int) $row['entities_id'];
            $results[] = [
                'id' => $userId,
                'label' => trim(sprintf('%s %s (%s)', $row['firstname'], $row['realname'], $row['name'])),
                'email' => (string) ($row['email'] ?? ''),
                'entity_ids' => $valid,
                'entities' => array_map(static fn(int $entityId): array => ['id' => $entityId, 'name' => \Dropdown::getDropdownName('glpi_entities', $entityId)], $valid),
                'suggested_entity_id' => EntityIntersection::suggestedTarget($valid, $preferred),
            ];
            if (count($results) > $limit) {
                break;
            }
        }
        $more = count($results) > $limit;

        return ['results' => array_slice($results, 0, $limit), 'more' => $more];
    }

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
