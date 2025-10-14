<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Data;

use Cognesy\Instructor\Streaming\PartialGen\PartialJson;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Throwable;

/**
 * Immutable carrier for partial processing state.
 * Flows through transformation pipeline carrying all transformation state.
 */
final readonly class PartialContext
{
    public function __construct(
        // Source
        public PartialInferenceResponse $response,

        // Transformation state
        public string $delta = '',
        public ?PartialJson $json = null,
        public mixed $object = null,

        // Control flags
        public bool $shouldEmit = false,
        public bool $isError = false,
        public ?string $errorMessage = null,

        // Tool call state (if applicable)
        public ?ToolCallStateUpdate $toolCallUpdate = null,

        // Sequence state (if applicable)
        public ?SequenceEvent $sequenceEvent = null,
    ) {}

    // NAMED CONSTRUCTORS //////////////////////////////////////////

    public static function fromResponse(PartialInferenceResponse $response): self {
        return new self(response: $response);
    }

    public static function error(PartialInferenceResponse $response, string|Throwable $message): self {
        return new self(
            response: $response,
            isError: true,
            errorMessage: $message instanceof Throwable ? $message->getMessage() : (string)$message,
        );
    }

    // FLUENT MUTATORS (all return new instance) /////////////////

    public function withDelta(string $delta): self {
        return new self(
            response: $this->response,
            delta: $delta,
            json: $this->json,
            object: $this->object,
            shouldEmit: $this->shouldEmit,
            isError: $this->isError,
            errorMessage: $this->errorMessage,
            toolCallUpdate: $this->toolCallUpdate,
            sequenceEvent: $this->sequenceEvent,
        );
    }

    public function withJson(PartialJson $json): self {
        return new self(
            response: $this->response,
            delta: $this->delta,
            json: $json,
            object: $this->object,
            shouldEmit: $this->shouldEmit,
            isError: $this->isError,
            errorMessage: $this->errorMessage,
            toolCallUpdate: $this->toolCallUpdate,
            sequenceEvent: $this->sequenceEvent,
        );
    }

    public function withObject(mixed $object): self {
        return new self(
            response: $this->response,
            delta: $this->delta,
            json: $this->json,
            object: $object,
            shouldEmit: $this->shouldEmit,
            isError: $this->isError,
            errorMessage: $this->errorMessage,
            toolCallUpdate: $this->toolCallUpdate,
            sequenceEvent: $this->sequenceEvent,
        );
    }

    public function markForEmit(): self {
        return new self(
            response: $this->response,
            delta: $this->delta,
            json: $this->json,
            object: $this->object,
            shouldEmit: true,
            isError: $this->isError,
            errorMessage: $this->errorMessage,
            toolCallUpdate: $this->toolCallUpdate,
            sequenceEvent: $this->sequenceEvent,
        );
    }

    public function withError(string|Throwable $message): self {
        return new self(
            response: $this->response,
            delta: $this->delta,
            json: $this->json,
            object: $this->object,
            shouldEmit: false,
            isError: true,
            errorMessage: $message instanceof Throwable ? $message->getMessage() : (string)$message,
            toolCallUpdate: $this->toolCallUpdate,
            sequenceEvent: $this->sequenceEvent,
        );
    }

    public function withToolCallUpdate(ToolCallStateUpdate $update): self {
        return new self(
            response: $this->response,
            delta: $this->delta,
            json: $this->json,
            object: $this->object,
            shouldEmit: $this->shouldEmit,
            isError: $this->isError,
            errorMessage: $this->errorMessage,
            toolCallUpdate: $update,
            sequenceEvent: $this->sequenceEvent,
        );
    }

    public function withSequenceEvent(SequenceEvent $event): self {
        return new self(
            response: $this->response,
            delta: $this->delta,
            json: $this->json,
            object: $this->object,
            shouldEmit: $this->shouldEmit,
            isError: $this->isError,
            errorMessage: $this->errorMessage,
            toolCallUpdate: $this->toolCallUpdate,
            sequenceEvent: $event,
        );
    }

    // CONVERSION //////////////////////////////////////////////////

    public function toPartialResponse(): PartialInferenceResponse {
        return $this->response
            ->withValue($this->object)
            ->withContent($this->json?->raw() ?? '');
    }

    // INTROSPECTION ///////////////////////////////////////////////

    public function hasJson(): bool {
        return $this->json !== null && !$this->json->isEmpty();
    }

    public function hasObject(): bool {
        return $this->object !== null;
    }
}
