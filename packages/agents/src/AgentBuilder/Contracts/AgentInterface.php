<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Contracts;

use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;
use Cognesy\Agents\AgentBuilder\Data\AgentDescriptor;

/**
 * High-level interface for agent definitions.
 *
 * Extends CanControlAgentLoop to provide iterative execution capabilities,
 * plus agent identity via descriptor.
 */
interface AgentInterface extends CanControlAgentLoop
{
    public function descriptor(): AgentDescriptor;
}
