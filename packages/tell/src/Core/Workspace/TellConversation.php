<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Data\TellClearResult;
use Cognesy\Tell\Data\TellCompactionResult;
use Cognesy\Tell\Data\TellContext;
use Cognesy\Tell\Data\TellConversationView;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellConversation;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellWorkspaceArena;
use Cognesy\Tell\Core\Workspace\Compaction\CompactionRunner;
use Cognesy\Tell\Core\Workspace\Conversation\ContextInspector;
use Cognesy\Tell\Core\Workspace\Conversation\ConversationInspection;
use Cognesy\Tell\Core\Workspace\Conversation\ConversationReader;
use Cognesy\Tell\Core\Workspace\Session\SessionRef;
use Generator;
use InvalidArgumentException;

/** A selected main or named durable Tell conversation. */
final readonly class TellConversation implements CanUseTellConversation
{
    private const int MAX_HINT_CHARACTERS = 500;

    public function __construct(
        private CanRunTell $runner,
        private CanBuildTellAgent $agents,
        private CanTraceTellExecution $tracer,
        private CanOpenTellWorkspace $workspaces,
        private string $directory,
        private ?string $name = null,
    ) {}

    #[\Override]
    public function send(TellRequest $request): TellResult {
        return $this->runner->run($this->inDirectory($request)->conversation($this->name)->durable());
    }

    /** @return Generator<int, TellProgress, mixed, TellResult> */
    #[\Override]
    public function sendStream(TellRequest $request): Generator {
        return $this->runner->stream($this->inDirectory($request)->conversation($this->name)->durable());
    }

    #[\Override]
    public function history(int $limit = 20, bool $full = false): TellConversationView {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Tell history limit must be between 1 and 100.');
        }

        $inspection = $this->inspection();
        $turns = $inspection->historyRows($full);

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            turns: array_slice($turns, -$limit),
            totalCount: count($turns),
            truncated: count($turns) > $limit,
        );
    }

    #[\Override]
    public function transcript(bool $full = false): TellConversationView {
        $inspection = $this->inspection();

        $history = $inspection->history();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $history->root?->toString(),
            messages: $inspection->transcriptRows($full),
            toolCallCount: array_sum(array_map(
                static fn ($entry): int => count($entry->turn->toolCalls()),
                $history->turns,
            )),
            toolResultCount: array_sum(array_map(
                static fn ($entry): int => count($entry->turn->toolResults()),
                $history->turns,
            )),
        );
    }

    #[\Override]
    public function context(?TellRequest $request = null): TellContext {
        $request ??= TellRequest::prompt('Inspect Tell context.');
        $request = $this->inDirectory($request);
        $definition = $this->agents->definition($request);

        return new TellContext((new ContextInspector())->inspect(
            conversation: $this->inspection(),
            definition: $definition,
            connection: $request->connection,
        ));
    }

    #[\Override]
    public function clear(): TellClearResult {
        $inspection = $this->inspection();
        $previousHead = $inspection->head();
        $result = $this->workspace()->arena->compareAndSwapToEmpty(
            $this->refName(),
            $previousHead,
        );

        return new TellClearResult(
            selector: $inspection->selector(),
            previousHead: $previousHead?->toString(),
            head: $result->head?->toString(),
        );
    }

    #[\Override]
    public function compact(TellRequest $request, string $hint = ''): TellCompactionResult {
        if (mb_strlen($hint) > self::MAX_HINT_CHARACTERS) {
            throw new InvalidArgumentException('Tell compact hint must be at most ' . self::MAX_HINT_CHARACTERS . ' characters.');
        }
        $request = $this->inDirectory($request);
        $definition = $this->agents->definition($request);
        $this->agents->assertReady($request);
        $loop = $this->agents->build($request, definition: $definition);
        $this->tracer->attach($loop, $request);
        $result = (new CompactionRunner(
            arena: $this->workspace()->arena,
            ref: $this->refName(),
        ))->execute($loop, $definition, $hint);

        return new TellCompactionResult($this->inspection()->selector(), $result->toArray());
    }

    private function inspection(): ConversationInspection {
        return (new ConversationReader($this->workspace()->arena))->read($this->sessionId());
    }

    private function workspace(): TellWorkspaceContext {
        return $this->workspaces->open($this->directory);
    }

    private function sessionId(): ?SessionId {
        $session = match ($this->name) {
            null => null,
            default => SessionId::from($this->name),
        };
        if ($session !== null) {
            (new SessionRef($session))->metadata();
        }

        return $session;
    }

    private function refName(): string {
        $session = $this->sessionId();

        return match ($session) {
            null => 'main',
            default => (new SessionRef($session))->refName(),
        };
    }

    private function inDirectory(TellRequest $request): TellRequest {
        return match ($request->directory) {
            '' => $request->withDirectory($this->directory),
            default => $request,
        };
    }
}
