<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Bash;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Sandbox\Config\ExecutionPolicy;

final class UseBash implements AgentCapability
{
    public function __construct(
        private ?ExecutionPolicy $policy = null,
        private string $baseDir = '',
        private ?BashPolicy $outputPolicy = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $bashTool = new BashTool(
            policy: $this->policy,
            baseDir: $this->baseDir,
            outputPolicy: $this->outputPolicy
        );
        $builder->withTools(new Tools($bashTool));
    }
}
