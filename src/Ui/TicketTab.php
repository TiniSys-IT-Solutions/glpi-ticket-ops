<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Ui;

use CommonGLPI;
use GlpiPlugin\Ticketops\Config;
use GlpiPlugin\Ticketops\Domain\DiagnosticFinding;
use GlpiPlugin\Ticketops\Security\TicketOperationGuard;
use GlpiPlugin\Ticketops\Service\TicketDiagnosticService;
use Ticket;

final class TicketTab extends CommonGLPI
{
    /** @param bool|int $withtemplate */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!$item instanceof Ticket
            || (!Config::enabled('diagnostic') && !Config::operationsEnabled())
            || !(new TicketOperationGuard())->canViewDiagnostic($item)) {
            return '';
        }

        $issueCount = 0;
        if (Config::enabled('diagnostic')) {
            $issueCount = count(array_filter(
                (new TicketDiagnosticService())->diagnose($item),
                static fn(DiagnosticFinding $finding): bool => $finding->level !== DiagnosticFinding::SUCCESS,
            ));
        }

        return self::createTabEntry(__('TicketOps', 'ticketops'), $issueCount, $item::getType(), 'ti ti-heartbeat');
    }

    /**
     * @param int $tabnum
     * @param bool|int $withtemplate
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        return $item instanceof Ticket && TicketPanel::renderTab($item);
    }
}
