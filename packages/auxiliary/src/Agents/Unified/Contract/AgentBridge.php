<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Unified\Contract;

use Cognesy\Auxiliary\Agents\Unified\Dto\AgentResponse;

/**
 * Contract for executing prompts against a CLI-based code agent.
 */
interface AgentBridge
{
    /**
     * Execute a prompt synchronously.
     */
    public function execute(string $prompt): AgentResponse;

    /**
     * Execute a prompt with streaming output.
     */
    public function executeStreaming(string $prompt, ?StreamHandler $handler): AgentResponse;
}
