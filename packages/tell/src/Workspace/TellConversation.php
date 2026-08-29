<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Data\TellClearResult;
use Cognesy\Tell\Data\TellCompactionResult;
use Cognesy\Tell\Data\TellContext;
use Cognesy\Tell\Data\TellConversationView;
use Cognesy\Tell\Data\TellProgress;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellResult;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Compaction\CompactionRunner;
use Cognesy\Tell\Workspace\Conversation\ContextInspector;
use Cognesy\Tell\Workspace\Conversation\ConversationInspection;
use Cognesy\Tell\Workspace\Conversation\ConversationReader;
use Cognesy\Tell\Workspace\Session\SessionRef;
use Generator;
use InvalidArgumentException;

/** A selected main or named durable Tell conversation. */
final readonly class TellConversation
{
    private const int MAX_HINT_CHARACTERS = 500;

    public function __construct(
        private Tell $tell,
        private TellAgentFactory $agents,
        private string $directory,
        private ?string $name = null,
    ) {}

    public function send(TellRequest $request): TellResult {
        return $this->tell->run($request->conversation($this->name)->durable());
    }

    /** @return Generator<int, TellProgress, mixed, TellResult> */
    public function sendStream(TellRequest $request): Generator {
        return $this->tell->runStream($request->conversation($this->name)->durable());
    }

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
        );
    }

    public function transcript(bool $full = false): TellConversationView {
        $inspection = $this->inspection();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            messages: $inspection->transcriptRows($full),
        );
    }

    public function context(?TellRequest $request = null): TellContext {
        $request ??= TellRequest::prompt('Inspect Tell context.');
        $request = $this->inDirectory($request);
        $definition = $this->agents->definition($request->toOptions());

        return new TellContext((new ContextInspector())->inspect(
            conversation: $this->inspection(),
            definition: $definition,
            connection: $request->connection,
        ));
    }

    public function clear(): TellClearResult {
        $inspection = $this->inspection();
        $previousHead = $inspection->head();
        $result = (new FilesystemArena($this->workspace()))->compareAndSwapToEmpty(
            $this->refName(),
            $previousHead,
        );

        return new TellClearResult(
            selector: $inspection->selector(),
            previousHead: $previousHead?->toString(),
            head: $result->head?->toString(),
        );
    }

    public function compact(TellRequest $request, string $hint = ''): TellCompactionResult {
        if (mb_strlen($hint) > self::MAX_HINT_CHARACTERS) {
            throw new InvalidArgumentException('Tell compact hint must be at most ' . self::MAX_HINT_CHARACTERS . ' characters.');
        }
        $request = $this->inDirectory($request);
        $options = $request->toOptions();
        $definition = $this->agents->definition($options);
        $this->agents->assertReady($options);
        $loop = $this->agents->build($options, $definition);
        $this->agents->attachExecutionTrace($loop, $options);
        $result = (new CompactionRunner(
            arena: new FilesystemArena($this->workspace()),
            ref: $this->refName(),
        ))->execute($loop, $definition, $hint);

        return new TellCompactionResult($this->inspection()->selector(), $result->toArray());
    }

    private function inspection(): ConversationInspection {
        return (new ConversationReader(new FilesystemArena($this->workspace())))->read($this->sessionId());
    }

    private function workspace(): WorkspaceState {
        $workspace = $this->agents->workspace()->discover($this->directory);
        if ($workspace === null) {
            throw new WorkspaceException('Tell workspace controls require an initialized workspace. Call workspace()->initialize() first.');
        }

        return $workspace;
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
