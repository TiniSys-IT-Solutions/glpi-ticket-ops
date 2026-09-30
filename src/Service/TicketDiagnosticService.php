<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Service;

use GlpiPlugin\Ticketops\Domain\DiagnosticFinding;
use Profile_User;
use Ticket;
use User;

final class TicketDiagnosticService
{
    /** @return list<DiagnosticFinding> */
    public function diagnose(Ticket $ticket): array
    {
        $snapshot = (new TicketSnapshotFactory())->fromTicket($ticket);
        $entities = [];
        $inactive = [];
        foreach ([...$snapshot->requesters, ...$snapshot->assignees, ...$snapshot->observers] as $actor) {
            if ($actor['itemtype'] !== 'User' || (int) $actor['items_id'] <= 0) {
                continue;
            }
            $id = (int) $actor['items_id'];
            $entities[$id] = array_map('intval', Profile_User::getUserEntities($id, true));
            $user = new User();
            $inactive[$id] = !$user->getFromDB($id) || !(bool) ($user->fields['is_active'] ?? false) || (bool) ($user->fields['is_deleted'] ?? false);
        }
        $analysis = (new RelationCompatibilityService())->analyze($snapshot, $snapshot->entityId);
        $compatibility = [];
        foreach ($analysis['preserved'] as $actor) {
            if (($actor['kind'] ?? '') === 'actor') {
                $compatibility[(int) $actor['link_id']] = true;
            }
        }
        foreach ($analysis['incompatible'] as $actor) {
            if (($actor['kind'] ?? '') === 'actor') {
                $compatibility[(int) $actor['link_id']] = false;
            }
        }

        return (new TicketDiagnosticEvaluator())->evaluate($snapshot, $entities, $inactive, $compatibility);
    }
}
