<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Traits;

trait HandlesFluentMethods
{
    private string|array $inputs = [];
    private string $model = '';
    private array $options = [];

    public function withInputs(string|array $input) : self {
        $this->inputs = $input;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : self {
        $this->model = $model;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given options.
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options) : self {
        $this->options = $options;
        return $this;
    }
}