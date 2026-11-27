<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit;

use GlpiPlugin\Ticketops\Install\RightSet;
use PHPUnit\Framework\TestCase;

final class RightSetTest extends TestCase
{
    public function testMissingRightsPreserveRequiredOrder(): void
    {
        self::assertSame(['diagnostic', 'switch'], RightSet::missing(['diagnostic', 'switch'], []));
        self::assertSame(['switch'], RightSet::missing(['diagnostic', 'switch'], ['diagnostic' => 0]));
    }
}
