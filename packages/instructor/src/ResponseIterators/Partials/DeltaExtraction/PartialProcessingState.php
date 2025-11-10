<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Partials\DeltaExtraction;

use Cognesy\Instructor\ResponseIterators\Partials\JsonMode\PartialJson;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Throwable;

/**
 * Immutable carrier for core partial processing state.
 * Flows through transformation pipeline carrying transformation state.
 *
 * Mode-specific state (tool calls, sequences) is handled via typed context wrappers
 * in their respective reducers, not carried through the entire pipeline.
 */
final readonly class PartialProcessingState
{
    public function __construct(
        // Source
        public PartialInferenceResponse $response,

        // Transformation state (progressive accumulation)
        public string $delta = '',
        public ?PartialJson $json = null,
        public mixed $object = null,

        // Control flags
        public bool $shouldEmit = false,
        public bool $isError = false,
        public ?string $errorMessage = null,
    ) {}

    // CONSTRUCTORS //////////////////////////////////////////////////

    public static function fromResponse(PartialInferenceResponse $response): self {
        return new self(response: $response);
    }

    // ACCESSORS /////////////////////////////////////////////////////

    public function hasJson(): bool {
        return $this->json !== null && !$this->json->isEmpty();
    }

    public function hasObject(): bool {
        return $this->object !== null;
    }

    // MUTATORS //////////////////////////////////////////////////////

    public function withDelta(string $delta): self {
        return $this->with(delta: $delta);
    }

    public function withJson(PartialJson $json): self {
        return $this->with(json: $json);
    }

    public function withObject(mixed $object): self {
        return $this->with(object: $object);
    }

    public function markForEmission(): self {
        return $this->with(shouldEmit: true);
    }

    public function withError(string|Throwable $message): self {
        return $this->with(
            shouldEmit: false,
            isError: true,
            errorMessage: $message instanceof Throwable ? $message->getMessage() : $message,
        );
    }

    // CONVERSION //////////////////////////////////////////////////

    public function toPartialResponse(): PartialInferenceResponse {
        return $this->response
            ->withValue($this->object)
            ->withContent($this->json?->raw() ?? '');
    }

    // INTERNAL /////////////////////////////////////////////////////

    public function with(
        ?string $delta = null,
        ?PartialJson $json = null,
        mixed $object = null,
        ?bool $shouldEmit = null,
        ?bool $isError = null,
        ?string $errorMessage = null,
    ): self {
        return new self(
            response: $this->response,
            delta: $delta ?? $this->delta,
            json: $json ?? $this->json,
            object: $object ?? $this->object,
            shouldEmit: $shouldEmit ?? $this->shouldEmit,
            isError: $isError ?? $this->isError,
            errorMessage: $errorMessage ?? $this->errorMessage,
        );
    }
}
