<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Subagent;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanProvideDeferredTools;
use Cognesy\Agents\Builder\Data\DeferredToolContext;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMProvider;
use Cognesy\Polyglot\Inference\LLMProvider;

final class UseSubagents implements CanProvideAgentCapability
{
    private SubagentPolicy $policy;

    public function __construct(
        private ?CanManageAgentDefinitions $provider = null,
        ?SubagentPolicy $policy = null,
        private ?SkillLibrary $skillLibrary = null,
    ) {
        $this->policy = $policy ?? SubagentPolicy::default();
    }

    public static function withDepth(
        int $maxDepth,
        ?CanManageAgentDefinitions $provider = null,
        ?SkillLibrary $skillLibrary = null,
    ): self {
        return new self(
            provider: $provider,
            policy: new SubagentPolicy(maxDepth: $maxDepth),
            skillLibrary: $skillLibrary,
        );
    }

    #[\Override]
    public static function capabilityName(): string {
        return 'use_subagents';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $provider = $this->resolveProvider();
        $skillLibrary = $this->skillLibrary;
        $policy = $this->policy;

        $deferred = new class($provider, $skillLibrary, $policy) implements CanProvideDeferredTools {
            public function __construct(
                private CanManageAgentDefinitions $provider,
                private ?SkillLibrary $skillLibrary,
                private SubagentPolicy $policy,
            ) {}

            #[\Override]
            public function provideTools(DeferredToolContext $context): Tools {
                $driver = $context->toolUseDriver();
                $llmProvider = match (true) {
                    $driver instanceof CanAcceptLLMProvider => $driver->llmProvider(),
                    default => LLMProvider::new(),
                };

                return new Tools(new SpawnSubagentTool(
                    parentTools: $context->tools(),
                    parentDriver: $driver,
                    provider: $this->provider,
                    skillLibrary: $this->skillLibrary,
                    parentLLMProvider: $llmProvider,
                    currentDepth: 0,
                    policy: $this->policy,
                    events: $context->events(),
                ));
            }
        };

        return $agent->withDeferredTools(
            $agent->deferredTools()->withProvider($deferred)
        );
    }

    private function resolveProvider(): CanManageAgentDefinitions {
        return $this->provider ?? new EmptySubagentProvider();
    }
}
