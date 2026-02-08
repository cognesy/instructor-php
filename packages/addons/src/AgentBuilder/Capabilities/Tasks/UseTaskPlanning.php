<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\Tasks;

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;

class UseTaskPlanning implements AgentCapability
{
    public function __construct(
        private ?TodoPolicy $policy = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $policy = $this->policy ?? new TodoPolicy();
        
        $builder->withTools(new Tools(new TodoWriteTool($policy)));
        
        $builder->addProcessor(new TodoReminderProcessor($policy));
        $builder->addProcessor(new TodoRenderProcessor($policy));
        $builder->addProcessor(new PersistTasksProcessor());
    }
}
