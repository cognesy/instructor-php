<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Describe;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Override;

/** @implements CanProvideAgentCapability<CanConfigureAgent> */
final readonly class UseSelfDescription implements CanProvideAgentCapability
{
    #[Override]
    public static function capabilityName(): string {
        return 'use_self_description';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent->withTools($agent->tools()->withTool(new DescribeSelfTool()));
    }
}
