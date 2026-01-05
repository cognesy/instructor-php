<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Extras\Tasks\PersistTasksProcessor;
use Cognesy\Addons\Agent\Extras\Tasks\TodoPolicy;
use Cognesy\Addons\Agent\Extras\Tasks\TodoReminderProcessor;
use Cognesy\Addons\Agent\Extras\Tasks\TodoRenderProcessor;
use Cognesy\Addons\Agent\Extras\Tasks\TodoWriteTool;

class UseTaskPlanning implements AgentCapability
{
    public function __construct(
        private ?TodoPolicy $policy = null,
    ) {}

    public function install(AgentBuilder $builder): void {
        $policy = $this->policy ?? new TodoPolicy();
        
        $builder->withTools(new Tools(new TodoWriteTool($policy)));
        
        $builder->addProcessor(new TodoReminderProcessor($policy));
        $builder->addProcessor(new TodoRenderProcessor($policy));
        $builder->addProcessor(new PersistTasksProcessor());
    }
}
