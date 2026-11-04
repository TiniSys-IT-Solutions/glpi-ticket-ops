<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit\Domain;

use GlpiPlugin\Ticketops\Domain\TicketFingerprint;
use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;
use PHPUnit\Framework\TestCase;

final class TicketOperationPlanTest extends TestCase
{
    public function testPlanExecutionDependsOnBlockers(): void
    {
        $plan = new TicketOperationPlan(1, 'hash', 0, 2, ['id' => 3], 4);
        self::assertTrue($plan->isExecutable());
        self::assertFalse((new TicketOperationPlan(1, 'hash', 0, 2, [], 4, blockers: ['denied']))->isExecutable());
    }

    public function testFingerprintIsStableAcrossKeyOrder(): void
    {
        self::assertSame(TicketFingerprint::fromState(['a' => 1, 'b' => 2]), TicketFingerprint::fromState(['b' => 2, 'a' => 1]));
    }
}
