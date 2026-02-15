<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Bash;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Sandbox\Config\ExecutionPolicy;

final class UseBash implements CanProvideAgentCapability
{
    public function __construct(
        private ?ExecutionPolicy $policy = null,
        private string $baseDir = '',
        private ?BashPolicy $outputPolicy = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_bash';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $bashTool = new BashTool(
            policy: $this->policy,
            baseDir: $this->baseDir,
            outputPolicy: $this->outputPolicy
        );
        return $agent->withTools(
            $agent->tools()->merge(new Tools($bashTool))
        );
    }
}
