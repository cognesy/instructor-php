<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

final readonly class WorkspaceState
{
    public function __construct(
        public WorkspacePaths $paths,
        public int $schema,
    ) {}

    /** @return array{root: string, arena: string, schema: int} */
    public function toArray(): array {
        return [
            'root' => $this->paths->root,
            'arena' => $this->paths->arena,
            'schema' => $this->schema,
        ];
    }
}
