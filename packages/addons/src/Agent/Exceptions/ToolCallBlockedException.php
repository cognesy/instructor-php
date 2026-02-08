<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Exceptions;

use Throwable;

/**
 * Exception thrown when a tool call is blocked by middleware.
 */
class ToolCallBlockedException extends AgentException
{
    public function __construct(
        string $toolName,
        string $reason = 'Blocked by middleware',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: "Tool call '{$toolName}' was blocked: {$reason}",
            previous: $previous,
        );
    }
}
