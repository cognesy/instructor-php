<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Exception;

use Cognesy\Tell\Workspace\Arena\ObjectHash;

final class RefConflict extends ArenaException
{
    public function __construct(
        public readonly string $ref,
        public readonly ?ObjectHash $expected,
        public readonly ?ObjectHash $actual,
    ) {
        parent::__construct("Tell ref '{$ref}' changed before it could be published.");
    }
}
