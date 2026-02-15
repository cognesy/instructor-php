<?php declare(strict_types=1);

namespace Cognesy\Agents\Template\Parsers;

use Cognesy\Agents\Template\Data\AgentDefinition;

interface CanParseAgentDefinition
{
    public function parse(mixed $data): AgentDefinition;
}

