<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support\Discovery;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Override;

final readonly class RequiresArgumentCapability implements CanProvideAgentCapability
{
    public function __construct(private string $value) {}

    #[Override]
    public static function capabilityName(): string {
        return 'requires-argument';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent;
    }
}
