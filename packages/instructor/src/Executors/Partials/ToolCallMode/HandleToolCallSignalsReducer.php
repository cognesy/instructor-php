<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials\ToolCallMode;

use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Stream\Contracts\Reducer;
use Psr\EventDispatcher\EventDispatcherInterface;

class HandleToolCallSignalsReducer implements Reducer
{
    private ToolCallStreamState $state;

    public function __construct(
        private Reducer $inner,
        private string $expectedToolName,
        private EventDispatcherInterface $events,
    ) {
        $this->state = ToolCallStreamState::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->state = ToolCallStreamState::empty(
            onStart: function (ToolCall $tc): void {
                $this->events->dispatch(new StreamedToolCallStarted(['toolCall' => $tc->toArray()]));
            },
            onUpdate: function (ToolCall $tc): void {
                $this->events->dispatch(new StreamedToolCallUpdated(['toolCall' => $tc->toArray()]));
            },
            onComplete: function (ToolCall $tc): void {
                $this->events->dispatch(new StreamedToolCallCompleted(['toolCall' => $tc->toArray()]));
            },
        );
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        // Handle tool name signal
        if ($reducible->toolName !== '') {
            $this->state = $this->state->handleSignal($reducible->toolName);
        }

        // Start tool call if not active
        $this->state = $this->state->startIfEmpty($this->expectedToolName);

        // Forward original response (ExtractDelta will handle args extraction)
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // Finalize active tool call if present
        if ($this->state->hasActive()) {
            $this->state->finalizeActive();
        }
        return $this->inner->complete($accumulator);
    }
}
