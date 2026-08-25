<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalException;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalSerializer;
use Cognesy\Tell\Canonical\CanonicalSessionMetadata;
use Throwable;

/**
 * Runs one workspace-backed Tell turn and atomically advances its arena head.
 */
final class WorkspaceTurnRunner
{
    public function __construct(
        private readonly ArenaStore $arena,
        private readonly ArenaHistoryCompiler $historyCompiler = new ArenaHistoryCompiler,
        private readonly CanonicalTurnCapture $capture = new CanonicalTurnCapture,
        private readonly CanonicalSerializer $serializer = new CanonicalSerializer,
        private readonly string $ref = 'main',
        private readonly ?CanonicalSessionMetadata $session = null,
    ) {}

    public function execute(AgentLoop $loop, AgentDefinition $definition, string $prompt): AgentState
    {
        $reference = $this->arena->readOptionalRef($this->ref) ?? ArenaRef::empty();
        $history = $this->historyCompiler->compile($this->arena, $reference->head);
        $seed = (new DefinitionStateFactory)
            ->instantiateAgentState($definition)
            ->withMessages($history->messages)
            ->withUserMessage($prompt);
        $state = $loop->execute($seed);

        $this->assertPublishable($state);
        $this->publish($state, $history);

        return $state;
    }

    private function assertPublishable(AgentState $state): void
    {
        if ($state->status() !== ExecutionStatus::Completed) {
            throw new WorkspaceTurnException('Tell workspace turn was not completed; arena head was left unchanged.');
        }
        $lastStep = $state->lastStep();
        if (
            $lastStep === null
            || $lastStep->stepType() !== AgentStepType::FinalResponse
            || $lastStep->outputMessages()->isEmpty()
        ) {
            throw new WorkspaceTurnException('Tell workspace turn has no final response; arena head was left unchanged.');
        }
    }

    private function publish(AgentState $state, ArenaHistory $history): void
    {
        $root = null;
        $rootHash = $history->root;
        try {
            if ($rootHash === null) {
                $root = new CanonicalConversationRoot($this->identifier('conversation'), session: $this->session);
                $rootHash = $this->serializer->hash($root);
            }
            $turn = $this->capture->capture(
                state: $state,
                historyMessageCount: $history->messages->count(),
                turnId: $this->identifier('turn'),
                lineage: new CanonicalLineage($rootHash, $history->turnHead),
            );
            // Validate complete canonical bytes before writing any new object.
            $this->serializer->encode($turn);
        } catch (WorkspaceTurnException $exception) {
            throw $exception;
        } catch (CanonicalException $exception) {
            throw new WorkspaceTurnException(
                'Tell workspace turn could not be canonically recorded; arena head was left unchanged.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new WorkspaceTurnException(
                'Tell workspace turn could not be prepared for publication; arena head was left unchanged.',
                previous: $exception,
            );
        }

        if ($root instanceof CanonicalConversationRoot) {
            $this->arena->put($root);
        }
        $turnHash = $this->arena->put($turn);
        $this->arena->compareAndSwap($this->ref, $history->referenceHead, $turnHash);
    }

    private function identifier(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(12));
    }
}
