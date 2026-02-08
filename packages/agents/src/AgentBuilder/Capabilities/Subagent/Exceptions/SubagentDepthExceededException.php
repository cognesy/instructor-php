<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Subagent\Exceptions;

use Cognesy\Agents\Exceptions\AgentException;

final class SubagentDepthExceededException extends AgentException
{
    public function __construct(
        public readonly string $subagentName,
        public readonly int $currentDepth,
        public readonly int $maxDepth,
    ) {
        parent::__construct(
            "Maximum nesting depth ({$this->maxDepth}) reached for subagent '{$this->subagentName}'."
        );
    }
}
