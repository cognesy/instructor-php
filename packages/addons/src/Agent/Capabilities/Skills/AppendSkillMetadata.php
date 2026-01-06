<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Skills;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;

class AppendSkillMetadata implements CanProcessAnyState
{
    private const MARKER = '<skills-metadata>';

    public function __construct(private SkillLibrary $library) {}

    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof AgentState;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $state;
        assert($newState instanceof AgentState);

        $skills = $this->library->listSkills();
        if ($skills !== [] && !$this->hasInjectedSkills($newState->messages())) {
            $content = implode("\n", [
                self::MARKER,
                $this->library->renderSkillList(),
                '</skills-metadata>',
                'Use load_skill to load a skill when needed.',
            ]);
            $messages = $newState->messages()->prependMessages([
                Message::asSystem($content),
            ]);
            $newState = $newState->withMessages($messages);
        }

        return $next ? $next($newState) : $newState;
    }

    private function hasInjectedSkills(Messages $messages): bool {
        foreach ($messages->toArray() as $message) {
            $content = $message['content'] ?? '';
            $role = $message['role'] ?? '';
            if ($role === 'system' && is_string($content) && str_contains($content, self::MARKER)) {
                return true;
            }
        }
        return false;
    }
}
