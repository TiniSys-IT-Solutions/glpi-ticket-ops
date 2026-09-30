<?php

declare(strict_types=1);

class CommonGLPI
{
    /** @var array<string, mixed> */
    public array $fields = [];
    public function getID(): int
    {
        return 0;
    }
    public static function getType(): string
    {
        return '';
    }
}
class Profile extends CommonGLPI
{
    public static function getTable(): string
    {
        return 'glpi_profiles';
    }
    public static function createTabEntry(string $label, int $count = 0, ?string $itemtype = null, string $icon = ''): string
    {
        return '';
    }
    public function getFormURL(): string
    {
        return '';
    }
    /** @param array<mixed> $rights @param array<mixed> $options */
    public function displayRightsChoiceMatrix(array $rights, array $options = []): void {}
}
class ProfileRight
{
    public static function getTable(): string
    {
        return 'glpi_profilerights';
    }
    /** @param list<string> $rights @return array<string, int> */
    public static function getProfileRights(int $profileId, array $rights = []): array
    {
        return [];
    }
    /** @param list<string> $rights */
    public static function deleteProfileRights(array $rights): bool
    {
        return true;
    }
}
class Session
{
    public static function haveRight(string $right, int $level): bool
    {
        return false;
    }
}
class Html
{
    /** @param array<string, mixed> $options */
    public static function hidden(string $name, array $options = []): string
    {
        return '';
    }
    /** @param array<string, mixed> $options */
    public static function submit(string $label, array $options = []): string
    {
        return '';
    }
    public static function closeForm(): void {}
}
interface TicketOperationsDatabaseStub
{
    /** @param array<string, mixed> $query @return iterable<array<string, mixed>> */
    public function request(array $query): iterable;
    /** @param array<string, mixed> $values */
    public function insert(string $table, array $values): bool;
}
interface TicketOperationsCacheStub
{
    public function set(string $key, mixed $value): bool;
}
/** @var TicketOperationsDatabaseStub $DB */
$DB = $DB;
/** @var TicketOperationsCacheStub $GLPI_CACHE */
$GLPI_CACHE = $GLPI_CACHE;
const READ = 1;
const UPDATE = 2;
function __(string $message, ?string $domain = null): string
{
    return $message;
}
function _sx(string $context, string $message, ?string $domain = null): string
{
    return $message;
}
