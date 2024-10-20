<?php

namespace Cognesy\Instructor\Utils\Codebase\Data;

class CodeFunction
{
    public function __construct(
        readonly public string $namespace = '',
        readonly public string $name = '',
        readonly public string $shortName = '',
        readonly public string $visibility = 'public',
        readonly public string $docComment = '',
        readonly public string $body = '',
    ) {}
}
