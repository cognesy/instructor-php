<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support\Discovery;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Override;

final class RootCapability implements CanProvideAgentCapability
{
    #[Override]
    public static function capabilityName(): string {
        return 'root-capability';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent;
    }
}
