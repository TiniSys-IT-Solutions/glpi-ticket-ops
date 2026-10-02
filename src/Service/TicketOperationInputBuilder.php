<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\RelationIdentity;
use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;

final class TicketOperationInputBuilder
{
    /** @return array<string, mixed> */
    public function build(TicketOperationPlan $plan, \GlpiPlugin\Ticketops\Domain\TicketSnapshot $snapshot): array
    {
        $removalDecision = $plan->decisions['remove_relation_keys'] ?? [];
        $removed = is_array($removalDecision) ? $removalDecision : [];
        $actors = [...$snapshot->requesters, ...$snapshot->assignees, ...$snapshot->observers];
        $input = ['id' => $plan->ticketId, 'entities_id' => $plan->targetEntityId];
        if ($plan->ticketTitle !== null) {
            $input['name'] = $plan->ticketTitle;
        }
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
            if (($relation['kind'] ?? '') === 'field' && in_array(RelationIdentity::key($relation), $removed, true)) {
                $field = (string) $relation['field'];
                if (!array_key_exists($field, $input)) {
                    $input[$field] = 0;
                }
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
                            $input[$deletedKey][] = ['id' => (int) $actor['link_id'], 'itemtype' => $itemtype, 'items_id' => (int) $actor['items_id'], 'type' => $type];
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
     * @param list<scalar> $confirmedKeys
     */
    private function isConfirmedActorRemoval(array $actor, array $incompatibilities, array $confirmedKeys): bool
    {
        foreach ($incompatibilities as $relation) {
            if (($relation['kind'] ?? '') !== 'actor'
                || !in_array(RelationIdentity::key($relation), $confirmedKeys, true)) {
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
