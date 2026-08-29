<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Compaction;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Messages\Message;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Lineage;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Role;
use Cognesy\Tell\Workspace\Arena\Record\TextPart;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use Cognesy\Tell\Workspace\Arena\RecordCodec;
use Cognesy\Tell\Workspace\Arena\RecordException;
use Cognesy\Tell\Workspace\Arena\Ref;
use Throwable;

/**
 * Runs one explicit summarization request then atomically replaces a canonical head.
 *
 * The summary prompt is inference-only. The resulting immutable turn contains only
 * the semantic assistant summary and its compacted-from provenance.
 */
final class CompactionRunner
{
    public function __construct(
        private readonly FilesystemArena $arena,
        private readonly string $ref = 'main',
        private readonly HistoryCompiler $historyCompiler = new HistoryCompiler(),
        private readonly RecordCodec $serializer = new RecordCodec(),
    ) {}

    public function execute(AgentLoop $loop, AgentDefinition $definition, string $hint = ''): CompactionResult {
        $reference = $this->arena->readOptionalRef($this->ref) ?? Ref::empty();
        $history = $this->historyCompiler->compile($this->arena, $reference->head);
        $sourceHead = $history->referenceHead;
        $root = $history->root;
        if ($sourceHead === null || $history->turnHead === null || $root === null) {
            throw new CompactionException(
                'Tell compact requires at least one completed canonical turn; arena head was left unchanged.',
            );
        }

        try {
            $seed = (new DefinitionStateFactory())
                ->instantiateAgentState($definition)
                ->withMessages($history->messages)
                ->withUserMessage($this->prompt($hint));
            $state = $loop->execute($seed);
            $summary = $this->summary($state);
            $turn = new Turn(
                id: $this->identifier('compact'),
                lineage: new Lineage($root, compactedFrom: [$sourceHead]),
                messages: [new RecordMessage(Role::Assistant, [new TextPart($summary)])],
            );
            // Validate all canonical bytes before writing objects or advancing the ref.
            $this->serializer->encode($turn);
        } catch (CompactionException $exception) {
            throw $exception;
        } catch (RecordException $exception) {
            throw new CompactionException(
                'Tell compact could not canonically record its summary; arena head was left unchanged.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new CompactionException(
                'Tell compact did not complete; arena head was left unchanged.',
                previous: $exception,
            );
        }

        $rootRecord = $this->arena->get($root);
        if (!$rootRecord instanceof ConversationRoot) {
            throw new CompactionException('Tell compact found an invalid canonical conversation root.');
        }

        $head = $this->arena->put($turn);
        $this->arena->compareAndSwap($this->ref, $sourceHead, $head);

        return new CompactionResult(
            sourceHead: $sourceHead,
            head: $head,
            beforeMessageCount: $history->messages->count(),
            beforeTurnCount: count($history->turns),
            afterMessageCount: count($rootRecord->messages()) + 1,
            afterTurnCount: 1,
        );
    }

    private function summary(AgentState $state): string {
        if ($state->status() !== ExecutionStatus::Completed) {
            throw new CompactionException('Tell compact did not complete; arena head was left unchanged.');
        }
        $lastStep = $state->lastStep();
        if (
            $lastStep === null
            || $lastStep->stepType() !== AgentStepType::FinalResponse
            || $lastStep->outputMessages()->isEmpty()
        ) {
            throw new CompactionException(
                'Tell compact has no final summary; arena head was left unchanged.',
            );
        }

        $parts = [];
        foreach ($lastStep->outputMessages()->all() as $message) {
            $parts[] = $this->text($message);
        }
        $summary = trim(implode("\n", $parts));
        if ($summary === '') {
            throw new CompactionException('Tell compact returned an empty summary; arena head was left unchanged.');
        }

        return $summary;
    }

    private function text(Message $message): string {
        if ($message->role()->value !== Role::Assistant->value) {
            throw new CompactionException('Tell compact only accepts an assistant text summary; arena head was left unchanged.');
        }

        $parts = [];
        foreach ($message->contentParts()->all() as $part) {
            if (!$part->isTextPart() || !$part->hasText()) {
                throw new CompactionException('Tell compact only accepts text summary content; arena head was left unchanged.');
            }
            $parts[] = $part->toString();
        }

        return implode("\n", $parts);
    }

    private function prompt(string $hint): string {
        $prompt = <<<'PROMPT'
Summarize the preceding canonical conversation for the next Tell turn. Preserve
decisions, established facts, constraints, relevant tool results, open work,
and concrete next steps. Return only a concise continuation summary.
PROMPT;

        return match ($hint) {
            '' => $prompt,
            default => $prompt . "\n\nAdditional focus: {$hint}",
        };
    }

    private function identifier(string $prefix): string {
        return $prefix . '-' . bin2hex(random_bytes(12));
    }
}
