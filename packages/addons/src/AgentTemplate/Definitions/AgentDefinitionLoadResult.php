<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentTemplate\Definitions;

final readonly class AgentDefinitionLoadResult
{
    /**
     * @param array<string, AgentDefinition> $definitions
     * @param array<string, string> $errors
     */
    public function __construct(
        public array $definitions = [],
        public array $errors = [],
    ) {}
}
