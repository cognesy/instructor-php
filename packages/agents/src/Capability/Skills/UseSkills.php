<?php declare(strict_types=1);
namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Hook\Collections\HookTriggers;

final class UseSkills implements CanProvideAgentCapability
{
    public function __construct(
        private SkillLibrary $library,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_skills';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $library = $this->library;

        $agent = $agent->withTools(
            $agent->tools()->merge(new Tools(LoadSkillTool::withLibrary($library)))
        );
        $hooks = $agent->hooks()->with(
            hook: new AppendSkillMetadataHook($library),
            triggerTypes: HookTriggers::beforeStep(),
        );
        return $agent->withHooks($hooks);
    }
}
