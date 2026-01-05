<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Skills\AppendSkillMetadata;
use Cognesy\Addons\Agent\Skills\LoadSkillTool;
use Cognesy\Addons\Agent\Skills\SkillLibrary;

class UseSkills implements AgentCapability
{
    public function __construct(
        private ?SkillLibrary $library = null,
    ) {}

    public function install(AgentBuilder $builder): void {
        $library = $this->library ?? new SkillLibrary();
        
        $builder->withTools(new Tools(LoadSkillTool::withLibrary($library)));
        $builder->addProcessor(new AppendSkillMetadata($library));
    }
}
