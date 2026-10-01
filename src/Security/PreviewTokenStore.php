<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Security;

use GlpiPlugin\Ticketops\Domain\TicketOperationPlan;

final class PreviewTokenStore
{
    private const SESSION_KEY = 'ticketops_preview_tokens';
    private const LIFETIME = 600;

    public function issue(TicketOperationPlan $plan, int $userId): string
    {
        $this->removeExpired();
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY][$token] = [
            'digest' => $this->digest($plan),
            'expires' => time() + self::LIFETIME,
            'ticket_id' => $plan->ticketId,
            'user_id' => $userId,
        ];

        return $token;
    }

    public function consume(string $token, TicketOperationPlan $plan, int $userId): bool
    {
        $stored = $_SESSION[self::SESSION_KEY][$token] ?? null;
        unset($_SESSION[self::SESSION_KEY][$token]);
        if (!is_array($stored)
            || (int) ($stored['expires'] ?? 0) < time()
            || (int) ($stored['ticket_id'] ?? 0) !== $plan->ticketId
            || (int) ($stored['user_id'] ?? 0) !== $userId
            || !is_string($stored['digest'] ?? null)) {
            return false;
        }

        return hash_equals($stored['digest'], $this->digest($plan));
    }

    private function removeExpired(): void
    {
        $tokens = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($tokens)) {
            $_SESSION[self::SESSION_KEY] = [];

            return;
        }
        foreach ($tokens as $token => $stored) {
            if (!is_array($stored) || (int) ($stored['expires'] ?? 0) < time()) {
                unset($_SESSION[self::SESSION_KEY][$token]);
            }
        }
    }

    private function digest(TicketOperationPlan $plan): string
    {
        $state = $plan->toArray();
        $this->sortRecursively($state);

        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    /** @param array<mixed> $value */
    private function sortRecursively(array &$value): void
    {
        foreach ($value as &$child) {
            if (is_array($child)) {
                $this->sortRecursively($child);
            }
        }
        unset($child);
        if (!array_is_list($value)) {
            ksort($value);
        }
    }
}
