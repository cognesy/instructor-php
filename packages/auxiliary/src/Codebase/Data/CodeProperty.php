<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase\Data;

use Cognesy\Auxiliary\Codebase\Enums\CodeElementVisibility;

class CodeProperty
{
    public function __construct(
        readonly public string $name = '',
        readonly public CodeElementVisibility $visibility = CodeElementVisibility::Public,
        readonly public string $type = '',
        readonly public string $defaultValue = '',
        readonly public string $docComment = '',
    ) {}
}
