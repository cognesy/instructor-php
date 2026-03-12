<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Collections\StructuredOutputAttemptList;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Utils\Profiler\TracksObjectCreation;
use DateTimeImmutable;

final readonly class StructuredOutputExecution
{
    use TracksObjectCreation;

    private StructuredOutputExecutionId $id;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private int $step;

    private StructuredOutputRequest $request;
    private StructuredOutputConfig $config;
    private ?ResponseModel $responseModel;

    private StructuredOutputAttemptList $attemptHistory;
    private ?StructuredOutputAttempt $activeAttempt;
    private ExecutionStatus $status;

    public function __construct(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        ?StructuredOutputAttemptList $attemptHistory = null,
        ?StructuredOutputAttempt $activeAttempt = null,
        ?ExecutionStatus $status = null,
        ?StructuredOutputExecutionId $id = null,
        ?int $step = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->id = $id ?? StructuredOutputExecutionId::generate();
        $this->step = $step ?? 1;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;

        $this->request = $request ?? new StructuredOutputRequest();
        $this->config = $config ?? new StructuredOutputConfig();
        $this->responseModel = $responseModel;
        $this->attemptHistory = $attemptHistory ?? StructuredOutputAttemptList::empty();
        $this->activeAttempt = $activeAttempt;
        $this->status = $status ?? $this->resolveStatus(
            activeAttempt: $this->activeAttempt,
            attemptHistory: $this->attemptHistory,
        );
        $this->assertInvariants();
        $this->trackObjectCreation();
    }

    public function request(): StructuredOutputRequest
    {
        return $this->request;
    }

    public function id(): StructuredOutputExecutionId
    {
        return $this->id;
    }

    public function responseModel(): ?ResponseModel
    {
        return $this->responseModel;
    }

    public function config(): StructuredOutputConfig
    {
        return $this->config;
    }

    public function outputMode(): OutputMode
    {
        return $this->config->outputMode();
    }

    public function status(): ExecutionStatus
    {
        return $this->status;
    }

    public function attempts(): StructuredOutputAttemptList
    {
        return $this->attemptHistory;
    }

    public function attemptHistory(): StructuredOutputAttemptList
    {
        return $this->attemptHistory;
    }

    public function attemptCount(): int
    {
        return $this->attemptHistory->count();
    }

    public function activeAttempt(): ?StructuredOutputAttempt
    {
        return $this->activeAttempt;
    }

    public function inferenceResponse(): ?InferenceResponse
    {
        return $this->activeAttempt?->inferenceResponse()
            ?? $this->lastFinalizedAttempt()?->inferenceResponse();
    }

    public function output(): mixed
    {
        return $this->activeAttempt?->output()
            ?? $this->lastFinalizedAttempt()?->output();
    }

    public function hasOutput(): bool
    {
        return $this->output() !== null;
    }

    public function lastFinalizedAttempt(): ?StructuredOutputAttempt
    {
        return $this->attemptHistory->last();
    }

    public function maxRetriesReached(): bool
    {
        return $this->attemptCount() > $this->config->maxRetries();
    }

    public function isFinalized(): bool
    {
        return $this->status->isTerminal();
    }

    public function isStreamed(): bool
    {
        return $this->request->isStreamed();
    }

    public function isAttemptActive(): bool
    {
        return $this->activeAttempt !== null;
    }

    public function usage(): InferenceUsage
    {
        $usage = $this->attemptHistory->usage();
        return match (true) {
            $this->activeAttempt === null => $usage,
            default => $usage->withAccumulated($this->activeAttempt->usage()),
        };
    }

    public function errors(): array
    {
        $all = [];
        foreach ($this->attemptHistory as $attempt) {
            $all = [...$all, ...$attempt->errors()];
        }

        return match (true) {
            $this->activeAttempt?->hasErrors() ?? false => array_merge($all, $this->activeAttempt->errors()),
            default => $all,
        };
    }

    public function currentErrors(): array
    {
        return $this->activeAttempt?->errors() ?? [];
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isFinalFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function withStreamed(bool $isStreamed = true): self
    {
        return $this->with(request: $this->request->withStreamed($isStreamed));
    }

    public function withStartedAttempt(): self
    {
        return match (true) {
            $this->isFinalized() => throw new \LogicException('Cannot start a new attempt on a terminal execution.'),
            $this->activeAttempt !== null => $this,
            default => $this->copy(
                request: $this->request,
                config: $this->config,
                responseModel: $this->responseModel,
                attemptHistory: $this->attemptHistory,
                activeAttempt: new StructuredOutputAttempt(),
                status: ExecutionStatus::Running,
            ),
        };
    }

    public function withFailedAttempt(
        InferenceResponse $inferenceResponse,
        mixed $returnedValue = null,
        array $errors = [],
    ): self {
        if ($this->isFinalized()) {
            throw new \LogicException('Cannot record a failed attempt on a terminal execution.');
        }

        $attempt = ($this->activeAttempt ?? new StructuredOutputAttempt())->withCompletion(
            inferenceResponse: $inferenceResponse,
            errors: $errors,
            output: $returnedValue,
        );
        $attemptHistory = $this->attemptHistory->withNewAttempt($attempt);
        $status = match (true) {
            $attemptHistory->count() > $this->config->maxRetries() => ExecutionStatus::Failed,
            default => ExecutionStatus::Pending,
        };

        return $this->copy(
            request: $this->request,
            config: $this->config,
            responseModel: $this->responseModel,
            attemptHistory: $attemptHistory,
            activeAttempt: null,
            status: $status,
        );
    }

    public function withSuccessfulAttempt(
        InferenceResponse $inferenceResponse,
        mixed $returnedValue = null,
    ): self {
        if ($this->isFinalized()) {
            throw new \LogicException('Cannot record a successful attempt on a terminal execution.');
        }

        $attempt = ($this->activeAttempt ?? new StructuredOutputAttempt())->withCompletion(
            inferenceResponse: $inferenceResponse,
            errors: [],
            output: $returnedValue,
        );

        return $this->copy(
            request: $this->request,
            config: $this->config,
            responseModel: $this->responseModel,
            attemptHistory: $this->attemptHistory->withNewAttempt($attempt),
            activeAttempt: null,
            status: ExecutionStatus::Succeeded,
        );
    }

    public function with(
        ?StructuredOutputRequest $request = null,
        ?StructuredOutputConfig $config = null,
        ?ResponseModel $responseModel = null,
        ?StructuredOutputAttemptList $attemptHistory = null,
        ?StructuredOutputAttempt $activeAttempt = null,
        ?ExecutionStatus $status = null,
    ): self {
        return $this->copy(
            request: $request ?? $this->request,
            config: $config ?? $this->config,
            responseModel: $responseModel ?? $this->responseModel,
            attemptHistory: $attemptHistory ?? $this->attemptHistory,
            activeAttempt: $activeAttempt ?? $this->activeAttempt,
            status: $status ?? $this->status,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'step' => $this->step,
            'status' => $this->status->value,
            'request' => $this->request->toArray(),
            'attempts' => $this->attemptHistory->toArray(),
            'activeAttempt' => $this->activeAttempt?->toArray(),
            'response' => $this->inferenceResponse()?->toArray(),
            'responseModel' => $this->responseModel?->toArray(),
            'config' => $this->config->toArray(),
        ];
    }

    private function copy(
        StructuredOutputRequest $request,
        StructuredOutputConfig $config,
        ?ResponseModel $responseModel,
        StructuredOutputAttemptList $attemptHistory,
        ?StructuredOutputAttempt $activeAttempt,
        ExecutionStatus $status,
    ): self {
        return new self(
            request: $request,
            config: $config,
            responseModel: $responseModel,
            attemptHistory: $attemptHistory,
            activeAttempt: $activeAttempt,
            status: $status,
            id: $this->id,
            step: $this->step + 1,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    private function resolveStatus(
        ?StructuredOutputAttempt $activeAttempt = null,
        ?StructuredOutputAttemptList $attemptHistory = null,
    ): ExecutionStatus {
        $activeAttempt = $activeAttempt ?? $this->activeAttempt;
        $attemptHistory = $attemptHistory ?? $this->attemptHistory;
        $lastAttempt = $attemptHistory->last();

        return match (true) {
            $activeAttempt !== null => ExecutionStatus::Running,
            $lastAttempt === null => ExecutionStatus::Pending,
            $attemptHistory->count() > $this->config->maxRetries() => ExecutionStatus::Failed,
            default => ExecutionStatus::Pending,
        };
    }

    private function assertInvariants(): void
    {
        if ($this->activeAttempt?->isFinalized() ?? false) {
            throw new \LogicException('Active attempt cannot be finalized.');
        }

        foreach ($this->attemptHistory as $attempt) {
            if (!$attempt->isFinalized()) {
                throw new \LogicException('Attempt history can only contain finalized attempts.');
            }
        }

        if ($this->status->isTerminal() && $this->activeAttempt !== null) {
            throw new \LogicException('Terminal execution cannot have an active attempt.');
        }
    }
}
