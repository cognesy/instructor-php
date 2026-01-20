<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\Tools;

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\AgentBuilder\Capabilities\Tools\ToolRegistryInterface;
use Cognesy\Addons\AgentBuilder\Capabilities\Tools\ToolsTool;

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
