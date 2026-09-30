<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use Group;
use Profile_User;
use User;

final class OrganizationOptionService
{
    /** @return array{results: list<array{id: int, text: string}>, more: bool} */
    public function search(string $kind, string $term, int $entityId, int $page = 1, int $limit = 20): array
    {
        return $kind === 'technician'
            ? $this->searchTechnicians($term, $entityId, $page, $limit)
            : $this->searchEntityItems($kind, $term, $entityId, $page, $limit);
    }

    public function isValid(string $kind, int $id, int $entityId): bool
    {
        if ($id <= 0) {
            return false;
        }
        if ($kind === 'technician') {
            $user = new User();

            return $user->getFromDB($id)
                && (bool) ($user->fields['is_active'] ?? false)
                && !(bool) ($user->fields['is_deleted'] ?? false)
                && in_array($entityId, array_map('intval', Profile_User::getUserEntities($id, true)), true);
        }
        $class = $this->classFor($kind);
        if ($class === null) {
            return false;
        }
        $item = new $class();
        if (!$item->getFromDB($id)) {
            return false;
        }
        if ($kind === 'group' && !(bool) ($item->fields['is_assign'] ?? false)) {
            return false;
        }
        $owner = (int) ($item->fields['entities_id'] ?? -1);

        return $owner === $entityId || ((bool) ($item->fields['is_recursive'] ?? false)
            && in_array($entityId, array_map('intval', getSonsOf('glpi_entities', $owner)), true));
    }

    /** @return array{results: list<array{id: int, text: string}>, more: bool} */
    private function searchTechnicians(string $term, int $entityId, int $page, int $limit): array
    {
        $result = (new UserEntitySearchService())->search($term, $page, $limit);
        $users = [];
        foreach ($result['results'] as $user) {
            if (in_array($entityId, array_map('intval', $user['entity_ids']), true)) {
                $users[] = ['id' => (int) $user['id'], 'text' => trim((string) $user['label'] . ' — ' . (string) $user['email'])];
            }
        }

        return ['results' => $users, 'more' => $result['more']];
    }

    /** @return array{results: list<array{id: int, text: string}>, more: bool} */
    private function searchEntityItems(string $kind, string $term, int $entityId, int $page, int $limit): array
    {
        global $DB;

        $class = $this->classFor($kind);
        if ($class === null || mb_strlen(trim($term)) < 2) {
            return ['results' => [], 'more' => false];
        }
        $table = $class::getTable();
        $where = ['name' => ['LIKE', '%' . trim($term) . '%']];
        if ($kind === 'group') {
            $where['is_assign'] = 1;
        }
        $rows = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => $table,
            'WHERE' => $where,
            'ORDER' => ['name'],
            'START' => (max(1, $page) - 1) * $limit,
            'LIMIT' => $limit + 1,
        ]);
        $results = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if ($this->isValid($kind, $id, $entityId)) {
                $results[] = ['id' => $id, 'text' => (string) $row['name']];
            }
        }

        return ['results' => array_slice($results, 0, $limit), 'more' => count($results) > $limit];
    }

    /** @return class-string<\CommonGLPI>|null */
    private function classFor(string $kind): ?string
    {
        return match ($kind) {
            'category' => \ITILCategory::class,
            'location' => \Location::class,
            'group' => Group::class,
            default => null,
        };
    }
}
