<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Skills;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

class AppendSkillMetadata implements CanProcessAgentState
{
    private const MARKER = '<skills-metadata>';

    public function __construct(private SkillLibrary $library) {}

    #[\Override]
    public function canProcess(AgentState $state): bool {
        return true;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $state;

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
