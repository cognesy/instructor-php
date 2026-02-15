<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Drivers\CanUseTools;

final readonly class UseDriver implements CanProvideAgentCapability
{
    public function __construct(
        private CanUseTools $driver,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_driver';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent->withToolUseDriver($this->driver);
    }
}
