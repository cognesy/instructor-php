<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Prompt;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Profile\SystemPromptComposer;
use Override;

final readonly class UseSystemPrompt implements CanProvideAgentCapability
{
    /** @param list<string> $extraGuidelines */
    public function __construct(
        private ?string $preamble = null,
        private array $extraGuidelines = [],
        private ?string $append = null,
        private bool $overrideExisting = false,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'use_system_prompt';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $hook = new ComposeSystemPromptHook(
            composer: new SystemPromptComposer(
                preamble: $this->preamble,
                extraGuidelines: $this->extraGuidelines,
                append: $this->append,
            ),
            overrideExisting: $this->overrideExisting,
        );
        return $agent->withHooks($agent->hooks()->with(
            hook: $hook,
            triggerTypes: HookTriggers::beforeStep(),
            priority: -1000,
            name: 'profile:system_prompt',
        ));
    }
}
