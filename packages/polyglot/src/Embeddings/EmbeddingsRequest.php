<?php

namespace Cognesy\Polyglot\Embeddings;

class EmbeddingsRequest
{
    protected array $inputs = [];
    protected array $options = [];
    protected string $model = '';

    public function __construct(
        string|array $input = [],
        array $options = [],
        string $model = ''
    ) {
        $this->inputs = match(true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        $this->model = $model;
        $this->options = $options;
    }

    // MUTATORS

    public function withAnyInput(array|string $input) : static {
        $this->inputs = match(true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        return $this;
    }

    public function withInput(string $input) : static {
        $this->inputs = [$input];
        return $this;
    }

    public function withInputs(array $inputs) : static {
        $this->inputs = $inputs;
        return $this;
    }

    public function withOptions(array $options) : static {
        $this->options = $options;
        return $this;
    }

    public function withModel(string $model) : static {
        $this->model = $model;
        return $this;
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