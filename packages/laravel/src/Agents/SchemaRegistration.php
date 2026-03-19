<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Agents;

use Cognesy\Agents\Capability\StructuredOutput\SchemaDefinition;

final readonly class SchemaRegistration
{
    public function __construct(
        public string $name,
        public string|SchemaDefinition $schema,
    ) {}
}
