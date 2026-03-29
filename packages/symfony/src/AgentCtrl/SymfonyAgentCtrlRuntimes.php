<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

use Cognesy\Config\Contracts\CanProvideConfig;

/**
 * Factory for context-specific Symfony AgentCtrl runtime adapters.
 */
final readonly class SymfonyAgentCtrlRuntimes
{
    public function __construct(
        private SymfonyAgentCtrl $agentCtrl,
        private CanProvideConfig $configProvider,
    ) {}

    public function cli(): SymfonyAgentCtrlRuntime
    {
        return $this->runtime(AgentCtrlExecutionContext::Cli);
    }

    public function http(): SymfonyAgentCtrlRuntime
    {
        return $this->runtime(AgentCtrlExecutionContext::Http);
    }

    public function messenger(): SymfonyAgentCtrlRuntime
    {
        return $this->runtime(AgentCtrlExecutionContext::Messenger);
    }

    private function runtime(AgentCtrlExecutionContext $context): SymfonyAgentCtrlRuntime
    {
        return new SymfonyAgentCtrlRuntime(
            agentCtrl: $this->agentCtrl,
            policy: AgentCtrlExecutionPolicy::fromArray($context, $this->executionConfig()),
            continuationPolicy: AgentCtrlContinuationPolicy::fromArray($this->continuationConfig()),
        );
    }

    /** @return array<string, mixed> */
    private function executionConfig(): array
    {
        $value = $this->configProvider->get('instructor.agent_ctrl.execution', []);

        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed> */
    private function continuationConfig(): array
    {
        $value = $this->configProvider->get('instructor.agent_ctrl.continuation', []);

        return is_array($value) ? $value : [];
    }
}
