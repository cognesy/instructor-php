<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Execution;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Core\Execution\TellRunOutcome;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellWorkspaceArena;
use Cognesy\Tell\Core\Workspace\Arena\History;
use Cognesy\Tell\Core\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Core\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Core\Workspace\Arena\Record\Lineage;
use Cognesy\Tell\Core\Workspace\Arena\Record\SessionMetadata;
use Cognesy\Tell\Core\Workspace\Arena\RecordCodec;
use Cognesy\Tell\Core\Workspace\Arena\RecordException;
use Cognesy\Tell\Core\Workspace\Arena\Ref;
use Cognesy\Tell\Core\Workspace\Arena\TurnCapture;
use Cognesy\Tell\Core\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Data\TellPublication;
use Cognesy\Tell\Data\TellTermination;
use Generator;
use Throwable;

/**
 * Runs one workspace-backed Tell turn and atomically advances its arena head.
 */
final class TurnRunner
{
    public function __construct(
        private readonly CanUseTellWorkspaceArena $arena,
        private readonly HistoryCompiler $historyCompiler = new HistoryCompiler(),
        private readonly TurnCapture $capture = new TurnCapture(),
        private readonly RecordCodec $serializer = new RecordCodec(),
        private readonly string $ref = 'main',
        private readonly ?SessionMetadata $session = null,
    ) {}

    public function execute(AgentLoop $loop, AgentDefinition $definition, string $prompt): AgentState {
        $states = $this->iterate($loop, $definition, $prompt);
        foreach ($states as $_) {
        }

        return $states->getReturn();
    }

    /**
     * Publishes the terminal state *before* yielding it, so that observing the
     * final checkpoint implies the turn is durable. Committing after the loop
     * would leave the arena head hostage to one further advance the caller has
     * no reason to make.
     *
     * @return Generator<int, AgentState, mixed, AgentState>
     */
    public function iterate(
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
        ?TellRunOutcome $outcome = null,
    ): Generator {
        $reference = $this->arena->readOptionalRef($this->ref) ?? Ref::empty();
        $history = $this->historyCompiler->compile($this->arena, $reference->head);
        $seed = (new DefinitionStateFactory())
            ->instantiateAgentState($definition)
            ->withMessages($history->messages)
            ->withUserMessage($prompt);
        $state = $seed;
        $published = false;
        foreach ($loop->iterate($seed) as $checkpoint) {
            $state = $checkpoint;
            if (!$published && $this->isPublishable($checkpoint)) {
                $publication = $this->publish($checkpoint, $history, $outcome);
                $published = true;
                $outcome?->recordPublished($checkpoint, $publication);
            } elseif ($this->isTerminalWithoutPublication($checkpoint)) {
                $outcome?->recordSettled($checkpoint);
            }
            yield $checkpoint;
        }

        if (!$published) {
            if ($this->isTerminalWithoutPublication($state)) {
                $outcome?->recordSettled($state);

                return $state;
            }
            $this->assertValidCompletedState($state, $outcome?->publication());
        }

        return $state;
    }

    /**
     * Only a completed turn carrying a non-empty final response may advance the
     * arena head. Intermediate tool-calling checkpoints never qualify, so the
     * first checkpoint that does is also the last one the loop yields.
     */
    private function isPublishable(AgentState $state): bool {
        if ($state->status() !== ExecutionStatus::Completed) {
            return false;
        }
        $lastStep = $state->lastStep();

        return $lastStep !== null
            && $lastStep->stepType() === AgentStepType::FinalResponse
            && !$lastStep->outputMessages()->isEmpty();
    }

    private function isTerminalWithoutPublication(AgentState $state): bool {
        return in_array($state->status(), [ExecutionStatus::Stopped, ExecutionStatus::Failed], true);
    }

    private function assertValidCompletedState(AgentState $state, ?TellPublication $publication): void {
        $termination = TellTermination::fromState($state);
        if ($state->status() !== ExecutionStatus::Completed) {
            throw new TurnException(
                'Tell workspace turn ended in an invalid non-terminal state; arena head was left unchanged.',
                termination: $termination,
                publication: $publication,
            );
        }
        $lastStep = $state->lastStep();
        if (
            $lastStep === null
            || $lastStep->stepType() !== AgentStepType::FinalResponse
            || $lastStep->outputMessages()->isEmpty()
        ) {
            throw new TurnException(
                'Tell workspace turn has no final response; arena head was left unchanged.',
                termination: $termination,
                publication: $publication,
            );
        }
    }

    private function publish(
        AgentState $state,
        History $history,
        ?TellRunOutcome $outcome,
    ): TellPublication {
        $publication = $outcome?->publication() ?? TellPublication::notAttempted(branch: $this->ref);
        $baseHead = $history->referenceHead?->toString();
        $root = null;
        $rootHash = $history->root;
        try {
            if ($rootHash === null) {
                $root = new ConversationRoot($this->identifier('conversation'), session: $this->session);
                $rootHash = $this->serializer->hash($root);
            }
            $turn = $this->capture->capture(
                state: $state,
                historyMessageCount: $history->messages->count(),
                turnId: $this->identifier('turn'),
                lineage: new Lineage($rootHash, $history->turnHead),
            );
            // Validate complete canonical bytes before writing any new object.
            $this->serializer->encode($turn);
        } catch (TurnException $exception) {
            throw $this->publicationFailure(
                $state,
                $publication,
                $outcome,
                'turn_record_invalid',
                $exception->getMessage(),
                $exception,
                $baseHead,
            );
        } catch (RecordException $exception) {
            throw $this->publicationFailure(
                $state,
                $publication,
                $outcome,
                'turn_record_invalid',
                'Tell workspace turn could not be canonically recorded; arena head was left unchanged.',
                $exception,
                $baseHead,
            );
        } catch (Throwable $exception) {
            throw $this->publicationFailure(
                $state,
                $publication,
                $outcome,
                'turn_publication_failed',
                'Tell workspace turn could not be prepared for publication; arena head was left unchanged.',
                $exception,
                $baseHead,
            );
        }

        try {
            if ($root instanceof ConversationRoot) {
                $this->arena->put($root);
            }
            $turnHash = $this->arena->put($turn);
            $this->arena->compareAndSwap($this->ref, $history->referenceHead, $turnHash);
        } catch (RefConflict $exception) {
            throw $this->publicationFailure(
                $state,
                $publication,
                $outcome,
                'turn_publication_conflict',
                'Tell workspace changed before the turn could be published; arena head was left unchanged.',
                $exception,
                $baseHead,
            );
        } catch (Throwable $exception) {
            throw $this->publicationFailure(
                $state,
                $publication,
                $outcome,
                'turn_publication_failed',
                'Tell workspace turn could not be published; arena head was left unchanged.',
                $exception,
                $baseHead,
            );
        }

        return $publication->published($baseHead, $turnHash->toString());
    }

    private function publicationFailure(
        AgentState $state,
        TellPublication $publication,
        ?TellRunOutcome $outcome,
        string $code,
        string $message,
        Throwable $previous,
        ?string $baseHead,
    ): TurnException {
        $failed = $publication->failed($code, $baseHead);
        $outcome?->recordPublicationFailed($state, $failed);

        return new TurnException(
            $message,
            failureCode: $code,
            termination: TellTermination::fromState($state),
            publication: $failed,
            previous: $previous,
        );
    }

    private function identifier(string $prefix): string {
        return $prefix . '-' . bin2hex(random_bytes(12));
    }
}
