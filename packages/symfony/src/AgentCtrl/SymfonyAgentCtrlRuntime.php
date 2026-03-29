<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\AgentCtrl;

use Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Builder\GeminiBridgeBuilder;
use Cognesy\AgentCtrl\Builder\OpenCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\PiBridgeBuilder;
use Cognesy\AgentCtrl\Contract\AgentBridgeBuilder;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;

/**
 * Context-aware AgentCtrl runtime adapter for Symfony entrypoints.
 */
final readonly class SymfonyAgentCtrlRuntime
{
    public function __construct(
        private SymfonyAgentCtrl $agentCtrl,
        private AgentCtrlExecutionPolicy $policy,
        private AgentCtrlContinuationPolicy $continuationPolicy,
    ) {}

    public function context(): AgentCtrlExecutionContext
    {
        return $this->policy->context;
    }

    public function policy(): AgentCtrlExecutionPolicy
    {
        return $this->policy;
    }

    public function continuationPolicy(): AgentCtrlContinuationPolicy
    {
        return $this->continuationPolicy;
    }

    public function continuation(AgentType|string|null $type = null): AgentCtrlContinuationReference
    {
        $backend = $this->resolveBackend($type);

        return match ($this->continuationPolicy->mode) {
            AgentCtrlContinuationMode::Fresh => AgentCtrlContinuationReference::fresh(
                $backend,
                $this->continuationPolicy->sessionKey,
            ),
            AgentCtrlContinuationMode::ContinueLast => AgentCtrlContinuationReference::continueLast(
                $backend,
                $this->continuationPolicy->sessionKey,
                $this->context(),
            ),
            AgentCtrlContinuationMode::ResumeSession => throw new \RuntimeException(
                'AgentCtrl continuation mode resume_session requires an explicit session reference. Use handoff() or resumeSession().',
            ),
        };
    }

    public function defaultBuilder(): AgentBridgeBuilder
    {
        $this->policy->assertContextAllowed();

        return $this->agentCtrl->defaultBuilder();
    }

    public function make(AgentType|string $type): AgentBridgeBuilder
    {
        $this->policy->assertContextAllowed();

        return $this->agentCtrl->make($type);
    }

    public function claudeCode(): ClaudeCodeBridgeBuilder
    {
        /** @var ClaudeCodeBridgeBuilder $builder */
        $builder = $this->make('claude_code');

        return $builder;
    }

    public function codex(): CodexBridgeBuilder
    {
        /** @var CodexBridgeBuilder $builder */
        $builder = $this->make('codex');

        return $builder;
    }

    public function openCode(): OpenCodeBridgeBuilder
    {
        /** @var OpenCodeBridgeBuilder $builder */
        $builder = $this->make('opencode');

        return $builder;
    }

    public function pi(): PiBridgeBuilder
    {
        /** @var PiBridgeBuilder $builder */
        $builder = $this->make('pi');

        return $builder;
    }

    public function gemini(): GeminiBridgeBuilder
    {
        /** @var GeminiBridgeBuilder $builder */
        $builder = $this->make('gemini');

        return $builder;
    }

    public function continueLast(AgentType|string|null $type = null): AgentBridgeBuilder
    {
        $this->policy->assertContextAllowed();

        return $this->agentCtrl->continueLast($this->resolveBackend($type));
    }

    public function resumeSession(
        AgentCtrlContinuationReference|string $reference,
        AgentType|string|null $type = null,
    ): AgentBridgeBuilder {
        $this->policy->assertContextAllowed();

        $continuation = $reference instanceof AgentCtrlContinuationReference
            ? $reference
            : AgentCtrlContinuationReference::resumeSession(
                backend: $this->resolveBackend($type),
                sessionId: $reference,
                sessionKey: $this->continuationPolicy->sessionKey,
            );

        $this->assertCrossContextResumeAllowed($continuation);

        return match ($continuation->mode) {
            AgentCtrlContinuationMode::Fresh => $this->make($continuation->backend),
            AgentCtrlContinuationMode::ContinueLast => $this->resumeContinuationLast($continuation),
            AgentCtrlContinuationMode::ResumeSession => $this->resumeContinuationSession($continuation),
        };
    }

    public function handoff(
        AgentResponse $response,
        AgentType|string|null $type = null,
    ): ?AgentCtrlContinuationReference {
        if (! $this->continuationPolicy->createsHandoff()) {
            return null;
        }

        $sessionId = $response->sessionId()?->toString();

        if ($sessionId === null) {
            return null;
        }

        return AgentCtrlContinuationReference::resumeSession(
            backend: $this->resolveBackend($type ?? $response->agentType),
            sessionId: $sessionId,
            sessionKey: $this->continuationPolicy->sessionKey,
            sourceContext: $this->context(),
        );
    }

    public function execute(string|\Stringable $prompt, AgentType|string|null $type = null): AgentResponse
    {
        $this->policy->assertInlineExecutionAllowed();

        return $this->resolveBuilder($type)->execute($prompt);
    }

    public function executeStreaming(string|\Stringable $prompt, AgentType|string|null $type = null): AgentResponse
    {
        $this->policy->assertInlineExecutionAllowed();

        return $this->resolveBuilder($type)->executeStreaming($prompt);
    }

    private function resolveBuilder(AgentType|string|null $type): AgentBridgeBuilder
    {
        return match ($type) {
            null => $this->defaultBuilder(),
            default => $this->make($type),
        };
    }

    private function resolveBackend(AgentType|string|null $type): string
    {
        return match ($type) {
            null => $this->agentCtrl->defaultBackendName(),
            default => $this->agentCtrl->backend($type),
        };
    }

    private function resumeContinuationLast(AgentCtrlContinuationReference $continuation): AgentBridgeBuilder
    {
        $this->assertContinueLastContextAllowed($continuation);

        return $this->agentCtrl->continueLast($continuation->backend);
    }

    private function resumeContinuationSession(AgentCtrlContinuationReference $continuation): AgentBridgeBuilder
    {
        return $this->agentCtrl->resumeSession(
            $continuation->backend,
            $continuation->sessionId ?? throw new \RuntimeException('Missing AgentCtrl continuation session ID.'),
        );
    }

    private function assertCrossContextResumeAllowed(AgentCtrlContinuationReference $continuation): void
    {
        if ($continuation->sourceContext === null || $continuation->sourceContext === $this->context()) {
            return;
        }

        if ($this->continuationPolicy->allowCrossContextResume) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'AgentCtrl continuation from %s cannot be resumed in %s because instructor.agent_ctrl.continuation.allow_cross_context_resume is false.',
            $continuation->sourceContext->value,
            $this->context()->value,
        ));
    }

    private function assertContinueLastContextAllowed(AgentCtrlContinuationReference $continuation): void
    {
        if ($continuation->sourceContext === null || $continuation->sourceContext === $this->context()) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'AgentCtrl continue_last continuation from %s cannot be resumed in %s. Use handoff() or a resume_session continuation instead.',
            $continuation->sourceContext->value,
            $this->context()->value,
        ));
    }
}
