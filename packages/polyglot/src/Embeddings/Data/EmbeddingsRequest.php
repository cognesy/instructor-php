<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

use InvalidArgumentException;

class EmbeddingsRequest
{
    protected array $inputs = [];
    protected array $options = [];
    protected string $model = '';

    public function __construct(
        string|array $input = [],
        array $options = [],
        string $model = '',
    ) {
        $this->inputs = match(true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        $this->model = $model;
        $this->options = $options;

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

    // TRANSFORMATIONS

    public function toArray() : array {
        return [
            'inputs' => $this->inputs,
            'options' => $this->options,
            'model' => $this->model,
        ];
    }
}