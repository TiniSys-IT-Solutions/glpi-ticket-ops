<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;
use GlpiPlugin\Ticketops\Domain\TicketSnapshot;
use Session;
use Ticket;

final class TicketOperationValidator
{
    /** @return list<string> */
    public function validate(Ticket $ticket, TicketOperationPlan $plan, TicketSnapshot $snapshot): array
    {
        $blockers = [];
        $input = (new TicketOperationInputBuilder())->build($plan, $snapshot);
        if (isset($plan->organizationChanges['category'])) {
            $category = new \ITILCategory();
            $kind = match ((int) ($ticket->fields['type'] ?? 0)) {
                Ticket::INCIDENT_TYPE => 'is_incident',
                Ticket::DEMAND_TYPE => 'is_request',
                default => null,
            };
            if ($kind !== null && (!$category->getFromDB($plan->organizationChanges['category']) || empty($category->fields[$kind]))) {
                $blockers[] = __('The selected category is not available for this ticket type.', 'ticketops');
            }
        }
        $technician = $plan->organizationChanges['technician'] ?? null;
        $canAssign = $ticket->canAssign();
        $selfAssignment = $technician === Session::getLoginUserID() && $ticket->canAssignToMe();
        if ($technician !== null && !$canAssign && !$selfAssignment) {
            $blockers[] = __('GLPI does not allow this technician assignment.', 'ticketops');
        }
        if (isset($plan->organizationChanges['status'])
            && ($technician !== Session::getLoginUserID()
                || (!$canAssign && !$selfAssignment)
                || !Ticket::isAllowedStatus($snapshot->status, $plan->organizationChanges['status']))) {
            $blockers[] = __('GLPI does not allow assigning this ticket to you and starting it.', 'ticketops');
        }
        foreach (['_users_id_assign_deleted', '_groups_id_assign_deleted', '_suppliers_id_assign_deleted'] as $key) {
            if (!empty($input[$key]) && !$canAssign && (!$selfAssignment || $key !== '_users_id_assign_deleted')) {
                $blockers[] = __('GLPI does not allow removing the current assignment.', 'ticketops');
            }
        }
        // GLPI permits entity/category/location correction on closed tickets, but filters actors and title.
        if ($ticket->isClosed() && ($plan->ticketTitle !== null || $plan->newRequesterId > 0
            || isset($plan->organizationChanges['technician']) || isset($plan->organizationChanges['observer'])
            || $this->hasActorRemoval($input))) {
            $blockers[] = __('GLPI does not allow these title or actor changes on a closed ticket.', 'ticketops');
        }

        // Do not impose new-template obligations on an unrelated title/assignment correction.
        $templateChanged = $plan->sourceEntityId !== $plan->targetEntityId
            || (isset($input['itilcategories_id']) && (int) $input['itilcategories_id'] !== $snapshot->categoryId);
        if ($templateChanged) {
            $template = $ticket->getITILTemplateFromInput($input);
            if ($template !== null && $template->getID() > 0) {
                $template->getFromDBWithData($template->getID());
                $fields = $template->getAllowedFieldsNames(true);
                $final = array_replace($ticket->fields, $input);
                foreach ($template->mandatory as $key => $number) {
                    // Mirror GLPI's submitted-field checks. Unchanged missing values may be
                    // populated by native ONUPDATE rules; do not invent a new prerequisite.
                    if (!array_key_exists($key, $input)) {
                        continue;
                    }
                    $value = $final[$key] ?? null;
                    if (!$this->hasValue($key, $value, $input)) {
                        $blockers[] = sprintf(__('The target ticket template requires: %s.', 'ticketops'), (string) ($fields[$number] ?? $key));
                    }
                }
            }
        }

        return array_values(array_unique($blockers));
    }

    /** @param array<string, mixed> $input */
    private function hasActorRemoval(array $input): bool
    {
        foreach ($input as $key => $value) {
            if (str_ends_with($key, '_deleted') && !empty($value)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $input */
    private function hasValue(string $key, mixed $value, array $input): bool
    {
        if (str_starts_with($key, '_users_id_') && is_array($value)) {
            return array_filter($value, static fn(mixed $id): bool => (int) $id > 0) !== []
                || array_filter((array) ($input[$key . '_notif']['alternative_email'] ?? [])) !== [];
        }

        return !empty($value) && $value !== 'NULL' && (!is_string($value) || trim($value) !== '');
    }
}
