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
        private ?SkillPreprocessor $preprocessor = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_skills';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $library = $this->library;

        $agent = $agent->withTools(
            $agent->tools()->merge(new Tools(LoadSkillTool::fromLibrary($library, preprocessor: $this->preprocessor)))
        );
        $hooks = $agent->hooks()
            ->with(
                hook: new AppendSkillMetadataHook($library),
                triggerTypes: HookTriggers::beforeStep(),
            )
            ->with(
                hook: new TrackActiveSkillHook($library),
                triggerTypes: HookTriggers::afterToolUse(),
            )
            ->with(
                hook: new SkillToolFilterHook(),
                triggerTypes: HookTriggers::beforeToolUse(),
            )
            ->with(
                hook: new SkillModelOverrideHook(),
                triggerTypes: HookTriggers::beforeStep(),
            );
        return $agent->withHooks($hooks);
    }
}
