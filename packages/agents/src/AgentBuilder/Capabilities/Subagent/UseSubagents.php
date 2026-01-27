<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent;

use Cognesy\Agents\Agent\Agent;
use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Skills\SkillLibrary;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Polyglot\Inference\LLMProvider;

class UseSubagents implements AgentCapability
{
    private SubagentPolicy $policy;

    public function __construct(
        private ?SubagentProvider $provider = null,
        ?SubagentPolicy $policy = null,
        private ?SkillLibrary $skillLibrary = null,
    ) {
        $this->policy = $policy ?? SubagentPolicy::default();
    }

    public static function withDepth(
        int $maxDepth,
        ?SubagentProvider $provider = null,
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
        $builder->onBuild(function (Agent $agent) {
            $driver = $agent->driver();
            $llmProvider = LLMProvider::new();
            if ($driver instanceof ToolCallingDriver) {
                $llmProvider = $driver->getLlmProvider();
            }

            $subagentTool = new SpawnSubagentTool(
                parentAgent: $agent,
                provider: $this->resolveProvider(),
                skillLibrary: $this->skillLibrary,
                parentLlmProvider: $llmProvider,
                currentDepth: 0,
                maxDepth: $this->policy->maxDepth,
                summaryMaxChars: $this->policy->summaryMaxChars,
                policy: $this->policy,
                eventEmitter: $agent->eventEmitter(),
            );

            return $agent->with(tools: $agent->tools()->merge(new Tools($subagentTool)));
        });
    }

    private function resolveProvider(): SubagentProvider {
        return $this->provider ?? new EmptySubagentProvider();
    }
}
