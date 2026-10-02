<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\RelationIdentity;
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
     * @param list<string> $confirmedRemovalKeys
     * @param array<string, int> $organizationChanges
     */
    public function build(Ticket $ticket, string $replacedActorKey, int $newRequesterId, int $targetEntityId, array $confirmedRemovalKeys = [], array $organizationChanges = [], ?string $ticketTitle = null): TicketOperationPlan
    {
        $snapshot = $this->snapshots->fromTicket($ticket, true);
        $blockers = [];
        if ($ticketTitle !== null) {
            $ticketTitle = trim($ticketTitle);
            if ($ticketTitle === '' || mb_strlen($ticketTitle) > 255) {
                $blockers[] = __('The ticket title must contain between 1 and 255 characters.', 'ticketops');
            }
            if ($ticketTitle === $snapshot->title) {
                $ticketTitle = null;
            }
        }
        $replaced = [];
        if ($replacedActorKey !== '' || $newRequesterId > 0) {
            if ($replacedActorKey === '' || $newRequesterId <= 0) {
                $blockers[] = __('The requester correction is incomplete.', 'ticketops');
            }
            foreach ($snapshot->requesters as $actor) {
                if (RelationIdentity::key($actor) === $replacedActorKey) {
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
                if (RelationIdentity::key($actor) !== $replacedActorKey && $actor['itemtype'] === 'User' && (int) $actor['items_id'] === $newRequesterId) {
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
            if (!in_array($kind, ['category', 'location', 'technician', 'observer'], true)
                || !$organizationOptions->isValid($kind, (int) $id, $targetEntityId, isset($organizationChanges['status']))) {
                $blockers[] = __('An organization choice is not valid in the target entity.', 'ticketops');
                unset($organizationChanges[$kind]);
            } else {
                $organizationChanges[$kind] = (int) $id;
            }
        }
        $analysis = $this->relations->analyze($snapshot, $targetEntityId);
        // Validate the final choice rather than asking to remove a field already replaced.
        $replacedFields = [];
        foreach (['category' => 'itilcategories_id', 'location' => 'locations_id'] as $kind => $field) {
            if (isset($organizationChanges[$kind])) {
                $replacedFields[] = $field;
            }
        }
        $replacesRelation = static function (array $relation) use ($replacedFields, $replacedActorKey, $organizationChanges): bool {
            if (($relation['kind'] ?? '') === 'field') {
                return in_array($relation['field'] ?? '', $replacedFields, true);
            }
            if (($relation['kind'] ?? '') !== 'actor') {
                return false;
            }
            if ((int) $relation['type'] === 1 && $replacedActorKey !== '' && RelationIdentity::key($relation) === $replacedActorKey) {
                return true;
            }
            return (int) $relation['type'] === 2 && $relation['itemtype'] === 'User'
                && isset($organizationChanges['technician'])
                && (int) $relation['items_id'] !== $organizationChanges['technician'];
        };
        $keepRelation = static fn(array $relation): bool => !$replacesRelation($relation);
        $analysis['incompatible'] = array_values(array_filter($analysis['incompatible'], $keepRelation));
        $analysis['preserved'] = array_values(array_filter($analysis['preserved'], $keepRelation));
        foreach ($analysis['incompatible'] as $relation) {
            if (!($relation['removable'] ?? false)) {
                $blockers[] = sprintf(__('The incompatible relation %s cannot be removed safely by TicketOps.', 'ticketops'), (string) $relation['name']);
            }
        }
        $incompatibleKeys = array_map(
            RelationIdentity::key(...),
            array_values(array_filter($analysis['incompatible'], static fn(array $relation): bool => (bool) ($relation['removable'] ?? false))),
        );
        // Accept only removals belonging to this plan, using type and role as well as link ID.
        $confirmedRemovalKeys = array_values(array_unique(array_intersect($confirmedRemovalKeys, $incompatibleKeys)));
        sort($confirmedRemovalKeys);
        $unconfirmed = array_diff($incompatibleKeys, $confirmedRemovalKeys);
        if ($unconfirmed !== []) {
            $blockers[] = __('Every incompatible relation must be explicitly confirmed before removal.', 'ticketops');
        }

        $plan = new TicketOperationPlan(
            $snapshot->id,
            TicketFingerprint::fromState($snapshot->fingerprintData()),
            $snapshot->entityId,
            $targetEntityId,
            $replaced,
            $newRequesterId,
            $analysis['preserved'],
            $analysis['incompatible'],
            ['remove_relation_keys' => $confirmedRemovalKeys],
            $analysis['warnings'],
            $blockers,
            [
                'notifications' => __('GLPI will emit the final native ticket update notification.', 'ticketops'),
                'rules' => __('GLPI will evaluate its native ONUPDATE rules; TicketOps requests no additional replay.', 'ticketops'),
            ],
            $organizationChanges,
            $ticketTitle,
        );

        return $plan->withBlockers((new TicketOperationValidator())->validate($ticket, $plan, $snapshot));
    }
}
