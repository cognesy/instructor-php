<?php declare(strict_types=1);
namespace Cognesy\Addons\Agent\Capabilities\Subagent;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Capabilities\Skills\SkillLibrary;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Polyglot\Inference\LLMProvider;

class UseSubagents implements AgentCapability
{
    private SubagentPolicy $policy;

    public function __construct(
        private ?AgentRegistry $registry = null,
        ?SubagentPolicy $policy = null,
        private ?SkillLibrary $skillLibrary = null,
    ) {
        $this->policy = $policy ?? SubagentPolicy::default();
    }

    public static function withDepth(
        int $maxDepth,
        ?AgentRegistry $registry = null,
        ?int $summaryMaxChars = null,
        ?SkillLibrary $skillLibrary = null,
    ): self {
        return new self(
            registry: $registry,
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
            $llmProvider = ($driver instanceof ToolCallingDriver) 
                ? $driver->getLlmProvider() 
                : LLMProvider::new();

            $subagentTool = new SpawnSubagentTool(
                parentAgent: $agent,
                registry: $this->registry ?? new AgentRegistry(),
                skillLibrary: $this->skillLibrary,
                parentLlmProvider: $llmProvider,
                currentDepth: 0,
                maxDepth: $this->policy->maxDepth,
                summaryMaxChars: $this->policy->summaryMaxChars,
                policy: $this->policy,
            );

            return $agent->withTools($agent->tools()->merge(new Tools($subagentTool)));
        });
    }
}
