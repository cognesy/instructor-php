<?php

namespace Cognesy\Polyglot\Embeddings;

class EmbeddingsRequest
{
    protected array $inputs = [];
    protected array $options = [];

    public function __construct(
        string|array $input = [],
        array $options = [],
    ) {
        $this->inputs = match(true) {
            is_string($input) => [$input],
            is_array($input) => $input,
            default => []
        };
        $this->options = $options;
    }

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

    public function inputs() : array {
        return $this->inputs;
    }

    public function options() : array {
        return $this->options;
    }

    public function toArray() : array {
        return [
            'inputs' => $this->inputs,
            'options' => $this->options,
        ];
    }
}