<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Skills;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Hook that prepends skill metadata to messages on first step.
 */
final readonly class AppendSkillMetadataHook implements HookInterface
{
    private const MARKER = '<skills-metadata>';

    public function __construct(private SkillLibrary $library) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $skills = $this->library->listSkills();

        if ($skills === [] || $this->hasInjectedSkills($state->messages())) {
            return $context;
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

        return $context->withState($state->withMessages($messages));
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
