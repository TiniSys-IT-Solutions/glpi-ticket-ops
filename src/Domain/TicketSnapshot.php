<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Domain;

final readonly class TicketSnapshot
{
    /**
     * @param list<array<string, mixed>> $requesters
     * @param list<array<string, mixed>> $assignees
     * @param list<array<string, mixed>> $observers
     * @param array<string, mixed> $linkedState
     * @param array<string, scalar|null> $nativeFields
     */
    public function __construct(
        public int $id,
        public string $title,
        public int $entityId,
        public int $categoryId,
        public int $locationId,
        public int $templateId,
        public int $slaId,
        public int $olaId,
        public string $dateModified,
        public array $requesters,
        public array $assignees,
        public array $observers,
        public int $assignedUserCount = 0,
        public int $assignedGroupCount = 0,
        public int $assignedSupplierCount = 0,
        public int $status = 0,
        public int $slaOwnId = 0,
        public int $olaOwnId = 0,
        public array $linkedState = [],
        public array $nativeFields = [],
    ) {}

    /** @return array<string, mixed> */
    public function fingerprintData(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'sla_own' => $this->slaOwnId,
            'ola_own' => $this->olaOwnId,
            'linked_state' => $this->linkedState,
            'native_fields' => $this->nativeFields,
            'entity' => $this->entityId,
            'category' => $this->categoryId,
            'location' => $this->locationId,
            'template' => $this->templateId,
            'sla' => $this->slaId,
            'ola' => $this->olaId,
            'date_mod' => $this->dateModified,
            'requesters' => $this->requesters,
            'assignees' => $this->assignees,
            'observers' => $this->observers,
            'assigned_users' => $this->assignedUserCount,
            'assigned_groups' => $this->assignedGroupCount,
            'assigned_suppliers' => $this->assignedSupplierCount,
        ];
    }
}
