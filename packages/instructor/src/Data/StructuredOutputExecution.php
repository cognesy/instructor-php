<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Collections\StructuredOutputAttempts;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

final readonly class StructuredOutputExecution
{
    private string $id;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private StructuredOutputRequest $request;
    private StructuredOutputConfig $config;
    private ?ResponseModel $responseModel;

    private StructuredOutputAttempts $attempts;
    private StructuredOutputAttempt $currentAttempt;
    private bool $isFinalized;

    public function __construct(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        //
        ?StructuredOutputAttempts $attempts = null,
        ?StructuredOutputAttempt $currentAttempt = null,
        ?bool $isFinalized = null,
        //
        ?string $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->request = $request ?? new StructuredOutputRequest();
        $this->config = $config ?? new StructuredOutputConfig();
        $this->responseModel = $responseModel;

        $this->attempts = $attempts ?? new StructuredOutputAttempts();
        $this->currentAttempt = $currentAttempt ?? new StructuredOutputAttempt();
        $this->isFinalized = $isFinalized ?? false;
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function request(): StructuredOutputRequest {
        return $this->request;
    }

    public function responseModel(): ResponseModel {
        return $this->responseModel;
    }

    public function config(): StructuredOutputConfig {
        return $this->config;
    }

    public function outputMode() : OutputMode {
        return $this->config->outputMode();
    }

    public function attempts(): StructuredOutputAttempts {
        return $this->attempts;
    }

    public function attemptCount(): int {
        return $this->attempts->count();
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->currentAttempt->inferenceResponse();
    }

    public function lastResponse(): ?StructuredOutputAttempt {
        return $this->attempts->last();
    }

    public function maxRetriesReached(): bool {
        return $this->attempts->count() > $this->config->maxRetries();
    }

    public function isFinalized(): bool {
        return $this->isFinalized;
    }

    public function usage(): \Cognesy\Polyglot\Inference\Data\Usage {
        return $this->attempts->usage();
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        ?StructuredOutputAttempts $attempts = null,
        ?StructuredOutputAttempt $currentAttempt = null,
        ?bool $isFinalized = null,
    ) : self {
        return new self(
            request: $request ?? $this->request,
            config: $config ?? $this->config,
            responseModel: $responseModel ?? $this->responseModel,
            attempts: $attempts ?? $this->attempts,
            currentAttempt: $currentAttempt ?? $this->currentAttempt,
            isFinalized: $isFinalized ?? $this->isFinalized,
            //
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withStreamed(bool $isStreamed = true) : self {
        return $this->with(request: $this->request->withStreamed($isStreamed));
    }

    public function withCurrentAttempt(
        Messages $messages,
        InferenceResponse $inferenceResponse,
        PartialInferenceResponseList $partialInferenceResponses,
        array $errors
    ) : self {
        $inferenceExecution = new InferenceExecution();
        $inferenceAttempt = new InferenceAttempt(
            response: $inferenceResponse,
            partialResponses: $partialInferenceResponses,
            isFinalized: false,
            errors: $errors,
        );
        $inferenceExecution = $inferenceExecution->with(
            attempts: $inferenceExecution->attempts()->withNewAttempt($inferenceAttempt),
            currentAttempt: $inferenceAttempt,
            isFinalized: false,
        );

        $attempt = new StructuredOutputAttempt(
            inferenceExecution: $inferenceExecution,
            isFinalized: false,
            errors: $errors,
        );

        return $this->with(
            attempts: $this->attempts->withNewAttempt($attempt),
            currentAttempt: $attempt,
            isFinalized: false,
        );
    }

    public function withFailedAttempt(
        Messages $messages,
        InferenceResponse $inferenceResponse,
        ?PartialInferenceResponseList $partialInferenceResponses = null,
        mixed $returnedValue = null,
        array $errors = [],
    ): self {
        if ($this->isFinalized && !$this->currentAttempt->hasErrors()) {
            throw new \LogicException('Cannot record a failed attempt on an execution that has already succeeded.');
        }
        $inferenceExecution = new InferenceExecution();
        $inferenceAttempt = InferenceAttempt::fromFailedResponse(
            response: $inferenceResponse,
            partialResponses: $partialInferenceResponses,
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
        return $this->with(
            attempts: $this->attempts->withNewAttempt($attempt),
            currentAttempt: $attempt,
            isFinalized: false, // failed attempt doesn't finalize the execution
        );
    }

    public function withSuccessfulAttempt(
        Messages $messages,
        InferenceResponse $inferenceResponse,
        ?PartialInferenceResponseList $partialInferenceResponses = null,
        mixed $returnedValue = null,
    ): self {
        if ($this->isFinalized && !$this->currentAttempt->hasErrors()) {
            throw new \LogicException('Cannot record a successful attempt on an execution that has already succeeded.');
        }
        $inferenceExecution = new InferenceExecution();
        $inferenceAttempt = InferenceAttempt::fromResponse($inferenceResponse);
        if ($partialInferenceResponses !== null && !$partialInferenceResponses->isEmpty()) {
            $inferenceAttempt = $inferenceAttempt->with(partialResponses: $partialInferenceResponses);
        }
        $inferenceExecution = $inferenceExecution->with(
            attempts: $inferenceExecution->attempts()->withNewAttempt($inferenceAttempt),
            currentAttempt: $inferenceAttempt,
            isFinalized: true,
        );

        $attempt = new StructuredOutputAttempt(
            inferenceExecution: $inferenceExecution,
            isFinalized: true,
            output: $returnedValue,
        );
        return $this->with(
            attempts: $this->attempts->withNewAttempt($attempt),
            currentAttempt: $attempt,
            isFinalized: true, // successful attempt finalizes the execution
        );
    }

    // SERIALIZATION /////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'request' => $this->request->toArray(),
            'attempts' => $this->attempts->toArray(),
            'response' => $this->response?->toArray(),
            'responseModel' => $this->responseModel?->toArray(),
            'config' => $this->config->toArray(),
        ];
    }
}
