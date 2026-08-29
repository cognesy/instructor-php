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
    ) {}
}
