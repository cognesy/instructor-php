<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Collections\InferenceAttemptList;
use Cognesy\Polyglot\Inference\Creation\InferenceResponseFactory;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Arrays;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

class InferenceExecution
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    public readonly DateTimeImmutable $updatedAt;

    private InferenceRequest $request;
    private InferenceAttemptList $attempts;

    private ?InferenceAttempt $currentAttempt;
    private bool $isFinalized;

    public function __construct(
        ?InferenceRequest $request = null,
        ?InferenceAttemptList $attempts = null,
        ?InferenceAttempt $currentAttempt = null,
        ?bool $isFinalized = null,
        //
        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->request = $request ?? new InferenceRequest();
        $this->attempts = $attempts ?? InferenceAttemptList::empty();
        $this->currentAttempt = $currentAttempt ?? $this->attempts->last();
        $this->isFinalized = $isFinalized ?? false;
        //
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;
    }

    public static function fromRequest(InferenceRequest $request): self {
        return new self($request);
    }

    public static function empty(): self {
        return new self();
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function request(): InferenceRequest {
        return $this->request;
    }

    public function attempts(): InferenceAttemptList {
        return $this->attempts;
    }

    public function currentAttempt(): ?InferenceAttempt {
        return $this->currentAttempt;
    }

    public function response(): ?InferenceResponse {
        return $this->currentAttempt?->response();
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->currentAttempt?->response()?->finishReason();
    }

    public function partialResponse(): ?PartialInferenceResponse {
        return $this->currentAttempt?->partialResponse();
    }

    /**
     * Deprecated shim for compatibility; returns list with single aggregated partial.
     * @deprecated Use partialResponse() instead.
     */
    public function partialResponses(): PartialInferenceResponse {
        return $this->currentAttempt?->partialResponse() ?? PartialInferenceResponse::empty();
    }

    public function usage(): Usage {
        $attemptsUsage = $this->attempts->usage();
        $current = $this->currentAttempt;
        if ($current === null) {
            return $attemptsUsage;
        }
        // Check if current attempt is already counted in attempts list
        $isInAttempts = $this->attempts->count() > 0
            && $this->attempts->last()?->id === $current->id;
        if ($isInAttempts) {
            // Already counted via attempts->usage()
            return $attemptsUsage;
        }
        // Include current attempt's usage (whether finalized or streaming)
        return $attemptsUsage->withAccumulated($current->usage());
    }

    public function hasErrors(): bool {
        return !empty($this->errors());
    }

    public function isFinalized(): bool {
        return $this->isFinalized;
    }

    public function isFailed(): bool {
        // a) has no response
        if ($this->response() === null) {
            return true;
        }
        // b) has errors
        if ($this->hasErrors()) {
            return true;
        }
        // c) has finish reason indicating failure
        return $this->response()->hasFinishedWithFailure();
    }

    // COHESION HELPERS ///////////////////////////////////////

    /**
     * Errors for the in‑flight attempt (if any).
     */
    public function currentErrors(): array {
        return $this->currentAttempt?->errors() ?? [];
    }

    /**
     * Aggregate errors from finalized attempts and the current one.
     */
    public function errors(): array {
        $chunks = [];
        foreach ($this->attempts->all() as $attempt) {
            if ($attempt->hasErrors()) {
                $chunks[] = $attempt->errors();
            }
        }
        if ($this->currentAttempt?->hasErrors()) {
            $chunks[] = $this->currentAttempt->errors();
        }
        return Arrays::mergeMany($chunks);
    }

    /**
     * True if the latest finalized attempt succeeded.
     */
    public function isSuccessful(): bool {
        if ($this->currentAttempt !== null && !$this->currentAttempt->isFinalized()) {
            return false;
        }
        $last = $this->attempts->last();
        if ($last === null) {
            return false;
        }
        if ($last->hasErrors()) {
            return false;
        }
        $resp = $last->response();
        return $resp !== null && !$resp->hasFinishedWithFailure();
    }

    /**
     * True if the latest finalized attempt failed.
     */
    public function isFailedFinal(): bool {
        $last = $this->attempts->last();
        if ($last === null) {
            return false;
        }
        if ($last->hasErrors()) {
            return true;
        }
        $resp = $last->response();
        return $resp?->hasFinishedWithFailure() ?? false;
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?InferenceRequest $request = null,
        ?InferenceAttemptList $attempts = null,
        ?InferenceAttempt $currentAttempt = null,
        ?bool $isFinalized = null,
    ): self {
        return new self(
            request: $request ?? $this->request,
            attempts: $attempts ?? $this->attempts,
            currentAttempt: $currentAttempt ?? $this->currentAttempt,
            isFinalized: $isFinalized ?? $this->isFinalized,
            //
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withRequest(InferenceRequest $request): self {
        return $this->with(request: $request, isFinalized: false);
    }

    public function startAttempt(?InferenceAttempt $attempt = null): self {
        $newAttempt = $attempt ?? InferenceAttempt::started();
        return $this->with(
            currentAttempt: $newAttempt,
            isFinalized: false,
        );
    }

    public function withSuccessfulAttempt(InferenceResponse $response): self {
        $newAttempt = $this->currentAttempt !== null && !$this->currentAttempt->isFinalized()
            ? $this->currentAttempt->with(
                response: $response,
                isFinalized: true,
                errors: [],
            )
            : InferenceAttempt::fromResponse($response);
        return $this->withFinalizedAttempt($newAttempt);
    }

    public function withFailedAttempt(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponse $partialResponse = null,
        string|Throwable ...$errors,
    ): self {
        $newAttempt = $this->currentAttempt !== null && !$this->currentAttempt->isFinalized()
            ? $this->currentAttempt->with(
                response: $response,
                accumulatedPartial: $partialResponse ?? $this->currentAttempt->partialResponse(),
                isFinalized: true,
                errors: array_merge($this->currentAttempt->errors(), $errors),
            )
            : InferenceAttempt::fromFailedResponse(
                response: $response,
                accumulatedPartial: $partialResponse,
                errors: $errors,
            );
        return $this->withFinalizedAttempt($newAttempt);
    }

    public function withNewPartialResponse(PartialInferenceResponse $partialResponse): self {
        $currentAttempt = match (true) {
            $this->currentAttempt === null => InferenceAttempt::started(),
            $this->currentAttempt->isFinalized() => InferenceAttempt::started(),
            default => $this->currentAttempt,
        };
        return $this->with(
            currentAttempt: $currentAttempt->withNewPartialResponse($partialResponse),
            isFinalized: false,
        );
    }

    public function withFinalizedPartialResponse(): self {
        // Prefer finalizing from the single accumulated partial when available
        $curr = $this->currentAttempt;
        if ($curr !== null && $curr->partialResponse() !== null) {
            $newAttempt = $curr->withFinalizedPartialResponse();
        } else {
            $partial = $curr?->partialResponse() ?? PartialInferenceResponse::empty();
            $response = InferenceResponse::fromAccumulatedPartial($partial);
            $newAttempt = InferenceAttempt::fromResponse($response);
        }
        return $this->with(
            attempts: $this->attempts->withNewAttempt($newAttempt),
            currentAttempt: $newAttempt,
            isFinalized: true,
        );
    }
    private function withFinalizedAttempt(InferenceAttempt $attempt): self {
        return $this->with(
            attempts: $this->attempts->withNewAttempt($attempt),
            currentAttempt: $attempt,
            isFinalized: true,
        );
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'request' => $this->request->toArray(),
            'attempts' => $this->attempts->toArray(),
            'currentAttempt' => $this->currentAttempt?->toArray(),
            'isFinalized' => $this->isFinalized,
            //
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromArray(mixed $data): self {
        return new self(
            request: InferenceRequest::fromArray($data['request'] ?? []),
            attempts: InferenceAttemptList::fromArray($data['attempts'] ?? []),
            currentAttempt: InferenceAttempt::fromArray($data['currentAttempt'] ?? []),
            isFinalized: $data['isFinalized'] ?? false,
            //
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }
}
