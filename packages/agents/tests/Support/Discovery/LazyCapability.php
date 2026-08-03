<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support\Discovery;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Override;

final class LazyCapability implements CanProvideAgentCapability
{
    public static int $constructions = 0;

    public function __construct() {
        self::$constructions++;
    }

    #[Override]
    public static function capabilityName(): string {
        return 'lazy-capability';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent;
    }
}
