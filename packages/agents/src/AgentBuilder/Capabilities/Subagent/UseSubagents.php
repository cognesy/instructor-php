<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\SkillLibrary;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanAcceptLLMProvider;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Polyglot\Inference\LLMProvider;

final class UseSubagents implements AgentCapability
{
    private SubagentPolicy $policy;

    public function __construct(
        private ?AgentDefinitionProvider $provider = null,
        ?SubagentPolicy $policy = null,
        private ?SkillLibrary $skillLibrary = null,
    ) {
        $this->policy = $policy ?? SubagentPolicy::default();
    }

    public static function withDepth(
        int $maxDepth,
        ?AgentDefinitionProvider $provider = null,
        ?SkillLibrary $skillLibrary = null,
    ): self {
        return new self(
            provider: $provider,
            policy: new SubagentPolicy(maxDepth: $maxDepth),
            skillLibrary: $skillLibrary,
        );
    }

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $builder->addToolFactory(function (Tools $tools, CanUseTools $driver, CanEmitAgentEvents $emitter) {
            $llmProvider = match (true) {
                $driver instanceof CanAcceptLLMProvider => $driver->llmProvider(),
                default => LLMProvider::new(),
            };

            return new SpawnSubagentTool(
                parentTools: $tools,
                parentDriver: $driver,
                provider: $this->resolveProvider(),
                skillLibrary: $this->skillLibrary,
                parentLlmProvider: $llmProvider,
                currentDepth: 0,
                policy: $this->policy,
                eventEmitter: $emitter,
            );
        });
    }

    private function resolveProvider(): AgentDefinitionProvider {
        return $this->provider ?? new EmptySubagentProvider();
    }
}
