<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

final class ToolUseOptions
{
    public function __construct(
        public readonly int $maxSteps = 3,
        public readonly int $maxTokens = 8192,
        public readonly int $maxExecutionSeconds = 30,
        public readonly int $maxRetries = 3,
        public readonly array $finishOnReasons = [],
        public readonly bool $parallelToolCalls = false,
        public readonly bool $throwOnToolFailure = false,
        public readonly string $model = '',
        public readonly array $responseFormat = [],
        public readonly array $driverOptions = [],
    ) {}
}

