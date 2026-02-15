<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Tools;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Tool\Contracts\CanManageTools;

class UseToolRegistry implements CanProvideAgentCapability
{
    public function __construct(
        private readonly CanManageTools $registry,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_tool_registry';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        return $agent->withTools($agent->tools()->merge(new Tools(
            new ToolsTool($this->registry),
        )));
    }
}
