<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\SkillLibrary;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\CanUseTools;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Agents\Events\CanEmitAgentEvents;
use Cognesy\Polyglot\Inference\LLMProvider;

class UseSubagents implements AgentCapability
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
        ?int $summaryMaxChars = null,
        ?SkillLibrary $skillLibrary = null,
    ): self {
        return new self(
            provider: $provider,
            policy: new SubagentPolicy(
                maxDepth: $maxDepth,
                summaryMaxChars: $summaryMaxChars ?? 8000,
            ),
            skillLibrary: $skillLibrary,
        );
    }

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $builder->addToolFactory(function (Tools $tools, CanUseTools $driver, CanEmitAgentEvents $emitter) {
            $llmProvider = LLMProvider::new();
            if ($driver instanceof ToolCallingDriver) {
                $llmProvider = $driver->getLlmProvider();
            }

            return new SpawnSubagentTool(
                parentTools: $tools,
                parentDriver: $driver,
                provider: $this->resolveProvider(),
                skillLibrary: $this->skillLibrary,
                parentLlmProvider: $llmProvider,
                currentDepth: 0,
                maxDepth: $this->policy->maxDepth,
                summaryMaxChars: $this->policy->summaryMaxChars,
                policy: $this->policy,
                eventEmitter: $emitter,
            );
        });
    }

    private function resolveProvider(): AgentDefinitionProvider {
        return $this->provider ?? new EmptySubagentProvider();
    }
}
