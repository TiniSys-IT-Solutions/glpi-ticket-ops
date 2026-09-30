<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Domain;

final class TicketFingerprint
{
    /** @param array<string, mixed> $state */
    public static function fromState(array $state): string
    {
        ksort($state);

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }
}
