<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase\Data;

use Cognesy\Auxiliary\Codebase\Enums\CodeElementVisibility;

class CodeFunction
{
    public function __construct(
        readonly public string $namespace = '',
        readonly public string $name = '',
        readonly public string $shortName = '',
        readonly public CodeElementVisibility $visibility = CodeElementVisibility::Public,
        readonly public string $docComment = '',
        readonly public string $body = '',
    ) {}
}
