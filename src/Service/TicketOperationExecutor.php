<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\TicketFingerprint;
use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;
use GlpiPlugin\Ticketops\Security\TicketOperationGuard;
use RuntimeException;
use Ticket;

final class TicketOperationExecutor
{
    public function __construct(
        private readonly TicketSnapshotFactory $snapshots = new TicketSnapshotFactory(),
        private readonly TicketOperationGuard $guard = new TicketOperationGuard(),
    ) {}

    public function execute(TicketOperationPlan $plan): Ticket
    {
        global $DB;

        $ticket = new Ticket();
        if (!$ticket->getFromDB($plan->ticketId) || !$this->guard->canOperate($ticket, $plan->targetEntityId)) {
            throw new RuntimeException(__('The ticket is unavailable or you are not allowed to update it.', 'ticketops'));
        }
        if (!$plan->isExecutable()) {
            throw new RuntimeException(__('This plan contains blocking issues.', 'ticketops'));
        }
        $snapshot = $this->snapshots->fromTicket($ticket);
        if (!hash_equals($plan->fingerprint, TicketFingerprint::fromState($snapshot->fingerprintData()))) {
            throw new RuntimeException(__('The ticket changed after the preview. Create a new preview.', 'ticketops'));
        }
        $input = $this->buildNativeInput($plan, $snapshot);
        $DB->beginTransaction();
        try {
            if (!$ticket->update($input)) {
                throw new RuntimeException(__('GLPI refused the ticket update.', 'ticketops'));
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollback();
            throw $exception;
        }
        if (!$ticket->getFromDB($plan->ticketId)) {
            throw new RuntimeException(__('The updated ticket could not be reloaded.', 'ticketops'));
        }

        return $ticket;
    }

    /** @return array<string, mixed> */
    private function buildNativeInput(TicketOperationPlan $plan, \GlpiPlugin\Ticketops\Domain\TicketSnapshot $snapshot): array
    {
        $removalDecision = $plan->decisions['remove_relation_ids'] ?? [];
        $removed = is_array($removalDecision) ? array_map('intval', $removalDecision) : [];
        $actors = [...$snapshot->requesters, ...$snapshot->assignees, ...$snapshot->observers];
        $input = ['id' => $plan->ticketId, 'entities_id' => $plan->targetEntityId];
        if (isset($plan->organizationChanges['category'])) {
            $input['itilcategories_id'] = $plan->organizationChanges['category'];
        }
        if (isset($plan->organizationChanges['location'])) {
            $input['locations_id'] = $plan->organizationChanges['location'];
        }
        if (isset($plan->organizationChanges['status'])) {
            $input['status'] = $plan->organizationChanges['status'];
        }
        foreach ($plan->incompatibilities as $relation) {
            if (($relation['kind'] ?? '') === 'field' && in_array((int) $relation['link_id'], $removed, true)) {
                $input[(string) $relation['field']] = 0;
            }
        }
        $typeMap = ['requester' => 1, 'assign' => 2, 'observer' => 3];
        foreach ($typeMap as $name => $type) {
            foreach (['User' => 'users', 'Group' => 'groups', 'Supplier' => 'suppliers'] as $itemtype => $prefix) {
                $key = "_{$prefix}_id_{$name}";
                $deletedKey = "_{$prefix}_id_{$name}_deleted";
                $input[$key] = [];
                foreach ($actors as $actor) {
                    if ($actor['itemtype'] !== $itemtype || (int) $actor['type'] !== $type) {
                        continue;
                    }
                    $isReplacement = $type === 1
                        && $itemtype === (string) ($plan->replacedActor['itemtype'] ?? '')
                        && (int) $actor['link_id'] === (int) ($plan->replacedActor['link_id'] ?? 0);
                    $isConfirmedRemoval = $this->isConfirmedActorRemoval($actor, $plan->incompatibilities, $removed);
                    $desiredAssignment = $type === 2 ? match ($itemtype) {
                        'User' => $plan->organizationChanges['technician'] ?? null,
                        default => null,
                    } : null;
                    $isReassigned = $desiredAssignment !== null && (int) $actor['items_id'] !== (int) $desiredAssignment;
                    if ($isReplacement || $isConfirmedRemoval || $isReassigned) {
                        if ((int) $actor['link_id'] > 0) {
                            $input[$deletedKey][] = ['id' => (int) $actor['link_id'], 'itemtype' => $itemtype];
                        }
                        continue;
                    }
                    $input = $this->appendListValue($input, $key, (int) $actor['items_id']);
                    if ($itemtype === 'User') {
                        $input = $this->appendNotificationValue($input, "_users_id_{$name}_notif", 'use_notification', (int) $actor['use_notification']);
                        $input = $this->appendNotificationValue($input, "_users_id_{$name}_notif", 'alternative_email', (string) $actor['alternative_email']);
                    }
                }
            }
        }
        if ($plan->newRequesterId > 0) {
            $input = $this->appendListValue($input, '_users_id_requester', $plan->newRequesterId);
            $input = $this->appendNotificationValue($input, '_users_id_requester_notif', 'use_notification', 1);
            $input = $this->appendNotificationValue($input, '_users_id_requester_notif', 'alternative_email', '');
        }
        if (isset($plan->organizationChanges['technician'])
            && !in_array($plan->organizationChanges['technician'], $input['_users_id_assign'], true)) {
            $input = $this->appendListValue($input, '_users_id_assign', $plan->organizationChanges['technician']);
            $input = $this->appendNotificationValue($input, '_users_id_assign_notif', 'use_notification', 1);
            $input = $this->appendNotificationValue($input, '_users_id_assign_notif', 'alternative_email', '');
        }
        if (isset($plan->organizationChanges['observer'])
            && !in_array($plan->organizationChanges['observer'], $input['_users_id_observer'], true)) {
            $input = $this->appendListValue($input, '_users_id_observer', $plan->organizationChanges['observer']);
            $input = $this->appendNotificationValue($input, '_users_id_observer_notif', 'use_notification', 1);
            $input = $this->appendNotificationValue($input, '_users_id_observer_notif', 'alternative_email', '');
        }

        return $input;
    }

    /**
     * @param array<string, mixed> $actor
     * @param list<array<string, scalar|null>> $incompatibilities
     * @param list<int> $confirmedIds
     */
    private function isConfirmedActorRemoval(array $actor, array $incompatibilities, array $confirmedIds): bool
    {
        foreach ($incompatibilities as $relation) {
            if (($relation['kind'] ?? '') !== 'actor'
                || !in_array((int) ($relation['link_id'] ?? 0), $confirmedIds, true)) {
                continue;
            }
            if ((int) ($relation['link_id'] ?? 0) === (int) $actor['link_id']
                && (string) ($relation['itemtype'] ?? '') === (string) $actor['itemtype']
                && (int) ($relation['type'] ?? 0) === (int) $actor['type']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function appendListValue(array $input, string $key, int $value): array
    {
        $values = $input[$key] ?? [];
        $values = is_array($values) ? $values : [];
        $values[] = $value;
        $input[$key] = $values;

        return $input;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function appendNotificationValue(array $input, string $key, string $field, int|string $value): array
    {
        $notification = $input[$key] ?? [];
        $notification = is_array($notification) ? $notification : [];
        $values = $notification[$field] ?? [];
        $values = is_array($values) ? $values : [];
        $values[] = $value;
        $notification[$field] = $values;
        $input[$key] = $notification;

        return $input;
    }
}
