<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Bash;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;

class UseBash implements AgentCapability
{
    public function __construct(
        private ?ExecutionPolicy $policy = null,
        private ?string $baseDir = null,
        private int $timeout = 120,
        private ?BashPolicy $outputPolicy = null,
    ) {}

    public function install(AgentBuilder $builder): void {
        $bashTool = new BashTool(
            policy: $this->policy,
            baseDir: $this->baseDir,
            timeout: $this->timeout,
            outputPolicy: $this->outputPolicy
        );
        $builder->withTools(new Tools($bashTool));
    }
}
