<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Execution;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Runtime\TellRunOutcome;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\History;
use Cognesy\Tell\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Lineage;
use Cognesy\Tell\Workspace\Arena\Record\SessionMetadata;
use Cognesy\Tell\Workspace\Arena\RecordCodec;
use Cognesy\Tell\Workspace\Arena\RecordException;
use Cognesy\Tell\Workspace\Arena\Ref;
use Cognesy\Tell\Workspace\Arena\TurnCapture;
use Generator;
use Throwable;

/**
 * Runs one workspace-backed Tell turn and atomically advances its arena head.
 */
final class TurnRunner
{
    public function __construct(
        private readonly FilesystemArena $arena,
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
                $this->publish($checkpoint, $history);
                $published = true;
                $outcome?->recordCommitted($checkpoint);
            }
            yield $checkpoint;
        }

        if (!$published) {
            // No checkpoint was publishable: report the same refusal as before.
            $this->assertPublishable($state);
            $this->publish($state, $history);
            $outcome?->recordCommitted($state);
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

    private function assertPublishable(AgentState $state): void {
        if ($state->status() !== ExecutionStatus::Completed) {
            throw new TurnException('Tell workspace turn was not completed; arena head was left unchanged.');
        }
        $lastStep = $state->lastStep();
        if (
            $lastStep === null
            || $lastStep->stepType() !== AgentStepType::FinalResponse
            || $lastStep->outputMessages()->isEmpty()
        ) {
            throw new TurnException('Tell workspace turn has no final response; arena head was left unchanged.');
        }
    }

    private function publish(AgentState $state, History $history): void {
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
            throw $exception;
        } catch (RecordException $exception) {
            throw new TurnException(
                'Tell workspace turn could not be canonically recorded; arena head was left unchanged.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new TurnException(
                'Tell workspace turn could not be prepared for publication; arena head was left unchanged.',
                previous: $exception,
            );
        }

        if ($root instanceof ConversationRoot) {
            $this->arena->put($root);
        }
        $turnHash = $this->arena->put($turn);
        $this->arena->compareAndSwap($this->ref, $history->referenceHead, $turnHash);
    }

    private function identifier(string $prefix): string {
        return $prefix . '-' . bin2hex(random_bytes(12));
    }
}
