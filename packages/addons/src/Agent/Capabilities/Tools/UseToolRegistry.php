<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Tools;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Contracts\ToolRegistryInterface;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Tools\ToolsTool;

class UseToolRegistry implements AgentCapability
{
    public function __construct(
        private readonly ToolRegistryInterface $registry,
        private readonly ?string $locale = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void
    {
        $builder->withTools(new Tools(
            new ToolsTool($this->registry, $this->locale),
        ));
    }
}
