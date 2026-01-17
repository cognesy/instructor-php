<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Addons\Agent\Definitions\AgentDefinition;
use Cognesy\Utils\Result\Result;

interface AgentBlueprint
{
    public static function fromDefinition(AgentDefinition $definition): Result;
}
