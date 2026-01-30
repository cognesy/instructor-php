<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Skills;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Hook that prepends skill metadata to messages on first step.
 */
final readonly class AppendSkillMetadataHook implements Hook
{
    private const MARKER = '<skills-metadata>';

    public function __construct(private SkillLibrary $library) {}

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::BeforeStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        $skills = $this->library->listSkills();

        if ($skills === [] || $this->hasInjectedSkills($state->messages())) {
            return $state;
        }

        $content = implode("\n", [
            self::MARKER,
            $this->library->renderSkillList(),
            '</skills-metadata>',
            'Use load_skill to load a skill when needed.',
        ]);

        $messages = $state->messages()->prependMessages([
            Message::asSystem($content),
        ]);

        return $state->withMessages($messages);
    }

    private function hasInjectedSkills(Messages $messages): bool
    {
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
