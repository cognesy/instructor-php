<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Creation\InferenceResponseFactory;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

class InferenceAttempt
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    public readonly DateTimeImmutable $updatedAt;

    private ?InferenceResponse $response;
    private ?PartialInferenceResponse $accumulatedPartial;

    private array $errors;
    private ?bool $isFinalized = null;

    // CONSTRUCTORS //////////////////////////////////////////////////////

    public function __construct(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponse $accumulatedPartial = null,
        ?bool $isFinalized = null,
        ?array $errors = null,
        //
        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->response = $response;
        $this->accumulatedPartial = $accumulatedPartial;
        $this->isFinalized = $isFinalized;
        $this->errors = $errors ?? [];
    }

    public static function fromResponse(InferenceResponse $response) : self {
        return new self(response: $response, isFinalized: true);
    }

    public static function started(): self {
        return new self(isFinalized: false);
    }

    public static function fromFailedResponse(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponse $accumulatedPartial = null,
        array $errors = [],
    ) : self {
        return new self(
            response: $response,
            accumulatedPartial: $accumulatedPartial,
            isFinalized: true,
            errors: $errors,
        );
    }

    public static function fromPartialResponses(PartialInferenceResponse $accumulatedPartial, bool $isFinalized = false) : self {
        // Backward compatibility: accept list, keep only the last aggregated partial
        $response = null;
        if ($isFinalized) {
            // Legacy finalization path via list-based factory
            $response = InferenceResponse::fromAccumulatedPartial($accumulatedPartial);
        }
        return new self(
            response: $response,
            accumulatedPartial: $accumulatedPartial,
            isFinalized: $isFinalized
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function response(): ?InferenceResponse {
        return $this->response;
    }

    public function partialResponse(): ?PartialInferenceResponse {
        return $this->accumulatedPartial;
    }

    public function errors(): array {
        return $this->errors;
    }

    public function isFinalized(): bool {
        return $this->isFinalized === true;
    }

    public function hasResponse(): bool {
        return $this->response !== null;
    }

    public function hasPartialResponses(): bool {
        return $this->accumulatedPartial !== null;
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function isFailed(): bool {
        if ($this->hasErrors()) {
            return true;
        }
        if ($this->response === null) {
            return false;
        }
        return $this->response->hasFinishedWithFailure();
    }

    public function usage(): Usage {
        return match (true) {
            $this->response !== null => $this->response->usage(),
            $this->accumulatedPartial !== null => $this->accumulatedPartial->usage(),
            default => Usage::none(),
        };
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponse $accumulatedPartial = null,
        ?bool $isFinalized = null,
        ?array $errors = null
    ): self {
        return new self(
            response: $response ?? $this->response,
            accumulatedPartial: $accumulatedPartial ?? $this->accumulatedPartial,
            isFinalized: $isFinalized ?? $this->isFinalized,
            errors: $errors ?? $this->errors,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withResponse(InferenceResponse $response): self {
        return $this->with(
            response: $response,
            isFinalized: true
        );
    }

    public function withNewPartialResponse(PartialInferenceResponse $partialResponse): self {
        // If we already have a partial and incoming looks like a delta (no accumulated content),
        // accumulate to keep usage/content correct in non-stream test paths.
        if ($this->accumulatedPartial !== null
            && !$partialResponse->hasContent()
            && !$partialResponse->hasReasoningContent()
        ) {
            $partialResponse = $partialResponse->withAccumulatedContent($this->accumulatedPartial);
        }
        return $this->with(
            accumulatedPartial: $partialResponse,
            isFinalized: false,
        );
    }

    public function withFinalizedPartialResponse(): self {
        // Prefer accumulated single partial when available
        if ($this->accumulatedPartial !== null) {
            return $this->with(
                response: InferenceResponse::fromAccumulatedPartial($this->accumulatedPartial),
                isFinalized: true
            );
        }
        $partial = $this->partialResponse() ?? PartialInferenceResponse::empty();
        return $this->with(
            response: InferenceResponse::fromAccumulatedPartial($partial),
            isFinalized: true
        );
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'response' => $this->response?->toArray(),
            'isFinalized' => $this->isFinalized,
            'errors' => $this->errorsToStringArray($this->errors),
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            response: InferenceResponse::fromArray($data['response'] ?? []),
            isFinalized: $data['isFinalized'] ?? null,
            errors: $data['errors'] ?? [],
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }

    // PRIVATE HELPERS ////////////////////////////////////////////////////

    private function errorsToStringArray(array $errors) : array {
        return array_map(fn($e) => $e instanceof Throwable ? $e->getMessage() : (string) $e, $errors);
    }
}
