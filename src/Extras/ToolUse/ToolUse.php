<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanUseTools;
use Cognesy\Instructor\Extras\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Instructor\Utils\Messages\Messages;
use Generator;

class ToolUse {
    use Traits\ToolUse\HandlesContinuationCriteria;
    use Traits\ToolUse\HandlesStepProcessors;

    private CanUseTools $driver;
    private array $processors = [];
    private array $continuationCriteria = [];

    private ToolUseContext $context;

    public function __construct(
    ) {
        $this->context = new ToolUseContext;
    }

    public function withDriver(ToolCallingDriver $driver) : self {
        $this->driver = $driver;
        return $this;
    }

    public function withTools(array|Tools $tools) : self {
        if (is_array($tools)) {
            $tools = new Tools($tools);
        }
        $this->context->withTools($tools);
        return $this;
    }

    public function withMessages(string|array $messages) : self {
        $messages = match(true) {
            is_string($messages) => [['role' => 'user', 'content' => $messages]],
            is_array($messages) => $messages,
            default => []
        };
        $this->context->withMessages(Messages::fromArray($messages));
        return $this;
    }

    public function context() : ToolUseContext {
        return $this->context;
    }

    public function nextStep() : ToolUseStep {
        $step = $this->driver->useTools($this->context);
        return $this->processStep($step, $this->context);
    }

    public function finalStep() : ToolUseStep {
        while ($this->hasNextStep()) {
            $this->nextStep();
        }
        return $this->context->currentStep();
    }

    /** @return Generator<ToolUseStep> */
    public function iterator() : iterable {
        while ($this->hasNextStep()) {
            yield $this->nextStep();
        }
    }
}
