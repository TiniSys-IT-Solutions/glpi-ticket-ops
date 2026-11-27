<?php

declare(strict_types=1);

function getUserName(int $id): string
{
    return '';
}

class CommonGLPI
{
    /** @var array<string, mixed> */
    public array $fields = [];
    public function getEntityID(): int
    {
        return 0;
    }
    public function isRecursive(): bool
    {
        return false;
    }
    public static function getSearchURL(): string
    {
        return '';
    }
    public function getID(): int
    {
        return 0;
    }
    public static function getType(): string
    {
        return '';
    }
    public static function getTable(): string
    {
        return '';
    }
    public static function createTabEntry(string $label, int $count = 0, ?string $itemtype = null, string $icon = ''): string
    {
        return '';
    }
    public function getFromDB(int $id): bool
    {
        return false;
    }
}
class ITILCategory extends CommonGLPI {}
class Location extends CommonGLPI {}
class Change extends CommonGLPI {}
class Problem extends CommonGLPI {}
class ProjectTask extends CommonGLPI {}
class Contract extends CommonGLPI {}
class Document extends CommonGLPI {}
class ITILTemplate extends CommonGLPI
{
    /** @var array<string, int> */
    public array $mandatory = [];
    public function getFromDBWithData(int $id): bool
    {
        return false;
    }
    /** @return array<int, string> */
    public function getAllowedFieldsNames(bool $withTypeAndCategory = false): array
    {
        return [];
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
class Config
{
    /** @return array<string, string> */
    public static function getConfigurationValues(string $context): array
    {
        return [];
    }
    /** @param array<string, string> $values */
    public static function setConfigurationValues(string $context, array $values): void {}
    /** @param list<string> $values */
    public static function deleteConfigurationValues(string $context, array $values = []): void {}
}
class Session
{
    public static function getCurrentInterface(): string
    {
        return 'central';
    }
    public static function getLoginUserID(): int
    {
        return 0;
    }
    public static function haveRight(string $right, int $level): bool
    {
        return false;
    }
    public static function checkLoginUser(): void {}
    /** @return list<int> */
    public static function getActiveEntities(): array
    {
        return [];
    }
    public static function haveAccessToEntity(int $id, bool $recursive = false): bool
    {
        return false;
    }
    public static function getNewCSRFToken(bool $standalone = false): string
    {
        return '';
    }
}
class Ticket extends CommonGLPI
{
    public const INCIDENT_TYPE = 1;
    public const DEMAND_TYPE = 2;
    public const ASSIGNED = 2;
    public const OWN = 32768;
    public const STEAL = 16384;
    public function canAssign(): bool
    {
        return false;
    }
    public function canAssignToMe(): bool
    {
        return false;
    }
    public function isClosed(): bool
    {
        return false;
    }
    public static function isAllowedStatus(int $old, int $new): bool
    {
        return false;
    }
    /** @param array<string, mixed> $input */
    public function getITILTemplateFromInput(array $input = []): ?ITILTemplate
    {
        return null;
    }
    /** @return array<string, mixed> */
    public function getAssociatedDocumentsCriteria(bool $bypassRights = false): array
    {
        return [];
    }
    public function loadActors(): void {}
    public function countUsers(int $type = 0): int
    {
        return 0;
    }
    public function countGroups(int $type = 0): int
    {
        return 0;
    }
    public function countSuppliers(int $type = 0): int
    {
        return 0;
    }
    /** @phpstan-impure */
    public function getFromDB(int $id): bool
    {
        return false;
    }
    public function canViewItem(): bool
    {
        return false;
    }
    public function canUpdateItem(): bool
    {
        return false;
    }
    /** @return array<int, array<string, mixed>> */
    public function getActorsForType(int $type): array
    {
        return [];
    }
    /** @param array<string, mixed> $input */
    public function update(array $input): bool
    {
        return false;
    }
    public function getLinkURL(): string
    {
        return '';
    }
}
class Dropdown
{
    /** @param array<string, mixed> $options */
    public static function show(string $itemtype, array $options = []): string|false|int
    {
        return '';
    }
    public static function getDropdownName(string $table, int $id): string
    {
        return '';
    }
}
class User extends CommonGLPI
{
    /** @param int|list<int> $entity */
    public static function isValidUserForEntity(int $id, int|array $entity): bool
    {
        return false;
    }
    /** @param array<string, mixed> $options */
    public static function dropdown(array $options = []): int|string
    {
        return '';
    }
    public function getFromDB(int $id): bool
    {
        return false;
    }
}
class Group extends CommonGLPI
{
    public function getFromDB(int $id): bool
    {
        return false;
    }
}
class Profile_User
{
    /** @return list<int> */
    public static function getUserEntitiesForRight(int $id, string $right, int $rights, bool $recursive = true): array
    {
        return [];
    }
    /** @return list<int> */
    public static function getUserEntities(int $id, bool $recursive = true, bool $defaultFirst = false): array
    {
        return [];
    }
}
class CommonITILActor
{
    public const REQUESTER = 1;
    public const ASSIGN = 2;
    public const OBSERVER = 3;
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
    public function query(string $query): mysqli_result|bool;
    public function doQuery(string $query): mysqli_result|true;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollback(): void;
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
/** @return list<int> */
function getSonsOf(string $table, int $id): array
{
    return [];
}
