<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Collections\StructuredOutputAttemptList;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final readonly class StructuredOutputExecution
{
    private string $id;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private int $step;

    private StructuredOutputRequest $request;
    private StructuredOutputConfig $config;
    private ?ResponseModel $responseModel;

    private StructuredOutputAttemptList $attempts;
    private StructuredOutputAttempt $currentAttempt;
    private bool $isFinalized;
    private ?StructuredOutputAttemptState $attemptState;

    public function __construct(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        //
        ?StructuredOutputAttemptList $attempts = null,
        ?StructuredOutputAttempt $currentAttempt = null,
        ?bool $isFinalized = null,
        ?StructuredOutputAttemptState $attemptState = null,
        //
        ?string $id = null,
        ?int $step = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->step = $step ?? 1;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->request = $request ?? new StructuredOutputRequest();
        $this->config = $config ?? new StructuredOutputConfig();
        $this->responseModel = $responseModel;

        $this->attempts = $attempts ?? new StructuredOutputAttemptList();
        $this->currentAttempt = $currentAttempt ?? new StructuredOutputAttempt();
        $this->isFinalized = $isFinalized ?? false;
        $this->attemptState = $attemptState;
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function request(): StructuredOutputRequest {
        return $this->request;
    }

    public function responseModel(): ?ResponseModel {
        return $this->responseModel;
    }

    public function config(): StructuredOutputConfig {
        return $this->config;
    }

    public function outputMode() : OutputMode {
        return $this->config->outputMode();
    }

    public function attempts(): StructuredOutputAttemptList {
        return $this->attempts;
    }

    public function attemptCount(): int {
        return $this->attempts->count();
    }

    public function currentAttempt(): ?StructuredOutputAttempt {
        return $this->currentAttempt;
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->currentAttempt->inferenceResponse();
    }

    public function lastFinalizedAttempt(): ?StructuredOutputAttempt {
        return $this->attempts->last();
    }

    public function maxRetriesReached(): bool {
        return $this->attempts->count() > $this->config->maxRetries();
    }

    public function isFinalized(): bool {
        return $this->isFinalized;
    }

    public function isStreamed(): bool {
        return $this->request->isStreamed();
    }

    public function attemptState(): ?StructuredOutputAttemptState {
        return $this->attemptState;
    }

    /**
     * Check if there is an active attempt in progress (sync or streaming).
     */
    public function isAttemptActive(): bool {
        return $this->attemptState !== null
            && $this->attemptState->hasMoreChunks();
    }

    /**
     * Backward-compat alias for older code/tests.
     * Prefer isAttemptActive().
     */
    public function isCurrentlyStreaming(): bool { return $this->isAttemptActive(); }

    public function usage(): Usage {
        $usage = $this->attempts->usage();
        if (!$this->currentAttempt->isFinalized()) {
            // include partial usage from current attempt (partials only)
            $partial = $this->currentAttempt->partialResponse();
            $usage = $usage->withAccumulated($partial?->usage() ?? Usage::none());
        }
        return $usage;
    }

    /**
     * Aggregate errors from finalized attempts and the current one (if present).
     */
    public function errors(): array {
        $all = [];
        foreach ($this->attempts as $attempt) {
            $all = [...$all, ...$attempt->errors()];
        }
        if ($this->currentAttempt->hasErrors()) {
            $all = array_merge($all, $this->currentAttempt->errors());
        }
        return $all;
    }

    /**
     * Errors for the in‑flight attempt (if any).
     */
    public function currentErrors(): array {
        return $this->currentAttempt->errors();
    }

    /**
     * True if the latest finalized attempt succeeded.
     */
    public function isSuccessful(): bool {
        if (!$this->currentAttempt->isFinalized()) {
            return false;
        }
        $last = $this->attempts->last();
        if ($last === null) {
            return false;
        }
        $response = $last->inferenceResponse();
        if ($last->hasErrors() || $response === null) {
            return false;
        }
        return !$response->hasFinishedWithFailure();
    }

    /**
     * True if the latest finalized attempt failed.
     */
    public function isFinalFailed(): bool {
        $last = $this->attempts->last();
        if ($last === null) {
            return false;
        }
        if ($last->hasErrors()) {
            return true;
        }
        $response = $last->inferenceResponse();
        return $response !== null && $response->hasFinishedWithFailure();
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function withStreamed(bool $isStreamed = true) : self {
        return $this->with(request: $this->request->withStreamed($isStreamed));
    }

    public function withCurrentAttempt(
        ?InferenceResponse $inferenceResponse,
        ?PartialInferenceResponse $partialInferenceResponse,
        array $errors
    ) : self {
        $existingExecution = $this->currentAttempt->inferenceExecution();
        $inferenceAttempt = new InferenceAttempt(
            response: $inferenceResponse,
            accumulatedPartial: $partialInferenceResponse,
            isFinalized: false,
            errors: $errors,
        );
        $inferenceExecution = $existingExecution->with(
            currentAttempt: $inferenceAttempt,
            isFinalized: false,
        );
        $attempt = $this->currentAttempt->with(
            inferenceExecution: $inferenceExecution,
            errors: $errors,
        );
        return $this->with(
            currentAttempt: $attempt,
            isFinalized: false,
        );
    }

    public function withFailedAttempt(
        InferenceResponse $inferenceResponse,
        ?PartialInferenceResponse $partialInferenceResponse = null,
        mixed $returnedValue = null,
        array $errors = [],
    ): self {
        if ($this->isFinalized && !$this->currentAttempt->hasErrors()) {
            throw new \LogicException('Cannot record a failed attempt on an execution that has already succeeded.');
        }
        $inferenceExecution = new InferenceExecution();
        $inferenceAttempt = InferenceAttempt::fromFailedResponse(
            response: $inferenceResponse,
            accumulatedPartial: $partialInferenceResponse,
            errors: $errors,
        );
        $inferenceExecution = $inferenceExecution->with(
            attempts: $inferenceExecution->attempts()->withNewAttempt($inferenceAttempt),
            currentAttempt: $inferenceAttempt,
            isFinalized: true,
        );
        $attempt = new StructuredOutputAttempt(
            inferenceExecution: $inferenceExecution,
            errors: $errors,
        );
        // IMPORTANT: Do not use with() here because it coalesces null values.
        // We need to explicitly reset attemptState to null to start a fresh attempt.
        return new self(
            request: $this->request,
            config: $this->config,
            responseModel: $this->responseModel,
            attempts: $this->attempts->withNewAttempt($attempt),
            currentAttempt: $attempt,
            isFinalized: false,
            attemptState: null,
            id: $this->id,
            step: $this->step + 1,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withSuccessfulAttempt(
        InferenceResponse $inferenceResponse,
        ?PartialInferenceResponse $partialInferenceResponse = null,
        mixed $returnedValue = null,
    ): self {
        if ($this->isFinalized && !$this->currentAttempt->hasErrors()) {
            throw new \LogicException('Cannot record a successful attempt on an execution that has already completed (succeeded).');
        }
        $existingExecution = $this->currentAttempt->inferenceExecution();
        $inferenceAttempt = InferenceAttempt::fromResponse($inferenceResponse);
        if ($partialInferenceResponse !== null) {
            $inferenceAttempt = $inferenceAttempt->with(accumulatedPartial: $partialInferenceResponse);
        }
        $inferenceExecution = $existingExecution->with(
            currentAttempt: $inferenceAttempt,
            isFinalized: true,
        );
        $attempt = $this->currentAttempt->with(
            inferenceExecution: $inferenceExecution,
            isFinalized: true,
            errors: [],
            output: $returnedValue,
        );
        return $this->with(
            attempts: $this->attempts->withNewAttempt($attempt),
            currentAttempt: $attempt,
            isFinalized: true, // successful attempt finalizes the execution
            attemptState: StructuredOutputAttemptState::cleared(), // Clear streaming state (exhausted)
        );
    }

    public function with(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        ?StructuredOutputAttemptList $attempts = null,
        ?StructuredOutputAttempt $currentAttempt = null,
        ?bool $isFinalized = null,
        ?StructuredOutputAttemptState $attemptState = null,
    ) : self {
        return new self(
            request: $request ?? $this->request,
            config: $config ?? $this->config,
            responseModel: $responseModel ?? $this->responseModel,
            attempts: $attempts ?? $this->attempts,
            currentAttempt: $currentAttempt ?? $this->currentAttempt,
            isFinalized: $isFinalized ?? $this->isFinalized,
            attemptState: $attemptState ?? $this->attemptState,
            //
            id: $this->id,
            step: $this->step + 1,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withAttemptState(?StructuredOutputAttemptState $state): self {
        return $this->with(attemptState: $state);
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'step' => $this->step,
            'request' => $this->request->toArray(),
            'attempts' => $this->attempts->toArray(),
            'response' => $this->inferenceResponse()?->toArray(),
            'responseModel' => $this->responseModel?->toArray(),
            'config' => $this->config->toArray(),
        ];
    }

    // INTERNAL //////////////////////////////////////////////////////////
}
