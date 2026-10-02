<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use Ticket;

/** Read-only inventory. GLPI retains ownership of every child and linked record. */
final class LinkedRecordService
{
    /** @return array<string, mixed> */
    public function snapshot(Ticket $ticket): array
    {
        $id = $ticket->getID();
        $state = [];
        foreach ([
            'glpi_itilfollowups' => ['itemtype' => 'Ticket', 'items_id' => $id],
            'glpi_itilsolutions' => ['itemtype' => 'Ticket', 'items_id' => $id],
            'glpi_tickettasks' => ['tickets_id' => $id],
            'glpi_ticketvalidations' => ['tickets_id' => $id],
            'glpi_ticketcosts' => ['tickets_id' => $id],
            'glpi_items_tickets' => ['tickets_id' => $id],
            'glpi_changes_tickets' => ['tickets_id' => $id],
            'glpi_problems_tickets' => ['tickets_id' => $id],
            'glpi_projecttasks_tickets' => ['tickets_id' => $id],
            'glpi_tickets_contracts' => ['tickets_id' => $id],
            'glpi_tickets_tickets' => ['OR' => ['tickets_id_1' => $id, 'tickets_id_2' => $id]],
        ] as $table => $where) {
            $state[$table] = $this->rows($table, $where);
        }

        $relations = [];
        foreach ([
            ['glpi_changes_tickets', 'Change', 'changes_id'],
            ['glpi_problems_tickets', 'Problem', 'problems_id'],
            ['glpi_projecttasks_tickets', 'ProjectTask', 'projecttasks_id'],
            ['glpi_tickets_contracts', 'Contract', 'contracts_id'],
        ] as [$table, $itemtype, $foreignKey]) {
            foreach ($state[$table] as $link) {
                $relations[] = $this->relation($itemtype, (int) $link[$foreignKey], (int) $link['id'], 'linked_record');
            }
        }
        foreach ($state['glpi_tickets_tickets'] as $link) {
            $otherId = (int) $link['tickets_id_1'] === $id ? (int) $link['tickets_id_2'] : (int) $link['tickets_id_1'];
            $relations[] = $this->relation('Ticket', $otherId, (int) $link['id'], 'linked_record');
        }

        // This native criterion includes documents on followups, tasks, solutions and validations.
        // Bypass display filtering only for server-side safety checks, never to render private content.
        $links = $this->rows('glpi_documents_items', [$ticket->getAssociatedDocumentsCriteria(true)]);
        $state['document_links'] = $links;
        $state['documents'] = [];
        $documentIds = array_unique(array_map(static fn(array $link): int => (int) $link['documents_id'], $links));
        sort($documentIds);
        foreach ($documentIds as $documentId) {
            $document = $this->relation('Document', $documentId, $documentId, 'document');
            $allLinks = $this->rows('glpi_documents_items', ['documents_id' => $documentId]);
            $ownedIds = array_column($links, 'id');
            $document['shared'] = array_diff(array_column($allLinks, 'id'), $ownedIds) !== [];
            $state['documents'][] = ['record' => $document, 'links' => $allLinks];
            $relations[] = $document;
        }
        $state['relations'] = $relations;

        return $state;
    }

    /**
     * @param array<mixed> $where
     * @return list<array<string, mixed>>
     */
    private function rows(string $table, array $where): array
    {
        global $DB;

        $rows = [];
        foreach ($DB->request(['FROM' => $table, 'WHERE' => $where, 'ORDERBY' => 'id']) as $row) {
            ksort($row);
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string, scalar|null> */
    private function relation(string $itemtype, int $id, int $linkId, string $kind): array
    {
        $item = match ($itemtype) {
            'Ticket' => new \Ticket(),
            'Change' => new \Change(),
            'Problem' => new \Problem(),
            'ProjectTask' => new \ProjectTask(),
            'Contract' => new \Contract(),
            'Document' => new \Document(),
            default => throw new \LogicException('Unsupported linked record type.'),
        };
        $exists = $item->getFromDB($id);
        $entity = $exists ? (int) $item->getEntityID() : -1;

        return [
            'kind' => $kind,
            'itemtype' => $itemtype,
            'items_id' => $id,
            'link_id' => $linkId,
            'name' => $itemtype . ' #' . $id,
            'removable' => false,
            'exists' => $exists,
            'entity_id' => $entity,
            'recursive' => $exists && $item->isRecursive(),
        ];
    }

    /** @param array<string, mixed> $relation */
    public function isCompatible(array $relation, int $targetEntity, int $sourceEntity): bool
    {
        // A title/organization correction is not a transfer and must not reclassify existing links.
        // Inventory existing native record links without changing their transfer policy.
        // Extra blocking of these links remains a separate user policy decision.
        if (($relation['kind'] ?? '') === 'linked_record') {
            return true;
        }
        if ($sourceEntity === $targetEntity) {
            return true;
        }
        if (!($relation['exists'] ?? false)) {
            return false;
        }
        $owner = (int) $relation['entity_id'];
        $visible = $owner < 0 || $owner === $targetEntity
            || (($relation['recursive'] ?? false) && in_array($targetEntity, array_map('intval', getSonsOf('glpi_entities', $owner)), true));
        if ($visible) {
            return true;
        }

        // An exclusive attachment remains with its ticket through GLPI's native access rules.
        // A shared document outside the target scope must not expose content from another record.
        return ($relation['kind'] ?? '') === 'document' && !($relation['shared'] ?? false);
    }
}
