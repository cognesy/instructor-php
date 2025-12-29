<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\AgentBridgeBuilder;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\CallbackStreamHandler;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentErrorOccurred;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted;
use Cognesy\AgentCtrl\Event\AgentTextReceived;
use Cognesy\AgentCtrl\Event\AgentToolUsed;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Base builder with common configuration methods.
 */
abstract class AbstractBridgeBuilder implements AgentBridgeBuilder
{
    use HandlesEvents;

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

    public function __construct()
    {
        $this->events = EventBusResolver::default();
    }

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
        $this->dispatch(new AgentExecutionStarted(
            agentType: $this->agentType(),
            prompt: $prompt,
            model: $this->model,
            workingDirectory: $this->workingDirectory,
        ));

        try {
            $bridge = $this->build();
            $response = $bridge->execute($prompt);

            $this->dispatch(AgentExecutionCompleted::fromResponse($response));

            return $response;
        } catch (Throwable $e) {
            $this->dispatch(AgentErrorOccurred::fromException($this->agentType(), $e));
            throw $e;
        }
    }

    #[\Override]
    public function executeStreaming(string $prompt): AgentResponse
    {
        $this->dispatch(new AgentExecutionStarted(
            agentType: $this->agentType(),
            prompt: $prompt,
            model: $this->model,
            workingDirectory: $this->workingDirectory,
        ));

        try {
            $bridge = $this->build();
            $handler = $this->buildStreamHandler();
            $response = $bridge->executeStreaming($prompt, $handler);

            if ($handler !== null) {
                $handler->onComplete($response);
            }

            $this->dispatch(AgentExecutionCompleted::fromResponse($response));

            return $response;
        } catch (Throwable $e) {
            $this->dispatch(AgentErrorOccurred::fromException($this->agentType(), $e));
            throw $e;
        }
    }

    protected function buildStreamHandler(): ?StreamHandler
    {
        // We always want a handler to emit events, even if user didn't provide callbacks
        return new CallbackStreamHandler(
            onText: function(string $text): void {
                $this->dispatch(new AgentTextReceived($this->agentType(), $text));
                if ($this->onTextCallback !== null) {
                    ($this->onTextCallback)($text);
                }
            },
            onToolUse: function($toolCall): void {
                $this->dispatch(AgentToolUsed::fromToolCall($this->agentType(), $toolCall));
                if ($this->onToolUseCallback !== null) {
                    ($this->onToolUseCallback)($toolCall->tool, $toolCall->input, $toolCall->output);
                }
            },
            onComplete: $this->onCompleteCallback,
        );
    }
}
