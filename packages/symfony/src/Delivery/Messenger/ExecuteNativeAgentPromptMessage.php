<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Messenger;

final readonly class ExecuteNativeAgentPromptMessage
{
    public function __construct(
        public string $definition,
        public string $prompt,
        public ?string $sessionId = null,
    ) {}
}
