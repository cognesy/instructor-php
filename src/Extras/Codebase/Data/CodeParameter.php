<?php

namespace Cognesy\Instructor\Extras\Codebase\Data;

class CodeParameter
{
    public function __construct(
        readonly public string $name = '',
        readonly public string $type = '',
        readonly public string $defaultValue = '',
        readonly public string $docComment = '',
    ) {}
}