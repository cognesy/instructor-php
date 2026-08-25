<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Messages\Message;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalException;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalSerializer;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Throwable;

/**
 * Runs one explicit summarization request then atomically replaces a canonical head.
 *
 * The summary prompt is inference-only. The resulting immutable turn contains only
 * the semantic assistant summary and its compacted-from provenance.
 */
final class WorkspaceCompactionRunner
{
    public function __construct(
        private readonly ArenaStore $arena,
        private readonly string $ref = 'main',
        private readonly ArenaHistoryCompiler $historyCompiler = new ArenaHistoryCompiler,
        private readonly CanonicalSerializer $serializer = new CanonicalSerializer,
    ) {}

    public function execute(AgentLoop $loop, AgentDefinition $definition, string $hint = ''): WorkspaceCompactionResult
    {
        $reference = $this->arena->readOptionalRef($this->ref) ?? ArenaRef::empty();
        $history = $this->historyCompiler->compile($this->arena, $reference->head);
        $sourceHead = $history->referenceHead;
        $root = $history->root;
        if ($sourceHead === null || $history->turnHead === null || $root === null) {
            throw new WorkspaceCompactionException(
                'Tell compact requires at least one completed canonical turn; arena head was left unchanged.',
            );
        }

        try {
            $seed = (new DefinitionStateFactory)
                ->instantiateAgentState($definition)
                ->withMessages($history->messages)
                ->withUserMessage($this->prompt($hint));
            $state = $loop->execute($seed);
            $summary = $this->summary($state);
            $turn = new CanonicalTurn(
                id: $this->identifier('compact'),
                lineage: new CanonicalLineage($root, compactedFrom: [$sourceHead]),
                messages: [new CanonicalMessage(CanonicalRole::Assistant, [new CanonicalTextPart($summary)])],
            );
            // Validate all canonical bytes before writing objects or advancing the ref.
            $this->serializer->encode($turn);
        } catch (WorkspaceCompactionException $exception) {
            throw $exception;
        } catch (CanonicalException $exception) {
            throw new WorkspaceCompactionException(
                'Tell compact could not canonically record its summary; arena head was left unchanged.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new WorkspaceCompactionException(
                'Tell compact did not complete; arena head was left unchanged.',
                previous: $exception,
            );
        }

        $rootRecord = $this->arena->get($root);
        if (! $rootRecord instanceof CanonicalConversationRoot) {
            throw new WorkspaceCompactionException('Tell compact found an invalid canonical conversation root.');
        }

        $head = $this->arena->put($turn);
        $this->arena->compareAndSwap($this->ref, $sourceHead, $head);

        return new WorkspaceCompactionResult(
            sourceHead: $sourceHead,
            head: $head,
            beforeMessageCount: $history->messages->count(),
            beforeTurnCount: count($history->turns),
            afterMessageCount: count($rootRecord->messages()) + 1,
            afterTurnCount: 1,
        );
    }

    private function summary(AgentState $state): string
    {
        if ($state->status() !== ExecutionStatus::Completed) {
            throw new WorkspaceCompactionException('Tell compact did not complete; arena head was left unchanged.');
        }
        $lastStep = $state->lastStep();
        if (
            $lastStep === null
            || $lastStep->stepType() !== AgentStepType::FinalResponse
            || $lastStep->outputMessages()->isEmpty()
        ) {
            throw new WorkspaceCompactionException(
                'Tell compact has no final summary; arena head was left unchanged.',
            );
        }

        $parts = [];
        foreach ($lastStep->outputMessages()->all() as $message) {
            $parts[] = $this->text($message);
        }
        $summary = trim(implode("\n", $parts));
        if ($summary === '') {
            throw new WorkspaceCompactionException('Tell compact returned an empty summary; arena head was left unchanged.');
        }

        return $summary;
    }

    private function text(Message $message): string
    {
        if ($message->role()->value !== CanonicalRole::Assistant->value) {
            throw new WorkspaceCompactionException('Tell compact only accepts an assistant text summary; arena head was left unchanged.');
        }

        $parts = [];
        foreach ($message->contentParts()->all() as $part) {
            if (! $part->isTextPart() || ! $part->hasText()) {
                throw new WorkspaceCompactionException('Tell compact only accepts text summary content; arena head was left unchanged.');
            }
            $parts[] = $part->toString();
        }

        return implode("\n", $parts);
    }

    private function prompt(string $hint): string
    {
        $prompt = <<<'PROMPT'
Summarize the preceding canonical conversation for the next Tell turn. Preserve
decisions, established facts, constraints, relevant tool results, open work,
and concrete next steps. Return only a concise continuation summary.
PROMPT;

        return match ($hint) {
            '' => $prompt,
            default => $prompt."\n\nAdditional focus: {$hint}",
        };
    }

    private function identifier(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(12));
    }
}
