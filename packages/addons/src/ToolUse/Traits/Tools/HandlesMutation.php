<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits\Tools;

use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;

trait HandlesMutation
{
    public function withThrowOnToolFailure(bool $throw): Tools {
        return new self(
            tools: $this->tools,
            parallelToolCalls: $this->parallelToolCalls,
            throwOnToolFailure: $throw,
            events: $this->events,
        );
    }

    public function withParallelCalls(bool $parallelToolCalls = true): Tools {
        return new self(
            tools: $this->tools,
            parallelToolCalls: $parallelToolCalls,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: $this->events,
        );
    }

    public function withEventHandler(CanHandleEvents $events): Tools {
        return new self(
            tools: $this->tools,
            parallelToolCalls: $this->parallelToolCalls,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: EventBusResolver::using($events),
        );
    }

    public function withTools(ToolInterface ...$tools): Tools {
        $newTools = $this->tools;
        foreach ($tools as $tool) {
            $newTools[$tool->name()] = $tool;
        }
        return new self(
            tools: $newTools,
            parallelToolCalls: $this->parallelToolCalls,
            throwOnToolFailure: $this->throwOnToolFailure,
            events: $this->events,
        );
    }

    public function withTool(ToolInterface $tool): Tools {
        return $this->withTools($tool);
    }

    public function withToolRemoved(string $name): Tools {
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