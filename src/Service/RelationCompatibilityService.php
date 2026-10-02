<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\RelationIdentity;
use GlpiPlugin\Ticketops\Domain\TicketSnapshot;

final class RelationCompatibilityService
{
    /** @return array{preserved: list<array<string, mixed>>, incompatible: list<array<string, mixed>>, warnings: list<string>} */
    public function analyze(TicketSnapshot $ticket, int $targetEntity): array
    {
        $preserved = [];
        $incompatible = [];
        foreach ([...$ticket->requesters, ...$ticket->assignees, ...$ticket->observers] as $actor) {
            $actor['kind'] = 'actor';
            $actor['removable'] = true;
            $compatible = $ticket->entityId === $targetEntity || $this->actorIsValid($actor, $targetEntity);
            if ($compatible) {
                $preserved[] = $actor;
            } else {
                $incompatible[] = $actor;
            }
        }
        foreach ([
            ['ITILCategory', 'itilcategories_id', $ticket->categoryId, -101],
            ['Location', 'locations_id', $ticket->locationId, -102],
            ['TicketTemplate', 'tickettemplates_id', $ticket->templateId, -103],
            ['SLA', 'slas_id_ttr', $ticket->slaId, -104],
            ['OLA', 'olas_id_ttr', $ticket->olaId, -105],
            ['SLA', 'slas_id_tto', $ticket->slaOwnId, -106],
            ['OLA', 'olas_id_tto', $ticket->olaOwnId, -107],
        ] as [$itemtype, $field, $id, $linkId]) {
            if ($id <= 0) {
                continue;
            }
            $relation = ['link_id' => $linkId, 'kind' => 'field', 'field' => $field, 'itemtype' => $itemtype, 'items_id' => $id, 'name' => \Dropdown::getDropdownName($this->tableFor($itemtype), $id), 'removable' => true];
            if ($ticket->entityId === $targetEntity || $this->itemIsValid($itemtype, $id, $targetEntity)) {
                $preserved[] = $relation;
            } else {
                $incompatible[] = $relation;
            }
        }
        foreach ($this->associatedItems($ticket->id, $targetEntity) as $relation) {
            if ($relation['compatible']) {
                $preserved[] = $relation;
            } else {
                $incompatible[] = $relation;
            }
        }
        foreach ($ticket->linkedState['relations'] ?? [] as $relation) {
            if ((new LinkedRecordService())->isCompatible($relation, $targetEntity, $ticket->entityId)) {
                $preserved[] = $relation;
            } else {
                $incompatible[] = $relation;
            }
        }
        $withKey = static fn(array $relation): array => $relation + ['key' => RelationIdentity::key($relation)];
        $preserved = array_map($withKey, $preserved);
        $incompatible = array_map($withKey, $incompatible);
        $warnings = [__('GLPI will evaluate native ONUPDATE ticket rules during execution.', 'ticketops')];

        return ['preserved' => $preserved, 'incompatible' => $incompatible, 'warnings' => $warnings];
    }

    private function itemIsValid(string $itemtype, int $id, int $targetEntity): bool
    {
        if (!class_exists($itemtype)) {
            return false;
        }
        $item = new $itemtype();
        if (!method_exists($item, 'getFromDB') || !$item->getFromDB($id) || !method_exists($item, 'getEntityID')) {
            return false;
        }
        $entityId = (int) $item->getEntityID();
        if ($entityId < 0 || $entityId === $targetEntity
            || ($itemtype === 'Location' && in_array($entityId, array_map('intval', getSonsOf('glpi_entities', $targetEntity)), true))) {
            return true;
        }

        return method_exists($item, 'isRecursive') && $item->isRecursive()
            && in_array($targetEntity, array_map('intval', getSonsOf('glpi_entities', $entityId)), true);
    }

    /** @return list<array<string, mixed>> */
    private function associatedItems(int $ticketId, int $targetEntity): array
    {
        global $DB;

        $relations = [];
        foreach ($DB->request(['SELECT' => ['id', 'itemtype', 'items_id'], 'FROM' => 'glpi_items_tickets', 'WHERE' => ['tickets_id' => $ticketId]]) as $row) {
            $itemtype = (string) $row['itemtype'];
            $id = (int) $row['items_id'];
            $relations[] = [
                'link_id' => -200000 - (int) $row['id'],
                'kind' => 'associated_item',
                'itemtype' => $itemtype,
                'items_id' => $id,
                'name' => $itemtype . ' #' . $id,
                'removable' => false,
                'compatible' => $this->itemIsValid($itemtype, $id, $targetEntity),
            ];
        }

        return $relations;
    }

    private function tableFor(string $itemtype): string
    {
        return match ($itemtype) {
            'ITILCategory' => 'glpi_itilcategories',
            'Location' => 'glpi_locations',
            'TicketTemplate' => 'glpi_tickettemplates',
            'SLA' => 'glpi_slas',
            'OLA' => 'glpi_olas',
            default => '',
        };
    }

    /** @param array<string, mixed> $actor */
    private function actorIsValid(array $actor, int $targetEntity): bool
    {
        $id = (int) $actor['items_id'];
        if ($actor['itemtype'] === 'User') {
            return $id === 0 || \User::isValidUserForEntity($id, $targetEntity);
        }
        if ($actor['itemtype'] === 'Group') {
            $group = new \Group();
            if (!$group->getFromDB($id)) {
                return false;
            }
            $entityId = (int) ($group->fields['entities_id'] ?? -1);

            return $entityId === $targetEntity
                || ((bool) ($group->fields['is_recursive'] ?? false) && in_array($targetEntity, array_map('intval', getSonsOf('glpi_entities', $entityId)), true));
        }

        if ($actor['itemtype'] === 'Supplier') {
            return $this->itemIsValid('Supplier', $id, $targetEntity);
        }

        return false;
    }
}
