<?php

declare(strict_types=1);

// Synthetic adapters for plugin unit tests; no GLPI instance or database is accessed.
final class GlpiRuntime
{
    public static array $records = [];
    public static array $tables = [];
    public static array $actors = [];
    public static array $entities = [2, 6];
    public static array $descendants = [];
    public static array $userEntities = [42 => [2, 6], 99 => [2, 6]];
    public static array $technicians = [42 => [2, 6], 99 => [2, 6]];
    public static array $events = [];
    public static array $updates = [];
    public static bool $assign = true;
    public static bool $selfAssign = true;
    public static bool $update = true;
    public static bool $statusAllowed = true;
    public static ?ITILTemplate $template = null;
    public static ?Closure $onLock = null;
}

const READ = 1;
const UPDATE = 2;

function getSonsOf(string $table, int $id): array
{
    return GlpiRuntime::$descendants[$id] ?? [$id];
}

class CommonGLPI
{
    public array $fields = [];
    public function getFromDB(int $id): bool
    {
        $this->fields = GlpiRuntime::$records[static::class][$id] ?? [];
        return $this->fields !== [];
    }
    public function getID(): int
    {
        return (int) ($this->fields['id'] ?? 0);
    }
    public function getEntityID(): int
    {
        return (int) ($this->fields['entities_id'] ?? -1);
    }
    public function isRecursive(): bool
    {
        return (bool) ($this->fields['is_recursive'] ?? false);
    }
}
class Profile extends CommonGLPI {}
class Group extends CommonGLPI {}
class Supplier extends CommonGLPI {}
class ITILCategory extends CommonGLPI {}
class Location extends CommonGLPI {}
class SLA extends CommonGLPI {}
class OLA extends CommonGLPI {}
class TicketTemplate extends CommonGLPI {}
class Change extends CommonGLPI {}
class Problem extends CommonGLPI {}
class ProjectTask extends CommonGLPI {}
class Contract extends CommonGLPI {}
class Document extends CommonGLPI {}
class ITILTemplate extends CommonGLPI
{
    public array $mandatory = [];
    public function getFromDBWithData(int $id): bool
    {
        return true;
    }
    public function getAllowedFieldsNames(bool $withTypeAndCategory = false): array
    {
        return [7 => 'Category', 9 => 'Location', 4 => 'Requester'];
    }
}
class Session
{
    public static function getCurrentInterface(): string
    {
        return 'central';
    }
    public static function getLoginUserID(): int
    {
        return 42;
    }
    public static function haveRight(string $right, int $level): bool
    {
        return $right === 'ticket' ? GlpiRuntime::$update : true;
    }
    public static function getActiveEntities(): array
    {
        return GlpiRuntime::$entities;
    }
    public static function haveAccessToEntity(int $id): bool
    {
        return in_array($id, GlpiRuntime::$entities, true);
    }
}
class User extends CommonGLPI
{
    public static function isValidUserForEntity(int $id, int|array $entities): bool
    {
        $user = GlpiRuntime::$records[self::class][$id] ?? [];
        return ($user['is_active'] ?? false) && !($user['is_deleted'] ?? false)
            && !($user['expired'] ?? false) && !($user['system'] ?? false)
            && array_intersect((array) $entities, GlpiRuntime::$userEntities[$id] ?? []) !== [];
    }
}
class Profile_User
{
    public static function getUserEntities(int $id, bool $recursive = true): array
    {
        return GlpiRuntime::$userEntities[$id] ?? [];
    }
    public static function getUserEntitiesForRight(int $id, string $right, int $rights, bool $recursive = true): array
    {
        return GlpiRuntime::$technicians[$id] ?? [];
    }
}
class Dropdown
{
    public static function getDropdownName(string $table, int $id): string
    {
        return 'Synthetic #' . $id;
    }
}
class CommonITILActor
{
    public const REQUESTER = 1;
    public const ASSIGN = 2;
    public const OBSERVER = 3;
}
class Ticket extends CommonGLPI
{
    public const ASSIGNED = 2;
    public const INCIDENT_TYPE = 1;
    public const DEMAND_TYPE = 2;
    public const OWN = 32768;
    public const STEAL = 16384;
    public function loadActors(): void {}
    public function getActorsForType(int $type): array
    {
        return GlpiRuntime::$actors[$type] ?? [];
    }
    public function countUsers(int $type): int
    {
        return count(array_filter($this->getActorsForType($type), static fn(array $actor): bool => $actor['itemtype'] === 'User'));
    }
    public function countGroups(int $type): int
    {
        return count(array_filter($this->getActorsForType($type), static fn(array $actor): bool => $actor['itemtype'] === 'Group'));
    }
    public function countSuppliers(int $type): int
    {
        return count(array_filter($this->getActorsForType($type), static fn(array $actor): bool => $actor['itemtype'] === 'Supplier'));
    }
    public function canViewItem(): bool
    {
        return true;
    }
    public function canUpdateItem(): bool
    {
        return true;
    }
    public function canAssign(): bool
    {
        return GlpiRuntime::$assign && !$this->isClosed();
    }
    public function canAssignToMe(): bool
    {
        return GlpiRuntime::$selfAssign && !$this->isClosed();
    }
    public function isClosed(): bool
    {
        return ($this->fields['status'] ?? 1) === 6;
    }
    public static function isAllowedStatus(int $old, int $new): bool
    {
        return GlpiRuntime::$statusAllowed;
    }
    public function getITILTemplateFromInput(array $input = []): ?ITILTemplate
    {
        return GlpiRuntime::$template;
    }
    public function getAssociatedDocumentsCriteria(bool $bypass = false): array
    {
        return ['OR' => [
            ['itemtype' => 'Ticket', 'items_id' => $this->getID()],
            ['itemtype' => 'TicketTask', 'items_id' => [20]],
            ['itemtype' => 'ITILFollowup', 'items_id' => [21]],
            ['itemtype' => 'ITILSolution', 'items_id' => [22]],
            ['itemtype' => 'TicketValidation', 'items_id' => [23]],
        ]];
    }
    public function update(array $input): bool
    {
        GlpiRuntime::$events[] = 'update';
        GlpiRuntime::$updates[] = $input;
        foreach ($input as $key => $value) {
            if ($key[0] !== '_') {
                GlpiRuntime::$records[self::class][$this->getID()][$key] = $value;
            }
        }
        return true;
    }
}
final class SyntheticDatabase
{
    public function request(array $query): iterable
    {
        $rows = GlpiRuntime::$tables[$query['FROM']] ?? [];
        $rows = array_values(array_filter($rows, fn(array $row): bool => $this->matches($row, $query['WHERE'] ?? [])));
        usort($rows, static fn(array $a, array $b): int => $a['id'] <=> $b['id']);
        return $rows;
    }
    private function matches(array $row, array $where): bool
    {
        foreach ($where as $key => $value) {
            if ($key === 'OR') {
                foreach ($value as $nestedKey => $nestedValue) {
                    $nested = is_int($nestedKey) ? $nestedValue : [$nestedKey => $nestedValue];
                    if ($this->matches($row, $nested)) {
                        continue 2;
                    }
                }
                return false;
            }
            if (is_int($key)) {
                if (!$this->matches($row, $value)) {
                    return false;
                }
            } elseif (is_array($value) ? !in_array($row[$key] ?? null, $value, true) : ($row[$key] ?? null) !== $value) {
                return false;
            }
        }
        return true;
    }
    public function beginTransaction(): void
    {
        GlpiRuntime::$events[] = 'begin';
    }
    public function query(string $query): bool
    {
        throw new Exception('Executing direct queries is not allowed!');
    }
    public function doQuery(string $query): true
    {
        if (!str_ends_with($query, ' FOR UPDATE')) {
            throw new LogicException('Expected a read lock.');
        }
        GlpiRuntime::$events[] = 'lock';
        (GlpiRuntime::$onLock ?? static fn() => null)();
        return true;
    }
    public function commit(): void
    {
        GlpiRuntime::$events[] = 'commit';
    }
    public function rollback(): void
    {
        GlpiRuntime::$events[] = 'rollback';
    }
}
