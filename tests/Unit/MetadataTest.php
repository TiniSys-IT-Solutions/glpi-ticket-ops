<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketoperations\Tests\Unit;

use GlpiPlugin\Ticketoperations\Plugin;
use PHPUnit\Framework\TestCase;

final class MetadataTest extends TestCase
{
    public function testVersionMetadataRemainSynchronized(): void
    {
        $root = dirname(__DIR__, 2);
        $setup = file_get_contents($root . '/setup.php');
        self::assertIsString($setup);
        if (preg_match("/const PLUGIN_TICKETOPERATIONS_VERSION = '([^']+)'/", $setup, $matches) !== 1) {
            self::fail('The setup version constant is missing.');
        }
        $xml = simplexml_load_file($root . '/ticketoperations.xml');
        self::assertNotFalse($xml);
        self::assertSame(Plugin::VERSION, $matches[1]);
        self::assertSame(Plugin::VERSION, (string) $xml->versions->version->num);
    }

    public function testCompatibilityBoundsMatchTheFoundationBrief(): void
    {
        $setup = file_get_contents(dirname(__DIR__, 2) . '/setup.php');
        self::assertIsString($setup);
        self::assertStringContainsString("MIN_GLPI = '11.0.8'", $setup);
        self::assertStringContainsString("MAX_GLPI = '11.1.0'", $setup);
        self::assertStringContainsString("MIN_PHP = '8.2.0'", $setup);
    }
}
