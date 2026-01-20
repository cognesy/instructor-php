<?php declare(strict_types=1);
namespace Cognesy\Addons\AgentBuilder\Capabilities\Skills;

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;

class UseSkills implements AgentCapability
{
    public function __construct(
        private ?SkillLibrary $library = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $library = $this->library ?? new SkillLibrary();
        
        $builder->withTools(new Tools(LoadSkillTool::withLibrary($library)));
        $builder->addProcessor(new AppendSkillMetadata($library));
    }
}
