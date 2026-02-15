<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Closure;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Drivers\CanUseTools;

final readonly class UseDriverDecorator implements CanProvideAgentCapability
{
    /** @var Closure(CanUseTools): CanUseTools */
    private Closure $decorator;

    /** @param callable(CanUseTools): CanUseTools $decorator */
    public function __construct(
        callable $decorator,
    ) {
        $this->decorator = Closure::fromCallable($decorator);
    }

    #[\Override]
    public static function capabilityName(): string {
        return 'use_driver_decorator';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent->withToolUseDriver(
            ($this->decorator)($agent->toolUseDriver())
        );
    }
}
