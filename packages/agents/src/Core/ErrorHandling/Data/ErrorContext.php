<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\ErrorHandling\Data;

use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorType;

final readonly class ErrorContext
{
    public function __construct(
        public ErrorType $type,
        public int $consecutiveFailures,
        public int $totalFailures,
        public ?string $message = null,
        public ?string $toolName = null,
        public array $metadata = [],
    ) {}

    public static function none(): self {
        return new self(
            type: ErrorType::Unknown,
            consecutiveFailures: 0,
            totalFailures: 0,
        );
    }
}
