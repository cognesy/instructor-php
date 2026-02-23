<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Closure;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Contract\AgentBridgeBuilder;
use Cognesy\AgentCtrl\Contract\StreamHandler;
use Cognesy\AgentCtrl\Dto\CallbackStreamHandler;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Dto\StreamError;
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

    /** @var (Closure(string): void)|null */
    protected ?Closure $onTextCallback = null;

    /** @var (Closure(string, array, ?string): void)|null */
    protected ?Closure $onToolUseCallback = null;

    /** @var (Closure(AgentResponse): void)|null */
    protected ?Closure $onCompleteCallback = null;

    /** @var (Closure(string, ?string): void)|null */
    protected ?Closure $onErrorCallback = null;

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

    #[\Override]
    public function onText(callable $handler): static
    {
        $this->onTextCallback = Closure::fromCallable($handler);
        return $this;
    }

    #[\Override]
    public function onToolUse(callable $handler): static
    {
        $this->onToolUseCallback = Closure::fromCallable($handler);
        return $this;
    }

    #[\Override]
    public function onComplete(callable $handler): static
    {
        $this->onCompleteCallback = Closure::fromCallable($handler);
        return $this;
    }

    #[\Override]
    public function onError(callable $handler): static
    {
        $this->onErrorCallback = Closure::fromCallable($handler);
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
                if ($text === '') {
                    return;
                }
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
            onError: function(StreamError $error): void {
                $this->dispatch(new AgentErrorOccurred(
                    agentType: $this->agentType(),
                    error: $error->message,
                ));
                if ($this->onErrorCallback !== null) {
                    ($this->onErrorCallback)($error->message, $error->code);
                }
            },
        );
    }
}
