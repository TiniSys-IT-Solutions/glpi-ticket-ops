<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\DiagnosticFinding;
use GlpiPlugin\Ticketops\Domain\TicketSnapshot;

final class TicketDiagnosticEvaluator
{
    /**
     * @param array<int, list<int>> $userEntities
     * @param array<int, bool> $inactiveUsers
     * @param array<int, bool> $actorCompatible
     * @return list<DiagnosticFinding>
     */
    public function evaluate(TicketSnapshot $ticket, array $userEntities, array $inactiveUsers, array $actorCompatible): array
    {
        $findings = [];
        $emailOnly = false;
        foreach ($ticket->requesters as $actor) {
            if ($actor['itemtype'] === 'User' && (int) $actor['items_id'] === 0) {
                $emailOnly = true;
                $findings[] = new DiagnosticFinding('email_requester', DiagnosticFinding::WARNING, __('The requester is represented only by an email address.', 'ticketops'));
                continue;
            }
            if ($actor['itemtype'] !== 'User') {
                continue;
            }
            $userId = (int) $actor['items_id'];
            $validEntities = $userEntities[$userId] ?? [];
            if ($inactiveUsers[$userId] ?? false) {
                $findings[] = new DiagnosticFinding('inactive_requester', DiagnosticFinding::BLOCKING, __('A requester account is inactive or deleted.', 'ticketops'));
            } elseif (!in_array($ticket->entityId, $validEntities, true)) {
                $findings[] = new DiagnosticFinding('invalid_requester_entity', DiagnosticFinding::BLOCKING, __('A requester is not valid in the ticket entity.', 'ticketops'));
            }
        }
        if ($ticket->assignedUserCount === 0 && $ticket->assignedSupplierCount === 0) {
            $findings[] = new DiagnosticFinding('missing_technician', DiagnosticFinding::WARNING, __('No technician is assigned.', 'ticketops'));
        }
        if ($ticket->categoryId <= 0) {
            $findings[] = new DiagnosticFinding('missing_category', DiagnosticFinding::WARNING, __('No ITIL category is selected.', 'ticketops'));
            if ($emailOnly) {
                $findings[] = new DiagnosticFinding('unqualified_email', DiagnosticFinding::INFORMATION, __('The email requester is not yet qualified by an ITIL category.', 'ticketops'));
            }
        }
        if ($ticket->locationId <= 0) {
            $findings[] = new DiagnosticFinding('missing_location', DiagnosticFinding::WARNING, __('No location is selected.', 'ticketops'));
        }
        foreach ($actorCompatible as $linkId => $compatible) {
            if (!$compatible) {
                $findings[] = new DiagnosticFinding('incompatible_actor', DiagnosticFinding::BLOCKING, sprintf(__('Actor relation #%d is incompatible with the ticket entity.', 'ticketops'), $linkId));
            }
        }
        if ($findings === []) {
            $findings[] = new DiagnosticFinding('organization_ok', DiagnosticFinding::SUCCESS, __('No organization inconsistency was detected.', 'ticketops'));
        }

        return $findings;
    }
}
