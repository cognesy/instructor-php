<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Hooks\ApplyContextConfigHook;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

final readonly class UseContextConfig implements CanProvideAgentCapability
{
    public function __construct(
        private string $systemPrompt = '',
        private ?ResponseFormat $responseFormat = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_context_config';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $prompt = trim($this->systemPrompt);
        $format = $this->responseFormat;

        $hasFormat = $format !== null && !$format->isEmpty();
        if ($prompt === '' && !$hasFormat) {
            return $agent;
        }

        $hooks = $agent->hooks()->with(
            hook: new ApplyContextConfigHook($prompt, $format),
            triggerTypes: HookTriggers::beforeStep(),
            priority: 100,
            name: 'context:config',
        );
        return $agent->withHooks($hooks);
    }
}
