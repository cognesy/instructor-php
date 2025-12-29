<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Testing;

use Cognesy\Auxiliary\Agents\Unified\Dto\TokenUsage;
use Cognesy\Auxiliary\Agents\Unified\Dto\ToolCall;
use Cognesy\Auxiliary\Agents\Unified\Dto\AgentResponse;
use Cognesy\Auxiliary\Agents\Unified\Enum\AgentType;
use PHPUnit\Framework\Assert;

/**
 * Testing double for the AgentCtrl facade.
 *
 * Usage:
 *   $fake = AgentCtrl::fake(['Generated code response']);
 *   // ... run code that uses AgentCtrl ...
 *   $fake->assertExecuted();
 *   $fake->assertExecutedTimes(1);
 *   $fake->assertExecutedWith('Generate a migration');
 */
class AgentCtrlFake
{
    private array $responses;
    private int $callIndex = 0;
    private array $executions = [];

    // Builder state
    private ?string $model = null;
    private ?int $timeout = null;
    private ?string $directory = null;
    private AgentType $agentType = AgentType::ClaudeCode;

    /**
     * @param  array<string|AgentResponse>  $responses  Text responses or AgentResponse objects
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses ?: ['Fake agent response'];
    }

    /**
     * Get a fake Claude Code builder.
     */
    public function claudeCode(): self
    {
        $this->agentType = AgentType::ClaudeCode;

        return $this;
    }

    /**
     * Get a fake Codex builder.
     */
    public function codex(): self
    {
        $this->agentType = AgentType::Codex;

        return $this;
    }

    /**
     * Get a fake OpenCode builder.
     */
    public function openCode(): self
    {
        $this->agentType = AgentType::OpenCode;

        return $this;
    }

    /**
     * Get a fake builder by type.
     */
    public function make(AgentType $type): self
    {
        $this->agentType = $type;

        return $this;
    }

    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function withTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function inDirectory(string $path): self
    {
        $this->directory = $path;

        return $this;
    }

    public function withSandboxDriver(mixed $driver): self
    {
        // No-op for fake
        return $this;
    }

    public function withMaxRetries(int $retries): self
    {
        // No-op for fake
        return $this;
    }

    public function onText(callable $handler): self
    {
        // No-op for fake
        return $this;
    }

    public function onToolUse(callable $handler): self
    {
        // No-op for fake
        return $this;
    }

    public function onComplete(callable $handler): self
    {
        // No-op for fake
        return $this;
    }

    /**
     * Execute the agent with a prompt (returns fake response).
     */
    public function execute(string $prompt): AgentResponse
    {
        $this->executions[] = [
            'prompt' => $prompt,
            'agentType' => $this->agentType,
            'model' => $this->model,
            'timeout' => $this->timeout,
            'directory' => $this->directory,
            'streaming' => false,
        ];

        return $this->getNextResponse($prompt);
    }

    /**
     * Execute the agent with streaming (returns fake response).
     */
    public function executeStreaming(string $prompt): AgentResponse
    {
        $this->executions[] = [
            'prompt' => $prompt,
            'agentType' => $this->agentType,
            'model' => $this->model,
            'timeout' => $this->timeout,
            'directory' => $this->directory,
            'streaming' => true,
        ];

        return $this->getNextResponse($prompt);
    }

    /**
     * Get the next response from the queue.
     */
    private function getNextResponse(string $prompt): AgentResponse
    {
        $response = $this->responses[$this->callIndex]
            ?? $this->responses[array_key_last($this->responses)]
            ?? 'Fake response';

        $this->callIndex++;

        if ($response instanceof AgentResponse) {
            return $response;
        }

        return new AgentResponse(
            agentType: $this->agentType,
            text: $response,
            exitCode: 0,
            sessionId: 'fake-session-' . uniqid(),
            usage: new TokenUsage(input: 100, output: 50),
            cost: 0.001,
            toolCalls: [],
            rawResponse: null,
        );
    }

