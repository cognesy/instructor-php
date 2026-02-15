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
        $this->inputs = $input;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : static {
        $this->model = $model;
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given options.
     * @param array $options
     * @return $this
     */
    public function withOptions(array $options) : static {
        $this->options = $options;
        return $this;
    }

    public function withRetryPolicy(EmbeddingsRetryPolicy $retryPolicy) : static {
        $this->retryPolicy = $retryPolicy;
        return $this;
    }
}
