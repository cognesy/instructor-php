<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Closure;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanProvideDeferredTools;
use Cognesy\Agents\Builder\Data\DeferredToolContext;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Events\Contracts\CanHandleEvents;

final readonly class UseToolFactory implements CanProvideAgentCapability
{
    /** @var Closure(Tools, CanUseTools, CanHandleEvents): ToolInterface */
    private Closure $factory;

    /** @param callable(Tools, CanUseTools, CanHandleEvents): ToolInterface $factory */
    public function __construct(
        callable $factory,
    ) {
        $this->factory = Closure::fromCallable($factory);
    }

    #[\Override]
    public static function capabilityName(): string {
        return 'use_tool_factory';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $provider = new class($this->factory) implements CanProvideDeferredTools {
            /** @var Closure(Tools, CanUseTools, CanHandleEvents): ToolInterface */
            private Closure $factory;

            /** @param Closure(Tools, CanUseTools, CanHandleEvents): ToolInterface $factory */
            public function __construct(Closure $factory) {
                $this->factory = $factory;
            }

            #[\Override]
            public function provideTools(DeferredToolContext $context): Tools {
                $tool = ($this->factory)(
                    $context->tools(),
                    $context->toolUseDriver(),
                    $context->events(),
                );
                return new Tools($tool);
            }
        };

        return $agent->withDeferredTools(
            $agent->deferredTools()->withProvider($provider)
        );
    }
}
