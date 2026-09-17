<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Data;

use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Internal\DecisionData;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use InvalidArgumentException;
use stdClass;

final readonly class DecisionRequest
{
    private JsonContent $input;

    private ?string $model;

    private DecisionRequestId $id;

    public function __construct(
        string|JsonContent $input,
        private Questions $questions,
        ?string $model = null,
        private ?DecisionRetryPolicy $retryPolicy = null,
        private ?OperationCorrelation $telemetryCorrelation = null,
        ?DecisionRequestId $id = null,
    ) {
        $this->input = is_string($input) ? JsonContent::text($input) : $input;
        if ($questions->isEmpty()) {
            throw new InvalidArgumentException('An executable Decision request requires at least one question.');
        }
        $this->model = match (true) {
            $model === null => null,
            default => DecisionData::nonEmptyString($model, 'Decision request model'),
        };
        $this->id = $id ?? DecisionRequestId::generate();
    }

    public static function fromArray(array $data): self
    {
        DecisionData::assertKnownFields($data, ['input', 'questions', 'model'], 'Decision request');
        $input = $data['input'] ?? null;
        if (! is_string($input) && ! is_array($input) && ! $input instanceof stdClass) {
            throw new InvalidArgumentException('Decision request input must be text, an object, or a list.');
        }
        if (! is_array($data['questions'] ?? null)) {
            throw new InvalidArgumentException('Decision request questions must be a list.');
        }
        $model = $data['model'] ?? null;
        if ($model !== null && ! is_string($model)) {
            throw new InvalidArgumentException('Decision request model must be a string or null.');
        }

        return new self(
            input: JsonContent::from($input),
            questions: Questions::fromArray($data['questions']),
            model: $model,
        );
    }

    public function id(): DecisionRequestId
    {
        return $this->id;
    }

    public function input(): JsonContent
    {
        return $this->input;
    }

    public function questions(): Questions
    {
        return $this->questions;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function hasModel(): bool
    {
        return $this->model !== null;
    }

    public function retryPolicy(): ?DecisionRetryPolicy
    {
        return $this->retryPolicy;
    }

    public function telemetryCorrelation(): ?OperationCorrelation
    {
        return $this->telemetryCorrelation;
    }

    public function withInput(string|JsonContent $input): self
    {
        return new self(
            $input,
            $this->questions,
            $this->model,
            $this->retryPolicy,
            $this->telemetryCorrelation,
            $this->id,
        );
    }

    public function withQuestions(Questions $questions): self
    {
        return new self(
            $this->input,
            $questions,
            $this->model,
            $this->retryPolicy,
            $this->telemetryCorrelation,
            $this->id,
        );
    }

    public function withModel(string $model): self
    {
        return new self(
            $this->input,
            $this->questions,
            $model,
            $this->retryPolicy,
            $this->telemetryCorrelation,
            $this->id,
        );
    }

    public function withRetryPolicy(DecisionRetryPolicy $retryPolicy): self
    {
        return new self(
            $this->input,
            $this->questions,
            $this->model,
            $retryPolicy,
            $this->telemetryCorrelation,
            $this->id,
        );
    }

    public function withTelemetryCorrelation(OperationCorrelation $correlation): self
    {
        return new self(
            $this->input,
            $this->questions,
            $this->model,
            $this->retryPolicy,
            $correlation,
            $this->id,
        );
    }

    public function toArray(): array
    {
        return [
            'input' => $this->input->value(),
            'questions' => $this->questions->toArray(),
            ...($this->model === null ? [] : ['model' => $this->model]),
        ];
    }
}
