<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Contract;

use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Dto\AgentResponse;

/**
 * Fluent builder interface for configuring and executing agent bridges.
 */
interface AgentBridgeBuilder
{
    // Common configuration methods

    /**
     * Set the model to use.
     */
    public function withModel(string $model): static;

    /**
     * Set execution timeout in seconds.
     */
    public function withTimeout(int $seconds): static;

    /**
     * Set the working directory for the agent.
     */
    public function inDirectory(string $path): static;

    /**
     * Set the sandbox driver for execution.
     */
    public function withSandboxDriver(SandboxDriver $driver): static;

    // Streaming callbacks

    /**
     * Set callback for text content events.
     *
     * @param callable(string): void $handler
     */
    public function onText(callable $handler): static;

    /**
     * Set callback for tool use events.
     *
     * @param callable(string $tool, array $input, ?string $output): void $handler
     */
    public function onToolUse(callable $handler): static;

    /**
     * Set callback for completion events.
     *
     * @param callable(AgentResponse): void $handler
     */
    public function onComplete(callable $handler): static;

    // Execution

    /**
     * Execute the prompt synchronously.
     */
    public function execute(string $prompt): AgentResponse;

    /**
     * Execute the prompt with streaming output.
     */
    public function executeStreaming(string $prompt): AgentResponse;

    /**
     * Build the configured bridge for advanced use cases.
     */
    public function build(): AgentBridge;
}
