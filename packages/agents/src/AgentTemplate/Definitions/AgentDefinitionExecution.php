<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentTemplate\Definitions;

final readonly class AgentDefinitionExecution
{
    public function __construct(
        public ?int $maxSteps = null,
        public ?int $maxTokens = null,
        public ?int $timeoutSec = null,
        public ?string $errorPolicy = null,
        public ?int $errorPolicyMaxRetries = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_steps' => $this->maxSteps,
            'max_tokens' => $this->maxTokens,
            'timeout_sec' => $this->timeoutSec,
            'error_policy' => $this->errorPolicy,
            'error_policy_max_retries' => $this->errorPolicyMaxRetries,
        ];
    }
}
