<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\AgentBridgeBuilder;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\CallbackStreamHandler;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;

/**
 * Base builder with common configuration methods.
 */
abstract class AbstractBridgeBuilder implements AgentBridgeBuilder
{
    protected ?string $model = null;
    protected int $timeout = 120;
    protected ?string $workingDirectory = null;
    protected SandboxDriver $sandboxDriver = SandboxDriver::Host;
    protected int $maxRetries = 0;

    /** @var callable(string): void|null */
    protected $onTextCallback = null;

    /** @var callable(string, array, ?string): void|null */
    protected $onToolUseCallback = null;

    /** @var callable(AgentResponse): void|null */
    protected $onCompleteCallback = null;

    abstract public function agentType(): AgentType;

    #[\Override]
    public function withModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    #[\Override]
    public function withTimeout(int $seconds): static
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    #[\Override]
    public function inDirectory(string $path): static
    {
        $this->workingDirectory = $path;
        return $this;
    }

    #[\Override]
    public function withSandboxDriver(SandboxDriver $driver): static
    {
        $this->sandboxDriver = $driver;
        return $this;
    }

    public function withMaxRetries(int $retries): static
    {
        $this->maxRetries = max(0, $retries);
        return $this;
    }

    #[\Override]
    public function onText(callable $handler): static
    {
        $this->onTextCallback = $handler;
        return $this;
    }

    #[\Override]
    public function onToolUse(callable $handler): static
    {
        $this->onToolUseCallback = $handler;
        return $this;
    }

    #[\Override]
    public function onComplete(callable $handler): static
    {
        $this->onCompleteCallback = $handler;
        return $this;
    }

    #[\Override]
    public function execute(string $prompt): AgentResponse
    {
        $bridge = $this->build();
        return $bridge->execute($prompt);
    }

    #[\Override]
    public function executeStreaming(string $prompt): AgentResponse
    {
        $bridge = $this->build();
        $handler = $this->buildStreamHandler();
        $response = $bridge->executeStreaming($prompt, $handler);

        if ($handler !== null) {
            $handler->onComplete($response);
        }

        return $response;
    }

    protected function buildStreamHandler(): ?StreamHandler
    {
        if ($this->onTextCallback === null
            && $this->onToolUseCallback === null
            && $this->onCompleteCallback === null
        ) {
            return null;
        }

        return new CallbackStreamHandler(
            onText: $this->onTextCallback,
            onToolUse: $this->onToolUseCallback !== null
                ? fn($toolCall) => ($this->onToolUseCallback)($toolCall->tool, $toolCall->input, $toolCall->output)
                : null,
            onComplete: $this->onCompleteCallback,
        );
    }
}
