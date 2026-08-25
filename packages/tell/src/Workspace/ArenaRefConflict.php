<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalHash;

final class ArenaRefConflict extends ArenaException
{
    public function __construct(
        public readonly string $ref,
        public readonly ?CanonicalHash $expected,
        public readonly ?CanonicalHash $actual,
    ) {
        parent::__construct("Tell ref '{$ref}' changed before it could be published.");
    }
}
