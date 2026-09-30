<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\TicketFingerprint;
use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;
use Ticket;

final class TicketOperationPlanner
{
    public function __construct(
        private readonly TicketSnapshotFactory $snapshots = new TicketSnapshotFactory(),
        private readonly RelationCompatibilityService $relations = new RelationCompatibilityService(),
        private readonly UserEntitySearchService $users = new UserEntitySearchService(),
    ) {}

    /**
     * @param list<int> $confirmedRemovalIds
     * @param array<string, int> $organizationChanges
     */
    public function build(Ticket $ticket, int $replacedActorLinkId, int $newRequesterId, int $targetEntityId, array $confirmedRemovalIds = [], array $organizationChanges = []): TicketOperationPlan
    {
        $snapshot = $this->snapshots->fromTicket($ticket);
        $blockers = [];
        $replaced = [];
        if ($replacedActorLinkId > 0 || $newRequesterId > 0) {
            if ($replacedActorLinkId <= 0 || $newRequesterId <= 0) {
                $blockers[] = __('The requester correction is incomplete.', 'ticketops');
            }
            foreach ($snapshot->requesters as $actor) {
                if ((int) $actor['link_id'] === $replacedActorLinkId) {
                    $replaced = $actor;
                    break;
                }
            }
            if ($replaced === []) {
                $blockers[] = __('The requester to replace no longer exists on this ticket.', 'ticketops');
            }
            $resolved = $this->users->resolve($newRequesterId);
            if ($resolved === null || !in_array($targetEntityId, $resolved['entity_ids'], true)) {
                $blockers[] = __('The selected requester is not usable in the target entity.', 'ticketops');
            }
            foreach ($snapshot->requesters as $actor) {
                if ((int) $actor['link_id'] !== $replacedActorLinkId && $actor['itemtype'] === 'User' && (int) $actor['items_id'] === $newRequesterId) {
                    $blockers[] = __('The selected requester is already present on this ticket.', 'ticketops');
                }
            }
        }
        $organizationOptions = new OrganizationOptionService();
        foreach ($organizationChanges as $kind => $id) {
            if ($kind === 'status') {
                if ((int) $id !== Ticket::ASSIGNED) {
                    $blockers[] = __('An organization choice is not valid in the target entity.', 'ticketops');
                    unset($organizationChanges[$kind]);
                }
                continue;
            }
            if (!in_array($kind, ['category', 'location', 'group', 'technician'], true)
                || !$organizationOptions->isValid($kind, (int) $id, $targetEntityId)) {
                $blockers[] = __('An organization choice is not valid in the target entity.', 'ticketops');
                unset($organizationChanges[$kind]);
            } else {
                $organizationChanges[$kind] = (int) $id;
            }
        }
        $analysis = $this->relations->analyze($snapshot, $targetEntityId);
        $analysis['preserved'] = array_values(array_filter(
            $analysis['preserved'],
            static fn(array $actor): bool => !(($actor['kind'] ?? '') === 'actor'
                && (int) ($actor['type'] ?? 0) === 1
                && $replacedActorLinkId > 0
                && (string) ($actor['itemtype'] ?? '') === (string) ($replaced['itemtype'] ?? '')
                && (int) ($actor['link_id'] ?? 0) === $replacedActorLinkId),
        ));
        foreach ($analysis['incompatible'] as $relation) {
            if (!($relation['removable'] ?? false)) {
                $blockers[] = sprintf(__('The incompatible relation %s cannot be removed safely by TicketOps.', 'ticketops'), (string) $relation['name']);
            }
        }
        $incompatibleIds = array_map(
            static fn(array $actor): int => (int) $actor['link_id'],
            array_values(array_filter($analysis['incompatible'], static fn(array $relation): bool => (bool) ($relation['removable'] ?? false))),
        );
        $unconfirmed = array_diff($incompatibleIds, array_map('intval', $confirmedRemovalIds), [$replacedActorLinkId]);
        if ($unconfirmed !== []) {
            $blockers[] = __('Every incompatible relation must be explicitly confirmed before removal.', 'ticketops');
        }

        return new TicketOperationPlan(
            $snapshot->id,
            TicketFingerprint::fromState($snapshot->fingerprintData()),
            $snapshot->entityId,
            $targetEntityId,
            $replaced,
            $newRequesterId,
            $analysis['preserved'],
            $analysis['incompatible'],
            ['remove_relation_ids' => array_map('intval', $confirmedRemovalIds)],
            $analysis['warnings'],
            $blockers,
            [
                'notifications' => __('GLPI will emit the final native ticket update notification.', 'ticketops'),
                'rules' => __('GLPI will evaluate its native ONUPDATE rules; TicketOps requests no additional replay.', 'ticketops'),
            ],
            $organizationChanges,
        );
    }
}
