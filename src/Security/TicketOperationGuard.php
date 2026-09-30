<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Security;

use GlpiPlugin\Ticketops\Profile;
use Session;
use Ticket;

final class TicketOperationGuard
{
    public function canViewDiagnostic(Ticket $ticket): bool
    {
        return $ticket->getID() > 0
            && !(bool) ($ticket->fields['is_deleted'] ?? false)
            && Profile::canViewDiagnostic()
            && $ticket->canViewItem();
    }

    public function canOperate(Ticket $ticket, ?int $targetEntity = null): bool
    {
        if ($ticket->getID() <= 0 || (bool) ($ticket->fields['is_deleted'] ?? false)
            || !Profile::canSwitchRequesterAndEntity() || !$ticket->canViewItem() || !$ticket->canUpdateItem()
            || !Session::haveAccessToEntity((int) ($ticket->fields['entities_id'] ?? 0))) {
            return false;
        }

        return $targetEntity === null || Session::haveAccessToEntity($targetEntity);
    }
}
