<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit;

use GlpiPlugin\Ticketops\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testIdentityIsStable(): void
    {
        self::assertSame('ticketops', Plugin::KEY);
        self::assertSame('TicketOps', Plugin::NAME);
        self::assertSame('TicketOps', Plugin::SHORT_NAME);
        self::assertSame('0.2.0', Plugin::VERSION);
    }
}
