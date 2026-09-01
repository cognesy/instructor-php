<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace;

use Cognesy\Tell\Data\TellContext;
use Cognesy\Tell\Data\TellConversationView;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellRef;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Core\Workspace\Conversation\ContextInspector;
use Cognesy\Tell\Core\Workspace\Conversation\ConversationInspection;
use Cognesy\Tell\Core\Workspace\Conversation\ConversationReader;
use InvalidArgumentException;

/** Immutable, read-only handle to one verified canonical conversation head or root. */
final readonly class TellRef implements CanUseTellRef
{
    private ObjectHash $reference;

    public function __construct(
        private CanBuildTellAgent $agents,
        private CanOpenTellWorkspace $workspaces,
        private string $directory,
        string $hash,
    ) {
        $this->reference = new ObjectHash($hash);
        $this->inspection();
    }

    #[\Override]
    public function hash(): string {
        return $this->reference->toString();
    }

    #[\Override]
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

    #[\Override]
    public function transcript(bool $full = false): TellConversationView {
        $inspection = $this->inspection();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            messages: $inspection->transcriptRows($full),
        );
    }

    #[\Override]
    public function context(?TellRequest $request = null): TellContext {
        $request ??= TellRequest::prompt('Inspect immutable Tell context.');
        $request = $request->directory === '' ? $request->withDirectory($this->directory) : $request;
        $definition = $this->agents->definition($request);

        return new TellContext((new ContextInspector())->inspect(
            conversation: $this->inspection(),
            definition: $definition,
            connection: $request->connection,
        ));
    }

    private function inspection(): ConversationInspection {
        return (new ConversationReader($this->workspaces->open($this->directory)->arena))
            ->readImmutable($this->reference);
    }
}
