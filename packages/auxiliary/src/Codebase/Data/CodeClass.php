<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Codebase\Data;

class CodeClass
{
    public function __construct(
        readonly public string $namespace = '',
        readonly public string $name = '',
        readonly public string $shortName = '',
        readonly public string $extends = '',
        /** @var string[] */
        readonly public array $implements = [],
        /** @var string[] */
        readonly public array $uses = [],
        /** @var string[] */
        readonly public array $imports = [],
        /** @var CodeFunction[] */
        readonly public array $methods = [],
        /** @var string[] */
        readonly public array $properties = [],
        readonly public string $docComment = '',
        readonly public string $body = '',
    ) {}
}
