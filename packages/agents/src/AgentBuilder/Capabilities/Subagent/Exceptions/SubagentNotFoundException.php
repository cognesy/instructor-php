<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions;

use Cognesy\Agents\Exceptions\AgentException;
use Throwable;

final class SubagentNotFoundException extends AgentException
{
    public function __construct(
        public readonly string $subagentName,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "Subagent '{$this->subagentName}' not found.",
            $previous
        );
    }
}
