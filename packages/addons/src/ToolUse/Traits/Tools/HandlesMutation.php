<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits\Tools;

use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;

trait HandlesMutation
{
    public function withThrowOnToolFailure(bool $throw): self {
        return new self(
            tools: $this->tools,
            parallelToolCalls: $this->parallelToolCalls,
            throwOnToolFailure: $throw,
            events: $this->events,
        );
    }

    public function withParallelCalls(bool $parallelToolCalls = true): self {
        return new self(
            tools: $this->tools,
            parallelToolCalls: $parallelToolCalls,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: $this->events,
        );
    }

    public function withEventHandler(CanHandleEvents $events): self {
        return new self(
            tools: $this->tools,
            parallelToolCalls: $this->parallelToolCalls,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: EventBusResolver::using($events),
        );
    }

    public function withTool(ToolInterface $tool): self {
        $newTools = $this->tools;
        $newTools[$tool->name()] = $tool;
        return new self(
            tools: $newTools,
            parallelToolCalls: $this->parallelToolCalls,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: $this->events,
        );
    }

    public function withToolRemoved(string $name): self {
        $newTools = $this->tools;
        unset($newTools[$name]);
        return new self(
            tools: $newTools,
            parallelToolCalls: $this->parallelToolCalls,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: $this->events,
        );
    }
}