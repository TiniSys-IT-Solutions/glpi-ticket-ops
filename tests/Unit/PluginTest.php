<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketoperations\Tests\Unit;

use GlpiPlugin\Ticketoperations\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testIdentityIsStable(): void
    {
        self::assertSame('ticketoperations', Plugin::KEY);
        self::assertSame('Ticket Operations', Plugin::NAME);
        self::assertSame('TicketOps', Plugin::SHORT_NAME);
        self::assertSame('0.0.1', Plugin::VERSION);
    }
}
