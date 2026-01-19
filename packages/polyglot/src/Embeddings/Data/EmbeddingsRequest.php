<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use InvalidArgumentException;

class EmbeddingsRequest
{
    protected array $inputs = [];
    protected array $options = [];
    protected string $model = '';
    protected ?EmbeddingsRetryPolicy $retryPolicy;

    public function __construct(
        string|array $input = [],
        array $options = [],
        string $model = '',
        ?EmbeddingsRetryPolicy $retryPolicy = null,
    ) {
        $this->inputs = match(true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        $this->model = $model;
        $this->options = $options;
        $this->assertNoRetryPolicyInOptions($this->options);
        $this->retryPolicy = $retryPolicy;

        if (empty($this->inputs)) {
            throw new InvalidArgumentException("Input data is required");
        }
    }

    // ACCESSORS

    public function inputs() : array {
        return $this->inputs;
    }

    public function options() : array {
        return $this->options;
    }

    public function model() : string {
        return $this->model;
    }

    public function retryPolicy() : ?EmbeddingsRetryPolicy {
        return $this->retryPolicy;
    }

    // TRANSFORMATIONS

    public function toArray() : array {
        return [
            'inputs' => $this->inputs,
            'options' => $this->options,
            'model' => $this->model,
        ];
    }

    private function assertNoRetryPolicyInOptions(array $options) : void {
        if (!array_key_exists('retryPolicy', $options) && !array_key_exists('retry_policy', $options)) {
            return;
        }

        throw new InvalidArgumentException('retryPolicy must be set via withRetryPolicy().');
    }
}
