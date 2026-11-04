<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit\Service;

use GlpiPlugin\Ticketops\Domain\RelationIdentity;
use GlpiPlugin\Ticketops\Domain\TicketFingerprint;
use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;
use GlpiPlugin\Ticketops\Domain\TicketSnapshot;
use GlpiPlugin\Ticketops\Security\TicketOperationGuard;
use GlpiPlugin\Ticketops\Service\LinkedRecordService;
use GlpiPlugin\Ticketops\Service\TicketOperationExecutor;
use GlpiPlugin\Ticketops\Service\TicketOperationInputBuilder;
use GlpiPlugin\Ticketops\Service\TicketOperationPlanner;
use GlpiPlugin\Ticketops\Service\TicketSnapshotFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TicketOperationSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        require dirname(__DIR__, 2) . '/Fixtures/GlpiRuntime.php';
        $GLOBALS['DB'] = new \SyntheticDatabase();
        \GlpiRuntime::$records = [
            'Ticket' => [10 => ['id' => 10, 'name' => 'Synthetic', 'entities_id' => 2, 'status' => 1, 'is_deleted' => 0,
                'itilcategories_id' => 0, 'locations_id' => 0, 'date_mod' => 'same-second']],
            'User' => [42 => ['id' => 42, 'is_active' => 1], 99 => ['id' => 99, 'is_active' => 1]],
        ];
    }

    private function ticket(): \Ticket
    {
        $ticket = new \Ticket();
        self::assertTrue($ticket->getFromDB(10));
        return $ticket;
    }

    private function build(array $organization = [], int $target = 6): TicketOperationPlan
    {
        return (new TicketOperationPlanner())->build($this->ticket(), '', 0, $target, [], $organization);
    }

    public function testOneRemovalCannotDeleteAnotherActorWithTheSameNumericId(): void
    {
        $user = ['kind' => 'actor', 'link_id' => 7, 'type' => 1, 'itemtype' => 'User', 'items_id' => 99, 'use_notification' => 1, 'alternative_email' => ''];
        $group = ['kind' => 'actor', 'link_id' => 7, 'type' => 2, 'itemtype' => 'Group', 'items_id' => 51, 'use_notification' => 0, 'alternative_email' => ''];
        $snapshot = new TicketSnapshot(10, 'Synthetic', 2, 0, 0, 0, 0, 0, '', [$user], [$group], []);
        $plan = new TicketOperationPlan(
            10,
            '',
            2,
            6,
            [],
            0,
            incompatibilities: [$user, $group],
            decisions: ['remove_relation_keys' => [RelationIdentity::key($user)]],
        );
        $input = (new TicketOperationInputBuilder())->build($plan, $snapshot);
        self::assertSame([['id' => 7, 'itemtype' => 'User', 'items_id' => 99, 'type' => 1]], $input['_users_id_requester_deleted']);
        self::assertArrayNotHasKey('_groups_id_assign_deleted', $input);
        self::assertSame([51], $input['_groups_id_assign']);
    }

    public function testReplacingRequesterDoesNotExemptAnIncompatibleGroupWithSameLinkId(): void
    {
        \GlpiRuntime::$actors = [1 => [['id' => 7, 'itemtype' => 'User', 'items_id' => 99]],
            2 => [['id' => 7, 'itemtype' => 'Group', 'items_id' => 51]]];
        \GlpiRuntime::$records['Group'][51] = ['id' => 51, 'entities_id' => 2, 'is_recursive' => 0];
        $plan = (new TicketOperationPlanner())->build($this->ticket(), 'actor:User:1:7', 42, 6);
        self::assertFalse($plan->isExecutable());
        self::assertContains('Every incompatible relation must be explicitly confirmed before removal.', $plan->blockers);
    }

    public function testRequesterChoiceDistinguishesUserAndGroupWithSameLinkId(): void
    {
        \GlpiRuntime::$actors[1] = [
            ['id' => 7, 'itemtype' => 'User', 'items_id' => 99],
            ['id' => 7, 'itemtype' => 'Group', 'items_id' => 51],
        ];
        \GlpiRuntime::$records['Group'][51] = ['id' => 51, 'entities_id' => 6];
        $ticket = $this->ticket();
        $plan = (new TicketOperationPlanner())->build($ticket, 'actor:User:1:7', 42, 6);
        self::assertTrue($plan->isExecutable());
        self::assertSame('User', $plan->replacedActor['itemtype']);
        $input = (new TicketOperationInputBuilder())->build($plan, (new TicketSnapshotFactory())->fromTicket($ticket, true));
        self::assertSame([51], $input['_groups_id_requester']);
        self::assertSame([42], $input['_users_id_requester']);
    }

    public function testQuickActionCannotUseSelfAssignmentToRemoveAnIncompatibleGroup(): void
    {
        \GlpiRuntime::$assign = false;
        \GlpiRuntime::$selfAssign = true;
        \GlpiRuntime::$actors[2] = [['id' => 7, 'itemtype' => 'Group', 'items_id' => 51]];
        \GlpiRuntime::$records['Group'][51] = ['id' => 51, 'entities_id' => 2];
        $plan = (new TicketOperationPlanner())->build(
            $this->ticket(),
            '',
            0,
            6,
            ['actor:Group:2:7'],
            ['technician' => 42, 'status' => 2],
        );
        self::assertContains('GLPI does not allow removing the current assignment.', $plan->blockers);
    }

    public function testDescendantLocationOfferedByNativeSelectorRemainsUsable(): void
    {
        \GlpiRuntime::$descendants[6] = [6, 7];
        \GlpiRuntime::$records['Location'][21] = ['id' => 21, 'entities_id' => 7];
        self::assertTrue($this->build(['location' => 21])->isExecutable());
    }

    public function testCategoryMustMatchTheNativeTicketTypeFilter(): void
    {
        \GlpiRuntime::$records['Ticket'][10]['type'] = 1;
        \GlpiRuntime::$records['ITILCategory'][20] = ['id' => 20, 'entities_id' => 6, 'is_incident' => 0, 'is_request' => 1];
        self::assertFalse($this->build(['category' => 20])->isExecutable());
        \GlpiRuntime::$records['ITILCategory'][20]['is_incident'] = 1;
        self::assertTrue($this->build(['category' => 20])->isExecutable());
    }

    public function testExplicitCategoryReplacementNeedsNoRemovalAndIsNeverReset(): void
    {
        \GlpiRuntime::$records['Ticket'][10]['itilcategories_id'] = 3;
        \GlpiRuntime::$records['ITILCategory'] = [3 => ['id' => 3, 'entities_id' => 2], 20 => ['id' => 20, 'entities_id' => 6]];
        $plan = $this->build(['category' => 20]);
        self::assertTrue($plan->isExecutable());
        self::assertSame([], $plan->incompatibilities);
        $snapshot = (new TicketSnapshotFactory())->fromTicket($this->ticket(), true);
        self::assertSame(20, (new TicketOperationInputBuilder())->build($plan, $snapshot)['itilcategories_id']);
        // Also defend against an already-prepared contradictory plan.
        $contradictory = new TicketOperationPlan(
            10,
            '',
            2,
            6,
            [],
            0,
            incompatibilities: [['kind' => 'field', 'field' => 'itilcategories_id', 'link_id' => -101]],
            decisions: ['remove_relation_keys' => ['field:itilcategories_id']],
            organizationChanges: ['category' => 20],
        );
        self::assertSame(20, (new TicketOperationInputBuilder())->build($contradictory, $snapshot)['itilcategories_id']);
    }

    public function testAssignmentAndQuickActionRespectNativeRightsAndCurrentUser(): void
    {
        self::assertTrue($this->build(['technician' => 99])->isExecutable());
        \GlpiRuntime::$assign = false;
        self::assertFalse($this->build(['technician' => 99])->isExecutable());
        self::assertTrue($this->build(['technician' => 42, 'status' => 2])->isExecutable());
        self::assertFalse($this->build(['technician' => 99, 'status' => 2])->isExecutable());
        \GlpiRuntime::$selfAssign = false;
        self::assertFalse($this->build(['technician' => 42, 'status' => 2])->isExecutable());
    }

    public function testOrdinaryOrganizationRemainsPossibleWithoutAssignmentRight(): void
    {
        \GlpiRuntime::$assign = false;
        \GlpiRuntime::$selfAssign = false;
        self::assertTrue($this->build(target: 2)->isExecutable());
        \GlpiRuntime::$update = false;
        self::assertFalse((new TicketOperationGuard())->canOperate($this->ticket(), 2));
    }

    public function testTechnicianEligibilityAndExpiredUserAreCheckedOnTheServer(): void
    {
        \GlpiRuntime::$technicians[99] = [];
        self::assertFalse($this->build(['technician' => 99])->isExecutable());
        \GlpiRuntime::$records['User'][42]['expired'] = true;
        self::assertFalse($this->build(['observer' => 42])->isExecutable());
    }

    public function testTargetTemplateChecksFinalStateAndKeepsEmailOnlyRequester(): void
    {
        $template = new \ITILTemplate();
        $template->fields = ['id' => 5];
        $template->mandatory = ['itilcategories_id' => 7];
        \GlpiRuntime::$template = $template;
        // An omitted category may be populated by GLPI's ONUPDATE rules.
        self::assertTrue($this->build()->isExecutable());
        \GlpiRuntime::$records['Ticket'][10]['itilcategories_id'] = 3;
        \GlpiRuntime::$records['ITILCategory'][3] = ['id' => 3, 'entities_id' => 2];
        $removed = (new TicketOperationPlanner())->build($this->ticket(), '', 0, 6, ['field:itilcategories_id']);
        self::assertContains('The target ticket template requires: Category.', $removed->blockers);
        // No new target template is imposed for a title/assignment correction in the same entity.
        self::assertTrue($this->build(target: 2)->isExecutable());
        \GlpiRuntime::$records['ITILCategory'][20] = ['id' => 20, 'entities_id' => 6];
        self::assertTrue($this->build(['category' => 20])->isExecutable());
        \GlpiRuntime::$records['Ticket'][10]['itilcategories_id'] = 0;
        $template->mandatory = ['_users_id_requester' => 4];
        \GlpiRuntime::$actors[1] = [['id' => 8, 'itemtype' => 'User', 'items_id' => 0, 'alternative_email' => 'synthetic@example.invalid']];
        self::assertTrue($this->build()->isExecutable());
    }

    public function testClosedTicketAllowsEntityCorrectionButDoesNotPretendToRenameIt(): void
    {
        \GlpiRuntime::$records['Ticket'][10]['status'] = 6;
        self::assertTrue($this->build()->isExecutable());
        $plan = (new TicketOperationPlanner())->build($this->ticket(), '', 0, 6, ticketTitle: 'Corrected');
        self::assertFalse($plan->isExecutable());
    }

    public function testSharedDocumentFromTaskBlocksTransferWhileExclusiveAttachmentIsKept(): void
    {
        \GlpiRuntime::$records['Document'][30] = ['id' => 30, 'entities_id' => 2, 'is_recursive' => 0];
        \GlpiRuntime::$tables['glpi_documents_items'] = [
            ['id' => 1, 'documents_id' => 30, 'itemtype' => 'TicketTask', 'items_id' => 20],
            ['id' => 2, 'documents_id' => 30, 'itemtype' => 'Ticket', 'items_id' => 999],
        ];
        self::assertFalse($this->build()->isExecutable());
        self::assertTrue($this->build(target: 2)->isExecutable());
        array_pop(\GlpiRuntime::$tables['glpi_documents_items']);
        $plan = $this->build();
        self::assertTrue($plan->isExecutable());
        self::assertSame('Document', $plan->preservedRelations[0]['itemtype']);
        self::assertSame([], \GlpiRuntime::$updates);
    }

    public function testDocumentsInTargetScopeAndRecursiveAncestorAreKept(): void
    {
        $relation = ['kind' => 'document', 'exists' => true, 'entity_id' => 0, 'recursive' => true, 'shared' => true];
        \GlpiRuntime::$descendants[0] = [0, 2, 6];
        self::assertTrue((new LinkedRecordService())->isCompatible($relation, 6, 2));
        $relation['recursive'] = false;
        self::assertFalse((new LinkedRecordService())->isCompatible($relation, 6, 2));
        $relation['entity_id'] = 6;
        self::assertTrue((new LinkedRecordService())->isCompatible($relation, 6, 2));
    }

    public function testChangeLinkIsInventoriedWithoutChangingTheExistingTransferPolicy(): void
    {
        \GlpiRuntime::$records['Change'][50] = ['id' => 50, 'entities_id' => 2];
        \GlpiRuntime::$tables['glpi_changes_tickets'] = [['id' => 4, 'changes_id' => 50, 'tickets_id' => 10]];
        $plan = $this->build();
        self::assertTrue($plan->isExecutable());
        self::assertSame('Change', $plan->preservedRelations[0]['itemtype']);
        self::assertTrue($this->build(target: 2)->isExecutable());
        self::assertCount(1, \GlpiRuntime::$tables['glpi_changes_tickets']);
    }

    public function testFingerprintIncludesTitleStatusAndChildChangesInSameSecond(): void
    {
        $factory = new TicketSnapshotFactory();
        $first = TicketFingerprint::fromState($factory->fromTicket($this->ticket(), true)->fingerprintData());
        \GlpiRuntime::$records['Ticket'][10]['name'] = 'Changed in same second';
        $second = TicketFingerprint::fromState($factory->fromTicket($this->ticket(), true)->fingerprintData());
        self::assertNotSame($first, $second);
        \GlpiRuntime::$tables['glpi_tickettasks'][] = ['id' => 20, 'tickets_id' => 10, 'content' => 'Synthetic'];
        self::assertNotSame($second, TicketFingerprint::fromState($factory->fromTicket($this->ticket(), true)->fingerprintData()));
    }

    public function testExecutionLocksBeforeRevalidationAndUsesOneNativeUpdate(): void
    {
        $plan = $this->build();
        (new TicketOperationExecutor())->execute($plan);
        self::assertSame(['begin', 'lock', 'update', 'commit'], \GlpiRuntime::$events);
        self::assertCount(1, \GlpiRuntime::$updates);
        self::assertSame(6, \GlpiRuntime::$updates[0]['entities_id']);
    }

    public function testChangeBetweenPreparationAndLockRollsBackWithoutMutation(): void
    {
        $plan = $this->build();
        \GlpiRuntime::$onLock = static function (): void {
            \GlpiRuntime::$records['Ticket'][10]['name'] = 'Concurrent edit';
        };
        try {
            (new TicketOperationExecutor())->execute($plan);
            self::fail('A concurrent edit should have been rejected.');
        } catch (\RuntimeException $exception) {
            self::assertSame('The ticket changed during validation. Apply the operation again.', $exception->getMessage());
        }
        self::assertSame(['begin', 'lock', 'rollback'], \GlpiRuntime::$events);
        self::assertSame([], \GlpiRuntime::$updates);
    }
}
