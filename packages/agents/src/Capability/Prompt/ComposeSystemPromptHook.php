<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Prompt;

use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Profile\Contracts\CanAcceptAgentProfile;
use Cognesy\Agents\Profile\SystemPromptComposer;
use Override;

final readonly class ComposeSystemPromptHook implements HookInterface, CanAcceptAgentProfile
{
    private const START = '<!-- cognesy-agent-profile:start -->';
    private const END = '<!-- cognesy-agent-profile:end -->';

    public function __construct(
        private SystemPromptComposer $composer,
        private bool $overrideExisting = false,
        private AgentProfile $profile = new AgentProfile(
            identity: new \Cognesy\Agents\Profile\AgentIdentity('anonymous', ''),
            tools: new \Cognesy\Agents\Profile\ToolProfileList(),
            capabilities: new \Cognesy\Agents\Profile\CapabilityProfileList(),
            hooks: new \Cognesy\Agents\Profile\HookProfileList(),
        ),
    ) {}

    #[Override]
    public function handle(HookContext $context): HookContext {
        $existing = $this->withoutOwnedBlock($context->state()->context()->systemPrompt());
        $base = $this->overrideExisting ? '' : trim($existing);
        $generated = self::START . "\n" . $this->composer->compose($this->profile) . "\n" . self::END;
        $prompt = $base === '' ? $generated : $base . "\n\n" . $generated;
        return $context->withState($context->state()->withSystemPrompt($prompt));
    }

    #[Override]
    public function withAgentProfile(AgentProfile $profile): static {
        return new self($this->composer, $this->overrideExisting, $profile);
    }

    private function withoutOwnedBlock(string $prompt): string {
        $pattern = sprintf(
            '/\s*%s.*?%s\s*/s',
            preg_quote(self::START, '/'),
            preg_quote(self::END, '/'),
        );
        return preg_replace($pattern, '', $prompt) ?? $prompt;
    }
}
