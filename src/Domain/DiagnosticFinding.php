<?php

declare(strict_types=1);

namespace GlpiPlugin\Ticketops\Domain;

final readonly class DiagnosticFinding
{
    public const SUCCESS = 'success';
    public const INFORMATION = 'information';
    public const WARNING = 'warning';
    public const BLOCKING = 'blocking';

    /** @param array<string, scalar|null> $context */
    public function __construct(
        public string $code,
        public string $level,
        public string $message,
        public array $context = [],
    ) {
        if (!in_array($level, [self::SUCCESS, self::INFORMATION, self::WARNING, self::BLOCKING], true)) {
            throw new \InvalidArgumentException('Unsupported diagnostic level.');
        }
    }
}
