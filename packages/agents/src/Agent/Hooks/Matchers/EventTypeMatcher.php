<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Hooks\Matchers;

use Cognesy\Agents\Agent\Hooks\Contracts\HookContext;
use Cognesy\Agents\Agent\Hooks\Contracts\HookMatcher;
use Cognesy\Agents\Agent\Hooks\Enums\HookType;

/**
 * Matcher that matches contexts by event type.
 *
 * Filters hooks to only run for specific event types.
 * Useful when registering a general-purpose hook that should only
 * respond to certain events.
 *
 * @example
 * // Match only PreToolUse events
 * $matcher = new EventTypeMatcher(HookEvent::PreToolUse);
 *
 * // Match multiple event types
 * $matcher = new EventTypeMatcher(HookEvent::BeforeStep, HookEvent::AfterStep);
 */
final readonly class EventTypeMatcher implements HookMatcher
{
    /** @var array<HookType> */
    private array $events;

    /**
     * @param HookType ...$events The events to match
     */
    public function __construct(HookType ...$events)
    {
        $this->events = $events;
    }

    #[\Override]
    public function matches(HookContext $context): bool
    {
        return in_array($context->eventType(), $this->events, true);
    }
}
