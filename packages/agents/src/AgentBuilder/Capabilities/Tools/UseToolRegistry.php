<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tools;

use Cognesy\Agents\Agent\Collections\Tools;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;

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
