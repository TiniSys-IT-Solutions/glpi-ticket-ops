<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit\Security;

use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;
use GlpiPlugin\Ticketops\Security\PreviewTokenStore;
use PHPUnit\Framework\TestCase;

final class PreviewTokenStoreTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenIsBoundToPlanAndConsumedOnce(): void
    {
        $store = new PreviewTokenStore();
        $plan = new TicketOperationPlan(10, 'state', 2, 3, [], 0, organizationChanges: ['location' => 7]);
        $token = $store->issue($plan, 42);

        self::assertTrue($store->consume($token, $plan, 42));
        self::assertFalse($store->consume($token, $plan, 42));
    }

    public function testTokenRejectsChangedChoices(): void
    {
        $store = new PreviewTokenStore();
        $previewed = new TicketOperationPlan(10, 'state', 2, 3, [], 0, organizationChanges: ['location' => 7]);
        $changed = new TicketOperationPlan(10, 'state', 2, 4, [], 0, organizationChanges: ['location' => 8]);

        self::assertFalse($store->consume($store->issue($previewed, 42), $changed, 42));
    }
    public function testTokenRejectsChangedTitle(): void
    {
        $store = new PreviewTokenStore();
        $previewed = new TicketOperationPlan(10, 'state', 2, 2, [], 0, ticketTitle: 'Reviewed title');
        $changed = new TicketOperationPlan(10, 'state', 2, 2, [], 0, ticketTitle: 'Different title');

        self::assertFalse($store->consume($store->issue($previewed, 42), $changed, 42));
    }

}
