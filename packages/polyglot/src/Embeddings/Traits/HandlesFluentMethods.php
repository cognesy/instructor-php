<?php

namespace Cognesy\Polyglot\Embeddings\Traits;

trait HandlesFluentMethods
{
    public function withInput(string|array $input) : self {
        $this->request->withAnyInput($input);
        return $this;
    }

    /**
     * Configures the Embeddings instance with the given model name.
     * @param string $model
     * @return $this
     */
    public function withModel(string $model) : self {
        $this->request->withModel($model);
        return $this;
    }
}