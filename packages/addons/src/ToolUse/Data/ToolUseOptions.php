<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

final readonly class ToolUseOptions
{
    public function __construct(
        public int $maxSteps = 3,
        public int $maxTokens = 8192,
        public int $maxExecutionSeconds = 30,
        public int $maxRetries = 3,
        public array $finishOnReasons = [],
        public bool $parallelToolCalls = false,
        public bool $throwOnToolFailure = false,
        public string $model = '',
        public array $responseFormat = [],
        public array $driverOptions = [],
    ) {}
}