    /**
     * Assert that the agent was executed at least once.
     */
    public function assertExecuted(): void
    {
        Assert::assertNotEmpty(
            $this->executions,
            'Expected AgentCtrl to be executed, but it was not.'
        );
    }

    /**
     * Assert that the agent was not executed.
     */
    public function assertNotExecuted(): void
    {
        Assert::assertEmpty(
            $this->executions,
            'Expected AgentCtrl not to be executed, but it was.'
        );
    }

    /**
     * Assert that the agent was executed a specific number of times.
     */
    public function assertExecutedTimes(int $times): void
    {
        Assert::assertCount(
            $times,
            $this->executions,
            sprintf('Expected AgentCtrl to be executed %d times, but it was executed %d times.', $times, count($this->executions))
        );
    }

    /**
     * Assert that the agent was executed with a specific prompt.
     */
    public function assertExecutedWith(string $expectedPrompt): void
    {
        $found = false;
        foreach ($this->executions as $execution) {
            if (str_contains($execution['prompt'], $expectedPrompt)) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue(
            $found,
            sprintf('Expected AgentCtrl to be executed with prompt containing "%s", but it was not.', $expectedPrompt)
        );
    }

    /**
     * Assert that a specific agent type was used.
     */
    public function assertAgentType(AgentType $expectedType): void
    {
        $found = false;
        foreach ($this->executions as $execution) {
            if ($execution['agentType'] === $expectedType) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue(
            $found,
            sprintf('Expected AgentCtrl to use %s, but it did not.', $expectedType->name)
        );
    }

    /**
     * Assert that Claude Code agent was used.
     */
    public function assertUsedClaudeCode(): void
    {
        $this->assertAgentType(AgentType::ClaudeCode);
    }

    /**
     * Assert that Codex agent was used.
     */
    public function assertUsedCodex(): void
    {
        $this->assertAgentType(AgentType::Codex);
    }

    /**
     * Assert that OpenCode agent was used.
     */
    public function assertUsedOpenCode(): void
    {
        $this->assertAgentType(AgentType::OpenCode);
    }

    /**
     * Assert that streaming was used.
     */
    public function assertStreaming(): void
    {
        $found = false;
        foreach ($this->executions as $execution) {
            if ($execution['streaming'] === true) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue(
            $found,
            'Expected AgentCtrl to use streaming, but it did not.'
        );
    }

    /**
     * Get all recorded executions for inspection.
     */
    public function getExecutions(): array
    {
        return $this->executions;
    }

    /**
     * Reset the fake state.
     */
    public function reset(): void
    {
        $this->callIndex = 0;
        $this->executions = [];
        $this->model = null;
        $this->timeout = null;
        $this->directory = null;
        $this->agentType = AgentType::ClaudeCode;
    }

    /**
     * Create a fake response with custom properties.
     */
    public static function response(
        string $text = 'Fake response',
        int $exitCode = 0,
        AgentType $agentType = AgentType::ClaudeCode,
        ?string $sessionId = null,
        ?TokenUsage $usage = null,
        ?float $cost = null,
        array $toolCalls = [],
    ): AgentResponse {
        return new AgentResponse(
            agentType: $agentType,
            text: $text,
            exitCode: $exitCode,
            sessionId: $sessionId ?? 'fake-session-' . uniqid(),
            usage: $usage ?? new TokenUsage(input: 100, output: 50),
            cost: $cost ?? 0.001,
            toolCalls: $toolCalls,
            rawResponse: null,
        );
    }

    /**
     * Create a fake tool call.
     */
    public static function toolCall(
        string $tool,
        array $input = [],
        ?string $output = null,
        bool $isError = false,
    ): ToolCall {
        return new ToolCall(
            tool: $tool,
            input: $input,
            output: $output,
            callId: 'fake-call-' . uniqid(),
            isError: $isError,
        );
    }
}
