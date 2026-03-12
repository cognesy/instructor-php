<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Contract;

use Cognesy\AgentCtrl\Dto\AgentResponse;

/**
 * Contract for executing prompts against a CLI-based code agent.
 */
interface AgentBridge
{
    /**
     * Execute a prompt synchronously.
     */
    public function execute(string|\Stringable $prompt): AgentResponse;

    /**
     * Execute a prompt with streaming output.
     */
    public function executeStreaming(string|\Stringable $prompt, ?StreamHandler $handler): AgentResponse;
}
