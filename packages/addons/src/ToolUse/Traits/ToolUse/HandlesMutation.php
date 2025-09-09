<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\ToolUse\Data\Collections\StepProcessors;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Tools;
use Cognesy\Messages\Messages;

trait HandlesMutation
{
    public function withProcessors(CanProcessToolState ...$processors): self {
        return new self(
            state: $this->state,
            processors: new StepProcessors(...$processors),
            continuationCriteria: $this->continuationCriteria,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withDriver(CanUseTools $driver) : self {
        $this->driver = $driver;
        return new self(
            state: $this->state,
            processors: $this->processors,
            continuationCriteria: $this->continuationCriteria,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withContinuationCriteria(CanDecideToContinueToolUse ...$continuationCriteria) : self {
        return new self(
            state: $this->state,
            processors: $this->processors,
            continuationCriteria: new ContinuationCriteria(...$continuationCriteria),
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withState(ToolUseState $state) : self {
        return new self(
            state: $state,
            processors: $this->processors,
            continuationCriteria: $this->continuationCriteria,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withTools(array|ToolInterface|Tools $tools) : self {
        $tools = match(true) {
            is_array($tools) => new Tools($tools),
            $tools instanceof ToolInterface => new Tools([$tools]),
            $tools instanceof Tools => $tools,
            default => new Tools(),
        };

        return new self(
            state: $this->state->withTools($tools->withEventHandler($this->events)),
            processors: $this->processors,
            continuationCriteria: $this->continuationCriteria,
            driver: $this->driver,
            events: $this->events,
        );
    }

    public function withMessages(string|array|Messages $messages) : self {
        $messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            is_array($messages) => $messages,
            is_object($messages) && ($messages instanceof Messages) => $messages->toArray(),
            default => []
        };
        $this->state->withMessages(Messages::fromArray($messages));
        return $this;
    }
}