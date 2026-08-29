<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Data\TellContext;
use Cognesy\Tell\Data\TellConversationView;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Conversation\ContextInspector;
use Cognesy\Tell\Workspace\Conversation\ConversationInspection;
use Cognesy\Tell\Workspace\Conversation\ConversationReader;
use Cognesy\Tell\Workspace\WorkspaceState as StoredWorkspace;
use InvalidArgumentException;

/** Immutable, read-only handle to one verified canonical conversation head or root. */
final readonly class TellRef
{
    private ObjectHash $reference;

    public function __construct(
        private TellAgentFactory $agents,
        private string $directory,
        string $hash,
    ) {
        $this->reference = new ObjectHash($hash);
        $this->inspection();
    }

    public function hash(): string {
        return $this->reference->toString();
    }

    public function history(int $limit = 20, bool $full = false): TellConversationView {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Tell history limit must be between 1 and 100.');
        }
        $inspection = $this->inspection();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            turns: array_slice($inspection->historyRows($full), -$limit),
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
        $request ??= TellRequest::prompt('Inspect immutable Tell context.');
        $request = $request->directory === '' ? $request->withDirectory($this->directory) : $request;
        $definition = $this->agents->definition($request->toOptions());

        return new TellContext((new ContextInspector())->inspect(
            conversation: $this->inspection(),
            definition: $definition,
            connection: $request->connection,
        ));
    }

    private function inspection(): ConversationInspection {
        return (new ConversationReader(new FilesystemArena($this->workspace())))
            ->readImmutable($this->reference);
    }

    private function workspace(): StoredWorkspace {
        $workspace = $this->agents->workspace()->discover($this->directory);
        if ($workspace === null) {
            throw new WorkspaceException('Tell immutable-ref inspection requires an initialized workspace. Call workspace()->initialize() first.');
        }

        return $workspace;
    }
}
