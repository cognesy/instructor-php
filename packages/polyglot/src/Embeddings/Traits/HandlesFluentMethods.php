<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Traits;

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;

trait HandlesFluentMethods
{
    private string|array $inputs = [];
    private string $model = '';
    private array $options = [];
    private ?EmbeddingsRetryPolicy $retryPolicy = null;

    public function withInputs(string|array $input) : static {
        $copy = clone $this;
        $copy->inputs = $input;
        return $copy;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : static {
        $copy = clone $this;
        $copy->model = $model;
        return $copy;
    }

    /**
     * Configures the Embeddings instance with the given options.
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options) : static {
        $copy = clone $this;
        $copy->options = $options;
        return $copy;
    }

    public function withRetryPolicy(EmbeddingsRetryPolicy $retryPolicy) : static {
        $copy = clone $this;
        $copy->retryPolicy = $retryPolicy;
        return $copy;
    }
}
