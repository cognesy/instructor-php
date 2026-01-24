<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Contracts;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Agent\Contracts\CanExecuteIteratively;
use Cognesy\Agents\Agent\Data\AgentDescriptor;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @extends CanExecuteIteratively<AgentState>
 */
interface AgentInterface extends CanExecuteIteratively
{
    public function descriptor(): AgentDescriptor;

    public function build(): Agent;

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
