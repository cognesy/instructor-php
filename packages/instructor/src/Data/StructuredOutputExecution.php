<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Config\StructuredOutputConfig;
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
    private StructuredOutputAttempts $attempts;
    private StructuredOutputConfig $config;
    private ?ResponseModel $responseModel;
    private ?StructuredOutputResponse $response;

    public function __construct(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        //
        ?StructuredOutputAttempts $attempts = null,
        ?StructuredOutputResponse $response = null,
        //
        ?string $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->request = $request ?? new StructuredOutputRequest();
        $this->attempts = $attempts ?? new StructuredOutputAttempts();
        $this->responseModel = $responseModel;
        $this->response = $response;
        $this->config = $config ?? new StructuredOutputConfig();
    }

    // ACCESSORS /////////////////////////////////////////////////////////

    public function request(): StructuredOutputRequest {
        return $this->request;
    }

    public function responseModel(): ResponseModel {
        return $this->responseModel;
    }

    public function attempts(): StructuredOutputAttempts {
        return $this->attempts;
    }

    public function response(): ?StructuredOutputResponse {
        return $this->response;
    }

    public function inferenceResponse(): ?InferenceResponse {
        return $this->attempts->response()?->inferenceResponse();
    }

    public function config(): StructuredOutputConfig {
        return $this->config;
    }

    public function outputMode() : OutputMode {
        return $this->config->outputMode();
    }

    public function hasSucceeded(): bool {
        return !is_null($this->response);
    }

    public function attemptCount(): int {
        return $this->attempts->attemptCount();
    }

    public function lastFailedResponse(): ?StructuredOutputAttempt {
        return $this->attempts->lastFailedResponse();
    }

    public function hasLastResponseFailed(): bool {
        return $this->attempts->hasLastResponseFailed();
    }

    public function maxRetriesReached(): bool {
        return $this->attempts->attemptCount() > $this->config->maxRetries();
    }

    // MUTATORS //////////////////////////////////////////////////////////

    public function with(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        ?StructuredOutputAttempts $attempts = null,
        ?StructuredOutputResponse $response = null,
    ) : self {
        return new self(
            request: $request ?? $this->request,
            config: $config ?? $this->config,
            responseModel: $responseModel ?? $this->responseModel,
            attempts: $attempts ?? $this->attempts,
            response: $response ?? $this->response,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withStreamed(bool $isStreamed = true) : self {
        return $this->with(
            request: $this->request->withStreamed($isStreamed), // TODO: maybe this should be set at execution level?
        );
    }

    public function withResponseModel(ResponseModel $model): self {
        return $this->with(responseModel: $model);
    }

    public function withFailedAttempt(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        array $errors = [],
    ): self {
        if (!is_null($this->response)) {
            throw new \LogicException('Cannot record a failed attempt on an execution that has already succeeded.');
        }
        $attempts = $this->attempts->withNewFailedAttempt(
            messages: $messages,
            inferenceResponse: $inferenceResponse,
            partialInferenceResponses: $partialInferenceResponses,
            errors: $errors,
        );
        return $this->with(attempts: $attempts);
    }

    public function withSuccessfulAttempt(
        array $messages,
        InferenceResponse $inferenceResponse,
        array $partialInferenceResponses = [],
        mixed $returnedValue = null,
    ): self {
        if (!is_null($this->response)) {
            throw new \LogicException('Cannot record a successful attempt on an execution that has already succeeded.');
        }

        $attempts = $this->attempts->withSuccessfulResponse(
            messages: $messages,
            inferenceResponse: $inferenceResponse,
            partialInferenceResponses: $partialInferenceResponses,
        );
        $response = new StructuredOutputResponse(
            raw: $inferenceResponse->content(),
            decoded: $inferenceResponse->findJsonData()->toArray(),
            deserialized: $returnedValue,
        );
        return $this->with(
            attempts: $attempts,
            response: $response,
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
        ];
    }
}