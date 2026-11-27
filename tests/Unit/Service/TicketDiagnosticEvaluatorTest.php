<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit\Service;

use GlpiPlugin\Ticketops\Domain\DiagnosticFinding;
use GlpiPlugin\Ticketops\Domain\TicketSnapshot;
use GlpiPlugin\Ticketops\Service\TicketDiagnosticEvaluator;
use PHPUnit\Framework\TestCase;

final class TicketDiagnosticEvaluatorTest extends TestCase
{
    public function testEmailRequesterAndMissingQualificationAreReported(): void
    {
        $findings = (new TicketDiagnosticEvaluator())->evaluate($this->snapshot([
            ['link_id' => 4, 'itemtype' => 'User', 'items_id' => 0, 'alternative_email' => 'external@example.test'],
        ]), [], [], [4 => true]);

        self::assertContains('email_requester', array_column($findings, 'code'));
        self::assertContains('unqualified_email', array_column($findings, 'code'));
    }

    public function testInactiveRequesterIsBlocking(): void
    {
        $findings = (new TicketDiagnosticEvaluator())->evaluate($this->snapshot([
            ['link_id' => 5, 'itemtype' => 'User', 'items_id' => 42],
        ], category: 3), [42 => [2]], [42 => true], [5 => true]);

        self::assertContains(DiagnosticFinding::BLOCKING, array_column($findings, 'level'));
    }

    public function testValidOrganizationReturnsSuccess(): void
    {
        $ticket = new TicketSnapshot(
            1,
            'Synthetic',
            2,
            3,
            4,
            0,
            0,
            0,
            '2026-01-01',
            [['link_id' => 1, 'itemtype' => 'User', 'items_id' => 42]],
            [['link_id' => 2, 'itemtype' => 'User', 'items_id' => 7], ['link_id' => 3, 'itemtype' => 'Group', 'items_id' => 8]],
            [],
            1,
            1,
            0,
        );
        $findings = (new TicketDiagnosticEvaluator())->evaluate($ticket, [42 => [2]], [42 => false], [1 => true, 2 => true, 3 => true]);

        self::assertSame(['organization_ok'], array_column($findings, 'code'));
    }

    public function testRequesterWithSeveralValidEntitiesDoesNotCreateNoise(): void
    {
        $ticket = new TicketSnapshot(
            1,
            'Synthetic',
            2,
            3,
            4,
            0,
            0,
            0,
            '2026-01-01',
            [['link_id' => 2, 'itemtype' => 'User', 'items_id' => 7]],
            [],
            [],
            1,
            0,
            0,
        );

        $findings = (new TicketDiagnosticEvaluator())->evaluate($ticket, [7 => [2, 3]], [7 => false], [2 => true]);

        self::assertSame(['organization_ok'], array_column($findings, 'code'));
    }

    /** @param list<array<string, mixed>> $requesters */
    private function snapshot(array $requesters, int $category = 0): TicketSnapshot
    {
        return new TicketSnapshot(1, 'Synthetic', 2, $category, 0, 0, 0, 0, '2026-01-01', $requesters, [], []);
    }

    public function testMissingLocationIsReported(): void
    {
        $findings = (new TicketDiagnosticEvaluator())->evaluate($this->snapshot([], category: 3), [], [], []);

        self::assertContains('missing_location', array_column($findings, 'code'));
    }
}
