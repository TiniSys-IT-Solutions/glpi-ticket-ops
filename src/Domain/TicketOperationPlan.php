<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Domain;

final readonly class TicketOperationPlan
{
    /**
     * @param array<string, scalar|null> $replacedActor
     * @param list<array<string, scalar|null>> $preservedRelations
     * @param list<array<string, scalar|null>> $incompatibilities
     * @param array<string, scalar|list<scalar>|null> $decisions
     * @param list<string> $warnings
     * @param list<string> $blockers
     * @param array<string, scalar|null> $effects
     * @param array<string, int> $organizationChanges
     */
    public function __construct(
        public int $ticketId,
        public string $fingerprint,
        public int $sourceEntityId,
        public int $targetEntityId,
        public array $replacedActor,
        public int $newRequesterId,
        public array $preservedRelations = [],
        public array $incompatibilities = [],
        public array $decisions = [],
        public array $warnings = [],
        public array $blockers = [],
        public array $effects = [],
        public array $organizationChanges = [],
    ) {}

    public function isExecutable(): bool
    {
        return $this->blockers === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [...get_object_vars($this), 'isExecutable' => $this->isExecutable()];
    }
}
