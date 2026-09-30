<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Ui;

use GlpiPlugin\Ticketops\Config;
use GlpiPlugin\Ticketops\Domain\DiagnosticFinding;
use GlpiPlugin\Ticketops\Security\TicketOperationGuard;
use GlpiPlugin\Ticketops\Service\TicketDiagnosticService;
use GlpiPlugin\Ticketops\Service\TicketSnapshotFactory;
use Session;
use Ticket;

final class TicketPanel
{
    /** @param array<string, mixed> $params */
    public static function render(array $params): void
    {
        global $CFG_GLPI;

        $ticket = $params['item'] ?? null;
        if (!$ticket instanceof Ticket) {
            return;
        }
        $guard = new TicketOperationGuard();
        if ((!Config::enabled('diagnostic') && !Config::operationsEnabled()) || !$guard->canViewDiagnostic($ticket)) {
            return;
        }
        $snapshot = (new TicketSnapshotFactory())->fromTicket($ticket);
        $findings = Config::enabled('diagnostic') ? (new TicketDiagnosticService())->diagnose($ticket) : [];
        $issueCount = count(array_filter(
            $findings,
            static fn(DiagnosticFinding $finding): bool => $finding->level !== DiagnosticFinding::SUCCESS,
        ));
        $healthLevel = self::healthLevel($findings);
        $sectionId = 'ticketops-organization-' . $snapshot->id;
        $operatorEntities = array_map(
            static fn(int $id): array => ['id' => $id, 'name' => \Dropdown::getDropdownName('glpi_entities', $id)],
            array_values(array_unique(array_map('intval', Session::getActiveEntities()))),
        );
        $payload = htmlspecialchars((string) json_encode([
            'ticketId' => $snapshot->id,
            'ticketTitle' => $snapshot->title,
            'entityId' => $snapshot->entityId,
            'requesters' => $snapshot->requesters,
            'operatorEntities' => $operatorEntities,
            'currentUser' => ['id' => Session::getLoginUserID(), 'name' => getUserName(Session::getLoginUserID())],
            'csrfToken' => Session::getNewCSRFToken(),
            'baseUrl' => rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/ticketops/TicketOps',
            'organizationUrl' => rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/ticketops/TicketOps/Ticket/' . $snapshot->id . '/OrganizationOptions',
            'labels' => [
                'title' => __('Correct requester and entity', 'ticketops'),
                'requester' => __('Requester to replace', 'ticketops'),
                'search' => __('Search an existing user', 'ticketops'),
                'close' => __('Close', 'ticketops'),
                'cancel' => __('Cancel', 'ticketops'),
                'preview' => __('Preview', 'ticketops'),
                'execute' => __('Change requester and entity', 'ticketops'),
                'searching' => __('Searching…', 'ticketops'),
                'not_found' => __('No accessible user found.', 'ticketops'),
                'target_entity' => __('Target entity', 'ticketops'),
                'choose' => __('Choose explicitly', 'ticketops'),
                'expected' => __('Expected result', 'ticketops'),
                'remove' => __('Remove incompatible relation', 'ticketops'),
                'email' => __('Email address', 'ticketops'),
                'summary' => __('The target entity, requester and compatible relations shown above will be applied.', 'ticketops'),
                'current_entity' => __('Current entity', 'ticketops'),
                'optional_organization' => __('Optional ticket organization', 'ticketops'),
                'category' => __('ITIL category', 'ticketops'),
                'location' => __('Location', 'ticketops'),
                'group' => __('Technician group', 'ticketops'),
                'technician' => __('Technician', 'ticketops'),
                'keep' => __('Keep current value', 'ticketops'),
                'organize' => __('Reorganize ticket', 'ticketops'),
                'organize_title' => __('Reorganize ticket assignment', 'ticketops'),
                'apply' => __('Apply ticket organization', 'ticketops'),
                'assign_me' => __('Assign to me and start', 'ticketops'),
            ],
        ], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<section id="' . self::escape($sectionId) . '" class="plugin-ticketops accordion-item" data-ticketops="' . $payload . '"'
            . ' data-ticketops-health="' . self::escape($healthLevel) . '" data-ticketops-count="' . $issueCount . '">';
        echo '<div class="accordion-header" id="ticketops-heading-' . $snapshot->id . '" title="' . self::escape(__('Ticket organization', 'ticketops')) . '" data-bs-toggle="tooltip">';
        echo '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ticketops-body-' . $snapshot->id . '" aria-expanded="false" aria-controls="ticketops-body-' . $snapshot->id . '">';
        echo '<i class="ti ti-heartbeat"></i><span class="item-title">' . self::escape(__('Ticket organization', 'ticketops')) . '</span>';
        if ($issueCount > 0) {
            echo '<span class="badge bg-secondary text-secondary-fg ms-2">' . $issueCount . '</span>';
        }
        echo '</button></div>';
        echo '<div id="ticketops-body-' . $snapshot->id . '" class="accordion-collapse collapse" aria-labelledby="ticketops-heading-' . $snapshot->id . '">';
        echo '<div class="accordion-body row m-0 mt-n2"><div class="col-12 pt-3">';
        echo '<dl class="ticketops-summary row mb-3">';
        self::summaryRow(__('Current entity', 'ticketops'), \Dropdown::getDropdownName('glpi_entities', $snapshot->entityId));
        self::summaryRow(__('Source', 'ticketops'), self::dropdownValue('glpi_requesttypes', (int) ($ticket->fields['requesttypes_id'] ?? 0)));
        self::summaryRow(__('ITIL category', 'ticketops'), self::dropdownValue('glpi_itilcategories', $snapshot->categoryId));
        self::summaryRow(__('Location', 'ticketops'), self::dropdownValue('glpi_locations', $snapshot->locationId));
        self::summaryRow(__('Actors', 'ticketops'), sprintf(
            __('%1$d requester(s), %2$d assignee(s), %3$d observer(s)', 'ticketops'),
            count($snapshot->requesters),
            count($snapshot->assignees),
            count($snapshot->observers),
        ));
        self::summaryRow(__('Last update', 'ticketops'), $snapshot->dateModified);
        echo '</dl>';
        echo '<ul class="ticketops-findings">';
        foreach ($findings as $finding) {
            echo '<li class="ticketops-finding ticketops-' . self::escape($finding->level) . '"><span aria-hidden="true">' . self::icon($finding->level) . '</span> ' . self::escape($finding->message) . '</li>';
        }
        echo '</ul>';
        if (Config::operationsEnabled() && $guard->canOperate($ticket)) {
            echo '<div class="d-flex flex-wrap gap-2">';
            if (Config::enabled('requester_entity_switch')) {
                echo '<button type="button" class="btn btn-primary ticketops-open" data-ticketops-mode="requester">' . self::escape(__('Correct requester and entity', 'ticketops')) . '</button>';
            }
            if (Config::enabled('organization')) {
                echo '<button type="button" class="btn btn-outline-primary ticketops-open" data-ticketops-mode="organization"><i class="ti ti-adjustments me-1"></i>' . self::escape(__('Reorganize ticket', 'ticketops')) . '</button>';
            }
            if (Config::enabled('quick_assignment')) {
                echo '<button type="button" class="btn btn-outline-secondary ticketops-open" data-ticketops-mode="quick"><i class="ti ti-user-check me-1"></i>' . self::escape(__('Assign to me and start', 'ticketops')) . '</button>';
            }
            echo '</div>';
        }
        echo '<p class="text-muted ticketops-fallback">' . self::escape(__('TicketOps never grants additional access and does not replay business rules.', 'ticketops')) . '</p>';
        echo '</div></div></div></section>';
    }

    /** @param list<DiagnosticFinding> $findings */
    private static function healthLevel(array $findings): string
    {
        foreach ([DiagnosticFinding::BLOCKING, DiagnosticFinding::WARNING, DiagnosticFinding::INFORMATION] as $level) {
            foreach ($findings as $finding) {
                if ($finding->level === $level) {
                    return $level;
                }
            }
        }

        return DiagnosticFinding::SUCCESS;
    }

    private static function icon(string $level): string
    {
        return match ($level) {
            DiagnosticFinding::SUCCESS => '✓',
            DiagnosticFinding::INFORMATION => 'ℹ',
            DiagnosticFinding::WARNING => '⚠',
            default => '⛔',
        };
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function dropdownValue(string $table, int $id): string
    {
        return $id > 0 ? \Dropdown::getDropdownName($table, $id) : '—';
    }

    private static function summaryRow(string $label, string $value): void
    {
        echo '<dt class="col-sm-3 text-muted">' . self::escape($label) . '</dt><dd class="col-sm-9">' . self::escape($value) . '</dd>';
    }
}
