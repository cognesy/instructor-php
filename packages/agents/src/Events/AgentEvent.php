<?php declare(strict_types=1);

namespace Cognesy\Agents\Events;

use Cognesy\Events\Event;

/**
 * Base class for all agent-related events.
 * Provides common structure for agent execution observability.
 */
abstract class AgentEvent extends Event {}
