<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** The safe public identity of an initialized Tell workspace. */
final readonly class TellWorkspaceInfo
{
    public function __construct(
        public string $root,
        public int $schema,
        public bool $created,
        public ?string $arena = null,
    ) {}

    /** @return array{root: string, arena: ?string, schema: int} */
    public function toArray(): array {
        return [
            'root' => $this->root,
            'arena' => $this->arena,
            'schema' => $this->schema,
        ];
    }
}
