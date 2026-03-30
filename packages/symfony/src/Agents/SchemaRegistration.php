<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Agents;

use Cognesy\Agents\Capability\StructuredOutput\SchemaDefinition;

final readonly class SchemaRegistration
{
    public function __construct(
        public string $name,
        public string|SchemaDefinition $schema,
    ) {}
}
