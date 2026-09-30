<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use CommonITILActor;
use GlpiPlugin\Ticketops\Domain\TicketSnapshot;
use Ticket;

final class TicketSnapshotFactory
{
    public function fromTicket(Ticket $ticket): TicketSnapshot
    {
        // GLPI lazily loads actors. Force the native actor collections to be
        // populated before both normalizing them and using the native counts.
        $ticket->loadActors();

        return new TicketSnapshot(
            (int) $ticket->getID(),
            (string) ($ticket->fields['name'] ?? ''),
            (int) ($ticket->fields['entities_id'] ?? 0),
            (int) ($ticket->fields['itilcategories_id'] ?? 0),
            (int) ($ticket->fields['locations_id'] ?? 0),
            (int) ($ticket->fields['tickettemplates_id'] ?? 0),
            (int) ($ticket->fields['slas_id_ttr'] ?? 0),
            (int) ($ticket->fields['olas_id_ttr'] ?? 0),
            (string) ($ticket->fields['date_mod'] ?? ''),
            $this->normalize($ticket->getActorsForType(CommonITILActor::REQUESTER), CommonITILActor::REQUESTER),
            $this->normalize($ticket->getActorsForType(CommonITILActor::ASSIGN), CommonITILActor::ASSIGN),
            $this->normalize($ticket->getActorsForType(CommonITILActor::OBSERVER), CommonITILActor::OBSERVER),
            $ticket->countUsers(CommonITILActor::ASSIGN),
            $ticket->countGroups(CommonITILActor::ASSIGN),
            $ticket->countSuppliers(CommonITILActor::ASSIGN),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $actors
     * @return list<array<string, mixed>>
     */
    private function normalize(array $actors, int $role): array
    {
        $normalized = [];
        foreach ($actors as $actor) {
            $normalized[] = [
                'link_id' => (int) ($actor['id'] ?? 0),
                'type' => $role,
                'itemtype' => (string) ($actor['itemtype'] ?? ''),
                'items_id' => (int) ($actor['items_id'] ?? 0),
                'name' => (string) ($actor['text'] ?? $actor['title'] ?? ''),
                'use_notification' => (int) ($actor['use_notification'] ?? 1),
                'alternative_email' => (string) ($actor['alternative_email'] ?? $actor['default_email'] ?? ''),
            ];
        }

        usort($normalized, static fn(array $a, array $b): int => [$a['itemtype'], $a['items_id'], $a['link_id']] <=> [$b['itemtype'], $b['items_id'], $b['link_id']]);

        return $normalized;
    }
}
