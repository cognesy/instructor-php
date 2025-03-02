<?php

namespace Cognesy\Auxiliary\Codebase\Data;

class CodeProperty
{
    public function __construct(
        readonly public string $name = '',
        readonly public string $visibility = 'public',
        readonly public string $type = '',
        readonly public string $defaultValue = '',
        readonly public string $docComment = '',
    ) {}
}
