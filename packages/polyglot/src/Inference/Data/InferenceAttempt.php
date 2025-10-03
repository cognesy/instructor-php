<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
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
    private ?PartialInferenceResponseList $partialResponses;

    private array $errors;
    private ?bool $isFinalized = null;

    // CONSTRUCTORS //////////////////////////////////////////////////////

    public function __construct(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponseList $partialResponses = null,
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
        $this->partialResponses = $partialResponses;
        $this->isFinalized = $isFinalized;
        $this->errors = $errors ?? [];
    }

    public static function fromResponse(InferenceResponse $response) : self {
        return new self(response: $response, isFinalized: true);
    }

    public static function fromFailedResponse(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponseList $partialResponses = null,
        array $errors = [],
    ) : self {
        return new self(
            response: $response,
            partialResponses: $partialResponses,
            isFinalized: true,
            errors: $errors,
        );
    }

    public static function fromPartialResponses(PartialInferenceResponseList $partialResponses, bool $isFinalized = false) : self {
        $response = $isFinalized ? InferenceResponseFactory::fromPartialResponses($partialResponses) : null;
        return new self(
            response: $response,
            partialResponses: $partialResponses,
            isFinalized: $isFinalized
        );
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function response(): ?InferenceResponse {
        return $this->response;
    }

    public function partialResponses(): ?PartialInferenceResponseList {
        return $this->partialResponses;
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
        return $this->partialResponses !== null && !$this->partialResponses->isEmpty();
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
        $usage = Usage::none();
        if ($this->response !== null) {
            $usage = $this->response->usage();
        }
        if ($this->partialResponses !== null && !$this->partialResponses->isEmpty()) {
            foreach ($this->partialResponses->all() as $partial) {
                $usage = $usage->withAccumulated($partial->usage());
            }
        }
        return $usage;
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponseList $partialResponses = null,
        ?bool $isFinalized = null,
        ?array $errors = null
    ): self {
        return new self(
            response: $response ?? $this->response,
            partialResponses: $partialResponses ?? $this->partialResponses,
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
        $partialResponses = $this->partialResponses ?? PartialInferenceResponseList::empty();
        return $this->with(
            partialResponses: $partialResponses->withNewPartialResponse($partialResponse),
            isFinalized: false,
        );
    }

    public function withFinalizedPartialResponse(): self {
        $partials = $this->partialResponses ?? PartialInferenceResponseList::empty();
        return $this->with(
            response: InferenceResponseFactory::fromPartialResponses($partials),
            isFinalized: true
        );
    }

    public function withFailedResponse(
        InferenceResponse $response,
        ?PartialInferenceResponseList $partialResponses,
        string|Throwable $error
    ): self {
        $errors = $this->errors ?? [];
        $errors[] = $error;
        return $this->with(
            response: $response,
            partialResponses: $partialResponses ?? $this->partialResponses,
            isFinalized: true,
            errors: $errors,
        );
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'response' => $this->response?->toArray(),
            'partialResponses' => $this->partialResponses?->toArray(),
            'isFinalized' => $this->isFinalized,
            'errors' => $this->errorsToStringArray($this->errors),
        ];
    }

    public static function fromArray(array $data) : self {
        return new self(
            response: InferenceResponse::fromArray($data['response'] ?? []),
            partialResponses: PartialInferenceResponseList::fromArray($data['partialResponses'] ?? []),
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
