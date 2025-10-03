<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Collections\InferenceAttemptList;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
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

    public function response(): ?InferenceResponse {
        return $this->currentAttempt?->response();
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->currentAttempt?->response()?->finishReason();
    }

    public function partialResponses(): PartialInferenceResponseList {
        return $this->currentAttempt?->partialResponses() ?? PartialInferenceResponseList::empty();
    }

    public function usage(): Usage {
        $attemptsUsage = $this->attempts->usage();
        $current = $this->currentAttempt;
        if ($current !== null && !$current->isFinalized()) {
            // Include only partials usage from the current attempt to avoid double counting
            $partialsUsage = Usage::none();
            $partials = $current->partialResponses();
            if ($partials !== null && !$partials->isEmpty()) {
                foreach ($partials->all() as $partial) {
                    $partialsUsage = $partialsUsage->withAccumulated($partial->usage());
                }
            }
            return $attemptsUsage->withAccumulated($partialsUsage);
        }
        return $attemptsUsage;
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

    public function withNewResponse(InferenceResponse $response): self {
        $newAttempt = InferenceAttempt::fromResponse($response);
        return $this->with(
            attempts: $this->attempts->withNewAttempt($newAttempt),
            currentAttempt: $newAttempt,
            isFinalized: true,
        );
    }

    public function withFailedResponse(
        ?InferenceResponse $response = null,
        ?PartialInferenceResponseList $partialResponses = null,
        string|Throwable ...$errors,
    ): self {
        $newAttempt = InferenceAttempt::fromFailedResponse(
            response: $response,
            partialResponses: $partialResponses,
            errors: $errors,
        );
        return $this->with(
            attempts: $this->attempts->withNewAttempt($newAttempt),
            currentAttempt: $newAttempt,
            isFinalized: true,
        );
    }

    public function withNewPartialResponse(PartialInferenceResponse $partialResponse): self {
        $currentAttempt = $this->currentAttempt ?? InferenceAttempt::fromPartialResponses(
            PartialInferenceResponseList::empty(),
            isFinalized: false,
        );
        return $this->with(
            currentAttempt: $currentAttempt->withNewPartialResponse($partialResponse),
            isFinalized: false,
        );
    }

    public function withFinalizedPartialResponse(): self {
        $partials = $this->currentAttempt?->partialResponses() ?? PartialInferenceResponseList::empty();
        $newAttempt = InferenceAttempt::fromPartialResponses($partials, true);
        return $this->with(
            attempts: $this->attempts->withNewAttempt($newAttempt),
            currentAttempt: $newAttempt,
            isFinalized: true,
        );
    }

    public function withFailedFinalizedResponse(string|Throwable ...$errors): self {
        $newAttempt = InferenceAttempt::fromFailedResponse(
            response: $this->currentAttempt?->response(),
            partialResponses: $this->currentAttempt?->partialResponses(),
            errors: $errors,
        );
        return $this->with(
            attempts: $this->attempts->withNewAttempt($newAttempt),
            currentAttempt: $newAttempt,
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
