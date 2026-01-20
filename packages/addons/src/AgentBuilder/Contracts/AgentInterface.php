<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Contracts;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\Core\Data\AgentDescriptor;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\Contracts\CanExecuteIteratively;
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
