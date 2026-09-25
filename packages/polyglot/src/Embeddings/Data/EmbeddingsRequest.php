<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Embeddings\Models\EmbeddingModel;
use InvalidArgumentException;

final class EmbeddingsRequest
{
    protected array $inputs = [];

    protected array $options = [];

    protected string $model = '';

    protected ?EmbeddingsRetryPolicy $retryPolicy;

    protected ?EmbeddingModel $modelProfile;

    public function __construct(
        string|array $input = [],
        array $options = [],
        string $model = '',
        ?EmbeddingsRetryPolicy $retryPolicy = null,
        ?EmbeddingModel $modelProfile = null,
    ) {
        $this->inputs = match (true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        $this->model = $model;
        $this->options = $options;
        $this->assertNoRetryPolicyInOptions($this->options);
        $this->retryPolicy = $retryPolicy;
        $this->modelProfile = $modelProfile;
    }

    public static function empty(): self
    {
        return new self;
    }

    // ACCESSORS

    public function inputs(): array
    {
        return $this->inputs;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function retryPolicy(): ?EmbeddingsRetryPolicy
    {
        return $this->retryPolicy;
    }

    public function modelProfile(): ?EmbeddingModel
    {
        return $this->modelProfile;
    }

    public function hasInputs(): bool
    {
        return $this->inputs !== [];
    }

    public function withInputs(string|array $input): self
    {
        return new self(
            input: $input,
            options: $this->options,
            model: $this->model,
            retryPolicy: $this->retryPolicy,
            modelProfile: $this->modelProfile,
        );
    }

    public function withModel(string $model): self
    {
        return new self(
            input: $this->inputs,
            options: $this->options,
            model: $model,
            retryPolicy: $this->retryPolicy,
            modelProfile: null,
        );
    }

    public function withOptions(array $options): self
    {
        return new self(
            input: $this->inputs,
            options: $options,
            model: $this->model,
            retryPolicy: $this->retryPolicy,
            modelProfile: $this->modelProfile,
        );
    }

    public function withRetryPolicy(?EmbeddingsRetryPolicy $retryPolicy): self
    {
        return new self(
            input: $this->inputs,
            options: $this->options,
            model: $this->model,
            retryPolicy: $retryPolicy,
            modelProfile: $this->modelProfile,
        );
    }

    public function withModelProfile(EmbeddingModel $modelProfile): self
    {
        return new self(
            input: $this->inputs,
            options: $this->options,
            model: $this->model,
            retryPolicy: $this->retryPolicy,
            modelProfile: $modelProfile,
        );
    }

    // TRANSFORMATIONS

    public function toArray(): array
    {
        return [
            'inputs' => $this->inputs,
            'options' => $this->options,
            'model' => $this->model,
        ];
    }

    private function assertNoRetryPolicyInOptions(array $options): void
    {
        if (! array_key_exists('retryPolicy', $options) && ! array_key_exists('retry_policy', $options)) {
            return;
        }

        throw new InvalidArgumentException('retryPolicy must be set via withRetryPolicy().');
    }
}
