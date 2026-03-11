<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

/**
 * Tracks the active skill's metadata in AgentState after load_skill completes.
 *
 * Triggers on AfterToolUse. When load_skill completes successfully,
 * updates the state metadata with the loaded skill's allowed-tools list
 * and model override. Clears values when a skill without them is loaded.
 */
final readonly class TrackActiveSkillHook implements HookInterface
{
    public function __construct(private SkillLibrary $library) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $toolExecution = $context->toolExecution();
        if ($toolExecution === null) {
            return $context;
        }

        // Only track load_skill calls
        if ($toolExecution->toolCall()->name() !== 'load_skill') {
            return $context;
        }

        // Only track successful loads (not list operations)
        $args = $toolExecution->toolCall()->args();
        $skillName = $args['skill_name'] ?? null;
        if ($skillName === null) {
            return $context;
        }

        $skill = $this->library->getSkill($skillName);
        $allowedTools = ($skill !== null && $skill->allowedTools !== [])
            ? $skill->allowedTools
            : [];
        $model = ($skill !== null) ? $skill->model : null;

        $state = $context->state()
            ->withMetadata(SkillToolFilterHook::META_KEY, $allowedTools)
            ->withMetadata(SkillModelOverrideHook::META_KEY, $model ?? '');

        return $context->withState($state);
    }
}
