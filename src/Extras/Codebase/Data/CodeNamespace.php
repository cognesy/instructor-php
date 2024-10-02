<?php

namespace Cognesy\Instructor\Extras\Codebase\Data;

class CodeNamespace
{
    public function __construct(
        readonly public string $name = '',
        /** @var string[] */
        readonly public array $namespaces = [],
        /** @var string[] */
        readonly public array $classes = [],
        /** @var string[] */
        readonly public array $functions = [],
    ) {}
}
