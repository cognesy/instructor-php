<?php

declare(strict_types=1);

namespace Cognesy\Tell;

use Cognesy\Tell\Runtime\TellAgentFactory;

/** Developer-facing workspace control surface for a Tell project. */
final readonly class TellWorkspace
{
    public function __construct(
        private Tell $tell,
        private TellAgentFactory $agents,
        private string $directory,
    ) {}

    public function initialize(): TellWorkspaceInfo
    {
        $result = $this->agents->workspace()->initialize($this->directory);

        return new TellWorkspaceInfo(
            root: $result->workspace->paths->root,
            schema: $result->workspace->schema,
            created: $result->created,
        );
    }

    public function discover(): ?TellWorkspaceInfo
    {
        $workspace = $this->agents->workspace()->discover($this->directory);
        if ($workspace === null) {
            return null;
        }

        return new TellWorkspaceInfo($workspace->paths->root, $workspace->schema, false);
    }

    public function main(): TellConversation
    {
        return new TellConversation($this->tell, $this->agents, $this->directory);
    }

    public function conversation(string $name): TellConversation
    {
        return new TellConversation($this->tell, $this->agents, $this->directory, $name);
    }
}
