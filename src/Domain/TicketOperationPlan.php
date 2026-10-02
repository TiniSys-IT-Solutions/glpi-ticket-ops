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
        public ?string $ticketTitle = null,
    ) {}

    /** @param list<string> $blockers */
    public function withBlockers(array $blockers): self
    {
        return new self(
            $this->ticketId,
            $this->fingerprint,
            $this->sourceEntityId,
            $this->targetEntityId,
            $this->replacedActor,
            $this->newRequesterId,
            $this->preservedRelations,
            $this->incompatibilities,
            $this->decisions,
            $this->warnings,
            array_values(array_unique([...$this->blockers, ...$blockers])),
            $this->effects,
            $this->organizationChanges,
            $this->ticketTitle,
        );
    }

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
