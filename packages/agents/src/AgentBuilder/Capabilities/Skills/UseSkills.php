<?php declare(strict_types=1);
namespace Cognesy\Agents\AgentBuilder\Capabilities\Skills;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;

class UseSkills implements AgentCapability
{
    public function __construct(
        private ?SkillLibrary $library = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $library = $this->library ?? new SkillLibrary();

        $builder->withTools(new Tools(LoadSkillTool::withLibrary($library)));
        $builder->addHook(HookType::BeforeStep, new AppendSkillMetadataHook($library));
    }
}
