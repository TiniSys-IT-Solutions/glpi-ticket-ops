<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit\Service;

use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;
use GlpiPlugin\Ticketops\Domain\TicketSnapshot;
use GlpiPlugin\Ticketops\Service\TicketOperationExecutor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TicketOperationExecutorTest extends TestCase
{
    public function testReplacementUsesNativeDeletionAndDoesNotRemoveCollidingAssigneeId(): void
    {
        $replaced = ['link_id' => 7, 'type' => 1, 'itemtype' => 'User', 'items_id' => 42, 'use_notification' => 1, 'alternative_email' => ''];
        $assignee = ['link_id' => 7, 'type' => 2, 'itemtype' => 'User', 'items_id' => 50, 'use_notification' => 1, 'alternative_email' => ''];
        $snapshot = new TicketSnapshot(10, 'Synthetic', 2, 3, 4, 0, 0, 0, '2026-09-30', [$replaced], [$assignee], []);
        $plan = new TicketOperationPlan(10, 'fingerprint', 2, 2, $replaced, 99);

        $method = new ReflectionMethod(TicketOperationExecutor::class, 'buildNativeInput');
        /** @var array<string, mixed> $input */
        $input = $method->invoke(new TicketOperationExecutor(), $plan, $snapshot);

        self::assertSame([['id' => 7, 'itemtype' => 'User', 'items_id' => 42, 'type' => 1]], $input['_users_id_requester_deleted']);
        self::assertSame([99], $input['_users_id_requester']);
        self::assertSame([50], $input['_users_id_assign']);
        self::assertArrayNotHasKey('name', $input);
    }

    public function testOrganizationChangesAreIncludedInTheSameNativeUpdate(): void
    {
        $requester = ['link_id' => 2, 'type' => 1, 'itemtype' => 'User', 'items_id' => 42, 'use_notification' => 1, 'alternative_email' => ''];
        $technician = ['link_id' => 8, 'type' => 2, 'itemtype' => 'User', 'items_id' => 50, 'use_notification' => 1, 'alternative_email' => ''];
        $group = ['link_id' => 9, 'type' => 2, 'itemtype' => 'Group', 'items_id' => 51, 'use_notification' => 0, 'alternative_email' => ''];
        $observer = ['link_id' => 10, 'type' => 3, 'itemtype' => 'User', 'items_id' => 52, 'use_notification' => 1, 'alternative_email' => ''];
        $snapshot = new TicketSnapshot(10, 'Synthetic', 2, 3, 4, 0, 0, 0, '2026-09-30', [$requester], [$technician, $group], [$observer]);
        $plan = new TicketOperationPlan(10, 'fingerprint', 2, 6, $requester, 99, organizationChanges: [
            'category' => 20,
            'location' => 21,
            'technician' => 71,
            'observer' => 72,
            'status' => 2,
        ], ticketTitle: 'Corrected synthetic title');

        $method = new ReflectionMethod(TicketOperationExecutor::class, 'buildNativeInput');
        /** @var array<string, mixed> $input */
        $input = $method->invoke(new TicketOperationExecutor(), $plan, $snapshot);

        self::assertSame('Corrected synthetic title', $input['name']);
        self::assertSame(6, $input['entities_id']);
        self::assertSame(20, $input['itilcategories_id']);
        self::assertSame(21, $input['locations_id']);
        self::assertSame(2, $input['status']);
        self::assertSame([['id' => 8, 'itemtype' => 'User', 'items_id' => 50, 'type' => 2]], $input['_users_id_assign_deleted']);
        self::assertSame([71], $input['_users_id_assign']);
        self::assertSame([51], $input['_groups_id_assign']);
        self::assertSame([52, 72], $input['_users_id_observer']);
        foreach ([
            'items_id',
            'contracts_id',
            'problems_id',
            'changes_id',
            'projecttasks_id',
            'ticketcosts_id',
            'tickets_id_1',
            'tickets_id_2',
        ] as $relationInput) {
            self::assertArrayNotHasKey($relationInput, $input);
        }
    }
}
