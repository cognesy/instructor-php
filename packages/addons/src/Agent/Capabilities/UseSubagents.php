<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities;

use Cognesy\Addons\Agent\Agent;
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\Agent\Skills\SkillLibrary;
use Cognesy\Addons\Agent\Agents\AgentRegistry;
use Cognesy\Addons\Agent\Tools\Subagent\SpawnSubagentTool;
use Cognesy\Addons\Agent\Tools\Subagent\SubagentPolicy;
use Cognesy\Polyglot\Inference\LLMProvider;

class UseSubagents implements AgentCapability
{
    private SubagentPolicy $policy;

    public function __construct(
        private ?AgentRegistry $registry = null,
        int|SubagentPolicy $policyOrDepth = 3,
        private ?int $summaryMaxChars = null,
        private ?SkillLibrary $skillLibrary = null,
    ) {
        if ($policyOrDepth instanceof SubagentPolicy) {
            $this->policy = $policyOrDepth;
        } else {
            $this->policy = new SubagentPolicy(
                maxDepth: $policyOrDepth,
                summaryMaxChars: $summaryMaxChars ?? 8000,
            );
        }
    }

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
