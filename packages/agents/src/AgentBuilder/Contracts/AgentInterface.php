<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Contracts;

use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;
use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * High-level interface for agent definitions.
 *
 * Extends CanControlAgentLoop to provide iterative execution capabilities,
 * plus additional methods for configuration and event handling.
 */
interface AgentInterface extends CanControlAgentLoop
{
    public function descriptor(): AgentDescriptor;

    public function build(): AgentLoop;

    public function run(AgentState $state): AgentState;

    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): self;

    /**
     * @param callable(object): void|null $listener
     */
    public function wiretap(?callable $listener): self;

    /**
     * @param callable(object): void|null $listener
     */
    public function onEvent(string $class, ?callable $listener): self;

    public function serializeConfig(): array;

    public static function fromConfig(array $config): AgentInterface;
}
