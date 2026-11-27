<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Tests\Unit\Security;

use GlpiPlugin\Ticketops\Security\EntityIntersection;
use PHPUnit\Framework\TestCase;

final class EntityIntersectionTest extends TestCase
{
    public function testTargetsAreUniqueSortedIntersection(): void
    {
        self::assertSame([2, 3], EntityIntersection::validTargets([3, 2, 2, 8], [1, 2, 3]));
    }

    public function testSuggestionUsesUniqueOrValidPreferenceOnly(): void
    {
        self::assertSame(2, EntityIntersection::suggestedTarget([2], null));
        self::assertSame(3, EntityIntersection::suggestedTarget([2, 3], 3));
        self::assertNull(EntityIntersection::suggestedTarget([2, 3], 9));
    }
}
