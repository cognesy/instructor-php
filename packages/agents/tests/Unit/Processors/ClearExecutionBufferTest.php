<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Processors;

use Cognesy\Agents\Agent\StateProcessing\Processors\ClearExecutionBuffer;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Messages\Messages;

describe('ClearExecutionBuffer', function () {
    it('keeps buffer when continuation should continue', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('trace', 'tool'));
        $state = $state->withMessageStore($store);

        $outcome = ContinuationOutcome::fromEvaluations([
            ContinuationEvaluation::fromDecision('Test', ContinuationDecision::AllowContinuation),
        ]);
        $step = new AgentStep();
        $now = new \DateTimeImmutable();
        $execution = new StepExecution(
            step: $step,
            outcome: $outcome,
            startedAt: $now,
            completedAt: $now,
            stepNumber: 1,
        );

        $processor = new ClearExecutionBuffer();
        $result = $processor->process($state, fn(AgentState $state): AgentState => $state->recordStepExecution($execution));

        expect($result->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeFalse();
    });

    it('clears buffer when continuation stops', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('trace', 'tool'));
        $state = $state->withMessageStore($store);

        $outcome = ContinuationOutcome::fromEvaluations([
            ContinuationEvaluation::fromDecision('Test', ContinuationDecision::ForbidContinuation, StopReason::Completed),
        ]);
        $step = new AgentStep();
        $now = new \DateTimeImmutable();
        $execution = new StepExecution(
            step: $step,
            outcome: $outcome,
            startedAt: $now,
            completedAt: $now,
            stepNumber: 1,
        );

        $processor = new ClearExecutionBuffer();
        $result = $processor->process($state, fn(AgentState $state): AgentState => $state->recordStepExecution($execution));

        expect($result->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });

    it('returns state unchanged when continuationOutcome is null', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('trace', 'tool'));
        $state = $state->withMessageStore($store);

        $processor = new ClearExecutionBuffer();
        $result = $processor->process($state);

        expect($result->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeFalse();
    });
});

describe('AgentState execution buffer clearing', function () {
    it('clears execution buffer when withUserMessage resets state', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('tool trace', 'tool'));
        $state = $state->withMessageStore($store);

        // Verify buffer is populated
        expect($state->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeFalse();

        // Add user message with reset (default behavior)
        $newState = $state->withUserMessage('Hello', resetExecutionState: true);

        // Buffer should be cleared
        expect($newState->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
        // User message should be added
        expect($newState->messages()->count())->toBe(1);
        expect($newState->messages()->last()->toString())->toBe('Hello');
    });

    it('preserves execution buffer when withUserMessage does not reset state', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('tool trace', 'tool'));
        $state = $state->withMessageStore($store);

        // Add user message without reset
        $newState = $state->withUserMessage('Hello', resetExecutionState: false);

        // Buffer should be preserved
        expect($newState->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeFalse();
    });

    it('clears execution buffer via forContinuation', function () {
        $state = AgentState::empty();
        $store = $state->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->setMessages(Messages::fromString('tool trace', 'tool'));
        $state = $state->withMessageStore($store);

        // Verify buffer is populated
        expect($state->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeFalse();

        // Reset for continuation
        $newState = $state->forContinuation();

        // Buffer should be cleared
        expect($newState->store()->section(AgentState::EXECUTION_BUFFER_SECTION)->isEmpty())->toBeTrue();
    });
});
