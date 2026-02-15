<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

/**
 * @template TAgent of CanConfigureAgent
 */
interface CanProvideAgentCapability
{
    public static function capabilityName(): string;

    /**
     * @param TAgent $agent
     * @return TAgent
     */
    public function configure(CanConfigureAgent $agent): CanConfigureAgent;
}
