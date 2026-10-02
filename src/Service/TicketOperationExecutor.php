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

        if (!$plan->isExecutable()) {
            throw new RuntimeException(__('This plan contains blocking issues.', 'ticketops'));
        }
        $ticket = new Ticket();
        $DB->beginTransaction();
        try {
            // GLPI 11 disables query(); doQuery() is its native SQL execution API.
            // The query builder has no FOR UPDATE option. Fixed table and integer ID
            // only; this locks one row for reading and never writes to ticket tables.
            $DB->doQuery('SELECT `id` FROM `glpi_tickets` WHERE `id` = ' . $plan->ticketId . ' FOR UPDATE');
            if (!$ticket->getFromDB($plan->ticketId) || !$this->guard->canOperate($ticket, $plan->targetEntityId)) {
                throw new RuntimeException(__('The ticket is unavailable or you are not allowed to update it.', 'ticketops'));
            }
            $removals = $plan->decisions['remove_relation_keys'] ?? [];
            $rebuilt = (new TicketOperationPlanner())->build(
                $ticket,
                $plan->replacedActor !== [] ? \GlpiPlugin\Ticketops\Domain\RelationIdentity::key($plan->replacedActor) : '',
                $plan->newRequesterId,
                $plan->targetEntityId,
                is_array($removals) ? array_values(array_filter($removals, 'is_string')) : [],
                $plan->organizationChanges,
                $plan->ticketTitle,
            );
            if (!$rebuilt->isExecutable()
                || !hash_equals(TicketFingerprint::fromState($plan->toArray()), TicketFingerprint::fromState($rebuilt->toArray()))) {
                throw new RuntimeException(__('The ticket changed during validation. Apply the operation again.', 'ticketops'));
            }
            $snapshot = $this->snapshots->fromTicket($ticket, true);
            if (!hash_equals($plan->fingerprint, TicketFingerprint::fromState($snapshot->fingerprintData()))) {
                throw new RuntimeException(__('The ticket changed during validation. Apply the operation again.', 'ticketops'));
            }
            $input = $this->buildNativeInput($rebuilt, $snapshot);
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
        return (new TicketOperationInputBuilder())->build($plan, $snapshot);
    }
}
