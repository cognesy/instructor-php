<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Messages\Message;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalSerializer;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalToolCall;
use Cognesy\Tell\Canonical\CanonicalToolResult;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Throwable;

/**
 * Converts one immutable legacy snapshot before publishing its compatibility ref.
 */
final readonly class LegacySessionMigrator
{
    public function __construct(
        private CanonicalSerializer $serializer = new CanonicalSerializer,
    ) {}

    /**
     * Returns the arena head that won publication. A competing first import is
     * safe because the generated records are deterministic for one source digest.
     */
    public function migrate(
        ArenaStore $arena,
        SessionCompatibilityRef $compatibility,
        LegacySessionSnapshot $snapshot,
    ): CanonicalHash {
        [$root, $turn] = $this->records($compatibility, $snapshot);
        try {
            // Verify the complete new lineage before writing any immutable object.
            $rootBytes = $this->serializer->encode($root);
            $rootHash = CanonicalHash::fromBytes($rootBytes);
            $turnBytes = $turn === null ? null : $this->serializer->encode($turn);
            $turnHash = $turnBytes === null ? null : CanonicalHash::fromBytes($turnBytes);
        } catch (Throwable $exception) {
            throw new WorkspaceSessionException(
                'Tell legacy session cannot be canonically migrated; it was left unchanged.',
                previous: $exception,
            );
        }

        try {
            if (! $arena->put($root)->equals($rootHash)) {
                throw new WorkspaceSessionException('Tell legacy session migration produced an inconsistent root.');
            }
            if ($turn !== null) {
                if ($turnHash === null || ! $arena->put($turn)->equals($turnHash)) {
                    throw new WorkspaceSessionException('Tell legacy session migration produced an inconsistent turn.');
                }
            }
            $published = $arena->compareAndSwap(
                $compatibility->refName(),
                null,
                $turnHash ?? $rootHash,
            );
        } catch (ArenaRefConflict) {
            $published = $arena->readRef($compatibility->refName());
        } catch (WorkspaceSessionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new WorkspaceSessionException(
                'Tell legacy session migration could not be published; both stores remain usable.',
                previous: $exception,
            );
        }

        if ($published->head === null) {
            throw new WorkspaceSessionException('Tell legacy session migration lost its canonical head.');
        }

        return $published->head;
    }

    /** @return array{0: CanonicalConversationRoot, 1: CanonicalTurn|null} */
    private function records(SessionCompatibilityRef $compatibility, LegacySessionSnapshot $snapshot): array
    {
        $suffix = substr($snapshot->fingerprint->toString(), 0, 32);
        $messages = $snapshot->session->state()->messages()->all();
        $canonicalMessages = [];
        $toolCalls = [];
        $toolResults = [];
        $callIds = [];
        $callNumber = 0;
        $firstAssistant = null;

        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                throw new WorkspaceSessionException('Tell legacy session has an unsupported message.');
            }
            if ($message->isTool()) {
                $this->toolResult($message, $callIds, $toolResults);

                continue;
            }

            $this->toolCalls($message, $suffix, $callIds, $toolCalls, $callNumber);
            if ($message->hasToolCalls() && $message->content()->isEmpty()) {
                // Arena turns persist tool traces separately and rehydrate them
                // immediately before the final assistant response.
                continue;
            }
            $canonical = $this->message($message);
            if ($canonical->role() === CanonicalRole::Assistant && $firstAssistant === null) {
                $firstAssistant = count($canonicalMessages);
            }
            $canonicalMessages[] = $canonical;
        }

        if (count($toolCalls) !== count($toolResults)) {
            throw new WorkspaceSessionException('Tell legacy session has an incomplete tool call trace.');
        }

        $rootMessages = $firstAssistant === null
            ? $canonicalMessages
            : array_slice($canonicalMessages, 0, $firstAssistant);
        $turnMessages = $firstAssistant === null
            ? []
            : array_slice($canonicalMessages, $firstAssistant);
        $root = new CanonicalConversationRoot(
            id: 'conversation-session-'.$suffix,
            messages: $rootMessages,
            session: $compatibility->metadata($snapshot->fingerprint),
        );
        $rootHash = $this->serializer->hash($root);
        if ($turnMessages === []) {
            if ($toolCalls !== []) {
                throw new WorkspaceSessionException('Tell legacy session has a tool trace without an assistant response.');
            }

            return [$root, null];
        }

        return [
            $root,
            new CanonicalTurn(
                id: 'turn-session-'.$suffix,
                lineage: new CanonicalLineage($rootHash),
                messages: $turnMessages,
                toolCalls: $toolCalls,
                toolResults: $toolResults,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $callIds
     * @param  list<CanonicalToolCall>  $calls
     */
    private function toolCalls(
        Message $message,
        string $suffix,
        array &$callIds,
        array &$calls,
        int &$callNumber,
    ): void {
        foreach ($message->toolCalls()->all() as $call) {
            $callNumber++;
            $canonicalId = 'legacy-'.$suffix.'-tool-'.$callNumber;
            $legacyId = $call->idString();
            if ($legacyId === '' || isset($callIds[$legacyId])) {
                throw new WorkspaceSessionException('Tell legacy session has an ambiguous tool call trace.');
            }
            $callIds[$legacyId] = $canonicalId;
            $calls[] = new CanonicalToolCall($canonicalId, $call->name(), $call->arguments());
        }
    }

    /**
     * @param  array<string, string>  $callIds
     * @param  list<CanonicalToolResult>  $results
     */
    private function toolResult(Message $message, array $callIds, array &$results): void
    {
        $result = $message->toolResult();
        if ($result === null || $result->callIdString() === '') {
            throw new WorkspaceSessionException('Tell legacy session has an unpaired tool result.');
        }
        $callId = $callIds[$result->callIdString()] ?? null;
        if ($callId === null) {
            throw new WorkspaceSessionException('Tell legacy session has an unpaired tool result.');
        }
        $results[] = new CanonicalToolResult(
            callId: $callId,
            parts: [new CanonicalTextPart($result->content())],
            isError: $result->isError(),
        );
    }

    private function message(Message $message): CanonicalMessage
    {
        try {
            $role = CanonicalRole::from($message->role()->value);
        } catch (\ValueError $exception) {
            throw new WorkspaceSessionException(
                'Tell legacy session has an unsupported message role.',
                previous: $exception,
            );
        }

        $parts = [];
        foreach ($message->contentParts()->all() as $part) {
            if (! $part->isTextPart() || ! $part->hasText()) {
                throw new WorkspaceSessionException('Tell legacy session has non-text content that cannot be migrated.');
            }
            $parts[] = new CanonicalTextPart($part->toString());
        }

        return new CanonicalMessage($role, $parts);
    }
}
