<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops;

use Session;

final class Config
{
    public const CONTEXT = 'plugin:ticketops';

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'diagnostic_enabled' => '1',
            'requester_entity_switch_enabled' => '0',
            'organization_enabled' => '0',
            'quick_assignment_enabled' => '0',
        ];
    }

    public static function installDefaults(): void
    {
        $current = \Config::getConfigurationValues(self::CONTEXT);
        $missing = array_diff_key(self::defaults(), $current);
        if ($missing !== []) {
            \Config::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    public static function uninstall(): void
    {
        \Config::deleteConfigurationValues(self::CONTEXT, array_keys(self::defaults()));
    }

    /** @return array<string, bool> */
    public static function values(): array
    {
        $values = array_replace(self::defaults(), \Config::getConfigurationValues(self::CONTEXT));

        return array_map(static fn(mixed $value): bool => (bool) (int) $value, $values);
    }

    public static function enabled(string $module): bool
    {
        return self::values()[$module . '_enabled'] ?? false;
    }

    public static function operationsEnabled(): bool
    {
        return self::enabled('requester_entity_switch') || self::enabled('organization') || self::enabled('quick_assignment');
    }

    public static function canManage(): bool
    {
        return (bool) Session::haveRight('config', UPDATE);
    }

    /** @param array<string, mixed> $input */
    public static function save(array $input): void
    {
        $values = [];
        foreach (array_keys(self::defaults()) as $key) {
            $values[$key] = !empty($input[$key]) ? '1' : '0';
        }
        \Config::setConfigurationValues(self::CONTEXT, $values);
    }

    public static function url(): string
    {
        global $CFG_GLPI;

        return rtrim((string) ($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/ticketops/front/config.php';
    }
}
