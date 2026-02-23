<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Dto;

/**
 * Unified stream error representation across all agent types.
 */
final readonly class StreamError
{
    /**
     * @param array<string,mixed> $details
     */
    public function __construct(
        public string $message,
        public ?string $code = null,
        public array $details = [],
    ) {}
}
