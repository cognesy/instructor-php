<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Messenger;

final readonly class ExecuteAgentCtrlPromptMessage
{
    public function __construct(
        public string $prompt,
        public ?string $backend = null,
    ) {}
}
